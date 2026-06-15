<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Models\AuditLog;

/**
 * Génération de voyages depuis des patterns d'horaires récurrents (GAP-11).
 */
final class SchedulePatternService
{
    /**
     * Matérialise les voyages des N prochains jours pour un pattern donné.
     * Idempotent : ne re-crée pas un voyage existant.
     *
     * @return int Nombre de voyages créés
     */
    public function generate(int $patternId, ?int $daysAhead = null, ?int $userId = null): int
    {
        $p = Database::selectOne("SELECT * FROM schedule_patterns WHERE id = ? AND is_active = 1", [$patternId]);
        if (!$p) return 0;

        $daysAhead = $daysAhead ?? (int)$p['auto_generate_days'];
        $startFrom = $p['last_generated_until']
            ? max(strtotime((string)$p['last_generated_until']) + 86400, time())
            : max(strtotime((string)$p['valid_from']), time());
        $endTs = strtotime("+$daysAhead days");
        if ($p['valid_until']) {
            $endTs = min($endTs, strtotime((string)$p['valid_until']));
        }

        $daysOfWeek = array_map('intval', explode(',', (string)$p['days_of_week']));
        $exceptions = $this->loadExceptions($patternId, $startFrom, $endTs);
        $created = 0;

        for ($ts = $startFrom; $ts <= $endTs; $ts += 86400) {
            $date = date('Y-m-d', $ts);
            $dow = (int)date('N', $ts); // 1=lundi..7=dimanche
            if (!in_array($dow, $daysOfWeek, true)) continue;
            if (isset($exceptions[$date]) && $exceptions[$date]['type'] === 'skip') continue;

            $depTime = $exceptions[$date]['custom_departure_time'] ?? $p['departure_time'];

            // Évite les doublons (même pattern + même date)
            $exists = Database::selectOne(
                "SELECT id FROM trips WHERE schedule_pattern_id = ? AND trip_date = ?",
                [$patternId, $date]
            );
            if ($exists) continue;

            // Génère un trip_code unique
            $code = $this->generateTripCode($p, $date);
            Database::insert(
                "INSERT INTO trips
                    (trip_code, line_id, bus_id, trip_date, departure_scheduled, arrival_scheduled,
                     status, schedule_pattern_id, base_price_fcfa, created_at)
                 VALUES (?,?,?,?,?,?,'planifie',?,?,NOW())",
                [
                    $code,
                    (int)$p['line_id'],
                    !empty($p['bus_id']) ? (int)$p['bus_id'] : null,
                    $date,
                    $depTime,
                    $p['arrival_time'] ?? null,
                    $patternId,
                    !empty($p['base_price_fcfa']) ? (int)$p['base_price_fcfa'] : null,
                ]
            );
            $created++;
        }

        Database::execute(
            "UPDATE schedule_patterns SET last_generated_until = ? WHERE id = ?",
            [date('Y-m-d', $endTs), $patternId]
        );
        AuditLog::record('schedule_pattern.generate', 'schedule_pattern', $patternId, [
            'created' => $created, 'until' => date('Y-m-d', $endTs),
        ]);
        return $created;
    }

    /** Génère pour tous les patterns actifs (cron). */
    public function generateAll(): array
    {
        $patterns = Database::select("SELECT id FROM schedule_patterns WHERE is_active = 1");
        $total = 0;
        foreach ($patterns as $p) {
            $total += $this->generate((int)$p['id']);
        }
        return ['patterns' => count($patterns), 'created' => $total];
    }

    private function loadExceptions(int $patternId, int $from, int $to): array
    {
        $rows = Database::select(
            "SELECT * FROM schedule_pattern_exceptions
              WHERE (pattern_id = ? OR pattern_id IS NULL)
                AND exception_date BETWEEN ? AND ?",
            [$patternId, date('Y-m-d', $from), date('Y-m-d', $to)]
        );
        $out = [];
        foreach ($rows as $r) $out[$r['exception_date']] = $r;
        return $out;
    }

    private function generateTripCode(array $pattern, string $date): string
    {
        $line = Database::selectOne("SELECT code FROM bus_lines WHERE id = ?", [(int)$pattern['line_id']]);
        $base = ($line['code'] ?? 'L') . '-' . str_replace('-', '', $date) . '-' . str_replace(':', '', substr((string)$pattern['departure_time'], 0, 5));
        $candidate = $base;
        $i = 1;
        while (Database::selectOne("SELECT id FROM trips WHERE trip_code = ?", [$candidate])) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }
}
