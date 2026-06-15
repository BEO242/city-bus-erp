<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

/**
 * Liste d'attente : inscription quand un voyage est plein, notification
 * automatique au premier de la liste lors d'une libération de siège.
 */
final class WaitlistService
{
    public function add(int $tripId, array $passenger, ?int $customerId = null): int
    {
        $position = (int)(Database::selectOne(
            "SELECT COALESCE(MAX(position), 0) + 1 AS p FROM waitlist_entries
              WHERE trip_id = ? AND status IN ('waiting','notified')",
            [$tripId]
        )['p'] ?? 1);

        return (int)Database::insert(
            "INSERT INTO waitlist_entries
                (trip_id, customer_id, passenger_name, passenger_phone, seats_requested,
                 position, status, requested_at)
             VALUES (?,?,?,?,?,?, 'waiting', NOW())",
            [
                $tripId, $customerId,
                $passenger['name'], $passenger['phone'],
                (int)($passenger['seats'] ?? 1),
                $position,
            ]
        );
    }

    /**
     * Notifie le 1er passager en attente quand un siège se libère.
     * Le passager a X minutes pour confirmer (paramètre).
     */
    public function notifyNext(int $tripId): ?array
    {
        $entry = Database::selectOne(
            "SELECT * FROM waitlist_entries
              WHERE trip_id = ? AND status = 'waiting'
              ORDER BY position ASC LIMIT 1",
            [$tripId]
        );
        if (!$entry) return null;

        $delay = max(5, Setting::getInt('waitlist.confirmation_minutes', 30));
        $deadline = date('Y-m-d H:i:s', time() + $delay * 60);

        Database::execute(
            "UPDATE waitlist_entries
                SET status = 'notified', notified_at = NOW(), confirmation_deadline = ?
              WHERE id = ?",
            [$deadline, (int)$entry['id']]
        );
        AuditLog::record('waitlist.notify', 'waitlist', (int)$entry['id'], [
            'trip_id' => $tripId, 'phone' => $entry['passenger_phone'],
        ]);

        $msg = sprintf(
            "CITY BUS · Une place se libère sur votre voyage. Confirmez votre achat avant %s. Réf : %d",
            date('H:i', strtotime($deadline)),
            (int)$entry['id']
        );
        try {
            SmsService::send((string)$entry['passenger_phone'], $msg);
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::warning('waitlist.notify_failed: ' . $e->getMessage());
        }
        return $entry;
    }

    /** Job : passe les notifications expirées au suivant. */
    public function processExpired(): int
    {
        $expired = Database::select(
            "SELECT id, trip_id FROM waitlist_entries
              WHERE status = 'notified' AND confirmation_deadline < NOW()"
        );
        $count = 0;
        foreach ($expired as $e) {
            Database::execute("UPDATE waitlist_entries SET status = 'expired' WHERE id = ?", [(int)$e['id']]);
            $this->notifyNext((int)$e['trip_id']);
            $count++;
        }
        return $count;
    }

    public function listForTrip(int $tripId): array
    {
        return Database::select(
            "SELECT * FROM waitlist_entries
              WHERE trip_id = ? ORDER BY position ASC", [$tripId]
        );
    }

    public function cancel(int $entryId): void
    {
        Database::execute("UPDATE waitlist_entries SET status = 'cancelled' WHERE id = ?", [$entryId]);
    }
}
