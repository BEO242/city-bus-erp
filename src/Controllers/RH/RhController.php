<?php

declare(strict_types=1);

namespace CityBus\Controllers\RH;

use CityBus\Controllers\Controller;
use CityBus\Controllers\RH\RhPositionController;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Models\AuditLog;
use CityBus\Models\Employee;
use CityBus\Services\PayrollService;
use CityBus\Services\PdfService;

final class RhController extends Controller
{
    public function __construct(
        private PayrollService $service = new PayrollService(),
        private PdfService $pdf = new PdfService(),
    ) {}

    // ─── Planning (schedules) ─────────────────────────────────────────────
    /** Vue planning hebdomadaire — grille employés × jours. */
    public function schedule(Request $request): void
    {
        $weekStart = trim((string)$request->input('week', date('Y-m-d', strtotime('monday this week'))));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
        }
        $startTs = strtotime($weekStart);
        $weekEnd = date('Y-m-d', $startTs + 6 * 86400);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = date('Y-m-d', $startTs + $i * 86400);
        }

        $positionFilter = trim((string)$request->input('position', ''));
        $whereEmp = ['e.deleted_at IS NULL', "e.status = 'actif'"];
        $paramsEmp = [];
        if ($positionFilter !== '') {
            $whereEmp[] = 'e.position = ?'; $paramsEmp[] = $positionFilter;
        }
        $employees = Database::select(
            "SELECT e.id, e.matricule, e.first_name, e.last_name, e.position
             FROM employees e
             WHERE " . implode(' AND ', $whereEmp) . "
             ORDER BY e.position, e.last_name, e.first_name",
            $paramsEmp
        );

        $schedules = Database::select(
            "SELECT s.*, tr.trip_code, l.code AS line_code
             FROM schedules s
             LEFT JOIN trips tr ON tr.id = s.trip_id
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             WHERE s.schedule_date BETWEEN ? AND ?",
            [$weekStart, $weekEnd]
        );
        // Indexer par employee_id|date
        $grid = [];
        foreach ($schedules as $s) {
            $grid[$s['employee_id'] . '|' . $s['schedule_date']][] = $s;
        }

        $positions = Database::select(
            "SELECT DISTINCT position FROM employees WHERE deleted_at IS NULL AND status='actif' ORDER BY position"
        );

        $this->view('rh/schedule/index', [
            'title'          => 'Planning',
            'weekStart'      => $weekStart,
            'weekEnd'        => $weekEnd,
            'days'           => $days,
            'employees'      => $employees,
            'grid'           => $grid,
            'positions'      => array_column($positions, 'position'),
            'positionFilter' => $positionFilter,
        ]);
    }

    public function storeSchedule(Request $request): void
    {
        if (!\CityBus\Core\Auth::can('rh.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $data = $this->validate($request, [
            'employee_id'   => 'required|integer',
            'schedule_date' => 'required|date',
            'shift_type'    => 'required|in:voyage,agence,conge,absent',
            'trip_id'       => 'integer',
        ]);
        Database::insert(
            "INSERT INTO schedules (employee_id, trip_id, schedule_date, shift_type, notes)
             VALUES (?,?,?,?,?)",
            [
                (int)$data['employee_id'],
                !empty($data['trip_id']) ? (int)$data['trip_id'] : null,
                $data['schedule_date'],
                $data['shift_type'],
                $request->input('notes') ?: null,
            ]
        );
        AuditLog::record('schedule.create', 'schedule', null, [
            'employee_id' => (int)$data['employee_id'],
            'date'        => $data['schedule_date'],
            'shift'       => $data['shift_type'],
        ]);
        $this->flash('success', 'Affectation enregistrée.');
        back();
    }

    public function destroySchedule(Request $request, string $id): void
    {
        if (!\CityBus\Core\Auth::can('rh.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        Database::execute("DELETE FROM schedules WHERE id=?", [(int)$id]);
        AuditLog::record('schedule.delete', 'schedule', (int)$id);
        $this->flash('success', 'Affectation supprimée.');
        back();
    }

    /** Tableau de bord RH : effectifs, masse salariale, alertes. */
    public function dashboard(Request $request): void
    {
        $month = (int)$request->input('month', (int)date('n'));
        $year  = (int)$request->input('year', (int)date('Y'));

        $totals = Database::selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status='actif'   THEN 1 ELSE 0 END) AS actifs,
                SUM(CASE WHEN status='inactif' THEN 1 ELSE 0 END) AS inactifs,
                SUM(CASE WHEN status='conge'   THEN 1 ELSE 0 END) AS conges
             FROM employees WHERE deleted_at IS NULL"
        ) ?: ['total'=>0,'actifs'=>0,'inactifs'=>0,'conges'=>0];

        $byPosition = Database::select(
            "SELECT position, COUNT(*) AS n
             FROM employees
             WHERE deleted_at IS NULL AND status='actif'
             GROUP BY position
             ORDER BY n DESC"
        );

        $byAgency = Database::select(
            "SELECT a.name AS agency_name, COUNT(e.id) AS n
             FROM agencies a
             LEFT JOIN employees e ON e.agency_id=a.id AND e.deleted_at IS NULL AND e.status='actif'
             WHERE a.is_active = 1
             GROUP BY a.id, a.name
             ORDER BY n DESC"
        );

        $payrollMonth = Database::selectOne(
            "SELECT
                COUNT(*) AS payslips,
                COALESCE(SUM(net_amount),0) AS net_total,
                COALESCE(SUM(salary_base + bonus_amount),0) AS gross_total,
                SUM(CASE WHEN is_paid=1 THEN 1 ELSE 0 END) AS paid_count
             FROM payroll WHERE month=? AND year=?",
            [$month, $year]
        ) ?: ['payslips'=>0,'net_total'=>0,'gross_total'=>0,'paid_count'=>0];

        // Évolution masse salariale 6 derniers mois
        $payrollHistory = Database::select(
            "SELECT year, month, COALESCE(SUM(net_amount),0) AS net_total, COUNT(*) AS payslips
             FROM payroll
             WHERE STR_TO_DATE(CONCAT(year,'-',LPAD(month,2,'0'),'-01'),'%Y-%m-%d') >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY year, month
             ORDER BY year ASC, month ASC"
        );

        // Alertes RH : permis et visite médicale
        $licenseDays = max(1, \CityBus\Core\Setting::getInt('rh.license_alert_days', 45));
        $medicalDays = max(1, \CityBus\Core\Setting::getInt('rh.medical_cert_alert_days', 30));
        $licenseAlerts = Database::select(
            "SELECT id, CONCAT(first_name,' ',last_name) AS name, license_expiry
             FROM drivers
             WHERE deleted_at IS NULL AND license_expiry IS NOT NULL
               AND license_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY license_expiry ASC LIMIT 10",
            [$licenseDays]
        );
        $medicalAlerts = Database::select(
            "SELECT id, CONCAT(first_name,' ',last_name) AS name, medical_cert_expiry
             FROM drivers
             WHERE deleted_at IS NULL AND medical_cert_expiry IS NOT NULL
               AND medical_cert_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY medical_cert_expiry ASC LIMIT 10",
            [$medicalDays]
        );

        // Anniversaires d'embauche du mois
        $anniversaries = Database::select(
            "SELECT id, matricule, first_name, last_name, position, hire_date,
                    TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) AS years
             FROM employees
             WHERE deleted_at IS NULL AND status='actif'
               AND MONTH(hire_date) = ?
               AND YEAR(hire_date) < ?
             ORDER BY DAY(hire_date) ASC LIMIT 20",
            [$month, $year]
        );

        $this->view('rh/dashboard', [
            'title'           => 'Tableau de bord RH',
            'month'           => $month,
            'year'            => $year,
            'totals'          => $totals,
            'byPosition'      => $byPosition,
            'byAgency'        => $byAgency,
            'payrollMonth'    => $payrollMonth,
            'payrollHistory'  => $payrollHistory,
            'licenseAlerts'   => $licenseAlerts,
            'medicalAlerts'   => $medicalAlerts,
            'anniversaries'   => $anniversaries,
        ]);
    }

    public function employees(Request $request): void
    {
        [$where, $params] = $this->buildEmployeesWhere($request);
        $employees = Database::select(
            "SELECT e.*, a.name AS agency_name FROM employees e
             JOIN agencies a ON a.id=e.agency_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY e.last_name, e.first_name", $params
        );
        $this->view('rh/employees/index', [
            'title'        => 'Effectifs',
            'employees'    => $employees,
            'q'            => trim((string)$request->input('q', '')),
            'position'     => trim((string)$request->input('position', '')),
            'allPositions' => RhPositionController::loadPositions(),
        ]);
    }

    private function buildEmployeesWhere(Request $request): array
    {
        $q        = trim((string)$request->input('q', ''));
        $position = trim((string)$request->input('position', ''));
        $where    = ['e.deleted_at IS NULL'];
        $params   = [];
        if ($q !== '') {
            $where[] = '(e.first_name LIKE ? OR e.last_name LIKE ? OR e.matricule LIKE ? OR e.phone LIKE ?)';
            $like = "%$q%";
            array_push($params, $like, $like, $like, $like);
        }
        if ($position !== '') {
            $where[] = 'e.position = ?';
            $params[] = $position;
        }
        return [$where, $params];
    }

    /** Export CSV de la liste des employés (avec filtres courants). */
    public function exportEmployeesCsv(Request $request): void
    {
        if (!\CityBus\Core\Auth::can('rh.view')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        [$where, $params] = $this->buildEmployeesWhere($request);
        $rows = Database::select(
            "SELECT e.matricule, e.last_name, e.first_name, e.position, e.phone, e.email,
                    a.name AS agency_name, e.salary_base, e.daily_bonus, e.hire_date, e.status
             FROM employees e
             JOIN agencies a ON a.id=e.agency_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY e.last_name, e.first_name", $params
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="employes_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Matricule','Nom','Prénom','Poste','Téléphone','Email','Agence','Salaire base','Prime journalière','Embauche','Statut'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['matricule'], $r['last_name'], $r['first_name'], $r['position'],
                $r['phone'] ?? '', $r['email'] ?? '', $r['agency_name'],
                (int)$r['salary_base'], (int)($r['daily_bonus'] ?? 0),
                $r['hire_date'], $r['status'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    public function showEmployee(Request $request, string $id): void
    {
        $emp = Database::selectOne(
            "SELECT e.*, a.name AS agency_name FROM employees e
             JOIN agencies a ON a.id=e.agency_id WHERE e.id=?", [$id]
        );
        if (!$emp) { http_response_code(404); $this->view('errors/404'); return; }

        $payrolls = Database::select(
            "SELECT * FROM payroll WHERE employee_id=? ORDER BY year DESC, month DESC LIMIT 12", [$id]
        );
        $schedules = Database::select(
            "SELECT s.*, tr.trip_code, l.name AS line_name FROM schedules s
             LEFT JOIN trips tr ON tr.id=s.trip_id
             LEFT JOIN bus_lines l ON l.id=tr.line_id
             WHERE s.employee_id=? AND s.schedule_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             ORDER BY s.schedule_date DESC LIMIT 60", [$id]
        );

        $this->view('rh/employees/show', [
            'title' => $emp['first_name'] . ' ' . $emp['last_name'],
            'employee' => $emp,
            'payrolls' => $payrolls,
            'schedules' => $schedules,
        ]);
    }

    public function createEmployee(Request $request): void
    {
        $this->view('rh/employees/form', [
            'title' => 'Nouvel employé', 'employee' => null,
            'agencies' => Database::select("SELECT a.*, c.slug AS city, c.name AS city_name FROM agencies a LEFT JOIN cities c ON c.id=a.city_id WHERE a.is_active=1"),
        ]);
    }

    public function storeEmployee(Request $request): void
    {
        $data = $this->validate($request, [
            'matricule'    => 'required|max:20|unique:employees,matricule',
            'first_name'   => 'required|max:50',
            'last_name'    => 'required|max:50',
            'phone'        => 'max:20',
            'agency_id'    => 'required|integer',
            'position'     => 'required|max:50',
            'salary_base'  => 'required|integer',
            'daily_bonus'  => 'integer',
            'hire_date'    => 'required|date',
        ]);
        $empId = (int)Employee::create($data);
        AuditLog::record('employee.create', 'employee', $empId, [
            'matricule' => $data['matricule'],
            'name'      => $data['first_name'] . ' ' . $data['last_name'],
            'position'  => $data['position'],
        ]);
        $this->flash('success', 'Employé créé.');
        redirect('rh/employees');
    }

    public function editEmployee(Request $request, string $id): void
    {
        $employee = Employee::find((int)$id);
        if (!$employee) { http_response_code(404); $this->view('errors/404'); return; }
        $this->view('rh/employees/form', [
            'title'    => 'Modifier l\'employé',
            'employee' => $employee,
            'agencies' => Database::select("SELECT a.*, c.slug AS city, c.name AS city_name FROM agencies a LEFT JOIN cities c ON c.id=a.city_id WHERE a.is_active=1"),
        ]);
    }

    public function updateEmployee(Request $request, string $id): void
    {
        $employee = Employee::find((int)$id);
        if (!$employee) { http_response_code(404); $this->view('errors/404'); return; }
        $data = $this->validate($request, [
            'matricule'   => 'required|max:20|unique:employees,matricule,' . (int)$id,
            'first_name'  => 'required|max:50',
            'last_name'   => 'required|max:50',
            'phone'       => 'max:20',
            'agency_id'   => 'required|integer',
            'position'    => 'required|max:50',
            'salary_base' => 'required|integer',
            'daily_bonus' => 'integer',
            'hire_date'   => 'required|date',
        ]);
        Employee::update((int)$id, $data);
        AuditLog::record('employee.update', 'employee', (int)$id, [
            'matricule' => $data['matricule'],
            'name'      => $data['first_name'] . ' ' . $data['last_name'],
            'position'  => $data['position'],
        ]);
        $this->flash('success', 'Employé mis à jour.');
        redirect('rh/employees/' . (int)$id);
    }

    public function payrolls(Request $request): void
    {
        $month = (int)$request->input('month', (int)date('n'));
        $year  = (int)$request->input('year',  (int)date('Y'));
        $rows = Database::select(
            "SELECT p.*,
                    (p.salary_base + p.bonus_amount) AS gross,
                    p.net_amount AS net,
                    CASE WHEN p.is_paid=1 THEN 'paye' ELSE 'impaye' END AS status,
                    e.first_name, e.last_name, e.matricule, e.position, a.name AS agency_name
             FROM payroll p
             JOIN employees e ON e.id=p.employee_id
             JOIN agencies a ON a.id=e.agency_id
             WHERE p.month=? AND p.year=?
             ORDER BY e.last_name, e.first_name", [$month, $year]
        );
        $totals = Database::selectOne(
            "SELECT COALESCE(SUM(net_amount),0) AS total, COUNT(*) AS cnt
             FROM payroll WHERE month=? AND year=?", [$month, $year]
        );
        $this->view('rh/payroll/index', [
            'title' => "Paie $month/$year",
            'payrolls' => $rows,
            'rows' => $rows,
            'totals' => $totals,
            'month' => $month,
            'year' => $year,
        ]);
    }

    public function runPayroll(Request $request): void
    {
        $data = $this->validate($request, ['month' => 'required|integer', 'year' => 'required|integer']);
        $r = $this->service->runMonth((int)$data['month'], (int)$data['year'], (int)\CityBus\Core\Auth::id());
        $this->flash('success', "Paie générée : {$r['created']} créée(s), {$r['skipped']} ignorée(s).");
        redirect("rh/payroll?month={$data['month']}&year={$data['year']}");
    }

    public function payslipPdf(Request $request, string $id): void
    {
        $p = Database::selectOne("SELECT * FROM payroll WHERE id=?", [$id]);
        $emp = Database::selectOne("SELECT * FROM employees WHERE id=?", [$p['employee_id']]);
        $ag = Database::selectOne("SELECT * FROM agencies WHERE id=?", [$emp['agency_id']]);
        $path = BASE_PATH . '/storage/' . $this->pdf->generatePayslip($p, $emp, $ag);
        Response::download($path, "fiche-paie-{$emp['matricule']}-{$p['year']}-{$p['month']}.pdf", 'application/pdf');
    }

    public function markPaid(Request $request, string $id): void
    {
        $this->service->markPaid((int)$id, (int)\CityBus\Core\Auth::id());
        $this->flash('success', 'Marqué comme payé.');
        back();
    }

    public function toggleEmployee(Request $request, string $id): void
    {
        $employee = Employee::find((int)$id);
        if (!$employee) { http_response_code(404); $this->view('errors/404'); return; }
        if ($employee['deleted_at']) {
            Database::execute("UPDATE employees SET deleted_at=NULL, status='actif' WHERE id=?", [(int)$id]);
            AuditLog::record('employee.reactivate', 'employee', (int)$id, ['matricule' => $employee['matricule']]);
            $this->flash('success', 'Employé réactivé.');
        } else {
            Database::execute("UPDATE employees SET deleted_at=NOW(), status='inactif' WHERE id=?", [(int)$id]);
            AuditLog::record('employee.deactivate', 'employee', (int)$id, ['matricule' => $employee['matricule']]);
            $this->flash('success', 'Employé désactivé.');
        }
        redirect('rh/employees');
    }
}
