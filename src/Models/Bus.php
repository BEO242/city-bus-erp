<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class Bus extends BaseModel
{
    protected static string $table = 'buses';

    public const STATUSES = [
        'disponible'    => 'Disponible',
        'en_voyage'     => 'En voyage',
        'maintenance'   => 'En maintenance',
        'hors_service'  => 'Hors service',
    ];

    public const FUEL_TYPES = [
        'diesel'     => 'Diesel',
        'essence'    => 'Essence',
        'hybride'    => 'Hybride',
        'electrique' => 'Électrique',
    ];

    public const TRANSMISSIONS = [
        'manuelle'    => 'Manuelle',
        'automatique' => 'Automatique',
    ];

    /** @deprecated Utiliser vehicle_types en base. Conservé pour rétro-compatibilité. */
    public const BODY_TYPES = [
        'autocar'      => 'Autocar (grand tourisme)',
        'minibus'      => 'Minibus',
        'midibus'      => 'Midibus',
        'double_etage' => 'Double étage',
        'urbain'       => 'Bus urbain',
    ];

    /** Types de véhicules actifs (depuis la table vehicle_types). */
    public static function vehicleTypes(): array
    {
        return Database::select(
            "SELECT * FROM vehicle_types WHERE is_active = 1 ORDER BY sort_order, label"
        );
    }

    public const FINANCING_TYPES = [
        'cash'    => 'Comptant',
        'leasing' => 'Leasing',
        'credit'  => 'Crédit bancaire',
        'don'     => 'Don / subvention',
    ];

    public const COLORS = [
        'Blanc', 'Bleu', 'Gris', 'Jaune', 'Noir', 'Orange', 'Rouge', 'Vert',
    ];

    /** Badge CSS selon statut */
    public static function statusClass(string $status): string
    {
        return match ($status) {
            'disponible'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'en_voyage'    => 'bg-blue-50 text-blue-700 border-blue-200',
            'maintenance'  => 'bg-amber-50 text-amber-700 border-amber-200',
            'hors_service' => 'bg-rose-50 text-rose-700 border-rose-200',
            default        => 'bg-slate-100 text-slate-600',
        };
    }

    /**
     * Calcule les alertes d'expiration pour un bus.
     * Retourne un tableau d'alertes avec niveau (info/warn/danger), label, date.
     */
    public static function alerts(array $bus): array
    {
        $alerts = [];
        $today  = strtotime(date('Y-m-d'));
        // 'exp' = libellé expiré(e), 'near' = libellé bientôt, 'renew' = libellé à renouveler
        $checks = [
            ['key' => 'insurance_expiry',    'icon' => 'shield',           'skip' => false,
             'exp' => 'Assurance expirée',             'near' => "Assurance expire bientôt",      'renew' => "Assurance à renouveler"],
            ['key' => 'tech_control_expiry', 'icon' => 'clipboard-check',  'skip' => false,
             'exp' => 'Contrôle technique expiré',     'near' => "Contrôle technique imminent",   'renew' => "Contrôle technique à planifier"],
            ['key' => 'next_maintenance_at', 'icon' => 'wrench',           'skip' => false,
             'exp' => 'Maintenance en retard',         'near' => "Maintenance imminente",         'renew' => "Maintenance à planifier"],
            ['key' => 'registration_card_date', 'icon' => 'file-text',     'skip' => true, // date d'émission, pas d'expiration
             'exp' => '', 'near' => '', 'renew' => ''],
        ];
        foreach ($checks as $c) {
            if ($c['skip']) continue;
            $val = $bus[$c['key']] ?? null;
            // Exclure valeurs vides, NULL MySQL (0000-00-00) et dates non renseignées
            if (empty($val) || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') continue;
            $ts = strtotime((string)$val);
            // Ignorer si strtotime échoue ou si la date est avant 2000 (saisie invalide)
            if ($ts === false || (int)date('Y', $ts) < 2000) continue;
            $days = (int)floor(($ts - $today) / 86400);
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
        // Maintenance kilométrique
        if (!empty($bus['next_maintenance_km']) && !empty($bus['km_current'])) {
            $remaining = (int)$bus['next_maintenance_km'] - (int)$bus['km_current'];
            if ($remaining < 0) {
                $alerts[] = ['level' => 'danger', 'label' => 'Maintenance km dépassée', 'icon' => 'wrench',
                             'detail' => abs($remaining).' km de retard', 'date' => null];
            } elseif ($remaining <= 1000) {
                $alerts[] = ['level' => 'warn', 'label' => 'Maintenance km proche', 'icon' => 'wrench',
                             'detail' => $remaining.' km restants', 'date' => null];
            }
        }
        return $alerts;
    }

    /** Statistiques d'activité d'un bus (voyages, km, revenus). */
    public static function stats(int $busId): array
    {
        $row = Database::selectOne(
            "SELECT
               COUNT(t.id) AS trips_total,
               SUM(CASE WHEN t.status='cloture' THEN 1 ELSE 0 END) AS trips_done,
               SUM(CASE WHEN t.status IN ('planifie','embarquement','en_route') THEN 1 ELSE 0 END) AS trips_active,
               COUNT(DISTINCT CASE WHEN t.trip_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN t.id END) AS trips_30d
             FROM trips t WHERE t.bus_id = ?",
            [$busId]
        ) ?: [];
        // Revenus via tickets sur ces voyages
        $rev = Database::selectOne(
            "SELECT COALESCE(SUM(tk.price_fcfa),0) AS revenue
             FROM tickets tk
             INNER JOIN trips t ON t.id = tk.trip_id
             WHERE t.bus_id = ? AND tk.status = 'valide'",
            [$busId]
        ) ?: ['revenue' => 0];
        return [
            'trips_total'  => (int)($row['trips_total']  ?? 0),
            'trips_done'   => (int)($row['trips_done']   ?? 0),
            'trips_active' => (int)($row['trips_active'] ?? 0),
            'trips_30d'    => (int)($row['trips_30d']    ?? 0),
            'revenue'      => (int)($rev['revenue']      ?? 0),
        ];
    }

    /** Derniers voyages d'un bus (avec ligne, chauffeur). */
    public static function recentTrips(int $busId, int $limit = 8): array
    {
        $sql = "SELECT t.*, l.name AS line_name,
                       CONCAT(d.first_name,' ',d.last_name) AS driver_name
                FROM trips t
                LEFT JOIN bus_lines l ON l.id = t.line_id
                LEFT JOIN drivers d   ON d.id = t.driver_id
                WHERE t.bus_id = ?
                ORDER BY t.trip_date DESC, t.departure_scheduled DESC
                LIMIT $limit";
        return Database::select($sql, [$busId]);
    }
}
