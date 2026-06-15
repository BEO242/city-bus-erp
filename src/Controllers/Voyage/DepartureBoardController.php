<?php

declare(strict_types=1);

namespace CityBus\Controllers\Voyage;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Setting;

/**
 * Departure board public — style aéroport.
 * Pas d'auth ; affiche les voyages du jour (et J+1 si tard).
 */
final class DepartureBoardController extends Controller
{
    public function index(Request $request): void
    {
        if (!Setting::getBool('public.departures.view', true)) {
            http_response_code(404); $this->view('errors/404'); return;
        }
        $cities = Database::select(
            "SELECT DISTINCT cd.id, cd.name
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             WHERE tr.trip_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             ORDER BY cd.name"
        );
        $this->view('public/departure_board/index', [
            'title'  => 'Départs du jour',
            'cities' => $cities,
            'rows'   => $this->fetchRows(0),
            'cityId' => 0,
            'cityName' => 'Toutes gares',
        ]);
    }

    public function forCity(Request $request, string $cityId): void
    {
        if (!Setting::getBool('public.departures.view', true)) {
            http_response_code(404); $this->view('errors/404'); return;
        }
        $city = Database::selectOne("SELECT id, name FROM cities WHERE id = ?", [(int)$cityId]);
        if (!$city) { http_response_code(404); $this->view('errors/404'); return; }

        $this->view('public/departure_board/index', [
            'title'    => 'Départs · ' . $city['name'],
            'cities'   => Database::select("SELECT id, name FROM cities ORDER BY name"),
            'rows'     => $this->fetchRows((int)$cityId),
            'cityId'   => (int)$cityId,
            'cityName' => $city['name'],
        ]);
    }

    private function fetchRows(int $cityId): array
    {
        $where = ["tr.trip_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)"];
        $params = [];
        if ($cityId > 0) { $where[] = "l.departure_city_id = ?"; $params[] = $cityId; }
        // Cacher les voyages très en retard ou terminés depuis longtemps
        $where[] = "tr.status IN ('planifie','prep','prepare','en_route','retarde','retardé','boarding','embarquement')";

        $sql = "SELECT tr.id, tr.trip_code, tr.trip_date, tr.departure_time, tr.status, tr.delay_minutes,
                       l.code AS line_code, l.name AS line_name,
                       cd.name AS departure_city, ca.name AS arrival_city,
                       b.code AS bus_code, b.plate AS bus_plate
                FROM trips tr
                JOIN bus_lines l ON l.id = tr.line_id
                JOIN cities cd ON cd.id = l.departure_city_id
                JOIN cities ca ON ca.id = l.arrival_city_id
                LEFT JOIN buses b ON b.id = tr.bus_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY tr.trip_date ASC, tr.departure_time ASC
                LIMIT 50";
        return Database::select($sql, $params);
    }
}
