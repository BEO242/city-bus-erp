<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Models\AuditLog;

final class PromoCodeService
{
    public function validate(string $code, int $amount, ?int $customerId = null, ?int $lineId = null, ?string $bookingClass = null): array
    {
        $promo = Database::selectOne(
            "SELECT * FROM promo_codes WHERE code = ? AND active = 1
             AND (valid_from IS NULL OR valid_from <= CURDATE())
             AND (valid_until IS NULL OR valid_until >= CURDATE())", [$code]
        );
        if (!$promo) return ['valid' => false, 'reason' => 'Code invalide'];

        if ($promo['max_uses'] && (int)$promo['used_count'] >= (int)$promo['max_uses']) {
            return ['valid' => false, 'reason' => 'Limite d\'utilisations atteinte'];
        }
        if ((int)$promo['min_amount_fcfa'] > 0 && $amount < (int)$promo['min_amount_fcfa']) {
            return ['valid' => false, 'reason' => 'Montant minimum non atteint'];
        }
        if ($promo['line_id'] && $lineId && (int)$promo['line_id'] !== $lineId) {
            return ['valid' => false, 'reason' => 'Code non valide sur cette ligne'];
        }
        if ($promo['booking_class'] && $bookingClass && $promo['booking_class'] !== $bookingClass) {
            return ['valid' => false, 'reason' => 'Code non valide sur cette classe'];
        }
        if ($customerId && $promo['max_per_customer']) {
            $used = (int)Database::scalar(
                "SELECT COUNT(*) FROM promo_redemptions WHERE promo_id = ? AND customer_id = ?",
                [$promo['id'], $customerId]
            );
            if ($used >= (int)$promo['max_per_customer']) {
                return ['valid' => false, 'reason' => 'Vous avez déjà utilisé ce code'];
            }
        }

        $discount = $promo['discount_type'] === 'percent'
            ? (int)round($amount * (int)$promo['discount_value'] / 100)
            : (int)$promo['discount_value'];
        $discount = min($discount, $amount);

        return [
            'valid' => true, 'promo' => $promo,
            'discount_fcfa' => $discount,
            'final_amount' => $amount - $discount,
        ];
    }

    public function redeem(int $promoId, ?int $customerId, ?int $pnrId, int $discount): int
    {
        Database::execute("UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?", [$promoId]);
        $id = Database::insert('promo_redemptions', [
            'promo_id'        => $promoId,
            'customer_id'     => $customerId,
            'pnr_id'          => $pnrId,
            'discount_applied'=> $discount,
        ]);
        AuditLog::record('promo.redeem', 'promo', $promoId, ['discount' => $discount]);
        return $id;
    }

    public function listAll(): array
    {
        return Database::select(
            "SELECT pc.*, l.code AS line_code FROM promo_codes pc
             LEFT JOIN bus_lines l ON l.id = pc.line_id
             ORDER BY pc.active DESC, pc.id DESC"
        );
    }

    public function create(array $data): int
    {
        return Database::insert('promo_codes', [
            'code' => strtoupper(trim($data['code'])),
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'promo_code',
            'discount_type' => $data['discount_type'] ?? 'percent',
            'discount_value' => (int)$data['discount_value'],
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'max_per_customer' => $data['max_per_customer'] ?? 1,
            'line_id' => $data['line_id'] ?? null,
            'booking_class' => $data['booking_class'] ?? null,
            'min_amount_fcfa' => $data['min_amount_fcfa'] ?? 0,
            'active' => $data['active'] ?? 1,
        ]);
    }
}
