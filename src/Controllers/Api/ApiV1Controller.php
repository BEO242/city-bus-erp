<?php

declare(strict_types=1);

namespace CityBus\Controllers\Api;

use CityBus\Core\Database;
use CityBus\Core\Request;

/**
 * API REST publique en lecture seule + endpoint de validation de billet.
 * Toutes les routes /api/v1/* passent par ApiTokenMiddleware.
 */
final class ApiV1Controller
{
    public function ping(Request $request): void
    {
        $this->json([
            'service' => 'citybus-api',
            'version' => '1.0',
            'time'    => date('c'),
        ]);
    }

    public function trips(Request $request): void
    {
        $date = trim((string)$request->input('date', date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error(400, 'invalid_date', 'Format YYYY-MM-DD attendu.');
            return;
        }
        $rows = Database::select(
            "SELECT id, trip_code, trip_date, departure_scheduled, arrival_scheduled,
                    status, line_id, bus_id
               FROM trips
              WHERE trip_date = ?
              ORDER BY departure_scheduled
              LIMIT 200",
            [$date]
        );
        $this->json(['date' => $date, 'count' => count($rows), 'trips' => $rows]);
    }

    public function buses(Request $request): void
    {
        $rows = Database::select(
            "SELECT id, code, plate, brand, model, seats, status
               FROM buses
              ORDER BY code
              LIMIT 500"
        );
        $this->json(['count' => count($rows), 'buses' => $rows]);
    }

    public function ticketByCode(Request $request, string $code): void
    {
        $row = Database::selectOne(
            "SELECT id, ticket_number, trip_id, status, price_fcfa, passenger_name,
                    passenger_phone, seat_number, ticket_type, passenger_category, travel_class
               FROM tickets
              WHERE ticket_number = ? AND deleted_at IS NULL",
            [trim($code)]
        );
        if (!$row) {
            $this->error(404, 'not_found', 'Billet introuvable.');
            return;
        }
        $this->json(['ticket' => $row]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function error(int $status, string $code, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $code, 'message' => $message]);
    }
}
