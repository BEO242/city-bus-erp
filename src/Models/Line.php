<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class Line extends BaseModel
{
    protected static string $table = 'bus_lines';

    /** Types de ligne. */
    public const LINE_TYPES = [
        'interurbain' => 'Interurbain',
        'urbain'      => 'Urbain',
    ];

    public const LINE_TYPE_COLORS = [
        'interurbain' => 'bg-blue-50 text-blue-700 border-blue-200',
        'urbain'      => 'bg-purple-50 text-purple-700 border-purple-200',
    ];

    public const LINE_TYPE_ICONS = [
        'interurbain' => 'route',
        'urbain'      => 'bus',
    ];

    /** Villes desservies (cohérent avec ENUM SQL). */
    public const CITIES = [
        'brazzaville'  => 'Brazzaville',
        'pointe_noire' => 'Pointe-Noire',
    ];

    /**
     * Statut "logique" dérivé : actuellement basé sur is_active.
     * Permet une évolution future vers un statut multi-états sans casser l'API.
     */
    public const STATUSES = [
        'active'   => 'En service',
        'inactive' => 'Désactivée',
    ];

    public static function statusOf(array $line): string
    {
        return !empty($line['is_active']) ? 'active' : 'inactive';
    }

    public static function statusClass(string $status): string
    {
        return match ($status) {
            'active'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'inactive' => 'bg-slate-100 text-slate-600 border-slate-200',
            default    => 'bg-slate-100 text-slate-600 border-slate-200',
        };
    }

    /** Libellé court ville → ville. Utilise les alias *_city_name si présents. */
    public static function tripLabel(array $line): string
    {
        $a = $line['departure_city_name']
             ?? (self::CITIES[$line['departure_city'] ?? ''] ?? ($line['departure_city'] ?? ''));
        $b = $line['arrival_city_name']
             ?? (self::CITIES[$line['arrival_city']   ?? ''] ?? ($line['arrival_city']   ?? ''));
        return $a . ' → ' . $b;
    }

    /**
     * Calcule les alertes/incohérences d'une ligne.
     * Niveaux : danger (bloque la billetterie), warn (à corriger), info (suggestion).
     * @return array<int,array{level:string,label:string,icon:string,detail:string}>
     */
    public static function alerts(array $line): array
    {
        $alerts = [];
        $id     = (int)($line['id'] ?? 0);
        if ($id <= 0) return $alerts;

        // 1. Aucun tarif actif → bloque la billetterie
        $tariffsActive = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM tariffs WHERE line_id=? AND is_active=1",
            [$id]
        )['n'] ?? 0);
        if ($tariffsActive === 0) {
            $alerts[] = ['level' => 'danger', 'label' => 'Aucun tarif actif', 'icon' => 'tag',
                         'detail' => 'Impossible de vendre des billets sur cette ligne'];
        }

        // 2. Tarif "passager" manquant
        $hasPax = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM tariffs WHERE line_id=? AND ticket_type='passager' AND is_active=1",
            [$id]
        )['n'] ?? 0);
        if ($tariffsActive > 0 && $hasPax === 0) {
            $alerts[] = ['level' => 'warn', 'label' => 'Tarif passager absent', 'icon' => 'ticket',
                         'detail' => 'Aucun prix de base pour les voyageurs'];
        }

        // 3. Arrêts : vérification spécifique urbain (min 2) et générique
        $stopsN = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM stops WHERE line_id=?",
            [$id]
        )['n'] ?? 0);
        $isUrbain = ($line['line_type'] ?? '') === 'urbain';
        if ($isUrbain && $stopsN < 2) {
            $alerts[] = ['level' => 'danger', 'label' => 'Arrêts insuffisants', 'icon' => 'map-pin',
                         'detail' => 'Une ligne urbaine doit avoir au moins 2 arrêts (départ et arrivée). Actuellement : ' . $stopsN];
        } elseif ($stopsN === 0) {
            $alerts[] = ['level' => 'warn', 'label' => 'Aucun arrêt défini', 'icon' => 'map-pin',
                         'detail' => 'Aucun arrêt intermédiaire enregistré'];
        }

        // 4. Distance ou durée manquante
        if (empty($line['distance_km']) || (float)$line['distance_km'] <= 0) {
            $alerts[] = ['level' => 'warn', 'label' => 'Distance non renseignée', 'icon' => 'route',
                         'detail' => 'Le kilométrage de la ligne est requis'];
        }
        if (empty($line['duration_hours']) || (float)$line['duration_hours'] <= 0) {
            $alerts[] = ['level' => 'warn', 'label' => 'Durée non renseignée', 'icon' => 'clock',
                         'detail' => 'La durée estimée est requise pour la planification'];
        }

        // 5. Aucun voyage planifié dans les 30 prochains jours
        $upcoming = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM trips WHERE line_id=? AND trip_date >= CURDATE() AND trip_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
            [$id]
        )['n'] ?? 0);
        if (!empty($line['is_active']) && $upcoming === 0) {
            $alerts[] = ['level' => 'info', 'label' => 'Aucun voyage planifié', 'icon' => 'calendar-x',
                         'detail' => 'Aucun voyage prévu sur les 30 prochains jours'];
        }

        // 6. Ville de départ = ville d'arrivée (incohérence) — seulement pour les interurbaines
        if (!$isUrbain && !empty($line['departure_city']) && $line['departure_city'] === ($line['arrival_city'] ?? '')) {
            $alerts[] = ['level' => 'danger', 'label' => 'Itinéraire incohérent', 'icon' => 'alert-triangle',
                         'detail' => "Ville de départ identique à la ville d'arrivée"];
        }

        return $alerts;
    }

    /** Statistiques d'activité d'une ligne. */
    public static function stats(int $lineId): array
    {
        $row = Database::selectOne(
            "SELECT
               COUNT(t.id) AS trips_total,
               SUM(CASE WHEN t.status='cloture' THEN 1 ELSE 0 END) AS trips_done,
               SUM(CASE WHEN t.status IN ('planifie','embarquement','en_route') THEN 1 ELSE 0 END) AS trips_active,
               COUNT(DISTINCT CASE WHEN t.trip_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN t.id END) AS trips_30d,
               COUNT(DISTINCT t.bus_id)    AS buses_used,
               COUNT(DISTINCT t.driver_id) AS drivers_used
             FROM trips t WHERE t.line_id=?",
            [$lineId]
        ) ?: [];
        $rev = Database::selectOne(
            "SELECT COALESCE(SUM(tk.price_fcfa),0) AS revenue
             FROM tickets tk
             INNER JOIN trips t ON t.id = tk.trip_id
             WHERE t.line_id=? AND tk.deleted_at IS NULL AND tk.status IN ('valide','embarque','arrive','cloture')",
            [$lineId]
        ) ?: ['revenue' => 0];
        return [
            'trips_total'  => (int)($row['trips_total']  ?? 0),
            'trips_done'   => (int)($row['trips_done']   ?? 0),
            'trips_active' => (int)($row['trips_active'] ?? 0),
            'trips_30d'    => (int)($row['trips_30d']    ?? 0),
            'buses_used'   => (int)($row['buses_used']   ?? 0),
            'drivers_used' => (int)($row['drivers_used'] ?? 0),
            'revenue'      => (int)($rev['revenue']      ?? 0),
        ];
    }

    /** Derniers voyages effectués sur la ligne. */
    public static function recentTrips(int $lineId, int $limit = 10): array
    {
        return Database::select(
            "SELECT t.id, t.trip_date, t.status, t.departure_scheduled,
                    b.code AS bus_code, b.plate AS bus_plate,
                    CONCAT(d.first_name,' ',d.last_name) AS driver_name
             FROM trips t
             LEFT JOIN buses   b ON b.id = t.bus_id
             LEFT JOIN drivers d ON d.id = t.driver_id
             WHERE t.line_id=?
             ORDER BY t.trip_date DESC, t.departure_scheduled DESC
             LIMIT " . (int)$limit,
            [$lineId]
        );
    }
}
