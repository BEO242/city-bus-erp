<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Logger;

/**
 * Notification d'escalade automatique sur événements critiques (GAP-15).
 */
final class EscalationService
{
    private const SEVERITY_RANK = ['mineur' => 1, 'modere' => 2, 'grave' => 3, 'critique' => 4];

    /**
     * Déclenche l'escalade pour un incident.
     */
    public function escalate(array $incident): void
    {
        $type     = $incident['type'] ?? '';
        $severity = $incident['severity'] ?? 'mineur';
        $sevRank  = self::SEVERITY_RANK[$severity] ?? 0;

        $rules = Database::select(
            "SELECT * FROM escalation_rules
             WHERE is_active = 1
               AND (incident_type IS NULL OR incident_type = ?)",
            [$type]
        );

        $notif = new NotificationService();
        $sent = 0;

        foreach ($rules as $rule) {
            $minRank = self::SEVERITY_RANK[$rule['min_severity']] ?? 99;
            if ($sevRank < $minRank) continue;

            $vars = [
                'type'        => $type,
                'severity'    => $severity,
                'description' => mb_substr((string)($incident['description'] ?? ''), 0, 150),
                'location'    => $incident['location'] ?? '',
                'occurred_at' => $incident['occurred_at'] ?? date('Y-m-d H:i:s'),
                'incident_id' => $incident['id'] ?? '',
            ];
            $message = sprintf(
                "[ESCALADE] Incident %s/%s · %s · %s. ID #%s",
                $vars['type'], $vars['severity'],
                $vars['description'], $vars['location'], $vars['incident_id']
            );

            // Notifier les rôles
            if (!empty($rule['notify_role_slugs'])) {
                $slugs = array_map('trim', explode(',', (string)$rule['notify_role_slugs']));
                $users = Database::select(
                    "SELECT u.email, u.phone FROM users u
                     JOIN roles r ON r.id = u.role_id
                     WHERE r.slug IN (" . implode(',', array_fill(0, count($slugs), '?')) . ")
                       AND u.is_active = 1 AND u.deleted_at IS NULL",
                    $slugs
                );
                foreach ($users as $u) {
                    if ($u['phone']) { $notif->sendSms($u['phone'], $message); $sent++; }
                    if ($u['email']) { $notif->sendEmail($u['email'], "[CITY BUS] Incident " . $vars['type'], $message); $sent++; }
                }
            }
            // Numéros / emails spécifiques
            if (!empty($rule['notify_phones'])) {
                $phones = preg_split('/[\n,;]+/', (string)$rule['notify_phones']);
                foreach ($phones as $p) { $p = trim($p); if ($p) { $notif->sendSms($p, $message); $sent++; } }
            }
            if (!empty($rule['notify_emails'])) {
                $emails = preg_split('/[\n,;]+/', (string)$rule['notify_emails']);
                foreach ($emails as $em) { $em = trim($em); if ($em) { $notif->sendEmail($em, "[CITY BUS] Incident " . $vars['type'], $message); $sent++; } }
            }
        }

        Logger::info("escalation.dispatched", ['incident_id' => $incident['id'] ?? null, 'recipients' => $sent]);
    }
}
