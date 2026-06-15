<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * Génération d'écritures comptables SYSCOHADA pour chaque transaction.
 *
 * Chaque vente est ventilée en :
 *   Débit  411 (clients) ou 531 (caisse) selon le mode de paiement
 *   Crédit 706 (prestations de service) pour le HT
 *   Crédit 445660 (TVA collectée) pour la TVA
 */
final class AccountingService
{
    public function isEnabled(): bool
    {
        return Setting::getBool('accounting.enabled', true);
    }

    /** Pour une vente de billet : génère les écritures HT/TVA. */
    public function recordTicketSale(array $ticket, string $paymentMethod = 'especes'): void
    {
        if (!$this->isEnabled()) return;

        $ttc = (int)($ticket['price_fcfa']     ?? 0);
        $ht  = (int)($ticket['price_ht_fcfa']  ?? $ttc);
        $tax = (int)($ticket['tax_amount_fcfa'] ?? 0);
        if ($ttc <= 0) return;

        $debitAccount = $this->paymentDebitAccount($paymentMethod);
        $label = sprintf('Vente billet %s', $ticket['ticket_number'] ?? '');
        $ref   = $ticket['invoice_number'] ?? $ticket['ticket_number'] ?? null;

        $this->record([
            'entry_date'    => date('Y-m-d'),
            'journal_code'  => 'VTE',
            'account_code'  => $debitAccount,
            'label'         => $label,
            'debit'         => $ttc,
            'credit'        => 0,
            'reference'     => $ref,
            'source_table'  => 'tickets',
            'source_id'     => (int)($ticket['id'] ?? 0),
            'agency_id'     => (int)($ticket['agency_id'] ?? 0),
            'third_party'   => $ticket['passenger_name'] ?? null,
        ]);
        $this->record([
            'entry_date'    => date('Y-m-d'),
            'journal_code'  => 'VTE',
            'account_code'  => Setting::getString('accounting.account_tickets', '706000'),
            'label'         => $label . ' (HT)',
            'debit'         => 0,
            'credit'        => $ht,
            'reference'     => $ref,
            'source_table'  => 'tickets',
            'source_id'     => (int)($ticket['id'] ?? 0),
            'agency_id'     => (int)($ticket['agency_id'] ?? 0),
        ]);
        if ($tax > 0) {
            $this->record([
                'entry_date'   => date('Y-m-d'),
                'journal_code' => 'VTE',
                'account_code' => Setting::getString('accounting.account_vat', '445660'),
                'label'        => $label . ' (TVA)',
                'debit'        => 0,
                'credit'       => $tax,
                'reference'    => $ref,
                'source_table' => 'tickets',
                'source_id'    => (int)($ticket['id'] ?? 0),
                'agency_id'    => (int)($ticket['agency_id'] ?? 0),
            ]);
        }
    }

    public function recordBaggageSale(array $bt, string $paymentMethod = 'especes'): void
    {
        if (!$this->isEnabled()) return;
        $ttc = (int)($bt['total_price_fcfa'] ?? 0);
        $ht  = (int)($bt['price_ht_fcfa']    ?? $ttc);
        $tax = (int)($bt['tax_amount_fcfa']  ?? 0);
        if ($ttc <= 0) return;

        $label = sprintf('Vente bagage %s', $bt['ticket_number'] ?? '');
        $ref   = $bt['invoice_number'] ?? $bt['ticket_number'] ?? null;

        $this->record([
            'entry_date'   => date('Y-m-d'),
            'journal_code' => 'VTE',
            'account_code' => $this->paymentDebitAccount($paymentMethod),
            'label'        => $label,
            'debit'        => $ttc, 'credit' => 0,
            'reference'    => $ref,
            'source_table' => 'baggage_tickets',
            'source_id'    => (int)($bt['id'] ?? 0),
            'agency_id'    => (int)($bt['agency_id'] ?? 0),
        ]);
        $this->record([
            'entry_date'   => date('Y-m-d'),
            'journal_code' => 'VTE',
            'account_code' => Setting::getString('accounting.account_baggage', '706100'),
            'label'        => $label . ' (HT)',
            'debit'        => 0, 'credit' => $ht,
            'reference'    => $ref,
            'source_table' => 'baggage_tickets',
            'source_id'    => (int)($bt['id'] ?? 0),
        ]);
        if ($tax > 0) {
            $this->record([
                'entry_date'   => date('Y-m-d'),
                'journal_code' => 'VTE',
                'account_code' => Setting::getString('accounting.account_vat', '445660'),
                'label'        => $label . ' (TVA)',
                'debit'        => 0, 'credit' => $tax,
                'reference'    => $ref, 'source_table' => 'baggage_tickets',
                'source_id'    => (int)($bt['id'] ?? 0),
            ]);
        }
    }

    public function recordParcelSale(array $parcel): void
    {
        if (!$this->isEnabled()) return;
        $ttc = (int)($parcel['total_price_fcfa'] ?? 0);
        $tax = (int)($parcel['tax_amount_fcfa']  ?? 0);
        $ht  = $ttc - $tax;
        if ($ttc <= 0) return;
        $label = sprintf('Fret %s', $parcel['parcel_number'] ?? '');

        $debit = $parcel['paid_at_origin']
            ? $this->paymentDebitAccount($parcel['payment_method'] ?? 'especes')
            : Setting::getString('accounting.account_clients', '411000');

        $this->record([
            'entry_date'   => date('Y-m-d'),
            'journal_code' => 'VTE',
            'account_code' => $debit,
            'label'        => $label, 'debit' => $ttc, 'credit' => 0,
            'reference'    => $parcel['parcel_number'] ?? null,
            'source_table' => 'parcels', 'source_id' => (int)($parcel['id'] ?? 0),
            'agency_id'    => (int)($parcel['origin_agency_id'] ?? 0),
            'third_party'  => $parcel['sender_name'] ?? null,
        ]);
        $this->record([
            'entry_date'   => date('Y-m-d'),
            'journal_code' => 'VTE',
            'account_code' => Setting::getString('accounting.account_cargo', '706200'),
            'label'        => $label . ' (HT)',
            'debit'        => 0, 'credit' => $ht,
            'reference'    => $parcel['parcel_number'] ?? null,
            'source_table' => 'parcels', 'source_id' => (int)($parcel['id'] ?? 0),
        ]);
        if ($tax > 0) {
            $this->record([
                'entry_date'   => date('Y-m-d'),
                'journal_code' => 'VTE',
                'account_code' => Setting::getString('accounting.account_vat', '445660'),
                'label'        => $label . ' (TVA)',
                'debit'        => 0, 'credit' => $tax,
                'reference'    => $parcel['parcel_number'] ?? null,
                'source_table' => 'parcels', 'source_id' => (int)($parcel['id'] ?? 0),
            ]);
        }
    }

    /** Achat de carburant. */
    public function recordFuelPurchase(array $log): void
    {
        if (!$this->isEnabled()) return;
        $amount = (int)($log['total_cost'] ?? 0);
        if ($amount <= 0) return;
        $label = sprintf('Plein carburant véhicule #%d (%s L)', (int)$log['bus_id'], $log['liters'] ?? '?');

        $this->record([
            'entry_date'   => $log['logged_at'] ? date('Y-m-d', strtotime((string)$log['logged_at'])) : date('Y-m-d'),
            'journal_code' => 'ACH',
            'account_code' => Setting::getString('accounting.account_fuel', '605000'),
            'label'        => $label,
            'debit'        => $amount, 'credit' => 0,
            'source_table' => 'fuel_logs', 'source_id' => (int)($log['id'] ?? 0),
        ]);
        $this->record([
            'entry_date'   => $log['logged_at'] ? date('Y-m-d', strtotime((string)$log['logged_at'])) : date('Y-m-d'),
            'journal_code' => 'ACH',
            'account_code' => Setting::getString('accounting.account_cash', '531000'),
            'label'        => $label,
            'debit'        => 0, 'credit' => $amount,
            'source_table' => 'fuel_logs', 'source_id' => (int)($log['id'] ?? 0),
        ]);
    }

    public function recordPayrollRun(array $payslip): void
    {
        if (!$this->isEnabled()) return;
        $amount = (int)($payslip['net_amount'] ?? 0);
        if ($amount <= 0) return;
        $label = sprintf('Salaire %s %02d/%d', $payslip['employee_name'] ?? '', $payslip['month'] ?? 0, $payslip['year'] ?? 0);

        $this->record([
            'entry_date'   => date('Y-m-d'),
            'journal_code' => 'OD',
            'account_code' => Setting::getString('accounting.account_salary', '641000'),
            'label'        => $label, 'debit' => $amount, 'credit' => 0,
            'source_table' => 'payroll', 'source_id' => (int)($payslip['id'] ?? 0),
            'third_party'  => $payslip['employee_name'] ?? null,
        ]);
        $this->record([
            'entry_date'   => date('Y-m-d'),
            'journal_code' => 'OD',
            'account_code' => Setting::getString('accounting.account_personnel', '421000'),
            'label'        => $label, 'debit' => 0, 'credit' => $amount,
            'source_table' => 'payroll', 'source_id' => (int)($payslip['id'] ?? 0),
            'third_party'  => $payslip['employee_name'] ?? null,
        ]);
    }

    /** Liste des écritures (avec filtres). */
    public function listEntries(string $from, string $to, ?string $journal = null, ?string $account = null, ?int $agencyId = null): array
    {
        $where = ['entry_date BETWEEN ? AND ?'];
        $params = [$from, $to];
        if ($journal) { $where[] = 'journal_code = ?'; $params[] = $journal; }
        if ($account) { $where[] = 'account_code = ?'; $params[] = $account; }
        if ($agencyId) { $where[] = 'agency_id = ?'; $params[] = $agencyId; }

        return Database::select(
            "SELECT * FROM accounting_entries
             WHERE " . implode(' AND ', $where) . "
             ORDER BY entry_date ASC, id ASC",
            $params
        );
    }

    public function totals(string $from, string $to): array
    {
        return Database::select(
            "SELECT account_code,
                    (SELECT label FROM chart_of_accounts WHERE code = ae.account_code) AS account_label,
                    SUM(debit_fcfa) AS total_debit,
                    SUM(credit_fcfa) AS total_credit,
                    SUM(debit_fcfa - credit_fcfa) AS balance
             FROM accounting_entries ae
             WHERE entry_date BETWEEN ? AND ?
             GROUP BY account_code
             ORDER BY account_code",
            [$from, $to]
        );
    }

    private function paymentDebitAccount(string $method): string
    {
        return match ($method) {
            'especes'                          => Setting::getString('accounting.account_cash', '531000'),
            'mobile', 'mobile_money', 'carte', 'virement'
                                               => Setting::getString('accounting.account_bank', '521000'),
            default                            => Setting::getString('accounting.account_clients', '411000'),
        };
    }

    private function record(array $e): void
    {
        $accountLabel = Database::selectOne("SELECT label FROM chart_of_accounts WHERE code = ?", [$e['account_code']])['label'] ?? null;
        Database::insert(
            "INSERT INTO accounting_entries
                (entry_date, journal_code, account_code, account_label, label,
                 debit_fcfa, credit_fcfa, reference, source_table, source_id,
                 agency_id, third_party)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $e['entry_date'], $e['journal_code'], $e['account_code'],
                $accountLabel, $e['label'],
                (int)($e['debit'] ?? 0), (int)($e['credit'] ?? 0),
                $e['reference'] ?? null,
                $e['source_table'] ?? null, $e['source_id'] ?? null,
                !empty($e['agency_id']) ? (int)$e['agency_id'] : null,
                $e['third_party'] ?? null,
            ]
        );
    }
}
