<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * Hours of Service compliance.
 *
 * Calcule en live le total conduite/repos sur fenêtres glissantes :
 *  - 24h : conduite max 9h (étendue 10h max 2x/sem)
 *  - 7j  : conduite max 56h
 *  - 14j : conduite max 90h
 *  - Pause 45min après 4h30 de conduite continue
 *  - Repos quotidien min 11h sur 24h, hebdo min 45h sur 7j
 */
final class HosService
{
    public function startDuty(int $driverId, string $type, ?int $tripId = null, ?string $location = null, string $source = 'manual'): int
    {
        // Ferme tout duty ouvert pour ce chauffeur
        $this->closeOpenDuties($driverId);

        return Database::insert('driver_duty_logs', [
            'driver_id'  => $driverId,
            'trip_id'    => $tripId,
            'duty_type'  => $type,
            'started_at' => date('Y-m-d H:i:s'),
            'location'   => $location,
            'source'     => $source,
        ]);
    }

    public function endDuty(int $logId, ?string $endedAt = null): void
    {
        $log = Database::selectOne("SELECT * FROM driver_duty_logs WHERE id = ?", [$logId]);
        if (!$log || $log['ended_at']) return;

        $end = $endedAt ?: date('Y-m-d H:i:s');
        $duration = max(0, (int)((strtotime($end) - strtotime($log['started_at'])) / 60));

        Database::update('driver_duty_logs', [
            'ended_at'     => $end,
            'duration_min' => $duration,
        ], 'id = ?', [$logId]);

        // Vérifie violations à la clôture
        if ($log['duty_type'] === 'drive') {
            $this->checkViolations((int)$log['driver_id'], (int)($log['trip_id'] ?? 0));
        }
    }

    public function closeOpenDuties(int $driverId): void
    {
        $open = Database::select(
            "SELECT id FROM driver_duty_logs WHERE driver_id = ? AND ended_at IS NULL",
            [$driverId]
        );
        foreach ($open as $o) {
            $this->endDuty((int)$o['id']);
        }
    }

    /** Statut courant d'un chauffeur (résumé temps réel). */
    public function status(int $driverId): array
    {
        $now = time();
        $w24 = date('Y-m-d H:i:s', $now - 86400);
        $w7d = date('Y-m-d H:i:s', $now - 7 * 86400);
        $w14d= date('Y-m-d H:i:s', $now - 14 * 86400);

        $drive24 = $this->minutesIn($driverId, 'drive', $w24);
        $drive7  = $this->minutesIn($driverId, 'drive', $w7d);
        $drive14 = $this->minutesIn($driverId, 'drive', $w14d);
        $rest24  = $this->minutesIn($driverId, 'rest',  $w24);

        $cont = $this->continuousDriveMinutes($driverId);

        $current = Database::selectOne(
            "SELECT * FROM driver_duty_logs WHERE driver_id = ? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1",
            [$driverId]
        );

        $lim = [
            'daily_drive'      => (int)Setting::get('hos.daily_drive_max_min', 540),
            'weekly_drive'     => (int)Setting::get('hos.weekly_drive_max_min', 3360),
            'biweekly_drive'   => (int)Setting::get('hos.biweekly_drive_max_min', 5400),
            'daily_rest'       => (int)Setting::get('hos.daily_rest_min', 660),
            'continuous'       => (int)Setting::get('hos.continuous_drive_max_min', 270),
        ];

        return [
            'current'          => $current,
            'drive_24h_min'    => $drive24,
            'drive_7d_min'     => $drive7,
            'drive_14d_min'    => $drive14,
            'rest_24h_min'     => $rest24,
            'continuous_min'   => $cont,
            'limits'           => $lim,
            'pct'              => [
                'daily'    => $lim['daily_drive']    > 0 ? round($drive24 / $lim['daily_drive'] * 100, 1) : 0,
                'weekly'   => $lim['weekly_drive']   > 0 ? round($drive7  / $lim['weekly_drive'] * 100, 1) : 0,
                'biweekly' => $lim['biweekly_drive'] > 0 ? round($drive14 / $lim['biweekly_drive'] * 100, 1) : 0,
            ],
        ];
    }

    /** Vérifie qu'un chauffeur peut prendre un service supplémentaire. */
    public function canTakeAssignment(int $driverId, int $additionalMinutes): array
    {
        $st = $this->status($driverId);
        $blockers = [];
        if ($st['drive_24h_min'] + $additionalMinutes > $st['limits']['daily_drive']) {
            $blockers[] = ['rule' => 'DAILY_DRIVE_MAX', 'msg' => 'Dépasserait la limite journalière de conduite'];
        }
        if ($st['drive_7d_min'] + $additionalMinutes > $st['limits']['weekly_drive']) {
            $blockers[] = ['rule' => 'WEEKLY_DRIVE_MAX', 'msg' => 'Dépasserait la limite hebdomadaire'];
        }
        if ($st['drive_14d_min'] + $additionalMinutes > $st['limits']['biweekly_drive']) {
            $blockers[] = ['rule' => 'BIWEEKLY_DRIVE_MAX', 'msg' => 'Dépasserait la limite bi-hebdomadaire'];
        }
        return ['ok' => empty($blockers), 'blockers' => $blockers, 'status' => $st];
    }

    /** Détecte et persiste les violations actuelles du chauffeur. */
    public function checkViolations(int $driverId, ?int $tripId = null): array
    {
        $st = $this->status($driverId);
        $now = date('Y-m-d H:i:s');
        $found = [];

        $report = function(string $code, string $sev, float $actual, float $limit, string $desc) use ($driverId, $tripId, $now, &$found) {
            // Évite doublons à la minute près
            $exists = Database::selectOne(
                "SELECT id FROM hos_violations WHERE driver_id = ? AND rule_code = ? AND detected_at >= DATE_SUB(?, INTERVAL 5 MINUTE)",
                [$driverId, $code, $now]
            );
            if ($exists) return;
            $id = Database::insert('hos_violations', [
                'driver_id'    => $driverId,
                'trip_id'      => $tripId ?: null,
                'rule_code'    => $code,
                'severity'     => $sev,
                'detected_at'  => $now,
                'actual_value' => $actual,
                'limit_value'  => $limit,
                'description'  => $desc,
            ]);
            $found[] = ['id' => $id, 'rule' => $code, 'severity' => $sev];
        };

        if ($st['drive_24h_min'] > $st['limits']['daily_drive']) {
            $report('DAILY_DRIVE_MAX', 'major',
                $st['drive_24h_min'], $st['limits']['daily_drive'],
                "Conduite 24h: {$st['drive_24h_min']}min > {$st['limits']['daily_drive']}min");
        }
        if ($st['drive_7d_min'] > $st['limits']['weekly_drive']) {
            $report('WEEKLY_DRIVE_MAX', 'major',
                $st['drive_7d_min'], $st['limits']['weekly_drive'],
                "Conduite 7j: {$st['drive_7d_min']}min > {$st['limits']['weekly_drive']}min");
        }
        if ($st['continuous_min'] > $st['limits']['continuous']) {
            $report('CONTINUOUS_DRIVE', 'critical',
                $st['continuous_min'], $st['limits']['continuous'],
                "Conduite continue: {$st['continuous_min']}min > {$st['limits']['continuous']}min sans pause");
        }
        if ($st['rest_24h_min'] < $st['limits']['daily_rest']) {
            $report('REST_DAILY_MIN', 'minor',
                $st['rest_24h_min'], $st['limits']['daily_rest'],
                "Repos 24h: {$st['rest_24h_min']}min < {$st['limits']['daily_rest']}min");
        }

        return $found;
    }

    public function acknowledgeViolation(int $violationId, int $userId, ?string $notes = null): void
    {
        Database::update('hos_violations', [
            'acknowledged'    => 1,
            'acknowledged_by' => $userId,
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'resolution_notes'=> $notes,
        ], 'id = ?', [$violationId]);
    }

    public function logsForDriver(int $driverId, string $from, string $to): array
    {
        return Database::select(
            "SELECT d.*, tr.trip_code
             FROM driver_duty_logs d
             LEFT JOIN trips tr ON tr.id = d.trip_id
             WHERE d.driver_id = ?
               AND d.started_at BETWEEN ? AND ?
             ORDER BY d.started_at DESC",
            [$driverId, $from, $to]
        );
    }

    public function violationsForDriver(int $driverId, int $limit = 50): array
    {
        return Database::select(
            "SELECT v.*, tr.trip_code,
                    CONCAT(u.first_name,' ',u.last_name) AS ack_name
             FROM hos_violations v
             LEFT JOIN trips tr ON tr.id = v.trip_id
             LEFT JOIN users u ON u.id = v.acknowledged_by
             WHERE v.driver_id = ?
             ORDER BY v.detected_at DESC LIMIT $limit",
            [$driverId]
        );
    }

    public function fleetSummary(): array
    {
        $drivers = Database::select(
            "SELECT id, first_name, last_name, phone FROM employees
             WHERE position LIKE '%hauffeur%' OR position LIKE '%driver%'
               AND (deleted_at IS NULL)
               AND (status IS NULL OR status = 'actif')
             ORDER BY last_name LIMIT 200"
        );
        $rows = [];
        foreach ($drivers as $d) {
            $st = $this->status((int)$d['id']);
            $unack = (int)Database::scalar(
                "SELECT COUNT(*) FROM hos_violations WHERE driver_id = ? AND acknowledged = 0",
                [$d['id']]
            );
            $rows[] = [
                'driver'           => $d,
                'status'           => $st,
                'unack_violations' => $unack,
            ];
        }
        return $rows;
    }

    private function minutesIn(int $driverId, string $type, string $since): int
    {
        // Sum durées clôturées + durée en cours si type matches
        $closed = (int)Database::scalar(
            "SELECT COALESCE(SUM(duration_min),0) FROM driver_duty_logs
             WHERE driver_id = ? AND duty_type = ? AND started_at >= ? AND ended_at IS NOT NULL",
            [$driverId, $type, $since]
        );
        $open = Database::selectOne(
            "SELECT started_at FROM driver_duty_logs
             WHERE driver_id = ? AND duty_type = ? AND ended_at IS NULL
             ORDER BY started_at DESC LIMIT 1",
            [$driverId, $type]
        );
        if ($open) {
            $start = max(strtotime($open['started_at']), strtotime($since));
            $closed += max(0, (int)((time() - $start) / 60));
        }
        return $closed;
    }

    private function continuousDriveMinutes(int $driverId): int
    {
        // Trouve le dernier rest/break/other_work, puis somme les drive après
        $lastBreak = Database::selectOne(
            "SELECT ended_at FROM driver_duty_logs
             WHERE driver_id = ? AND duty_type IN ('rest','break')
               AND duration_min >= 30
             ORDER BY ended_at DESC LIMIT 1",
            [$driverId]
        );
        $since = $lastBreak ? $lastBreak['ended_at'] : date('Y-m-d H:i:s', time() - 14 * 86400);
        return $this->minutesIn($driverId, 'drive', $since);
    }
}
