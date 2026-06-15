<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Auth;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

final class CashDrawerService
{
    public function open(int $cashierId, ?int $agencyId, int $openingBalance, ?string $drawerCode = null): int
    {
        $existing = Database::selectOne(
            "SELECT id FROM cash_drawers WHERE cashier_id = ? AND closed_at IS NULL",
            [$cashierId]
        );
        if ($existing) throw new \RuntimeException("Une caisse est déjà ouverte (#{$existing['id']}).");

        $id = Database::insert('cash_drawers', [
            'cashier_id' => $cashierId,
            'agency_id'  => $agencyId,
            'drawer_code'=> $drawerCode,
            'opened_at'  => date('Y-m-d H:i:s'),
            'opening_balance' => $openingBalance,
        ]);
        AuditLog::record('caisse.open', 'drawer', $id, ['opening' => $openingBalance]);
        return $id;
    }

    public function recordMovement(int $drawerId, string $type, string $method, int $amount, ?string $reference = null, ?string $notes = null): int
    {
        return Database::insert('cash_drawer_movements', [
            'drawer_id'      => $drawerId,
            'movement_type'  => $type,
            'payment_method' => $method,
            'amount'         => $amount,
            'reference'      => $reference,
            'notes'          => $notes,
        ]);
    }

    public function close(int $drawerId, int $declaredCash): array
    {
        $drawer = Database::selectOne("SELECT * FROM cash_drawers WHERE id = ?", [$drawerId]);
        if (!$drawer) throw new \RuntimeException("Caisse introuvable");
        if ($drawer['closed_at']) throw new \RuntimeException("Caisse déjà clôturée");

        $cashIn = (int)Database::scalar(
            "SELECT COALESCE(SUM(amount),0) FROM cash_drawer_movements
             WHERE drawer_id = ? AND payment_method = 'CASH' AND movement_type IN ('sale','deposit')",
            [$drawerId]
        );
        $cashOut = (int)Database::scalar(
            "SELECT COALESCE(SUM(amount),0) FROM cash_drawer_movements
             WHERE drawer_id = ? AND payment_method = 'CASH' AND movement_type IN ('refund','withdraw')",
            [$drawerId]
        );
        $expected = (int)$drawer['opening_balance'] + $cashIn - $cashOut;
        $variance = $declaredCash - $expected;

        Database::update('cash_drawers', [
            'closed_at' => date('Y-m-d H:i:s'),
            'declared_cash_close' => $declaredCash,
            'expected_cash_close' => $expected,
            'variance' => $variance,
        ], 'id = ?', [$drawerId]);

        AuditLog::record('caisse.close', 'drawer', $drawerId, [
            'expected' => $expected, 'declared' => $declaredCash, 'variance' => $variance,
        ]);

        return [
            'expected' => $expected,
            'declared' => $declaredCash,
            'variance' => $variance,
            'cash_in'  => $cashIn,
            'cash_out' => $cashOut,
        ];
    }

    public function summary(int $drawerId): array
    {
        $drawer = Database::selectOne("SELECT * FROM cash_drawers WHERE id = ?", [$drawerId]);
        if (!$drawer) return [];

        $byMethod = Database::select(
            "SELECT payment_method, movement_type, COUNT(*) AS n, COALESCE(SUM(amount),0) AS total
             FROM cash_drawer_movements WHERE drawer_id = ?
             GROUP BY payment_method, movement_type
             ORDER BY payment_method, movement_type",
            [$drawerId]
        );
        return ['drawer' => $drawer, 'by_method' => $byMethod];
    }

    public function listForCashier(int $cashierId, int $limit = 30): array
    {
        return Database::select(
            "SELECT * FROM cash_drawers WHERE cashier_id = ? ORDER BY opened_at DESC LIMIT $limit",
            [$cashierId]
        );
    }

    public function openDrawer(int $cashierId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM cash_drawers WHERE cashier_id = ? AND closed_at IS NULL",
            [$cashierId]
        );
    }
}
