<?php

declare(strict_types=1);

namespace CityBus\Controllers\Flotte;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\MaintenanceOrder;
use CityBus\Models\AuditLog;

final class MaintenanceController extends Controller
{
    public function index(Request $request): void
    {
        $statusFilter = trim((string)$request->input('status', ''));
        $typeFilter = trim((string)$request->input('type', ''));
        $busFilter = (int)$request->input('bus_id', 0);
        $dateFrom = trim((string)$request->input('date_from', ''));
        $dateTo = trim((string)$request->input('date_to', ''));

        $where = [];
        $params = [];

        if (in_array($statusFilter, array_keys(MaintenanceOrder::STATUSES), true)) {
            $where[] = 'mo.status = ?';
            $params[] = $statusFilter;
        }
        if (in_array($typeFilter, ['preventive', 'corrective'], true)) {
            $where[] = 'mo.type = ?';
            $params[] = $typeFilter;
        }
        if ($busFilter > 0) {
            $where[] = 'mo.bus_id = ?';
            $params[] = $busFilter;
        }
        if ($dateFrom !== '') {
            $where[] = 'DATE(COALESCE(mo.done_at, mo.scheduled_at, mo.created_at)) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'DATE(COALESCE(mo.done_at, mo.scheduled_at, mo.created_at)) <= ?';
            $params[] = $dateTo;
        }

        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        $page    = max(1, (int)$request->input('page', 1));
        $perPage = 30;
        $total   = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM maintenance_orders mo $whereSql", $params
        )['c'] ?? 0);
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $offset   = ($page - 1) * $perPage;

        $orders = Database::select(
            "SELECT mo.*, b.code AS bus_code, b.plate, e.first_name AS meca_first, e.last_name AS meca_last
             FROM maintenance_orders mo
             JOIN buses b ON b.id=mo.bus_id
             LEFT JOIN employees e ON e.id=mo.mechanic_id
             $whereSql
             ORDER BY COALESCE(mo.done_at, mo.scheduled_at, mo.created_at) DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $stats = Database::selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'termine' THEN 1 ELSE 0 END) AS done,
                SUM(CASE WHEN type = 'corrective' THEN 1 ELSE 0 END) AS unplanned,
                SUM(CASE WHEN type = 'preventive' THEN 1 ELSE 0 END) AS planned
             FROM maintenance_orders mo
             $whereSql",
            $params
        ) ?: ['total' => 0, 'done' => 0, 'unplanned' => 0, 'planned' => 0];

        $this->view('flotte/maintenance/index', [
            'title' => 'Maintenance',
            'orders' => $orders,
            'stats' => $stats,
            'buses' => Database::select("SELECT id, code, plate FROM buses ORDER BY code"),
            'statusFilter' => $statusFilter,
            'typeFilter' => $typeFilter,
            'busFilter' => $busFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'page'         => $page,
            'perPage'      => $perPage,
            'total'        => $total,
            'lastPage'     => $lastPage,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('flotte/maintenance/form', [
            'title' => 'Nouvel ordre',
            'order' => null,
            'buses' => Database::select("SELECT * FROM buses ORDER BY code"),
            'mechanics' => Database::select("SELECT * FROM employees WHERE position='mecanicien' AND status='actif'"),
        ]);
    }

    public function store(Request $request): void
    {
        $entryMode = trim((string)$request->input('entry_mode', 'realized'));
        if (!in_array($entryMode, ['realized', 'planned'], true)) {
            $entryMode = 'realized';
        }

        $data = $this->validate($request, [
            'bus_id'         => 'required|integer',
            'type'           => 'required|in:preventive,corrective',
            'description'    => 'required|min:5',
            'estimated_cost' => 'integer',
            'actual_cost'    => 'integer',
            'scheduled_at'   => 'date',
            'done_on'        => 'date',
            'mechanic_id'    => 'integer',
        ]);

        if (isset($data['mechanic_id']) && (int)$data['mechanic_id'] === 0) {
            $data['mechanic_id'] = null;
        }

        if ($entryMode === 'planned') {
            if (empty($data['scheduled_at'])) {
                $this->flash('danger', 'La date prévue est obligatoire pour une maintenance planifiée.');
                back();
                return;
            }
            $data['status'] = 'planifie';
            $data['actual_cost'] = null;
            $data['done_at'] = null;
        } else {
            $doneOn = trim((string)$request->input('done_on', ''));
            $data['status'] = 'termine';
            $data['done_at'] = $doneOn !== '' ? ($doneOn . ' 12:00:00') : date('Y-m-d H:i:s');

            if (empty($data['actual_cost']) && !empty($data['estimated_cost'])) {
                $data['actual_cost'] = (int)$data['estimated_cost'];
            }
            $data['scheduled_at'] = null;
        }

        unset($data['done_on']);
        $data['created_by'] = \CityBus\Core\Auth::id();
        $id = MaintenanceOrder::create($data);
        AuditLog::record('maintenance.create', 'maintenance_order', $id);
        $this->flash('success', $entryMode === 'planned' ? 'Ordre planifié créé.' : 'Maintenance réalisée enregistrée.');
        $next = trim((string)$request->input('_next', ''));
        redirect($next !== '' ? $next : 'flotte/maintenance');
    }

    public function changeStatus(Request $request, string $id): void
    {
        $status = $request->input('status');
        if (!array_key_exists($status, MaintenanceOrder::STATUSES)) { back(); return; }
        $update = ['status' => $status];
        if ($status === 'en_cours') $update['started_at'] = date('Y-m-d H:i:s');
        if ($status === 'termine')  {
            $update['done_at'] = date('Y-m-d H:i:s');
            if ($actual = $request->input('actual_cost')) $update['actual_cost'] = (int)$actual;
        }
        MaintenanceOrder::update((int)$id, $update);
        $this->flash('success', 'Statut mis à jour.');
        back();
    }

    public function edit(Request $request, string $id): void
    {
        $order = MaintenanceOrder::find((int)$id);
        if (!$order) { http_response_code(404); $this->view('errors/404'); return; }
        $this->view('flotte/maintenance/form', [
            'title'      => 'Modifier l\'ordre de maintenance',
            'order'      => $order,
            'buses'      => Database::select("SELECT * FROM buses ORDER BY code"),
            'mechanics'  => Database::select("SELECT * FROM employees WHERE position='mecanicien' AND status='actif'"),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $order = Database::selectOne(
            "SELECT mo.*, b.code AS bus_code, b.plate, b.brand, b.model,
                    e.first_name AS meca_first, e.last_name AS meca_last, e.matricule AS meca_matricule,
                    CONCAT(u.first_name,' ',u.last_name) AS created_by_name
               FROM maintenance_orders mo
               JOIN buses b ON b.id=mo.bus_id
               LEFT JOIN employees e ON e.id=mo.mechanic_id
               LEFT JOIN users u ON u.id=mo.created_by
              WHERE mo.id=?",
            [(int)$id]
        );
        if (!$order) { http_response_code(404); $this->view('errors/404'); return; }

        $this->view('flotte/maintenance/show', [
            'title' => 'Ordre de maintenance #' . $order['id'],
            'order' => $order,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $order = MaintenanceOrder::find((int)$id);
        if (!$order) { http_response_code(404); $this->view('errors/404'); return; }

        $data = $this->validate($request, [
            'bus_id'         => 'required|integer',
            'type'           => 'required|in:preventive,corrective',
            'description'    => 'required|min:5',
            'estimated_cost' => 'integer',
            'actual_cost'    => 'integer',
            'scheduled_at'   => 'date',
            'mechanic_id'    => 'integer',
        ]);
        if (isset($data['mechanic_id']) && (int)$data['mechanic_id'] === 0) {
            $data['mechanic_id'] = null;
        }
        MaintenanceOrder::update((int)$id, $data);
        AuditLog::record('maintenance.update', 'maintenance_order', (int)$id);
        $this->flash('success', 'Ordre de maintenance mis à jour.');
        redirect('flotte/maintenance');
    }
}
