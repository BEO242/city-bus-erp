<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

/**
 * Disruption management (GAP-08) :
 *  - annulation d'un voyage avec re-protection automatique sur le suivant
 *  - émission automatique d'avoirs
 *  - communication groupée aux passagers
 */
final class DisruptionService
{
    /**
     * Lance la procédure d'annulation d'un voyage entier.
     */
    public function cancelTrip(int $tripId, string $reason, int $userId): array
    {
        $trip = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) throw new \RuntimeException('Voyage introuvable.');

        // 1. Tickets actifs sur ce voyage
        $tickets = Database::select(
            "SELECT * FROM tickets
             WHERE trip_id = ? AND deleted_at IS NULL AND status IN ('emis','controle','embarque')",
            [$tripId]
        );

        $vouchersIssued = 0;
        $smsSent = 0;
        $voucherPct = max(0, min(100, Setting::getInt('disruption.auto_voucher_pct', 100)));
        $validityDays = max(7, Setting::getInt('vouchers.default_validity_days', 90));
        $validUntil = date('Y-m-d', time() + $validityDays * 86400);

        foreach ($tickets as $t) {
            // Annule le billet
            Database::execute(
                "UPDATE tickets
                    SET status = 'annule', cancelled_at = NOW(),
                        cancelled_by = ?, cancel_reason = ?
                  WHERE id = ?",
                [$userId, "Annulation voyage : $reason", (int)$t['id']]
            );

            // Émet un avoir
            if ($voucherPct > 0 && (int)$t['price_fcfa'] > 0) {
                $voucherAmount = (int)round(((int)$t['price_fcfa']) * $voucherPct / 100);
                $code = $this->generateVoucherCode();
                Database::insert(
                    "INSERT INTO vouchers
                        (code, customer_id, issued_amount, remaining_amount, reason,
                         source_trip_id, issued_at, valid_until)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)",
                    [$code, $t['customer_id'] ?? null, $voucherAmount, $voucherAmount,
                     "Annulation voyage {$trip['trip_code']}", $tripId, $validUntil]
                );
                $vouchersIssued++;

                // Notification SMS si téléphone disponible
                if (!empty($t['passenger_phone'])) {
                    $msg = sprintf(
                        "CITY BUS · Désolés, le voyage %s du %s est annulé. Avoir %s FCFA (code: %s) valable jusqu'au %s.",
                        $trip['trip_code'],
                        date('d/m/Y', strtotime((string)$trip['trip_date'])),
                        number_format($voucherAmount, 0, ',', ' '),
                        $code,
                        date('d/m/Y', strtotime($validUntil))
                    );
                    try {
                        SmsService::send((string)$t['passenger_phone'], $msg);
                        $smsSent++;
                    } catch (\Throwable $e) {
                        \CityBus\Core\Logger::warning('disruption.sms_failed: ' . $e->getMessage());
                    }
                }
            }
        }

        // 2. Statut voyage
        Database::execute(
            "UPDATE trips SET status = 'annule', cancellation_reason = ? WHERE id = ?",
            [$reason, $tripId]
        );

        AuditLog::record('disruption.cancel_trip', 'trip', $tripId, [
            'reason'    => $reason,
            'tickets'   => count($tickets),
            'vouchers'  => $vouchersIssued,
            'sms_sent'  => $smsSent,
        ]);
        WebhookService::dispatch('trip.cancelled', [
            'trip_id'  => $tripId,
            'tickets'  => count($tickets),
            'vouchers' => $vouchersIssued,
        ]);

        return [
            'tickets_cancelled' => count($tickets),
            'vouchers_issued'   => $vouchersIssued,
            'sms_sent'          => $smsSent,
        ];
    }

    /**
     * Re-protection : propose les voyages alternatifs disponibles sur la même
     * ligne dans les 48 heures suivantes.
     */
    public function reprotectionOptions(int $cancelledTripId): array
    {
        $trip = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$cancelledTripId]);
        if (!$trip) return [];

        return Database::select(
            "SELECT id, trip_code, trip_date, departure_scheduled, status
             FROM trips
             WHERE line_id = ?
               AND id != ?
               AND status IN ('planifie','embarquement')
               AND TIMESTAMPDIFF(HOUR, ?, CONCAT(trip_date, ' ', departure_scheduled)) BETWEEN 0 AND 48
             ORDER BY trip_date ASC, departure_scheduled ASC LIMIT 5",
            [(int)$trip['line_id'], $cancelledTripId, $trip['trip_date'] . ' ' . $trip['departure_scheduled']]
        );
    }

    public function applyVoucher(string $code, int $amount): ?array
    {
        $voucher = Database::selectOne(
            "SELECT * FROM vouchers
              WHERE code = ? AND is_void = 0 AND remaining_amount > 0
                AND (valid_until IS NULL OR valid_until >= CURDATE())",
            [$code]
        );
        if (!$voucher) return null;

        $apply = min((int)$voucher['remaining_amount'], $amount);
        Database::execute(
            "UPDATE vouchers SET remaining_amount = remaining_amount - ?,
                                  used_at = COALESCE(used_at, NOW())
              WHERE id = ?",
            [$apply, (int)$voucher['id']]
        );
        return ['voucher' => $voucher, 'discount_fcfa' => $apply];
    }

    public function issueManualVoucher(int $amount, ?int $customerId, ?int $tripId, string $reason, ?int $validityDays = null): array
    {
        $code = $this->generateVoucherCode();
        $vd   = $validityDays ?? Setting::getInt('vouchers.default_validity_days', 90);
        $until = date('Y-m-d', time() + $vd * 86400);

        $id = (int)Database::insert(
            "INSERT INTO vouchers
                (code, customer_id, issued_amount, remaining_amount, reason,
                 source_trip_id, issued_at, valid_until)
             VALUES (?,?,?,?,?,?,NOW(),?)",
            [$code, $customerId, $amount, $amount, $reason, $tripId, $until]
        );
        return ['id' => $id, 'code' => $code, 'amount' => $amount, 'valid_until' => $until];
    }

    private function generateVoucherCode(): string
    {
        // 10 chars alphanumériques (sans confusion 0/O 1/I)
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'V';
            for ($i = 0; $i < 9; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $exists = Database::selectOne("SELECT id FROM vouchers WHERE code = ?", [$code]);
        } while ($exists);
        return $code;
    }
}
