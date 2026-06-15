<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

/**
 * Gestion des réservations (PNR) — workflow hold → confirm → paid → converted.
 *
 * Le hold bloque le siège pendant N minutes. Si non confirmé, libération auto
 * (cron `php bin/expire-reservations.php`).
 */
final class ReservationService
{
    /**
     * Crée une réservation en statut "hold".
     *
     * @param array{
     *   contact_name:string, contact_phone:string, contact_email?:string,
     *   channel?:string, items:array<int,array>
     * } $data
     */
    public function hold(array $data, ?int $userId = null): array
    {
        if (empty($data['items'])) {
            throw new \InvalidArgumentException('Aucun passager fourni.');
        }
        $totalAmount = array_sum(array_map(fn($i) => (int)($i['price_fcfa'] ?? 0), $data['items']));

        $pnr = $this->generatePnr();
        $holdMinutes = max(5, Setting::getInt('reservation.hold_default_minutes', 60));
        $expiresAt = date('Y-m-d H:i:s', time() + $holdMinutes * 60);

        // Trouver / créer le client CRM
        $customerId = (new CustomerService())->findOrCreateFromTicket([
            'name'  => $data['contact_name'],
            'phone' => $data['contact_phone'],
            'email' => $data['contact_email'] ?? null,
        ]);

        $reservationId = (int)Database::insert(
            "INSERT INTO reservations
                (pnr_code, customer_id, contact_name, contact_phone, contact_email,
                 channel, partner_id, corporate_id,
                 total_amount_fcfa, status, hold_expires_at, created_by, agency_id)
             VALUES (?,?,?,?,?,?,?,?,?, 'hold', ?, ?, ?)",
            [
                $pnr, $customerId,
                $data['contact_name'], $data['contact_phone'], $data['contact_email'] ?? null,
                $data['channel'] ?? 'counter',
                $data['partner_id']   ?? null,
                $data['corporate_id'] ?? null,
                $totalAmount,
                $expiresAt,
                $userId,
                $data['agency_id'] ?? null,
            ]
        );

        foreach ($data['items'] as $item) {
            Database::insert(
                "INSERT INTO reservation_items
                    (reservation_id, trip_id, seat_number, passenger_name, passenger_phone,
                     passenger_category, travel_class, boarding_stop_id, alighting_stop_id, price_fcfa)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $reservationId,
                    (int)$item['trip_id'],
                    $item['seat_number'] ?? null,
                    $item['passenger_name'],
                    $item['passenger_phone'] ?? null,
                    $item['passenger_category'] ?? 'adulte',
                    $item['travel_class'] ?? 'standard',
                    $item['boarding_stop_id']  ?? null,
                    $item['alighting_stop_id'] ?? null,
                    (int)($item['price_fcfa'] ?? 0),
                ]
            );
        }

        AuditLog::record('reservation.hold', 'reservation', $reservationId, [
            'pnr' => $pnr, 'amount' => $totalAmount, 'items' => count($data['items']),
        ]);
        WebhookService::dispatch('reservation.created', [
            'pnr' => $pnr, 'reservation_id' => $reservationId, 'amount' => $totalAmount,
        ]);

        // SMS de confirmation
        try {
            $msg = sprintf(
                "CITY BUS · Réservation %s confirmée. Total %s FCFA. Validité jusqu'au %s.",
                $pnr,
                number_format($totalAmount, 0, ',', ' '),
                date('d/m H:i', strtotime($expiresAt))
            );
            SmsService::send((string)$data['contact_phone'], $msg);
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::warning('reservation.sms_failed: ' . $e->getMessage());
        }

        return $this->loadByPnr($pnr) ?? throw new \RuntimeException('Réservation introuvable après création.');
    }

    /** Confirme une réservation (sans paiement immédiat). */
    public function confirm(string $pnr, int $userId): void
    {
        $r = $this->loadByPnr($pnr);
        if (!$r) throw new \RuntimeException("PNR $pnr introuvable.");
        if (!in_array($r['status'], ['hold','partially_paid'], true)) {
            throw new \RuntimeException("Statut {$r['status']} non confirmable.");
        }
        Database::execute(
            "UPDATE reservations SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?",
            [(int)$r['id']]
        );
        AuditLog::record('reservation.confirm', 'reservation', (int)$r['id'], ['pnr' => $pnr]);
    }

    /**
     * Convertit une réservation en tickets émis (paiement complet).
     * Retourne la liste des ticket_id créés.
     */
    public function convertToTickets(string $pnr, string $paymentMethod, int $userId, ?int $cashRegisterId = null): array
    {
        $r = $this->loadByPnr($pnr);
        if (!$r) throw new \RuntimeException("PNR $pnr introuvable.");
        if ($r['status'] === 'cancelled' || $r['status'] === 'expired') {
            throw new \RuntimeException("Réservation {$r['status']}.");
        }

        $items = Database::select("SELECT * FROM reservation_items WHERE reservation_id = ?", [(int)$r['id']]);
        if (!$items) throw new \RuntimeException('Aucun passager dans cette réservation.');

        $ticketSvc = new TicketService();
        $ticketIds = [];

        foreach ($items as $item) {
            if (!empty($item['converted_ticket_id'])) {
                $ticketIds[] = (int)$item['converted_ticket_id'];
                continue;
            }
            $ticket = $ticketSvc->create([
                'trip_id'           => (int)$item['trip_id'],
                'ticket_type'       => 'finale',
                'passenger_category'=> $item['passenger_category'],
                'travel_class'      => $item['travel_class'],
                'passenger_name'    => $item['passenger_name'],
                'passenger_phone'   => $item['passenger_phone'] ?? $r['contact_phone'],
                'seat_number'       => $item['seat_number'],
                'boarding_stop_id'  => $item['boarding_stop_id'],
                'alighting_stop_id' => $item['alighting_stop_id'],
                'price_fcfa'        => (int)$item['price_fcfa'],
                'sold_by'           => $userId,
                'agency_id'         => (int)$r['agency_id'],
                'cash_register_id'  => $cashRegisterId,
                'payment_method'    => $paymentMethod,
            ]);
            Database::execute(
                "UPDATE reservation_items SET converted_ticket_id = ? WHERE id = ?",
                [(int)$ticket['id'], (int)$item['id']]
            );
            $ticketIds[] = (int)$ticket['id'];
        }

        Database::execute(
            "UPDATE reservations
                SET status = 'paid', paid_amount_fcfa = total_amount_fcfa,
                    confirmed_at = COALESCE(confirmed_at, NOW())
              WHERE id = ?",
            [(int)$r['id']]
        );
        // Statut final converted (audit)
        Database::execute("UPDATE reservations SET status = 'converted' WHERE id = ?", [(int)$r['id']]);

        AuditLog::record('reservation.convert', 'reservation', (int)$r['id'], [
            'pnr' => $pnr, 'tickets' => count($ticketIds),
        ]);
        WebhookService::dispatch('reservation.converted', [
            'pnr' => $pnr, 'tickets' => $ticketIds,
        ]);

        return $ticketIds;
    }

    public function cancel(string $pnr, string $reason, int $userId): void
    {
        $r = $this->loadByPnr($pnr);
        if (!$r) throw new \RuntimeException("PNR $pnr introuvable.");
        if ($r['status'] === 'converted') throw new \RuntimeException('Réservation déjà convertie.');

        Database::execute(
            "UPDATE reservations
                SET status = 'cancelled', cancelled_at = NOW(), cancel_reason = ?
              WHERE id = ?",
            [$reason, (int)$r['id']]
        );
        AuditLog::record('reservation.cancel', 'reservation', (int)$r['id'], ['pnr' => $pnr, 'reason' => $reason]);
    }

    /** Job de purge automatique des holds expirés. */
    public function expireHolds(): int
    {
        $expired = Database::select(
            "SELECT id, pnr_code FROM reservations
              WHERE status = 'hold' AND hold_expires_at < NOW()"
        );
        foreach ($expired as $r) {
            Database::execute("UPDATE reservations SET status = 'expired' WHERE id = ?", [(int)$r['id']]);
            AuditLog::record('reservation.expire', 'reservation', (int)$r['id'], ['pnr' => $r['pnr_code']]);
        }
        return count($expired);
    }

    public function loadByPnr(string $pnr): ?array
    {
        return Database::selectOne(
            "SELECT * FROM reservations WHERE pnr_code = ? LIMIT 1",
            [strtoupper($pnr)]
        );
    }

    public function items(int $reservationId): array
    {
        return Database::select(
            "SELECT ri.*, tr.trip_code, tr.trip_date, l.code AS line_code, l.name AS line_name
             FROM reservation_items ri
             JOIN trips tr ON tr.id = ri.trip_id
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             WHERE ri.reservation_id = ?
             ORDER BY ri.id", [$reservationId]
        );
    }

    private function generatePnr(): string
    {
        $format = Setting::getString('reservation.pnr_format', '6char');
        $len = $format === '8char' ? 8 : 6;
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < $len; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $exists = Database::selectOne("SELECT id FROM reservations WHERE pnr_code = ?", [$code]);
        } while ($exists);
        return $code;
    }
}
