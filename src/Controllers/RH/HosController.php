<?php

declare(strict_types=1);

namespace CityBus\Controllers\RH;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;
use CityBus\Services\DriverHosService;

final class HosController extends Controller
{
    private DriverHosService $svc;
    public function __construct() { $this->svc = new DriverHosService(); }

    public function dashboard(Request $request): void
    {
        $drivers = Database::select(
            "SELECT id, first_name, last_name, matricule, phone, license_expiry
             FROM drivers WHERE deleted_at IS NULL
             ORDER BY last_name, first_name"
        );
        $rows = [];
        foreach ($drivers as $d) {
            $st = $this->svc->status((int)$d['id']);
            $rows[] = [
                'driver'  => $d,
                'status'  => $st,
            ];
        }
        // Tri : bloquants en haut
        usort($rows, fn($a,$b) => ($b['status']['blocking'] <=> $a['status']['blocking'])
            ?: ($b['status']['week_minutes'] <=> $a['status']['week_minutes']));

        $this->view('rh/hos/dashboard', [
            'title' => 'Conformité HOS chauffeurs',
            'rows'  => $rows,
        ]);
    }

    public function show(Request $request, string $driverId): void
    {
        $driver = Database::selectOne(
            "SELECT * FROM drivers WHERE id = ? AND deleted_at IS NULL", [(int)$driverId]
        );
        if (!$driver) { http_response_code(404); $this->view('errors/404'); return; }

        $status = $this->svc->status((int)$driverId);
        $logs   = $this->svc->recentLogs((int)$driverId, 14);

        $this->view('rh/hos/show', [
            'title'  => 'HOS · ' . $driver['first_name'] . ' ' . $driver['last_name'],
            'driver' => $driver,
            'status' => $status,
            'logs'   => $logs,
        ]);
    }

    public function logEntry(Request $request, string $driverId): void
    {
        if (!Auth::can('hos.edit')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'log_type' => 'required|in:conduite,pause,repos_quotidien,repos_hebdo,disponibilite,autre',
            'start_at' => 'required',
            'end_at'   => '',
            'location' => 'max:150',
            'notes'    => 'max:1000',
        ]);
        $data['driver_id'] = (int)$driverId;
        $id = $this->svc->logEntry($data, (int)Auth::id());
        AuditLog::record('hos.log', 'driver', (int)$driverId, ['log_id' => $id, 'type' => $data['log_type']]);
        $this->flash('success', 'Saisie enregistrée.');
        back();
    }
}
