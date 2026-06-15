<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;

/**
 * Suggestions de rotation flotte (GAP-29).
 * Pour un voyage à affecter, propose les bus optimaux selon :
 *  - dispo (pas de conflit)
 *  - position attendue (dernier voyage finit à l'origine de ce voyage)
 *  - maintenance imminente (CT, assurance qui expirent)
 *  - kilométrage (équilibrage)
 */
final class FleetRotationService
{
    /**
     * Suggère les bus optimaux pour un voyage.
     *
     * @return array tableau de ['bus' => array, 'score' => float, 'reasons' => array]
     */
    public function suggestForTrip(int $tripId, int $limit = 5): array
    {
        $trip = Database::selectOne(
            "SELECT tr.*, l.departure_city_id, l.arrival_city_id
             FROM trips tr LEFT JOIN bus_lines l ON l.id = tr.line_id
             WHERE tr.id = ?", [$tripId]
        );
        if (!$trip) return [];

        // Bus disponibles (sans conflit horaire)
        $tripStart = $trip['trip_date'] . ' ' . $trip['departure_scheduled'];
        $tripEnd   = $trip['trip_date'] . ' ' . ($trip['arrival_scheduled'] ?? $trip['departure_scheduled']);

        $candidates = Database::select(
            "SELECT b.id, b.code, b.plate, b.brand, b.model, b.seats,
                    b.km_current, b.tech_control_expiry, b.insurance_expiry,
                    -- dernier voyage du bus
                    (SELECT MAX(CONCAT(trip_date, ' ', arrival_scheduled))
                       FROM trips WHERE bus_id = b.id AND id != ? AND status != 'annule'
                         AND CONCAT(trip_date, ' ', arrival_scheduled) <= ?) AS last_trip_end
             FROM buses b
             WHERE (b.deleted_at IS NULL OR b.deleted_at IS NULL)
               AND (b.status IS NULL OR b.status NOT IN ('hors_service','reforme'))
               AND NOT EXISTS (
                 SELECT 1 FROM trips WHERE bus_id = b.id AND id != ?
                   AND status NOT IN ('annule','cloture')
                   AND CONCAT(trip_date, ' ', departure_scheduled) < ?
                   AND CONCAT(trip_date, ' ', COALESCE(arrival_scheduled, departure_scheduled)) > ?
               )
             ORDER BY b.code",
            [$tripId, $tripStart, $tripId, $tripEnd, $tripStart]
        );

        $scored = [];
        foreach ($candidates as $b) {
            $score = 100.0;
            $reasons = [];

            // Pénalité si CT expire dans les 7 jours
            if ($b['tech_control_expiry']) {
                $days = (strtotime($b['tech_control_expiry']) - time()) / 86400;
                if ($days < 7) {
                    $score -= 30;
                    $reasons[] = "Contrôle technique expire dans " . round($days) . " j";
                } elseif ($days < 30) {
                    $score -= 10;
                    $reasons[] = "CT à renouveler bientôt";
                }
            }
            if ($b['insurance_expiry']) {
                $days = (strtotime($b['insurance_expiry']) - time()) / 86400;
                if ($days < 7) {
                    $score -= 25;
                    $reasons[] = "Assurance expire dans " . round($days) . " j";
                }
            }

            // Bonus si déjà sur la même ligne / dans la zone
            if (!empty($b['last_trip_end'])) {
                $delaySinceLast = max(0, (strtotime($tripStart) - strtotime((string)$b['last_trip_end'])) / 3600);
                if ($delaySinceLast < 1) {
                    $score -= 20;
                    $reasons[] = "Pas assez de temps depuis dernier voyage";
                } elseif ($delaySinceLast < 4) {
                    $score += 15;
                    $reasons[] = "Bus déjà positionné";
                }
            } else {
                $score += 5;
                $reasons[] = "Bus disponible (pas de voyage récent)";
            }

            // Équilibrage km : bonus pour les moins utilisés
            // (on calcule la moyenne des km du parc)
            $avgKm = (float)(Database::selectOne("SELECT AVG(km_current) AS m FROM buses WHERE km_current > 0")['m'] ?? 0);
            if ($avgKm > 0 && (int)$b['km_current'] < $avgKm) {
                $score += 10;
                $reasons[] = "Sous-utilisation kilométrique";
            }

            $scored[] = [
                'bus'     => $b,
                'score'   => round($score, 1),
                'reasons' => $reasons,
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }
}
