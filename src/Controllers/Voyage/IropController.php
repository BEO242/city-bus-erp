<?php

declare(strict_types=1);

namespace CityBus\Controllers\Voyage;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\IropService;

final class IropController extends Controller
{
    private IropService $svc;
    public function __construct() { $this->svc = new IropService(); }

    public function index(Request $request): void
    {
        if (!Auth::can('voyages.irop.view')) { back(); return; }
        $this->view('voyages/irop/index', [
            'title'  => 'Événements IROP',
            'events' => $this->svc->listOpen(),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        if (!Auth::can('voyages.irop.view')) { back(); return; }
        $irop = $this->svc->get((int)$id);
        if (!$irop) { http_response_code(404); $this->view('errors/404'); return; }
        $this->view('voyages/irop/show', [
            'title'      => "IROP #$id",
            'irop'       => $irop,
            'rebookings' => $this->svc->rebookings((int)$id),
            'altTrips'   => $this->fetchAlternativeTrips((int)$irop['trip_id']),
        ]);
    }

    public function open(Request $request, string $tripId): void
    {
        if (!Auth::can('voyages.irop.manage')) { back(); return; }
        $type = (string)$request->input('irop_type', 'incident');
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 10) {
            $this->flash('danger', 'Motif requis (10 caractères min).');
            back(); return;
        }
        try {
            $id = $this->svc->open((int)$tripId, $type, $reason, [
                'severity'      => $request->input('severity', 'medium'),
                'delay_minutes' => (int)$request->input('delay_minutes', 0),
                'opened_by'     => Auth::user()['id'] ?? null,
                'notes'         => $request->input('notes'),
            ]);
            $this->flash('success', "IROP #$id ouvert.");
            $this->redirect('voyages/irop/' . $id);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            back();
        }
    }

    public function initRebook(Request $request, string $id): void
    {
        if (!Auth::can('voyages.irop.rebook')) { back(); return; }
        $count = $this->svc->initRebooking((int)$id);
        $this->flash('success', "$count demande(s) de rebooking créée(s).");
        back();
    }

    public function rebookTo(Request $request, string $id, string $rebookId): void
    {
        if (!Auth::can('voyages.irop.rebook')) { back(); return; }
        $newTripId = (int)$request->input('new_trip_id');
        if ($newTripId <= 0) { $this->flash('danger', 'Voyage cible requis.'); back(); return; }
        $this->svc->rebookTo((int)$rebookId, $newTripId, Auth::user()['id'] ?? null);
        $this->flash('success', 'Rebooking effectué.');
        back();
    }

    public function refund(Request $request, string $id, string $rebookId): void
    {
        if (!Auth::can('voyages.irop.rebook')) { back(); return; }
        $this->svc->refund((int)$rebookId, Auth::user()['id'] ?? null);
        $this->flash('success', 'Remboursement enregistré.');
        back();
    }

    public function resolve(Request $request, string $id): void
    {
        if (!Auth::can('voyages.irop.manage')) { back(); return; }
        $this->svc->resolve((int)$id, Auth::user()['id'] ?? null, $request->input('notes'));
        $this->flash('success', 'IROP résolu.');
        back();
    }

    public function close(Request $request, string $id): void
    {
        if (!Auth::can('voyages.irop.manage')) { back(); return; }
        $this->svc->close((int)$id, Auth::user()['id'] ?? null);
        $this->flash('success', 'IROP fermé.');
        $this->redirect('voyages/irop');
    }

    private function fetchAlternativeTrips(int $tripId): array
    {
        $trip = Database::selectOne("SELECT line_id, trip_date FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) return [];
        return Database::select(
            "SELECT id, trip_code, trip_date, departure_time, status
             FROM trips
             WHERE line_id = ?
               AND trip_date BETWEEN ? AND DATE_ADD(?, INTERVAL 2 DAY)
               AND id <> ?
               AND status NOT IN ('annule','annulé','termine','terminé')
             ORDER BY trip_date, departure_time
             LIMIT 20",
            [$trip['line_id'], $trip['trip_date'], $trip['trip_date'], $tripId]
        );
    }
}
