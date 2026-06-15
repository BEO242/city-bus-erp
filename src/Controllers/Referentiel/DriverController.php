<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\Driver;
use CityBus\Models\Media;
use CityBus\Models\Note;
use CityBus\Services\MediaService;
use CityBus\Services\TreasuryExpenseService;

final class DriverController extends Controller
{
    public function index(Request $request): void
    {
        $statusFilter = trim((string)$request->input('status', ''));
        $agencyFilter = (int)$request->input('agency_id', 0);
        $search       = trim((string)$request->input('q', ''));
        $alertsFilter = trim((string)$request->input('alerts', ''));
        $licFilter    = trim((string)$request->input('license_cat', ''));

        $validSorts = ['last_name', 'matricule', 'hire_date', 'rating_score', 'status', 'created_at', 'alerts_n'];
        $sortField  = in_array($request->input('sort'), $validSorts, true) ? $request->input('sort') : 'last_name';
        $sortDir    = $request->input('dir') === 'desc' ? 'DESC' : 'ASC';

        $perPageOpts = [12, 24, 48, 9999];
        $perPage     = in_array((int)$request->input('per_page'), $perPageOpts, true) ? (int)$request->input('per_page') : 24;
        $page        = max(1, (int)$request->input('page', 1));

        $sql    = "SELECT d.*, a.name AS agency_name,
                          b.code AS bus_code, b.plate AS bus_plate
                   FROM drivers d
                   LEFT JOIN agencies a ON a.id = d.agency_id
                   LEFT JOIN buses    b ON b.id = d.primary_bus_id
                   WHERE d.deleted_at IS NULL";
        $params = [];

        if ($statusFilter !== '' && isset(Driver::STATUSES[$statusFilter])) {
            $sql .= ' AND d.status = ?';
            $params[] = $statusFilter;
        }
        if ($agencyFilter > 0) {
            $sql .= ' AND d.agency_id = ?';
            $params[] = $agencyFilter;
        }
        if ($licFilter !== '') {
            $sql .= ' AND FIND_IN_SET(?, d.license_categories)';
            $params[] = $licFilter;
        }
        if ($search !== '') {
            $sql .= ' AND (d.matricule LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR d.phone LIKE ? OR d.license_number LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        // Tri SQL (sauf alerts_n calculé en PHP)
        $sqlSort = $sortField !== 'alerts_n' ? $sortField : 'last_name';
        $sql .= " ORDER BY d.{$sqlSort} {$sortDir}";

        $drivers = Database::select($sql, $params);

        // Enrichir : photo + alertes
        foreach ($drivers as &$d) {
            $cover = Media::cover('drivers', (int)$d['id']);
            $d['photo_url'] = $cover ? Media::getThumbUrl($cover) : null;
            $d['alerts']    = Driver::alerts($d);
            $d['alerts_n']  = count(array_filter($d['alerts'], fn($a) => $a['level'] === 'danger'));
        }
        unset($d);

        // Tri PHP pour alerts_n
        if ($sortField === 'alerts_n') {
            usort($drivers, fn($a, $b) => $sortDir === 'ASC'
                ? $a['alerts_n'] <=> $b['alerts_n']
                : $b['alerts_n'] <=> $a['alerts_n']
            );
        }

        // Filtre PHP alertes
        if ($alertsFilter === 'yes') {
            $drivers = array_values(array_filter($drivers, fn($d) => $d['alerts_n'] > 0));
        } elseif ($alertsFilter === 'no') {
            $drivers = array_values(array_filter($drivers, fn($d) => $d['alerts_n'] === 0));
        }

        // Comptage alertes avant pagination (sur l'ensemble filtré)
        $alertsCount = array_sum(array_map(fn($d) => ($d['alerts_n'] ?? 0) > 0 ? 1 : 0, $drivers));

        // Pagination PHP
        $total    = count($drivers);
        $lastPage = $perPage === 9999 ? 1 : (int)ceil($total / $perPage);
        $lastPage = max(1, $lastPage);
        $page     = max(1, min($page, $lastPage));
        $drivers  = $perPage === 9999 ? $drivers : array_slice($drivers, ($page - 1) * $perPage, $perPage);

        // ─── KPIs globaux (sur toute la base, pas juste la page) ─────────────────
        $kpis = Database::selectOne(
            "SELECT
               COUNT(*)                                                                         AS total,
               SUM(status = 'actif')                                                            AS actif_n,
               SUM(status IN ('conge','en_formation','accident'))                               AS indispo_n,
               SUM(status = 'suspendu')                                                         AS suspendu_n,
               SUM(license_expiry IS NOT NULL AND license_expiry < CURDATE())                   AS lic_expired_n,
               SUM(license_expiry IS NOT NULL AND license_expiry >= CURDATE()
                   AND license_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))                  AS lic_soon_n
             FROM drivers WHERE deleted_at IS NULL"
        ) ?: [];

        // Comptage par statut (pour chips) sur l'ensemble non paginé
        $statusCounts = Database::select(
            "SELECT status, COUNT(*) AS n FROM drivers WHERE deleted_at IS NULL GROUP BY status"
        );
        $statusCountMap = array_column($statusCounts, 'n', 'status');

        $this->view('referentiel/drivers/index', [
            'title'          => 'Chauffeurs',
            'drivers'        => $drivers,
            'agencies'       => Database::select("SELECT * FROM agencies WHERE is_active=1 ORDER BY name"),
            'statusFilter'   => $statusFilter,
            'agencyFilter'   => $agencyFilter,
            'alertsFilter'   => $alertsFilter,
            'licFilter'      => $licFilter,
            'search'         => $search,
            'sortField'      => $sortField,
            'sortDir'        => strtolower($sortDir),
            'perPage'        => $perPage,
            'page'           => $page,
            'total'          => $total,
            'lastPage'       => $lastPage,
            'kpis'           => $kpis,
            'alertsCount'    => $alertsCount,
            'statusCountMap' => $statusCountMap,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('referentiel/drivers/form', [
            'title'    => 'Nouveau chauffeur',
            'driver'   => null,
            'agencies' => Database::select("SELECT * FROM agencies WHERE is_active=1 ORDER BY name"),
            'buses'    => Database::select("SELECT id, code, plate FROM buses ORDER BY code"),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $driver = Driver::findOrFail((int)$id);
        $agencyName = null;
        if (!empty($driver['agency_id'])) {
            $row = Database::selectOne("SELECT name FROM agencies WHERE id=?", [$driver['agency_id']]);
            $agencyName = $row['name'] ?? null;
        }
        $driver['agency_name']   = $agencyName;
        $driver['primary_bus']   = $driver['primary_bus_id']
            ? Database::selectOne("SELECT id, code, plate, brand, model FROM buses WHERE id=?", [$driver['primary_bus_id']])
            : null;

        $gallery = MediaService::enrichAll(Media::forModel('drivers', (int)$id, 'gallery'));
        $docs    = MediaService::enrichAll(Media::forModel('drivers', (int)$id, 'documents'));

        $stats     = Driver::stats((int)$id);
        $alerts    = Driver::alerts($driver);
        $trips     = Driver::recentTrips((int)$id, 8);
        $notes     = Note::forEntity('drivers', (int)$id);
        $incidents = Database::select(
            "SELECT i.*, b.code AS bus_code, b.plate AS bus_plate
             FROM incidents i LEFT JOIN buses b ON b.id=i.bus_id
             WHERE i.subject_type='driver' AND i.subject_id=?
             ORDER BY i.occurred_at DESC LIMIT 10",
            [$id]
        );

        // Tables spécifiques chauffeur
        $payrollRecords = Database::select(
            "SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM payroll_records pr LEFT JOIN users u ON u.id = pr.logged_by
             WHERE pr.driver_id = ? ORDER BY pr.period_year DESC, pr.period_month DESC LIMIT 50", [$id]
        );
        $compensations = Database::select(
            "SELECT dc.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name, t.trip_code
             FROM driver_compensations dc LEFT JOIN users u ON u.id = dc.logged_by LEFT JOIN trips t ON t.id = dc.trip_id
             WHERE dc.driver_id = ? ORDER BY dc.created_at DESC LIMIT 50", [$id]
        );
        $fineRecords = Database::select(
            "SELECT fr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM fine_records fr LEFT JOIN users u ON u.id = fr.logged_by
             WHERE fr.driver_id = ? ORDER BY fr.created_at DESC LIMIT 50", [$id]
        );

        // Dépenses bidirectionnelles
        $expService       = new TreasuryExpenseService();
        $driverExpenses   = $expService->forDriver((int)$id);
        $driverExpCats    = $expService->categoriesFor('driver');
        $driverExpTotals  = $expService->totalsFor('driver', (int)$id);

        $this->view('referentiel/drivers/show', [
            'title'     => Driver::fullName($driver) . ' — Fiche chauffeur',
            'driver'    => $driver,
            'gallery'   => $gallery,
            'docs'      => $docs,
            'stats'     => $stats,
            'alerts'    => $alerts,
            'trips'     => $trips,
            'notes'     => $notes,
            'incidents' => $incidents,
            // Tables spécifiques
            'payrollRecords' => $payrollRecords,
            'compensations'  => $compensations,
            'fineRecords'    => $fineRecords,
            // Widget dépenses
            'expenses'      => $driverExpenses,
            'expCategories' => $driverExpCats,
            'expTotals'     => $driverExpTotals,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request, $this->rules());
        $this->normalize($data, $request);
        $data = array_filter($data, fn($v) => $v !== '' && $v !== null);
        $id = Driver::create($data);
        $this->flash('success', 'Chauffeur créé avec succès.');
        redirect('referentiel/drivers/' . $id);
    }

    public function edit(Request $request, string $id): void
    {
        $driver  = Driver::findOrFail((int)$id);
        $gallery = MediaService::enrichAll(Media::forModel('drivers', (int)$id, 'gallery'));
        $docs    = MediaService::enrichAll(Media::forModel('drivers', (int)$id, 'documents'));

        $this->view('referentiel/drivers/form', [
            'title'    => 'Modifier ' . Driver::fullName($driver),
            'driver'   => $driver,
            'agencies' => Database::select("SELECT * FROM agencies WHERE is_active=1 ORDER BY name"),
            'buses'    => Database::select("SELECT id, code, plate FROM buses ORDER BY code"),
            'gallery'  => $gallery,
            'docs'     => $docs,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $rules = $this->rules((int)$id);
        $data = $this->validate($request, $rules);
        $this->normalize($data, $request);

        if (isset($data['primary_bus_id']) && (int)$data['primary_bus_id'] === 0) {
            $data['primary_bus_id'] = null;
        }
        if (isset($data['agency_id']) && (int)$data['agency_id'] === 0) {
            $data['agency_id'] = null;
        }

        Driver::update((int)$id, $data);
        $this->flash('success', 'Chauffeur mis à jour.');
        redirect('referentiel/drivers/' . $id);
    }

    public function destroy(Request $request, string $id): void
    {
        $mediaService = new MediaService();
        foreach (Media::forModel('drivers', (int)$id) as $m) {
            $mediaService->delete((int)$m['id']);
        }
        Driver::delete((int)$id);
        $this->flash('success', 'Chauffeur supprimé.');
        redirect('referentiel/drivers');
    }

    /** Règles de validation communes. $id = null pour création */
    private function rules(?int $id = null): array
    {
        $matRule = 'required|max:20|unique:drivers,matricule' . ($id ? ",$id" : '');
        return [
            'matricule'         => $matRule,
            'first_name'        => 'required|max:60',
            'last_name'         => 'required|max:60',
            'birth_date'        => 'date',
            'birth_place'       => 'max:80',
            'gender'            => 'in:M,F',
            'marital_status'    => 'in:celibataire,marie,divorce,veuf',
            'children_count'    => 'integer',
            'nationality'       => 'max:50',
            'blood_type'        => 'max:5',
            'national_id'       => 'max:40',
            'national_id_expiry'=> 'date',
            'phone'             => 'required|max:20',
            'phone_alt'         => 'max:20',
            'email'             => 'email|max:120',
            'address'           => '',
            'city'              => 'max:60',
            'emergency_name'    => 'max:100',
            'emergency_phone'   => 'max:20',
            'emergency_relation'=> 'max:50',
            'license_number'    => 'required|max:50',
            'license_categories'=> 'max:40',
            'license_issue_date'=> 'date',
            'license_expiry'    => 'required|date',
            'license_authority' => 'max:100',
            'medical_cert_expiry'=> 'date',
            'psycho_test_expiry'  => 'date',
            'ophthalmo_expiry'   => 'date',
            'drug_test_last'     => 'date',
            'hire_date'         => 'required|date',
            'experience_years'  => 'integer',
            'previous_employer' => 'max:120',
            'agency_id'         => 'integer',
            'primary_bus_id'    => 'integer',
            'status'            => 'required|in:actif,conge,suspendu,en_formation,accident,quitte',
            'salary_base'       => 'integer',
            'daily_bonus'       => 'integer',
            'km_bonus_rate'     => 'numeric',
            'bank_name'         => 'max:80',
            'bank_account'      => 'max:60',
            'mobile_money_number'=> 'max:20',
        ];
    }

    /** Normalise les checkboxes textuelles + categories permis. */
    private function normalize(array &$data, Request $request): void
    {
        // license_categories : si tableau, joindre par virgule
        $cats = $request->input('license_categories');
        if (is_array($cats)) {
            $data['license_categories'] = implode(',', array_filter(array_map('trim', $cats)));
        }
        $data['notes'] = $request->input('notes') ?? null;
    }
}
