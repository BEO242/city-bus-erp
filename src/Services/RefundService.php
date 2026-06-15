<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;

/**
 * Service centralisé pour les opérations de remboursement.
 * Les remboursements sont effectués via TicketService::refund() et FretService::refund(),
 * ce service est utilisé pour la consultation, les rapports et les approbations.
 */
final class RefundService
{
    /**
     * Lister les remboursements avec filtres.
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['refund_type'])) {
            $where[]  = 'r.refund_type = ?';
            $params[] = $filters['refund_type'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['agency_id'])) {
            $where[]  = 'r.agency_id = ?';
            $params[] = (int)$filters['agency_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'DATE(r.created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'DATE(r.created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $whereSql = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $total = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM refunds r WHERE {$whereSql}",
            $params
        )['c'] ?? 0);

        $rows = Database::select(
            "SELECT r.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS refunded_by_name,
                    a.name AS agency_name
               FROM refunds r
               LEFT JOIN users u ON u.id = r.refunded_by
               LEFT JOIN agencies a ON a.id = r.agency_id
              WHERE {$whereSql}
              ORDER BY r.created_at DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Totaux des remboursements par type et période.
     */
    public function totals(?string $from = null, ?string $to = null, ?int $agencyId = null): array
    {
        $where  = ["r.status = 'execute'"];
        $params = [];

        if ($from) { $where[] = 'DATE(r.created_at) >= ?'; $params[] = $from; }
        if ($to)   { $where[] = 'DATE(r.created_at) <= ?'; $params[] = $to; }
        if ($agencyId) { $where[] = 'r.agency_id = ?'; $params[] = $agencyId; }

        $whereSql = implode(' AND ', $where);

        return Database::select(
            "SELECT refund_type,
                    COUNT(*) AS count,
                    SUM(refund_amount_fcfa) AS total_amount,
                    AVG(refund_percent) AS avg_percent
               FROM refunds r
              WHERE {$whereSql}
              GROUP BY refund_type",
            $params
        );
    }

    /**
     * Résumé KPI des remboursements.
     */
    public function kpis(?int $agencyId = null): array
    {
        $where  = ["status = 'execute'"];
        $params = [];
        if ($agencyId) { $where[] = 'agency_id = ?'; $params[] = $agencyId; }
        $whereSql = implode(' AND ', $where);

        $today = Database::selectOne(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(refund_amount_fcfa), 0) AS total
               FROM refunds WHERE {$whereSql} AND DATE(created_at) = CURDATE()",
            $params
        ) ?: ['cnt' => 0, 'total' => 0];

        $month = Database::selectOne(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(refund_amount_fcfa), 0) AS total
               FROM refunds WHERE {$whereSql} AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())",
            $params
        ) ?: ['cnt' => 0, 'total' => 0];

        return [
            'today_count'  => (int)$today['cnt'],
            'today_amount' => (int)$today['total'],
            'month_count'  => (int)$month['cnt'],
            'month_amount' => (int)$month['total'],
        ];
    }
}
