<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;

/**
 * Codes promo : validation et application d'une remise.
 */
final class PromoService
{
    /**
     * Valide un code et retourne la remise applicable, ou null si invalide.
     *
     * @return array{promo:array, discount_fcfa:int}|null
     */
    public function validate(string $code, int $amountFcfa, ?int $customerId = null, ?int $lineId = null, ?string $category = null): ?array
    {
        $promo = Database::selectOne(
            "SELECT * FROM promo_codes
              WHERE UPPER(code) = UPPER(?)
                AND is_active = 1
                AND (valid_from IS NULL OR valid_from <= NOW())
                AND (valid_until IS NULL OR valid_until >= NOW())",
            [$code]
        );
        if (!$promo) return null;
        if ($amountFcfa < (int)$promo['min_amount_fcfa']) return null;
        if ($promo['max_uses'] && (int)$promo['used_count'] >= (int)$promo['max_uses']) return null;

        // Limite par client
        if ($customerId && $promo['max_uses_per_customer']) {
            $used = (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM promo_redemptions
                  WHERE promo_id = ? AND customer_id = ?",
                [(int)$promo['id'], $customerId]
            )['c'] ?? 0);
            if ($used >= (int)$promo['max_uses_per_customer']) return null;
        }

        // Filtres lignes / catégorie
        if (!empty($promo['applicable_lines'])) {
            $lines = json_decode((string)$promo['applicable_lines'], true) ?: [];
            if ($lineId !== null && !empty($lines) && !in_array($lineId, $lines, true)) return null;
        }
        if (!empty($promo['applicable_categories'])) {
            $cats = json_decode((string)$promo['applicable_categories'], true) ?: [];
            if ($category !== null && !empty($cats) && !in_array($category, $cats, true)) return null;
        }

        $discount = match ($promo['discount_type']) {
            'percent'   => (int)round($amountFcfa * (int)$promo['discount_value'] / 100),
            'fixed'     => min($amountFcfa, (int)$promo['discount_value']),
            'free_seat' => $amountFcfa,
            default     => 0,
        };
        if ($promo['max_discount_fcfa'] && $discount > (int)$promo['max_discount_fcfa']) {
            $discount = (int)$promo['max_discount_fcfa'];
        }
        return ['promo' => $promo, 'discount_fcfa' => $discount];
    }

    public function recordRedemption(int $promoId, ?int $customerId, ?int $ticketId, int $discount): void
    {
        Database::insert(
            "INSERT INTO promo_redemptions (promo_id, customer_id, ticket_id, discount_fcfa, used_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$promoId, $customerId, $ticketId, $discount]
        );
        Database::execute("UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?", [$promoId]);
    }

    public function active(): array
    {
        return Database::select(
            "SELECT * FROM promo_codes
              WHERE is_active = 1
                AND (valid_until IS NULL OR valid_until >= NOW())
              ORDER BY valid_until ASC, code ASC"
        );
    }
}
