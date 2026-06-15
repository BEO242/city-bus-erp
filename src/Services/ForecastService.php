<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;

/**
 * Forecast simple : moyenne mobile pondérée 4 semaines + saisonnalité jour-de-semaine.
 * En prod : remplacer par modèle ML via micro-service Python.
 */
final class ForecastService
{
    public function forecastForLineDate(int $lineId, string $date): int
    {
        // 4 dernières semaines, même jour de semaine
        $samples = [];
        for ($w = 1; $w <= 4; $w++) {
            $past = date('Y-m-d', strtotime("$date -$w week"));
            $pax = (int)Database::scalar(
                "SELECT COUNT(*) FROM tickets t JOIN trips tr ON tr.id = t.trip_id
                 WHERE tr.line_id = ? AND tr.trip_date = ? AND t.status NOT IN ('annule','rembourse')",
                [$lineId, $past]
            );
            $samples[] = $pax;
        }
        if (empty($samples) || max($samples) === 0) return 0;

        // Moyenne pondérée : semaine la plus proche pèse plus
        $weights = [0.4, 0.3, 0.2, 0.1];
        $weighted = 0;
        foreach ($samples as $i => $s) {
            $weighted += $s * ($weights[$i] ?? 0.1);
        }
        return (int)round($weighted);
    }

    public function recomputeAll(string $date): int
    {
        $lines = Database::select("SELECT id FROM bus_lines WHERE deleted_at IS NULL");
        $count = 0;
        foreach ($lines as $l) {
            $forecast = $this->forecastForLineDate((int)$l['id'], $date);
            Database::execute(
                "INSERT INTO demand_forecast (line_id, forecast_date, expected_pax, confidence_pct, method)
                 VALUES (?, ?, ?, 60, 'historical')
                 ON DUPLICATE KEY UPDATE expected_pax = VALUES(expected_pax), confidence_pct=60, method='historical'",
                [$l['id'], $date, $forecast]
            );
            $count++;
        }
        return $count;
    }

    public function listForecasts(string $from, string $to): array
    {
        return Database::select(
            "SELECT df.*, l.code AS line_code, l.name AS line_name
             FROM demand_forecast df
             JOIN bus_lines l ON l.id = df.line_id
             WHERE df.forecast_date BETWEEN ? AND ?
             ORDER BY df.forecast_date, l.code",
            [$from, $to]
        );
    }
}
