<?php

declare(strict_types=1);

namespace CityBus\Controllers\Flotte;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

/**
 * Incidents (bus / chauffeur) — accidents, pannes, infractions, etc.
 */
final class IncidentController extends Controller
{
    public const TYPES = [
        'accident'    => 'Accident',
        'panne'       => 'Panne',
        'retard'      => 'Retard',
        'infraction'  => 'Infraction',
        'altercation' => 'Altercation',
        'vol'         => 'Vol',
        'autre'       => 'Autre',
    ];

    public const SEVERITIES = [
        'mineur'   => 'Mineur',
        'modere'   => 'Modéré',
        'grave'    => 'Grave',
        'critique' => 'Critique',
    ];

    public function index(Request $request): void
    {
        $type     = trim((string)$request->input('type', ''));
        $severity = trim((string)$request->input('severity', ''));
        $resolved = trim((string)$request->input('resolved', ''));
        $busId    = (int)$request->input('bus_id', 0);
        $driverId = (int)$request->input('driver_id', 0);
        $from     = trim((string)$request->input('from', ''));
        $to       = trim((string)$request->input('to', ''));

        $where  = ['1=1'];
        $params = [];
        if ($type !== '' && isset(self::TYPES[$type])) {
            $where[] = 'i.type = ?'; $params[] = $type;
        }
        if ($severity !== '' && isset(self::SEVERITIES[$severity])) {
            $where[] = 'i.severity = ?'; $params[] = $severity;
        }
        if ($resolved === 'yes') { $where[] = 'i.resolved = 1'; }
        if ($resolved === 'no')  { $where[] = 'i.resolved = 0'; }
        if ($busId > 0)    { $where[] = 'i.bus_id = ?';    $params[] = $busId; }
        if ($driverId > 0) { $where[] = 'i.driver_id = ?'; $params[] = $driverId; }
        if ($from !== '')  { $where[] = 'DATE(i.occurred_at) >= ?'; $params[] = $from; }
        if ($to !== '')    { $where[] = 'DATE(i.occurred_at) <= ?'; $params[] = $to; }

        $page    = max(1, (int)$request->input('page', 1));
        $perPage = 30;
        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $total    = (int)(Database::selectOne("SELECT COUNT(*) AS c FROM incidents i $whereSql", $params)['c'] ?? 0);
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $offset   = ($page - 1) * $perPage;

        $incidents = Database::select(
            "SELECT i.*,
                    b.code AS bus_code, b.plate,
                    d.first_name AS driver_first, d.last_name AS driver_last, d.matricule AS driver_matricule,
                    CONCAT(u.first_name,' ',u.last_name) AS reporter_name
             FROM incidents i
             LEFT JOIN buses b ON b.id = i.bus_id
             LEFT JOIN drivers d ON d.id = i.driver_id
             LEFT JOIN users u ON u.id = i.created_by
             $whereSql
             ORDER BY i.occurred_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $stats = Database::selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN resolved = 1 THEN 1 ELSE 0 END) AS resolved_count,
                SUM(CASE WHEN severity IN ('grave','critique') THEN 1 ELSE 0 END) AS critical_count,
                COALESCE(SUM(cost_fcfa), 0) AS total_cost
             FROM incidents i $whereSql",
            $params
        ) ?: ['total' => 0, 'resolved_count' => 0, 'critical_count' => 0, 'total_cost' => 0];

        $this->view('flotte/incidents/index', [
            'title'      => 'Incidents',
            'incidents'  => $incidents,
            'stats'      => $stats,
            'types'      => self::TYPES,
            'severities' => self::SEVERITIES,
            'buses'      => Database::select("SELECT id, code, plate FROM buses ORDER BY code"),
            'drivers'    => Database::select("SELECT id, first_name, last_name, matricule FROM drivers WHERE deleted_at IS NULL ORDER BY last_name"),
            'filters'    => compact('type', 'severity', 'resolved', 'busId', 'driverId', 'from', 'to'),
            'page'       => $page,
            'lastPage'   => $lastPage,
            'total'      => $total,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('flotte/incidents/form', [
            'title'      => 'Nouvel incident',
            'incident'   => null,
            'types'      => self::TYPES,
            'severities' => self::SEVERITIES,
            'buses'      => Database::select("SELECT id, code, plate FROM buses ORDER BY code"),
            'drivers'    => Database::select("SELECT id, first_name, last_name, matricule FROM drivers WHERE deleted_at IS NULL ORDER BY last_name"),
        ]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('flotte.incidents.create') && !Auth::can('flotte.maintenance.create')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $data = $this->validate($request, [
            'subject_type' => 'required|in:bus,driver',
            'subject_id'   => 'required|integer',
            'type'         => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'severity'     => 'required|in:' . implode(',', array_keys(self::SEVERITIES)),
            'occurred_at'  => 'required',
            'description'  => 'required|min:5',
            'location'     => 'max:150',
            'cost_fcfa'    => 'integer',
            'bus_id'       => 'integer',
            'driver_id'    => 'integer',
        ]);

        // Synchroniser bus_id / driver_id avec subject_*
        $busId    = !empty($data['bus_id'])    ? (int)$data['bus_id']    : null;
        $driverId = !empty($data['driver_id']) ? (int)$data['driver_id'] : null;
        if ($data['subject_type'] === 'bus')    $busId    = (int)$data['subject_id'];
        if ($data['subject_type'] === 'driver') $driverId = (int)$data['subject_id'];

        $id = (int)Database::insert(
            "INSERT INTO incidents
                (subject_type, subject_id, bus_id, driver_id, type, severity,
                 occurred_at, location, description, cost_fcfa, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['subject_type'],
                (int)$data['subject_id'],
                $busId,
                $driverId,
                $data['type'],
                $data['severity'],
                $data['occurred_at'],
                $data['location'] ?? null,
                $data['description'],
                isset($data['cost_fcfa']) ? (int)$data['cost_fcfa'] : 0,
                (int)Auth::id(),
            ]
        );
        AuditLog::record('incident.create', 'incident', $id, [
            'type'         => $data['type'],
            'severity'     => $data['severity'],
            'subject_type' => $data['subject_type'],
            'subject_id'   => (int)$data['subject_id'],
        ]);

        // Escalade auto (GAP-15)
        try {
            (new \CityBus\Services\EscalationService())->escalate(array_merge(
                ['id' => $id, 'occurred_at' => $data['occurred_at']],
                $data
            ));
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::warning('escalation.failed: ' . $e->getMessage());
        }

        $this->flash('success', 'Incident enregistré.');
        $next = trim((string)$request->input('_next', ''));
        redirect($next !== '' ? $next : 'flotte/incidents/' . $id);
    }

    public function show(Request $request, string $id): void
    {
        $incident = Database::selectOne(
            "SELECT i.*,
                    b.code AS bus_code, b.plate, b.brand, b.model,
                    d.first_name AS driver_first, d.last_name AS driver_last, d.matricule AS driver_matricule, d.phone AS driver_phone,
                    CONCAT(u.first_name,' ',u.last_name) AS reporter_name
             FROM incidents i
             LEFT JOIN buses b ON b.id = i.bus_id
             LEFT JOIN drivers d ON d.id = i.driver_id
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.id = ?",
            [(int)$id]
        );
        if (!$incident) { http_response_code(404); $this->view('errors/404'); return; }

        $this->view('flotte/incidents/show', [
            'title'      => 'Incident #' . $incident['id'],
            'incident'   => $incident,
            'types'      => self::TYPES,
            'severities' => self::SEVERITIES,
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        $incident = Database::selectOne("SELECT * FROM incidents WHERE id = ?", [(int)$id]);
        if (!$incident) { http_response_code(404); $this->view('errors/404'); return; }

        $this->view('flotte/incidents/form', [
            'title'      => 'Modifier l\'incident',
            'incident'   => $incident,
            'types'      => self::TYPES,
            'severities' => self::SEVERITIES,
            'buses'      => Database::select("SELECT id, code, plate FROM buses ORDER BY code"),
            'drivers'    => Database::select("SELECT id, first_name, last_name, matricule FROM drivers WHERE deleted_at IS NULL ORDER BY last_name"),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        if (!Auth::can('flotte.incidents.edit') && !Auth::can('flotte.maintenance.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $incId = (int)$id;
        $incident = Database::selectOne("SELECT * FROM incidents WHERE id = ?", [$incId]);
        if (!$incident) { http_response_code(404); $this->view('errors/404'); return; }

        $data = $this->validate($request, [
            'subject_type' => 'required|in:bus,driver',
            'subject_id'   => 'required|integer',
            'type'         => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'severity'     => 'required|in:' . implode(',', array_keys(self::SEVERITIES)),
            'occurred_at'  => 'required',
            'description'  => 'required|min:5',
            'location'     => 'max:150',
            'cost_fcfa'    => 'integer',
        ]);
        $busId    = $data['subject_type'] === 'bus'    ? (int)$data['subject_id'] : null;
        $driverId = $data['subject_type'] === 'driver' ? (int)$data['subject_id'] : null;

        Database::execute(
            "UPDATE incidents
                SET subject_type=?, subject_id=?, bus_id=?, driver_id=?,
                    type=?, severity=?, occurred_at=?, location=?,
                    description=?, cost_fcfa=?
              WHERE id=?",
            [
                $data['subject_type'],
                (int)$data['subject_id'],
                $busId, $driverId,
                $data['type'], $data['severity'],
                $data['occurred_at'], $data['location'] ?? null,
                $data['description'],
                isset($data['cost_fcfa']) ? (int)$data['cost_fcfa'] : 0,
                $incId,
            ]
        );
        AuditLog::record('incident.update', 'incident', $incId);
        $this->flash('success', 'Incident mis à jour.');
        redirect('flotte/incidents/' . $incId);
    }

    public function resolve(Request $request, string $id): void
    {
        if (!Auth::can('flotte.incidents.edit') && !Auth::can('flotte.maintenance.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $notes = trim((string)$request->input('resolution_notes', ''));
        Database::execute(
            "UPDATE incidents
                SET resolved=1, resolved_at=NOW(), resolution_notes=?
              WHERE id=?",
            [$notes ?: null, (int)$id]
        );
        AuditLog::record('incident.resolve', 'incident', (int)$id);
        $this->flash('success', 'Incident marqué comme résolu.');
        back();
    }

    public function reopen(Request $request, string $id): void
    {
        if (!Auth::can('flotte.incidents.edit') && !Auth::can('flotte.maintenance.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        Database::execute(
            "UPDATE incidents SET resolved=0, resolved_at=NULL WHERE id=?", [(int)$id]
        );
        AuditLog::record('incident.reopen', 'incident', (int)$id);
        $this->flash('success', 'Incident rouvert.');
        back();
    }
}
