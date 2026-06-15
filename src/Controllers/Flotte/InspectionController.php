<?php

declare(strict_types=1);

namespace CityBus\Controllers\Flotte;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\InspectionService;

final class InspectionController extends Controller
{
    public function form(Request $request, string $tripId): void
    {
        $trip = Database::selectOne(
            "SELECT tr.*, b.code AS bus_code, b.plate
             FROM trips tr LEFT JOIN buses b ON b.id = tr.bus_id
             WHERE tr.id = ?", [(int)$tripId]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        $existing = (new InspectionService())->get((int)$tripId);
        $this->view('flotte/inspections/form', [
            'title'      => 'Pré-vérification voyage ' . ($trip['trip_code'] ?? ''),
            'trip'       => $trip,
            'inspection' => $existing,
            'fields'     => InspectionService::FIELDS,
        ]);
    }

    public function save(Request $request, string $tripId): void
    {
        if (!Auth::can('inspection.create')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $svc = new InspectionService();
        try {
            $svc->save((int)$tripId, $request->all(), (int)Auth::id());
            $this->flash('success', 'Pré-vérification enregistrée.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        redirect('voyages/' . $tripId);
    }
}
