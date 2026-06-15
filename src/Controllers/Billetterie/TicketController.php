<?php

declare(strict_types=1);

namespace CityBus\Controllers\Billetterie;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Models\AuditLog;
use CityBus\Models\Tariff;
use CityBus\Services\CashRegisterService;
use CityBus\Services\TicketService;
use CityBus\Services\BaggageTicketService;
use CityBus\Services\TripService;

final class TicketController extends Controller
{
    public function __construct(
        private TicketService        $service        = new TicketService(),
        private CashRegisterService  $caisse         = new CashRegisterService(),
        private TripService          $trips          = new TripService(),
        private BaggageTicketService $baggageService = new BaggageTicketService(),
    ) {}

    // ─── Helper : bloque l'accès si aucune caisse ouverte ────────────────────
    private function requireCaisse(): ?array
    {
        if (!\CityBus\Core\Setting::getBool('caisse.require_open_session', true)) {
            return null; // contrainte désactivée
        }
        $register = $this->caisse->currentForUser((int)Auth::id());
        if (!$register) {
            $this->flash('warning', 'Vous devez ouvrir une caisse avant d\'accéder à la billetterie.');
            redirect('caisse');
        }
        return $register;
    }

    // ─── INDEX : liste des billets passagers vendus ───────────────────────────
    public function index(Request $request): void
    {
        $page         = max(1, (int)$request->input('page', 1));
        $perPage      = 30;
        $q            = trim((string)$request->input('q', ''));
        $lineFilter   = (int)$request->input('line_id', 0);
        $statusFilter = trim((string)$request->input('status', ''));
        $dateFrom     = trim((string)$request->input('date_from', ''));
        $dateUntil    = trim((string)$request->input('date_until', ''));

        $where  = ['t.deleted_at IS NULL'];
        $params = [];

        if ($q !== '') {
            $where[] = '(t.ticket_number LIKE ? OR t.passenger_name LIKE ? OR t.passenger_phone LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        if ($lineFilter > 0) {
            $where[]  = 'tr.line_id = ?';
            $params[] = $lineFilter;
        }
        if (in_array($statusFilter, ['emis','valide','arrive','annule'], true)) {
            $where[]  = 't.status = ?';
            $params[] = $statusFilter;
        }
        if ($dateFrom !== '') {
            $where[]  = 'tr.trip_date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateUntil !== '') {
            $where[]  = 'tr.trip_date <= ?';
            $params[] = $dateUntil;
        }
        // Caissier ne voit que ses propres ventes
        if (Auth::role() === 'caissier') {
            $where[]  = 't.sold_by = ?';
            $params[] = Auth::id();
        }

        $whereSql = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $tickets = Database::select(
            "SELECT t.id, t.ticket_number, t.passenger_name, t.passenger_phone,
                    t.seat_number, t.price_fcfa, t.status, t.sold_at,
                    t.ticket_type, t.passenger_category, t.travel_class,
                    tr.trip_date, tr.departure_scheduled,
                    l.code AS line_code, l.name AS line_name,
                    u.first_name AS sold_by_first, u.last_name AS sold_by_last
               FROM tickets t
               JOIN trips tr      ON tr.id = t.trip_id
               JOIN bus_lines l   ON l.id  = tr.line_id
               LEFT JOIN users u  ON u.id  = t.sold_by
              WHERE $whereSql
              ORDER BY t.sold_at DESC
              LIMIT $perPage OFFSET $offset",
            $params
        );

        $total = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM tickets t
               JOIN trips tr      ON tr.id = t.trip_id
               JOIN bus_lines l   ON l.id  = tr.line_id
              WHERE $whereSql",
            $params
        )['c'] ?? 0);

        // KPI globaux (scope = mêmes filtres sauf pagination)
        $statsRows = Database::select(
            "SELECT t.status, COUNT(*) AS cnt, SUM(t.price_fcfa) AS rev
               FROM tickets t
               JOIN trips tr      ON tr.id = t.trip_id
               JOIN bus_lines l   ON l.id  = tr.line_id
              WHERE $whereSql
              GROUP BY t.status",
            $params
        );
        $stats = ['emis' => 0, 'valide' => 0, 'arrive' => 0, 'annule' => 0, 'revenu' => 0];
        foreach ($statsRows as $row) {
            $s = $row['status'];
            if (isset($stats[$s])) $stats[$s] = (int)$row['cnt'];
            if ($s !== 'annule') $stats['revenu'] += (int)$row['rev'];
        }

        $this->view('billetterie/index', [
            'title'        => 'Billets passagers',
            'tickets'      => $tickets,
            'q'            => $q,
            'lineFilter'   => $lineFilter,
            'statusFilter' => $statusFilter,
            'dateFrom'     => $dateFrom,
            'dateUntil'    => $dateUntil,
            'page'         => $page,
            'perPage'      => $perPage,
            'total'        => $total,
            'lastPage'     => max(1, (int)ceil($total / $perPage)),
            'lines'        => Database::select("SELECT id, code, name FROM bus_lines ORDER BY code"),
            'stats'        => $stats,
        ]);
    }

    // ─── SELECT-TRIP : choix du voyage ────────────────────────────────────────
    public function selectTrip(Request $request): void
    {
        $this->requireCaisse();

        $dateFrom     = trim((string)$request->input('date_from', ''));
        $dateTo       = trim((string)$request->input('date_to', ''));
        $lineId       = (int)$request->input('line_id', 0);
        $statusFilter = trim((string)$request->input('status', ''));
        $availability = trim((string)$request->input('availability', ''));

        // Détecter si l'utilisateur a soumis des filtres actifs
        $hasFilter = ($dateFrom !== '' || $dateTo !== '' || $lineId > 0
                   || $statusFilter !== '' || $availability !== '');

        // Normaliser les dates si une seule est fournie
        if ($dateFrom !== '' && $dateTo === '') {
            $dateTo = $dateFrom;
        } elseif ($dateTo !== '' && $dateFrom === '') {
            $dateFrom = $dateTo;
        }
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        if (!in_array($statusFilter, ['', 'open', 'planifie', 'valide', 'embarquement'], true)) {
            $statusFilter = '';
        }
        if (!in_array($availability, ['', 'all', 'available', 'full', 'low'], true)) {
            $availability = '';
        }

        $subquery = "SELECT tr.*, l.name AS line_name, l.code AS line_code,
                            cd.slug AS departure_city, cd.name AS departure_city_name,
                            ca.slug AS arrival_city,   ca.name AS arrival_city_name,
                            b.code AS bus_code, b.seats,
                            (SELECT COUNT(*) FROM tickets t
                               WHERE t.trip_id = tr.id
                                 AND t.status != 'annule'
                                 AND t.deleted_at IS NULL) AS sold_seats,
                            (b.seats - (SELECT COUNT(*) FROM tickets t2
                                          WHERE t2.trip_id = tr.id
                                            AND t2.status != 'annule'
                                            AND t2.deleted_at IS NULL)) AS available_seats
                       FROM trips tr
                       JOIN bus_lines l ON l.id = tr.line_id
                       JOIN cities cd   ON cd.id = l.departure_city_id
                       JOIN cities ca   ON ca.id = l.arrival_city_id
                       JOIN buses b     ON b.id = tr.bus_id";

        $sql    = "SELECT q.* FROM ({$subquery}) q WHERE 1=1";
        $params = [];

        // Filtre date (seulement si renseigné)
        if ($dateFrom !== '' && $dateTo !== '') {
            $sql     .= " AND q.trip_date BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }

        if ($lineId > 0) {
            $sql     .= " AND q.line_id = ?";
            $params[] = $lineId;
        }

        // Filtre statut
        if ($statusFilter === 'open') {
            $sql .= " AND q.status IN ('planifie','valide','embarquement')";
        } elseif ($statusFilter !== '') {
            $sql     .= " AND q.status = ?";
            $params[] = $statusFilter;
        }
        // '' = tous les statuts (pas de restriction)

        // Filtre disponibilité
        if ($availability === 'available') {
            $sql .= " AND q.available_seats > 0";
        } elseif ($availability === 'full') {
            $sql .= " AND q.available_seats <= 0";
        } elseif ($availability === 'low') {
            $sql .= " AND q.available_seats BETWEEN 1 AND 10";
        }

        if (!$hasFilter) {
            // Défaut : 30 derniers voyages, tous statuts, sans contrainte de date
            $sql .= " ORDER BY q.trip_date DESC, q.departure_scheduled DESC LIMIT 30";
        } else {
            $sql .= " ORDER BY q.trip_date ASC, q.departure_scheduled ASC";
        }

        $trips = Database::select($sql, $params);

        $this->view('billetterie/select-trip', [
            'title'        => 'Vendre un billet passager',
            'trips'        => $trips,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'lineId'       => $lineId,
            'statusFilter' => $statusFilter,
            'availability' => $availability,
            'hasFilter'    => $hasFilter,
            'lines'        => Database::select("SELECT id, code, name FROM bus_lines ORDER BY code"),
        ]);
    }

    // ─── SHOW-SALE : formulaire de vente ──────────────────────────────────────
    public function showSale(Request $request, string $tripId): void
    {
        $this->requireCaisse();

        $trip = Database::selectOne(
            "SELECT tr.*, l.name AS line_name, l.code AS line_code, l.id AS line_id,
                    b.code AS bus_code, b.seats AS bus_seats
               FROM trips tr
               JOIN bus_lines l ON l.id = tr.line_id
               JOIN buses b     ON b.id = tr.bus_id
              WHERE tr.id = ?",
            [$tripId]
        );
        if (!$trip) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }

        $bookedSeats = $this->trips->bookedSeats((int)$tripId);
        $register    = $this->caisse->currentForUser((int)Auth::id());
        $stops       = Database::select(
            "SELECT id, name, order_position FROM stops WHERE line_id = ? ORDER BY order_position",
            [(int)$trip['line_id']]
        );

        // Périmètres disponibles : expand JSON passenger_categories en entrées individuelles
        $scopeRows = Database::select(
            "SELECT DISTINCT t.ticket_type, t.passenger_categories, t.travel_class
               FROM tariffs t
              WHERE t.line_id = ?
                AND t.is_active = 1
                AND (t.valid_from  IS NULL OR t.valid_from  <= ?)
                AND (t.valid_until IS NULL OR t.valid_until >= ?)
              ORDER BY t.ticket_type, t.travel_class",
            [(int)$trip['line_id'], $trip['trip_date'], $trip['trip_date']]
        );
        $scopesSeen      = [];
        $availableScopes = [];
        foreach ($scopeRows as $row) {
            $cats = json_decode($row['passenger_categories'] ?? '[]', true) ?: [];
            foreach ($cats as $cat) {
                $key = $row['ticket_type'] . '|' . $cat . '|' . $row['travel_class'];
                if (!isset($scopesSeen[$key])) {
                    $scopesSeen[$key]   = true;
                    $availableScopes[] = [
                        'ticket_type'        => $row['ticket_type'],
                        'passenger_category' => $cat,
                        'travel_class'       => $row['travel_class'],
                    ];
                }
            }
        }

        // Tarifs bagages disponibles pour la ligne (excédent en temps réel)
        $baggageTariffs = Database::select(
            "SELECT bt.id, bt.label, bt.baggage_nature_ids
               FROM baggage_tariffs bt
              WHERE bt.line_id = ?
                AND bt.is_active = 1
                AND (bt.valid_from  IS NULL OR bt.valid_from  <= ?)
                AND (bt.valid_until IS NULL OR bt.valid_until >= ?)
              ORDER BY bt.id",
            [(int)$trip['line_id'], $trip['trip_date'], $trip['trip_date']]
        );

        $fretCategories = Database::select(
            "SELECT slug, label, price_per_kg, min_price_fcfa, color
               FROM fret_categories WHERE is_active = 1 ORDER BY sort_order"
        );

        $this->view('billetterie/sale', [
            'title'              => 'Vente — ' . $trip['line_name'],
            'trip'               => $trip,
            'bookedSeats'        => $bookedSeats,
            'register'           => $register,
            'stops'              => $stops,
            'availableScopes'    => $availableScopes,
            'ticketTypes'        => Tariff::ticketTypesFull(),
            'passengerCategories'=> Tariff::passengerCategoriesFull(),
            'travelClasses'      => Tariff::travelClassesFull(),
            'baggageTariffs'     => $baggageTariffs,
            'fretCategories'     => $fretCategories,
        ]);
    }

    // ─── RESOLVE-TARIFF : AJAX endpoint ───────────────────────────────────────
    public function resolveTariff(Request $request): void
    {
        $lineId       = (int)$request->input('line_id', 0);
        $type         = trim((string)$request->input('ticket_type', ''));
        $cat          = trim((string)$request->input('passenger_category', 'adulte'));
        $class        = trim((string)$request->input('travel_class', 'standard'));
        $date         = trim((string)$request->input('date', date('Y-m-d')));
        $originStopId = $request->input('origin_stop_id')      ? (int)$request->input('origin_stop_id')      : null;
        $destStopId   = $request->input('destination_stop_id') ? (int)$request->input('destination_stop_id') : null;

        if ($lineId <= 0 || $type === '') {
            $this->json(['ok' => false, 'error' => 'Paramètres manquants.'], 400);
            return;
        }

        // Résolution stricte : exact match uniquement (voir Tariff::resolve()).
        // Aucun fallback — si aucun tarif ne correspond au segment exact → saisie manuelle.
        $tariff = Tariff::resolve($lineId, $type, $cat, $class, $date, $destStopId, $originStopId);

        if (!$tariff) {
            $hasStop = ($destStopId !== null || $originStopId !== null);
            $this->json([
                'ok'                => false,
                'manual_mode'       => true,
                'no_segment_tariff' => $hasStop,
                'error'             => $hasStop
                    ? "Aucun tarif configuré pour cet arrêt. Saisissez le prix manuellement."
                    : "Aucun tarif configuré sur cette ligne pour la combinaison sélectionnée. Saisissez le prix manuellement.",
            ]);
            return;
        }

        $services = Tariff::servicesForTariff((int)$tariff['id']);

        $this->json([
            'ok'     => true,
            'tariff' => [
                'id'                   => (int)$tariff['id'],
                'label'                => $tariff['label'] ?: Tariff::summary($tariff),
                'price_fcfa'           => (int)$tariff['price_fcfa'],
                'price_formatted'      => fcfa((int)$tariff['price_fcfa']),
                'baggage_included_qty' => (int)$tariff['baggage_included_qty'],
                'baggage_included_kg'  => (float)$tariff['baggage_included_kg'],
                'valid_from'           => $tariff['valid_from'],
                'valid_until'          => $tariff['valid_until'],
                'services'             => $services,
            ],
        ]);
    }

    // ─── STORE : créer un billet (PRIX VERROUILLÉ SERVEUR) ────────────────────
    public function store(Request $request): void
    {
        if (!Auth::can('billetterie.create')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }

        $register = $this->requireCaisse();

        // 1. Validation entrées
        $tripId       = (int)$request->input('trip_id', 0);
        $type         = trim((string)$request->input('ticket_type', ''));
        $cat          = trim((string)$request->input('passenger_category', 'adulte'));
        $class        = trim((string)$request->input('travel_class', 'standard'));
        $originStopId = $request->input('origin_stop_id')      ? (int)$request->input('origin_stop_id')      : null;
        $destStopId   = $request->input('destination_stop_id') ? (int)$request->input('destination_stop_id') : null;

        if ($tripId <= 0) { $this->flash('danger', 'Voyage invalide.'); back(); return; }

        $trip = Database::selectOne(
            "SELECT id, line_id, trip_date, status FROM trips WHERE id = ?",
            [$tripId]
        );
        if (!$trip) { $this->flash('danger', 'Voyage introuvable.'); back(); return; }
        if (!in_array($trip['status'], ['planifie','valide','embarquement'], true)) {
            $this->flash('danger', "Ce voyage n'accepte plus de ventes.");
            back(); return;
        }

        if (!array_key_exists($type, Tariff::types())) {
            $this->flash('danger', 'Type de billet invalide.'); back(); return;
        }
        if (!array_key_exists($cat, Tariff::passengerCategories())) {
            $this->flash('danger', 'Catégorie passager invalide.'); back(); return;
        }
        if (!array_key_exists($class, Tariff::travelClasses())) {
            $this->flash('danger', 'Classe de voyage invalide.'); back(); return;
        }

        $name = trim((string)$request->input('passenger_name', ''));
        if (mb_strlen($name) < 2) {
            $this->flash('danger', 'Nom du passager requis (2 caractères min).');
            back(); return;
        }

        // 2. RÉSOLUTION TARIF — exact match uniquement, aucun fallback (identique à resolveTariff()).
        $tariff        = Tariff::resolve((int)$trip['line_id'], $type, $cat, $class, $trip['trip_date'], $destStopId, $originStopId);
        $isManualPrice = false;
        $manualReason  = null;

        if (!$tariff) {
            // Niveau 3 : prix manuel — vérifier que le champ est bien rempli
            $manualPrice = (int)$request->input('manual_price_fcfa', 0);
            $manualReason = trim((string)$request->input('manual_price_reason', ''));
            if ($manualPrice <= 0) {
                $this->flash('danger', "Aucun tarif configuré pour cette combinaison. Saisissez un prix manuel et un motif.");
                back(); return;
            }
            if (mb_strlen($manualReason) < 5) {
                $this->flash('danger', "Un motif d'au moins 5 caractères est requis pour justifier le prix hors tarif.");
                back(); return;
            }
            $isManualPrice = true;
            $priceToUse    = $manualPrice;
            $tariffId      = null;
        } else {
            $priceToUse = (int)$tariff['price_fcfa'];
            $tariffId   = (int)$tariff['id'];
        }

        // 2b. Réduction optionnelle (uniquement quand tarif trouvé, pas en mode manuel)
        $discountFcfa   = 0;
        $discountReason = null;
        if (!$isManualPrice) {
            $discountFcfa = max(0, (int)$request->input('discount_fcfa', 0));
            if ($discountFcfa > 0) {
                $discountReason = trim((string)$request->input('discount_reason', ''));
                if (mb_strlen($discountReason) < 5) {
                    $this->flash('danger', 'Motif de réduction requis (5 caractères minimum).');
                    back(); return;
                }
                if ($discountFcfa >= $priceToUse) {
                    $this->flash('danger', 'La réduction ne peut pas être égale ou supérieure au tarif.');
                    back(); return;
                }
                $priceToUse -= $discountFcfa;
            }
        }

        // 3. Construire les données
        $allowSeatChoice = \CityBus\Core\Setting::getBool('billetterie.allow_seat_choice', true);
        $user = Auth::user();

        // Statut de paiement : 'paye' (défaut = encaissement immédiat) ou 'en_attente'
        $paymentStatusInput = trim((string)$request->input('payment_status', 'paye'));
        if (!in_array($paymentStatusInput, ['paye', 'en_attente'], true)) {
            $paymentStatusInput = 'paye';
        }

        $data = [
            'trip_id'              => $tripId,
            'ticket_type'          => $type,
            'passenger_category'   => $cat,
            'travel_class'         => $class,
            'passenger_name'       => $name,
            'passenger_phone'      => trim((string)$request->input('passenger_phone', '')) ?: null,
            'seat_number'          => ($allowSeatChoice && $request->input('seat_number')) ? (int)$request->input('seat_number') : null,
            'boarding_stop_id'     => $request->input('boarding_stop_id')  ? (int)$request->input('boarding_stop_id')  : null,
            'alighting_stop_id'    => $request->input('alighting_stop_id') ? (int)$request->input('alighting_stop_id') : null,
            'price_fcfa'           => $priceToUse,
            'tariff_id'            => $tariffId,
            'is_manual_price'      => $isManualPrice ? 1 : 0,
            'manual_price_reason'  => $manualReason ?: null,
            'discount_fcfa'        => $discountFcfa,
            'discount_reason'      => $discountReason,
            'agency_id'          => $user['agency_id'] ?? 1,
            'sold_by'            => $user['id'],
            'cash_register_id'   => $register['id'] ?? null,
            'payment_method'     => trim((string)$request->input('payment_method', 'especes')),
            'payment_status'     => $paymentStatusInput,
        ];
        if ($request->input('pre_print_id')) {
            $data['pre_print_id'] = (int)$request->input('pre_print_id');
        }

        try {
            $ticket = $this->service->create($data);
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::error('ticket.create failed: ' . $e->getMessage() .
                ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->flash('danger', $e->getMessage());
            back(); return;
        }

        // ── Bagages excédentaires : création liée au billet passager ──────────
        $bagsJson = trim((string)$request->input('bags_json', '[]'));
        $bags     = json_decode($bagsJson, true);
        if (is_array($bags)) {
            foreach ($bags as $bag) {
                if (empty($bag['is_excess']) || empty($bag['baggage_tariff_id'])) {
                    continue;
                }
                $btRow = Database::selectOne(
                    'SELECT baggage_nature_id FROM baggage_tariffs WHERE id = ?',
                    [(int)$bag['baggage_tariff_id']]
                );
                if (!$btRow) { continue; }
                try {
                    $this->baggageService->create([
                        'trip_id'             => $tripId,
                        'line_id'             => (int)$trip['line_id'],
                        'passenger_ticket_id' => (int)$ticket['id'],
                        'passenger_name'      => $data['passenger_name'],
                        'passenger_phone'     => $data['passenger_phone'] ?? null,
                        'baggage_tariff_id'   => (int)$bag['baggage_tariff_id'],
                        'baggage_nature_id'   => (int)$btRow['baggage_nature_id'],
                        'weight_kg'           => max(0.0, (float)($bag['weight_kg'] ?? 0)),
                        'description'         => trim((string)($bag['description'] ?? '')) ?: null,
                        'agency_id'           => $user['agency_id'] ?? 1,
                        'sold_by'             => (int)$user['id'],
                        'cash_register_id'    => $register['id'] ?? null,
                    ]);
                } catch (\Throwable $be) {
                    \CityBus\Core\Logger::warning(
                        'ExcessBag create failed (ticket ' . $ticket['id'] . '): ' . $be->getMessage()
                    );
                }
            }
        }

        // ── Fret items : tous les bagages liés à un voyage sont enregistrés au fret ──
        if (is_array($bags)) {
            foreach ($bags as $bag) {
                $fretSlug = trim((string)($bag['fret_category_slug'] ?? ''));
                if ($fretSlug === '') continue;

                $fretCat = Database::selectOne(
                    "SELECT * FROM fret_categories WHERE slug = ? AND is_active = 1",
                    [$fretSlug]
                );
                if (!$fretCat) continue;

                $isExcess    = !empty($bag['is_excess']);
                $weightKg    = max(0, (int)($bag['weight_kg'] ?? 0));
                $excessKgInt = max(0, (int)($bag['excess_kg_int'] ?? 0));

                // Sécurité : si excédentaire mais excess_kg_int non renseigné, utiliser le poids total
                if ($isExcess && $excessKgInt <= 0) {
                    $excessKgInt = $weightKg;
                }

                // Prix fret : 0 si franchise, sinon price_per_kg × kg excédentaires (entier arrondi)
                $totalFretFcfa = $isExcess
                    ? max((int)$fretCat['price_per_kg'] * $excessKgInt, (int)$fretCat['min_price_fcfa'])
                    : 0;
                $isFranchise   = $isExcess ? 0 : 1;

                $trackingCode = \CityBus\Models\FretItem::generateTrackingCode();

                try {
                    $fretId = Database::insert(
                        "INSERT INTO fret_items (
                            tracking_code, item_type, category_slug, trip_id, passenger_ticket_id,
                            sender_name, sender_phone, recipient_name, recipient_phone,
                            weight_kg, pieces_count, description, is_franchise,
                            price_per_kg, min_price_fcfa, total_price_fcfa,
                            origin_agency_id, destination_agency_id, status,
                            agency_id, registered_by, cash_register_id, created_at, updated_at
                        ) VALUES (
                            ?, 'baggage', ?, ?, ?,
                            ?, ?, '', '',
                            ?, 1, ?, ?,
                            ?, ?, ?,
                            NULL, NULL, 'enregistre',
                            ?, ?, ?, NOW(), NOW()
                        )",
                        [
                            $trackingCode, $fretSlug, $tripId, (int)$ticket['id'],
                            $data['passenger_name'], $data['passenger_phone'] ?? null,
                            $weightKg, trim((string)($bag['description'] ?? '')) ?: null, $isFranchise,
                            (int)$fretCat['price_per_kg'], (int)$fretCat['min_price_fcfa'], $totalFretFcfa,
                            $user['agency_id'] ?? null, (int)$user['id'], $register['id'] ?? null,
                        ]
                    );
                    \CityBus\Models\AuditLog::record('fret.register', 'fret_item', $fretId, [
                        'tracking_code' => $trackingCode,
                        'item_type'     => 'baggage',
                        'linked_ticket' => $ticket['id'],
                        'is_franchise'  => $isFranchise,
                        'total_price'   => $totalFretFcfa,
                    ]);
                } catch (\Throwable $fe) {
                    \CityBus\Core\Logger::warning('FretItem (baggage) create failed: ' . $fe->getMessage());
                }
            }
        }

        if ($request->isAjax()) {
            $this->json([
                'ticket'  => $ticket,
                'pdf_url' => url('billetterie/' . $ticket['id'] . '/pdf'),
            ], 201);
            return;
        }

        $this->flash('success', "Billet {$ticket['ticket_number']} émis.");
        AuditLog::record('ticket.sell', 'ticket', (int)$ticket['id'], [
            'ticket_number' => $ticket['ticket_number'],
            'price_fcfa'    => $ticket['price_fcfa'],
            'trip_id'       => $tripId,
            'passenger'     => $data['passenger_name'],
        ]);
        // Impression auto à la vente (paramétrable)
        if (\CityBus\Core\Setting::getBool('billetterie.print_on_sale', true)) {
            redirect('billetterie/' . $ticket['id'] . '/print');
        } else {
            redirect('billetterie/' . $ticket['id']);
        }
    }

    // ─── SHOW : détail d'un billet ────────────────────────────────────────────
    public function show(Request $request, string $id): void
    {
        $ticket = Database::selectOne(
            "SELECT t.*, tr.trip_date, tr.departure_scheduled,
                    l.name AS line_name, l.code AS line_code,
                    b.code AS bus_code,
                    u.first_name AS sold_by_first, u.last_name AS sold_by_last,
                    bs.name AS boarding_stop_name,
                    als.name AS alighting_stop_name
               FROM tickets t
               JOIN trips tr      ON tr.id = t.trip_id
               JOIN bus_lines l   ON l.id  = tr.line_id
               JOIN buses b       ON b.id  = tr.bus_id
               LEFT JOIN users u  ON u.id  = t.sold_by
               LEFT JOIN stops bs ON bs.id = t.boarding_stop_id
               LEFT JOIN stops als ON als.id = t.alighting_stop_id
              WHERE t.id = ?",
            [$id]
        );
        if (!$ticket) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }

        $luggage = Database::select("SELECT * FROM luggage_tags WHERE ticket_id = ?", [$id]);

        $baggageTickets = Database::select(
            "SELECT bt.id, bt.ticket_number, bt.weight_kg, bt.total_price_fcfa, bt.status,
                    bn.label AS nature_label
               FROM baggage_tickets bt
               LEFT JOIN tariff_baggage_natures bn ON bn.id = bt.baggage_nature_id
              WHERE bt.passenger_ticket_id = ?",
            [$id]
        );

        $this->view('billetterie/show', [
            'title'          => $ticket['ticket_number'],
            'ticket'         => $ticket,
            'luggage'        => $luggage,
            'baggageTickets' => $baggageTickets,
        ]);
    }

    // ─── PDF / PRINT ──────────────────────────────────────────────────────────
    public function pdf(Request $request, string $id): void
    {
        $ticket = Database::selectOne("SELECT * FROM tickets WHERE id = ?", [$id]);
        if (!$ticket) {
            http_response_code(404);
            echo 'Ticket introuvable';
            exit;
        }
        $rawPath = $ticket['pdf_path'] ?? null;
        $path = null;
        if (!empty($rawPath)) {
            $path = str_starts_with((string)$rawPath, BASE_PATH)
                ? (string)$rawPath
                : BASE_PATH . '/storage/' . $rawPath;
        }

        $refresh = in_array(strtolower((string)$request->input('refresh', '0')), ['1', 'true', 'yes'], true);
        $templatePath = BASE_PATH . '/views/billetterie/pdf/ticket.php';
        $templateMtime = is_file($templatePath) ? (int)filemtime($templatePath) : 0;
        $pdfMtime = ($path && is_file($path)) ? (int)filemtime($path) : 0;

        // Régénérer aussi si la config visuelle (ticket_type_configs) a été mise à jour
        $cfgMtimeRow = Database::selectOne(
            "SELECT UNIX_TIMESTAMP(MAX(updated_at)) AS m FROM ticket_type_configs"
        );
        $cfgMtime = (int)($cfgMtimeRow['m'] ?? 0);

        if ($refresh || !$path || !is_file($path) || $pdfMtime < $templateMtime || $pdfMtime < $cfgMtime) {
            $path = $this->service->reprint((int)$id);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $ticket['ticket_number'] . '.pdf"');
        readfile($path);
        exit;
    }

    public function printView(Request $request, string $id): void
    {
        $ticket = Database::selectOne("SELECT * FROM tickets WHERE id = ?", [$id]);
        if (!$ticket) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }
        $this->view('billetterie/print-frame', [
            'title'  => 'Impression ' . $ticket['ticket_number'],
            'ticket' => $ticket,
        ]);
    }

    // ─── CANCEL / REPRINT ─────────────────────────────────────────────────────
    public function cancel(Request $request, string $id): void
    {
        if (!Auth::can('billetterie.cancel')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 5) {
            $this->flash('danger', 'Motif requis (5 caractères minimum).');
            back(); return;
        }

        // Scope agence : un caissier/superviseur d'une agence ne peut annuler que ses billets
        $ticket = Database::selectOne("SELECT agency_id FROM tickets WHERE id=?", [(int)$id]);
        if (!$ticket) {
            $this->flash('danger', 'Billet introuvable.');
            back(); return;
        }
        $user = Auth::user();
        if (Auth::role() !== 'admin'
            && !empty($user['agency_id'])
            && (int)$user['agency_id'] !== (int)$ticket['agency_id']) {
            $this->flash('danger', "Billet d'une autre agence.");
            back(); return;
        }

        try {
            $this->service->cancel((int)$id, $reason, (int)Auth::id());
            $this->flash('success', 'Billet annulé.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    // ─── PAY : encaisser un billet en attente ───────────────────────────────
    public function pay(Request $request, string $id): void
    {
        if (!Auth::can('billetterie.create')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }

        $register = $this->caisse->currentForUser((int)Auth::id());
        $paymentMethod = trim((string)$request->input('payment_method', 'especes'));

        try {
            $this->service->pay(
                (int)$id,
                (int)Auth::id(),
                $register['id'] ?? null,
                $paymentMethod
            );
            $this->flash('success', 'Paiement enregistré.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    // ─── REFUND : rembourser un billet ────────────────────────────────────────
    public function refund(Request $request, string $id): void
    {
        if (!Auth::can('billetterie.cancel')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }

        $amount = (int)$request->input('refund_amount', 0);
        $reason = trim((string)$request->input('reason', ''));

        if ($amount <= 0) {
            $this->flash('danger', 'Montant de remboursement invalide.');
            back(); return;
        }
        if (mb_strlen($reason) < 5) {
            $this->flash('danger', 'Motif requis (5 caractères minimum).');
            back(); return;
        }

        $register = $this->caisse->currentForUser((int)Auth::id());

        try {
            $this->service->refund(
                (int)$id,
                $amount,
                $reason,
                (int)Auth::id(),
                $register['id'] ?? null
            );
            $this->flash('success', 'Remboursement effectué.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    // ─── UPDATE STATUS : transition opérationnelle d'un billet ──────────────
    public function updateStatus(Request $request, string $id): void
    {
        if (!Auth::can('billetterie.create')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }

        $newStatus = trim((string)$request->input('status', ''));
        $ticket    = Database::selectOne("SELECT id, status FROM tickets WHERE id = ?", [(int)$id]);
        if (!$ticket) {
            $this->flash('danger', 'Billet introuvable.');
            back(); return;
        }

        try {
            \CityBus\StateMachines\TicketStateMachine::assertTransition($ticket['status'], $newStatus);

            Database::execute(
                "UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?",
                [$newStatus, (int)$id]
            );

            \CityBus\Models\AuditLog::record('ticket.status_change', 'ticket', (int)$id, [
                'from' => $ticket['status'],
                'to'   => $newStatus,
            ]);

            $label = \CityBus\StateMachines\TicketStateMachine::STATUS_LABELS[$newStatus] ?? $newStatus;
            $this->flash('success', "Statut mis à jour : {$label}.");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function reprint(Request $request, string $id): void
    {
        if (!Auth::can('billetterie.reprint')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }
        $path   = $this->service->reprint((int)$id);
        $ticket = Database::selectOne("SELECT ticket_number FROM tickets WHERE id = ?", [$id]);
        Response::download($path, $ticket['ticket_number'] . '.pdf', 'application/pdf');
    }
}
