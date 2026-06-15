<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * Irregular Operations management.
 * Ouverture / résolution / fermeture d'événements + workflow rebooking.
 */
final class IropService
{
    public function open(int $tripId, string $type, string $reason, array $opts = []): int
    {
        $allowed = ['cancellation','major_delay','equipment_swap','route_diversion','incident','strike','weather'];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException("Type IROP invalide: $type");
        }

        // Compter passagers impactés (tickets actifs)
        $impactPax = (int)Database::scalar(
            "SELECT COUNT(*) FROM tickets WHERE trip_id = ? AND status IN ('valide','active','vendu','reserve')",
            [$tripId]
        );

        return Database::insert('irop_events', [
            'trip_id'       => $tripId,
            'irop_type'     => $type,
            'severity'      => $opts['severity'] ?? 'medium',
            'reason'        => $reason,
            'impact_pax'    => $impactPax,
            'delay_minutes' => (int)($opts['delay_minutes'] ?? 0),
            'replacement_trip_id' => $opts['replacement_trip_id'] ?? null,
            'opened_by'     => $opts['opened_by'] ?? null,
            'notes'         => $opts['notes'] ?? null,
        ]);
    }

    public function resolve(int $iropId, ?int $actorId = null, ?string $notes = null): void
    {
        Database::update('irop_events',
            [
                'status' => 'resolved',
                'resolved_at' => date('Y-m-d H:i:s'),
                'notes' => $notes,
            ],
            'id = ? AND status NOT IN (?, ?)', [$iropId, 'closed', 'resolved']
        );
    }

    public function close(int $iropId, ?int $actorId = null): void
    {
        Database::update('irop_events',
            [
                'status' => 'closed',
                'closed_at' => date('Y-m-d H:i:s'),
                'closed_by' => $actorId,
            ],
            'id = ?', [$iropId]
        );
    }

    /** Initialise les rebooking requests pour tous les tickets impactés. */
    public function initRebooking(int $iropId): int
    {
        $irop = Database::selectOne("SELECT * FROM irop_events WHERE id = ?", [$iropId]);
        if (!$irop) throw new \RuntimeException('IROP introuvable');

        $tickets = Database::select(
            "SELECT t.id, t.pnr, t.price, t.passenger_phone, t.passenger_email
             FROM tickets t
             WHERE t.trip_id = ? AND t.status IN ('valide','active','vendu','reserve')",
            [$irop['trip_id']]
        );

        $compensationPct = (int)Setting::get('irop.compensation_pct', 20);
        $delayThreshold  = (int)Setting::get('irop.compensation_delay_min', 60);
        $needsComp = $irop['irop_type'] === 'cancellation'
                     || (int)$irop['delay_minutes'] >= $delayThreshold;

        $count = 0;
        foreach ($tickets as $t) {
            $compensation = $needsComp ? (int)round((int)$t['price'] * $compensationPct / 100) : 0;
            $refund = $irop['irop_type'] === 'cancellation' ? (int)$t['price'] : 0;

            // Évite doublons
            $exists = Database::selectOne(
                "SELECT id FROM rebooking_requests WHERE irop_id = ? AND original_ticket_id = ?",
                [$iropId, $t['id']]
            );
            if ($exists) continue;

            Database::insert('rebooking_requests', [
                'irop_id'           => $iropId,
                'original_ticket_id'=> $t['id'],
                'original_pnr'      => $t['pnr'] ?? null,
                'refund_amount'     => $refund,
                'compensation'      => $compensation,
                'customer_phone'    => $t['passenger_phone'] ?? null,
                'customer_email'    => $t['passenger_email'] ?? null,
            ]);
            $count++;
        }

        Database::update('irop_events',
            ['status' => 'rebooking'],
            'id = ?', [$iropId]
        );

        return $count;
    }

    public function rebookTo(int $rebookingId, int $newTripId, ?int $actorId = null): void
    {
        $req = Database::selectOne("SELECT * FROM rebooking_requests WHERE id = ?", [$rebookingId]);
        if (!$req) throw new \RuntimeException('Rebooking request introuvable');
        if ($req['status'] === 'rebooked') return;

        Database::update('rebooking_requests', [
            'status'      => 'rebooked',
            'new_trip_id' => $newTripId,
            'resolved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$rebookingId]);
    }

    public function refund(int $rebookingId, ?int $actorId = null): void
    {
        Database::update('rebooking_requests', [
            'status'      => 'refunded',
            'resolved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$rebookingId]);
    }

    public function listOpen(): array
    {
        return Database::select(
            "SELECT i.*, tr.trip_code, tr.trip_date, l.code AS line_code,
                    cd.name AS departure_city, ca.name AS arrival_city
             FROM irop_events i
             JOIN trips tr ON tr.id = i.trip_id
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             WHERE i.status IN ('open','rebooking')
             ORDER BY i.severity = 'critical' DESC, i.opened_at DESC"
        );
    }

    public function get(int $iropId): ?array
    {
        return Database::selectOne(
            "SELECT i.*, tr.trip_code, tr.trip_date,
                    CONCAT(u.first_name,' ',u.last_name) AS opened_by_name
             FROM irop_events i
             JOIN trips tr ON tr.id = i.trip_id
             LEFT JOIN users u ON u.id = i.opened_by
             WHERE i.id = ?", [$iropId]
        );
    }

    public function rebookings(int $iropId): array
    {
        return Database::select(
            "SELECT r.*, t.passenger_name AS pax_name, tr.trip_code AS new_trip_code
             FROM rebooking_requests r
             LEFT JOIN tickets t ON t.id = r.original_ticket_id
             LEFT JOIN trips tr ON tr.id = r.new_trip_id
             WHERE r.irop_id = ?
             ORDER BY r.id DESC", [$iropId]
        );
    }
}
