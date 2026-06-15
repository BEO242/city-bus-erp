<?php

declare(strict_types=1);

namespace CityBus\Controllers\Cargo;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Setting;
use CityBus\Models\Parcel;
use CityBus\Services\CargoService;
use CityBus\Services\CashRegisterService;
use CityBus\Services\PdfService;

final class ParcelController extends Controller
{
    private CargoService $service;
    private CashRegisterService $caisse;
    private PdfService $pdf;

    public function __construct()
    {
        $this->service = new CargoService();
        $this->caisse  = new CashRegisterService();
        $this->pdf     = new PdfService();
    }

    // ─── Index / liste ────────────────────────────────────────────────
    public function index(Request $request): void
    {
        $q       = trim((string)$request->input('q', ''));
        $status  = trim((string)$request->input('status', ''));
        $type    = trim((string)$request->input('type', ''));
        $origin  = (int)$request->input('origin_agency_id', 0);
        $dest    = (int)$request->input('destination_agency_id', 0);
        $from    = trim((string)$request->input('from', ''));
        $to      = trim((string)$request->input('to', ''));

        $where  = ['p.deleted_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $where[] = '(p.parcel_number LIKE ? OR p.sender_phone LIKE ? OR p.recipient_phone LIKE ? OR p.sender_name LIKE ? OR p.recipient_name LIKE ?)';
            $like = "%$q%";
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($status !== '' && isset(Parcel::STATUSES[$status])) { $where[] = 'p.status = ?'; $params[] = $status; }
        if ($type   !== '' && isset(Parcel::TYPES[$type]))      { $where[] = 'p.parcel_type = ?'; $params[] = $type; }
        if ($origin > 0) { $where[] = 'p.origin_agency_id = ?';      $params[] = $origin; }
        if ($dest   > 0) { $where[] = 'p.destination_agency_id = ?'; $params[] = $dest; }
        if ($from !== '') { $where[] = 'DATE(p.deposited_at) >= ?'; $params[] = $from; }
        if ($to   !== '') { $where[] = 'DATE(p.deposited_at) <= ?'; $params[] = $to; }

        // Scope agence (chef d'agence ne voit que ses dépôts ou destinations)
        $user = Auth::user();
        if (Auth::role() !== 'admin' && Auth::role() !== 'raf' && !empty($user['agency_id'])) {
            $where[]  = '(p.origin_agency_id = ? OR p.destination_agency_id = ?)';
            array_push($params, (int)$user['agency_id'], (int)$user['agency_id']);
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $page     = max(1, (int)$request->input('page', 1));
        $perPage  = 30;
        $total    = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM parcels p $whereSql", $params
        )['c'] ?? 0);
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $offset   = ($page - 1) * $perPage;

        $parcels = Database::select(
            "SELECT p.*,
                    ao.name AS origin_agency,
                    ad.name AS destination_agency,
                    tr.trip_code, tr.trip_date
               FROM parcels p
               JOIN agencies ao ON ao.id = p.origin_agency_id
               JOIN agencies ad ON ad.id = p.destination_agency_id
               LEFT JOIN trips tr ON tr.id = p.trip_id
               $whereSql
             ORDER BY p.deposited_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $stats = Database::selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status='depose'     THEN 1 ELSE 0 END) AS deposes,
                SUM(CASE WHEN status='en_transit' THEN 1 ELSE 0 END) AS en_transit,
                SUM(CASE WHEN status='arrive'     THEN 1 ELSE 0 END) AS arrives,
                SUM(CASE WHEN status='retire'     THEN 1 ELSE 0 END) AS retires,
                COALESCE(SUM(total_price_fcfa),0) AS revenue
             FROM parcels p $whereSql", $params
        ) ?: ['total'=>0,'deposes'=>0,'en_transit'=>0,'arrives'=>0,'retires'=>0,'revenue'=>0];

        $this->view('cargo/parcels/index', [
            'title'    => 'Fret / Colis',
            'parcels'  => $parcels,
            'stats'    => $stats,
            'agencies' => Database::select("SELECT id, name FROM agencies WHERE is_active=1 ORDER BY name"),
            'statuses' => Parcel::STATUSES,
            'types'    => Parcel::TYPES,
            'filters'  => compact('q','status','type','origin','dest','from','to'),
            'page'     => $page,
            'lastPage' => $lastPage,
            'total'    => $total,
        ]);
    }

    // ─── Dashboard cargo ─────────────────────────────────────────────
    public function dashboard(Request $request): void
    {
        $today = date('Y-m-d');
        $kpis = Database::selectOne(
            "SELECT
                SUM(CASE WHEN DATE(deposited_at)=? THEN 1 ELSE 0 END) AS today_count,
                SUM(CASE WHEN DATE(deposited_at)=? THEN total_price_fcfa ELSE 0 END) AS today_revenue,
                SUM(CASE WHEN status='depose'     THEN 1 ELSE 0 END) AS pending_deposits,
                SUM(CASE WHEN status='en_transit' THEN 1 ELSE 0 END) AS in_transit,
                SUM(CASE WHEN status='arrive'     AND DATEDIFF(NOW(), updated_at) > ? THEN 1 ELSE 0 END) AS overdue_pickups
             FROM parcels WHERE deleted_at IS NULL",
            [$today, $today, Setting::getInt('cargo.default_pickup_days', 7)]
        ) ?: [];

        $topRoutes = Database::select(
            "SELECT ao.name AS origin, ad.name AS destination, COUNT(*) AS c, SUM(total_price_fcfa) AS revenue
             FROM parcels p
             JOIN agencies ao ON ao.id=p.origin_agency_id
             JOIN agencies ad ON ad.id=p.destination_agency_id
             WHERE p.deleted_at IS NULL AND DATE(p.deposited_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY p.origin_agency_id, p.destination_agency_id
             ORDER BY revenue DESC LIMIT 10"
        );

        $monthlyRevenue = Database::select(
            "SELECT DATE_FORMAT(deposited_at, '%Y-%m') AS ym, COUNT(*) AS c, COALESCE(SUM(total_price_fcfa),0) AS revenue
             FROM parcels
             WHERE deleted_at IS NULL AND deposited_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY ym ORDER BY ym ASC"
        );

        $overdue = Database::select(
            "SELECT p.*, ad.name AS dest_name
             FROM parcels p
             JOIN agencies ad ON ad.id = p.destination_agency_id
             WHERE p.deleted_at IS NULL
               AND p.status = 'arrive'
               AND DATEDIFF(NOW(), p.updated_at) > ?
             ORDER BY p.updated_at ASC LIMIT 20",
            [Setting::getInt('cargo.default_pickup_days', 7)]
        );

        $this->view('cargo/dashboard', [
            'title'          => 'Tableau de bord Fret',
            'kpis'           => $kpis,
            'topRoutes'      => $topRoutes,
            'monthlyRevenue' => $monthlyRevenue,
            'overdue'        => $overdue,
        ]);
    }

    // ─── Création (formulaire) ────────────────────────────────────────
    public function create(Request $request): void
    {
        $this->ensureCaisseOpen();
        $this->view('cargo/parcels/form', [
            'title'    => 'Déposer un colis',
            'parcel'   => null,
            'agencies' => Database::select("SELECT id, name FROM agencies WHERE is_active=1 ORDER BY name"),
            'tariffs'  => Database::select("SELECT * FROM parcel_tariffs WHERE is_active=1 ORDER BY category ASC, sort_order ASC"),
            'types'    => $this->loadFretCategories(),
            'methods'  => Parcel::PAYMENT_METHODS,
        ]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('cargo.create')) { $this->flash('danger', 'Permission refusée.'); back(); }

        $this->ensureCaisseOpen();
        $register = $this->caisse->currentForUser((int)Auth::id());

        $data = $this->validate($request, [
            'origin_agency_id'      => 'required|integer',
            'destination_agency_id' => 'required|integer',
            'sender_name'           => 'required|min:2|max:120',
            'sender_phone'          => 'required|min:6|max:30',
            'recipient_name'        => 'required|min:2|max:120',
            'recipient_phone'       => 'required|min:6|max:30',
            'parcel_type'           => 'required|in:' . implode(',', array_keys(Parcel::TYPES)),
            'description'           => 'required|min:3|max:500',
            'weight_kg'             => 'required|numeric',
            'declared_value_fcfa'   => 'integer',
            'pieces_count'          => 'integer',
            'volume_m3'             => 'numeric',
            'payment_method'        => 'required|in:' . implode(',', array_keys(Parcel::PAYMENT_METHODS)),
            'parcel_tariff_id'      => 'integer',
            'sender_id_doc'         => 'max:50',
            'recipient_id_doc'      => 'max:50',
            'sender_address'        => 'max:200',
            'recipient_address'     => 'max:200',
            'notes'                 => 'max:1000',
        ]);

        if ((int)$data['origin_agency_id'] === (int)$data['destination_agency_id']) {
            $this->flash('danger', "L'agence d'origine et de destination doivent être différentes.");
            back();
        }

        $data['cash_register_id'] = $register['id'] ?? null;

        try {
            $id = $this->service->deposit($data, (int)Auth::id());
            $this->flash('success', 'Colis enregistré avec succès.');
            redirect('cargo/parcels/' . $id);
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::error('cargo.deposit failed: ' . $e->getMessage());
            $this->flash('danger', 'Erreur lors du dépôt : ' . $e->getMessage());
            back();
        }
    }

    // ─── Détail + suivi ─────────────────────────────────────────────
    public function show(Request $request, string $id): void
    {
        $parcel = $this->loadOrFail((int)$id);
        $this->scopeCheck($parcel);
        $this->view('cargo/parcels/show', [
            'title'    => 'Colis ' . $parcel['parcel_number'],
            'parcel'   => $parcel,
            'timeline' => $this->service->timeline((int)$id),
            'statuses' => Parcel::STATUSES,
            'types'    => Parcel::TYPES,
            'methods'  => Parcel::PAYMENT_METHODS,
        ]);
    }

    // ─── Étiquette PDF ──────────────────────────────────────────────
    public function label(Request $request, string $id): void
    {
        $parcel = $this->loadOrFail((int)$id);
        $this->scopeCheck($parcel);

        $path = $this->pdf->generateParcelLabel($parcel);
        \CityBus\Core\Response::download(BASE_PATH . '/storage/' . $path, "etiquette-{$parcel['parcel_number']}.pdf", 'application/pdf');
    }

    // ─── Marquer chargement / arrivée / retrait ─────────────────────
    public function loadOnTrip(Request $request, string $id): void
    {
        if (!Auth::can('cargo.edit')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $tripId = (int)$request->input('trip_id', 0);
        if ($tripId <= 0) { $this->flash('danger', 'Voyage manquant.'); back(); }
        $this->service->loadOnTrip((int)$id, $tripId, (int)Auth::id());
        $this->flash('success', 'Colis chargé sur le voyage.');
        back();
    }

    public function markArrived(Request $request, string $id): void
    {
        if (!Auth::can('cargo.edit')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $this->service->markArrived((int)$id, (int)Auth::id());
        $this->flash('success', 'Arrivée enregistrée. Le destinataire est notifié.');
        back();
    }

    public function pickup(Request $request, string $id): void
    {
        if (!Auth::can('cargo.pickup')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $parcel = $this->loadOrFail((int)$id);
        if ($parcel['status'] !== 'arrive') {
            $this->flash('danger', 'Le colis doit être arrivé pour être retiré.');
            back();
        }

        $data = $this->validate($request, [
            'pickup_recipient_name' => 'max:120',
            'pickup_id_doc'         => 'max:50',
            'pickup_notes'          => 'max:1000',
        ]);

        $this->service->pickup((int)$id, $data, (int)Auth::id());
        $this->flash('success', 'Retrait enregistré.');
        redirect('cargo/parcels/' . (int)$id);
    }

    public function reportIssue(Request $request, string $id): void
    {
        if (!Auth::can('cargo.edit')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $type = (string)$request->input('issue_type', '');
        $desc = (string)$request->input('description', '');
        if (!in_array($type, ['perdu','endommage'], true) || strlen($desc) < 5) {
            $this->flash('danger', 'Type de litige ou description invalide.');
            back();
        }
        $this->service->reportIssue((int)$id, $type, $desc, (int)Auth::id());
        $this->flash('success', 'Litige enregistré.');
        back();
    }

    public function cancel(Request $request, string $id): void
    {
        if (!Auth::can('cargo.delete')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 5) {
            $this->flash('danger', 'Motif requis (min. 5 caractères).');
            back();
        }
        $this->service->cancel((int)$id, $reason, (int)Auth::id());
        $this->flash('success', 'Colis annulé.');
        redirect('cargo/parcels');
    }

    // ─── Recherche par code (publique côté contrôleur, pour scan QR) ─
    public function lookup(Request $request): void
    {
        $code = trim((string)$request->input('code', ''));
        $parcel = $code ? $this->service->findByCodeOrToken($code) : null;
        if (!$parcel) {
            $this->flash('danger', 'Aucun colis trouvé pour ce code.');
            redirect('cargo/parcels');
        }
        redirect('cargo/parcels/' . (int)$parcel['id']);
    }

    // ─── Calc prix (AJAX) ───────────────────────────────────────────
    public function quote(Request $request): void
    {
        $type    = (string)$request->input('type', 'colis');
        $weight  = (float)$request->input('weight_kg', 0);
        $declared = (int)$request->input('declared_value_fcfa', 0);
        $tariffId = $request->input('parcel_tariff_id') ? (int)$request->input('parcel_tariff_id') : null;
        $this->json($this->service->quote($type, $weight, $declared, $tariffId));
    }

    // ─── Manifeste cargo d'un voyage ────────────────────────────────
    public function manifest(Request $request, string $tripId): void
    {
        $trip = Database::selectOne(
            "SELECT tr.*, l.name AS line_name, l.code AS line_code, b.code AS bus_code, b.plate
             FROM trips tr
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             LEFT JOIN buses b ON b.id = tr.bus_id
             WHERE tr.id = ?", [(int)$tripId]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        $parcels = $this->service->manifestForTrip((int)$tripId);
        $totals  = [
            'count'    => count($parcels),
            'weight'   => array_sum(array_map(fn($p) => (float)$p['weight_kg'], $parcels)),
            'revenue'  => array_sum(array_map(fn($p) => (int)$p['total_price_fcfa'], $parcels)),
            'declared' => array_sum(array_map(fn($p) => (int)$p['declared_value_fcfa'], $parcels)),
        ];

        if ($request->input('pdf') === '1') {
            $path = $this->pdf->generateCargoManifest($trip, $parcels, $totals);
            \CityBus\Core\Response::download(
                BASE_PATH . '/storage/' . $path,
                "manifeste-cargo-{$trip['trip_code']}.pdf",
                'application/pdf'
            );
        }

        $this->view('cargo/manifest', [
            'title'   => 'Manifeste fret · ' . ($trip['trip_code'] ?? ''),
            'trip'    => $trip,
            'parcels' => $parcels,
            'totals'  => $totals,
        ]);
    }

    // ─── Privé ──────────────────────────────────────────────────────

    private function loadOrFail(int $id): array
    {
        $parcel = Database::selectOne(
            "SELECT p.*, ao.name AS origin_agency, ao.address AS origin_address, ao.phone AS origin_phone,
                          ad.name AS destination_agency, ad.address AS destination_address, ad.phone AS destination_phone,
                          tr.trip_code, tr.trip_date,
                          CONCAT(u.first_name,' ',u.last_name) AS deposited_by_name,
                          CONCAT(uu.first_name,' ',uu.last_name) AS picked_up_by_name
               FROM parcels p
               JOIN agencies ao ON ao.id = p.origin_agency_id
               JOIN agencies ad ON ad.id = p.destination_agency_id
               LEFT JOIN trips tr ON tr.id = p.trip_id
               LEFT JOIN users u  ON u.id  = p.deposited_by
               LEFT JOIN users uu ON uu.id = p.picked_up_by
              WHERE p.id = ?",
            [$id]
        );
        if (!$parcel) {
            http_response_code(404);
            $this->view('errors/404');
            exit;
        }
        return $parcel;
    }

    private function scopeCheck(array $parcel): void
    {
        $user = Auth::user();
        if (Auth::role() === 'admin' || Auth::role() === 'raf') return;
        if (empty($user['agency_id'])) return;
        $aid = (int)$user['agency_id'];
        if ((int)$parcel['origin_agency_id'] !== $aid && (int)$parcel['destination_agency_id'] !== $aid) {
            http_response_code(403);
            $this->view('errors/403', ['permission' => 'cargo.scope']);
            exit;
        }
    }

    private function ensureCaisseOpen(): void
    {
        if (!Setting::getBool('caisse.require_open_session', true)) return;
        $register = $this->caisse->currentForUser((int)Auth::id());
        if (!$register && in_array(Auth::role(), ['caissier','agent'], true)) {
            $this->flash('warning', 'Ouvrez votre caisse avant de déposer un colis.');
            redirect('caisse/open');
        }
    }

    /**
     * Charge les catégories fret depuis la table parcel_tariffs.
     * Retourne un tableau [category => label] pour les datalists/selects.
     */
    private function loadFretCategories(): array
    {
        return \CityBus\Controllers\Cargo\ParcelTariffController::loadCategories();
    }
}
