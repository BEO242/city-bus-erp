<?php

declare(strict_types=1);

namespace CityBus\Controllers\Billetterie;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;
use CityBus\Services\WaitlistService;

final class WaitlistController extends Controller
{
    private WaitlistService $svc;
    public function __construct() { $this->svc = new WaitlistService(); }

    public function show(Request $request, string $tripId): void
    {
        $trip = Database::selectOne(
            "SELECT tr.*, l.code AS line_code, l.name AS line_name
             FROM trips tr LEFT JOIN bus_lines l ON l.id = tr.line_id
             WHERE tr.id = ?", [(int)$tripId]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        $entries = $this->svc->listForTrip((int)$tripId);
        $this->view('billetterie/waitlist/show', [
            'title'   => "Liste d'attente · " . $trip['trip_code'],
            'trip'    => $trip,
            'entries' => $entries,
        ]);
    }

    public function add(Request $request, string $tripId): void
    {
        if (!Auth::can('waitlist.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'passenger_name'  => 'required|min:2|max:120',
            'passenger_phone' => 'required|min:6|max:30',
            'seats_requested' => 'integer',
        ]);
        $id = $this->svc->add((int)$tripId, [
            'name'  => $data['passenger_name'],
            'phone' => $data['passenger_phone'],
            'seats' => $data['seats_requested'] ?? 1,
        ]);
        AuditLog::record('waitlist.add', 'waitlist', $id, ['trip_id' => (int)$tripId]);
        $this->flash('success', 'Inscrit en liste d\'attente.');
        back();
    }

    public function notifyNext(Request $request, string $tripId): void
    {
        if (!Auth::can('waitlist.manage')) { back(); }
        $entry = $this->svc->notifyNext((int)$tripId);
        if ($entry) {
            $this->flash('success', "Notification envoyée à {$entry['passenger_name']}.");
        } else {
            $this->flash('warning', "Aucun passager en attente.");
        }
        back();
    }

    public function cancel(Request $request, string $entryId): void
    {
        if (!Auth::can('waitlist.manage')) { back(); }
        $this->svc->cancel((int)$entryId);
        $this->flash('success', 'Inscription annulée.');
        back();
    }
}
