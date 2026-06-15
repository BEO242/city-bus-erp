<?php

declare(strict_types=1);

namespace CityBus\Controllers\Operations;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\FretItem;
use CityBus\Services\CashRegisterService;
use CityBus\Services\FretService;

final class FretController extends Controller
{
    public function __construct(
        private FretService $service = new FretService(),
        private CashRegisterService $cashRegisterService = new CashRegisterService(),
    ) {}

    // ─── INDEX : liste filtrée ──────────────────────────────────────────────
    public function index(Request $request): void
    {
        if (!Auth::can('fret.view')) {
            $this->flash('danger', 'Permission refusée.');
            redirect('dashboard');
            return;
        }

        $filters = [
            'q'             => trim((string)$request->input('q', '')),
            'item_type'     => $request->input('item_type'),
            'status'        => $request->input('status'),
            'category_slug' => $request->input('category_slug'),
            'trip_id'       => $request->input('trip_id'),
            'date_from'     => $request->input('date_from'),
            'date_to'       => $request->input('date_to'),
        ];

        $page = max(1, (int)$request->input('page', 1));

        $items = FretItem::listPaginated($filters, $page, 25);

        $categories = Database::select(
            "SELECT slug, label FROM fret_categories WHERE is_active = 1 ORDER BY sort_order"
        );

        $statusCounts = FretItem::countByStatus(
            $filters['trip_id'] ? (int)$filters['trip_id'] : null
        );

        $this->view('operations/fret/index', [
            'title'        => 'Fret & Bagages',
            'rows'         => $items['rows'],
            'total'        => $items['total'],
            'page'         => $items['page'],
            'lastPage'     => $items['lastPage'],
            'filters'      => $filters,
            'categories'   => $categories,
            'statusCounts' => $statusCounts,
        ]);
    }

    // ─── CREATE : formulaire ────────────────────────────────────────────────
    public function create(Request $request): void
    {
        if (!Auth::can('fret.create')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        $mode = in_array($request->input('mode'), ['baggage', 'colis'], true)
              ? $request->input('mode') : 'colis';

        $categories = Database::select(
            "SELECT id, slug, label, price_per_kg, min_price_fcfa, color
               FROM fret_categories WHERE is_active = 1 ORDER BY sort_order"
        );

        $agencies = Database::select(
            "SELECT a.id, a.name, c.name AS city_name
               FROM agencies a
               LEFT JOIN cities c ON c.id = a.city_id
              WHERE a.is_active = 1 ORDER BY a.name"
        );

        $trip       = null;
        $passengers = [];
        $tripId     = (int)$request->input('trip_id', 0);
        $tripStops  = [];

        $fretLoad = null;
        if ($tripId > 0) {
            $trip = Database::selectOne(
                "SELECT tr.*, l.name AS line_name, l.code AS line_code,
                        l.id AS line_id,
                        b.code AS bus_code, b.cargo_capacity_kg,
                        cd.name AS departure_city_name, ca.name AS arrival_city_name
                   FROM trips tr
                   JOIN bus_lines l ON l.id = tr.line_id
                   JOIN buses b     ON b.id = tr.bus_id
                   LEFT JOIN cities cd ON cd.id = l.departure_city_id
                   LEFT JOIN cities ca ON ca.id = l.arrival_city_id
                  WHERE tr.id = ?",
                [$tripId]
            );
            if ($trip) {
                $passengers = Database::select(
                    "SELECT id, ticket_number, passenger_name, seat_number
                       FROM tickets
                      WHERE trip_id = ? AND status != 'annule' AND deleted_at IS NULL
                      ORDER BY seat_number",
                    [$tripId]
                );
                $fretLoad = Database::selectOne(
                    "SELECT COALESCE(SUM(weight_kg), 0) AS total_weight_kg,
                            COUNT(*) AS items_count
                       FROM fret_items
                      WHERE trip_id = ? AND status != 'annule' AND deleted_at IS NULL",
                    [$tripId]
                );
                // Arrêts de la ligne de ce voyage (ordonnés)
                $tripStops = Database::select(
                    "SELECT s.id, s.name, s.order_position, s.km_from_origin
                       FROM stops s
                      WHERE s.line_id = ?
                      ORDER BY s.order_position ASC",
                    [(int)$trip['line_id']]
                );
            }
        }

        // Pour colis : si pas de voyage sélectionné, charger les voyages disponibles
        $availableTrips = [];
        if ($mode === 'colis' && !$trip) {
            $availableTrips = Database::select(
                "SELECT tr.id, tr.trip_date, tr.departure_scheduled, tr.arrival_scheduled,
                        tr.status, tr.trip_code,
                        l.name AS line_name, l.code AS line_code,
                        b.code AS bus_code,
                        cd.name AS departure_city_name, ca.name AS arrival_city_name
                   FROM trips tr
                   JOIN bus_lines l ON l.id = tr.line_id
                   JOIN buses b     ON b.id = tr.bus_id
                   LEFT JOIN cities cd ON cd.id = l.departure_city_id
                   LEFT JOIN cities ca ON ca.id = l.arrival_city_id
                  WHERE tr.status IN ('planifie','valide','embarquement')
                    AND tr.trip_date >= CURDATE()
                  ORDER BY tr.trip_date ASC, tr.departure_scheduled ASC
                  LIMIT 50"
            );
        }

        // Stops groupés par ligne (fallback pour baggage mode sans trip)
        $stops = Database::select(
            "SELECT s.id, s.name, s.line_id, l.code AS line_code, l.name AS line_name,
                    s.order_position, s.km_from_origin
               FROM stops s
               JOIN bus_lines l ON l.id = s.line_id AND l.is_active = 1
              ORDER BY l.code, s.order_position"
        );

        $this->view('operations/fret/create', [
            'title'          => $mode === 'baggage' ? 'Enregistrer un bagage' : 'Enregistrement des colis',
            'mode'           => $mode,
            'categories'     => $categories,
            'agencies'       => $agencies,
            'stops'          => $stops,
            'tripStops'      => $tripStops,
            'trip'           => $trip,
            'passengers'     => $passengers,
            'tripId'         => $tripId,
            'fretLoad'       => $fretLoad,
            'availableTrips' => $availableTrips,
        ]);
    }

    // ─── STORE : enregistrement (simple ou batch multi-colis) ─────────────────
    public function store(Request $request): void
    {
        if (!Auth::can('fret.create')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        try {
            $itemType = $request->input('item_type');
            if (!in_array($itemType, ['baggage', 'colis'], true)) {
                throw new \InvalidArgumentException("Type invalide.");
            }

            $senderName = trim((string)$request->input('sender_name', ''));
            if (mb_strlen($senderName) < 2) {
                throw new \InvalidArgumentException("Le nom de l'expéditeur est requis (min. 2 caractères).");
            }

            $recipientName  = trim((string)$request->input('recipient_name', ''));
            $recipientPhone = trim((string)$request->input('recipient_phone', ''));
            if ($itemType === 'colis') {
                if (mb_strlen($recipientName) < 2) {
                    throw new \InvalidArgumentException("Le nom du destinataire est requis (min. 2 caractères).");
                }
                if ($recipientPhone === '') {
                    throw new \InvalidArgumentException("Le téléphone du destinataire est requis.");
                }
            }

            $tripId = $request->input('trip_id') ? (int)$request->input('trip_id') : null;

            // Pour les colis, le voyage est obligatoire
            if ($itemType === 'colis' && !$tripId) {
                throw new \InvalidArgumentException("Le choix d'un voyage est obligatoire pour l'enregistrement d'un colis.");
            }

            $user     = Auth::user();
            $register = $this->cashRegisterService->currentForUser((int)Auth::id());

            // Données communes
            $commonData = [
                'item_type'             => $itemType,
                'trip_id'               => $tripId,
                'passenger_ticket_id'   => $request->input('passenger_ticket_id') ? (int)$request->input('passenger_ticket_id') : null,
                'sender_name'           => $senderName,
                'sender_phone'          => trim((string)$request->input('sender_phone', '')),
                'recipient_name'        => $recipientName,
                'recipient_phone'       => $recipientPhone,
                'origin_agency_id'      => $request->input('origin_agency_id') ? (int)$request->input('origin_agency_id') : null,
                'destination_agency_id' => $request->input('destination_agency_id') ? (int)$request->input('destination_agency_id') : null,
                'origin_stop_id'        => $request->input('origin_stop_id') ? (int)$request->input('origin_stop_id') : null,
                'destination_stop_id'   => $request->input('destination_stop_id') ? (int)$request->input('destination_stop_id') : null,
                'agency_id'             => $user['agency_id'] ?? null,
                'registered_by'         => (int)Auth::id(),
                'cash_register_id'      => $register['id'] ?? null,
            ];

            // ── Multi-colis : items_json contient un tableau de colis ──
            $itemsJson = trim((string)$request->input('items_json', ''));
            $items     = $itemsJson !== '' ? json_decode($itemsJson, true) : null;

            if (is_array($items) && count($items) > 0) {
                // Mode batch multi-colis
                $createdIds    = [];
                $trackingCodes = [];
                foreach ($items as $idx => $colisItem) {
                    $catSlug = trim((string)($colisItem['category_slug'] ?? ''));
                    $wt      = (float)($colisItem['weight_kg'] ?? 0);
                    $pcs     = max(1, (int)($colisItem['pieces_count'] ?? 1));
                    $desc    = trim((string)($colisItem['description'] ?? ''));

                    if ($catSlug === '') {
                        throw new \InvalidArgumentException("Colis #" . ($idx + 1) . " : catégorie requise.");
                    }
                    if ($wt <= 0) {
                        throw new \InvalidArgumentException("Colis #" . ($idx + 1) . " : le poids doit être > 0 kg.");
                    }

                    $data = array_merge($commonData, [
                        'category_slug' => $catSlug,
                        'weight_kg'     => $wt,
                        'pieces_count'  => $pcs,
                        'description'   => $desc,
                        'is_franchise'  => false,
                    ]);

                    $item = $this->service->register($data);
                    $createdIds[]    = $item['id'];
                    $trackingCodes[] = $item['tracking_code'];
                }

                $n = count($createdIds);
                $this->flash('success', "{$n} colis enregistré(s) — codes : " . implode(', ', $trackingCodes));
                // Redirect to the first one (or list)
                if ($n === 1) {
                    redirect('operations/fret/' . $createdIds[0]);
                } else {
                    redirect('operations/fret?trip_id=' . ($tripId ?? ''));
                }
            } else {
                // Mode simple (un seul item — baggage ou colis unique)
                $categorySlug = trim((string)$request->input('category_slug', ''));
                if ($categorySlug === '') {
                    throw new \InvalidArgumentException("La catégorie fret est requise.");
                }

                $isFranchise = (bool)$request->input('is_franchise', false);
                $weightKg    = (float)$request->input('weight_kg', 0);
                if (!$isFranchise && $weightKg <= 0) {
                    throw new \InvalidArgumentException("Le poids doit être supérieur à 0 kg.");
                }

                $data = array_merge($commonData, [
                    'category_slug' => $categorySlug,
                    'weight_kg'     => $weightKg,
                    'pieces_count'  => max(1, (int)$request->input('pieces_count', 1)),
                    'description'   => trim((string)$request->input('description', '')),
                    'is_franchise'  => $isFranchise,
                ]);

                $item = $this->service->register($data);

                $this->flash('success', "Fret enregistré — code de suivi : {$item['tracking_code']}");
                redirect('operations/fret/' . $item['id']);
            }
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            back();
        }
    }

    // ─── SHOW : détail ──────────────────────────────────────────────────────
    public function show(Request $request, string $id): void
    {
        if (!Auth::can('fret.view')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        $item = FretItem::findWithRelations((int)$id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }

        $this->view('operations/fret/show', [
            'title' => $item['tracking_code'],
            'item'  => $item,
        ]);
    }

    // ─── TALON : impression du talon ────────────────────────────────────────
    public function talon(Request $request, string $id): void
    {
        if (!Auth::can('fret.print_talon')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        $item = FretItem::findWithRelations((int)$id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }

        $this->service->markTalonPrinted((int)$id);

        $this->view('operations/fret/talon', [
            'item' => $item,
        ]);
    }

    // ─── UPDATE STATUS ──────────────────────────────────────────────────────
    public function updateStatus(Request $request, string $id): void
    {
        if (!Auth::can('fret.edit')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        try {
            $status = trim((string)$request->input('status', ''));
            $this->service->updateStatus((int)$id, $status, (int)Auth::id());
            $this->flash('success', 'Statut mis à jour.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    // ─── CANCEL ─────────────────────────────────────────────────────────────
    public function cancel(Request $request, string $id): void
    {
        if (!Auth::can('fret.cancel')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        try {
            $reason = trim((string)$request->input('reason', ''));
            if (mb_strlen($reason) < 5) {
                throw new \InvalidArgumentException("Motif requis (5 caractères minimum).");
            }
            $this->service->cancel((int)$id, $reason, (int)Auth::id());
            $this->flash('success', 'Fret annulé.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    // ─── PAY : encaisser un fret en attente ────────────────────────────────
    public function pay(Request $request, string $id): void
    {
        if (!Auth::can('fret.edit')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        try {
            $register = $this->cashRegisterService->currentForUser((int)Auth::id());
            $paymentMethod = trim((string)$request->input('payment_method', 'especes'));

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

    // ─── REFUND : rembourser un fret ─────────────────────────────────────────
    public function refund(Request $request, string $id): void
    {
        if (!Auth::can('fret.cancel')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        try {
            $amount = (int)$request->input('refund_amount', 0);
            $reason = trim((string)$request->input('reason', ''));

            if ($amount <= 0) {
                throw new \InvalidArgumentException("Montant de remboursement invalide.");
            }
            if (mb_strlen($reason) < 5) {
                throw new \InvalidArgumentException("Motif requis (5 caractères minimum).");
            }

            $register = $this->cashRegisterService->currentForUser((int)Auth::id());

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

    // ─── CALC PRICE (AJAX) ──────────────────────────────────────────────────
    public function calcPrice(Request $request): void
    {
        $categorySlug = trim((string)$request->input('category_slug', ''));
        $weightKg     = (float)$request->input('weight_kg', 0);

        try {
            $result = $this->service->calculatePrice($categorySlug, $weightKg);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
