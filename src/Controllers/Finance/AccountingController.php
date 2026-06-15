<?php

declare(strict_types=1);

namespace CityBus\Controllers\Finance;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\AccountingService;

final class AccountingController extends Controller
{
    private AccountingService $svc;
    public function __construct() { $this->svc = new AccountingService(); }

    public function journal(Request $request): void
    {
        $from    = trim((string)$request->input('from', date('Y-m-01')));
        $to      = trim((string)$request->input('to',   date('Y-m-d')));
        $journal = trim((string)$request->input('journal', ''));
        $account = trim((string)$request->input('account', ''));

        $entries = $this->svc->listEntries($from, $to, $journal ?: null, $account ?: null);
        $totals  = $this->svc->totals($from, $to);
        $accounts = Database::select("SELECT code, label FROM chart_of_accounts WHERE is_active=1 ORDER BY code");

        $totalDebit = array_sum(array_map(fn($e) => (int)$e['debit_fcfa'], $entries));
        $totalCredit = array_sum(array_map(fn($e) => (int)$e['credit_fcfa'], $entries));

        $this->view('finance/accounting/journal', [
            'title'       => 'Journal comptable',
            'entries'     => $entries,
            'totals'      => $totals,
            'totalDebit'  => $totalDebit,
            'totalCredit' => $totalCredit,
            'accounts'    => $accounts,
            'journals'    => ['VTE' => 'Ventes', 'ACH' => 'Achats', 'CSE' => 'Caisse', 'BNQ' => 'Banque', 'OD' => 'Opérations diverses'],
            'from'        => $from, 'to' => $to,
            'journal'     => $journal, 'account' => $account,
        ]);
    }

    public function exportCsv(Request $request): void
    {
        if (!Auth::can('finance.accounting.export')) { back(); }
        $from    = trim((string)$request->input('from', date('Y-m-01')));
        $to      = trim((string)$request->input('to',   date('Y-m-d')));
        $journal = trim((string)$request->input('journal', ''));

        $entries = $this->svc->listEntries($from, $to, $journal ?: null);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="journal_' . $from . '_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date','Journal','Compte','Libellé compte','Libellé écriture','Pièce','Tiers','Débit','Crédit','Source','Source ID'], ';');
        foreach ($entries as $e) {
            fputcsv($out, [
                $e['entry_date'], $e['journal_code'], $e['account_code'],
                $e['account_label'] ?? '', $e['label'],
                $e['reference'] ?? '', $e['third_party'] ?? '',
                (int)$e['debit_fcfa'], (int)$e['credit_fcfa'],
                $e['source_table'] ?? '', $e['source_id'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    /** Export Sage / fichier d'import standard SYSCOHADA. */
    public function exportSage(Request $request): void
    {
        if (!Auth::can('finance.accounting.export')) { back(); }
        $from = trim((string)$request->input('from', date('Y-m-01')));
        $to   = trim((string)$request->input('to',   date('Y-m-d')));

        $entries = $this->svc->listEntries($from, $to);

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="sage_' . $from . '_' . $to . '.txt"');
        // Format propre Sage import : JJMMAAAA;JJ;Cpte;Libellé;Débit;Crédit;Pièce
        foreach ($entries as $e) {
            echo sprintf("%s;%s;%s;%s;%d;%d;%s\n",
                date('dmY', strtotime((string)$e['entry_date'])),
                $e['journal_code'],
                $e['account_code'],
                str_replace([';', "\n"], ['_', ' '], (string)$e['label']),
                (int)$e['debit_fcfa'],
                (int)$e['credit_fcfa'],
                $e['reference'] ?? ''
            );
        }
        exit;
    }
}
