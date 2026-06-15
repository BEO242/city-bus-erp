<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Models\AuditLog;

final class PartnerCommissionService
{
    public function recordSale(int $partnerId, int $ticketId, int $saleAmount): int
    {
        $partner = Database::selectOne("SELECT commission_percent FROM sales_partners WHERE id = ?", [$partnerId]);
        if (!$partner) throw new \RuntimeException("Partenaire introuvable");
        $commission = (int)round($saleAmount * (float)$partner['commission_percent'] / 100);
        return Database::insert('partner_commissions', [
            'partner_id'        => $partnerId,
            'ticket_id'         => $ticketId,
            'sale_amount'       => $saleAmount,
            'commission_amount' => $commission,
            'period_month'      => date('Y-m'),
            'status'            => 'pending',
        ]);
    }

    public function generateMonthlyPayout(int $partnerId, string $periodYM): int
    {
        $from = $periodYM . '-01';
        $to   = date('Y-m-t', strtotime($from));
        $rows = Database::select(
            "SELECT COUNT(*) AS n, COALESCE(SUM(sale_amount),0) AS sales, COALESCE(SUM(commission_amount),0) AS commission
             FROM partner_commissions
             WHERE partner_id = ? AND period_month = ? AND status IN ('pending','accrued')",
            [$partnerId, $periodYM]
        );
        $tot = $rows[0] ?? null;
        if (!$tot || (int)$tot['n'] === 0) throw new \RuntimeException("Aucune vente sur la période");

        $payoutId = Database::insert('partner_payouts', [
            'partner_id'      => $partnerId,
            'period_from'     => $from,
            'period_to'       => $to,
            'tickets_count'   => (int)$tot['n'],
            'revenue_fcfa'    => (int)$tot['sales'],
            'commission_fcfa' => (int)$tot['commission'],
            'status'          => 'pending',
        ]);

        Database::execute(
            "UPDATE partner_commissions SET status='invoiced', payout_id=? WHERE partner_id=? AND period_month=? AND status IN ('pending','accrued')",
            [$payoutId, $partnerId, $periodYM]
        );

        AuditLog::record('partner.payout_generated', 'payout', $payoutId, ['amount' => $tot['commission']]);
        return $payoutId;
    }

    public function markPaid(int $payoutId): void
    {
        Database::update('partner_payouts',
            ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')],
            'id = ?', [$payoutId]
        );
        Database::execute(
            "UPDATE partner_commissions SET status='paid' WHERE payout_id = ?",
            [$payoutId]
        );
    }

    public function listForPartner(int $partnerId, int $limit = 30): array
    {
        return Database::select(
            "SELECT * FROM partner_payouts WHERE partner_id = ? ORDER BY period_from DESC LIMIT $limit",
            [$partnerId]
        );
    }

    public function dashboard(): array
    {
        return Database::select(
            "SELECT sp.id, sp.name, sp.code, sp.commission_percent,
                    COUNT(pc.id) AS sales_count,
                    COALESCE(SUM(pc.sale_amount),0) AS revenue,
                    COALESCE(SUM(pc.commission_amount),0) AS commission
             FROM sales_partners sp
             LEFT JOIN partner_commissions pc ON pc.partner_id = sp.id AND pc.period_month = ?
             WHERE sp.is_active = 1
             GROUP BY sp.id, sp.name, sp.code, sp.commission_percent
             ORDER BY commission DESC",
            [date('Y-m')]
        );
    }
}
