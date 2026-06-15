<?php

declare(strict_types=1);

namespace CityBus\Controllers\Finance;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\InvoiceService;
use CityBus\Services\TaxV4Service;

final class InvoiceController extends Controller
{
    private InvoiceService $svc;
    private TaxV4Service $tax;
    public function __construct() {
        $this->svc = new InvoiceService();
        $this->tax = new TaxV4Service();
    }

    public function index(Request $request): void
    {
        if (!Auth::can('finance.invoices.view')) { back(); return; }
        $this->view('finance/invoices/index', [
            'title' => 'Factures',
            'rows'  => $this->svc->listRecent(200),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        if (!Auth::can('finance.invoices.view')) { back(); return; }
        $inv = $this->svc->get((int)$id);
        if (!$inv) { http_response_code(404); $this->view('errors/404'); return; }
        $this->view('finance/invoices/show', [
            'title' => 'Facture ' . $inv['invoice_number'],
            'inv' => $inv,
        ]);
    }

    public function vatDeclaration(Request $request): void
    {
        if (!Auth::can('finance.tax.declare')) { back(); return; }
        $month = $request->input('month', date('Y-m'));
        $decl = $this->tax->vatDeclaration($month);
        $this->view('finance/tax/declaration', [
            'title' => 'Déclaration TVA · ' . $month,
            'declaration' => $decl,
            'month' => $month,
        ]);
    }

    public function vatExportCsv(Request $request): void
    {
        if (!Auth::can('finance.tax.declare')) { back(); return; }
        $month = $request->input('month', date('Y-m'));
        $d = $this->tax->vatDeclaration($month);
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"TVA-$month.csv\"");
        echo "Periode;Taux;HT;TVA;TTC\n";
        foreach ($d['by_rate'] as $r) {
            echo sprintf("%s;%.2f;%d;%d;%d\n",
                $month, (float)$r['tax_pct'], (int)$r['ht'], (int)$r['tax'], (int)$r['ht']+(int)$r['tax']);
        }
        exit;
    }

    public function markPaid(Request $request, string $id): void
    {
        if (!Auth::can('finance.invoices.create')) { back(); return; }
        $this->svc->markPaid((int)$id, (int)$request->input('amount', 0) ?: null);
        $this->flash('success', 'Encaissement enregistré.');
        back();
    }

    public function void(Request $request, string $id): void
    {
        if (!Auth::can('finance.invoices.cancel')) { back(); return; }
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 5) { $this->flash('danger', 'Motif requis.'); back(); return; }
        $this->svc->void((int)$id, $reason);
        $this->flash('success', 'Facture annulée.');
        back();
    }
}
