<?php

declare(strict_types=1);

namespace CityBus\Controllers\Finance;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\TripPnlService;

final class PnlController extends Controller
{
    private TripPnlService $svc;

    public function __construct() { $this->svc = new TripPnlService(); }

    public function index(Request $request): void
    {
        $from = trim((string)$request->input('from', date('Y-m-01')));
        $to   = trim((string)$request->input('to', date('Y-m-d')));

        $byLine  = $this->svc->byLine($from, $to);
        $best    = $this->svc->ranking($from, $to, 10, false);
        $worst   = $this->svc->ranking($from, $to, 10, true);

        $totals = [
            'revenue' => 0, 'cost_direct' => 0, 'cost_indirect' => 0,
            'margin_contrib' => 0, 'margin_net' => 0, 'trips' => 0,
        ];
        foreach ($byLine as $r) {
            $totals['revenue']        += (int)$r['revenue'];
            $totals['cost_direct']    += (int)$r['cost_direct'];
            $totals['cost_indirect']  += (int)$r['cost_indirect'];
            $totals['margin_contrib'] += (int)$r['margin_contrib'];
            $totals['margin_net']     += (int)$r['margin_net'];
            $totals['trips']          += (int)$r['trips_count'];
        }

        $this->view('finance/pnl/index', [
            'title'  => 'Profitabilité par voyage',
            'from'   => $from,
            'to'     => $to,
            'byLine' => $byLine,
            'best'   => $best,
            'worst'  => $worst,
            'totals' => $totals,
        ]);
    }

    public function trip(Request $request, string $tripId): void
    {
        $pnl = $this->svc->getByTrip((int)$tripId);
        if (!$pnl) {
            // Calcul à la volée
            $pnl = $this->svc->compute((int)$tripId, (int)Auth::id());
        }
        $trip = Database::selectOne(
            "SELECT tr.*, l.code AS line_code, l.name AS line_name,
                    b.code AS bus_code, b.plate
             FROM trips tr
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             LEFT JOIN buses b ON b.id = tr.bus_id
             WHERE tr.id = ?",
            [(int)$tripId]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        $this->view('finance/pnl/trip', [
            'title' => "P&L du voyage {$trip['trip_code']}",
            'pnl'   => $pnl,
            'trip'  => $trip,
        ]);
    }

    public function recompute(Request $request, string $tripId): void
    {
        if (!Auth::can('finance.pnl.view')) { back(); }
        $this->svc->compute((int)$tripId, (int)Auth::id(), force: true);
        $this->flash('success', 'P&L recalculé.');
        back();
    }

    public function export(Request $request): void
    {
        if (!Auth::can('finance.pnl.export')) { back(); }
        $from = trim((string)$request->input('from', date('Y-m-01')));
        $to   = trim((string)$request->input('to',   date('Y-m-d')));
        $rows = $this->svc->byLine($from, $to);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pnl-par-ligne_' . $from . '_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Code','Ligne','Voyages','Recettes','Coûts directs','Coûts indirects','Marge contrib.','Marge nette','Charge moy %'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['code'], $r['name'], (int)$r['trips_count'],
                (int)$r['revenue'], (int)$r['cost_direct'], (int)$r['cost_indirect'],
                (int)$r['margin_contrib'], (int)$r['margin_net'],
                number_format((float)$r['avg_load'], 1, ',', ''),
            ], ';');
        }
        fclose($out);
        exit;
    }
}
