<?php

declare(strict_types=1);

namespace CityBus\Controllers\Driver;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\HosService;
use CityBus\Services\StopTrackingService;

/**
 * PWA chauffeur — vue mobile.
 * Authentification : utilisateur logué + lié à un employé chauffeur (employees.user_id).
 */
final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $driver = $this->resolveDriver();
        if (!$driver) { $this->view('driver/no_link', ['title' => 'Compte non lié']); return; }

        $today = date('Y-m-d');
        $trips = Database::select(
            "SELECT tr.id, tr.trip_code, tr.trip_date, tr.departure_time, tr.status, tr.sub_status,
                    l.code AS line_code, cd.name AS departure_city, ca.name AS arrival_city,
                    b.code AS bus_code, b.plate AS bus_plate
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             LEFT JOIN buses b ON b.id = tr.bus_id
             WHERE tr.driver_id = ?
               AND tr.trip_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY tr.trip_date, tr.departure_time",
            [$driver['id'], $today, $today]
        );

        $hos = (new HosService())->status((int)$driver['id']);

        $this->view('driver/home', [
            'title'       => 'Mes voyages',
            'headerTitle' => $driver['first_name'] . ' ' . $driver['last_name'],
            'tab'         => 'home',
            'driver'      => $driver,
            'trips'       => $trips,
            'hos'         => $hos,
        ]);
    }

    public function trip(Request $request, string $tripId): void
    {
        $driver = $this->resolveDriver();
        if (!$driver) { $this->view('driver/no_link', ['title' => 'Compte non lié']); return; }

        $trip = Database::selectOne(
            "SELECT tr.*, l.code AS line_code, l.name AS line_name,
                    cd.name AS departure_city, ca.name AS arrival_city,
                    b.code AS bus_code, b.plate AS bus_plate
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             LEFT JOIN buses b ON b.id = tr.bus_id
             WHERE tr.id = ? AND tr.driver_id = ?",
            [(int)$tripId, $driver['id']]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        $stops = Database::select(
            "SELECT ts.*, s.name AS stop_name, s.code AS stop_code, s.city_id
             FROM trip_stops ts
             LEFT JOIN stops s ON s.id = ts.stop_id
             WHERE ts.trip_id = ?
             ORDER BY ts.sequence ASC",
            [(int)$tripId]
        );

        $this->view('driver/trip', [
            'title'       => $trip['trip_code'],
            'headerTitle' => 'Voyage ' . $trip['trip_code'],
            'tab'         => 'home',
            'trip'        => $trip,
            'stops'       => $stops,
            'driver'      => $driver,
        ]);
    }

    public function arrive(Request $request, string $tripId, string $stopId): void
    {
        $driver = $this->resolveDriverOrAbort();
        $this->assertOwnsTrip((int)$tripId, $driver['id']);
        try {
            (new StopTrackingService())->recordArrival((int)$tripId, (int)$stopId, null, $request->input('notes'));
            $this->flash('success', 'Arrivée enregistrée.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect('m/driver/trip/' . $tripId);
    }

    public function depart(Request $request, string $tripId, string $stopId): void
    {
        $driver = $this->resolveDriverOrAbort();
        $this->assertOwnsTrip((int)$tripId, $driver['id']);
        try {
            (new StopTrackingService())->recordDeparture((int)$tripId, (int)$stopId, null, [
                'pax_boarded'  => (int)$request->input('pax_boarded', 0),
                'pax_alighted' => (int)$request->input('pax_alighted', 0),
            ]);
            $this->flash('success', 'Départ enregistré.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect('m/driver/trip/' . $tripId);
    }

    public function stops(Request $request): void
    {
        $driver = $this->resolveDriverOrAbort();
        $today = date('Y-m-d');
        $rows = Database::select(
            "SELECT ts.*, s.name AS stop_name, tr.trip_code, tr.id AS trip_id
             FROM trip_stops ts
             JOIN trips tr ON tr.id = ts.trip_id
             LEFT JOIN stops s ON s.id = ts.stop_id
             WHERE tr.driver_id = ? AND tr.trip_date = ?
               AND ts.is_skipped = 0 AND ts.actual_departure IS NULL
             ORDER BY ts.scheduled_arrival ASC LIMIT 20",
            [$driver['id'], $today]
        );
        $this->view('driver/stops', [
            'title'       => 'Mes arrêts',
            'headerTitle' => 'Arrêts à venir',
            'tab'         => 'stops',
            'rows'        => $rows,
        ]);
    }

    public function hos(Request $request): void
    {
        $driver = $this->resolveDriverOrAbort();
        $hos = (new HosService())->status((int)$driver['id']);
        $logs = (new HosService())->logsForDriver((int)$driver['id'], date('Y-m-d', strtotime('-2 days')) . ' 00:00:00', date('Y-m-d') . ' 23:59:59');
        $this->view('driver/hos', [
            'title' => 'HOS',
            'headerTitle' => 'Mes heures',
            'tab' => 'hos',
            'hos' => $hos,
            'logs' => $logs,
            'driver' => $driver,
        ]);
    }

    public function startDuty(Request $request): void
    {
        $driver = $this->resolveDriverOrAbort();
        $type = $request->input('duty_type', 'drive');
        (new HosService())->startDuty((int)$driver['id'], $type, null, $request->input('location'), 'mobile');
        $this->flash('success', 'Service démarré.');
        $this->redirect('m/driver/hos');
    }

    public function endDuty(Request $request, string $logId): void
    {
        $driver = $this->resolveDriverOrAbort();
        $log = Database::selectOne("SELECT driver_id FROM driver_duty_logs WHERE id = ?", [(int)$logId]);
        if ($log && (int)$log['driver_id'] === (int)$driver['id']) {
            (new HosService())->endDuty((int)$logId);
            $this->flash('success', 'Service clôturé.');
        }
        $this->redirect('m/driver/hos');
    }

    public function profile(Request $request): void
    {
        $driver = $this->resolveDriverOrAbort();
        $this->view('driver/profile', [
            'title' => 'Profil',
            'headerTitle' => 'Mon profil',
            'tab' => 'profile',
            'driver' => $driver,
        ]);
    }

    private function resolveDriver(): ?array
    {
        $user = Auth::user();
        if (!$user) return null;
        return Database::selectOne(
            "SELECT * FROM employees WHERE user_id = ? LIMIT 1",
            [$user['id']]
        );
    }

    private function resolveDriverOrAbort(): array
    {
        $d = $this->resolveDriver();
        if (!$d) {
            $this->view('driver/no_link', ['title' => 'Compte non lié']);
            exit;
        }
        return $d;
    }

    private function assertOwnsTrip(int $tripId, int $driverId): void
    {
        $owns = Database::selectOne("SELECT id FROM trips WHERE id = ? AND driver_id = ?", [$tripId, $driverId]);
        if (!$owns) { http_response_code(403); $this->view('errors/403'); exit; }
    }
}
