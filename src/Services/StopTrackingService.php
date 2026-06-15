<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

/**
 * Tracking stop-by-stop des voyages.
 *
 * Logique :
 *  - À la création d'un voyage, on matérialise les arrêts depuis stops (table ligne).
 *  - Les heures planifiées sont calculées depuis l'horaire de départ et la distance/vitesse.
 *  - Pendant le voyage, l'opérateur enregistre arrivée/départ à chaque arrêt.
 *  - Les ETA des arrêts suivants sont recalculées en cas de retard.
 */
final class StopTrackingService
{
    /**
     * Génère les trip_stops à partir des arrêts de la ligne.
     */
    public function generateForTrip(int $tripId): int
    {
        $existing = Database::selectOne(
            "SELECT COUNT(*) AS c FROM trip_stops WHERE trip_id = ?", [$tripId]
        );
        if ((int)($existing['c'] ?? 0) > 0) return 0;

        $trip = Database::selectOne(
            "SELECT tr.id, tr.line_id, tr.trip_date, tr.departure_scheduled, tr.arrival_scheduled,
                    l.distance_km, l.duration_hours
             FROM trips tr JOIN bus_lines l ON l.id = tr.line_id
             WHERE tr.id = ?", [$tripId]
        );
        if (!$trip) return 0;

        $stops = Database::select(
            "SELECT * FROM stops
             WHERE line_id = ?
             ORDER BY km_from_origin ASC, order_position ASC", [$trip['line_id']]
        );
        if (!$stops) return 0;

        // Heure de départ = scheduled_departure de l'origine
        $departTs = strtotime($trip['trip_date'] . ' ' . $trip['departure_scheduled']);
        $arriveTs = !empty($trip['arrival_scheduled'])
            ? strtotime($trip['trip_date'] . ' ' . $trip['arrival_scheduled'])
            : $departTs + (int)round((float)($trip['duration_hours'] ?? 4) * 3600);

        $totalDistance = max(1, (float)($trip['distance_km'] ?? 1));
        $totalDuration = max(60, $arriveTs - $departTs); // secondes

        $count = 0;
        foreach ($stops as $i => $stop) {
            $km = (float)($stop['km_from_origin'] ?? 0);
            $progress = $km / $totalDistance;
            $stopTs = $departTs + (int)round($progress * $totalDuration);

            // Premier arrêt = origin (départ scheduled = trip departure)
            // Dernier arrêt = destination (arrival scheduled = trip arrival)
            $isFirst = $i === 0;
            $isLast  = $i === count($stops) - 1;
            $schedArrival   = $isFirst ? null : date('Y-m-d H:i:s', $stopTs);
            $schedDeparture = $isLast  ? null : date('Y-m-d H:i:s', $stopTs);
            // Buffer 5 min à l'arrêt
            if (!$isFirst && !$isLast) {
                $schedDeparture = date('Y-m-d H:i:s', $stopTs + 300);
            }

            Database::insert(
                "INSERT INTO trip_stops
                    (trip_id, stop_id, sequence, scheduled_arrival, scheduled_departure,
                     distance_from_origin_km, estimated_arrival, estimated_departure)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $tripId, (int)$stop['id'], (int)$stop['order_position'],
                    $schedArrival, $schedDeparture, $km,
                    $schedArrival, $schedDeparture,
                ]
            );
            $count++;
        }

        AuditLog::record('trip.stops.generate', 'trip', $tripId, ['stops_created' => $count]);
        return $count;
    }

    /**
     * Enregistre l'arrivée à un arrêt (avec calcul retard + propagation).
     */
    public function recordArrival(int $tripId, int $stopId, ?string $actualTime = null, ?string $notes = null): array
    {
        $now = $actualTime ?: date('Y-m-d H:i:s');

        $stop = Database::selectOne(
            "SELECT * FROM trip_stops WHERE trip_id = ? AND stop_id = ?",
            [$tripId, $stopId]
        );
        if (!$stop) throw new \RuntimeException("Arrêt non trouvé pour ce voyage.");

        $delay = 0;
        if (!empty($stop['scheduled_arrival'])) {
            $delay = (int)round((strtotime($now) - strtotime($stop['scheduled_arrival'])) / 60);
        }

        Database::execute(
            "UPDATE trip_stops
                SET actual_arrival = ?,
                    delay_added_min = GREATEST(0, ?),
                    notes = COALESCE(NULLIF(notes,''), ?)
              WHERE id = ?",
            [$now, $delay, $notes, (int)$stop['id']]
        );

        // Mettre à jour current_stop_id du voyage
        Database::execute(
            "UPDATE trips SET current_stop_id = ?, sub_status = ? WHERE id = ?",
            [$stopId, 'at_stop', $tripId]
        );

        // Logger l'événement
        $this->logEvent($tripId, $stopId, 'arrived_at_stop', [
            'delay_min' => $delay, 'actual_time' => $now,
        ]);

        // Propager le retard si paramètre activé
        if ($delay > 0 && Setting::getBool('voyage.tracking.eta_recalc_enabled', true)) {
            $this->propagateDelay($tripId, (int)$stop['sequence'], $delay);
        }

        // Notifier les passagers en aval si seuil dépassé
        $threshold = Setting::getInt('voyage.tracking.delay_notify_threshold', 15);
        if ($delay >= $threshold) {
            $this->notifyDelay($tripId, $delay, $stop);
        }

        AuditLog::record('trip.stop.arrival', 'trip', $tripId, [
            'stop_id' => $stopId, 'delay_min' => $delay,
        ]);

        (new TripStateMachine())->autoFromTracking($tripId);

        return ['delay_min' => $delay, 'actual_time' => $now];
    }

    /**
     * Enregistre le départ d'un arrêt.
     */
    public function recordDeparture(int $tripId, int $stopId, ?string $actualTime = null, array $stats = []): void
    {
        $now = $actualTime ?: date('Y-m-d H:i:s');
        $stop = Database::selectOne(
            "SELECT * FROM trip_stops WHERE trip_id = ? AND stop_id = ?",
            [$tripId, $stopId]
        );
        if (!$stop) throw new \RuntimeException("Arrêt non trouvé.");

        Database::execute(
            "UPDATE trip_stops
                SET actual_departure = ?,
                    pax_boarded = ?,
                    pax_alighted = ?,
                    parcels_loaded = ?,
                    parcels_unloaded = ?
              WHERE id = ?",
            [
                $now,
                (int)($stats['pax_boarded'] ?? 0),
                (int)($stats['pax_alighted'] ?? 0),
                (int)($stats['parcels_loaded'] ?? 0),
                (int)($stats['parcels_unloaded'] ?? 0),
                (int)$stop['id'],
            ]
        );

        // Trouver le prochain arrêt
        $next = Database::selectOne(
            "SELECT ts.stop_id FROM trip_stops ts
             WHERE ts.trip_id = ? AND ts.sequence > ? AND ts.actual_arrival IS NULL AND ts.is_skipped = 0
             ORDER BY ts.sequence ASC LIMIT 1",
            [$tripId, (int)$stop['sequence']]
        );

        Database::execute(
            "UPDATE trips SET current_stop_id = NULL, next_stop_id = ?, sub_status = 'between_stops' WHERE id = ?",
            [$next['stop_id'] ?? null, $tripId]
        );

        $this->logEvent($tripId, $stopId, 'departed_stop', $stats);

        AuditLog::record('trip.stop.departure', 'trip', $tripId, [
            'stop_id' => $stopId, 'stats' => $stats,
        ]);

        (new TripStateMachine())->autoFromTracking($tripId);
    }

    /**
     * Sauter un arrêt (vide, fermé, déroutement).
     */
    public function skipStop(int $tripId, int $stopId, string $reason): void
    {
        Database::execute(
            "UPDATE trip_stops SET is_skipped = 1, skip_reason = ?
             WHERE trip_id = ? AND stop_id = ?",
            [$reason, $tripId, $stopId]
        );
        $this->logEvent($tripId, $stopId, 'manual_note', ['skipped' => true, 'reason' => $reason]);
        AuditLog::record('trip.stop.skip', 'trip', $tripId, ['stop_id' => $stopId, 'reason' => $reason]);
    }

    /**
     * Propage un retard sur tous les arrêts suivants.
     */
    private function propagateDelay(int $tripId, int $fromSequence, int $delayMinutes): void
    {
        Database::execute(
            "UPDATE trip_stops
                SET delay_inherited_min = ?,
                    estimated_arrival   = DATE_ADD(scheduled_arrival,   INTERVAL ? MINUTE),
                    estimated_departure = DATE_ADD(scheduled_departure, INTERVAL ? MINUTE)
              WHERE trip_id = ? AND sequence > ? AND is_skipped = 0",
            [$delayMinutes, $delayMinutes, $delayMinutes, $tripId, $fromSequence]
        );

        // Mettre à jour l'ETA finale du voyage
        $finalEta = Database::selectOne(
            "SELECT estimated_arrival FROM trip_stops
             WHERE trip_id = ? AND is_skipped = 0
             ORDER BY sequence DESC LIMIT 1",
            [$tripId]
        );
        if ($finalEta && $finalEta['estimated_arrival']) {
            Database::execute(
                "UPDATE trips SET current_eta = ? WHERE id = ?",
                [$finalEta['estimated_arrival'], $tripId]
            );
        }

        $this->logEvent($tripId, null, 'eta_recalculated', [
            'from_sequence' => $fromSequence,
            'delay_propagated_min' => $delayMinutes,
        ]);
    }

    /**
     * Notifie les passagers du retard via SMS template.
     */
    private function notifyDelay(int $tripId, int $delayMin, array $stop): void
    {
        try {
            $rows = Database::select(
                "SELECT DISTINCT passenger_phone, passenger_name FROM tickets
                 WHERE trip_id = ? AND status NOT IN ('annule') AND deleted_at IS NULL
                   AND passenger_phone IS NOT NULL AND passenger_phone <> ''",
                [$tripId]
            );
            foreach ($rows as $r) {
                $msg = sprintf(
                    "CITY BUS · Votre voyage a un retard d'environ %d min. Merci de votre patience.",
                    $delayMin
                );
                try {
                    SmsService::send((string)$r['passenger_phone'], $msg);
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::warning('stop_tracking.notify_delay: ' . $e->getMessage());
        }
    }

    /**
     * Log un événement de progression dans trip_progress_events.
     */
    private function logEvent(int $tripId, ?int $stopId, string $type, array $metadata = []): void
    {
        Database::insert(
            "INSERT INTO trip_progress_events
                (trip_id, stop_id, event_type, occurred_at, actor_id, metadata_json)
             VALUES (?, ?, ?, NOW(), ?, ?)",
            [
                $tripId, $stopId, $type,
                Auth::id(),
                $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            ]
        );
    }
}
