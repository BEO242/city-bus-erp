<?php

declare(strict_types=1);

namespace CityBus\Controllers\Voyage;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Models\AuditLog;
use CityBus\Models\Trip;
use CityBus\Services\TripService;
use CityBus\Services\TreasuryExpenseService;

/**
 * Module Voyages — refonte audit profondeur (10 mai 2026).
 *
 * Tout passe par TripService pour mutation. Le contrôleur ne fait
 * que :
 *  - lire les données pour afficher
 *  - vérifier les permissions par action
 *  - appliquer le scoping par agence
 *  - paginer / filtrer / trier
 */
final class TripController extends Controller
{
    public function __construct(private TripService $service = new TripService()) {}

    // ════════════════════════════════════════════════════════════
    // INDEX — liste paginée avec filtres avancés et tris
    // ════════════════════════════════════════════════════════════

    public function index(Request $request): void
    {
        // Paramètres de filtrage
        $dateFrom  = trim((string)$request->input('date_from', ''));
        $dateTo    = trim((string)$request->input('date_to',   ''));
        $status    = trim((string)$request->input('status', ''));
        $lineId    = (int)$request->input('line_id', 0);
        $busId     = (int)$request->input('bus_id', 0);
        $driverId  = (int)$request->input('driver_id', 0);
        $tripType  = trim((string)$request->input('trip_type', ''));
        $priority  = trim((string)$request->input('priority', ''));
        $hasIncident = $request->input('has_incident'); // '', '1', '0'
        $minOccup  = $request->input('min_occupancy'); // %
        $maxOccup  = $request->input('max_occupancy');
        $delayMin  = $request->input('delay_minutes_min');
        $search    = trim((string)$request->input('search', ''));
        $sort      = $request->input('sort', 'date_desc');
        $perPage   = max(10, min(100, (int)$request->input('per_page', 30)));
        $page      = max(1, (int)$request->input('page', 1));
        $view      = $request->input('view', 'cards'); // cards | table | calendar

        // Préréglage rapide via "scope"
        $scope = trim((string)$request->input('scope', ''));
        if ($scope === 'today')      { $dateFrom = $dateTo = date('Y-m-d'); }
        if ($scope === 'tomorrow')   { $dateFrom = $dateTo = date('Y-m-d', strtotime('+1 day')); }
        if ($scope === 'week')       { $dateFrom = date('Y-m-d', strtotime('monday this week')); $dateTo = date('Y-m-d', strtotime('sunday this week')); }
        if ($scope === 'in_progress'){ $dateFrom = date('Y-m-d', strtotime('-1 day')); $dateTo = date('Y-m-d'); $status = 'en_route'; }
        if ($scope === 'to_close')   { $dateFrom = date('Y-m-d', strtotime('-7 days')); $dateTo = date('Y-m-d'); $status = 'arrive'; }

        if ($dateFrom !== '' && $dateTo !== '' && $dateTo < $dateFrom) $dateTo = $dateFrom;

        // Construction WHERE
        $where = ['1=1'];
        $params = [];
        if ($dateFrom !== '' && $dateTo !== '') {
            $where[] = 'tr.trip_date BETWEEN ? AND ?';
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif ($dateFrom !== '') {
            $where[] = 'tr.trip_date >= ?';
            $params[] = $dateFrom;
        } elseif ($dateTo !== '') {
            $where[] = 'tr.trip_date <= ?';
            $params[] = $dateTo;
        }
        if ($status   !== '' && array_key_exists($status, Trip::STATUSES)) { $where[] = 'tr.status = ?'; $params[] = $status; }
        if ($lineId   > 0)  { $where[] = 'tr.line_id = ?'; $params[] = $lineId; }
        if ($busId    > 0)  { $where[] = 'tr.bus_id = ?'; $params[] = $busId; }
        if ($driverId > 0)  { $where[] = 'tr.driver_id = ?'; $params[] = $driverId; }
        if ($tripType !== '' && array_key_exists($tripType, Trip::TYPES)) { $where[] = 'tr.trip_type = ?'; $params[] = $tripType; }
        if ($priority !== '' && array_key_exists($priority, Trip::PRIORITIES)) { $where[] = 'tr.priority = ?'; $params[] = $priority; }
        if ($hasIncident === '1') { $where[] = "(tr.status='incident' OR tr.incident_notes IS NOT NULL)"; }
        if ($hasIncident === '0') { $where[] = "tr.status<>'incident' AND tr.incident_notes IS NULL"; }
        if ($delayMin !== null && $delayMin !== '') { $where[] = 'tr.delay_minutes >= ?'; $params[] = (int)$delayMin; }
        if ($search !== '') {
            $where[] = '(tr.trip_code LIKE ? OR b.code LIKE ? OR b.plate LIKE ? OR CONCAT(e.first_name," ",e.last_name) LIKE ? OR tr.external_reference LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like, $like, $like);
        }

        // Scoping par agence pour rôles non globaux
        $user = Auth::user();
        $globalRoles = ['admin','raf','exploitation'];
        if (!in_array($user['role_slug'] ?? $user['role'] ?? null, $globalRoles, true)
            && !empty($user['agency_id'])) {
            $where[] = '(tr.agency_origin_id = ? OR tr.agency_destination_id = ?)';
            $params[] = (int)$user['agency_id'];
            $params[] = (int)$user['agency_id'];
        }

        // Tri
        $sortMap = [
            'date_desc'    => 'tr.trip_date DESC, tr.departure_scheduled DESC',
            'date_asc'     => 'tr.trip_date ASC, tr.departure_scheduled ASC',
            'revenue_desc' => 'revenue DESC',
            'revenue_asc'  => 'revenue ASC',
            'sold_desc'    => 'sold_count DESC',
            'sold_asc'     => 'sold_count ASC',
            'delay_desc'   => 'COALESCE(tr.delay_minutes,0) DESC',
            'code_asc'     => 'tr.trip_code ASC',
        ];
        $orderBy = $sortMap[$sort] ?? $sortMap['date_desc'];

        $whereSql = implode(' AND ', $where);

        // Total pour pagination
        $countSql = "SELECT COUNT(*) AS c
                     FROM trips tr
                     JOIN bus_lines l ON l.id = tr.line_id
                     JOIN buses b ON b.id = tr.bus_id
                     LEFT JOIN drivers e ON e.id = tr.driver_id
                     WHERE $whereSql";
        $total = (int)(Database::selectOne($countSql, $params)['c'] ?? 0);
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        // Liste paginée enrichie
        $listSql = "SELECT tr.*,
                           l.code AS line_code, l.name AS line_name,
                           b.code AS bus_code, b.plate AS bus_plate, b.seats AS bus_seats,
                           e.first_name AS driver_first, e.last_name AS driver_last,
                           ao.name AS agency_origin, ad.name AS agency_destination,
                           (SELECT COUNT(*) FROM tickets tk
                              WHERE tk.trip_id=tr.id AND tk.status<>'annule' AND tk.deleted_at IS NULL
                                AND tk.ticket_type IN ('passager','arret_route')) AS sold_count,
                           (SELECT COALESCE(SUM(tk.price_fcfa),0) FROM tickets tk
                              WHERE tk.trip_id=tr.id AND tk.status<>'annule' AND tk.deleted_at IS NULL) AS revenue,
                           (SELECT COUNT(*) FROM parcels p
                              WHERE p.trip_id=tr.id AND p.deleted_at IS NULL) AS parcels_count
                    FROM trips tr
                    JOIN bus_lines l ON l.id = tr.line_id
                    JOIN buses b ON b.id = tr.bus_id
                    LEFT JOIN drivers e ON e.id = tr.driver_id
                    LEFT JOIN agencies ao ON ao.id = tr.agency_origin_id
                    LEFT JOIN agencies ad ON ad.id = tr.agency_destination_id
                    WHERE $whereSql
                    ORDER BY $orderBy
                    LIMIT $perPage OFFSET $offset";
        $trips = Database::select($listSql, $params);

        // Filtre occupation post-query (calculé)
        if ($minOccup !== '' && $minOccup !== null) {
            $minOccup = (int)$minOccup;
            $trips = array_filter($trips, function($t) use ($minOccup) {
                $seats = (int)($t['bus_seats'] ?? 0);
                $sold = (int)($t['sold_count'] ?? 0);
                $occ = $seats > 0 ? round($sold / $seats * 100) : 0;
                return $occ >= $minOccup;
            });
        }
        if ($maxOccup !== '' && $maxOccup !== null) {
            $maxOccup = (int)$maxOccup;
            $trips = array_filter($trips, function($t) use ($maxOccup) {
                $seats = (int)($t['bus_seats'] ?? 0);
                $sold = (int)($t['sold_count'] ?? 0);
                $occ = $seats > 0 ? round($sold / $seats * 100) : 0;
                return $occ <= $maxOccup;
            });
        }

        // KPIs étendus
        $kpiRow = Database::selectOne(
            "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN status='en_route' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status='cloture' THEN 1 ELSE 0 END) AS closed_count,
                SUM(CASE WHEN status='annule' THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN status='incident' THEN 1 ELSE 0 END) AS incident_count,
                SUM(CASE WHEN status IN ('arrive','cloture') AND delay_minutes IS NOT NULL AND delay_minutes <= ? THEN 1 ELSE 0 END) AS otp_count,
                SUM(CASE WHEN status IN ('arrive','cloture') THEN 1 ELSE 0 END) AS done_count,
                AVG(CASE WHEN delay_minutes IS NOT NULL THEN delay_minutes END) AS avg_delay
             FROM trips tr WHERE $whereSql",
            array_merge([\CityBus\Core\Setting::getInt('voyage.delay_tolerance_minutes', 15)], $params)
        ) ?: [];

        $totalSeats = 0; $totalSold = 0; $totalRevenue = 0;
        foreach ($trips as $t) {
            $totalSold    += (int)($t['sold_count'] ?? 0);
            $totalSeats   += (int)($t['bus_seats'] ?? 0);
            $totalRevenue += (int)($t['revenue'] ?? 0);
        }

        $lines = Database::select("SELECT id, code, name FROM bus_lines WHERE is_active=1 ORDER BY code");
        $buses = Database::select("SELECT id, code, plate FROM buses ORDER BY code");

        $this->view('voyages/index', [
            'title'        => 'Voyages',
            'trips'        => $trips,
            'lines'        => $lines,
            'buses'        => $buses,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'status'       => $status,
            'lineId'       => $lineId,
            'busId'        => $busId,
            'driverId'     => $driverId,
            'tripType'     => $tripType,
            'priority'     => $priority,
            'hasIncident'  => $hasIncident,
            'minOccup'     => $minOccup,
            'maxOccup'     => $maxOccup,
            'delayMin'     => $delayMin,
            'search'       => $search,
            'sort'         => $sort,
            'view'         => $view,
            'scope'        => $scope,
            'page'         => $page,
            'lastPage'     => $lastPage,
            'perPage'      => $perPage,
            'total'        => $total,
            'totalSold'    => $totalSold,
            'totalSeats'   => $totalSeats,
            'totalRevenue' => $totalRevenue,
            'kpiRow'       => $kpiRow,
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // SHOW — page détail avec onglets
    // ════════════════════════════════════════════════════════════

    public function show(Request $request, string $id): void
    {
        $trip = Database::selectOne(
            "SELECT tr.*,
                    l.name AS line_name, l.code AS line_code,
                    cd.slug AS departure_city, cd.name AS departure_city_name,
                    ca.slug AS arrival_city,   ca.name AS arrival_city_name,
                    l.distance_km, l.duration_hours,
                    b.code AS bus_code, b.plate AS bus_plate, b.seats AS bus_seats,
                    b.brand AS bus_brand, b.model AS bus_model,
                    e.first_name AS driver_first, e.last_name AS driver_last,
                    e.matricule AS driver_matricule, e.phone AS driver_phone,
                    cv.first_name AS convoyeur_first, cv.last_name AS convoyeur_last,
                    cv.matricule AS convoyeur_matricule, cv.phone AS convoyeur_phone,
                    ao.name AS agency_origin, ad.name AS agency_destination,
                    rb.code AS replaced_bus_code,
                    re.first_name AS replaced_driver_first, re.last_name AS replaced_driver_last,
                    pt.trip_code AS parent_trip_code,
                    CONCAT(uc.first_name,' ',uc.last_name) AS created_by_name,
                    CONCAT(ucl.first_name,' ',ucl.last_name) AS closed_by_name,
                    CONCAT(uca.first_name,' ',uca.last_name) AS cancelled_by_name,
                    (SELECT COALESCE(SUM(tk.price_fcfa),0) FROM tickets tk
                     WHERE tk.trip_id=tr.id AND tk.status<>'annule' AND tk.deleted_at IS NULL) AS total_revenue
             FROM trips tr
             JOIN bus_lines l ON l.id=tr.line_id
             JOIN cities cd ON cd.id=l.departure_city_id
             JOIN cities ca ON ca.id=l.arrival_city_id
             JOIN buses b ON b.id=tr.bus_id
             LEFT JOIN drivers e ON e.id=tr.driver_id
             LEFT JOIN drivers cv ON cv.id=tr.convoyeur_id
             LEFT JOIN agencies ao ON ao.id=tr.agency_origin_id
             LEFT JOIN agencies ad ON ad.id=tr.agency_destination_id
             LEFT JOIN buses rb ON rb.id=tr.replaced_bus_id
             LEFT JOIN drivers re ON re.id=tr.replaced_driver_id
             LEFT JOIN trips pt ON pt.id=tr.parent_trip_id
             LEFT JOIN users uc ON uc.id=tr.created_by
             LEFT JOIN users ucl ON ucl.id=tr.closed_by
             LEFT JOIN users uca ON uca.id=tr.cancelled_by
             WHERE tr.id=?", [$id]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        // Scoping
        $this->ensureAgencyAccess($trip);

        // Audit lecture si on a un onglet "audit" ouvert (signal RGPD)
        if ($request->input('tab') === 'audit') {
            AuditLog::record('trip.view_audit', 'trip', (int)$id);
        }

        $tickets = Database::select(
            "SELECT t.*, sf.name AS boarding_name, st.name AS alighting_name
             FROM tickets t
             LEFT JOIN stops sf ON sf.id = t.boarding_stop_id
             LEFT JOIN stops st ON st.id = t.alighting_stop_id
             WHERE t.trip_id=? AND t.deleted_at IS NULL
             ORDER BY t.seat_number, t.sold_at",
            [$id]
        );
        $bookedSeats = $this->service->bookedSeats((int)$id);

        $crew = Database::select(
            "SELECT tc.role, tc.notes AS crew_note,
                    e.id AS emp_id, e.first_name, e.last_name, e.matricule, e.phone, e.position
             FROM trip_crew tc
             JOIN employees e ON e.id=tc.employee_id
             WHERE tc.trip_id=? ORDER BY tc.role, e.last_name",
            [$id]
        );

        $lineStops = Database::select(
            "SELECT s.id, s.name, s.order_position, s.km_from_origin
             FROM stops s WHERE s.line_id=? ORDER BY s.order_position",
            [$trip['line_id']]
        );

        // Données pour les onglets enrichis
        $statusTimeline = $this->service->statusTimeline((int)$id);
        $parcels        = Database::select(
            "SELECT p.*, ao.name AS origin_agency, ad.name AS destination_agency
             FROM parcels p
             JOIN agencies ao ON ao.id=p.origin_agency_id
             JOIN agencies ad ON ad.id=p.destination_agency_id
             WHERE p.trip_id=? AND p.deleted_at IS NULL
             ORDER BY p.deposited_at",
            [$id]
        );
        $reservations = Database::select(
            "SELECT r.*, COUNT(ri.id) AS items_count
             FROM reservations r
             JOIN reservation_items ri ON ri.reservation_id = r.id
             WHERE ri.trip_id = ?
             GROUP BY r.id
             ORDER BY r.created_at DESC LIMIT 20",
            [$id]
        );
        $waitlist     = Database::select(
            "SELECT * FROM waitlist_entries WHERE trip_id = ? ORDER BY position", [$id]
        );
        $documents    = Database::select(
            "SELECT td.*, CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name
             FROM trip_documents td
             LEFT JOIN users u ON u.id = td.uploaded_by
             WHERE td.trip_id = ? ORDER BY td.uploaded_at DESC",
            [$id]
        );
        $disputes = Database::select(
            "SELECT * FROM trip_disputes WHERE trip_id = ? ORDER BY opened_at DESC",
            [$id]
        );
        $costs = Database::select(
            "SELECT * FROM trip_costs WHERE trip_id = ? ORDER BY created_at DESC",
            [$id]
        );
        $messages = Database::select(
            "SELECT tm.*, CONCAT(u.first_name,' ',u.last_name) AS sender_name
             FROM trip_messages tm
             LEFT JOIN users u ON u.id = tm.sent_by
             WHERE tm.trip_id = ? ORDER BY tm.sent_at DESC LIMIT 50",
            [$id]
        );
        $inspection = Database::selectOne(
            "SELECT * FROM pre_trip_inspections WHERE trip_id = ? ORDER BY id DESC LIMIT 1",
            [$id]
        );
        $pnl = class_exists(\CityBus\Services\TripPnlService::class)
            ? Database::selectOne("SELECT * FROM trip_pnl WHERE trip_id = ?", [$id])
            : null;

        $manifestLocked = $this->service->isManifestLocked((int)$id);

        // Tables spécifiques voyage
        $tollRecords = Database::select(
            "SELECT tr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM toll_records tr LEFT JOIN users u ON u.id = tr.logged_by
             WHERE tr.trip_id = ? ORDER BY tr.created_at DESC LIMIT 50", [$id]
        );
        $parkingRecords = Database::select(
            "SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM parking_records pr LEFT JOIN users u ON u.id = pr.logged_by
             WHERE pr.trip_id = ? ORDER BY pr.created_at DESC LIMIT 50", [$id]
        );
        $washRecords = Database::select(
            "SELECT wr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM wash_records wr LEFT JOIN users u ON u.id = wr.logged_by
             WHERE wr.trip_id = ? ORDER BY wr.created_at DESC LIMIT 50", [$id]
        );
        $fineRecords = Database::select(
            "SELECT fr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM fine_records fr LEFT JOIN users u ON u.id = fr.logged_by
             WHERE fr.trip_id = ? ORDER BY fr.created_at DESC LIMIT 50", [$id]
        );
        $tripCompensations = Database::select(
            "SELECT dc.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM driver_compensations dc LEFT JOIN users u ON u.id = dc.logged_by
             WHERE dc.trip_id = ? ORDER BY dc.created_at DESC LIMIT 50", [$id]
        );

        // Dépenses bidirectionnelles
        $expService     = new TreasuryExpenseService();
        $tripExpenses   = $expService->forTrip((int)$id);
        $tripExpCats    = $expService->categoriesFor('trip');
        $tripExpTotals  = $expService->totalsFor('trip', (int)$id);

        $this->view('voyages/show', [
            'title'          => 'Voyage ' . $trip['trip_code'],
            'trip'           => $trip,
            'tickets'        => $tickets,
            'bookedSeats'    => $bookedSeats,
            'crew'           => $crew,
            'lineStops'      => $lineStops,
            'statusTimeline' => $statusTimeline,
            'parcels'        => $parcels,
            'reservations'   => $reservations,
            'waitlist'       => $waitlist,
            'documents'      => $documents,
            'disputes'       => $disputes,
            'costs'          => $costs,
            'messages'       => $messages,
            'inspection'     => $inspection,
            'pnl'            => $pnl,
            'manifestLocked' => $manifestLocked,
            'activeTab'      => $request->input('tab', 'details'),
            // Tables spécifiques
            'tollRecords'        => $tollRecords,
            'parkingRecords'     => $parkingRecords,
            'washRecords'        => $washRecords,
            'fineRecords'        => $fineRecords,
            'tripCompensations'  => $tripCompensations,
            // Widget dépenses
            'expenses'       => $tripExpenses,
            'expCategories'  => $tripExpCats,
            'expTotals'      => $tripExpTotals,
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // CREATE / EDIT
    // ════════════════════════════════════════════════════════════

    public function create(Request $request): void
    {
        if (!Auth::can('voyages.create')) { Response::error(403); }
        $this->view('voyages/form', array_merge($this->formData(), [
            'title' => 'Nouveau voyage',
            'trip'  => null,
            'crew'  => [],
        ]));
    }

    public function store(Request $request): void
    {
        if (!Auth::can('voyages.create')) { $this->flash('danger', 'Permission refusée.'); back(); }

        $data = $this->validate($request, [
            'line_id'             => 'required|integer',
            'bus_id'              => 'required|integer',
            'driver_id'           => 'required|integer',
            'trip_date'           => 'required|date',
            'departure_scheduled' => 'required',
        ]);
        $data['arrival_scheduled']  = $request->input('arrival_scheduled') ?: null;
        $data['mileage_start']      = $request->input('mileage_start') !== '' ? (int)$request->input('mileage_start') : null;
        $data['weather_conditions'] = $request->input('weather_conditions') ?: null;
        $data['weather_temp_celsius'] = $request->input('weather_temp_celsius') ?: null;
        $data['external_reference'] = $request->input('external_reference') ?: null;
        $data['trip_type']          = $request->input('trip_type', 'commercial');
        $data['priority']           = $request->input('priority', 'normale');
        $data['public_visible']     = (int)$request->input('public_visible', 1);

        $notesArr = array_values(array_filter(array_map('trim', (array)$request->input('notes', []))));
        $data['notes'] = $notesArr ? json_encode($notesArr, JSON_UNESCAPED_UNICODE) : null;

        try {
            $id = $this->service->create($data);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            back(); return;
        }

        $this->saveCrew((int)$id, $request);
        $this->flash('success', 'Voyage créé avec succès.');
        redirect("voyages/$id");
    }

    public function edit(Request $request, string $id): void
    {
        if (!Auth::can('voyages.edit')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $trip = Database::selectOne("SELECT * FROM trips WHERE id=?", [$id]);
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }
        $this->ensureAgencyAccess($trip);

        $crew = Database::select(
            "SELECT tc.role, tc.notes AS crew_note,
                    e.id AS emp_id, e.first_name, e.last_name, e.matricule, e.phone, e.position
             FROM trip_crew tc JOIN employees e ON e.id=tc.employee_id
             WHERE tc.trip_id=? ORDER BY tc.role, e.last_name", [$id]
        );

        $this->view('voyages/form', array_merge($this->formData(), [
            'title' => 'Modifier voyage ' . $trip['trip_code'],
            'trip'  => $trip,
            'crew'  => $crew,
        ]));
    }

    public function update(Request $request, string $id): void
    {
        if (!Auth::can('voyages.edit') && !Auth::can('voyages.update')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $trip = Database::selectOne("SELECT * FROM trips WHERE id=?", [$id]);
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }
        $this->ensureAgencyAccess($trip);

        $data = $this->validate($request, [
            'line_id'             => 'required|integer',
            'bus_id'              => 'required|integer',
            'driver_id'           => 'required|integer',
            'trip_date'           => 'required|date',
            'departure_scheduled' => 'required',
        ]);
        $data['arrival_scheduled']  = $request->input('arrival_scheduled') ?: null;
        $data['departure_actual']   = $request->input('departure_actual') ?: null;
        $data['arrival_actual']     = $request->input('arrival_actual')   ?: null;
        $data['mileage_start']      = $request->input('mileage_start');
        $data['mileage_end']        = $request->input('mileage_end');
        $data['weather_conditions'] = $request->input('weather_conditions') ?: null;
        $data['weather_temp_celsius'] = $request->input('weather_temp_celsius') ?: null;
        $data['external_reference'] = $request->input('external_reference') ?: null;
        $data['trip_type']          = $request->input('trip_type', $trip['trip_type']);
        $data['priority']           = $request->input('priority', $trip['priority']);
        $data['public_visible']     = (int)$request->input('public_visible', $trip['public_visible']);

        $notesArr   = array_values(array_filter(array_map('trim', (array)$request->input('notes', []))));
        $incArr     = array_values(array_filter(array_map('trim', (array)$request->input('incident_notes', []))));
        $data['notes']          = $notesArr ? json_encode($notesArr, JSON_UNESCAPED_UNICODE) : null;
        $data['incident_notes'] = $incArr   ? json_encode($incArr,   JSON_UNESCAPED_UNICODE) : null;

        try {
            $this->service->update((int)$id, $data);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            back(); return;
        }

        $this->saveCrew((int)$id, $request, replace: true);
        $this->flash('success', 'Voyage mis à jour.');
        redirect("voyages/$id");
    }

    // ════════════════════════════════════════════════════════════
    // CHANGEMENT DE STATUT — permissions ciblées
    // ════════════════════════════════════════════════════════════

    public function changeStatus(Request $request, string $id): void
    {
        $newStatus = (string)$request->input('status', '');
        $reason    = trim((string)$request->input('reason', ''));

        // Vérification de permission selon le statut cible
        $needPerm = match ($newStatus) {
            'annule'   => 'voyages.cancel',
            'cloture'  => 'voyages.close',
            default    => 'voyages.update',
        };
        if (!Auth::can($needPerm) && !Auth::can('voyages.edit')) {
            $this->flash('danger', "Permission refusée ($needPerm).");
            back(); return;
        }

        if (!array_key_exists($newStatus, Trip::STATUSES)) {
            $this->flash('danger', 'Statut invalide.'); back(); return;
        }

        $trip = Database::selectOne("SELECT * FROM trips WHERE id=?", [$id]);
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }
        $this->ensureAgencyAccess($trip);

        try {
            $this->service->changeStatus((int)$id, $newStatus, $reason ?: null);

            // Auto P&L à la clôture
            if ($newStatus === 'cloture' && class_exists(\CityBus\Services\TripPnlService::class)) {
                try {
                    (new \CityBus\Services\TripPnlService())->compute((int)$id, (int)Auth::id());
                } catch (\Throwable $e) {
                    \CityBus\Core\Logger::warning('trip_pnl.compute_failed: ' . $e->getMessage());
                }
            }
            // Auto-log HOS à la clôture
            if ($newStatus === 'cloture' && class_exists(\CityBus\Services\DriverHosService::class)) {
                try {
                    (new \CityBus\Services\DriverHosService())->autoLogFromTrip((int)$id);
                } catch (\Throwable $e) {
                    \CityBus\Core\Logger::warning('hos.auto_log_failed: ' . $e->getMessage());
                }
            }
            $this->flash('success', 'Statut mis à jour.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    // ════════════════════════════════════════════════════════════
    // ACTIONS OPÉRATIONNELLES
    // ════════════════════════════════════════════════════════════

    public function replaceBus(Request $request, string $id): void
    {
        if (!Auth::can('voyages.replace_bus')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $newBus = (int)$request->input('new_bus_id', 0);
        $reason = trim((string)$request->input('reason', ''));
        if ($newBus <= 0 || mb_strlen($reason) < 5) {
            $this->flash('danger', 'Bus et motif (5 car. min) requis.'); back();
        }
        try {
            $this->service->replaceBus((int)$id, $newBus, $reason);
            $this->flash('success', 'Bus remplacé.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function replaceDriver(Request $request, string $id): void
    {
        if (!Auth::can('voyages.replace_driver')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $newDriver = (int)$request->input('new_driver_id', 0);
        $reason = trim((string)$request->input('reason', ''));
        if ($newDriver <= 0 || mb_strlen($reason) < 5) {
            $this->flash('danger', 'Chauffeur et motif requis.'); back();
        }
        try {
            $this->service->replaceDriver((int)$id, $newDriver, $reason);
            $this->flash('success', 'Chauffeur remplacé.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function lockManifest(Request $request, string $id): void
    {
        if (!Auth::can('voyages.lock_manifest')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $reason = trim((string)$request->input('reason', ''));
        $this->service->lockManifest((int)$id, $reason ?: null);
        $this->flash('success', 'Manifeste verrouillé. Plus aucune vente n\'est acceptée.');
        back();
    }

    public function unlockManifest(Request $request, string $id): void
    {
        if (!Auth::can('voyages.unlock_manifest')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 5) { $this->flash('danger', 'Motif requis.'); back(); }
        $this->service->unlockManifest((int)$id, $reason);
        $this->flash('success', 'Manifeste déverrouillé.');
        back();
    }

    public function communicate(Request $request, string $id): void
    {
        if (!Auth::can('voyages.communicate')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $message = trim((string)$request->input('message', ''));
        if (mb_strlen($message) < 5) { $this->flash('danger', 'Message requis (5 car. min).'); back(); }
        try {
            $r = $this->service->communicateToPassengers((int)$id, $message, $request->input('audience', 'all_passengers'));
            $this->flash('success', "Message envoyé à {$r['sent']}/{$r['recipients']} destinataires.");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function createReplacement(Request $request, string $id): void
    {
        if (!Auth::can('voyages.create')) { $this->flash('danger', 'Permission refusée.'); back(); }
        try {
            $newId = $this->service->createReplacement((int)$id, [
                'bus_id'              => $request->input('bus_id'),
                'driver_id'           => $request->input('driver_id'),
                'trip_date'           => $request->input('trip_date'),
                'departure_scheduled' => $request->input('departure_scheduled'),
                'arrival_scheduled'   => $request->input('arrival_scheduled') ?: null,
            ]);
            $this->flash('success', "Voyage de remplacement #$newId créé.");
            redirect("voyages/$newId");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function destroy(Request $request, string $id): void
    {
        if (!Auth::can('voyages.delete')) { $this->flash('danger', 'Permission refusée.'); back(); }
        try {
            $this->service->delete((int)$id);
            $this->flash('success', 'Voyage supprimé.');
            redirect('voyages');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            back();
        }
    }

    public function uploadDocument(Request $request, string $id): void
    {
        if (!Auth::can('voyages.documents.upload')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $file = $_FILES['document'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'Fichier manquant ou invalide.'); back();
        }
        $type  = $request->input('doc_type', 'autre');
        $title = trim((string)$request->input('title', $file['name']));

        $dir = BASE_PATH . '/storage/voyages/' . (int)$id;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
        $dest = $dir . '/' . time() . '_' . $safe;
        move_uploaded_file($file['tmp_name'], $dest);

        $relPath = 'voyages/' . (int)$id . '/' . basename($dest);
        Database::insert(
            "INSERT INTO trip_documents (trip_id, doc_type, title, file_path, file_size, mime_type, uploaded_by)
             VALUES (?,?,?,?,?,?,?)",
            [(int)$id, $type, $title, $relPath, $file['size'], $file['type'], Auth::id()]
        );
        AuditLog::record('trip.documents.upload', 'trip', (int)$id, ['title' => $title, 'doc_type' => $type]);
        $this->flash('success', 'Document uploadé.');
        back();
    }

    public function addCost(Request $request, string $id): void
    {
        if (!Auth::can('voyages.costs.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'cost_type' => 'required',
            'amount_fcfa' => 'required|integer',
        ]);
        Database::insert(
            "INSERT INTO trip_costs (trip_id, cost_type, amount_fcfa, description, paid_at, created_by)
             VALUES (?,?,?,?,?,?)",
            [
                (int)$id, $data['cost_type'], (int)$data['amount_fcfa'],
                $request->input('description'),
                $request->input('paid_at') ?: null,
                Auth::id(),
            ]
        );
        AuditLog::record('trip.costs.add', 'trip', (int)$id, ['type' => $data['cost_type'], 'amount' => (int)$data['amount_fcfa']]);
        $this->flash('success', 'Coût ajouté.');
        back();
    }

    public function openDispute(Request $request, string $id): void
    {
        if (!Auth::can('voyages.dispute.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'type'        => 'required',
            'title'       => 'required|min:5|max:180',
            'description' => 'required|min:10',
        ]);
        Database::insert(
            "INSERT INTO trip_disputes (trip_id, type, title, description, claim_amount_fcfa, opened_by, customer_id, ticket_id)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                (int)$id, $data['type'], $data['title'], $data['description'],
                $request->input('claim_amount_fcfa') ? (int)$request->input('claim_amount_fcfa') : null,
                Auth::id(),
                $request->input('customer_id') ? (int)$request->input('customer_id') : null,
                $request->input('ticket_id') ? (int)$request->input('ticket_id') : null,
            ]
        );
        AuditLog::record('trip.dispute.open', 'trip', (int)$id, ['type' => $data['type'], 'title' => $data['title']]);
        $this->flash('success', 'Litige ouvert.');
        back();
    }

    // ════════════════════════════════════════════════════════════
    // EXPORTS
    // ════════════════════════════════════════════════════════════

    public function exportCsv(Request $request): void
    {
        if (!Auth::can('voyages.export')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo   = $request->input('date_to', date('Y-m-d'));

        $rows = Database::select(
            "SELECT tr.trip_code, tr.trip_date, tr.departure_scheduled, tr.arrival_scheduled,
                    tr.departure_actual, tr.arrival_actual, tr.delay_minutes,
                    tr.status, tr.trip_type, tr.priority,
                    l.code AS line_code, l.name AS line_name,
                    b.code AS bus_code, b.plate AS bus_plate, b.seats,
                    CONCAT(e.first_name,' ',e.last_name) AS driver,
                    (SELECT COUNT(*) FROM tickets tk WHERE tk.trip_id=tr.id AND tk.status<>'annule' AND tk.deleted_at IS NULL) AS tickets,
                    (SELECT COALESCE(SUM(tk.price_fcfa),0) FROM tickets tk WHERE tk.trip_id=tr.id AND tk.status<>'annule' AND tk.deleted_at IS NULL) AS revenue,
                    (SELECT COUNT(*) FROM parcels p WHERE p.trip_id=tr.id AND p.deleted_at IS NULL) AS parcels
             FROM trips tr
             JOIN bus_lines l ON l.id=tr.line_id
             JOIN buses b ON b.id=tr.bus_id
             LEFT JOIN drivers e ON e.id=tr.driver_id
             WHERE tr.trip_date BETWEEN ? AND ?
             ORDER BY tr.trip_date DESC, tr.departure_scheduled DESC",
            [$dateFrom, $dateTo]
        );

        AuditLog::record('trip.export.csv', null, null, ['from' => $dateFrom, 'to' => $dateTo, 'count' => count($rows)]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="voyages_' . $dateFrom . '_' . $dateTo . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Code','Date','Départ prévu','Arrivée prévue','Départ réel','Arrivée réelle','Retard (min)',
                       'Statut','Type','Priorité','Ligne','Nom ligne','Bus','Plaque','Sièges','Chauffeur',
                       'Tickets','Recettes (FCFA)','Colis'], ';');
        foreach ($rows as $r) {
            fputcsv($out, $r, ';');
        }
        fclose($out);
        exit;
    }

    public function manifest(Request $request, string $id): void
    {
        if (!Auth::can('voyages.view')) { Response::error(403); }
        $trip = Database::selectOne(
            "SELECT tr.*, l.code AS line_code, l.name AS line_name,
                    b.code AS bus_code, b.plate, b.brand, b.model
             FROM trips tr
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             LEFT JOIN buses b ON b.id = tr.bus_id
             WHERE tr.id = ?", [(int)$id]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }
        $this->ensureAgencyAccess($trip);

        $tickets = Database::select(
            "SELECT t.*, sf.name AS boarding_name, st.name AS alighting_name
             FROM tickets t
             LEFT JOIN stops sf ON sf.id = t.boarding_stop_id
             LEFT JOIN stops st ON st.id = t.alighting_stop_id
             WHERE t.trip_id = ? AND t.deleted_at IS NULL
               AND t.status IN ('emis','controle','utilise','embarque')
             ORDER BY t.seat_number ASC, t.ticket_number ASC", [(int)$id]
        );
        $crew = Database::select(
            "SELECT tc.role, e.first_name, e.last_name, e.matricule, e.phone
             FROM trip_crew tc
             JOIN employees e ON e.id = tc.employee_id
             WHERE tc.trip_id = ?", [(int)$id]
        );

        $pdf = (new \CityBus\Services\PdfService())->generateTripManifest($trip, $tickets, $crew);
        AuditLog::record('trip.manifest.print', 'trip', (int)$id);
        Response::download(BASE_PATH . '/storage/' . $pdf, "manifeste-{$trip['trip_code']}.pdf", 'application/pdf');
    }

    // ════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ════════════════════════════════════════════════════════════

    private function formData(): array
    {
        return [
            'lines' => Database::select(
                "SELECT l.id, l.code, l.name, cd.slug AS departure_city, cd.name AS departure_city_name,
                        ca.slug AS arrival_city, ca.name AS arrival_city_name,
                        l.distance_km, l.duration_hours
                 FROM bus_lines l
                 JOIN cities cd ON cd.id=l.departure_city_id
                 JOIN cities ca ON ca.id=l.arrival_city_id
                 WHERE l.is_active=1 ORDER BY l.code"
            ),
            'buses' => Database::select(
                "SELECT id, code, plate, seats, status FROM buses
                 WHERE status IN ('disponible','en_voyage') ORDER BY code"
            ),
            'drivers' => Database::select(
                "SELECT id, matricule, first_name, last_name, license_number, phone
                 FROM drivers
                 WHERE status='actif' AND deleted_at IS NULL
                 ORDER BY last_name, first_name"
            ),
            'staff' => Database::select(
                "SELECT id, matricule, first_name, last_name, position
                 FROM employees WHERE status='actif' AND deleted_at IS NULL
                 ORDER BY position, last_name"
            ),
            'stops' => Database::select(
                "SELECT s.id, s.name, s.line_id, s.order_position, s.km_from_origin
                 FROM stops s ORDER BY s.line_id, s.order_position"
            ),
            'tripTypes'  => Trip::TYPES,
            'priorities' => Trip::PRIORITIES,
        ];
    }

    private function saveCrew(int $tripId, Request $request, bool $replace = false): void
    {
        if ($replace) Database::execute("DELETE FROM trip_crew WHERE trip_id=?", [$tripId]);
        $crewIds   = (array)$request->input('crew_employee_id', []);
        $crewRoles = (array)$request->input('crew_role', []);
        $crewNotes = (array)$request->input('crew_notes', []);
        foreach ($crewIds as $i => $empId) {
            $empId = (int)$empId;
            if ($empId <= 0) continue;
            Database::execute(
                "INSERT IGNORE INTO trip_crew (trip_id, employee_id, role, notes) VALUES (?,?,?,?)",
                [$tripId, $empId, $crewRoles[$i] ?? 'convoyeur', $crewNotes[$i] ?? null]
            );
        }
    }

    private function ensureAgencyAccess(array $trip): void
    {
        $user = Auth::user();
        $globalRoles = ['admin','raf','exploitation'];
        if (in_array($user['role_slug'] ?? $user['role'] ?? null, $globalRoles, true)) return;
        $agencyId = (int)($user['agency_id'] ?? 0);
        if ($agencyId <= 0) return;
        $accessible = [(int)($trip['agency_origin_id'] ?? 0), (int)($trip['agency_destination_id'] ?? 0)];
        if (!in_array($agencyId, $accessible, true)) {
            http_response_code(403);
            echo (new \CityBus\Core\View())->render('errors/403', ['permission' => 'voyages.scope']);
            exit;
        }
    }
}
