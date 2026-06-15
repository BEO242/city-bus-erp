<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;
use CityBus\Models\Bus;
use CityBus\Models\Media;
use CityBus\Models\Note;
use CityBus\Services\MediaService;
use CityBus\Services\TreasuryExpenseService;

final class BusController extends Controller
{
    public function index(Request $request): void
    {
        $statusFilter = trim((string)$request->input('status', ''));
        $agencyFilter = (int)$request->input('agency_id', 0);
        $search       = trim((string)$request->input('q', ''));
        $alertsFilter = trim((string)$request->input('alerts', ''));

        $validSorts = ['code', 'plate', 'brand', 'year', 'km_current', 'status', 'created_at', 'alerts_n'];
        $sortField  = in_array($request->input('sort'), $validSorts, true) ? $request->input('sort') : 'code';
        $sortDir    = $request->input('dir') === 'desc' ? 'DESC' : 'ASC';

        $perPageOpts = [12, 24, 48, 9999];
        $perPage     = in_array((int)$request->input('per_page'), $perPageOpts, true) ? (int)$request->input('per_page') : 24;
        $page        = max(1, (int)$request->input('page', 1));

        $sql    = "SELECT b.*, a.name AS agency_name
                   FROM buses b
                   LEFT JOIN agencies a ON a.id = b.agency_id";
        $where  = [];
        $params = [];

        if ($statusFilter !== '' && isset(Bus::STATUSES[$statusFilter])) {
            $where[] = 'b.status = ?';
            $params[] = $statusFilter;
        }
        if ($agencyFilter > 0) {
            $where[] = 'b.agency_id = ?';
            $params[] = $agencyFilter;
        }
        if ($search !== '') {
            $where[] = '(b.code LIKE ? OR b.plate LIKE ? OR b.brand LIKE ? OR b.model LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // Tri SQL (sauf alerts_n calculé en PHP)
        $sqlSort = $sortField !== 'alerts_n' ? $sortField : 'code';
        $sql .= " ORDER BY b.{$sqlSort} {$sortDir}";

        $buses = Database::select($sql, $params);

        // Enrichir : couverture + alertes
        foreach ($buses as &$bus) {
            $cover = Media::cover('buses', (int)$bus['id']);
            $bus['cover_url'] = $cover ? Media::getThumbUrl($cover) : null;
            $bus['alerts']    = Bus::alerts($bus);
            $bus['alerts_n']  = count(array_filter($bus['alerts'], fn($a) => $a['level'] === 'danger'));
        }
        unset($bus);

        // Tri PHP pour alerts_n
        if ($sortField === 'alerts_n') {
            usort($buses, fn($a, $b) => $sortDir === 'ASC'
                ? $a['alerts_n'] <=> $b['alerts_n']
                : $b['alerts_n'] <=> $a['alerts_n']
            );
        }

        // Filtre PHP alertes
        if ($alertsFilter === 'yes') {
            $buses = array_values(array_filter($buses, fn($b) => $b['alerts_n'] > 0));
        } elseif ($alertsFilter === 'no') {
            $buses = array_values(array_filter($buses, fn($b) => $b['alerts_n'] === 0));
        }

        // Pagination PHP
        $total    = count($buses);
        $lastPage = $perPage === 9999 ? 1 : (int)ceil($total / $perPage);
        $lastPage = max(1, $lastPage);
        $page     = max(1, min($page, $lastPage));
        $buses    = $perPage === 9999 ? $buses : array_slice($buses, ($page - 1) * $perPage, $perPage);

        $this->view('referentiel/vehicules/index', [
            'title'        => 'Véhicules',
            'buses'        => $buses,
            'agencies'     => Database::select("SELECT * FROM agencies WHERE is_active=1 ORDER BY name"),
            'statusFilter' => $statusFilter,
            'agencyFilter' => $agencyFilter,
            'alertsFilter' => $alertsFilter,
            'search'       => $search,
            'sortField'    => $sortField,
            'sortDir'      => strtolower($sortDir),
            'perPage'      => $perPage,
            'page'         => $page,
            'total'        => $total,
            'lastPage'     => $lastPage,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('referentiel/vehicules/form', [
            'title'        => 'Nouveau véhicule',
            'bus'          => null,
            'agencies'     => Database::select("SELECT * FROM agencies WHERE is_active=1 ORDER BY name"),
            'drivers'      => Database::select("SELECT id, first_name, last_name, matricule FROM drivers WHERE deleted_at IS NULL AND status IN ('actif','conge') ORDER BY last_name, first_name"),
            'vehicleTypes' => Bus::vehicleTypes(),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $bus = Bus::findOrFail((int)$id);
        $bus['agency_name'] = Database::selectOne("SELECT name FROM agencies WHERE id=?", [$bus['agency_id']])['name'] ?? '—';

        $primaryDriver = null;
        if (!empty($bus['primary_driver_id'])) {
            $primaryDriver = Database::selectOne(
                "SELECT id, matricule, first_name, last_name, phone, license_number, license_expiry, rating_score
                 FROM drivers WHERE id=? AND deleted_at IS NULL",
                [$bus['primary_driver_id']]
            );
        }

        $gallery = MediaService::enrichAll(Media::forModel('buses', (int)$id, 'gallery'));
        $docs    = MediaService::enrichAll(Media::forModel('buses', (int)$id, 'documents'));

        $stats     = Bus::stats((int)$id);
        $alerts    = Bus::alerts($bus);
        $trips     = Bus::recentTrips((int)$id, 8);
        $notes     = Note::forEntity('buses', (int)$id);
        $incidents = Database::select(
            "SELECT i.*, CONCAT(d.first_name,' ',d.last_name) AS driver_name
             FROM incidents i LEFT JOIN drivers d ON d.id=i.driver_id
             WHERE i.subject_type='bus' AND i.subject_id=?
             ORDER BY i.occurred_at DESC LIMIT 30",
            [$id]
        );

        $maintenance = Database::select(
            "SELECT mo.*, CONCAT(e.first_name,' ',e.last_name) AS mechanic_name
             FROM maintenance_orders mo
             LEFT JOIN employees e ON e.id = mo.mechanic_id
             WHERE mo.bus_id = ?
             ORDER BY COALESCE(mo.done_at, mo.scheduled_at, mo.created_at) DESC LIMIT 30",
            [$id]
        );

        $fuelLogs = Database::select(
            "SELECT fl.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM fuel_logs fl
             LEFT JOIN users u ON u.id = fl.logged_by
             WHERE fl.bus_id = ?
             ORDER BY fl.logged_at DESC LIMIT 30",
            [$id]
        );

        $mechanics = Database::select(
            "SELECT id, first_name, last_name, matricule
             FROM employees WHERE status='actif' AND deleted_at IS NULL
             ORDER BY last_name, first_name"
        );

        // Données des tables spécifiques
        $tireRecords = Database::select(
            "SELECT tr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM tire_records tr LEFT JOIN users u ON u.id = tr.logged_by
             WHERE tr.bus_id = ? ORDER BY tr.created_at DESC LIMIT 50", [$id]
        );
        $insuranceRecords = Database::select(
            "SELECT ir.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM insurance_records ir LEFT JOIN users u ON u.id = ir.logged_by
             WHERE ir.bus_id = ? ORDER BY ir.created_at DESC LIMIT 30", [$id]
        );
        $inspectionRecords = Database::select(
            "SELECT inr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM inspection_records inr LEFT JOIN users u ON u.id = inr.logged_by
             WHERE inr.bus_id = ? ORDER BY inr.created_at DESC LIMIT 30", [$id]
        );
        $washRecords = Database::select(
            "SELECT wr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM wash_records wr LEFT JOIN users u ON u.id = wr.logged_by
             WHERE wr.bus_id = ? ORDER BY wr.created_at DESC LIMIT 50", [$id]
        );
        $tollRecords = Database::select(
            "SELECT tr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM toll_records tr LEFT JOIN users u ON u.id = tr.logged_by
             WHERE tr.bus_id = ? ORDER BY tr.created_at DESC LIMIT 50", [$id]
        );
        $parkingRecords = Database::select(
            "SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM parking_records pr LEFT JOIN users u ON u.id = pr.logged_by
             WHERE pr.bus_id = ? ORDER BY pr.created_at DESC LIMIT 50", [$id]
        );
        $fineRecords = Database::select(
            "SELECT fr.*, CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
             FROM fine_records fr LEFT JOIN users u ON u.id = fr.logged_by
             WHERE fr.bus_id = ? ORDER BY fr.created_at DESC LIMIT 50", [$id]
        );

        // Dépenses bidirectionnelles
        $expService    = new TreasuryExpenseService();
        $busExpenses   = $expService->forBus((int)$id);
        $busExpCats    = $expService->categoriesFor('bus');
        $busExpTotals  = $expService->totalsFor('bus', (int)$id);

        // Type de véhicule
        $vehicleType = !empty($bus['vehicle_type_id'])
            ? Database::selectOne("SELECT * FROM vehicle_types WHERE id = ?", [$bus['vehicle_type_id']])
            : null;

        $this->view('referentiel/vehicules/show', [
            'title'         => $bus['code'] . ' — Fiche véhicule',
            'vehicleType'   => $vehicleType,
            'bus'           => $bus,
            'gallery'       => $gallery,
            'docs'          => $docs,
            'stats'         => $stats,
            'alerts'        => $alerts,
            'trips'         => $trips,
            'notes'         => $notes,
            'incidents'     => $incidents,
            'maintenance'   => $maintenance,
            'fuelLogs'      => $fuelLogs,
            'mechanics'     => $mechanics,
            'primaryDriver' => $primaryDriver,
            // Tables spécifiques
            'tireRecords'      => $tireRecords,
            'insuranceRecords' => $insuranceRecords,
            'inspectionRecords'=> $inspectionRecords,
            'washRecords'      => $washRecords,
            'tollRecords'      => $tollRecords,
            'parkingRecords'   => $parkingRecords,
            'fineRecords'      => $fineRecords,
            // Widget dépenses
            'expenses'      => $busExpenses,
            'expCategories' => $busExpCats,
            'expTotals'     => $busExpTotals,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request, [
            'code'                => 'required|max:20|unique:buses,code',
            'plate'               => 'required|max:20|unique:buses,plate',
            'brand'               => 'max:50',
            'model'               => 'max:50',
            'vehicle_type_id'     => 'integer',
            'body_type'           => 'max:30',
            'color'               => 'max:30',
            'vin'                 => 'max:50',
            'engine_number'       => 'max:50',
            'fuel_type'           => 'in:diesel,essence,hybride,electrique',
            'transmission'        => 'in:manuelle,automatique',
            'year'                => 'integer',
            'seats'               => 'required|integer',
            'agency_id'           => 'integer',
            'primary_driver_id'   => 'integer',
            'status'              => 'required|in:disponible,en_voyage,maintenance,hors_service',
            'km_current'          => 'integer',
            'mileage_at_purchase' => 'integer',
            'purchase_date'       => 'date',
            'purchase_price_fcfa' => 'integer',
            'supplier'            => 'max:100',
            'financing_type'      => 'in:cash,leasing,credit,don',
            'registration_card_number' => 'max:50',
            'registration_card_date'   => 'date',
            'insurance_expiry'    => 'date',
            'insurance_company'   => 'max:100',
            'insurance_policy'    => 'max:100',
            'tech_control_expiry' => 'date',
            'tech_control_center' => 'max:100',
            'next_maintenance_at' => 'date',
            'next_maintenance_km' => 'integer',
            // Specs
            'length_m'            => 'numeric',
            'width_m'             => 'numeric',
            'height_m'            => 'numeric',
            'weight_empty_kg'     => 'integer',
            'weight_max_kg'       => 'integer',
            'cargo_capacity_kg'   => 'integer',
            'fuel_tank_l'         => 'integer',
            'consumption_avg_l'   => 'numeric',
            'axles_count'         => 'integer',
            'airbags_count'       => 'integer',
            // GPS
            'gps_provider'        => 'max:60',
            'gps_device_id'       => 'max:60',
            'gps_sim_number'      => 'max:30',
        ]);

        // Checkboxes (non soumis si décoché)
        foreach (['ac','gps_tracker','wifi','abs_brakes','esp_system','retarder','seatbelts_all','tachograph'] as $cb) {
            $data[$cb] = $request->input($cb) ? 1 : 0;
        }
        $data['notes'] = $request->input('notes') ?? null;

        // Équipements supplémentaires (JSON)
        $extraRaw = $request->input('equipment_extra') ?? '[]';
        $extraArr = json_decode($extraRaw, true);
        $data['equipment_extra'] = json_encode(
            is_array($extraArr) ? array_values(array_filter(array_map('trim', $extraArr))) : []
        );

        // Supprimer les clés vides pour ne pas écraser avec NULL
        $data = array_filter($data, fn($v) => $v !== '' && $v !== null);

        $busId = Bus::create($data);
        AuditLog::record('vehicle.create', 'vehicle', (int)$busId, ['code' => $data['code'] ?? '', 'plate' => $data['plate'] ?? '']);
        $this->flash('success', 'Véhicule créé avec succès.');
        redirect('referentiel/vehicules/' . $busId);
    }

    public function edit(Request $request, string $id): void
    {
        $bus     = Bus::findOrFail((int)$id);
        $gallery = MediaService::enrichAll(Media::forModel('buses', (int)$id, 'gallery'));
        $docs    = MediaService::enrichAll(Media::forModel('buses', (int)$id, 'documents'));

        $this->view('referentiel/vehicules/form', [
            'title'        => 'Modifier ' . $bus['code'],
            'bus'          => $bus,
            'agencies'     => Database::select("SELECT * FROM agencies WHERE is_active=1 ORDER BY name"),
            'drivers'      => Database::select("SELECT id, first_name, last_name, matricule FROM drivers WHERE deleted_at IS NULL AND status IN ('actif','conge') ORDER BY last_name, first_name"),
            'gallery'      => $gallery,
            'docs'         => $docs,
            'vehicleTypes' => Bus::vehicleTypes(),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $data = $this->validate($request, [
            'code'                => "required|max:20|unique:buses,code,$id",
            'plate'               => "required|max:20|unique:buses,plate,$id",
            'brand'               => 'max:50',
            'model'               => 'max:50',
            'vehicle_type_id'     => 'integer',
            'body_type'           => 'max:30',
            'color'               => 'max:30',
            'vin'                 => 'max:50',
            'engine_number'       => 'max:50',
            'fuel_type'           => 'in:diesel,essence,hybride,electrique',
            'transmission'        => 'in:manuelle,automatique',
            'year'                => 'integer',
            'seats'               => 'required|integer',
            'agency_id'           => 'integer',
            'primary_driver_id'   => 'integer',
            'status'              => 'required|in:disponible,en_voyage,maintenance,hors_service',
            'km_current'          => 'integer',
            'mileage_at_purchase' => 'integer',
            'purchase_date'       => 'date',
            'purchase_price_fcfa' => 'integer',
            'supplier'            => 'max:100',
            'financing_type'      => 'in:cash,leasing,credit,don',
            'registration_card_number' => 'max:50',
            'registration_card_date'   => 'date',
            'insurance_expiry'    => 'date',
            'insurance_company'   => 'max:100',
            'insurance_policy'    => 'max:100',
            'tech_control_expiry' => 'date',
            'tech_control_center' => 'max:100',
            'next_maintenance_at' => 'date',
            'next_maintenance_km' => 'integer',
            'length_m'            => 'numeric',
            'width_m'             => 'numeric',
            'height_m'            => 'numeric',
            'weight_empty_kg'     => 'integer',
            'weight_max_kg'       => 'integer',
            'cargo_capacity_kg'   => 'integer',
            'fuel_tank_l'         => 'integer',
            'consumption_avg_l'   => 'numeric',
            'axles_count'         => 'integer',
            'airbags_count'       => 'integer',
            'gps_provider'        => 'max:60',
            'gps_device_id'       => 'max:60',
            'gps_sim_number'      => 'max:30',
        ]);

        foreach (['ac','gps_tracker','wifi','abs_brakes','esp_system','retarder','seatbelts_all','tachograph'] as $cb) {
            $data[$cb] = $request->input($cb) ? 1 : 0;
        }
        $data['notes'] = $request->input('notes') ?? null;

        // Équipements supplémentaires (JSON)
        $extraRaw = $request->input('equipment_extra') ?? '[]';
        $extraArr = json_decode($extraRaw, true);
        $data['equipment_extra'] = json_encode(
            is_array($extraArr) ? array_values(array_filter(array_map('trim', $extraArr))) : []
        );

        // Normaliser les champs optionnels vides en NULL (évite notamment
        // les collisions sur contrainte UNIQUE avec "" comme valeur).
        foreach ($data as $key => $value) {
            if ($value === '') {
                $data[$key] = null;
            }
        }

        // primary_driver_id=0 → NULL
        if (isset($data['primary_driver_id']) && (int)$data['primary_driver_id'] === 0) {
            $data['primary_driver_id'] = null;
        }

        Bus::update((int)$id, $data);
        $this->flash('success', 'Véhicule mis à jour.');
        redirect('referentiel/vehicules/' . $id);
    }

    public function destroy(Request $request, string $id): void
    {
        // Vérifier l'absence de voyages actifs sur ce bus
        $activeTrips = Database::selectOne(
            "SELECT COUNT(*) AS n FROM trips WHERE bus_id=? AND status NOT IN ('annule','cloture')",
            [(int)$id]
        );
        if ((int)($activeTrips['n'] ?? 0) > 0) {
            $this->flash('danger', "Impossible de supprimer : {$activeTrips['n']} voyage(s) actif(s) sur ce véhicule.");
            back(); return;
        }

        // Supprimer les médias associés
        $mediaService = new MediaService();
        $allMedia = Media::forModel('buses', (int)$id);
        foreach ($allMedia as $m) {
            $mediaService->delete((int)$m['id']);
        }

        Bus::delete((int)$id);
        $this->flash('success', 'Véhicule supprimé.');
        redirect('referentiel/vehicules');
    }
}

