<?php

declare(strict_types=1);

namespace CityBus\Controllers\Finance;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Services\TaxService;

final class TaxController extends Controller
{
    private TaxService $tax;

    public function __construct() { $this->tax = new TaxService(); }

    public function vatReport(Request $request): void
    {
        $from = trim((string)$request->input('from', date('Y-m-01')));
        $to   = trim((string)$request->input('to',   date('Y-m-d')));
        $report = $this->tax->vatReport($from, $to);

        // Totaux globaux
        $totals = ['ht' => 0, 'tax' => 0, 'docs' => 0];
        foreach (['tickets','baggage','cargo'] as $k) {
            foreach ($report[$k] as $row) {
                $totals['ht']   += (int)($row['ht'] ?? 0);
                $totals['tax']  += (int)($row['tax'] ?? 0);
                $totals['docs'] += (int)($row['count_docs'] ?? 0);
            }
        }
        $totals['ttc'] = $totals['ht'] + $totals['tax'];

        $this->view('finance/tax/vat', [
            'title'   => 'Déclaration TVA',
            'report'  => $report,
            'totals'  => $totals,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    public function exportVatCsv(Request $request): void
    {
        if (!Auth::can('finance.tax.export')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $from = trim((string)$request->input('from', date('Y-m-01')));
        $to   = trim((string)$request->input('to',   date('Y-m-d')));
        $report = $this->tax->vatReport($from, $to);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="declaration-tva_' . $from . '_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Catégorie','Taux %','Base HT','Montant TVA','Montant TTC','Nombre de documents'], ';');
        foreach (['tickets' => 'Billets passagers', 'baggage' => 'Bagages', 'cargo' => 'Fret'] as $k => $label) {
            foreach ($report[$k] as $row) {
                $ht = (int)($row['ht'] ?? 0);
                $tax = (int)($row['tax'] ?? 0);
                fputcsv($out, [$label, number_format((float)($row['rate'] ?? 0), 2, ',', ''), $ht, $tax, $ht + $tax, (int)($row['count_docs'] ?? 0)], ';');
            }
        }
        fclose($out);
        exit;
    }
}
