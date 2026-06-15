<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Auth;
use CityBus\Core\Database;

/**
 * Service centralisé pour les dépenses/recettes système liées à des entités.
 *
 * Permet l'enregistrement bidirectionnel :
 *   → depuis la fiche entité (voyage, bus, chauffeur)
 *   → depuis la trésorerie
 * avec visibilité des deux côtés.
 */
final class TreasuryExpenseService
{
    /**
     * Catégories système pertinentes par type d'entité.
     * Utilisé pour filtrer le dropdown dans le widget.
     */
    public const ENTITY_CATEGORIES = [
        'trip' => [
            'peage', 'prime_journaliere', 'carburant', 'parking',
            'lavage_bus', 'amende', 'autre_recette', 'autre_depense',
        ],
        'bus' => [
            'carburant', 'lavage_bus', 'parking', 'peage', 'entretien',
            'pneumatique', 'assurance', 'visite_technique', 'amende',
            'autre_recette', 'autre_depense',
        ],
        'driver' => [
            'prime_journaliere', 'prime_autre', 'indemnite', 'amende',
            'salaire', 'salaire_avance', 'commission_agent',
            'autre_recette', 'autre_depense',
        ],
    ];

    // ─── Lecture ─────────────────────────────────────────────────────────

    /** Dépenses liées à un voyage. */
    public function forTrip(int $tripId): array
    {
        return Database::select(
            "SELECT tt.*, tc.code AS cat_code, tc.label AS cat_label, tc.color AS cat_color,
                    u.first_name AS user_first, u.last_name AS user_last,
                    b.plate AS bus_plate, b.code AS bus_code,
                    CONCAT(d.first_name,' ',d.last_name) AS driver_name
             FROM treasury_transactions tt
             JOIN treasury_categories tc ON tc.id = tt.category_id
             JOIN users u ON u.id = tt.created_by
             LEFT JOIN buses b ON b.id = tt.bus_id
             LEFT JOIN drivers d ON d.id = tt.driver_id
             WHERE tt.trip_id = ? AND COALESCE(tt.status,'pending') != 'rejected'
             ORDER BY tt.created_at DESC",
            [$tripId]
        );
    }

    /** Dépenses liées à un bus (hors tickets/colis). */
    public function forBus(int $busId): array
    {
        return Database::select(
            "SELECT tt.*, tc.code AS cat_code, tc.label AS cat_label, tc.color AS cat_color,
                    u.first_name AS user_first, u.last_name AS user_last,
                    t.trip_code,
                    CONCAT(d.first_name,' ',d.last_name) AS driver_name
             FROM treasury_transactions tt
             JOIN treasury_categories tc ON tc.id = tt.category_id
             JOIN users u ON u.id = tt.created_by
             LEFT JOIN trips t ON t.id = tt.trip_id
             LEFT JOIN drivers d ON d.id = tt.driver_id
             WHERE tt.bus_id = ? AND COALESCE(tt.status,'pending') != 'rejected'
               AND tc.code NOT IN ('billetterie','fret','remboursement')
             ORDER BY tt.created_at DESC
             LIMIT 50",
            [$busId]
        );
    }

    /** Dépenses liées à un chauffeur. */
    public function forDriver(int $driverId): array
    {
        return Database::select(
            "SELECT tt.*, tc.code AS cat_code, tc.label AS cat_label, tc.color AS cat_color,
                    u.first_name AS user_first, u.last_name AS user_last,
                    t.trip_code,
                    b.plate AS bus_plate, b.code AS bus_code
             FROM treasury_transactions tt
             JOIN treasury_categories tc ON tc.id = tt.category_id
             JOIN users u ON u.id = tt.created_by
             LEFT JOIN trips t ON t.id = tt.trip_id
             LEFT JOIN buses b ON b.id = tt.bus_id
             WHERE tt.driver_id = ? AND COALESCE(tt.status,'pending') != 'rejected'
             ORDER BY tt.created_at DESC
             LIMIT 50",
            [$driverId]
        );
    }

    // ─── Catégories filtrées ─────────────────────────────────────────────

    /** Catégories disponibles pour un type d'entité. */
    public function categoriesFor(string $entityType): array
    {
        $codes = self::ENTITY_CATEGORIES[$entityType] ?? [];
        if (empty($codes)) return [];

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        return Database::select(
            "SELECT id, code, label, type, color, sort_order
             FROM treasury_categories
             WHERE is_active = 1 AND code IN ($placeholders)
             ORDER BY sort_order, label",
            $codes
        );
    }

    // ─── Écriture ────────────────────────────────────────────────────────

    /**
     * Crée une transaction de trésorerie liée à des entités.
     *
     * @param array $data [
     *   'category_code' => string,  // code de la catégorie système
     *   'amount_fcfa'   => int,
     *   'description'   => string,
     *   'payment_method'=> string,
     *   'reference'     => ?string,
     *   'trip_id'       => ?int,
     *   'bus_id'        => ?int,
     *   'driver_id'     => ?int,
     * ]
     * @return array La transaction créée
     */
    public function create(array $data): array
    {
        $cat = Database::selectOne(
            "SELECT id, type FROM treasury_categories WHERE code = ? AND is_active = 1",
            [$data['category_code'] ?? '']
        );

        if (!$cat) {
            throw new \RuntimeException("Catégorie invalide : " . ($data['category_code'] ?? ''));
        }

        $amount   = max(1, (int)($data['amount_fcfa'] ?? 0));
        $method   = in_array($data['payment_method'] ?? '', ['especes','mobile_money','carte','virement','cheque'], true)
                    ? $data['payment_method'] : 'especes';
        $tripId   = !empty($data['trip_id'])   ? (int)$data['trip_id']   : null;
        $busId    = !empty($data['bus_id'])     ? (int)$data['bus_id']   : null;
        $driverId = !empty($data['driver_id'])  ? (int)$data['driver_id']: null;

        // Caisse : utiliser celle passée ou celle ouverte par l'utilisateur
        $registerId = !empty($data['cash_register_id'])
            ? (int)$data['cash_register_id']
            : $this->findOpenRegister();

        if (!$registerId) {
            throw new \RuntimeException("Aucune caisse ouverte. Ouvrez une caisse pour enregistrer cette dépense.");
        }

        $id = (int)Database::insert(
            "INSERT INTO treasury_transactions
                (cash_register_id, category_id, type, amount_fcfa, payment_method,
                 description, reference, trip_id, bus_id, driver_id, created_by, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?, 'pending')",
            [
                $registerId,
                (int)$cat['id'],
                $cat['type'],
                $amount,
                $method,
                $data['description'] ?? null,
                $data['reference']   ?? null,
                $tripId,
                $busId,
                $driverId,
                (int)Auth::id(),
            ]
        );

        // ── Enregistrements liés : carburant → fuel_logs ──
        $categoryCode = $data['category_code'] ?? '';
        if ($categoryCode === 'carburant' && $busId && !empty($data['liters'])) {
            $liters       = round((float)($data['liters'] ?? 0), 2);
            $pricePerL    = round((float)($data['price_per_liter'] ?? 625), 2);
            $kmAtFill     = !empty($data['km_at_fill']) ? (int)$data['km_at_fill'] : null;
            $stationName  = trim((string)($data['station_name'] ?? ''));
            Database::insert(
                "INSERT INTO fuel_logs (bus_id, trip_id, liters, price_per_liter, total_cost, km_at_fill, station_name, logged_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$busId, $tripId, $liters, $pricePerL, $amount, $kmAtFill, $stationName ?: null, (int)Auth::id()]
            );
            // Mettre à jour km_current du bus si renseigné
            if ($kmAtFill && $kmAtFill > 0) {
                Database::execute("UPDATE buses SET km_current = GREATEST(COALESCE(km_current,0), ?) WHERE id = ?", [$kmAtFill, $busId]);
            }
        }

        // ── Enregistrements liés : entretien → maintenance_orders ──
        if ($categoryCode === 'entretien' && $busId) {
            $mType      = in_array($data['maintenance_type'] ?? '', ['preventive','corrective'], true)
                        ? $data['maintenance_type'] : 'corrective';
            $mechanicId = !empty($data['mechanic_id']) ? (int)$data['mechanic_id'] : null;
            Database::insert(
                "INSERT INTO maintenance_orders (bus_id, type, description, actual_cost, status, done_at, mechanic_id, created_by)
                 VALUES (?,?,?,?,'termine',NOW(),?,?)",
                [$busId, $mType, $data['description'] ?? 'Entretien', $amount, $mechanicId, (int)Auth::id()]
            );
        }

        // ── Enregistrements liés : pneumatique → tire_records ──
        if ($categoryCode === 'pneumatique' && $busId) {
            $pos  = in_array($data['tire_position'] ?? '', ['avant_gauche','avant_droit','arriere_gauche','arriere_droit','secours'], true)
                  ? $data['tire_position'] : null;
            $tType = in_array($data['tire_type'] ?? '', ['neuf','occasion','rechape'], true)
                   ? $data['tire_type'] : 'neuf';
            Database::insert(
                "INSERT INTO tire_records (bus_id, trip_id, position, brand, size, tire_type, quantity, km_at_install, supplier, cost_fcfa, logged_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $busId, $tripId, $pos,
                    trim((string)($data['tire_brand'] ?? '')) ?: null,
                    trim((string)($data['tire_size'] ?? '')) ?: null,
                    $tType,
                    max(1, (int)($data['tire_quantity'] ?? 1)),
                    !empty($data['tire_km']) ? (int)$data['tire_km'] : null,
                    trim((string)($data['tire_supplier'] ?? '')) ?: null,
                    $amount, (int)Auth::id(),
                ]
            );
        }

        // ── Enregistrements liés : assurance → insurance_records ──
        if ($categoryCode === 'assurance' && $busId) {
            $covType = in_array($data['coverage_type'] ?? '', ['rc','tous_risques','mixte'], true)
                     ? $data['coverage_type'] : 'rc';
            $periodEnd = !empty($data['insurance_end']) ? $data['insurance_end'] : null;
            Database::insert(
                "INSERT INTO insurance_records (bus_id, policy_number, company, coverage_type, period_start, period_end, cost_fcfa, logged_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [
                    $busId,
                    trim((string)($data['insurance_policy'] ?? '')) ?: null,
                    trim((string)($data['insurance_company'] ?? '')) ?: null,
                    $covType,
                    !empty($data['insurance_start']) ? $data['insurance_start'] : null,
                    $periodEnd,
                    $amount, (int)Auth::id(),
                ]
            );
            // Mettre à jour les champs du bus
            if ($periodEnd) {
                Database::execute(
                    "UPDATE buses SET insurance_expiry = ?, insurance_company = COALESCE(?, insurance_company), insurance_policy = COALESCE(?, insurance_policy) WHERE id = ?",
                    [$periodEnd, trim((string)($data['insurance_company'] ?? '')) ?: null, trim((string)($data['insurance_policy'] ?? '')) ?: null, $busId]
                );
            }
        }

        // ── Enregistrements liés : visite_technique → inspection_records ──
        if ($categoryCode === 'visite_technique' && $busId) {
            $result = in_array($data['inspection_result'] ?? '', ['conforme','non_conforme'], true)
                    ? $data['inspection_result'] : 'conforme';
            $nextDue = !empty($data['inspection_next_due']) ? $data['inspection_next_due'] : null;
            Database::insert(
                "INSERT INTO inspection_records (bus_id, inspection_date, center, result, certificate_number, next_due, observations, cost_fcfa, logged_by)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [
                    $busId,
                    !empty($data['inspection_date']) ? $data['inspection_date'] : date('Y-m-d'),
                    trim((string)($data['inspection_center'] ?? '')) ?: null,
                    $result,
                    trim((string)($data['inspection_certificate'] ?? '')) ?: null,
                    $nextDue,
                    trim((string)($data['inspection_observations'] ?? '')) ?: null,
                    $amount, (int)Auth::id(),
                ]
            );
            // Mettre à jour les champs du bus
            if ($nextDue) {
                Database::execute(
                    "UPDATE buses SET tech_control_expiry = ?, tech_control_center = COALESCE(?, tech_control_center) WHERE id = ?",
                    [$nextDue, trim((string)($data['inspection_center'] ?? '')) ?: null, $busId]
                );
            }
        }

        // ── Enregistrements liés : amende → fine_records ──
        if ($categoryCode === 'amende') {
            Database::insert(
                "INSERT INTO fine_records (bus_id, driver_id, trip_id, infraction_type, location, authority, fine_date, is_contested, cost_fcfa, logged_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $busId, $driverId, $tripId,
                    trim((string)($data['infraction_type'] ?? '')) ?: null,
                    trim((string)($data['fine_location'] ?? '')) ?: null,
                    trim((string)($data['fine_authority'] ?? '')) ?: null,
                    !empty($data['fine_date']) ? $data['fine_date'] : date('Y-m-d'),
                    !empty($data['is_contested']) ? 1 : 0,
                    $amount, (int)Auth::id(),
                ]
            );
        }

        // ── Enregistrements liés : lavage_bus → wash_records ──
        if ($categoryCode === 'lavage_bus') {
            $wType = in_array($data['wash_type'] ?? '', ['interieur','exterieur','complet'], true)
                   ? $data['wash_type'] : 'complet';
            Database::insert(
                "INSERT INTO wash_records (bus_id, trip_id, wash_type, location, cost_fcfa, logged_by)
                 VALUES (?,?,?,?,?,?)",
                [$busId, $tripId, $wType, trim((string)($data['wash_location'] ?? '')) ?: null, $amount, (int)Auth::id()]
            );
        }

        // ── Enregistrements liés : peage → toll_records ──
        if ($categoryCode === 'peage') {
            Database::insert(
                "INSERT INTO toll_records (bus_id, trip_id, toll_name, route, cost_fcfa, logged_by)
                 VALUES (?,?,?,?,?,?)",
                [
                    $busId, $tripId,
                    trim((string)($data['toll_name'] ?? '')) ?: null,
                    trim((string)($data['toll_route'] ?? '')) ?: null,
                    $amount, (int)Auth::id(),
                ]
            );
        }

        // ── Enregistrements liés : parking → parking_records ──
        if ($categoryCode === 'parking') {
            Database::insert(
                "INSERT INTO parking_records (bus_id, trip_id, location, duration_hours, cost_fcfa, logged_by)
                 VALUES (?,?,?,?,?,?)",
                [
                    $busId, $tripId,
                    trim((string)($data['parking_location'] ?? '')) ?: null,
                    !empty($data['parking_duration']) ? round((float)$data['parking_duration'], 1) : null,
                    $amount, (int)Auth::id(),
                ]
            );
        }

        // ── Enregistrements liés : salaire/salaire_avance → payroll_records ──
        if (in_array($categoryCode, ['salaire', 'salaire_avance'], true) && $driverId) {
            $pType = $categoryCode === 'salaire_avance' ? 'avance' : 'salaire';
            $baseAmt = (int)($data['payroll_base'] ?? $amount);
            $deductions = (int)($data['payroll_deductions'] ?? 0);
            $netAmt = $baseAmt - $deductions;
            Database::insert(
                "INSERT INTO payroll_records (driver_id, payroll_type, period_month, period_year, base_amount, deductions, net_amount, notes, cost_fcfa, logged_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $driverId, $pType,
                    (int)($data['payroll_month'] ?? (int)date('n')),
                    (int)($data['payroll_year'] ?? (int)date('Y')),
                    $baseAmt, $deductions, max(0, $netAmt),
                    trim((string)($data['payroll_notes'] ?? '')) ?: null,
                    $amount, (int)Auth::id(),
                ]
            );
        }

        // ── Enregistrements liés : prime_journaliere/prime_autre/indemnite/commission_agent → driver_compensations ──
        if (in_array($categoryCode, ['prime_journaliere', 'prime_autre', 'indemnite', 'commission_agent'], true) && $driverId) {
            $rType = in_array($data['comp_rate_type'] ?? '', ['fixe','pourcentage'], true)
                   ? $data['comp_rate_type'] : 'fixe';
            Database::insert(
                "INSERT INTO driver_compensations (driver_id, trip_id, comp_type, reason, rate_type, rate_value, cost_fcfa, logged_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [
                    $driverId, $tripId,
                    $categoryCode,
                    trim((string)($data['comp_reason'] ?? '')) ?: null,
                    $rType,
                    !empty($data['comp_rate_value']) ? round((float)$data['comp_rate_value'], 2) : null,
                    $amount, (int)Auth::id(),
                ]
            );
        }

        // Retourner la transaction enrichie
        return Database::selectOne(
            "SELECT tt.*, tc.code AS cat_code, tc.label AS cat_label, tc.color AS cat_color,
                    u.first_name AS user_first, u.last_name AS user_last
             FROM treasury_transactions tt
             JOIN treasury_categories tc ON tc.id = tt.category_id
             JOIN users u ON u.id = tt.created_by
             WHERE tt.id = ?",
            [$id]
        ) ?: [];
    }

    /** Totaux des dépenses par catégorie pour une entité. */
    public function totalsFor(string $entityType, int $entityId): array
    {
        $col = match ($entityType) {
            'trip'   => 'trip_id',
            'bus'    => 'bus_id',
            'driver' => 'driver_id',
            default  => throw new \InvalidArgumentException("Type d'entité inconnu : $entityType"),
        };

        return Database::select(
            "SELECT tc.code, tc.label, tc.color, tt.type,
                    COUNT(*) AS tx_count,
                    SUM(tt.amount_fcfa) AS total_fcfa
             FROM treasury_transactions tt
             JOIN treasury_categories tc ON tc.id = tt.category_id
             WHERE tt.{$col} = ? AND COALESCE(tt.status,'pending') != 'rejected'
             GROUP BY tc.id
             ORDER BY tc.sort_order",
            [$entityId]
        );
    }

    // ─── Helpers privés ──────────────────────────────────────────────────

    private function findOpenRegister(): ?int
    {
        $row = Database::selectOne(
            "SELECT id FROM cash_registers WHERE cashier_id = ? AND status = 'ouverte' LIMIT 1",
            [(int)Auth::id()]
        );
        if ($row) return (int)$row['id'];

        // Fallback : n'importe quelle caisse ouverte de l'agence de l'utilisateur
        $user = Auth::user();
        $agencyId = (int)($user['agency_id'] ?? 0);
        if ($agencyId > 0) {
            $row = Database::selectOne(
                "SELECT id FROM cash_registers WHERE agency_id = ? AND status = 'ouverte' ORDER BY id LIMIT 1",
                [$agencyId]
            );
            if ($row) return (int)$row['id'];
        }

        return null;
    }
}
