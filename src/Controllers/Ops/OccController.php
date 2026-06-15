<?php

declare(strict_types=1);

namespace CityBus\Controllers\Ops;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;

/**
 * Operations Control Center — vue temps réel des voyages du jour.
 * Timeline + alertes (retards, voyages sans équipage, voyages sans bus).
 */
final class OccController extends Controller
{
    public function dashboard(Request $request): void
    {
        if (!Auth::can('ops.occ.view')) { back(); return; }

        $date = $request->input('date', date('Y-m-d'));

        // Voyages du jour
        $trips = Database::select(
            "SELECT tr.id, tr.trip_code, tr.trip_date, tr.departure_time, tr.status, tr.sub_status, tr.delay_minutes,
                    tr.bus_id, tr.driver_id, tr.current_stop_id, tr.next_stop_id, tr.current_eta,
                    l.code AS line_code, cd.name AS departure_city, ca.name AS arrival_city,
                    b.code AS bus_code, b.plate AS bus_plate,
                    CONCAT(e.first_name,' ',e.last_name) AS driver_name,
                    s.name AS current_stop_name, s2.name AS next_stop_name
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             LEFT JOIN buses b ON b.id = tr.bus_id
             LEFT JOIN employees e ON e.id = tr.driver_id
             LEFT JOIN stops s ON s.id = tr.current_stop_id
             LEFT JOIN stops s2 ON s2.id = tr.next_stop_id
             WHERE tr.trip_date = ?
             ORDER BY tr.departure_time ASC",
            [$date]
        );

        // Alertes
        $alerts = [];
        foreach ($trips as $t) {
            if (!$t['bus_id'] && !in_array($t['status'], ['annule','annulé','termine','terminé'])) {
                $alerts[] = ['level' => 'danger', 'trip_id' => $t['id'], 'trip_code' => $t['trip_code'], 'msg' => 'Aucun bus assigné'];
            }
            if (!$t['driver_id'] && !in_array($t['status'], ['annule','annulé','termine','terminé'])) {
                $alerts[] = ['level' => 'danger', 'trip_id' => $t['id'], 'trip_code' => $t['trip_code'], 'msg' => 'Aucun chauffeur assigné'];
            }
            if ((int)$t['delay_minutes'] >= 30) {
                $alerts[] = ['level' => 'warning', 'trip_id' => $t['id'], 'trip_code' => $t['trip_code'], 'msg' => 'Retard ' . (int)$t['delay_minutes'] . ' min'];
            }
        }

        // KPIs
        $kpi = [
            'total'       => count($trips),
            'planned'     => count(array_filter($trips, fn($t) => in_array($t['status'], ['planifie','planifié','prep','prepare']))),
            'in_progress' => count(array_filter($trips, fn($t) => in_array($t['status'], ['en_route','boarding','embarquement']))),
            'completed'   => count(array_filter($trips, fn($t) => in_array($t['status'], ['termine','terminé','arrive','arrivé']))),
            'delayed'     => count(array_filter($trips, fn($t) => (int)$t['delay_minutes'] > 0)),
            'incidents'   => count(array_filter($trips, fn($t) => $t['status'] === 'incident')),
        ];

        $this->view('ops/occ/dashboard', [
            'title'  => 'Operations Control Center',
            'date'   => $date,
            'trips'  => $trips,
            'alerts' => $alerts,
            'kpi'    => $kpi,
        ]);
    }
}
