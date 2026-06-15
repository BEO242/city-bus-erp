<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * Tarification par origine-destination + inventaire siège par segment.
 *
 * Permet de :
 *  - résoudre le prix d'un trajet partiel selon la grille O-D
 *  - vérifier qu'un siège est libre sur un segment donné (un siège peut être
 *    pris sur Brazza→Dolisie ET libre sur Dolisie→Pointe-Noire pour revente)
 */
final class OdPricingService
{
    /**
     * Récupère le prix pour un couple O-D, retombe sur le tarif global si
     * aucune grille n'est définie.
     */
    public function resolvePrice(
        int $lineId,
        ?int $fromStopId,
        ?int $toStopId,
        string $ticketType = 'finale',
        string $passengerCategory = 'adulte',
        string $travelClass = 'standard'
    ): ?int {
        if (!$fromStopId || !$toStopId || !Setting::getBool('pricing.od_enabled', true)) {
            return null;
        }
        $row = Database::selectOne(
            "SELECT price_fcfa FROM od_pricing
              WHERE line_id = ? AND from_stop_id = ? AND to_stop_id = ?
                AND ticket_type = ? AND passenger_category = ? AND travel_class = ?
                AND is_active = 1
                AND (valid_from  IS NULL OR valid_from  <= CURDATE())
                AND (valid_until IS NULL OR valid_until >= CURDATE())
              LIMIT 1",
            [$lineId, $fromStopId, $toStopId, $ticketType, $passengerCategory, $travelClass]
        );
        return $row ? (int)$row['price_fcfa'] : null;
    }

    /**
     * Génère une matrice O-D vierge basée sur la liste des arrêts.
     * Calcule un prix proportionnel au km parcouru sur la base d'un prix total ligne.
     */
    public function generateMatrix(int $lineId, int $totalLinePrice, string $ticketType = 'finale', string $passengerCategory = 'adulte'): int
    {
        $stops = Database::select(
            "SELECT id, position, distance_from_start_km
             FROM stops WHERE line_id = ? ORDER BY position ASC",
            [$lineId]
        );
        if (count($stops) < 2) return 0;

        $totalDistance = max(1.0, (float)end($stops)['distance_from_start_km']);
        reset($stops);
        $count = 0;

        foreach ($stops as $i => $from) {
            for ($j = $i + 1; $j < count($stops); $j++) {
                $to = $stops[$j];
                $segDistance = (float)$to['distance_from_start_km'] - (float)$from['distance_from_start_km'];
                if ($segDistance <= 0) continue;
                $segPrice = (int)round($totalLinePrice * $segDistance / $totalDistance);
                Database::execute(
                    "INSERT INTO od_pricing
                        (line_id, from_stop_id, to_stop_id, ticket_type, passenger_category, travel_class, price_fcfa, distance_km, is_active)
                     VALUES (?,?,?,?,?, 'standard', ?, ?, 1)
                     ON DUPLICATE KEY UPDATE price_fcfa = VALUES(price_fcfa), distance_km = VALUES(distance_km), updated_at = NOW()",
                    [$lineId, (int)$from['id'], (int)$to['id'], $ticketType, $passengerCategory, $segPrice, $segDistance]
                );
                $count++;
            }
        }
        return $count;
    }

    /** Liste la matrice complète d'une ligne pour édition. */
    public function matrix(int $lineId): array
    {
        return Database::select(
            "SELECT op.*, sf.name AS from_name, sf.position AS from_pos,
                          st.name AS to_name,   st.position AS to_pos
             FROM od_pricing op
             JOIN stops sf ON sf.id = op.from_stop_id
             JOIN stops st ON st.id = op.to_stop_id
             WHERE op.line_id = ?
             ORDER BY sf.position, st.position",
            [$lineId]
        );
    }

    /**
     * Vérifie qu'un siège est disponible sur le segment [fromStopId, toStopId]
     * d'un voyage donné (revente possible si paramètre activé).
     *
     * Retourne true si :
     *  - le siège n'est utilisé par aucun ticket actif chevauchant ce segment.
     */
    public function isSeatAvailable(int $tripId, string $seatNumber, ?int $fromStopId, ?int $toStopId): bool
    {
        if (!Setting::getBool('pricing.od_resale_seat', true) || !$fromStopId || !$toStopId) {
            // Sans revente : un seul ticket par siège
            $row = Database::selectOne(
                "SELECT COUNT(*) AS c FROM tickets
                  WHERE trip_id = ? AND seat_number = ? AND deleted_at IS NULL
                    AND status IN ('emis','controle','utilise','embarque')",
                [$tripId, $seatNumber]
            );
            return ((int)($row['c'] ?? 0)) === 0;
        }

        // Avec revente : vérifier le chevauchement par position d'arrêt
        $segment = $this->stopPositions($fromStopId, $toStopId);
        if (!$segment) return true;

        $existing = Database::select(
            "SELECT t.boarding_stop_id, t.alighting_stop_id,
                    sf.position AS from_pos, st.position AS to_pos
             FROM tickets t
             LEFT JOIN stops sf ON sf.id = t.boarding_stop_id
             LEFT JOIN stops st ON st.id = t.alighting_stop_id
             WHERE t.trip_id = ? AND t.seat_number = ? AND t.deleted_at IS NULL
               AND t.status IN ('emis','controle','utilise','embarque')",
            [$tripId, $seatNumber]
        );
        foreach ($existing as $e) {
            // Si un ticket sans arrêts précis : on considère qu'il bloque tout
            if ($e['from_pos'] === null || $e['to_pos'] === null) return false;
            // Chevauchement [a,b] ∩ [c,d] non vide
            if (max($e['from_pos'], $segment['from']) < min($e['to_pos'], $segment['to'])) {
                return false;
            }
        }
        return true;
    }

    private function stopPositions(int $fromStopId, int $toStopId): ?array
    {
        $row = Database::selectOne(
            "SELECT (SELECT position FROM stops WHERE id = ?) AS f,
                    (SELECT position FROM stops WHERE id = ?) AS t",
            [$fromStopId, $toStopId]
        );
        if (!$row || $row['f'] === null || $row['t'] === null) return null;
        return ['from' => (int)$row['f'], 'to' => (int)$row['t']];
    }
}
