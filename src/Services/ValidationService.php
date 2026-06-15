<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

final class ValidationService
{
    /** Valide un QR code à un poste de contrôle. */
    public function validate(string $qrCode, int $checkpointId, int $userId, ?string $deviceId = null): array
    {
        $ticket = Database::selectOne("SELECT * FROM tickets WHERE qr_code=? LIMIT 1", [$qrCode]);
        if (!$ticket) {
            return ['status' => 'invalide', 'message' => 'Ticket inconnu.'];
        }
        if ($ticket['status'] === 'annule') {
            return ['status' => 'invalide', 'message' => 'Ticket annulé.'];
        }

        // Expiration paramétrable (0 = jamais)
        $expH = max(0, Setting::getInt('billetterie.ticket_expiration_h', 0));
        if ($expH > 0 && !empty($ticket['sold_at'])) {
            $soldTs = strtotime((string)$ticket['sold_at']);
            if ($soldTs !== false && (time() - $soldTs) > $expH * 3600) {
                return ['status' => 'invalide', 'message' => "Ticket expiré (validité {$expH} h)."];
            }
        }

        // Doublon ?
        $already = Database::selectOne(
            "SELECT * FROM validations WHERE ticket_id=? AND checkpoint_id=? LIMIT 1",
            [$ticket['id'], $checkpointId]
        );
        if ($already) {
            return [
                'status'  => 'double',
                'message' => 'Déjà scanné à ce poste à ' . date('H:i', strtotime($already['validated_at'])),
                'ticket'  => $ticket,
            ];
        }

        Database::insert(
            "INSERT INTO validations (ticket_id, checkpoint_id, validated_by, validated_at, device_id, is_synced, sync_at)
             VALUES (?,?,?, NOW(), ?, TRUE, NOW())",
            [$ticket['id'], $checkpointId, $userId, $deviceId]
        );
        Database::execute("UPDATE tickets SET status='valide' WHERE id=? AND status='emis'", [$ticket['id']]);

        AuditLog::record('controle.validate', 'ticket', (int)$ticket['id'], [
            'checkpoint_id' => $checkpointId,
        ]);

        return ['status' => 'valide', 'message' => 'Ticket validé.', 'ticket' => $ticket];
    }

    /**
     * Sync de validations effectuées hors ligne.
     * Chaque item est traité dans une transaction indépendante : un échec
     * isolé ne casse pas le reste du batch (idempotent).
     * @param array $items [['qr_code','validated_at','checkpoint_id','device_id']]
     */
    public function syncBatch(array $items, int $userId): array
    {
        $results = [];
        foreach ($items as $it) {
            try {
                $result = Database::transaction(function () use ($it, $userId) {
                    $ticket = Database::selectOne(
                        "SELECT id FROM tickets WHERE qr_code=? FOR UPDATE",
                        [$it['qr_code']]
                    );
                    if (!$ticket) {
                        return ['qr_code' => $it['qr_code'], 'status' => 'invalide'];
                    }
                    $exists = Database::selectOne(
                        "SELECT id FROM validations WHERE ticket_id=? AND checkpoint_id=?",
                        [$ticket['id'], $it['checkpoint_id']]
                    );
                    if ($exists) {
                        return ['qr_code' => $it['qr_code'], 'status' => 'duplicate'];
                    }
                    Database::insert(
                        "INSERT INTO validations (ticket_id, checkpoint_id, validated_by, validated_at, device_id, is_synced, sync_at)
                         VALUES (?,?,?,?,?,TRUE, NOW())",
                        [$ticket['id'], $it['checkpoint_id'], $userId, $it['validated_at'], $it['device_id'] ?? null]
                    );
                    Database::execute(
                        "UPDATE tickets SET status='valide' WHERE id=? AND status='emis'",
                        [$ticket['id']]
                    );
                    AuditLog::record('controle.sync', 'ticket', (int)$ticket['id'], [
                        'checkpoint_id' => $it['checkpoint_id'],
                        'offline_at'    => $it['validated_at'] ?? null,
                    ]);
                    return ['qr_code' => $it['qr_code'], 'status' => 'synced'];
                });
                $results[] = $result;
            } catch (\Throwable $e) {
                $results[] = [
                    'qr_code' => $it['qr_code'] ?? null,
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }
        return $results;
    }

    public function tripCache(int $tripId): array
    {
        $tickets = Database::select(
            "SELECT id, ticket_number, qr_code, passenger_name, seat_number, status, ticket_type
             FROM tickets WHERE trip_id=? AND status IN ('emis','valide') AND deleted_at IS NULL",
            [$tripId]
        );
        return [
            'trip_id' => $tripId,
            'cached_at' => date('c'),
            'tickets' => $tickets,
        ];
    }
}
