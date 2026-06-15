<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Core\StructuredLogger;
use CityBus\Core\Auth;
use CityBus\Models\AuditLog;

/**
 * PNR multi-segments + multi-passagers.
 *
 * Workflow type :
 *   1. createHeader() -> PNR vide en hold
 *   2. addPassenger()
 *   3. addSegment() (autant que de tronçons)
 *   4. price() -> calcul total (recalc à chaque modif)
 *   5. confirm() -> hold -> confirmed (siège bloqué)
 *   6. ticket() -> émet billets, status=ticketed
 *   7. cancel() / refund() / modify()
 */
final class PnrService
{
    private OdFareService $od;
    public function __construct() { $this->od = new OdFareService(); }

    public function createHeader(array $contact): int
    {
        $pnr = $this->generatePnrCode();
        $holdMinutes = Setting::getInt('reservation.hold_default_minutes', 60);

        $id = Database::insert('reservations', [
            'pnr_code'        => $pnr,
            'customer_id'     => $contact['customer_id'] ?? null,
            'contact_name'    => $contact['contact_name'] ?? '',
            'contact_phone'   => $contact['contact_phone'] ?? '',
            'contact_email'   => $contact['contact_email'] ?? null,
            'channel'         => $contact['channel'] ?? 'counter',
            'partner_id'      => $contact['partner_id'] ?? null,
            'corporate_id'    => $contact['corporate_id'] ?? null,
            'agency_id'       => $contact['agency_id'] ?? null,
            'created_by'      => Auth::id(),
            'status'          => 'hold',
            'issue_status'    => 'held',
            'hold_expires_at' => date('Y-m-d H:i:s', time() + $holdMinutes * 60),
            'total_segments'  => 0,
            'total_pax'       => 0,
        ]);

        AuditLog::record('pnr.create', 'reservation', $id, ['pnr' => $pnr]);
        StructuredLogger::info('pnr.created', ['pnr' => $pnr, 'id' => $id], 'pnr');
        return $id;
    }

    public function addPassenger(int $pnrId, array $pax): int
    {
        $count = (int)Database::scalar("SELECT COUNT(*) FROM pnr_passengers WHERE reservation_id = ?", [$pnrId]);
        $id = Database::insert('pnr_passengers', [
            'reservation_id' => $pnrId,
            'customer_id'    => $pax['customer_id'] ?? null,
            'title'          => $pax['title'] ?? 'M',
            'first_name'     => $pax['first_name'] ?? '',
            'last_name'      => $pax['last_name'] ?? '',
            'dob'            => $pax['dob'] ?? null,
            'pax_type'       => $pax['pax_type'] ?? 'ADT',
            'document_type'  => $pax['document_type'] ?? 'cni',
            'document_number'=> $pax['document_number'] ?? null,
            'nationality'    => $pax['nationality'] ?? 'COG',
            'phone'          => $pax['phone'] ?? null,
            'email'          => $pax['email'] ?? null,
            'seat_preference'=> $pax['seat_preference'] ?? 'any',
            'special_request'=> $pax['special_request'] ?? null,
            'sequence'       => $count + 1,
        ]);
        Database::update('reservations', ['total_pax' => $count + 1], 'id = ?', [$pnrId]);
        return $id;
    }

    public function addSegment(int $pnrId, int $passengerId, array $segment): int
    {
        $tripId = (int)$segment['trip_id'];
        $boardId = (int)$segment['boarding_stop_id'];
        $alightId = (int)$segment['alighting_stop_id'];
        $class = $segment['booking_class'] ?? 'M';
        $paxType = $segment['pax_type'] ?? 'ADT';

        $trip = Database::selectOne("SELECT line_id FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) throw new \RuntimeException("Voyage #$tripId introuvable");

        // Lookup OD fare
        $od = $this->od->lookup((int)$trip['line_id'], $boardId, $alightId, $class, $paxType);
        $price = $od ? (int)$od['base_price_fcfa'] : $this->od->priceFor((int)$trip['line_id'], $boardId, $alightId, $class, $paxType);

        $count = (int)Database::scalar("SELECT COUNT(*) FROM reservation_items WHERE reservation_id = ?", [$pnrId]);
        $maxSeg = Setting::getInt('pnr.max_segments', 4);
        if ($count >= $maxSeg) throw new \RuntimeException("Max $maxSeg segments par PNR");

        $segId = Database::insert('reservation_items', [
            'reservation_id'   => $pnrId,
            'sequence'         => $count + 1,
            'pnr_passenger_id' => $passengerId,
            'trip_id'          => $tripId,
            'boarding_stop_id' => $boardId,
            'alighting_stop_id'=> $alightId,
            'passenger_name'   => $segment['passenger_name'] ?? '',
            'passenger_phone'  => $segment['passenger_phone'] ?? '',
            'passenger_category'=> $segment['passenger_category'] ?? 'adulte',
            'travel_class'     => $segment['travel_class'] ?? 'standard',
            'booking_class'    => $class,
            'pax_type'         => $paxType,
            'fare_basis'       => $od['fare_basis'] ?? 'STD',
            'od_fare_id'       => $od['id'] ?? null,
            'seat_number'      => $segment['seat_number'] ?? null,
            'price_fcfa'       => $price,
            'segment_status'   => 'booked',
        ]);

        $this->recomputeTotals($pnrId);

        // Détecte connexion auto si segment sequence > 1
        if ($count > 0) {
            $this->detectConnection($pnrId, $count, $count + 1);
        }
        return $segId;
    }

    public function price(int $pnrId): array
    {
        $segs = Database::select(
            "SELECT id, price_fcfa, booking_class FROM reservation_items WHERE reservation_id = ?",
            [$pnrId]
        );
        $total = array_sum(array_column($segs, 'price_fcfa'));
        return [
            'segments_count' => count($segs),
            'total_fcfa' => (int)$total,
            'segments' => $segs,
        ];
    }

    public function confirm(int $pnrId): void
    {
        $pnr = Database::selectOne("SELECT * FROM reservations WHERE id = ?", [$pnrId]);
        if (!$pnr) throw new \RuntimeException("PNR introuvable");
        if ($pnr['status'] !== 'hold') return;

        Database::update('reservations',
            ['status' => 'confirmed', 'confirmed_at' => date('Y-m-d H:i:s')],
            'id = ?', [$pnrId]
        );
        Database::update('reservation_items',
            ['segment_status' => 'confirmed'],
            'reservation_id = ?', [$pnrId]
        );

        AuditLog::record('pnr.confirm', 'reservation', $pnrId, []);
    }

    public function ticket(int $pnrId, int $paymentId): array
    {
        $pnr = Database::selectOne("SELECT * FROM reservations WHERE id = ?", [$pnrId]);
        if (!$pnr) throw new \RuntimeException("PNR introuvable");

        $segs = Database::select(
            "SELECT ri.*, p.first_name, p.last_name, p.phone, p.document_type, p.document_number
             FROM reservation_items ri
             LEFT JOIN pnr_passengers p ON p.id = ri.pnr_passenger_id
             WHERE ri.reservation_id = ?", [$pnrId]
        );

        $ticketIds = [];
        foreach ($segs as $s) {
            if ($s['converted_ticket_id']) { $ticketIds[] = $s['converted_ticket_id']; continue; }
            $ticketCode = 'TKT-' . strtoupper(bin2hex(random_bytes(4)));
            $tid = Database::insert('tickets', [
                'ticket_code'      => $ticketCode,
                'trip_id'          => $s['trip_id'],
                'passenger_name'   => trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?: $s['passenger_name'],
                'passenger_phone'  => $s['phone'] ?? $s['passenger_phone'],
                'seat_number'      => $s['seat_number'],
                'price'            => $s['price_fcfa'],
                'status'           => 'valide',
                'sale_channel'     => $pnr['channel'],
                'pnr_code'         => $pnr['pnr_code'],
                'agency_id'        => $pnr['agency_id'],
                'sold_by'          => Auth::id(),
                'sold_at'          => date('Y-m-d H:i:s'),
                'boarding_stop_id' => $s['boarding_stop_id'],
                'alighting_stop_id'=> $s['alighting_stop_id'],
                'payment_id'       => $paymentId,
            ]);
            Database::update('reservation_items',
                ['converted_ticket_id' => $tid, 'segment_status' => 'confirmed'],
                'id = ?', [$s['id']]
            );
            $ticketIds[] = $tid;
        }

        Database::update('reservations',
            ['status' => 'paid', 'issue_status' => 'ticketed'],
            'id = ?', [$pnrId]
        );
        AuditLog::record('pnr.ticket', 'reservation', $pnrId, ['tickets' => $ticketIds]);
        return $ticketIds;
    }

    public function cancel(int $pnrId, string $reason): void
    {
        Database::update('reservations', [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancel_reason' => $reason,
        ], 'id = ?', [$pnrId]);
        Database::update('reservation_items',
            ['segment_status' => 'cancelled'],
            'reservation_id = ?', [$pnrId]
        );
        // Annule les billets émis
        Database::execute(
            "UPDATE tickets SET status = 'annule' WHERE pnr_code = (SELECT pnr_code FROM reservations WHERE id = ?)",
            [$pnrId]
        );
        AuditLog::record('pnr.cancel', 'reservation', $pnrId, ['reason' => $reason]);
    }

    public function findByCode(string $pnrCode): ?array
    {
        $pnr = Database::selectOne("SELECT * FROM reservations WHERE pnr_code = ?", [$pnrCode]);
        if (!$pnr) return null;
        $pnr['passengers'] = Database::select(
            "SELECT * FROM pnr_passengers WHERE reservation_id = ? ORDER BY sequence", [$pnr['id']]
        );
        $pnr['segments'] = Database::select(
            "SELECT ri.*, t.trip_code, t.trip_date, t.departure_time,
                    fs.name AS from_name, ts.name AS to_name
             FROM reservation_items ri
             JOIN trips t ON t.id = ri.trip_id
             LEFT JOIN stops fs ON fs.id = ri.boarding_stop_id
             LEFT JOIN stops ts ON ts.id = ri.alighting_stop_id
             WHERE ri.reservation_id = ? ORDER BY ri.sequence", [$pnr['id']]
        );
        $pnr['connections'] = Database::select(
            "SELECT * FROM pnr_connections WHERE reservation_id = ?", [$pnr['id']]
        );
        return $pnr;
    }

    public function expireHolds(): int
    {
        $expired = Database::select(
            "SELECT id FROM reservations WHERE status = 'hold' AND hold_expires_at < NOW()"
        );
        foreach ($expired as $r) {
            $this->cancel((int)$r['id'], 'Hold expiré automatiquement');
        }
        return count($expired);
    }

    private function recomputeTotals(int $pnrId): void
    {
        $total = (int)Database::scalar(
            "SELECT COALESCE(SUM(price_fcfa),0) FROM reservation_items WHERE reservation_id = ?", [$pnrId]
        );
        $segCount = (int)Database::scalar(
            "SELECT COUNT(*) FROM reservation_items WHERE reservation_id = ?", [$pnrId]
        );
        Database::update('reservations',
            ['total_amount_fcfa' => $total, 'total_segments' => $segCount],
            'id = ?', [$pnrId]
        );
    }

    private function detectConnection(int $pnrId, int $inboundSeq, int $outboundSeq): void
    {
        $segs = Database::select(
            "SELECT ri.id, ri.sequence, t.trip_date, t.departure_time, t.arrival_actual,
                    t.estimated_duration_minutes, l.estimated_duration_minutes AS line_dur
             FROM reservation_items ri
             JOIN trips t ON t.id = ri.trip_id
             JOIN bus_lines l ON l.id = t.line_id
             WHERE ri.reservation_id = ? AND ri.sequence IN (?, ?)
             ORDER BY ri.sequence",
            [$pnrId, $inboundSeq, $outboundSeq]
        );
        if (count($segs) !== 2) return;
        [$inbound, $outbound] = $segs;

        // Estime arrivée inbound = départ + durée
        $depIn = strtotime($inbound['trip_date'] . ' ' . $inbound['departure_time']);
        $arrIn = $depIn + ((int)($inbound['estimated_duration_minutes'] ?? $inbound['line_dur'] ?? 240) * 60);
        $depOut = strtotime($outbound['trip_date'] . ' ' . $outbound['departure_time']);
        $connMin = max(0, (int)(($depOut - $arrIn) / 60));

        $mct = Setting::getInt('pnr.min_connection_time_min', 30);
        if ($connMin < $mct) {
            StructuredLogger::warn('pnr.tight_connection', [
                'pnr_id' => $pnrId, 'connection_min' => $connMin, 'mct' => $mct
            ], 'pnr');
        }

        Database::execute(
            "INSERT INTO pnr_connections (reservation_id, inbound_segment_id, outbound_segment_id, connection_minutes, is_protected)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE connection_minutes = VALUES(connection_minutes)",
            [$pnrId, $inbound['id'], $outbound['id'], $connMin]
        );
    }

    private function generatePnrCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $exists = Database::scalar("SELECT COUNT(*) FROM reservations WHERE pnr_code = ?", [$code]);
        } while ($exists > 0);
        return $code;
    }
}
