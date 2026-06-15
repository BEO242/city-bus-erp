<?php

declare(strict_types=1);

namespace CityBus\Controllers\Voyage;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\TripStop;
use CityBus\Models\TripInventory;

/**
 * Trip briefing — fiche de voyage consolidée pour chauffeur / régulateur.
 * Web view + version imprimable (A4).
 */
final class BriefingController extends Controller
{
    public function show(Request $request, string $tripId): void
    {
        if (!Auth::can('voyages.briefing.view')) { back(); return; }
        $data = $this->collect((int)$tripId);
        if (!$data) { http_response_code(404); $this->view('errors/404'); return; }
        $data['title'] = "Briefing · {$data['trip']['trip_code']}";
        $this->view('voyages/briefing/show', $data);
    }

    public function printable(Request $request, string $tripId): void
    {
        if (!Auth::can('voyages.briefing.print')) { back(); return; }
        $data = $this->collect((int)$tripId);
        if (!$data) { http_response_code(404); $this->view('errors/404'); return; }
        $data['title'] = "Briefing imprimable · {$data['trip']['trip_code']}";
        $this->view('voyages/briefing/print', $data);
    }

    private function collect(int $tripId): ?array
    {
        $trip = Database::selectOne(
            "SELECT tr.*, l.code AS line_code, l.name AS line_name, l.distance_km, l.duration_hours,
                    cd.name AS departure_city, ca.name AS arrival_city,
                    b.code AS bus_code, b.plate AS bus_plate, b.brand AS bus_brand, b.model AS bus_model, b.seats AS bus_seats,
                    CONCAT(e.first_name,' ',e.last_name) AS driver_name, e.phone AS driver_phone, e.license_number AS driver_license,
                    CONCAT(e2.first_name,' ',e2.last_name) AS conductor_name, e2.phone AS conductor_phone
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             LEFT JOIN buses b ON b.id = tr.bus_id
             LEFT JOIN drivers e ON e.id = tr.driver_id
             LEFT JOIN drivers e2 ON e2.id = tr.convoyeur_id
             WHERE tr.id = ?", [$tripId]
        );
        if (!$trip) return null;

        $stops      = TripStop::forTrip($tripId);
        $inventory  = TripInventory::forTrip($tripId);
        $totalCap   = TripInventory::totalCapacity($tripId);
        $totalSold  = TripInventory::totalSold($tripId);

        // Documents
        $documents = Database::select(
            "SELECT id, doc_type, file_path, original_name, uploaded_at
             FROM trip_documents WHERE trip_id = ? ORDER BY uploaded_at DESC", [$tripId]
        );

        // Inspections (pré-départ)
        $inspections = Database::select(
            "SELECT id, status, inspected_at, observations
             FROM trip_inspections WHERE trip_id = ? ORDER BY inspected_at DESC LIMIT 5", [$tripId]
        );

        // Météo / consignes (depuis settings ou notes)
        return [
            'trip'        => $trip,
            'stops'       => $stops,
            'inventory'   => $inventory,
            'totalCap'    => $totalCap,
            'totalSold'   => $totalSold,
            'documents'   => $documents,
            'inspections' => $inspections,
        ];
    }
}
