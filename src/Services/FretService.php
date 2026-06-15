<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;
use CityBus\Models\FretItem;
use CityBus\StateMachines\FretStateMachine;
use RuntimeException;
use InvalidArgumentException;

final class FretService
{
    /**
     * Register a new fret item (baggage or colis).
     *
     * @param array $data Item data with keys: item_type, category_slug, trip_id, passenger_ticket_id,
     *                    sender_name, sender_phone, recipient_name, recipient_phone, weight_kg,
     *                    pieces_count, description, is_franchise, origin_agency_id,
     *                    destination_agency_id, agency_id, registered_by, cash_register_id
     * @return array The inserted fret_item row
     */
    public function register(array $data): array
    {
        return Database::transaction(function () use ($data) {
            // Validate category exists and is active
            $category = Database::selectOne(
                "SELECT * FROM fret_categories WHERE slug = :slug AND is_active = 1",
                ['slug' => $data['category_slug']]
            );

            if (!$category) {
                throw new RuntimeException("Catégorie de fret invalide ou inactive : {$data['category_slug']}");
            }

            // Calculate pricing
            $pricePerKg = (int) $category['price_per_kg'];
            $minPrice = (int) $category['min_price_fcfa'];
            $totalPrice = max($minPrice, (int) ceil($data['weight_kg'] * $pricePerKg));

            // If franchise, total price is 0
            $isFranchise = !empty($data['is_franchise']);
            if ($isFranchise) {
                $totalPrice = 0;
            }

            // Generate tracking code
            $trackingCode = FretItem::generateTrackingCode();

            // Déterminer payment_status initial
            // Franchise → non_applicable, sinon → en_attente (paiement différé)
            $paymentStatus = $isFranchise ? 'non_applicable' : 'en_attente';

            // Insert into fret_items
            $id = Database::insert("INSERT INTO fret_items (
                tracking_code, item_type, category_slug, trip_id, passenger_ticket_id,
                sender_name, sender_phone, recipient_name, recipient_phone,
                weight_kg, pieces_count, description, is_franchise,
                price_per_kg, min_price_fcfa, total_price_fcfa,
                origin_agency_id, destination_agency_id,
                origin_stop_id, destination_stop_id,
                status, payment_status,
                agency_id, registered_by, cash_register_id, created_at, updated_at
            ) VALUES (
                :tracking_code, :item_type, :category_slug, :trip_id, :passenger_ticket_id,
                :sender_name, :sender_phone, :recipient_name, :recipient_phone,
                :weight_kg, :pieces_count, :description, :is_franchise,
                :price_per_kg, :min_price_fcfa, :total_price_fcfa,
                :origin_agency_id, :destination_agency_id,
                :origin_stop_id, :destination_stop_id,
                :status, :payment_status,
                :agency_id, :registered_by, :cash_register_id, NOW(), NOW()
            )", [
                'tracking_code'       => $trackingCode,
                'item_type'           => $data['item_type'],
                'category_slug'       => $data['category_slug'],
                'trip_id'             => $data['trip_id'] ?? null,
                'passenger_ticket_id' => $data['passenger_ticket_id'] ?? null,
                'sender_name'         => $data['sender_name'],
                'sender_phone'        => $data['sender_phone'],
                'recipient_name'      => $data['recipient_name'],
                'recipient_phone'     => $data['recipient_phone'],
                'weight_kg'           => $data['weight_kg'],
                'pieces_count'        => $data['pieces_count'] ?? 1,
                'description'         => $data['description'] ?? null,
                'is_franchise'        => $isFranchise ? 1 : 0,
                'price_per_kg'        => $pricePerKg,
                'min_price_fcfa'      => $minPrice,
                'total_price_fcfa'    => $totalPrice,
                'origin_agency_id'    => $data['origin_agency_id'],
                'destination_agency_id' => $data['destination_agency_id'],
                'origin_stop_id'      => $data['origin_stop_id'] ?? null,
                'destination_stop_id' => $data['destination_stop_id'] ?? null,
                'status'              => 'enregistre',
                'payment_status'      => $paymentStatus,
                'agency_id'           => $data['agency_id'],
                'registered_by'       => $data['registered_by'],
                'cash_register_id'    => $data['cash_register_id'] ?? null,
            ]);

            // Record audit log
            AuditLog::record('fret.register', 'fret_item', $id, [
                'tracking_code' => $trackingCode,
                'item_type'     => $data['item_type'],
                'category_slug' => $data['category_slug'],
                'weight_kg'     => $data['weight_kg'],
                'total_price'   => $totalPrice,
                'is_franchise'  => $isFranchise,
            ]);

            // Return the inserted row
            return Database::selectOne(
                "SELECT * FROM fret_items WHERE id = :id",
                ['id' => $id]
            );
        });
    }

    /**
     * Cancel a fret item (with state machine validation).
     */
    public function cancel(int $id, string $reason, int $userId): void
    {
        Database::transaction(function () use ($id, $reason, $userId) {
            $item = Database::selectOne(
                "SELECT * FROM fret_items WHERE id = :id AND deleted_at IS NULL FOR UPDATE",
                ['id' => $id]
            );

            if (!$item) {
                throw new RuntimeException("Fret item introuvable : #{$id}");
            }

            // Valider via state machine
            FretStateMachine::assertTransition($item['status'], 'annule');

            $currentPayment = $item['payment_status'] ?? 'en_attente';
            $refundAmount = 0;

            // Si déjà payé → rembourser
            if ($currentPayment === 'paye') {
                $refundPct = max(0, min(100, Setting::getInt('fret.refund_pct', 100)));
                $refundAmount = (int)round((int)$item['total_price_fcfa'] * $refundPct / 100);
            }

            $newPaymentStatus = ($currentPayment === 'non_applicable') ? 'non_applicable'
                : (($currentPayment === 'en_attente') ? 'rembourse' : 'rembourse');

            Database::execute(
                "UPDATE fret_items SET status = 'annule', payment_status = :ps,
                        refund_amount_fcfa = :refund, refund_reason = :reason,
                        refunded_at = NOW(), refunded_by = :refunded_by,
                        cancelled_at = NOW(), cancelled_by = :user_id, cancel_reason = :cancel_reason,
                        updated_at = NOW()
                 WHERE id = :id",
                [
                    'id'            => $id,
                    'ps'            => $newPaymentStatus,
                    'refund'        => $refundAmount,
                    'reason'        => $reason,
                    'refunded_by'   => $userId,
                    'user_id'       => $userId,
                    'cancel_reason' => $reason,
                ]
            );

            // Contre-passation si remboursement
            if ($refundAmount > 0 && !empty($item['cash_register_id'])) {
                Database::execute(
                    "INSERT INTO cash_register_entries
                       (cash_register_id, entry_type, amount_fcfa, reference_type, reference_id, note, created_by, created_at)
                     VALUES (?, 'remboursement_fret', ?, 'fret_item', ?, ?, ?, NOW())",
                    [
                        (int)$item['cash_register_id'], -$refundAmount, $id,
                        sprintf('Annulation fret %s — %s', $item['tracking_code'], $reason),
                        $userId,
                    ]
                );

                // Table centralisée refunds
                Database::insert(
                    "INSERT INTO refunds (refund_type, reference_id, original_amount_fcfa, refund_amount_fcfa,
                                         refund_percent, reason, cash_register_id, agency_id, refunded_by, status, executed_at)
                     VALUES ('fret', ?, ?, ?, ?, ?, ?, ?, ?, 'execute', NOW())",
                    [
                        $id, (int)$item['total_price_fcfa'], $refundAmount,
                        (int)$item['total_price_fcfa'] > 0 ? round($refundAmount / (int)$item['total_price_fcfa'] * 100, 2) : 0,
                        $reason, (int)$item['cash_register_id'],
                        $item['agency_id'] ?? null, $userId,
                    ]
                );
            }

            AuditLog::record('fret.cancel', 'fret_item', $id, [
                'reason'          => $reason,
                'cancelled_by'    => $userId,
                'previous_status' => $item['status'],
                'refund_amount'   => $refundAmount,
            ]);
        });
    }

    /**
     * Update the operational status of a fret item (with state machine validation).
     */
    public function updateStatus(int $id, string $newStatus, int $userId): void
    {
        Database::transaction(function () use ($id, $newStatus, $userId) {
            $item = Database::selectOne(
                "SELECT * FROM fret_items WHERE id = :id AND deleted_at IS NULL FOR UPDATE",
                ['id' => $id]
            );

            if (!$item) {
                throw new RuntimeException("Fret item introuvable : #{$id}");
            }

            // Valider la transition via state machine
            FretStateMachine::assertTransition($item['status'], $newStatus);

            // Vérification spéciale : le chargement nécessite le paiement
            if ($newStatus === 'charge') {
                if (!FretStateMachine::canLoad($item['status'], $item['payment_status'] ?? 'en_attente')) {
                    throw new RuntimeException(
                        'Le colis doit être payé (ou en franchise) avant d\'être chargé.'
                    );
                }
            }

            $previousStatus = $item['status'];

            Database::execute(
                "UPDATE fret_items SET status = :status, updated_at = NOW() WHERE id = :id",
                [
                    'id'     => $id,
                    'status' => $newStatus,
                ]
            );

            AuditLog::record('fret.status_change', 'fret_item', $id, [
                'previous_status' => $previousStatus,
                'new_status'      => $newStatus,
                'changed_by'      => $userId,
            ]);
        });
    }

    /**
     * Payer un item fret en attente de paiement.
     */
    public function pay(int $id, int $userId, ?int $cashRegisterId = null, string $paymentMethod = 'especes'): array
    {
        return Database::transaction(function () use ($id, $userId, $cashRegisterId, $paymentMethod) {
            $item = Database::selectOne(
                "SELECT * FROM fret_items WHERE id = :id AND deleted_at IS NULL FOR UPDATE",
                ['id' => $id]
            );

            if (!$item) {
                throw new RuntimeException("Fret item introuvable : #{$id}");
            }

            FretStateMachine::assertPaymentTransition($item['payment_status'], 'paye');

            $amount = (int)$item['total_price_fcfa'];

            Database::execute(
                "UPDATE fret_items SET payment_status = 'paye', paid_at = NOW(), paid_amount_fcfa = :amount, updated_at = NOW() WHERE id = :id",
                ['id' => $id, 'amount' => $amount]
            );

            // Impact en trésorerie (caisse)
            if ($cashRegisterId) {
                Database::insert(
                    "INSERT INTO sales (cash_register_id, sale_type, fret_item_id, amount_fcfa, payment_method)
                     VALUES (?, 'fret', ?, ?, ?)",
                    [$cashRegisterId, $id, $amount, $paymentMethod]
                );
            }

            // Écriture comptable
            try {
                (new AccountingService())->recordParcelSale(array_merge((array)$item, [
                    'paid_at_origin'  => true,
                    'payment_method'  => $paymentMethod,
                    'parcel_number'   => $item['tracking_code'],
                ]));
            } catch (\Throwable $e) {
                \CityBus\Core\Logger::warning('accounting.fret_pay_failed: ' . $e->getMessage());
            }

            AuditLog::record('fret.pay', 'fret_item', $id, [
                'amount'         => $amount,
                'payment_method' => $paymentMethod,
                'paid_by'        => $userId,
            ]);

            return Database::selectOne("SELECT * FROM fret_items WHERE id = :id", ['id' => $id]);
        });
    }

    /**
     * Rembourser un item fret (partiellement ou totalement).
     */
    public function refund(int $id, int $refundAmount, string $reason, int $userId, ?int $cashRegisterId = null): array
    {
        return Database::transaction(function () use ($id, $refundAmount, $reason, $userId, $cashRegisterId) {
            $item = Database::selectOne(
                "SELECT * FROM fret_items WHERE id = :id AND deleted_at IS NULL FOR UPDATE",
                ['id' => $id]
            );

            if (!$item) {
                throw new RuntimeException("Fret item introuvable : #{$id}");
            }

            if (!FretStateMachine::canRefund($item['payment_status'])) {
                throw new RuntimeException('Remboursement impossible dans l\'état de paiement actuel.');
            }

            $originalAmount = (int)$item['paid_amount_fcfa'];
            $alreadyRefunded = (int)$item['refund_amount_fcfa'];
            $maxRefundable = $originalAmount - $alreadyRefunded;

            if ($refundAmount <= 0 || $refundAmount > $maxRefundable) {
                throw new RuntimeException(sprintf(
                    'Montant de remboursement invalide. Maximum : %d FCFA.', $maxRefundable
                ));
            }

            $totalRefunded = $alreadyRefunded + $refundAmount;
            $newPaymentStatus = ($totalRefunded >= $originalAmount) ? 'rembourse' : 'rembourse_partiel';

            FretStateMachine::assertPaymentTransition($item['payment_status'], $newPaymentStatus);

            Database::execute(
                "UPDATE fret_items SET payment_status = :ps, refund_amount_fcfa = :refund,
                        refund_reason = :reason, refunded_at = NOW(), refunded_by = :user_id, updated_at = NOW()
                 WHERE id = :id",
                [
                    'id'      => $id,
                    'ps'      => $newPaymentStatus,
                    'refund'  => $totalRefunded,
                    'reason'  => $reason,
                    'user_id' => $userId,
                ]
            );

            // Contre-passation en caisse
            $crId = $cashRegisterId ?: ($item['cash_register_id'] ?? null);
            if ($crId && $refundAmount > 0) {
                Database::execute(
                    "INSERT INTO cash_register_entries
                       (cash_register_id, entry_type, amount_fcfa, reference_type, reference_id, note, created_by, created_at)
                     VALUES (?, 'remboursement_fret', ?, 'fret_item', ?, ?, ?, NOW())",
                    [
                        (int)$crId, -$refundAmount, $id,
                        sprintf('Remboursement fret %s — %s', $item['tracking_code'], $reason),
                        $userId,
                    ]
                );
            }

            // Table centralisée refunds
            $refundPct = $originalAmount > 0 ? round($refundAmount / $originalAmount * 100, 2) : 0;
            Database::insert(
                "INSERT INTO refunds (refund_type, reference_id, original_amount_fcfa, refund_amount_fcfa,
                                     refund_percent, reason, cash_register_id, agency_id, refunded_by, status, executed_at)
                 VALUES ('fret', ?, ?, ?, ?, ?, ?, ?, ?, 'execute', NOW())",
                [
                    $id, $originalAmount, $refundAmount, $refundPct,
                    $reason, $crId, $item['agency_id'] ?? null, $userId,
                ]
            );

            AuditLog::record('fret.refund', 'fret_item', $id, [
                'amount'     => $refundAmount,
                'reason'     => $reason,
                'new_status' => $newPaymentStatus,
            ]);

            return Database::selectOne("SELECT * FROM fret_items WHERE id = :id", ['id' => $id]);
        });
    }

    /**
     * Mark the talon as printed for a fret item.
     */
    public function markTalonPrinted(int $id): void
    {
        Database::execute(
            "UPDATE fret_items SET talon_printed = 1, talon_printed_at = NOW(), updated_at = NOW() WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Calculate the price for a given category and weight.
     *
     * @return array{price_per_kg: int, min_price_fcfa: int, total_price_fcfa: int, category_label: string}
     */
    public function calculatePrice(string $categorySlug, float $weightKg): array
    {
        $category = Database::selectOne(
            "SELECT * FROM fret_categories WHERE slug = :slug AND is_active = 1",
            ['slug' => $categorySlug]
        );

        if (!$category) {
            throw new RuntimeException("Catégorie de fret invalide ou inactive : {$categorySlug}");
        }

        $pricePerKg = (int) $category['price_per_kg'];
        $minPrice = (int) $category['min_price_fcfa'];
        $totalPrice = max($minPrice, (int) ceil($weightKg * $pricePerKg));

        return [
            'price_per_kg'    => $pricePerKg,
            'min_price_fcfa'  => $minPrice,
            'total_price_fcfa' => $totalPrice,
            'category_label'  => $category['label'],
        ];
    }

    /**
     * Get freight summary for a trip.
     *
     * @return array{baggage_franchise_count: int, baggage_excedent_count: int, colis_count: int, total_weight_kg: float, total_price_fcfa: int}
     */
    public function tripSummary(int $tripId): array
    {
        $items = Database::select(
            "SELECT item_type, is_franchise, weight_kg, total_price_fcfa FROM fret_items WHERE trip_id = :trip_id AND status != 'annule' AND deleted_at IS NULL",
            ['trip_id' => $tripId]
        );

        $bagageFranchiseCount = 0;
        $bagageExcedentCount = 0;
        $colisCount = 0;
        $totalWeight = 0.0;
        $totalPrice = 0;

        foreach ($items as $item) {
            $totalWeight += (float) $item['weight_kg'];
            $totalPrice += (int) $item['total_price_fcfa'];

            if ($item['item_type'] === 'baggage') {
                if ($item['is_franchise']) {
                    $bagageFranchiseCount++;
                } else {
                    $bagageExcedentCount++;
                }
            } else {
                $colisCount++;
            }
        }

        return [
            'baggage_franchise_count' => $bagageFranchiseCount,
            'baggage_excedent_count'  => $bagageExcedentCount,
            'colis_count'             => $colisCount,
            'total_weight_kg'         => $totalWeight,
            'total_price_fcfa'        => $totalPrice,
        ];
    }
}
