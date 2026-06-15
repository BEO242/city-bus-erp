<?php

declare(strict_types=1);

namespace CityBus\Controllers\Voyage;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;
use CityBus\Services\SchedulePatternService;

final class SchedulePatternController extends Controller
{
    private SchedulePatternService $svc;
    public function __construct() { $this->svc = new SchedulePatternService(); }

    public function index(Request $request): void
    {
        $patterns = Database::select(
            "SELECT sp.*, l.code AS line_code, l.name AS line_name,
                    b.code AS bus_code, b.plate
             FROM schedule_patterns sp
             JOIN bus_lines l ON l.id = sp.line_id
             LEFT JOIN buses b ON b.id = sp.bus_id
             ORDER BY sp.is_active DESC, sp.line_id, sp.departure_time"
        );
        $this->view('voyages/schedule_patterns/index', [
            'title' => 'Horaires récurrents',
            'patterns' => $patterns,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('voyages/schedule_patterns/form', [
            'title'   => 'Nouveau pattern',
            'pattern' => null,
            'lines'   => Database::select("SELECT id, code, name FROM bus_lines WHERE is_active = 1 ORDER BY code"),
            'buses'   => Database::select("SELECT id, code, plate FROM buses ORDER BY code"),
        ]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('voyages.schedule.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'label'         => 'required|min:3|max:120',
            'line_id'       => 'required|integer',
            'departure_time'=> 'required',
            'valid_from'    => 'required|date',
        ]);
        $days = $request->input('days_of_week', []);
        if (is_array($days)) $days = implode(',', array_map('intval', $days));
        if (!$days) $days = '1,2,3,4,5,6,7';

        $id = (int)Database::insert(
            "INSERT INTO schedule_patterns
                (label, line_id, bus_id, primary_driver_id, days_of_week, departure_time, arrival_time,
                 base_price_fcfa, valid_from, valid_until, is_active, auto_generate_days, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['label'], (int)$data['line_id'],
                $request->input('bus_id') ? (int)$request->input('bus_id') : null,
                $request->input('primary_driver_id') ? (int)$request->input('primary_driver_id') : null,
                $days,
                $data['departure_time'],
                $request->input('arrival_time') ?: null,
                $request->input('base_price_fcfa') ? (int)$request->input('base_price_fcfa') : null,
                $data['valid_from'],
                $request->input('valid_until') ?: null,
                (int)$request->input('is_active', 1),
                (int)$request->input('auto_generate_days', 14),
                $request->input('notes'),
            ]
        );
        AuditLog::record('schedule_pattern.create', 'schedule_pattern', $id);
        $this->flash('success', 'Pattern créé. Vous pouvez maintenant lancer la génération.');
        redirect('voyages/schedules');
    }

    public function generate(Request $request, string $id): void
    {
        if (!Auth::can('voyages.schedule.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $created = $this->svc->generate((int)$id);
        $this->flash('success', "$created voyage(s) généré(s).");
        back();
    }

    public function generateAll(Request $request): void
    {
        if (!Auth::can('voyages.schedule.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $r = $this->svc->generateAll();
        $this->flash('success', "Génération : {$r['created']} voyage(s) sur {$r['patterns']} pattern(s).");
        back();
    }

    public function destroy(Request $request, string $id): void
    {
        if (!Auth::can('voyages.schedule.manage')) { back(); }
        Database::execute("UPDATE schedule_patterns SET is_active = 0 WHERE id = ?", [(int)$id]);
        AuditLog::record('schedule_pattern.deactivate', 'schedule_pattern', (int)$id);
        $this->flash('success', 'Pattern désactivé.');
        back();
    }
}
