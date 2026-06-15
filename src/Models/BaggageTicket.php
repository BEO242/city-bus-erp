<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class BaggageTicket extends BaseModel
{
    protected static string $table = 'baggage_tickets';

    /**
     * Génère le prochain numéro de billet bagage (BGG-YYYY-NNNNN).
     * Utilise la table baggage_ticket_sequences avec verrou FOR UPDATE.
     */
    public static function nextNumber(): string
    {
        $year = (int)date('Y');

        Database::execute(
            "INSERT INTO baggage_ticket_sequences (year, last_seq)
             VALUES (?, 1)
             ON DUPLICATE KEY UPDATE last_seq = last_seq + 1",
            [$year]
        );

        $row = Database::selectOne(
            "SELECT last_seq FROM baggage_ticket_sequences WHERE year = ?",
            [$year]
        );

        return sprintf('BGG-%d-%05d', $year, (int)$row['last_seq']);
    }

    /**
     * Récupère un billet bagage avec toutes ses relations (trip, line, nature, vendeur).
     */
    public static function findWithRelations(int $id): ?array
    {
        return Database::selectOne(
            "SELECT bt.*,
                    tr.trip_date, tr.departure_scheduled, tr.status AS trip_status,
                    l.name AS line_name, l.code AS line_code,
                    bn.label AS nature_label, bn.icon AS nature_icon, bn.color_class AS nature_color,
                    u.first_name AS sold_by_first, u.last_name AS sold_by_last,
                    pt.ticket_number AS passenger_ticket_number, pt.seat_number AS passenger_seat
             FROM baggage_tickets bt
             JOIN trips tr ON tr.id = bt.trip_id
             JOIN bus_lines l ON l.id = bt.line_id
             JOIN tariff_baggage_natures bn ON bn.id = bt.baggage_nature_id
             JOIN users u ON u.id = bt.sold_by
             LEFT JOIN tickets pt ON pt.id = bt.passenger_ticket_id
             WHERE bt.id = ? AND bt.deleted_at IS NULL",
            [$id]
        );
    }

    /**
     * Liste paginée avec filtres.
     */
    public static function listPaginated(
        int $page,
        int $perPage,
        string $q = '',
        int $tripId = 0,
        int $lineId = 0,
        string $status = '',
        int $soldBy = 0
    ): array {
        $where  = ['bt.deleted_at IS NULL'];
        $params = [];

        if ($q !== '') {
            $where[]  = '(bt.ticket_number LIKE ? OR bt.passenger_name LIKE ? OR bt.passenger_phone LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        if ($tripId > 0) {
            $where[]  = 'bt.trip_id = ?';
            $params[] = $tripId;
        }
        if ($lineId > 0) {
            $where[]  = 'bt.line_id = ?';
            $params[] = $lineId;
        }
        if ($status !== '') {
            $where[]  = 'bt.status = ?';
            $params[] = $status;
        }
        if ($soldBy > 0) {
            $where[]  = 'bt.sold_by = ?';
            $params[] = $soldBy;
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $total = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM baggage_tickets bt WHERE $whereClause",
            $params
        )['c'] ?? 0);

        $rows = Database::select(
            "SELECT bt.*,
                    tr.trip_date, tr.departure_scheduled,
                    l.name AS line_name, l.code AS line_code,
                    bn.label AS nature_label, bn.icon AS nature_icon, bn.color_class AS nature_color,
                    u.first_name AS sold_by_first, u.last_name AS sold_by_last
             FROM baggage_tickets bt
             JOIN trips tr ON tr.id = bt.trip_id
             JOIN bus_lines l ON l.id = bt.line_id
             JOIN tariff_baggage_natures bn ON bn.id = bt.baggage_nature_id
             JOIN users u ON u.id = bt.sold_by
             WHERE $whereClause
             ORDER BY bt.sold_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return [
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'lastPage'  => max(1, (int)ceil($total / $perPage)),
        ];
    }
}
