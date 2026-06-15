<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * Conformité Hours of Service (HOS) chauffeurs.
 *
 * Calcule cumuls journalier / hebdomadaire / bihebdomadaire et vérifie
 * les seuils réglementaires (OIT / CEMAC) avant l'affectation d'un voyage.
 */
final class DriverHosService
{
    /**
     * Compute la situation HOS d'un chauffeur à un instant donné.
     *
     * @return array{
     *   driver_id:int,
     *   reference_at:string,
     *   today_minutes:int,
     *   week_minutes:int,
     *   biweek_minutes:int,
     *   continuous_minutes:int,
     *   last_rest_minutes:int,
     *   limits:array,
     *   warnings:array,
     *   blocking:bool
     * }
     */
    public function status(int $driverId, ?string $atIso = null): array
    {
        $now      = $atIso ?: date('Y-m-d H:i:s');
        $todayStart = date('Y-m-d 00:00:00', strtotime($now));
        $weekStart  = date('Y-m-d H:i:s', strtotime($now . ' -7 days'));
        $biwStart   = date('Y-m-d H:i:s', strtotime($now . ' -14 days'));

        $todayMin  = $this->driveMinutesBetween($driverId, $todayStart, $now);
        $weekMin   = $this->driveMinutesBetween($driverId, $weekStart, $now);
        $biweekMin = $this->driveMinutesBetween($driverId, $biwStart, $now);
        $continuousMin = $this->continuousDriveMinutes($driverId, $now);
        $lastRestMin   = $this->lastRestMinutes($driverId, $now);

        $limits = [
            'daily_max'           => Setting::getInt('hos.daily_max_hours', 9) * 60,
            'daily_max_extended'  => Setting::getInt('hos.daily_max_extended_hours', 10) * 60,
            'weekly_max'          => Setting::getInt('hos.weekly_max_hours', 56) * 60,
            'biweekly_max'        => Setting::getInt('hos.biweekly_max_hours', 90) * 60,
            'continuous_max'      => Setting::getInt('hos.continuous_max_minutes', 270),
            'required_break'      => Setting::getInt('hos.required_break_minutes', 45),
            'daily_rest'          => Setting::getInt('hos.daily_rest_minutes', 660),
        ];

        $warnings = [];
        $blocking = false;

        if ($todayMin >= $limits['daily_max_extended']) {
            $warnings[] = ['level' => 'critical', 'code' => 'daily_extended_exceeded',
                'message' => sprintf('Conduite jour : %d min ≥ %d min (limite étendue)', $todayMin, $limits['daily_max_extended'])];
            $blocking = true;
        } elseif ($todayMin >= $limits['daily_max']) {
            $warnings[] = ['level' => 'warning', 'code' => 'daily_normal_exceeded',
                'message' => sprintf('Conduite jour : %d min ≥ %d min (limite normale)', $todayMin, $limits['daily_max'])];
        }

        if ($weekMin >= $limits['weekly_max']) {
            $warnings[] = ['level' => 'critical', 'code' => 'weekly_exceeded',
                'message' => sprintf('Hebdomadaire : %d min ≥ %d min', $weekMin, $limits['weekly_max'])];
            $blocking = true;
        }
        if ($biweekMin >= $limits['biweekly_max']) {
            $warnings[] = ['level' => 'critical', 'code' => 'biweekly_exceeded',
                'message' => sprintf('Bihebdomadaire : %d min ≥ %d min', $biweekMin, $limits['biweekly_max'])];
            $blocking = true;
        }
        if ($continuousMin >= $limits['continuous_max']) {
            $warnings[] = ['level' => 'warning', 'code' => 'continuous_drive',
                'message' => sprintf('Conduite continue depuis %d min, pause obligatoire (%d min)', $continuousMin, $limits['required_break'])];
        }
        if ($lastRestMin > 0 && $lastRestMin < $limits['daily_rest']) {
            $warnings[] = ['level' => 'critical', 'code' => 'insufficient_rest',
                'message' => sprintf('Dernier repos : %d min < %d min requis', $lastRestMin, $limits['daily_rest'])];
            $blocking = true;
        }

        return [
            'driver_id'         => $driverId,
            'reference_at'      => $now,
            'today_minutes'     => $todayMin,
            'week_minutes'      => $weekMin,
            'biweek_minutes'    => $biweekMin,
            'continuous_minutes'=> $continuousMin,
            'last_rest_minutes' => $lastRestMin,
            'limits'            => $limits,
            'warnings'          => $warnings,
            'blocking'          => $blocking,
        ];
    }

    /**
     * Vérifie si un chauffeur peut être affecté à un voyage à un horaire donné.
     * Retourne la liste des warnings (vide si OK), avec flag blocking.
     * Selon hos.enforcement_mode (warning|block) le contrôleur décide ou non d'empêcher.
     */
    public function canAssign(int $driverId, string $tripStart, string $tripEnd): array
    {
        $startStatus = $this->status($driverId, $tripStart);
        $tripDurationMin = max(0, (strtotime($tripEnd) - strtotime($tripStart)) / 60);
        $projectedToday  = $startStatus['today_minutes']  + $tripDurationMin;
        $projectedWeek   = $startStatus['week_minutes']   + $tripDurationMin;
        $projectedBiweek = $startStatus['biweek_minutes'] + $tripDurationMin;

        $projWarnings = [];
        $blocking = $startStatus['blocking'];

        if ($projectedToday > $startStatus['limits']['daily_max_extended']) {
            $projWarnings[] = ['level' => 'critical', 'code' => 'projected_daily_exceeded',
                'message' => sprintf('Voyage projettera la conduite à %d min (max %d)', $projectedToday, $startStatus['limits']['daily_max_extended'])];
            $blocking = true;
        }
        if ($projectedWeek > $startStatus['limits']['weekly_max']) {
            $projWarnings[] = ['level' => 'critical', 'code' => 'projected_weekly_exceeded',
                'message' => sprintf('Voyage projettera l\'hebdo à %d min (max %d)', $projectedWeek, $startStatus['limits']['weekly_max'])];
            $blocking = true;
        }
        if ($projectedBiweek > $startStatus['limits']['biweekly_max']) {
            $projWarnings[] = ['level' => 'critical', 'code' => 'projected_biweekly_exceeded',
                'message' => sprintf('Voyage projettera le bihebdo à %d min (max %d)', $projectedBiweek, $startStatus['limits']['biweekly_max'])];
            $blocking = true;
        }

        return [
            'warnings' => array_merge($startStatus['warnings'], $projWarnings),
            'blocking' => $blocking,
            'enforce'  => Setting::getString('hos.enforcement_mode', 'warning') === 'block',
            'projected'=> [
                'today_minutes'  => (int)$projectedToday,
                'week_minutes'   => (int)$projectedWeek,
                'biweek_minutes' => (int)$projectedBiweek,
            ],
        ];
    }

    /** Enregistre une session de conduite ou de repos. */
    public function logEntry(array $data, ?int $userId = null): int
    {
        $duration = null;
        if (!empty($data['end_at'])) {
            $duration = max(0, (int)round((strtotime($data['end_at']) - strtotime($data['start_at'])) / 60));
        }
        return (int)Database::insert(
            "INSERT INTO driver_hours_log
                (driver_id, trip_id, log_type, start_at, end_at, duration_minutes, location, notes, source, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                (int)$data['driver_id'],
                !empty($data['trip_id']) ? (int)$data['trip_id'] : null,
                $data['log_type'],
                $data['start_at'],
                $data['end_at']           ?? null,
                $duration,
                $data['location']         ?? null,
                $data['notes']            ?? null,
                $data['source']           ?? 'manual',
                $userId,
            ]
        );
    }

    /** Auto-création d'une session conduite à partir d'un voyage clôturé. */
    public function autoLogFromTrip(int $tripId): void
    {
        $trip = Database::selectOne(
            "SELECT id, departure_actual, arrival_actual, departure_scheduled, arrival_scheduled
             FROM trips WHERE id = ?", [$tripId]
        );
        if (!$trip) return;

        $crew = Database::select(
            "SELECT employee_id FROM trip_crew WHERE trip_id = ? AND role IN ('chauffeur','chauffeur_relais')", [$tripId]
        );
        if (!$crew) return;

        $start = $trip['departure_actual'] ?? $trip['departure_scheduled'];
        $end   = $trip['arrival_actual']   ?? $trip['arrival_scheduled'];
        if (!$start || !$end) return;

        // Récupérer le driver_id correspondant à l'employee_id si possible
        foreach ($crew as $c) {
            $driver = Database::selectOne(
                "SELECT id FROM drivers WHERE employee_id = ? AND deleted_at IS NULL LIMIT 1",
                [(int)$c['employee_id']]
            );
            if (!$driver) continue;

            // Évite doublons : même voyage + même chauffeur
            $exists = Database::selectOne(
                "SELECT id FROM driver_hours_log WHERE driver_id = ? AND trip_id = ? AND log_type = 'conduite'",
                [(int)$driver['id'], $tripId]
            );
            if ($exists) continue;

            $this->logEntry([
                'driver_id' => (int)$driver['id'],
                'trip_id'   => $tripId,
                'log_type'  => 'conduite',
                'start_at'  => $start,
                'end_at'    => $end,
                'source'    => 'auto_trip',
            ]);
        }
    }

    public function recentLogs(int $driverId, int $days = 14): array
    {
        return Database::select(
            "SELECT dhl.*, tr.trip_code,
                    CONCAT(u.first_name,' ',u.last_name) AS author
             FROM driver_hours_log dhl
             LEFT JOIN trips tr ON tr.id = dhl.trip_id
             LEFT JOIN users u  ON u.id  = dhl.created_by
             WHERE dhl.driver_id = ?
               AND dhl.start_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY dhl.start_at DESC",
            [$driverId, $days]
        );
    }

    // ─── Privé ──────────────────────────────────────────────────────

    private function driveMinutesBetween(int $driverId, string $from, string $to): int
    {
        $row = Database::selectOne(
            "SELECT COALESCE(SUM(
                LEAST(TIMESTAMPDIFF(MINUTE, GREATEST(start_at, ?), LEAST(COALESCE(end_at, NOW()), ?)),
                      TIMESTAMPDIFF(MINUTE, start_at, COALESCE(end_at, NOW())))
            ), 0) AS m
             FROM driver_hours_log
             WHERE driver_id = ?
               AND log_type = 'conduite'
               AND start_at < ?
               AND COALESCE(end_at, NOW()) > ?",
            [$from, $to, $driverId, $to, $from]
        );
        return max(0, (int)($row['m'] ?? 0));
    }

    private function continuousDriveMinutes(int $driverId, string $now): int
    {
        // Cherche la dernière pause / repos > 15 min
        $lastBreak = Database::selectOne(
            "SELECT end_at FROM driver_hours_log
              WHERE driver_id = ?
                AND log_type IN ('pause','repos_quotidien','repos_hebdo')
                AND COALESCE(duration_minutes, TIMESTAMPDIFF(MINUTE, start_at, end_at)) >= 15
                AND end_at <= ?
              ORDER BY end_at DESC LIMIT 1",
            [$driverId, $now]
        );
        $since = $lastBreak['end_at'] ?? null;
        if (!$since) {
            // Pas de pause connue : retour 0 (pas d'alarme erronée)
            return 0;
        }
        return $this->driveMinutesBetween($driverId, $since, $now);
    }

    private function lastRestMinutes(int $driverId, string $now): int
    {
        $rest = Database::selectOne(
            "SELECT COALESCE(duration_minutes, TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS m
             FROM driver_hours_log
             WHERE driver_id = ?
               AND log_type IN ('repos_quotidien','repos_hebdo')
               AND end_at <= ?
             ORDER BY end_at DESC LIMIT 1",
            [$driverId, $now]
        );
        return (int)($rest['m'] ?? 0);
    }
}
