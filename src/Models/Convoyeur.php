<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class Convoyeur
{
    public const STATUSES = [
        'actif'        => 'Actif',
        'conge'        => 'En congé',
        'suspendu'     => 'Suspendu',
        'en_formation' => 'En formation',
        'quitte'       => 'A quitté',
    ];

    public const STATUS_COLORS = [
        'actif'        => 'bg-emerald-100 text-emerald-700',
        'conge'        => 'bg-amber-100 text-amber-700',
        'suspendu'     => 'bg-rose-100 text-rose-700',
        'en_formation' => 'bg-blue-100 text-blue-700',
        'quitte'       => 'bg-slate-100 text-slate-500',
    ];

    public static function findOrFail(int $id): array
    {
        $row = Database::selectOne(
            "SELECT * FROM convoyeurs WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
        if (!$row) {
            http_response_code(404);
            throw new \RuntimeException("Convoyeur introuvable : #{$id}");
        }
        return $row;
    }

    public static function create(array $data): int
    {
        $cols = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colsSql = implode(',', $cols);

        return Database::insert(
            "INSERT INTO convoyeurs ({$colsSql}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    public static function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $params = array_values($data);
        $params[] = $id;
        Database::execute("UPDATE convoyeurs SET {$sets} WHERE id = ?", $params);
    }

    public static function delete(int $id): void
    {
        Database::execute("UPDATE convoyeurs SET deleted_at = NOW() WHERE id = ?", [$id]);
    }

    public static function fullName(array $c): string
    {
        return trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    }

    public static function generateMatricule(): string
    {
        $prefix = 'CVY';
        $year = date('y');
        $row = Database::selectOne(
            "SELECT matricule FROM convoyeurs WHERE matricule LIKE ? ORDER BY id DESC LIMIT 1",
            ["{$prefix}{$year}%"]
        );
        $next = $row ? ((int)substr($row['matricule'], -4)) + 1 : 1;
        return sprintf('%s%s%04d', $prefix, $year, $next);
    }

    /**
     * Statistiques du convoyeur.
     */
    public static function stats(int $id): array
    {
        $row = Database::selectOne(
            "SELECT
                (SELECT COUNT(*) FROM trips WHERE convoyeur_id = ?) AS total_trips,
                (SELECT COUNT(*) FROM trips WHERE convoyeur_id = ? AND trip_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS trips_30d
            ",
            [$id, $id]
        ) ?: ['total_trips' => 0, 'trips_30d' => 0];

        return $row;
    }

    /**
     * Derniers voyages du convoyeur.
     */
    public static function recentTrips(int $id, int $limit = 10): array
    {
        return Database::select(
            "SELECT tr.*, l.name AS line_name, l.code AS line_code,
                    b.code AS bus_code, b.plate AS bus_plate
               FROM trips tr
               LEFT JOIN bus_lines l ON l.id = tr.line_id
               LEFT JOIN buses b ON b.id = tr.bus_id
              WHERE tr.convoyeur_id = ?
              ORDER BY tr.trip_date DESC, tr.departure_scheduled DESC
              LIMIT ?",
            [$id, $limit]
        );
    }

    /**
     * Alertes du convoyeur (documents expirants, etc.).
     */
    public static function alerts(array $c): array
    {
        $alerts = [];
        $today = date('Y-m-d');
        $soon = date('Y-m-d', strtotime('+30 days'));

        if (!empty($c['national_id_expiry'])) {
            if ($c['national_id_expiry'] < $today) {
                $alerts[] = ['level' => 'danger', 'msg' => 'CNI expirée'];
            } elseif ($c['national_id_expiry'] <= $soon) {
                $alerts[] = ['level' => 'warning', 'msg' => 'CNI expire bientôt'];
            }
        }

        if (($c['warnings_count'] ?? 0) >= 3) {
            $alerts[] = ['level' => 'danger', 'msg' => $c['warnings_count'] . ' avertissements'];
        }

        return $alerts;
    }
}
