<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Models\AuditLog;

final class InspectionService
{
    public const FIELDS = [
        'fluids_ok'        => 'Niveaux fluides',
        'tires_ok'         => 'Pneus',
        'lights_ok'        => 'Phares & feux',
        'brakes_ok'        => 'Freins',
        'extinguisher_ok'  => 'Extincteur',
        'first_aid_kit_ok' => 'Trousse de secours',
        'triangle_vest_ok' => 'Triangle & gilet',
        'seat_belts_ok'    => 'Ceintures',
        'cleanliness_ok'   => 'Propreté',
        'documents_ok'     => 'Carte grise / assurance / CT',
    ];

    public function save(int $tripId, array $data, int $userId): int
    {
        $trip = Database::selectOne("SELECT bus_id FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) throw new \RuntimeException('Voyage introuvable.');

        $checklist = [];
        $passCount = 0;
        $failCount = 0;
        foreach (array_keys(self::FIELDS) as $f) {
            $checklist[$f] = !empty($data[$f]) ? 1 : 0;
            if ($checklist[$f]) $passCount++;
            else $failCount++;
        }
        $status = $failCount === 0 ? 'pass' : ($failCount <= 2 ? 'pass_with_remarks' : 'fail');

        // Upsert (un voyage = une inspection)
        $existing = Database::selectOne("SELECT id FROM pre_trip_inspections WHERE trip_id = ?", [$tripId]);
        if ($existing) {
            Database::execute(
                "UPDATE pre_trip_inspections SET
                    inspected_at=NOW(), inspected_by=?, driver_id=?,
                    fluids_ok=?, tires_ok=?, lights_ok=?, brakes_ok=?,
                    extinguisher_ok=?, first_aid_kit_ok=?, triangle_vest_ok=?,
                    seat_belts_ok=?, cleanliness_ok=?, documents_ok=?,
                    overall_status=?, remarks=?, odometer_km=?, fuel_level_pct=?
                  WHERE id=?",
                [
                    $userId, !empty($data['driver_id']) ? (int)$data['driver_id'] : null,
                    $checklist['fluids_ok'], $checklist['tires_ok'], $checklist['lights_ok'], $checklist['brakes_ok'],
                    $checklist['extinguisher_ok'], $checklist['first_aid_kit_ok'], $checklist['triangle_vest_ok'],
                    $checklist['seat_belts_ok'], $checklist['cleanliness_ok'], $checklist['documents_ok'],
                    $status, $data['remarks'] ?? null,
                    !empty($data['odometer_km']) ? (int)$data['odometer_km'] : null,
                    !empty($data['fuel_level_pct']) ? (int)$data['fuel_level_pct'] : null,
                    (int)$existing['id'],
                ]
            );
            $id = (int)$existing['id'];
        } else {
            $id = (int)Database::insert(
                "INSERT INTO pre_trip_inspections
                    (trip_id, bus_id, driver_id, inspected_at, inspected_by,
                     fluids_ok, tires_ok, lights_ok, brakes_ok,
                     extinguisher_ok, first_aid_kit_ok, triangle_vest_ok,
                     seat_belts_ok, cleanliness_ok, documents_ok,
                     overall_status, remarks, odometer_km, fuel_level_pct)
                 VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $tripId, (int)$trip['bus_id'],
                    !empty($data['driver_id']) ? (int)$data['driver_id'] : null,
                    $userId,
                    $checklist['fluids_ok'], $checklist['tires_ok'], $checklist['lights_ok'], $checklist['brakes_ok'],
                    $checklist['extinguisher_ok'], $checklist['first_aid_kit_ok'], $checklist['triangle_vest_ok'],
                    $checklist['seat_belts_ok'], $checklist['cleanliness_ok'], $checklist['documents_ok'],
                    $status, $data['remarks'] ?? null,
                    !empty($data['odometer_km']) ? (int)$data['odometer_km'] : null,
                    !empty($data['fuel_level_pct']) ? (int)$data['fuel_level_pct'] : null,
                ]
            );
        }
        AuditLog::record('inspection.save', 'pre_trip_inspection', $id, ['status' => $status, 'fail' => $failCount]);
        return $id;
    }

    public function get(int $tripId): ?array
    {
        return Database::selectOne("SELECT * FROM pre_trip_inspections WHERE trip_id = ?", [$tripId]);
    }
}
