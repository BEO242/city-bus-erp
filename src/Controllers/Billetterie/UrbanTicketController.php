<?php

declare(strict_types=1);

namespace CityBus\Controllers\Billetterie;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Models\AuditLog;
use CityBus\Services\UrbanTicketService;

final class UrbanTicketController extends Controller
{
    public function __construct(private UrbanTicketService $service = new UrbanTicketService()) {}

    public function index(Request $request): void
    {
        $filters = [
            'status'    => $request->input('status', ''),
            'date_from' => $request->input('date_from', ''),
            'date_to'   => $request->input('date_to', ''),
            'bus_code'  => $request->input('bus_code', ''),
        ];

        $series  = $this->service->allSeries($filters);
        $stats   = $this->service->stats();
        $symbols = $this->service->symbols();

        $this->view('billetterie/urban-tickets/index', [
            'title'   => 'Tickets urbains pré-imprimés',
            'series'  => $series,
            'stats'   => $stats,
            'symbols' => $symbols,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): void
    {
        $symbols   = $this->service->symbols();
        $nextNum   = $this->service->nextNumStart();

        $buses = Database::select(
            "SELECT code, plate FROM buses WHERE status != 'hors_service' ORDER BY code"
        );

        $this->view('billetterie/urban-tickets/create', [
            'title'     => 'Nouvelle série de tickets urbains',
            'symbols'   => $symbols,
            'nextNum'   => $nextNum,
            'buses'     => $buses,
        ]);
    }

    public function store(Request $request): void
    {
        if ($request->method !== 'POST') Response::json(['error' => 'Méthode invalide.'], 405);
        if (!Auth::can('billetterie.preprint')) Response::json(['error' => 'Permission refusée.'], 403);

        try {
            $series = $this->service->createSeries([
                'ticket_date'   => $request->input('ticket_date'),
                'symbol_id'     => $request->input('symbol_id'),
                'price_fcfa'    => $request->input('price_fcfa', 150),
                'bus_code'      => $request->input('bus_code'),
                'departure'     => $request->input('departure'),
                'arrival'       => $request->input('arrival'),
                'network_label' => $request->input('network_label', 'Réseau urbain · Brazzaville'),
                'num_start'     => $request->input('num_start'),
                'ticket_count'  => $request->input('ticket_count', 96),
            ]);

            // Générer le PDF immédiatement
            $this->service->generatePdf((int)$series['id']);

            AuditLog::record('urban_ticket.create', 'urban_ticket_series', (int)$series['id'], [
                'series_code'  => $series['series_code'],
                'ticket_count' => $series['ticket_count'],
                'bus_code'     => $series['bus_code'],
            ]);

            if ($request->isAjax()) {
                Response::json(['success' => true, 'series' => $series, 'redirect' => url('billetterie/urban-tickets/' . $series['id'])]);
            }

            $this->flash('success', "Série {$series['series_code']} créée avec {$series['ticket_count']} tickets. PDF prêt au téléchargement.");
            redirect('billetterie/urban-tickets/' . $series['id']);
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                Response::json(['error' => $e->getMessage()], 422);
            }
            $this->flash('error', $e->getMessage());
            redirect('billetterie/urban-tickets/create');
        }
    }

    public function show(Request $request, string $id): void
    {
        $series = $this->service->getById((int)$id);
        if (!$series) {
            $this->flash('error', 'Série introuvable.');
            redirect('billetterie/urban-tickets');
            return;
        }

        $this->view('billetterie/urban-tickets/show', [
            'title'  => 'Série ' . $series['series_code'],
            'series' => $series,
        ]);
    }

    public function downloadPdf(Request $request, string $id): void
    {
        $id = (int)$id;
        $path = $this->service->getPdfPath($id);
        if (!$path) {
            try {
                $path = $this->service->generatePdf($id);
            } catch (\RuntimeException $e) {
                $this->flash('error', $e->getMessage());
                redirect('billetterie/urban-tickets/' . $id);
                return;
            }
        }

        $series = $this->service->getById($id);
        $filename = 'tickets-' . ($series['series_code'] ?? 'urban') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function start(Request $request, string $id): void
    {
        $id = (int)$id;
        if (!Auth::can('billetterie.preprint')) Response::json(['error' => 'Permission refusée.'], 403);

        try {
            $this->service->startSeries($id);
            AuditLog::record('urban_ticket.start', 'urban_ticket_series', $id);
            $this->flash('success', 'Série démarrée.');
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }
        redirect('billetterie/urban-tickets/' . $id);
    }

    public function close(Request $request, string $id): void
    {
        $id = (int)$id;
        if ($request->method !== 'POST') Response::json(['error' => 'Méthode invalide.'], 405);
        if (!Auth::can('billetterie.preprint')) Response::json(['error' => 'Permission refusée.'], 403);

        $ticketsSold   = max(0, (int)$request->input('tickets_sold', 0));
        $revenueActual = $request->input('revenue_actual') !== null && $request->input('revenue_actual') !== ''
            ? max(0, (int)$request->input('revenue_actual'))
            : null;

        try {
            $this->service->closeSeries($id, $ticketsSold, $revenueActual);
            AuditLog::record('urban_ticket.close', 'urban_ticket_series', $id, [
                'tickets_sold'   => $ticketsSold,
                'revenue_actual' => $revenueActual,
            ]);
            $this->flash('success', 'Série clôturée.');
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }
        redirect('billetterie/urban-tickets/' . $id);
    }

    public function cancel(Request $request, string $id): void
    {
        $id = (int)$id;
        if (!Auth::can('billetterie.preprint')) Response::json(['error' => 'Permission refusée.'], 403);

        try {
            $this->service->cancelSeries($id);
            AuditLog::record('urban_ticket.cancel', 'urban_ticket_series', $id);
            $this->flash('success', 'Série annulée.');
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }
        redirect('billetterie/urban-tickets/' . $id);
    }
}
