<?php

declare(strict_types=1);

namespace CityBus\Controllers\Billetterie;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\BaggageTicket;
use CityBus\Models\BaggageTariff;
use CityBus\Services\BaggageTicketService;
use CityBus\Services\CashRegisterService;
use CityBus\Services\TripService;

final class BaggageTicketController extends Controller
{
    public function __construct(
        private BaggageTicketService $service = new BaggageTicketService(),
        private CashRegisterService  $caisse  = new CashRegisterService(),
        private TripService          $trips   = new TripService(),
    ) {}

    // ─── Helper : bloque l'accès si aucune caisse ouverte ────────────────────
    private function requireCaisse(): ?array
    {
        if (!\CityBus\Core\Setting::getBool('caisse.require_open_session', true)) {
            return null;
        }
        $register = $this->caisse->currentForUser((int)Auth::id());
        if (!$register) {
            $this->flash('warning', 'Vous devez ouvrir une caisse avant d\'accéder à la billetterie bagages.');
            redirect('caisse');
        }
        return $register;
    }

    // ─── index ────────────────────────────────────────────────────────────────
    public function index(Request $request): void
    {
        $page   = max(1, (int)$request->input('page', 1));
        $q      = trim((string)$request->input('q', ''));
        $lineId = (int)$request->input('line_id', 0);
        $status = trim((string)$request->input('status', ''));
        $soldBy = Auth::role() === 'caissier' ? (int)Auth::id() : 0;

        $paginated = BaggageTicket::listPaginated(
            $page, 25, $q, 0, $lineId, $status, $soldBy
        );

        $this->view('billetterie-bagages/index', [
            'title'    => 'Billets bagages',
            'tickets'  => $paginated['rows'],
            'total'    => $paginated['total'],
            'page'     => $paginated['page'],
            'lastPage' => $paginated['lastPage'],
            'q'        => $q,
            'lineId'   => $lineId,
            'status'   => $status,
            'lines'    => Database::select("SELECT id, code, name FROM bus_lines ORDER BY code"),
        ]);
    }

    // ─── selectTrip ───────────────────────────────────────────────────────────
    public function selectTrip(Request $request): void
    {
        $this->requireCaisse();

        $date  = $request->input('date', date('Y-m-d'));
        $trips = Database::select(
            "SELECT tr.*, l.name AS line_name, l.code AS line_code, b.code AS bus_code,
                    (SELECT COUNT(*) FROM baggage_tickets bt
                     WHERE bt.trip_id = tr.id AND bt.status = 'emis' AND bt.deleted_at IS NULL) AS baggage_count
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN buses b ON b.id = tr.bus_id
             WHERE tr.trip_date = ? AND tr.status IN ('planifie','embarquement')
             ORDER BY tr.departure_scheduled",
            [$date]
        );

        $this->view('billetterie-bagages/select-trip', [
            'title' => 'Vendre un billet bagage',
            'trips' => $trips,
            'date'  => $date,
        ]);
    }

    // ─── showSale ─────────────────────────────────────────────────────────────
    public function showSale(Request $request, string $tripId): void
    {
        $this->requireCaisse();

        $trip = Database::selectOne(
            "SELECT tr.*, l.name AS line_name, l.code AS line_code, l.id AS line_id,
                    b.code AS bus_code
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN buses b ON b.id = tr.bus_id
             WHERE tr.id = ?", [$tripId]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        // Tarifs bagages actifs et VALIDES à la date du voyage (fenêtre valid_from/valid_until).
        // Si plusieurs tarifs couvrent la même nature, on garde le plus spécifique (bornes prioritaires).
        $tripDate = $trip['trip_date'];
        $rows = Database::select(
            "SELECT bt.*, bn.id AS baggage_nature_id,
                bn.label AS nature_label, bn.icon AS nature_icon,
                bn.color_class AS nature_color, bn.slug AS nature_slug, bn.sort_order
             FROM baggage_tariffs bt
             JOIN tariff_baggage_natures bn
               ON JSON_CONTAINS(bt.baggage_nature_ids, JSON_ARRAY(CAST(bn.id AS UNSIGNED)))
             WHERE bt.line_id = ?
               AND bt.is_active = 1
               AND (bt.valid_from  IS NULL OR bt.valid_from  <= ?)
               AND (bt.valid_until IS NULL OR bt.valid_until >= ?)
             ORDER BY bn.sort_order,
                      (bt.valid_from  IS NOT NULL) DESC,
                      (bt.valid_until IS NOT NULL) DESC,
                      bt.id DESC",
            [$trip['line_id'], $tripDate, $tripDate]
        );

        // Une nature => un tarif (le plus spécifique grâce au ORDER BY)
        $tariffs = [];
        $seenNatures = [];
        foreach ($rows as $r) {
            $natureId = (int)$r['baggage_nature_id'];
            if (isset($seenNatures[$natureId])) { continue; }
            $seenNatures[$natureId] = true;
            $tariffs[] = $r;
        }

        // Enrichir avec les tranches
        foreach ($tariffs as &$t) {
            $t['formula']  = BaggageTariff::formulaSummary($t);
            $t['brackets'] = (int)$t['bracket_mode'] === 1
                ? BaggageTariff::bracketsFor((int)$t['id'])
                : [];
        }
        unset($t);

        // Passagers dans ce voyage (pour la sélection)
        $passengers = Database::select(
            "SELECT id, ticket_number, passenger_name, passenger_phone, seat_number
             FROM tickets
             WHERE trip_id = ? AND status IN ('emis','valide') AND deleted_at IS NULL
             ORDER BY passenger_name",
            [$tripId]
        );

        $register = $this->caisse->currentForUser((int)Auth::id());

        $this->view('billetterie-bagages/sale', [
            'title'      => 'Billet bagage — ' . $trip['line_name'],
            'trip'       => $trip,
            'tariffs'    => $tariffs,
            'passengers' => $passengers,
            'register'   => $register,
        ]);
    }

    // ─── store ────────────────────────────────────────────────────────────────
    public function store(Request $request): void
    {
        if (!Auth::can('billetterie.create')) {
            $this->flash('danger', 'Permission refusée.'); back(); return;
        }

        $register = $this->requireCaisse();

        $tripId           = (int)$request->input('trip_id');
        $lineId           = (int)$request->input('line_id');
        $passTicketId     = (int)$request->input('passenger_ticket_id', 0);
        $passengerName    = trim((string)$request->input('passenger_name', ''));
        $passengerPhone   = trim((string)$request->input('passenger_phone', ''));
        $baggageTariffId  = (int)$request->input('baggage_tariff_id');
        $baggageNatureId  = (int)$request->input('baggage_nature_id');
        $weightKg         = (float)$request->input('weight_kg', 0);
        $lengthCm         = $request->input('length_cm', '') !== '' ? (int)$request->input('length_cm') : null;
        $widthCm          = $request->input('width_cm', '')  !== '' ? (int)$request->input('width_cm')  : null;
        $heightCm         = $request->input('height_cm', '') !== '' ? (int)$request->input('height_cm') : null;
        $description      = trim((string)$request->input('description', '')) ?: null;

        // Validations
        if ($tripId <= 0) {
            $this->flash('danger', 'Voyage invalide.'); back(); return;
        }
        if ($passengerName === '') {
            $this->flash('danger', 'Le nom du propriétaire est requis.'); back(); return;
        }
        if ($baggageTariffId <= 0) {
            $this->flash('danger', 'Veuillez sélectionner un tarif bagage.'); back(); return;
        }
        if ($weightKg <= 0) {
            $this->flash('danger', 'Le poids doit être supérieur à 0 kg.'); back(); return;
        }

        try {
            $ticket = $this->service->create([
                'trip_id'             => $tripId,
                'line_id'             => $lineId,
                'passenger_ticket_id' => $passTicketId > 0 ? $passTicketId : null,
                'passenger_name'      => $passengerName,
                'passenger_phone'     => $passengerPhone ?: null,
                'baggage_tariff_id'   => $baggageTariffId,
                'baggage_nature_id'   => $baggageNatureId,
                'weight_kg'           => $weightKg,
                'length_cm'           => $lengthCm,
                'width_cm'            => $widthCm,
                'height_cm'           => $heightCm,
                'description'         => $description,
                'agency_id'           => Auth::user()['agency_id'] ?? 1,
                'sold_by'             => Auth::id(),
                'cash_register_id'    => $register['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            back(); return;
        }

        if ($request->isAjax()) {
            $this->json([
                'ticket'  => $ticket,
                'pdf_url' => url('billetterie-bagages/' . $ticket['id'] . '/pdf'),
            ], 201);
            return;
        }

        $this->flash('success', "Billet bagage {$ticket['ticket_number']} émis.");
        redirect('billetterie-bagages/' . $ticket['id'] . '/print');
    }

    // ─── show ─────────────────────────────────────────────────────────────────
    public function show(Request $request, string $id): void
    {
        $ticket = BaggageTicket::findWithRelations((int)$id);
        if (!$ticket) { http_response_code(404); $this->view('errors/404'); return; }

        $this->view('billetterie-bagages/show', [
            'title'  => $ticket['ticket_number'],
            'ticket' => $ticket,
        ]);
    }

    // ─── printView ────────────────────────────────────────────────────────────
    public function printView(Request $request, string $id): void
    {
        $ticket = Database::selectOne("SELECT * FROM baggage_tickets WHERE id = ?", [$id]);
        if (!$ticket) { http_response_code(404); $this->view('errors/404'); return; }

        $this->view('billetterie-bagages/print-frame', [
            'title'  => 'Impression ' . $ticket['ticket_number'],
            'ticket' => $ticket,
        ]);
    }

    // ─── pdf ──────────────────────────────────────────────────────────────────
    public function pdf(Request $request, string $id): void
    {
        $ticket = Database::selectOne("SELECT * FROM baggage_tickets WHERE id = ?", [$id]);
        if (!$ticket) { http_response_code(404); echo 'Billet introuvable'; exit; }

        $rawPath = $ticket['pdf_path'] ?? null;
        $path = null;
        if (!empty($rawPath)) {
            $path = str_starts_with((string)$rawPath, BASE_PATH)
                ? (string)$rawPath
                : BASE_PATH . '/storage/' . $rawPath;
        }

        $refresh = in_array(strtolower((string)$request->input('refresh', '0')), ['1', 'true', 'yes'], true);
        $templatePath = BASE_PATH . '/views/billetterie-bagages/pdf/ticket.php';
        $templateMtime = is_file($templatePath) ? (int)filemtime($templatePath) : 0;
        $pdfMtime = ($path && is_file($path)) ? (int)filemtime($path) : 0;

        if ($refresh || !$path || !is_file($path) || $pdfMtime < $templateMtime) {
            $path = BASE_PATH . '/storage/' . $this->service->reprint((int)$id);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $ticket['ticket_number'] . '.pdf"');
        readfile($path);
        exit;
    }

    // ─── cancel ───────────────────────────────────────────────────────────────
    public function cancel(Request $request, string $id): void
    {
        if (!Auth::can('billetterie.cancel')) {
            $this->flash('danger', 'Permission refusée.'); back(); return;
        }
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 5) {
            $this->flash('danger', 'Motif requis (5 caractères minimum).');
            back(); return;
        }

        // Scope agence : un caissier/superviseur d'une agence ne peut annuler
        // que les billets bagages de sa propre agence.
        $ticket = Database::selectOne("SELECT agency_id FROM baggage_tickets WHERE id=?", [(int)$id]);
        if (!$ticket) {
            $this->flash('danger', 'Billet bagage introuvable.');
            back(); return;
        }
        $user = Auth::user();
        if (Auth::role() !== 'admin'
            && !empty($user['agency_id'])
            && (int)$user['agency_id'] !== (int)$ticket['agency_id']) {
            $this->flash('danger', "Billet bagage d'une autre agence.");
            back(); return;
        }

        try {
            $this->service->cancel((int)$id, $reason, (int)Auth::id());
            $this->flash('success', 'Billet bagage annulé.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    // ─── calcPrice (AJAX) ─────────────────────────────────────────────────────
    /**
     * Calcule le prix en temps réel sans créer de billet.
     * GET /billetterie-bagages/calc?tariff_id=X&weight_kg=Y&length=Z&width=W&height=H
     */
    public function calcPrice(Request $request): void
    {
        $tariffId = (int)$request->input('tariff_id');
        $weightKg = (float)$request->input('weight_kg', 0);
        $length   = $request->input('length', '') !== '' ? (int)$request->input('length') : null;
        $width    = $request->input('width', '')  !== '' ? (int)$request->input('width')  : null;
        $height   = $request->input('height', '') !== '' ? (int)$request->input('height') : null;
        $tripId   = (int)$request->input('trip_id', 0);

        // Date de référence : celle du voyage si fourni, sinon aujourd'hui
        $refDate = date('Y-m-d');
        if ($tripId > 0) {
            $row = Database::selectOne("SELECT trip_date FROM trips WHERE id = ?", [$tripId]);
            if ($row) { $refDate = $row['trip_date']; }
        }

        $tariff = Database::selectOne(
            "SELECT * FROM baggage_tariffs
             WHERE id = ?
               AND is_active = 1
               AND (valid_from  IS NULL OR valid_from  <= ?)
               AND (valid_until IS NULL OR valid_until >= ?)",
            [$tariffId, $refDate, $refDate]
        );
        if (!$tariff) {
            $this->json(['error' => 'Tarif introuvable ou hors période de validité'], 404); return;
        }

        $baseFee     = (int)$tariff['base_fee_fcfa'];
        $weightFee   = BaggageTariff::calculatePrice($tariff, $weightKg) ?? 0;
        $volSurcharge = 0;

        if ($tariff['volume_surcharge_fcfa'] !== null) {
            if (BaggageTariff::isOversize($tariff, $length, $width, $height)) {
                $volSurcharge = (int)$tariff['volume_surcharge_fcfa'];
            }
        }

        $maxWt = $tariff['max_weight_kg'] ? (float)$tariff['max_weight_kg'] : null;
        $overweight = $maxWt !== null && $weightKg > $maxWt;

        $this->json([
            'base_fee'         => $baseFee,
            'weight_fee'       => $weightFee,
            'volume_surcharge' => $volSurcharge,
            'total'            => $baseFee + $weightFee + $volSurcharge,
            'overweight'       => $overweight,
            'oversize'         => $volSurcharge > 0,
            'formula'          => BaggageTariff::formulaSummary($tariff),
        ]);
    }
}
