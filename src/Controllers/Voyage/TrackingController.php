<?php

declare(strict_types=1);

namespace CityBus\Controllers\Voyage;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\TripStop;
use CityBus\Services\StopTrackingService;

/**
 * Tracking stop-by-stop d'un voyage en cours.
 */
final class TrackingController extends Controller
{
    private StopTrackingService $svc;

    public function __construct()
    {
        $this->svc = new StopTrackingService();
    }

    public function show(Request $request, string $tripId): void
    {
        if (!Auth::can('voyages.view')) { back(); return; }

        $trip = Database::selectOne(
            "SELECT tr.*, l.code AS line_code, l.name AS line_name,
                    cd.name AS departure_city, ca.name AS arrival_city
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             WHERE tr.id = ?", [$tripId]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        $stops = TripStop::forTrip((int)$tripId);

        // Si pas d'arrêts, proposer la génération
        if (empty($stops)) {
            $this->view('voyages/tracking/empty', [
                'title' => "Suivi · {$trip['trip_code']}",
                'trip'  => $trip,
            ]);
            return;
        }

        // Stats
        $totalStops = count($stops);
        $reached = count(array_filter($stops, fn($s) => !empty($s['actual_arrival'])));
        $skipped = count(array_filter($stops, fn($s) => $s['is_skipped']));
        $progress = $totalStops > 0 ? round($reached / $totalStops * 100) : 0;

        // Événements récents
        $events = Database::select(
            "SELECT e.*, s.name AS stop_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             FROM trip_progress_events e
             LEFT JOIN stops s ON s.id = e.stop_id
             LEFT JOIN users u ON u.id = e.actor_id
             WHERE e.trip_id = ?
             ORDER BY e.occurred_at DESC LIMIT 30",
            [$tripId]
        );

        $this->view('voyages/tracking/show', [
            'title'      => "Suivi · {$trip['trip_code']}",
            'trip'       => $trip,
            'stops'      => $stops,
            'totalStops' => $totalStops,
            'reached'    => $reached,
            'skipped'    => $skipped,
            'progress'   => $progress,
            'events'     => $events,
        ]);
    }

    public function generate(Request $request, string $tripId): void
    {
        if (!Auth::can('voyages.tracking.update')) { back(); return; }
        $count = $this->svc->generateForTrip((int)$tripId);
        $this->flash('success', "$count arrêt(s) générés.");
        back();
    }

    public function recordArrival(Request $request, string $tripId, string $stopId): void
    {
        if (!Auth::can('voyages.tracking.update')) { $this->flash('danger', 'Permission refusée.'); back(); return; }
        try {
            $r = $this->svc->recordArrival(
                (int)$tripId,
                (int)$stopId,
                $request->input('actual_time') ?: null,
                $request->input('notes')
            );
            $msg = "Arrivée enregistrée";
            if ($r['delay_min'] > 0) $msg .= " (retard {$r['delay_min']} min)";
            $this->flash('success', $msg);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function recordDeparture(Request $request, string $tripId, string $stopId): void
    {
        if (!Auth::can('voyages.tracking.update')) { $this->flash('danger', 'Permission refusée.'); back(); return; }
        try {
            $this->svc->recordDeparture(
                (int)$tripId,
                (int)$stopId,
                $request->input('actual_time') ?: null,
                [
                    'pax_boarded'      => (int)$request->input('pax_boarded', 0),
                    'pax_alighted'     => (int)$request->input('pax_alighted', 0),
                    'parcels_loaded'   => (int)$request->input('parcels_loaded', 0),
                    'parcels_unloaded' => (int)$request->input('parcels_unloaded', 0),
                ]
            );
            $this->flash('success', 'Départ enregistré.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function skipStop(Request $request, string $tripId, string $stopId): void
    {
        if (!Auth::can('voyages.tracking.update')) { back(); return; }
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 5) {
            $this->flash('danger', 'Motif requis (5 car. min).');
            back(); return;
        }
        $this->svc->skipStop((int)$tripId, (int)$stopId, $reason);
        $this->flash('success', 'Arrêt sauté.');
        back();
    }
}
