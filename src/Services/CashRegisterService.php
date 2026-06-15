<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

final class CashRegisterService
{
    public function open(int $agencyId, int $cashierId, int $openingAmount = 0): array
    {
        $existing = Database::selectOne(
            "SELECT * FROM cash_registers WHERE cashier_id=? AND status='ouverte' LIMIT 1",
            [$cashierId]
        );
        if ($existing) {
            // Auto-close si paramètre activé et durée max dépassée
            $maxH = max(0, Setting::getInt('caisse.session_max_hours', 12));
            if ($maxH > 0 && Setting::getBool('caisse.auto_close', false)) {
                $openedTs = strtotime((string)$existing['opened_at']);
                if ($openedTs !== false && (time() - $openedTs) > $maxH * 3600) {
                    // Clôture automatique au montant théorique (gap = 0) avec note explicite
                    $this->close((int)$existing['id'], -1, $cashierId,
                        "Clôture automatique après dépassement de {$maxH} h.");
                } else {
                    throw new \RuntimeException('Impossible d\'ouvrir une deuxième caisse : une session est déjà active sur cet appareil. Clôturez-la d\'abord.');
                }
            } else {
                throw new \RuntimeException('Impossible d\'ouvrir une deuxième caisse : une session est déjà active sur cet appareil. Clôturez-la d\'abord.');
            }
        }

        $id = Database::insert(
            "INSERT INTO cash_registers (agency_id, cashier_id, opening_amount, opened_at, status)
             VALUES (?,?,?, NOW(), 'ouverte')",
            [$agencyId, $cashierId, $openingAmount]
        );
        AuditLog::record('caisse.open', 'cash_register', $id, ['opening' => $openingAmount]);
        return Database::selectOne("SELECT * FROM cash_registers WHERE id=?", [$id]);
    }

    public function currentForUser(int $cashierId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM cash_registers WHERE cashier_id=? AND status='ouverte' LIMIT 1",
            [$cashierId]
        );
    }

    public function close(int $registerId, int $declaredAmount, int $userId, ?string $notes = null): array
    {
        return Database::transaction(function () use ($registerId, $declaredAmount, $userId, $notes) {
            $reg = Database::selectOne("SELECT * FROM cash_registers WHERE id=? FOR UPDATE", [$registerId]);
            if (!$reg) throw new \RuntimeException('Caisse introuvable.');
            if ($reg['status'] !== 'ouverte') throw new \RuntimeException('Caisse déjà clôturée.');

            $sum = Database::selectOne(
                "SELECT COALESCE(SUM(amount_fcfa),0) AS total, COUNT(*) AS cnt
                 FROM sales WHERE cash_register_id=?",
                [$registerId]
            );
            // Ajustements / remboursements (montants signés, négatifs = sortie)
            $entries = Database::selectOne(
                "SELECT COALESCE(SUM(amount_fcfa),0) AS total
                 FROM cash_register_entries WHERE cash_register_id=?",
                [$registerId]
            );
            $theoretical = (int)$reg['opening_amount'] + (int)$sum['total'] + (int)$entries['total'];
            // Mode automatique : si declaredAmount < 0, on déclare le théorique (gap=0)
            if ($declaredAmount < 0) {
                $declaredAmount = $theoretical;
            }
            $gap = $declaredAmount - $theoretical;

            $closureId = Database::insert(
                "INSERT INTO daily_closures
                 (cash_register_id, theoretical_amount, declared_amount, gap_amount, ticket_count, closed_by, notes, closed_at)
                 VALUES (?,?,?,?,?,?,?, NOW())",
                [$registerId, $theoretical, $declaredAmount, $gap, (int)$sum['cnt'], $userId, $notes]
            );
            Database::execute(
                "UPDATE cash_registers SET status='cloturee', closed_at=NOW() WHERE id=?",
                [$registerId]
            );
            AuditLog::record('caisse.close', 'cash_register', $registerId, [
                'theoretical' => $theoretical, 'declared' => $declaredAmount, 'gap' => $gap,
            ]);
            return Database::selectOne("SELECT * FROM daily_closures WHERE id=?", [$closureId]);
        });
    }

    public function validateClosure(int $closureId, int $validatorId): bool
    {
        Database::execute(
            "UPDATE daily_closures SET validated_by=?, validated_at=NOW() WHERE id=?",
            [$validatorId, $closureId]
        );
        AuditLog::record('caisse.validate', 'closure', $closureId);
        return true;
    }

    /**
     * Décompte attendu par mode de paiement (GAP-26).
     * Retourne ['especes' => 1234, 'mobile_money' => 5000, 'carte' => 0, 'virement' => 0, 'voucher' => 0]
     */
    public function expectedByMode(int $registerId): array
    {
        $rows = Database::select(
            "SELECT payment_method, SUM(amount_fcfa) AS total
             FROM sales WHERE cash_register_id = ?
             GROUP BY payment_method", [$registerId]
        );
        $modes = ['especes' => 0, 'mobile_money' => 0, 'carte' => 0, 'virement' => 0, 'voucher' => 0];
        foreach ($rows as $r) {
            $key = $r['payment_method'];
            // Compatibilité legacy : 'mobile' -> 'mobile_money'
            if ($key === 'mobile') $key = 'mobile_money';
            if (isset($modes[$key])) {
                $modes[$key] += (int)$r['total'];
            }
        }
        return $modes;
    }

    /**
     * Clôture multi-modes (GAP-26) : enregistre comptés et écarts par mode.
     * @param array{especes:int, mobile_money:int, carte:int, virement:int, voucher:int} $countedByMode
     */
    public function closeMultiMode(int $registerId, array $countedByMode, int $userId, ?string $notes = null): array
    {
        return Database::transaction(function () use ($registerId, $countedByMode, $userId, $notes) {
            $reg = Database::selectOne("SELECT * FROM cash_registers WHERE id=? FOR UPDATE", [$registerId]);
            if (!$reg) throw new \RuntimeException('Caisse introuvable.');
            if ($reg['status'] !== 'ouverte') throw new \RuntimeException('Caisse déjà clôturée.');

            $expected = $this->expectedByMode($registerId);
            // Le solde initial s'ajoute aux espèces
            $expected['especes'] += (int)$reg['opening_amount'];
            // Ajustements : ajoute aux espèces (par convention)
            $entries = (int)(Database::selectOne(
                "SELECT COALESCE(SUM(amount_fcfa),0) AS total FROM cash_register_entries WHERE cash_register_id=?",
                [$registerId]
            )['total'] ?? 0);
            $expected['especes'] += $entries;

            $totalExpected = array_sum($expected);
            $totalCounted  = array_sum($countedByMode);
            $gap           = $totalCounted - $totalExpected;

            $count = (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM sales WHERE cash_register_id=?",
                [$registerId]
            )['c'] ?? 0);

            $closureId = Database::insert(
                "INSERT INTO daily_closures
                    (cash_register_id, theoretical_amount, declared_amount, gap_amount, ticket_count, closed_by, notes, closed_at,
                     counted_cash_fcfa, counted_mobile_money_fcfa, counted_card_fcfa, counted_bank_transfer_fcfa, counted_voucher_fcfa,
                     expected_cash_fcfa, expected_mobile_money_fcfa, expected_card_fcfa, expected_bank_transfer_fcfa, expected_voucher_fcfa)
                 VALUES (?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?,?,?,?)",
                [
                    $registerId, $totalExpected, $totalCounted, $gap, $count, $userId, $notes,
                    $countedByMode['especes']      ?? 0, $countedByMode['mobile_money'] ?? 0,
                    $countedByMode['carte']        ?? 0, $countedByMode['virement']     ?? 0,
                    $countedByMode['voucher']      ?? 0,
                    $expected['especes'],     $expected['mobile_money'],
                    $expected['carte'],       $expected['virement'],
                    $expected['voucher'],
                ]
            );
            Database::execute("UPDATE cash_registers SET status='cloturee', closed_at=NOW() WHERE id=?", [$registerId]);

            AuditLog::record('caisse.close_multi_mode', 'cash_register', $registerId, [
                'expected' => $expected, 'counted' => $countedByMode, 'gap' => $gap,
            ]);
            return Database::selectOne("SELECT * FROM daily_closures WHERE id=?", [$closureId]);
        });
    }

    /** Récap temps réel de la caisse en cours. */
    public function summary(int $registerId): array
    {
        $reg = Database::selectOne(
            "SELECT cr.*, u.first_name, u.last_name, a.name AS agency_name
             FROM cash_registers cr
             JOIN users u ON u.id = cr.cashier_id
             JOIN agencies a ON a.id = cr.agency_id
             WHERE cr.id=?", [$registerId]
        );
        $stats = Database::selectOne(
            "SELECT COUNT(*) AS tickets_sold, COALESCE(SUM(amount_fcfa),0) AS total
             FROM sales WHERE cash_register_id=?",
            [$registerId]
        );
        $entries = Database::selectOne(
            "SELECT COALESCE(SUM(amount_fcfa),0) AS adjustments
             FROM cash_register_entries WHERE cash_register_id=?",
            [$registerId]
        );
        $stats['adjustments'] = (int)($entries['adjustments'] ?? 0);
        $stats['theoretical'] = (int)$reg['opening_amount'] + (int)$stats['total'] + $stats['adjustments'];
        // Seuil d'alerte (warning UI) : si le théorique dépasse, on doit clôturer
        $threshold = max(0, Setting::getInt('caisse.alert_threshold', 0));
        $stats['alert_threshold'] = $threshold;
        $stats['over_threshold']  = $threshold > 0 && $stats['theoretical'] >= $threshold;
        // GAP-26 : ventilation par mode
        $stats['by_mode'] = $this->expectedByMode($registerId);
        return array_merge($reg, $stats);
    }
}
