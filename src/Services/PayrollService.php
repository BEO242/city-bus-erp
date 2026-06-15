<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

final class PayrollService
{
    public function __construct(private PdfService $pdf = new PdfService()) {}

    /** Calcule et crée la paie d'un mois pour tous les employés actifs. */
    public function runMonth(int $month, int $year, int $userId): array
    {
        return Database::transaction(function () use ($month, $year, $userId) {
            $employees = Database::select("SELECT * FROM employees WHERE status='actif' AND deleted_at IS NULL");
            $created = 0; $skipped = 0;

            // Paramètres RH
            $cnssEmpPct  = max(0, min(50, Setting::getInt('rh.cnss_rate_employee', 4)));
            $irppEnabled = Setting::getBool('rh.irpp_enabled', false);
            $overtimeMul = (float)Setting::getString('rh.overtime_rate_multiplier', '1.5');
            if ($overtimeMul < 1.0) $overtimeMul = 1.0;

            foreach ($employees as $emp) {
                $exists = Database::selectOne(
                    "SELECT id FROM payroll WHERE employee_id=? AND month=? AND year=?",
                    [$emp['id'], $month, $year]
                );
                if ($exists) { $skipped++; continue; }

                // Compter trips effectués (chauffeurs/convoyeurs/contrôleurs)
                $trips = Database::selectOne(
                    "SELECT COUNT(*) AS c FROM trips
                     WHERE (driver_id=? OR convoyeur_id=?)
                       AND MONTH(trip_date)=? AND YEAR(trip_date)=?
                       AND status IN ('arrive','cloture')",
                    [$emp['id'], $emp['id'], $month, $year]
                );
                $tripsCount = (int)$trips['c'];

                $base  = (int)$emp['salary_base'];
                $dailyBonus = (int)$emp['daily_bonus'];
                // Heures supplémentaires : trips au-delà de 20/mois (norme) bénéficient du multiplicateur
                $regularTrips  = min($tripsCount, 20);
                $overtimeTrips = max(0, $tripsCount - 20);
                $bonus = $regularTrips * $dailyBonus
                       + (int)round($overtimeTrips * $dailyBonus * $overtimeMul);

                // Déductions sociales et fiscales
                $gross = $base + $bonus;
                $cnss  = (int)round($gross * $cnssEmpPct / 100);
                $irpp  = 0;
                if ($irppEnabled) {
                    // Barème simplifié progressif (Congo) sur revenu mensuel après CNSS
                    $taxable = $gross - $cnss;
                    if      ($taxable <= 464000)  $irpp = (int)round($taxable * 0.01);
                    elseif  ($taxable <= 1000000) $irpp = (int)round(4640 + ($taxable - 464000) * 0.10);
                    elseif  ($taxable <= 3000000) $irpp = (int)round(58240 + ($taxable - 1000000) * 0.25);
                    else                          $irpp = (int)round(558240 + ($taxable - 3000000) * 0.40);
                }
                $deductions = $cnss + $irpp;
                $net = $gross - $deductions;

                Database::insert(
                    "INSERT INTO payroll (employee_id, month, year, salary_base, bonus_amount, deductions, net_amount, trips_count)
                     VALUES (?,?,?,?,?,?,?,?)",
                    [$emp['id'], $month, $year, $base, $bonus, $deductions, $net, $tripsCount]
                );
                $created++;
            }
            AuditLog::record('payroll.run', 'payroll_month', null, [
                'month' => $month, 'year' => $year,
                'created' => $created, 'skipped' => $skipped,
                'cnss_pct' => $cnssEmpPct, 'irpp' => $irppEnabled,
                'by' => $userId,
            ]);
            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    public function markPaid(int $payrollId, int $userId): bool
    {
        Database::execute(
            "UPDATE payroll SET is_paid=TRUE, paid_at=NOW(), paid_by=? WHERE id=?",
            [$userId, $payrollId]
        );
        AuditLog::record('payroll.paid', 'payroll', $payrollId);
        return true;
    }
}
