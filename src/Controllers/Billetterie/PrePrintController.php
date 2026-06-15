<?php

declare(strict_types=1);

namespace CityBus\Controllers\Billetterie;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Services\PrePrintService;

final class PrePrintController extends Controller
{
    public function __construct(private PrePrintService $service = new PrePrintService()) {}

    public function index(Request $request): void
    {
        $status       = $request->input('status', '');
        $preprintType = $request->input('preprint_type', '');
        $where  = 'pp.deleted_at IS NULL';
        $params = [];

        $user = Auth::user();
        if (Auth::role() !== 'admin' && $user['agency_id']) {
            $where .= ' AND pp.agency_id=?';
            $params[] = $user['agency_id'];
        }
        if ($status) { $where .= ' AND pp.status=?'; $params[] = $status; }
        if ($preprintType) { $where .= ' AND pp.preprint_type=?'; $params[] = $preprintType; }

        $tickets = Database::select(
            "SELECT pp.*, a.name AS agency_name,
                    u.first_name AS creator_first, u.last_name AS creator_last,
                    tr.trip_code, tr.trip_date, l.name AS line_name,
                    cd.slug AS departure_city, cd.name AS departure_city_name,
                    ca.slug AS arrival_city,   ca.name AS arrival_city_name
             FROM pre_printed_tickets pp
             JOIN agencies a ON a.id = pp.agency_id
             JOIN users u ON u.id = pp.pre_printed_by
             LEFT JOIN tickets tk ON tk.id = pp.activated_ticket_id
             LEFT JOIN trips tr ON tr.id = COALESCE(pp.trip_id, tk.trip_id)
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             LEFT JOIN cities cd ON cd.id = l.departure_city_id
             LEFT JOIN cities ca ON ca.id = l.arrival_city_id
             WHERE $where
             ORDER BY pp.created_at DESC LIMIT 200",
            $params
        );

        $stats = Database::selectOne(
            "SELECT
                SUM(status='disponible') AS dispo,
                SUM(status='active')      AS actives,
                SUM(status='annule')      AS annules,
                SUM(preprint_type='billet')       AS billets,
                SUM(preprint_type='talon_bagage') AS talons_bagage,
                SUM(preprint_type='talon_colis')  AS talons_colis
             FROM pre_printed_tickets WHERE deleted_at IS NULL"
        );

        $this->view('billetterie/preprint/index', [
            'title'         => 'Tickets pré-imprimés',
            'tickets'       => $tickets,
            'stats'         => $stats,
            'status'        => $status,
            'preprintType'  => $preprintType,
            'preprintTypes' => PrePrintService::PREPRINT_TYPES,
        ]);
    }

    public function create(Request $request): void
    {
        $trips = Database::select(
            "SELECT tr.id, tr.trip_code, tr.trip_date, tr.departure_scheduled,
                    l.name AS line_name,
                    cd.slug AS departure_city, cd.name AS departure_city_name,
                    ca.slug AS arrival_city,   ca.name AS arrival_city_name,
                    b.seats, b.code AS bus_code, b.plate
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             JOIN buses b ON b.id = tr.bus_id
             WHERE tr.status IN ('planifie','embarquement')
               AND tr.trip_date >= CURDATE()
             ORDER BY tr.trip_date, tr.departure_scheduled
             LIMIT 200"
        );

        $user = Auth::user();
        $agencies = [];
        if (!$user['agency_id']) {
            // Admin sans agence : laisser choisir
            $agencies = Database::select("SELECT id, name FROM agencies WHERE is_active=1 ORDER BY name");
        }

        // Catégories fret pour les talons bagage/colis
        $fretCategories = Database::select(
            "SELECT id, slug, label AS name, price_per_kg FROM fret_categories WHERE is_active = 1 ORDER BY sort_order ASC, label ASC"
        );

        $this->view('billetterie/preprint/create', [
            'title'          => 'Générer un lot',
            'trips'          => $trips,
            'agencies'       => $agencies,
            'userAgencyId'   => (int)($user['agency_id'] ?? 0),
            'typeConfigs'    => $this->service->loadTypeConfigs(),
            'preprintTypes'  => PrePrintService::PREPRINT_TYPES,
            'fretCategories' => $fretCategories,
        ]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('billetterie.preprint')) { $this->flash('danger', 'Permission refusée.'); back(); }

        $preprintType = trim((string)$request->input('preprint_type', 'billet'));
        $isBillet     = ($preprintType === 'billet');

        $rules = ['size' => 'required|integer|min:1|max:500'];
        if ($isBillet) {
            $rules['trip_id']     = 'required|integer';
            $rules['ticket_type'] = 'required';
        }
        $data = $this->validate($request, $rules);

        $notes       = trim((string)$request->input('notes', '')) ?: null;
        $ticketColor = trim((string)$request->input('ticket_color', '')) ?: null;
        if ($ticketColor && !preg_match('/^#[0-9A-Fa-f]{6}$/', $ticketColor)) {
            $ticketColor = null;
        }

        $tripId     = $isBillet ? (int)$data['trip_id'] : ((int)$request->input('trip_id', 0) ?: null);
        $ticketType = $isBillet ? $data['ticket_type'] : 'passage_final';

        // Résoudre l'agence
        $user = Auth::user();
        $agencyId = (int)($user['agency_id'] ?? $request->input('agency_id', 0));
        if (!$agencyId) { $this->flash('danger', 'Agence requise.'); back(); }

        try {
            $batch = $this->service->generateBatch(
                (int)$data['size'], $agencyId, (int)Auth::id(), $notes,
                $tripId, $ticketType, $ticketColor, $preprintType
            );
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage()); back();
        }

        $typeLabel = PrePrintService::PREPRINT_TYPES[$preprintType] ?? 'supports';
        $this->flash('success', "Lot de {$batch['size']} {$typeLabel} pré-imprimé(s) généré.");
        redirect('billetterie/preprint/batch/' . $batch['batch_id']);
    }

    public function showBatch(Request $request, string $batchId): void
    {
        $items = Database::select(
            "SELECT pp.*, a.name AS agency_name,
                    tr.trip_code, tr.trip_date, tr.departure_scheduled,
                    l.name AS line_name,
                    cd.slug AS departure_city, cd.name AS departure_city_name,
                    ca.slug AS arrival_city,   ca.name AS arrival_city_name
             FROM pre_printed_tickets pp
             JOIN agencies a ON a.id = pp.agency_id
             LEFT JOIN trips tr ON tr.id = pp.trip_id
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             LEFT JOIN cities cd ON cd.id = l.departure_city_id
             LEFT JOIN cities ca ON ca.id = l.arrival_city_id
             WHERE pp.batch_id=? ORDER BY pp.seat_number, pp.id",
            [$batchId]
        );
        if (!$items) { http_response_code(404); $this->view('errors/404'); return; }

        // Infos du voyage (premières lignes communes)
        $trip = null;
        if (!empty($items[0]['trip_code'])) {
            $trip = [
                'trip_code'         => $items[0]['trip_code'],
                'trip_date'         => $items[0]['trip_date'],
                'departure_scheduled' => $items[0]['departure_scheduled'],
                'line_name'         => $items[0]['line_name'],
                'departure_city'    => $items[0]['departure_city'],
                'arrival_city'      => $items[0]['arrival_city'],
            ];
        }

        $this->view('billetterie/preprint/batch', [
            'title'    => 'Lot de tickets',
            'batch_id' => $batchId,
            'tickets'  => $items,
            'trip'     => $trip,
        ]);
    }

    public function downloadBatch(Request $request, string $batchId): void
    {
        $row = Database::selectOne(
            "SELECT pdf_path FROM pre_printed_tickets WHERE batch_id=? AND pdf_path IS NOT NULL LIMIT 1",
            [$batchId]
        );
        if (!$row) { http_response_code(404); echo 'PDF introuvable'; exit; }
        Response::download(BASE_PATH . '/storage/' . $row['pdf_path'], "batch-$batchId.pdf", 'application/pdf');
    }

    public function cancel(Request $request, string $id): void
    {
        if (!Auth::can('billetterie.preprint')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 5) {
            $this->flash('danger', 'Motif requis.');
            back(); return;
        }

        // Scope agence : un caissier ne peut annuler qu'un pré-imprimé de son agence
        $pp = Database::selectOne("SELECT agency_id FROM pre_printed_tickets WHERE id=?", [(int)$id]);
        if (!$pp) { $this->flash('danger', 'Pré-imprimé introuvable.'); back(); return; }
        $user = Auth::user();
        if (Auth::role() !== 'admin'
            && !empty($user['agency_id'])
            && (int)$user['agency_id'] !== (int)$pp['agency_id']) {
            $this->flash('danger', "Pré-imprimé d'une autre agence.");
            back(); return;
        }

        try {
            $this->service->cancel((int)$id, $reason, (int)Auth::id());
            $this->flash('success', 'Support annulé.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    /** AJAX: scanner un QR/numéro et retourner le pré-imprimé. */
    public function lookup(Request $request): void
    {
        if (!Auth::can('billetterie.create') && !Auth::can('billetterie.preprint')) {
            $this->json(['error' => 'Permission refusée.'], 403); return;
        }
        $value = trim((string)$request->input('q', ''));
        if (!$value) { $this->json(['error' => 'Vide'], 400); return; }
        $found = $this->service->findByQrOrNumber($value);
        if (!$found) { $this->json(['error' => 'Introuvable'], 404); return; }

        // Scope agence : ne pas exposer un pré-imprimé d'une autre agence
        $user = Auth::user();
        if (Auth::role() !== 'admin'
            && !empty($user['agency_id'])
            && (int)$user['agency_id'] !== (int)($found['agency_id'] ?? 0)) {
            $this->json(['error' => 'Pré-imprimé hors périmètre.'], 403); return;
        }
        if ($found['status'] !== 'disponible') {
            $this->json(['error' => 'Statut: ' . $found['status']], 409); return;
        }
        $this->json(['ok' => true, 'preprint' => $found]);
    }

    /** Page de configuration des types de tickets. */
    public function config(Request $request): void
    {
        if (!Auth::can('admin')) { $this->flash('danger', 'Accès réservé à l\'administrateur.'); back(); }
        $this->view('billetterie/preprint/config', [
            'title'       => 'Configuration des types de tickets',
            'typeConfigs' => $this->service->loadTypeConfigs(),
        ]);
    }

    /** Enregistre la configuration des types et variantes de rendu. */
    public function updateConfig(Request $request): void
    {
        if (!Auth::can('admin')) { $this->flash('danger', 'Accès refusé.'); back(); }

        $keys = ['passage_arret', 'passage_final', 'bagage_excedent', 'bagage_inclus', 'talon_arret'];
        $defaultHeights = [
            'passage_arret'   => 62,
            'passage_final'   => 62,
            'bagage_excedent' => 62,
            'bagage_inclus'   => 62,
            'talon_arret'     => 80,
        ];
        foreach ($keys as $key) {
            $color      = trim((string)$request->input("color_{$key}", ''));
            $textColor  = trim((string)$request->input("text_color_{$key}", ''));
            $label      = trim((string)$request->input("label_{$key}", ''));
            $desc       = trim((string)$request->input("description_{$key}", '')) ?: null;
            $prefix     = strtoupper(trim((string)$request->input("number_prefix_{$key}", '')));
            $variant    = strtoupper(trim((string)$request->input("layout_variant_{$key}", 'A')));
            $rowHeight  = (int)$request->input("row_height_mm_{$key}", $defaultHeights[$key] ?? 62);
            $showQr     = $request->input("show_qr_{$key}") ? 1 : 0;
            $showContact= $request->input("show_company_contact_{$key}") ? 1 : 0;
            $showPhone  = $request->input("show_company_phone_{$key}") ? 1 : 0;
            $showTrip   = $request->input("show_trip_info_{$key}") ? 1 : 0;
            $showSeat   = $request->input("show_seat_info_{$key}") ? 1 : 0;
            $showPrice  = $request->input("show_price_field_{$key}") ? 1 : 0;
            $showAgency = $request->input("show_agency_stub_{$key}") ? 1 : 0;
            $showPassengerRef = $request->input("show_passenger_reference_{$key}") ? 1 : 0;

            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color))     $color     = '#C62828';
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $textColor)) $textColor = '#FFFFFF';
            if (!$label) continue;
            if (!in_array($variant, ['A', 'B'], true)) $variant = 'A';
            $minHeight = $key === 'talon_arret' ? 70 : 55;
            $maxHeight = $key === 'talon_arret' ? 95 : 75;
            if ($rowHeight < $minHeight || $rowHeight > $maxHeight) {
                $rowHeight = $defaultHeights[$key] ?? 62;
            }
            // Préfixe : lettres, chiffres et tirets uniquement, 2-12 chars
            if (!$prefix || !preg_match('/^[A-Z0-9\-]{2,12}$/', $prefix)) {
                $defaults = [
                    'passage_arret'   => 'CB-PA',
                    'passage_final'   => 'CB-PF',
                    'bagage_excedent' => 'CB-BE',
                    'bagage_inclus'   => 'CB-BI',
                    'talon_arret'     => 'CB-TA',
                ];
                $prefix = $defaults[$key] ?? 'CB-PP';
            }

            Database::execute(
                "INSERT INTO ticket_type_configs (
                    type_key, label, color, text_color, description, number_prefix,
                    layout_variant, row_height_mm,
                                        show_qr, show_company_contact, show_company_phone,
                                        show_trip_info, show_seat_info, show_price_field,
                                        show_agency_stub, show_passenger_reference
                 )
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE label=VALUES(label), color=VALUES(color),
                   text_color=VALUES(text_color), description=VALUES(description),
                   number_prefix=VALUES(number_prefix),
                   layout_variant=VALUES(layout_variant), row_height_mm=VALUES(row_height_mm),
                                     show_qr=VALUES(show_qr), show_company_contact=VALUES(show_company_contact),
                                     show_company_phone=VALUES(show_company_phone),
                                     show_trip_info=VALUES(show_trip_info), show_seat_info=VALUES(show_seat_info),
                                     show_price_field=VALUES(show_price_field),
                                     show_agency_stub=VALUES(show_agency_stub),
                                     show_passenger_reference=VALUES(show_passenger_reference)",
                [
                    $key, $label, $color, $textColor, $desc, $prefix,
                    $variant, $rowHeight,
                                        $showQr, $showContact, $showPhone,
                                        $showTrip, $showSeat, $showPrice,
                                        $showAgency, $showPassengerRef,
                ]
            );
        }

        $this->flash('success', 'Configuration des types de tickets mise à jour.');
        redirect('billetterie/preprint/config');
    }
}
