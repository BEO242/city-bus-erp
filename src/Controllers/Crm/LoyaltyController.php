<?php

declare(strict_types=1);

namespace CityBus\Controllers\Crm;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\CustomerService;

/**
 * Administration du programme de fidélité et inscription des clients.
 */
final class LoyaltyController extends Controller
{
    public function __construct(
        private CustomerService $customerService = new CustomerService(),
    ) {}

    // ─── Page de configuration du programme ─────────────────────────────────

    public function config(Request $request): void
    {
        $config = $this->customerService->loyaltyConfig();

        // Stats globales du programme
        $stats = Database::selectOne(
            "SELECT COUNT(*) AS total_members,
                    SUM(CASE WHEN last_trip_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS active_members,
                    AVG(total_trips) AS avg_trips,
                    SUM(total_spent) AS total_spent
               FROM customers
              WHERE is_loyalty_member = 1 AND deleted_at IS NULL"
        ) ?: ['total_members' => 0, 'active_members' => 0, 'avg_trips' => 0, 'total_spent' => 0];

        // Dernières inscriptions
        $recentEnrollments = Database::select(
            "SELECT c.id, c.customer_code, c.first_name, c.last_name, c.phone_display,
                    c.total_trips, c.total_spent, c.loyalty_enrolled_at,
                    CONCAT(u.first_name, ' ', u.last_name) AS enrolled_by_name
               FROM customers c
               LEFT JOIN users u ON u.id = c.loyalty_enrolled_by
              WHERE c.is_loyalty_member = 1 AND c.deleted_at IS NULL
              ORDER BY c.loyalty_enrolled_at DESC
              LIMIT 20"
        );

        // Clients éligibles non inscrits (beaucoup de voyages mais pas membres)
        $eligibleNotEnrolled = Database::select(
            "SELECT c.id, c.first_name, c.last_name, c.phone_display, c.total_trips, c.total_spent
               FROM customers c
              WHERE c.is_loyalty_member = 0 AND c.deleted_at IS NULL
                AND c.total_trips >= COALESCE((SELECT required_trips FROM loyalty_program_config WHERE id = 1), 10)
              ORDER BY c.total_trips DESC
              LIMIT 20"
        );

        $this->view('crm/loyalty/config', [
            'title'              => 'Programme de fidélité',
            'config'             => $config,
            'stats'              => $stats,
            'recentEnrollments'  => $recentEnrollments,
            'eligibleNotEnrolled' => $eligibleNotEnrolled,
        ]);
    }

    // ─── Sauvegarder la configuration ──────────────────────────────────────

    public function saveConfig(Request $request): void
    {
        if (!Auth::can('crm.edit')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        $isEnabled       = (int)(bool)$request->input('is_enabled', 0);
        $requiredTrips   = max(1, (int)$request->input('required_trips', 10));
        $discountPercent = max(0, min(100, (float)$request->input('discount_percent', 10)));
        $periodMonths    = max(0, (int)$request->input('period_months', 12));
        $message         = trim((string)$request->input('enrollment_message', ''));

        // Upsert config (single row, id=1)
        $existing = Database::selectOne("SELECT id FROM loyalty_program_config WHERE id = 1");
        if ($existing) {
            Database::execute(
                "UPDATE loyalty_program_config
                    SET is_enabled = ?, required_trips = ?, discount_percent = ?,
                        period_months = ?, enrollment_message = ?
                  WHERE id = 1",
                [$isEnabled, $requiredTrips, $discountPercent, $periodMonths, $message]
            );
        } else {
            Database::insert(
                "INSERT INTO loyalty_program_config (id, is_enabled, required_trips, discount_percent, period_months, enrollment_message)
                 VALUES (1, ?, ?, ?, ?, ?)",
                [$isEnabled, $requiredTrips, $discountPercent, $periodMonths, $message]
            );
        }

        $this->flash('success', 'Configuration du programme de fidélité mise à jour.');
        redirect('crm/loyalty');
    }

    // ─── Inscrire un client ────────────────────────────────────────────────

    public function enroll(Request $request, string $id): void
    {
        if (!Auth::can('crm.edit')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        $customerId = (int)$id;
        $result = $this->customerService->enrollLoyalty($customerId, (int)Auth::id());

        if ($result) {
            $this->flash('success', 'Client inscrit au programme de fidélité.');
        } else {
            $this->flash('danger', 'Impossible d\'inscrire ce client. Vérifiez que le programme est activé.');
        }

        back();
    }

    // ─── Générer les codes clients manquants (backfill) ───────────────────

    public function generateCodes(Request $request): void
    {
        if (!Auth::can('crm.edit')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        $rows = Database::select(
            "SELECT id FROM customers WHERE customer_code IS NULL AND deleted_at IS NULL LIMIT 500"
        );

        $count = 0;
        foreach ($rows as $r) {
            $code = $this->customerService->generateCustomerCode();
            Database::execute(
                "UPDATE customers SET customer_code = ? WHERE id = ?",
                [$code, (int)$r['id']]
            );
            $count++;
        }

        $this->flash('success', "$count code(s) client générés.");
        redirect('crm/loyalty');
    }
}
