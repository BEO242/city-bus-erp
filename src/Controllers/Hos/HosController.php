<?php

declare(strict_types=1);

namespace CityBus\Controllers\Hos;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\HosService;

final class HosController extends Controller
{
    private HosService $svc;
    public function __construct() { $this->svc = new HosService(); }

    public function dashboard(Request $request): void
    {
        if (!Auth::can('hos.view')) { back(); return; }
        $this->view('hos/dashboard', [
            'title' => 'HOS — Hours of Service',
            'fleet' => $this->svc->fleetSummary(),
        ]);
    }

    public function driver(Request $request, string $driverId): void
    {
        if (!Auth::can('hos.view')) { back(); return; }
        $driver = Database::selectOne("SELECT * FROM employees WHERE id = ?", [(int)$driverId]);
        if (!$driver) { http_response_code(404); $this->view('errors/404'); return; }

        $from = $request->input('from', date('Y-m-d', strtotime('-7 days')));
        $to   = $request->input('to',   date('Y-m-d'));

        $this->view('hos/driver', [
            'title'      => 'HOS · ' . $driver['first_name'] . ' ' . $driver['last_name'],
            'driver'     => $driver,
            'status'     => $this->svc->status((int)$driverId),
            'logs'       => $this->svc->logsForDriver((int)$driverId, $from . ' 00:00:00', $to . ' 23:59:59'),
            'violations' => $this->svc->violationsForDriver((int)$driverId, 30),
            'from'       => $from,
            'to'         => $to,
        ]);
    }

    public function startDuty(Request $request, string $driverId): void
    {
        if (!Auth::can('hos.log')) { back(); return; }
        $type = $request->input('duty_type', 'drive');
        $tripId = $request->input('trip_id') ? (int)$request->input('trip_id') : null;
        $this->svc->startDuty((int)$driverId, $type, $tripId, $request->input('location'), 'manual');
        $this->flash('success', 'Service démarré.');
        back();
    }

    public function endDuty(Request $request, string $driverId, string $logId): void
    {
        if (!Auth::can('hos.log')) { back(); return; }
        $this->svc->endDuty((int)$logId);
        $this->flash('success', 'Service clôturé.');
        back();
    }

    public function ackViolation(Request $request, string $violationId): void
    {
        if (!Auth::can('hos.violations')) { back(); return; }
        $this->svc->acknowledgeViolation((int)$violationId, (int)(Auth::user()['id'] ?? 0), $request->input('notes'));
        $this->flash('success', 'Violation acquittée.');
        back();
    }
}
