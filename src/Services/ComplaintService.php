<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Auth;
use CityBus\Models\AuditLog;

final class ComplaintService
{
    public function open(int $customerId, array $data): int
    {
        $id = Database::insert('customer_complaints', [
            'customer_id' => $customerId,
            'pnr_id'      => $data['pnr_id'] ?? null,
            'trip_id'     => $data['trip_id'] ?? null,
            'category'    => $data['category'] ?? 'other',
            'severity'    => $data['severity'] ?? 'medium',
            'description' => $data['description'] ?? '',
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);
        AuditLog::record('complaint.open', 'customer_complaint', $id, $data);
        return $id;
    }

    public function resolve(int $id, string $resolution, int $compensationFcfa = 0, ?string $voucherCode = null): void
    {
        Database::update('customer_complaints', [
            'status' => 'resolved',
            'resolution' => $resolution,
            'compensation_fcfa' => $compensationFcfa,
            'voucher_code' => $voucherCode,
            'resolved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        AuditLog::record('complaint.resolve', 'customer_complaint', $id, ['compensation' => $compensationFcfa]);
    }

    public function close(int $id): void
    {
        Database::update('customer_complaints',
            ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')],
            'id = ?', [$id]
        );
    }

    public function escalate(int $id, ?int $assignTo = null): void
    {
        Database::update('customer_complaints', [
            'status' => 'escalated',
            'severity' => 'high',
            'assigned_to' => $assignTo,
        ], 'id = ?', [$id]);
    }

    public function listOpen(?string $severity = null): array
    {
        $where = ["c.status IN ('open','investigating','escalated')"];
        $params = [];
        if ($severity) { $where[] = 'c.severity = ?'; $params[] = $severity; }

        return Database::select(
            "SELECT c.*, cu.first_name, cu.last_name, cu.phone_display,
                    CONCAT(u.first_name,' ',u.last_name) AS assigned_name
             FROM customer_complaints c
             JOIN customers cu ON cu.id = c.customer_id
             LEFT JOIN users u ON u.id = c.assigned_to
             WHERE " . implode(' AND ', $where) . "
             ORDER BY FIELD(c.severity,'critical','high','medium','low'), c.opened_at DESC LIMIT 200",
            $params
        );
    }

    public function stats(): array
    {
        $rows = Database::select(
            "SELECT category, COUNT(*) AS n, COALESCE(SUM(compensation_fcfa),0) AS comp_total
             FROM customer_complaints
             WHERE opened_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY category ORDER BY n DESC"
        );
        return $rows;
    }
}
