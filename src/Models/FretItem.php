<?php

namespace CityBus\Models;

use CityBus\Core\Database;

final class FretItem extends BaseModel
{
    protected static string $table = 'fret_items';
    protected static bool $softDeletes = true;

    public const TYPES = ['baggage' => 'Bagage passager', 'colis' => 'Colis / Envoi'];

    public const STATUSES = [
        'enregistre' => 'Enregistré',
        'charge'     => 'Chargé',
        'en_transit' => 'En transit',
        'arrive'     => 'Arrivé',
        'retire'     => 'Retiré',
        'annule'     => 'Annulé',
    ];

    public const STATUS_COLORS = [
        'enregistre' => 'amber',
        'charge'     => 'blue',
        'en_transit' => 'indigo',
        'arrive'     => 'green',
        'retire'     => 'slate',
        'annule'     => 'red',
    ];

    public static function generateTrackingCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0,O,I,1
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $tracking = 'FRT-' . $code;
            $exists = Database::selectOne(
                "SELECT id FROM fret_items WHERE tracking_code = ?",
                [$tracking]
            );
        } while ($exists);

        return $tracking;
    }

    public static function findWithRelations(int $id): ?array
    {
        return Database::selectOne(
            "SELECT f.*,
                    fc.label AS category_label,
                    fc.color AS category_color,
                    fc.price_per_kg AS category_price_per_kg,
                    t.trip_date,
                    t.departure_scheduled,
                    bl.name AS line_name,
                    bl.code AS line_code,
                    tk.ticket_number AS passenger_ticket_number,
                    tk.passenger_name AS linked_passenger_name,
                    tk.seat_number AS linked_seat_number,
                    oa.name AS origin_agency_name,
                    da.name AS destination_agency_name,
                    os.name AS origin_stop_name,
                    ds.name AS destination_stop_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS registered_by_name
             FROM fret_items f
             LEFT JOIN fret_categories fc ON fc.slug = f.category_slug
             LEFT JOIN trips t ON t.id = f.trip_id
             LEFT JOIN bus_lines bl ON bl.id = t.line_id
             LEFT JOIN tickets tk ON tk.id = f.passenger_ticket_id
             LEFT JOIN agencies oa ON oa.id = f.origin_agency_id
             LEFT JOIN agencies da ON da.id = f.destination_agency_id
             LEFT JOIN stops os ON os.id = f.origin_stop_id
             LEFT JOIN stops ds ON ds.id = f.destination_stop_id
             LEFT JOIN users u ON u.id = f.registered_by
             WHERE f.deleted_at IS NULL AND f.id = ?",
            [$id]
        );
    }

    public static function listPaginated(array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['f.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = "(f.tracking_code LIKE ? OR f.sender_name LIKE ? OR f.sender_phone LIKE ? OR f.recipient_name LIKE ?)";
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        if (!empty($filters['item_type'])) {
            $where[] = "f.item_type = ?";
            $params[] = $filters['item_type'];
        }

        if (!empty($filters['status'])) {
            $where[] = "f.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['category_slug'])) {
            $where[] = "f.category_slug = ?";
            $params[] = $filters['category_slug'];
        }

        if (!empty($filters['trip_id'])) {
            $where[] = "f.trip_id = ?";
            $params[] = (int) $filters['trip_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "f.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "f.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $countRow = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM fret_items f
             WHERE {$whereClause}",
            $params
        );
        $total = (int) ($countRow['total'] ?? 0);

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT f.*,
                    fc.label AS category_label,
                    fc.color AS category_color,
                    t.trip_date,
                    bl.code AS line_code
             FROM fret_items f
             LEFT JOIN fret_categories fc ON fc.slug = f.category_slug
             LEFT JOIN trips t ON t.id = f.trip_id
             LEFT JOIN bus_lines bl ON bl.id = t.line_id
             WHERE {$whereClause}
             ORDER BY f.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'lastPage' => $lastPage,
        ];
    }

    public static function countByStatus(?int $tripId = null): array
    {
        $where = 'deleted_at IS NULL';
        $params = [];

        if ($tripId !== null) {
            $where .= ' AND trip_id = ?';
            $params[] = $tripId;
        }

        $rows = Database::select(
            "SELECT status, COUNT(*) AS cnt
             FROM fret_items
             WHERE {$where}
             GROUP BY status",
            $params
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
