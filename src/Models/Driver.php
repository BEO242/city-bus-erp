<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class Driver extends BaseModel
{
    protected static string $table = 'drivers';
    protected static bool $softDeletes = true;

    public const STATUSES = [
        'actif'        => 'Actif',
        'conge'        => 'En cong&eacute;',
        'suspendu'     => 'Suspendu',
        'en_formation' => 'En formation',
        'accident'     => 'En arr&ecirc;t (accident)',
        'quitte'       => 'A quitt&eacute;',
    ];

    public const GENDERS = ['M' => 'Masculin', 'F' => 'F&eacute;minin'];

    public const MARITAL_STATUSES = [
        'celibataire' => 'C&eacute;libataire',
        'marie'       => 'Mari&eacute;(e)',
        'divorce'     => 'Divorc&eacute;(e)',
        'veuf'        => 'Veuf/Veuve',
    ];

    public const LICENSE_CATEGORIES = ['A','B','C','D','E','F'];

    public static function statusClass(string $status): string
    {
        return match ($status) {
            'actif'        => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'conge'        => 'bg-blue-50 text-blue-700 border-blue-200',
            'suspendu'     => 'bg-rose-50 text-rose-700 border-rose-200',
            'en_formation' => 'bg-purple-50 text-purple-700 border-purple-200',
            'accident'     => 'bg-orange-50 text-orange-700 border-orange-200',
            'quitte'       => 'bg-slate-100 text-slate-500 border-slate-200',
            default        => 'bg-slate-100 text-slate-600',
        };
    }

    /**
     * Calcule les alertes d'expiration pour un chauffeur (permis, médical, psycho).
     */
    public static function alerts(array $driver): array
    {
        $alerts = [];
        $today  = strtotime(date('Y-m-d'));
        // 'exp' = libellé expiré(e), 'near' = expire bientôt, 'renew' = à renouveler
        $checks = [
            ['key' => 'license_expiry',      'icon' => 'id-card',
             'exp' => 'Permis de conduire expiré',       'near' => 'Permis de conduire expire bientôt',       'renew' => 'Permis de conduire à renouveler'],
            ['key' => 'medical_cert_expiry', 'icon' => 'stethoscope',
             'exp' => 'Visite médicale expirée',         'near' => 'Visite médicale imminente',               'renew' => 'Visite médicale à planifier'],
            ['key' => 'psycho_test_expiry',  'icon' => 'brain',
             'exp' => 'Test psychotechnique expiré',     'near' => 'Test psychotechnique à renouveler',       'renew' => 'Test psychotechnique à planifier'],
            ['key' => 'ophthalmo_expiry',    'icon' => 'eye',
             'exp' => 'Bilan ophtalmologique expiré',    'near' => 'Bilan ophtalmologique à renouveler',      'renew' => 'Bilan ophtalmologique à planifier'],
            ['key' => 'national_id_expiry',  'icon' => 'badge',
             'exp' => "Carte d'identité expirée",        'near' => "Carte d'identité expire bientôt",         'renew' => "Carte d'identité à renouveler"],
        ];
        foreach ($checks as $c) {
            $val = $driver[$c['key']] ?? null;
            // Exclure valeurs vides, NULL MySQL (0000-00-00) et dates non renseignées
            if (empty($val) || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') continue;
            $ts = strtotime((string)$val);
            // Ignorer si strtotime échoue ou si la date est avant 2000 (saisie invalide)
            if ($ts === false || (int)date('Y', $ts) < 2000) continue;
            $days    = (int)floor(($ts - $today) / 86400);
            $dateFmt = date('d/m/Y', $ts);
            if ($days < 0) {
                $alerts[] = ['level' => 'danger', 'label' => $c['exp'], 'icon' => $c['icon'],
                             'detail' => 'Depuis '.abs($days).' jour(s)', 'date' => $dateFmt];
            } elseif ($days === 0) {
                $alerts[] = ['level' => 'danger', 'label' => $c['near'], 'icon' => $c['icon'],
                             'detail' => "Expire aujourd'hui", 'date' => $dateFmt];
            } elseif ($days <= 15) {
                $alerts[] = ['level' => 'danger', 'label' => $c['near'], 'icon' => $c['icon'],
                             'detail' => 'Dans '.$days.' jour(s)', 'date' => $dateFmt];
            } elseif ($days <= 45) {
                $alerts[] = ['level' => 'warn', 'label' => $c['renew'], 'icon' => $c['icon'],
                             'detail' => 'Dans '.$days.' jour(s)', 'date' => $dateFmt];
            }
        }
        return $alerts;
    }

    /** Statistiques d'activité d'un chauffeur. */
    public static function stats(int $driverId): array
    {
        $row = Database::selectOne(
            "SELECT
               COUNT(t.id) AS trips_total,
               SUM(CASE WHEN t.status='cloture' THEN 1 ELSE 0 END) AS trips_done,
               COUNT(DISTINCT CASE WHEN t.trip_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN t.id END) AS trips_30d,
               COUNT(DISTINCT t.bus_id) AS buses_driven
             FROM trips t WHERE t.driver_id = ?",
            [$driverId]
        ) ?: [];
        return [
            'trips_total'  => (int)($row['trips_total']  ?? 0),
            'trips_done'   => (int)($row['trips_done']   ?? 0),
            'trips_30d'    => (int)($row['trips_30d']    ?? 0),
            'buses_driven' => (int)($row['buses_driven'] ?? 0),
        ];
    }

    /** Voyages récents d'un chauffeur. */
    public static function recentTrips(int $driverId, int $limit = 8): array
    {
        $sql = "SELECT t.*, l.name AS line_name, b.code AS bus_code, b.plate AS bus_plate
                FROM trips t
                LEFT JOIN bus_lines l ON l.id = t.line_id
                LEFT JOIN buses b     ON b.id = t.bus_id
                WHERE t.driver_id = ?
                ORDER BY t.trip_date DESC, t.departure_scheduled DESC
                LIMIT $limit";
        return Database::select($sql, [$driverId]);
    }

    public static function fullName(array $driver): string
    {
        return trim(($driver['last_name'] ?? '').' '.($driver['first_name'] ?? ''));
    }

    public static function age(?string $birthDate): ?int
    {
        if (!$birthDate) return null;
        $bd = strtotime($birthDate);
        if (!$bd) return null;
        return (int)floor((time() - $bd) / (365.25 * 86400));
    }
}
