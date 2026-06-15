<?php

declare(strict_types=1);

namespace CityBus\Controllers\Api;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\PnrService;
use CityBus\Services\OdFareService;
use CityBus\Services\CustomerService;

final class ApiV2Controller extends Controller
{
    private function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-API-Version: v2');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function checkIdempotency(Request $request): ?array
    {
        $key = $request->header('Idempotency-Key');
        if (empty($key)) return null;
        $existing = Database::selectOne("SELECT * FROM api_idempotency_keys WHERE `key` = ?", [$key]);
        if ($existing && strtotime($existing['expires_at']) > time()) {
            return [
                'response' => json_decode($existing['response'], true),
                'status'   => (int)$existing['response_status'],
            ];
        }
        return null;
    }

    private function saveIdempotency(Request $request, array $response, int $status): void
    {
        $key = $request->header('Idempotency-Key');
        if (empty($key)) return;
        Database::execute(
            "INSERT INTO api_idempotency_keys (`key`, response, response_status, expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE response = VALUES(response), response_status = VALUES(response_status)",
            [$key, json_encode($response), $status, date('Y-m-d H:i:s', time() + 86400)]
        );
    }

    public function search(Request $request): void
    {
        $from = (int)$request->input('from');
        $to   = (int)$request->input('to');
        $date = $request->input('date', date('Y-m-d'));

        $trips = Database::select(
            "SELECT t.id, t.trip_code, t.trip_date, t.departure_time, t.status, l.code AS line_code,
                    cd.name AS departure_city, ca.name AS arrival_city
             FROM trips t
             JOIN bus_lines l ON l.id = t.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             WHERE l.departure_city_id = ? AND l.arrival_city_id = ?
               AND t.trip_date = ? AND t.status NOT IN ('annule','annulé')
             ORDER BY t.departure_time",
            [$from, $to, $date]
        );
        foreach ($trips as &$t) {
            $t['inventory'] = Database::select(
                "SELECT class_code, capacity, sold_count, price_fcfa FROM trip_inventory WHERE trip_id = ?",
                [$t['id']]
            );
        }
        $this->json(['data' => $trips, 'count' => count($trips)]);
    }

    public function createBooking(Request $request): void
    {
        $cached = $this->checkIdempotency($request);
        if ($cached) { $this->json($cached['response'], $cached['status']); return; }

        $data = json_decode(file_get_contents('php://input'), true) ?: $request->all();
        if (!isset($data['trip_id'], $data['contact'], $data['passengers'])) {
            $this->json(['error' => 'Missing required fields'], 400);
        }

        try {
            $custSvc = new CustomerService();
            $custId = $custSvc->findOrCreateFromTicket([
                'phone' => $data['contact']['phone'] ?? '',
                'name'  => $data['contact']['name'] ?? '',
                'email' => $data['contact']['email'] ?? '',
            ]);

            $pnrSvc = new PnrService();
            $pnrId = $pnrSvc->createHeader([
                'customer_id'   => $custId,
                'contact_name'  => $data['contact']['name'] ?? '',
                'contact_phone' => $data['contact']['phone'] ?? '',
                'contact_email' => $data['contact']['email'] ?? '',
                'channel'       => 'partner',
            ]);

            foreach ($data['passengers'] as $p) {
                $paxId = $pnrSvc->addPassenger($pnrId, $p);
                $pnrSvc->addSegment($pnrId, $paxId, [
                    'trip_id'           => $data['trip_id'],
                    'boarding_stop_id'  => $data['boarding_stop_id'] ?? null,
                    'alighting_stop_id' => $data['alighting_stop_id'] ?? null,
                    'booking_class'     => $data['booking_class'] ?? 'M',
                    'pax_type'          => $p['pax_type'] ?? 'ADT',
                    'passenger_name'    => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
                ]);
            }
            $pnrSvc->confirm($pnrId);
            $pnr = $pnrSvc->findByCode(Database::selectOne("SELECT pnr_code FROM reservations WHERE id = ?", [$pnrId])['pnr_code']);

            $response = ['data' => $pnr];
            $this->saveIdempotency($request, $response, 201);
            $this->json($response, 201);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
    }

    public function getPnr(Request $request, string $pnrCode): void
    {
        $pnr = (new PnrService())->findByCode($pnrCode);
        if (!$pnr) $this->json(['error' => 'Not found'], 404);
        $this->json(['data' => $pnr]);
    }

    public function cancelPnr(Request $request, string $pnrCode): void
    {
        $pnr = Database::selectOne("SELECT id FROM reservations WHERE pnr_code = ?", [$pnrCode]);
        if (!$pnr) $this->json(['error' => 'Not found'], 404);
        try {
            (new PnrService())->cancel((int)$pnr['id'], $request->input('reason', 'API cancel'));
            $this->json(['status' => 'cancelled', 'pnr' => $pnrCode]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
    }

    public function openapi(Request $request): void
    {
        $this->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'City Bus API',
                'version' => '2.0.0',
                'description' => 'API publique City Bus — recherche, réservation, gestion PNR',
            ],
            'servers' => [['url' => url('api/v2')]],
            'paths' => [
                '/search' => ['get' => ['summary' => 'Search trips', 'parameters' => [
                    ['name' => 'from', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['name' => 'to',   'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['name' => 'date', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
                ]]],
                '/bookings' => ['post' => ['summary' => 'Create PNR', 'requestBody' => [
                    'content' => ['application/json' => ['schema' => ['type' => 'object']]],
                ]]],
                '/bookings/{pnrCode}' => [
                    'get' => ['summary' => 'Retrieve PNR'],
                    'delete' => ['summary' => 'Cancel PNR'],
                ],
            ],
        ]);
    }
}
