<?php

declare(strict_types=1);

namespace CityBus\Controllers\Finance;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\AccountingV4Service;
use CityBus\Services\PnlService;

final class AccountingV4Controller extends Controller
{
    private AccountingV4Service $acc;
    private PnlService $pnl;
    public function __construct() {
        $this->acc = new AccountingV4Service();
        $this->pnl = new PnlService();
    }

    public function journal(Request $request): void
    {
        if (!Auth::can('finance.accounting.view')) { back(); return; }
        $code = $request->input('journal', 'sales');
        $from = $request->input('from', date('Y-m-01'));
        $to   = $request->input('to', date('Y-m-d'));
        $this->view('finance/accounting_v4/journal', [
            'title' => "Journal $code",
            'journal' => $code, 'from' => $from, 'to' => $to,
            'entries' => $this->acc->journal($code, $from, $to),
        ]);
    }

    public function ledger(Request $request): void
    {
        if (!Auth::can('finance.accounting.view')) { back(); return; }
        $code = $request->input('account', '411100');
        $from = $request->input('from', date('Y-m-01'));
        $to   = $request->input('to', date('Y-m-d'));
        $this->view('finance/accounting_v4/ledger', [
            'title' => "Grand livre $code",
            'account' => $code, 'from' => $from, 'to' => $to,
            'lines' => $this->acc->generalLedger($code, $from, $to),
            'accounts' => Database::select("SELECT code, label FROM chart_of_accounts ORDER BY code"),
        ]);
    }

    public function balance(Request $request): void
    {
        if (!Auth::can('finance.accounting.view')) { back(); return; }
        $from = $request->input('from', date('Y-01-01'));
        $to   = $request->input('to', date('Y-m-d'));
        $this->view('finance/accounting_v4/balance', [
            'title' => 'Balance comptable',
            'from' => $from, 'to' => $to,
            'rows' => $this->acc->balance($from, $to),
        ]);
    }

    public function exportCsv(Request $request): void
    {
        if (!Auth::can('finance.accounting.export')) { back(); return; }
        $from = $request->input('from', date('Y-m-01'));
        $to   = $request->input('to', date('Y-m-d'));
        $csv = $this->acc->exportCsv($from, $to);
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"compta-{$from}_{$to}.csv\"");
        echo $csv;
        exit;
    }

    public function post(Request $request, string $id): void
    {
        if (!Auth::can('finance.accounting.post')) { back(); return; }
        $this->acc->post((int)$id);
        $this->flash('success', 'Écriture validée.');
        back();
    }

    public function pnlIndex(Request $request): void
    {
        if (!Auth::can('finance.pnl.view')) { back(); return; }
        $from = $request->input('from', date('Y-m-01'));
        $to   = $request->input('to', date('Y-m-d'));
        if ($request->input('recompute')) {
            $n = $this->pnl->recomputeBatch($from, $to);
            $this->flash('success', "$n voyages recalculés.");
            $this->redirect("finance/pnl?from=$from&to=$to");
            return;
        }
        $this->view('finance/pnl_v4/index', [
            'title' => 'P&L par ligne',
            'from' => $from, 'to' => $to,
            'global' => $this->pnl->summaryGlobal($from, $to),
            'byLine' => $this->pnl->summaryByLine($from, $to),
        ]);
    }

    public function pnlTrip(Request $request, string $tripId): void
    {
        if (!Auth::can('finance.pnl.view')) { back(); return; }
        $r = $this->pnl->recomputeForTrip((int)$tripId);
        $this->flash('success', 'P&L recalculé.');
        $this->redirect('voyages/' . $tripId);
    }
}
