<?php

declare(strict_types=1);

namespace CityBus\Controllers\Ops;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;

/**
 * Tour de contrôle ops (GAP-28).
 * Affiche en temps réel : voyages en cours, positions GPS, alertes,
 * retards, taux de remplissage.
 */
final class ControlTowerController extends Controller
{
    public function index(Request $request): void
    {
        // Voyages actifs aujourd'hui
        $trips = Database::select(
            "SELECT tr.*, l.code AS line_code, l.name AS line_name,
                    b.code AS bus_code, b.plate, b.seats,
                    blp.lat AS gps_lat, blp.lng AS gps_lng,
                    blp.speed_kmh, blp.recorded_at AS gps_at,
                    (SELECT COUNT(*) FROM tickets WHERE trip_id = tr.id AND deleted_at IS NULL
                       AND status IN ('emis','controle','utilise','embarque')) AS pax_count
             FROM trips tr
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             LEFT JOIN buses b ON b.id = tr.bus_id
             LEFT JOIN bus_last_position blp ON blp.bus_id = tr.bus_id
             WHERE tr.trip_date = CURDATE()
             ORDER BY tr.departure_scheduled"
        );

        // Calculs : retard, load factor, gps stale
        foreach ($trips as &$t) {
            $depAct = $t['departure_actual'] ?? null;
            $depSch = $t['departure_scheduled'] ?? null;
            $t['delay_min'] = ($depAct && $depSch)
                ? (int)round((strtotime($t['trip_date'] . ' ' . $depAct) - strtotime($t['trip_date'] . ' ' . $depSch)) / 60)
                : null;
            $t['load_factor_pct'] = $t['seats'] > 0 ? round(((int)$t['pax_count'] / max(1, (int)$t['seats'])) * 100, 1) : 0;
            $t['gps_age_min'] = $t['gps_at'] ? (int)round((time() - strtotime($t['gps_at'])) / 60) : null;
            $t['gps_stale'] = $t['gps_age_min'] !== null && $t['gps_age_min'] > 15;
        }
        unset($t);

        $alerts = [];

        $kpis = Database::selectOne(
            "SELECT
                COUNT(*) AS total_today,
                SUM(CASE WHEN status='en_route' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status='cloture'  THEN 1 ELSE 0 END) AS done,
                SUM(CASE WHEN status='annule'   THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN status='incident' THEN 1 ELSE 0 END) AS incidents
             FROM trips WHERE trip_date = CURDATE()"
        ) ?: [];

        $this->view('ops/control_tower/index', [
            'title'  => 'Tour de contrôle',
            'trips'  => $trips,
            'alerts' => $alerts,
            'kpis'   => $kpis,
        ]);
    }

    public function alertAck(Request $request, string $id): void
    {
        back();
    }

    /** Endpoint JSON pour rafraîchissement auto (polling). */
    public function liveData(Request $request): void
    {
        $this->json(['positions' => [], 'now' => date('c')]);
    }
}
