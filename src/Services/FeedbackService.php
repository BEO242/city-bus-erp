<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

final class FeedbackService
{
    /** Crée la demande d'avis pour un ticket et envoie le SMS avec lien. */
    public function requestForTicket(int $ticketId): ?string
    {
        $t = Database::selectOne(
            "SELECT t.*, c.phone_norm AS customer_phone
             FROM tickets t
             LEFT JOIN customers c ON c.id = t.customer_id
             WHERE t.id = ?", [$ticketId]
        );
        if (!$t) return null;
        $phone = $t['customer_phone'] ?? $t['passenger_phone'] ?? null;
        if (!$phone) return null;

        // Évite les doublons
        $existing = Database::selectOne(
            "SELECT request_token FROM customer_feedback WHERE ticket_id = ? LIMIT 1",
            [$ticketId]
        );
        if ($existing) return $existing['request_token'];

        $token = bin2hex(random_bytes(16));
        Database::insert(
            "INSERT INTO customer_feedback
                (customer_id, ticket_id, trip_id, request_token, request_sent_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$t['customer_id'] ?? null, $ticketId, (int)$t['trip_id'], $token]
        );

        $url = url('feedback/' . $token);
        try {
            $tpl = (new NotificationService())->resolveTemplate('feedback.request', 'sms');
            $vars = [
                'feedback_url'    => $url,
                'trip_code'       => Database::selectOne("SELECT trip_code FROM trips WHERE id=?", [(int)$t['trip_id']])['trip_code'] ?? '',
                'passenger_name'  => $t['passenger_name'] ?? '',
            ];
            if ($tpl) {
                (new NotificationService())->sendFromTemplate('feedback.request', 'sms', $phone, $vars, [
                    'customer_id' => $t['customer_id'] ?? null,
                    'related_table' => 'tickets', 'related_id' => $ticketId,
                ]);
            } else {
                SmsService::send($phone, "CITY BUS · Notez votre voyage : $url");
            }
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::warning('feedback.sms_failed: ' . $e->getMessage());
        }

        return $token;
    }

    public function findByToken(string $token): ?array
    {
        return Database::selectOne(
            "SELECT cf.*, t.ticket_number, tr.trip_code, tr.trip_date, l.name AS line_name
             FROM customer_feedback cf
             LEFT JOIN tickets t ON t.id = cf.ticket_id
             LEFT JOIN trips tr ON tr.id = cf.trip_id
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             WHERE cf.request_token = ?", [$token]
        );
    }

    public function submit(string $token, array $data): bool
    {
        $row = $this->findByToken($token);
        if (!$row) return false;
        if ($row['submitted_at'] && $row['nps_score'] !== null) {
            // Déjà soumis
            return false;
        }
        Database::execute(
            "UPDATE customer_feedback
                SET nps_score = ?, rating_overall = ?, rating_punctuality = ?,
                    rating_comfort = ?, rating_driver = ?, rating_cleanliness = ?,
                    comment = ?, submitted_at = NOW()
              WHERE id = ?",
            [
                $data['nps_score'] ?? null,
                $data['rating_overall'] ?? null,
                $data['rating_punctuality'] ?? null,
                $data['rating_comfort'] ?? null,
                $data['rating_driver'] ?? null,
                $data['rating_cleanliness'] ?? null,
                $data['comment'] ?? null,
                (int)$row['id'],
            ]
        );
        return true;
    }

    public function summary(string $from, string $to): array
    {
        $row = Database::selectOne(
            "SELECT
                COUNT(*) AS responses,
                AVG(nps_score) AS avg_nps,
                AVG(rating_overall) AS avg_overall,
                AVG(rating_punctuality) AS avg_punctuality,
                AVG(rating_comfort) AS avg_comfort,
                AVG(rating_driver) AS avg_driver,
                AVG(rating_cleanliness) AS avg_cleanliness,
                SUM(CASE WHEN nps_score >= 9 THEN 1 ELSE 0 END) AS promoters,
                SUM(CASE WHEN nps_score BETWEEN 7 AND 8 THEN 1 ELSE 0 END) AS passives,
                SUM(CASE WHEN nps_score <= 6 THEN 1 ELSE 0 END) AS detractors
             FROM customer_feedback
             WHERE submitted_at IS NOT NULL AND DATE(submitted_at) BETWEEN ? AND ?",
            [$from, $to]
        );
        $total = max(1, (int)($row['responses'] ?? 0));
        $row['nps'] = round((((int)$row['promoters'] - (int)$row['detractors']) / $total) * 100, 1);
        return $row;
    }

    /** Auto-envoi des demandes d'avis pour les voyages clôturés (cron). */
    public function autoSendRequests(): int
    {
        if (!Setting::getBool('feedback.auto_request_enabled', true)) return 0;
        $hours = max(1, Setting::getInt('feedback.auto_request_after_hours', 24));

        $tickets = Database::select(
            "SELECT t.id FROM tickets t
             JOIN trips tr ON tr.id = t.trip_id
             WHERE tr.status = 'cloture'
               AND tr.updated_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
               AND tr.updated_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
               AND t.deleted_at IS NULL
               AND t.passenger_phone IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM customer_feedback cf WHERE cf.ticket_id = t.id)
             LIMIT 200",
            [$hours, $hours + 24]
        );
        foreach ($tickets as $t) {
            $this->requestForTicket((int)$t['id']);
        }
        return count($tickets);
    }
}
