<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;
use CityBus\StateMachines\TicketStateMachine;

/**
 * Cœur métier de la billetterie : création, annulation, remboursement, reprint.
 */
final class TicketService
{
    public function __construct(
        private QrCodeService $qr = new QrCodeService(),
        private PdfService $pdf = new PdfService(),
    ) {}

    /**
     * Crée un ticket, génère le QR, enregistre la vente, génère le PDF.
     * Tout est encapsulé dans une transaction avec verrou siège.
     *
     * @param array $data ['trip_id','ticket_type','passenger_name','passenger_phone',
     *                     'seat_number','price_fcfa','agency_id','sold_by',
     *                     'cash_register_id','luggage'=>[['type','weight_kg','price_fcfa']],
     *                     'pre_print_id'?]
     */
    public function create(array $data): array
    {
        return Database::transaction(function () use ($data) {

            // Arrondi du prix au multiple de N (paramètre caisse.rounding, 0 = pas d'arrondi)
            $rounding = max(0, Setting::getInt('caisse.rounding', 0));
            if ($rounding > 0 && isset($data['price_fcfa'])) {
                $data['price_fcfa'] = (int)(round((int)$data['price_fcfa'] / $rounding) * $rounding);
            }

            // 1. Verrouiller le voyage : statut + capacité (anti race-condition)
            $trip = Database::selectOne(
                "SELECT tr.id, tr.status, tr.bus_id, tr.trip_date, tr.departure_scheduled, b.seats
                   FROM trips tr
                   JOIN buses b ON b.id = tr.bus_id
                  WHERE tr.id = ?
                  FOR UPDATE",
                [$data['trip_id']]
            );
            if (!$trip) {
                throw new \RuntimeException('Voyage introuvable.');
            }
            // Ventes autorisées uniquement si le voyage est dans un statut ouvert.
            // La clôture des ventes est déclenchée par le passage du voyage au statut 'cloture'.
            if (!in_array($trip['status'], ['planifie', 'valide', 'embarquement'], true)) {
                throw new \RuntimeException("Ce voyage n'accepte plus de ventes (statut : {$trip['status']}).");
            }

            // Bascule automatique en 'embarquement' à H-X min (si paramètre > 0)
            if (!empty($trip['departure_scheduled'])) {
                $depDatetime = $trip['trip_date'] . ' ' . $trip['departure_scheduled'];
                $depTs = strtotime($depDatetime);
                if ($depTs !== false) {
                    $openMin = max(0, Setting::getInt('voyage.checkin_open_minutes', 60));
                    if (in_array($trip['status'], ['planifie', 'valide'], true)
                        && $openMin > 0
                        && time() >= ($depTs - $openMin * 60)) {
                        Database::execute(
                            "UPDATE trips SET status='embarquement' WHERE id=? AND status IN ('planifie','valide')",
                            [$trip['id']]
                        );
                    }
                }
            }

            // Capacité : compter les billets actifs déjà vendus
            $sold = (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM tickets
                  WHERE trip_id = ?
                    AND status IN ('emis','valide','arrive')
                    AND deleted_at IS NULL",
                [$data['trip_id']]
            )['c'] ?? 0);

            // Capacité effective : avec surréservation paramétrable
            $capacity = (int)$trip['seats'];
            if (Setting::getBool('voyage.allow_overbooking', false)) {
                $pct = max(0, min(100, Setting::getInt('voyage.overbooking_pct', 0)));
                $capacity = (int)floor($capacity * (1 + $pct / 100));
            }
            if ($sold >= $capacity) {
                throw new \RuntimeException("Bus complet : {$capacity} sièges déjà vendus.");
            }

            // 2. Verrouiller le siège demandé
            if (!empty($data['seat_number'])) {
                $seatNum = (int)$data['seat_number'];
                if ($seatNum < 1 || $seatNum > $capacity) {
                    throw new \RuntimeException("Siège n°{$seatNum} hors capacité (1 à {$capacity}).");
                }
                $existing = Database::selectOne(
                    "SELECT id FROM tickets
                     WHERE trip_id = ? AND seat_number = ?
                       AND status IN ('emis','valide','arrive')
                       AND deleted_at IS NULL
                     FOR UPDATE",
                    [$data['trip_id'], $seatNum]
                );
                if ($existing) {
                    throw new \RuntimeException('Le siège n°' . $seatNum . ' est déjà attribué.');
                }
            }

            // 2. Numéro de ticket séquentiel (préfixe paramétrable)
            $prefix = trim(\CityBus\Core\Setting::getString('billetterie.ticket_prefix', 'CB'));
            if ($prefix === '') $prefix = 'CB';
            $year = date('Y');
            $like = "{$prefix}-{$year}-%";
            $row = Database::selectOne(
                "SELECT ticket_number FROM tickets
                 WHERE ticket_number LIKE ?
                 ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$like]
            );
            $next = $row ? ((int)substr($row['ticket_number'], -5)) + 1 : 1;
            $ticketNumber = sprintf('%s-%s-%05d', $prefix, $year, $next);

            // 3. QR code (réutilise celui du pré-imprimé si fourni)
            if (!empty($data['pre_print_id'])) {
                $pp = Database::selectOne("SELECT * FROM pre_printed_tickets WHERE id = ? FOR UPDATE", [$data['pre_print_id']]);
                if (!$pp || $pp['status'] !== 'disponible') {
                    throw new \RuntimeException('Ticket pré-imprimé indisponible.');
                }
                $qrCode = $pp['qr_code'];
                $qrHash = $pp['qr_code_hash'];
            } else {
                $qrCode = $this->qr->generateUuid();
                $qrHash = $this->qr->hash($qrCode);
            }

            // 3.5 Find or create CRM customer (GAP-02)
            $customerSvc = new CustomerService();
            $customerId = $customerSvc->findOrCreateFromTicket([
                'name'  => $data['passenger_name'] ?? '',
                'phone' => $data['passenger_phone'] ?? '',
            ]);

            // 3.6 Loyalty discount : si le client est éligible, appliquer la réduction
            $loyaltyDiscount = 0;
            if ($customerId && empty($data['discount_fcfa'])) {
                $discountPct = $customerSvc->loyaltyDiscount($customerId);
                if ($discountPct > 0) {
                    $loyaltyDiscount = (int)round((int)$data['price_fcfa'] * $discountPct / 100);
                    $data['price_fcfa']     = max(0, (int)$data['price_fcfa'] - $loyaltyDiscount);
                    $data['discount_fcfa']  = $loyaltyDiscount;
                    $data['discount_reason'] = "Fidélité ({$discountPct}%)";
                }
            }

            // 4. Calcul TVA + numéro de facture (GAP-21)
            $taxSvc        = new TaxService();
            // Récupère le taux du tarif si défini, sinon défaut
            $tariffRow     = !empty($data['tariff_id'])
                ? Database::selectOne("SELECT tax_rate_id FROM tariffs WHERE id=?", [$data['tariff_id']])
                : null;
            $rateId        = $tariffRow['tax_rate_id'] ?? null;
            $breakdown     = $taxSvc->breakdown((int)$data['price_fcfa'], $rateId ? (int)$rateId : null);
            $invoiceNumber = $taxSvc->nextInvoiceNumber((int)$data['agency_id']);

            // 5. Déterminer le statut de paiement initial
            // Par défaut = en_attente (émission sans paiement, paiement via bouton "Payer")
            // Si payment_status explicitement fourni = 'paye' → encaissement immédiat
            $paymentStatus = ($data['payment_status'] ?? 'en_attente') === 'paye' ? 'paye' : 'en_attente';
            $paidAt        = $paymentStatus === 'paye' ? date('Y-m-d H:i:s') : null;
            $paidAmount    = $paymentStatus === 'paye' ? (int)$data['price_fcfa'] : null;

            // 5. Insertion ticket
            $ticketId = Database::insert(
                "INSERT INTO tickets
                 (ticket_number, trip_id, ticket_type, passenger_category, travel_class, tariff_id,
                  is_manual_price, manual_price_reason, discount_fcfa, discount_reason,
                  passenger_name, passenger_phone, customer_id,
                  seat_number, boarding_stop_id, alighting_stop_id, price_fcfa,
                  price_ht_fcfa, tax_rate_id, tax_rate_percent, tax_amount_fcfa, invoice_number,
                  payment_status, paid_at, paid_amount_fcfa,
                  qr_code, qr_code_hash, status, pre_print_id,
                  sold_by, agency_id, sold_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'emis',?,?,?,NOW())",
                [
                    $ticketNumber, $data['trip_id'], $data['ticket_type'] ?? 'passager',
                    $data['passenger_category'] ?? 'adulte',
                    $data['travel_class'] ?? 'standard',
                    $data['tariff_id'] ?? null,
                    $data['is_manual_price']     ?? 0,
                    $data['manual_price_reason'] ?? null,
                    $data['discount_fcfa']        ?? 0,
                    $data['discount_reason']      ?? null,
                    $data['passenger_name'] ?? null, $data['passenger_phone'] ?? null, $customerId,
                    $data['seat_number'] ?? null,
                    $data['boarding_stop_id'] ?? null,
                    $data['alighting_stop_id'] ?? null,
                    (int)$data['price_fcfa'],
                    $breakdown['ht'], $breakdown['rate_id'], $breakdown['rate_percent'], $breakdown['tax'],
                    $invoiceNumber,
                    $paymentStatus, $paidAt, $paidAmount,
                    $qrCode, $qrHash,
                    $data['pre_print_id'] ?? null,
                    $data['sold_by'], $data['agency_id'],
                ]
            );

            // Bump CRM stats
            if ($customerId) {
                (new CustomerService())->bumpStats($customerId, (int)$data['price_fcfa'], 'trip');
            }

            // 5. Activer pré-imprimé
            if (!empty($data['pre_print_id'])) {
                Database::execute(
                    "UPDATE pre_printed_tickets
                     SET status='active', activated_ticket_id=?, activated_by=?, activated_at=NOW()
                     WHERE id=?",
                    [$ticketId, $data['sold_by'], $data['pre_print_id']]
                );
            }

            // 6. Bagages
            foreach ($data['luggage'] ?? [] as $lug) {
                Database::insert(
                    "INSERT INTO luggage_tags (ticket_id, type, weight_kg, price_fcfa, tag_number)
                     VALUES (?,?,?,?,?)",
                    [$ticketId, $lug['type'], $lug['weight_kg'] ?? null,
                     (int)($lug['price_fcfa'] ?? 0),
                     $lug['tag_number'] ?? null]
                );
            }

            // 7. Vente liée à la caisse — seulement si paiement immédiat
            if ($paymentStatus === 'paye' && !empty($data['cash_register_id'])) {
                Database::insert(
                    "INSERT INTO sales (cash_register_id, sale_type, ticket_id, amount_fcfa, payment_method)
                     VALUES (?, 'ticket', ?, ?, ?)",
                    [$data['cash_register_id'], $ticketId, (int)$data['price_fcfa'],
                     $data['payment_method'] ?? 'especes']
                );
            }

            // 8. Récupérer ticket complet + générer PDF
            $ticket = Database::selectOne("SELECT * FROM tickets WHERE id = ?", [$ticketId]);
            $pdfPath = $this->pdf->generateTicket($ticket);
            Database::execute("UPDATE tickets SET pdf_path=? WHERE id=?", [$pdfPath, $ticketId]);
            $ticket['pdf_path'] = $pdfPath;

            // Écriture comptable automatique (GAP-23) — seulement si payé immédiatement
            if ($paymentStatus === 'paye') {
                try {
                    (new AccountingService())->recordTicketSale(
                        array_merge((array)$ticket, ['agency_id' => $data['agency_id']]),
                        $data['payment_method'] ?? 'especes'
                    );
                } catch (\Throwable $e) {
                    \CityBus\Core\Logger::warning('accounting.ticket_failed: ' . $e->getMessage());
                }
            }

            AuditLog::record('ticket.create', 'ticket', $ticketId, [
                'number'    => $ticketNumber,
                'price'     => $data['price_fcfa'],
                'tariff_id' => $data['tariff_id'] ?? null,
                'scope'     => ($data['ticket_type'] ?? '') . '/' .
                               ($data['passenger_category'] ?? '') . '/' .
                               ($data['travel_class'] ?? ''),
            ]);

            // Notifications externes (webhook + SMS) — fire-and-forget
            \CityBus\Services\WebhookService::dispatch('ticket.sold', [
                'ticket_id' => $ticketId,
                'number'    => $ticketNumber,
                'price_fcfa'=> (int)$data['price_fcfa'],
                'trip_id'   => $data['trip_id'] ?? null,
            ]);
            $phone = trim((string)($ticket['passenger_phone'] ?? ''));
            if ($phone !== '' && Setting::getBool('sms.notify_ticket_sold', false)) {
                \CityBus\Services\SmsService::send(
                    $phone,
                    "CITY BUS : votre billet {$ticketNumber} est confirmé. Bon voyage."
                );
            }

            return $ticket;
        });
    }

    public function cancel(int $ticketId, string $reason, int $userId): bool
    {
        return Database::transaction(function () use ($ticketId, $reason, $userId) {
            $ticket = Database::selectOne("SELECT * FROM tickets WHERE id=? FOR UPDATE", [$ticketId]);
            if (!$ticket) throw new \RuntimeException('Ticket introuvable.');

            // Valider la transition via la machine à états
            TicketStateMachine::assertTransition($ticket['status'], 'annule');

            $trip = Database::selectOne(
                "SELECT status, departure_scheduled FROM trips WHERE id=?",
                [$ticket['trip_id']]
            );
            if (in_array($trip['status'] ?? '', ['en_route','arrive','cloture'], true)) {
                throw new \RuntimeException('Annulation impossible : voyage déjà en cours/terminé.');
            }

            // Délai d'annulation paramétrable : il faut au moins X heures avant départ
            $minDelayH = max(0, \CityBus\Core\Setting::getInt('billetterie.cancellation_delay_h', 2));
            if ($minDelayH > 0 && !empty($trip['departure_scheduled'])) {
                $depTs = strtotime((string)$trip['departure_scheduled']);
                if ($depTs !== false) {
                    $remainingH = ($depTs - time()) / 3600.0;
                    if ($remainingH < $minDelayH) {
                        throw new \RuntimeException(sprintf(
                            'Annulation refusée : il reste moins de %d h avant le départ.',
                            $minDelayH
                        ));
                    }
                }
            }

            // Pourcentage remboursé paramétrable
            $refundPct = max(0, min(100, \CityBus\Core\Setting::getInt('billetterie.refund_pct', 80)));
            $originalAmount = (int)$ticket['price_fcfa'];
            $refundAmount   = (int)round($originalAmount * $refundPct / 100);

            // Déterminer le nouveau payment_status selon l'état actuel
            $currentPayment = $ticket['payment_status'] ?? 'paye';
            if ($currentPayment === 'en_attente') {
                // Pas encore payé → annulation sans remboursement
                $newPaymentStatus = 'rembourse'; // terminal
                $refundAmount = 0;
            } else {
                $newPaymentStatus = 'rembourse';
            }

            Database::execute(
                "UPDATE tickets SET status='annule', payment_status=?,
                        refund_amount_fcfa=?, refund_reason=?, refunded_at=NOW(), refunded_by=?,
                        cancelled_by=?, cancelled_at=NOW(), cancel_reason=?
                 WHERE id=?",
                [$newPaymentStatus, $refundAmount, $reason, $userId, $userId, $reason, $ticketId]
            );

            // Contre-passation en caisse : seulement si le billet avait été payé
            if ($currentPayment === 'paye' || $currentPayment === 'rembourse_partiel') {
                $sale = Database::selectOne(
                    "SELECT cash_register_id, amount_fcfa FROM sales WHERE ticket_id = ? LIMIT 1",
                    [$ticketId]
                );
                if ($sale && $refundAmount > 0) {
                    Database::execute(
                        "INSERT INTO cash_register_entries
                           (cash_register_id, entry_type, amount_fcfa, reference_type, reference_id, note, created_by, created_at)
                         VALUES (?, 'remboursement_billet', ?, 'ticket', ?, ?, ?, NOW())",
                        [
                            (int)$sale['cash_register_id'],
                            -$refundAmount,
                            $ticketId,
                            sprintf('Annulation %s — %s (remboursement %d%% : %d/%d F)',
                                $ticket['ticket_number'], $reason, $refundPct, $refundAmount, $originalAmount),
                            $userId,
                        ]
                    );
                }

                // Enregistrer dans la table centralisée des remboursements
                if ($refundAmount > 0) {
                    Database::insert(
                        "INSERT INTO refunds (refund_type, reference_id, original_amount_fcfa, refund_amount_fcfa,
                                             refund_percent, reason, cash_register_id, agency_id, refunded_by, status, executed_at)
                         VALUES ('ticket', ?, ?, ?, ?, ?, ?, ?, ?, 'execute', NOW())",
                        [
                            $ticketId, $originalAmount, $refundAmount, (float)$refundPct,
                            $reason, $sale['cash_register_id'] ?? null,
                            $ticket['agency_id'] ?? null, $userId,
                        ]
                    );
                }
            }

            // Bloquer le pré-imprimé physique : il a déjà été imprimé/distribué,
            // on ne le rend PAS disponible (anti-fraude). Statut = annule + traçabilité.
            if (!empty($ticket['pre_print_id'])) {
                Database::execute(
                    "UPDATE pre_printed_tickets
                        SET status='annule',
                            cancelled_by=?,
                            cancelled_at=NOW(),
                            cancel_reason=CONCAT('Annulation via billet ', ?, ' — ', ?)
                      WHERE id=? AND activated_ticket_id=?",
                    [
                        $userId,
                        $ticket['ticket_number'],
                        $reason,
                        (int)$ticket['pre_print_id'],
                        $ticketId,
                    ]
                );
            }

            AuditLog::record('ticket.cancel', 'ticket', $ticketId, ['reason' => $reason]);
            return true;
        });
    }

    /**
     * Payer un billet en attente de paiement.
     */
    public function pay(int $ticketId, int $userId, ?int $cashRegisterId = null, string $paymentMethod = 'especes'): array
    {
        return Database::transaction(function () use ($ticketId, $userId, $cashRegisterId, $paymentMethod) {
            $ticket = Database::selectOne("SELECT * FROM tickets WHERE id=? FOR UPDATE", [$ticketId]);
            if (!$ticket) throw new \RuntimeException('Ticket introuvable.');

            TicketStateMachine::assertPaymentTransition($ticket['payment_status'], 'paye');

            $amount = (int)$ticket['price_fcfa'];

            Database::execute(
                "UPDATE tickets SET payment_status='paye', paid_at=NOW(), paid_amount_fcfa=? WHERE id=?",
                [$amount, $ticketId]
            );

            // Impact caisse
            if ($cashRegisterId) {
                Database::insert(
                    "INSERT INTO sales (cash_register_id, sale_type, ticket_id, amount_fcfa, payment_method)
                     VALUES (?, 'ticket', ?, ?, ?)",
                    [$cashRegisterId, $ticketId, $amount, $paymentMethod]
                );
            }

            // Écriture comptable
            try {
                (new AccountingService())->recordTicketSale(
                    array_merge((array)$ticket, ['price_fcfa' => $amount]),
                    $paymentMethod
                );
            } catch (\Throwable $e) {
                \CityBus\Core\Logger::warning('accounting.ticket_pay_failed: ' . $e->getMessage());
            }

            AuditLog::record('ticket.pay', 'ticket', $ticketId, [
                'amount'         => $amount,
                'payment_method' => $paymentMethod,
            ]);

            return Database::selectOne("SELECT * FROM tickets WHERE id=?", [$ticketId]);
        });
    }

    /**
     * Rembourser partiellement ou totalement un billet payé (sans annulation opérationnelle).
     */
    public function refund(int $ticketId, int $refundAmount, string $reason, int $userId, ?int $cashRegisterId = null): array
    {
        return Database::transaction(function () use ($ticketId, $refundAmount, $reason, $userId, $cashRegisterId) {
            $ticket = Database::selectOne("SELECT * FROM tickets WHERE id=? FOR UPDATE", [$ticketId]);
            if (!$ticket) throw new \RuntimeException('Ticket introuvable.');

            if (!TicketStateMachine::canRefund($ticket['payment_status'])) {
                throw new \RuntimeException('Remboursement impossible dans l\'état de paiement actuel.');
            }

            $originalAmount = (int)$ticket['paid_amount_fcfa'];
            $alreadyRefunded = (int)$ticket['refund_amount_fcfa'];
            $maxRefundable = $originalAmount - $alreadyRefunded;

            if ($refundAmount <= 0 || $refundAmount > $maxRefundable) {
                throw new \RuntimeException(sprintf(
                    'Montant de remboursement invalide. Maximum remboursable : %d FCFA.',
                    $maxRefundable
                ));
            }

            $totalRefunded = $alreadyRefunded + $refundAmount;
            $newPaymentStatus = ($totalRefunded >= $originalAmount) ? 'rembourse' : 'rembourse_partiel';

            TicketStateMachine::assertPaymentTransition($ticket['payment_status'], $newPaymentStatus);

            Database::execute(
                "UPDATE tickets SET payment_status=?, refund_amount_fcfa=?, refund_reason=?,
                        refunded_at=NOW(), refunded_by=?
                 WHERE id=?",
                [$newPaymentStatus, $totalRefunded, $reason, $userId, $ticketId]
            );

            // Contre-passation en caisse
            $sale = Database::selectOne(
                "SELECT cash_register_id FROM sales WHERE ticket_id = ? LIMIT 1",
                [$ticketId]
            );
            $crId = $cashRegisterId ?: ($sale['cash_register_id'] ?? null);
            if ($crId && $refundAmount > 0) {
                Database::execute(
                    "INSERT INTO cash_register_entries
                       (cash_register_id, entry_type, amount_fcfa, reference_type, reference_id, note, created_by, created_at)
                     VALUES (?, 'remboursement_billet', ?, 'ticket', ?, ?, ?, NOW())",
                    [
                        $crId, -$refundAmount, $ticketId,
                        sprintf('Remboursement %s — %s (%d F)', $ticket['ticket_number'], $reason, $refundAmount),
                        $userId,
                    ]
                );
            }

            // Table centralisée refunds
            $refundPct = $originalAmount > 0 ? round($refundAmount / $originalAmount * 100, 2) : 0;
            Database::insert(
                "INSERT INTO refunds (refund_type, reference_id, original_amount_fcfa, refund_amount_fcfa,
                                     refund_percent, reason, cash_register_id, agency_id, refunded_by, status, executed_at)
                 VALUES ('ticket', ?, ?, ?, ?, ?, ?, ?, ?, 'execute', NOW())",
                [
                    $ticketId, $originalAmount, $refundAmount, $refundPct,
                    $reason, $crId, $ticket['agency_id'] ?? null, $userId,
                ]
            );

            AuditLog::record('ticket.refund', 'ticket', $ticketId, [
                'amount'       => $refundAmount,
                'reason'       => $reason,
                'new_status'   => $newPaymentStatus,
            ]);

            return Database::selectOne("SELECT * FROM tickets WHERE id=?", [$ticketId]);
        });
    }

    /**
     * Changer le statut opérationnel d'un billet (ex: emis→embarque, embarque→valide).
     */
    public function transition(int $ticketId, string $newStatus, int $userId): void
    {
        Database::transaction(function () use ($ticketId, $newStatus, $userId) {
            $ticket = Database::selectOne("SELECT * FROM tickets WHERE id=? FOR UPDATE", [$ticketId]);
            if (!$ticket) throw new \RuntimeException('Ticket introuvable.');

            TicketStateMachine::assertTransition($ticket['status'], $newStatus);

            Database::execute(
                "UPDATE tickets SET status=? WHERE id=?",
                [$newStatus, $ticketId]
            );

            AuditLog::record('ticket.transition', 'ticket', $ticketId, [
                'from' => $ticket['status'],
                'to'   => $newStatus,
            ]);
        });
    }

    public function reprint(int $ticketId): string
    {
        $ticket = Database::selectOne("SELECT * FROM tickets WHERE id=?", [$ticketId]);
        if (!$ticket) throw new \RuntimeException('Ticket introuvable.');

        Database::execute("UPDATE tickets SET printed_count = printed_count + 1 WHERE id=?", [$ticketId]);
        $path = $this->pdf->generateTicket($ticket);
        AuditLog::record('ticket.reprint', 'ticket', $ticketId);
        return BASE_PATH . '/storage/' . $path;
    }
}
