<?php

declare(strict_types=1);

namespace CityBus\Controllers\Controle;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\ValidationService;

final class ControleController extends Controller
{
    public function __construct(private ValidationService $service = new ValidationService()) {}

    public function index(Request $request): void
    {
        $user = Auth::user();
        $checkpoints = Database::select(
            "SELECT c.*, l.name AS line_name, ct.slug AS city, ct.name AS city_name, a.name AS agency_name
             FROM checkpoints c
             LEFT JOIN bus_lines l ON l.id=c.line_id
             JOIN agencies a ON a.id=c.agency_id
             LEFT JOIN cities ct ON ct.id=a.city_id
             WHERE c.is_active=1 AND (c.agency_id=? OR ? = 1) ORDER BY c.name",
            [$user['agency_id'] ?? 0, Auth::role() === 'admin' ? 1 : 0]
        );
        $today = Database::select(
            "SELECT v.*, t.ticket_number, t.passenger_name, t.seat_number,
                    c.name AS checkpoint_name
             FROM validations v
             JOIN tickets t ON t.id=v.ticket_id
             JOIN checkpoints c ON c.id=v.checkpoint_id
             WHERE v.validated_by=? AND DATE(v.validated_at)=CURDATE()
             ORDER BY v.validated_at DESC LIMIT 20",
            [Auth::id()]
        );
        $this->view('controle/index', [
            'title' => 'Contrôle des tickets',
            'checkpoints' => $checkpoints,
            'today' => $today,
        ]);
    }

    /** Endpoint API : valide un QR. */
    public function validateTicket(Request $request): void
    {
        $payload = $request->isAjax() && !$_POST ? $request->json() : $request->all();
        $qr = trim((string)($payload['qr_code'] ?? ''));
        $checkpointId = (int)($payload['checkpoint_id'] ?? 0);
        $deviceId = $payload['device_id'] ?? null;

        if (!$qr || !$checkpointId) {
            $this->json(['status' => 'invalide', 'message' => 'Données manquantes'], 422);
            return;
        }

        $result = $this->service->validate($qr, $checkpointId, (int)Auth::id(), $deviceId);
        $this->json($result);
    }

    /** Cache offline : liste des QR codes valides d'un voyage. */
    public function tripCache(Request $request, string $tripId): void
    {
        $data = $this->service->tripCache((int)$tripId);
        $this->json($data);
    }

    /** Sync batch des validations offline. */
    public function syncBatch(Request $request): void
    {
        $payload = $request->json() ?? [];
        $items = $payload['validations'] ?? $payload['batch'] ?? [];
        $results = $this->service->syncBatch($items, (int)Auth::id());
        $this->json(['results' => $results]);
    }
}
