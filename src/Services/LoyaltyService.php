<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * Programme de fidélité : gain et utilisation de points.
 */
final class LoyaltyService
{
    public function isEnabled(): bool
    {
        return Setting::getBool('loyalty.enabled', true);
    }

    /** Crédit suite à une vente. */
    public function earnFromSpend(int $customerId, int $amountSpent, ?int $ticketId = null): int
    {
        if (!$this->isEnabled() || $amountSpent <= 0) return 0;
        $rate = (float)Setting::getString('loyalty.points_per_fcfa', '0.001');
        $points = (int)floor($amountSpent * $rate);
        if ($points <= 0) return 0;

        Database::execute("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?", [$points, $customerId]);
        $balance = (int)(Database::selectOne("SELECT loyalty_points FROM customers WHERE id = ?", [$customerId])['loyalty_points'] ?? 0);
        Database::insert(
            "INSERT INTO loyalty_transactions (customer_id, points_delta, balance_after, reason, ticket_id, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$customerId, $points, $balance, 'Gain sur vente', $ticketId]
        );
        $this->updateTier($customerId, $balance);
        return $points;
    }

    /** Utilisation de points : retourne le montant FCFA équivalent. */
    public function redeemPoints(int $customerId, int $pointsToUse, ?int $ticketId = null): int
    {
        if (!$this->isEnabled() || $pointsToUse <= 0) return 0;
        $balance = (int)(Database::selectOne("SELECT loyalty_points FROM customers WHERE id = ?", [$customerId])['loyalty_points'] ?? 0);
        $pointsToUse = min($pointsToUse, $balance);
        if ($pointsToUse <= 0) return 0;

        $rate = Setting::getInt('loyalty.points_to_fcfa', 10);
        $value = $pointsToUse * $rate;

        Database::execute("UPDATE customers SET loyalty_points = loyalty_points - ? WHERE id = ?", [$pointsToUse, $customerId]);
        $newBalance = $balance - $pointsToUse;
        Database::insert(
            "INSERT INTO loyalty_transactions (customer_id, points_delta, balance_after, reason, ticket_id, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$customerId, -$pointsToUse, $newBalance, "Utilisation $pointsToUse points", $ticketId]
        );
        $this->updateTier($customerId, $newBalance);
        return $value;
    }

    private function updateTier(int $customerId, int $balance): void
    {
        $silver   = Setting::getInt('loyalty.tier_silver_points', 500);
        $gold     = Setting::getInt('loyalty.tier_gold_points', 2000);
        $platinum = Setting::getInt('loyalty.tier_platinum_points', 10000);

        $tier = match (true) {
            $balance >= $platinum => 'platinum',
            $balance >= $gold     => 'gold',
            $balance >= $silver   => 'silver',
            default               => 'standard',
        };
        Database::execute("UPDATE customers SET loyalty_tier = ? WHERE id = ?", [$tier, $customerId]);
    }
}
