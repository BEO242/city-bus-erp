<?php

declare(strict_types=1);

namespace CityBus\Controllers\Billetterie;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\CashRegisterService;
use CityBus\Services\ReservationService;

final class ReservationController extends Controller
{
    private ReservationService $svc;
    private CashRegisterService $caisse;

    public function __construct() {
        $this->svc = new ReservationService();
        $this->caisse = new CashRegisterService();
    }

    public function index(Request $request): void
    {
        $q = trim((string)$request->input('q', ''));
        $status = trim((string)$request->input('status', ''));

        $where  = ['1=1'];
        $params = [];
        if ($q) {
            $where[] = '(pnr_code LIKE ? OR contact_name LIKE ? OR contact_phone LIKE ?)';
            $like = "%$q%";
            array_push($params, $like, $like, $like);
        }
        if ($status) { $where[] = 'status = ?'; $params[] = $status; }

        $reservations = Database::select(
            "SELECT r.*, COUNT(ri.id) AS items_count
             FROM reservations r
             LEFT JOIN reservation_items ri ON ri.reservation_id = r.id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY r.id ORDER BY r.created_at DESC LIMIT 100",
            $params
        );

        $this->view('billetterie/reservations/index', [
            'title' => 'Réservations / PNR',
            'reservations' => $reservations,
            'q' => $q, 'status' => $status,
        ]);
    }

    public function show(Request $request, string $pnr): void
    {
        $reservation = $this->svc->loadByPnr($pnr);
        if (!$reservation) {
            $this->flash('danger', 'PNR introuvable.');
            redirect('billetterie/reservations');
        }
        $items = $this->svc->items((int)$reservation['id']);

        $this->view('billetterie/reservations/show', [
            'title'       => "Réservation $pnr",
            'reservation' => $reservation,
            'items'       => $items,
        ]);
    }

    public function lookup(Request $request): void
    {
        $code = strtoupper(trim((string)$request->input('pnr', '')));
        if (!$code) { $this->flash('danger', 'Code requis.'); back(); }
        $r = $this->svc->loadByPnr($code);
        if (!$r) {
            $this->flash('danger', 'PNR introuvable.');
            redirect('billetterie/reservations');
        }
        redirect('billetterie/reservations/' . $code);
    }

    public function create(Request $request): void
    {
        $tripId = (int)$request->input('trip_id', 0);
        $trip = null;
        if ($tripId) {
            $trip = Database::selectOne(
                "SELECT tr.*, l.code AS line_code, l.name AS line_name
                 FROM trips tr LEFT JOIN bus_lines l ON l.id = tr.line_id
                 WHERE tr.id = ?", [$tripId]
            );
        }
        $this->view('billetterie/reservations/form', [
            'title' => 'Nouvelle réservation',
            'trip'  => $trip,
        ]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('reservations.create')) { $this->flash('danger', 'Permission refusée.'); back(); }

        $items = (array)$request->input('items', []);
        $items = array_filter($items, fn($i) => !empty($i['passenger_name']) && !empty($i['trip_id']));
        if (!$items) { $this->flash('danger', 'Au moins un passager requis.'); back(); }

        try {
            $r = $this->svc->hold([
                'contact_name'  => $request->input('contact_name'),
                'contact_phone' => $request->input('contact_phone'),
                'contact_email' => $request->input('contact_email'),
                'channel'       => $request->input('channel', 'counter'),
                'agency_id'     => Auth::user()['agency_id'] ?? null,
                'items'         => array_values($items),
            ], (int)Auth::id());
            $this->flash('success', "Réservation {$r['pnr_code']} créée. Validité jusqu'au " . date('d/m/Y H:i', strtotime((string)$r['hold_expires_at'])));
            redirect('billetterie/reservations/' . $r['pnr_code']);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            back();
        }
    }

    public function confirm(Request $request, string $pnr): void
    {
        if (!Auth::can('reservations.confirm')) { $this->flash('danger', 'Permission refusée.'); back(); }
        try {
            $this->svc->confirm($pnr, (int)Auth::id());
            $this->flash('success', "Réservation $pnr confirmée.");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function convert(Request $request, string $pnr): void
    {
        if (!Auth::can('reservations.confirm')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $register = $this->caisse->currentForUser((int)Auth::id());
        $registerId = $register['id'] ?? null;

        try {
            $tickets = $this->svc->convertToTickets(
                $pnr,
                $request->input('payment_method', 'especes'),
                (int)Auth::id(),
                $registerId
            );
            $this->flash('success', count($tickets) . ' billet(s) émis.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function cancel(Request $request, string $pnr): void
    {
        if (!Auth::can('reservations.cancel')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 5) { $this->flash('danger', 'Motif requis (5 car. min).'); back(); }
        try {
            $this->svc->cancel($pnr, $reason, (int)Auth::id());
            $this->flash('success', "Réservation $pnr annulée.");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }
}
