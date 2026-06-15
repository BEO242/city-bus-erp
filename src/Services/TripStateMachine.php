<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Models\AuditLog;
use CityBus\Models\Trip;

/**
 * State machine — transitions validées + sub-statuts.
 *
 * Sub-statuts (column trips.sub_status) :
 *   at_origin / boarding / departed / between_stops / at_stop / arrived / completed / closed
 *
 * Règles de transition :
 *   planifie     → valide, annule
 *   valide       → embarquement, en_route, annule
 *   embarquement → en_route, retourne, annule, incident
 *   en_route     → arrive, incident, retourne
 *   arrive       → cloture, litige
 *   incident     → en_route, retourne, cloture, annule
 *   retourne     → cloture, annule
 *   cloture      → litige
 *   litige       → cloture
 *   annule       → (terminal)
 */
final class TripStateMachine
{
    public const TRANSITIONS = [
        'planifie'     => ['valide','annule'],
        'valide'       => ['embarquement','en_route','annule','planifie'],
        'embarquement' => ['en_route','retourne','annule','incident'],
        'en_route'     => ['arrive','incident','retourne'],
        'arrive'       => ['cloture','litige'],
        'incident'     => ['en_route','retourne','cloture','annule'],
        'retourne'     => ['cloture','annule'],
        'cloture'      => ['litige'],
        'litige'       => ['cloture'],
        'annule'       => [],
    ];

    public const SUB_STATUSES = [
        'at_origin'      => 'À la gare de départ',
        'boarding'       => 'Embarquement',
        'departed'       => 'Sorti de la gare',
        'between_stops'  => 'Entre deux arrêts',
        'at_stop'        => 'À un arrêt',
        'arrived'        => 'Arrivé à destination',
        'completed'      => 'Voyage terminé',
        'closed'         => 'Clôturé administrativement',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function allowedFrom(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /**
     * Effectue la transition (avec audit). Throws si invalide.
     */
    public function transition(int $tripId, string $newStatus, ?int $actorId = null, ?string $reason = null, ?string $newSubStatus = null): array
    {
        $trip = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) throw new \RuntimeException("Voyage #$tripId introuvable");

        $from = $trip['status'];
        if ($from === $newStatus && $newSubStatus === ($trip['sub_status'] ?? null)) {
            return ['changed' => false, 'reason' => 'no_change'];
        }

        if ($from !== $newStatus && !self::canTransition($from, $newStatus)) {
            throw new \DomainException("Transition $from → $newStatus interdite");
        }

        $update = ['status' => $newStatus];
        if ($newSubStatus !== null) {
            if (!array_key_exists($newSubStatus, self::SUB_STATUSES)) {
                throw new \InvalidArgumentException("Sub-statut inconnu: $newSubStatus");
            }
            $update['sub_status'] = $newSubStatus;
        }

        // Timestamps spéciaux
        if ($newStatus === 'en_route' && empty($trip['off_block_at'])) {
            $update['off_block_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === 'arrive' && empty($trip['on_block_at'])) {
            $update['on_block_at'] = date('Y-m-d H:i:s');
        }

        Database::update('trips', $update, 'id = ?', [$tripId]);

        AuditLog::record('trip.state.transition', 'trip', $tripId, [
            'actor_id' => $actorId,
            'from'     => $from,
            'to'       => $newStatus,
            'sub'      => $newSubStatus,
            'reason'   => $reason,
        ]);

        return ['changed' => true, 'from' => $from, 'to' => $newStatus, 'sub' => $newSubStatus];
    }

    /**
     * Auto-transition basée sur les événements de tracking.
     * Appelé après chaque arrival/departure.
     */
    public function autoFromTracking(int $tripId): ?array
    {
        $trip = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) return null;

        $stops = Database::select(
            "SELECT id, sequence, scheduled_arrival, scheduled_departure, actual_arrival, actual_departure, is_skipped
             FROM trip_stops WHERE trip_id = ? ORDER BY sequence ASC",
            [$tripId]
        );
        if (!$stops) return null;

        $totalNonSkipped = count(array_filter($stops, fn($s) => !$s['is_skipped']));
        $reached = count(array_filter($stops, fn($s) => !empty($s['actual_arrival']) && !$s['is_skipped']));
        $departed = count(array_filter($stops, fn($s) => !empty($s['actual_departure']) && !$s['is_skipped']));

        $first = $stops[0] ?? null;
        $last  = $stops[array_key_last($stops)] ?? null;

        // Détermine sub_status selon position courante
        $currentSub = null;
        if ($reached === 0 && $departed === 0) {
            $currentSub = 'at_origin';
        } elseif ($reached === count($stops) && !empty($last['actual_arrival'])) {
            $currentSub = 'arrived';
        } elseif ($reached > $departed) {
            $currentSub = 'at_stop';
        } else {
            $currentSub = 'between_stops';
        }

        // Détermine status principal
        $newStatus = $trip['status'];
        if ($trip['status'] === 'valide' && $departed > 0) {
            $newStatus = 'en_route';
            $currentSub = 'departed';
        } elseif (in_array($trip['status'], ['valide','embarquement']) && $first && !empty($first['actual_departure'])) {
            $newStatus = 'en_route';
        } elseif ($trip['status'] === 'en_route' && $reached === $totalNonSkipped && $last && !empty($last['actual_arrival'])) {
            $newStatus = 'arrive';
            $currentSub = 'arrived';
        }

        if ($newStatus === $trip['status'] && $currentSub === ($trip['sub_status'] ?? null)) {
            return null;
        }

        try {
            return $this->transition($tripId, $newStatus, null, 'Auto-transition tracking', $currentSub);
        } catch (\Throwable $e) {
            // Log silencieux : on ne casse pas le flow
            error_log("StateMachine auto-transition failed: " . $e->getMessage());
            return null;
        }
    }
}
