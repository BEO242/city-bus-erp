<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Models\AuditLog;
use CityBus\Models\InventoryClass;

/**
 * Inventaire des voyages — booking classes (Y/B/M/H/L).
 *
 * Logique :
 *  - À la création d'un voyage, on génère automatiquement l'inventaire par classe
 *    en répartissant les sièges du bus selon une répartition par défaut.
 *  - L'opérateur peut réajuster manuellement la capacité par classe.
 *  - À la vente, le service décrémente la classe choisie.
 */
final class InventoryService
{
    /**
     * Répartition par défaut des sièges entre classes (en %).
     * Total = 100. Configurable via settings.
     */
    private const DEFAULT_DISTRIBUTION = [
        'Y' => 6,    // Première     · 6%
        'B' => 14,   // Affaires     · 14%
        'M' => 40,   // Standard     · 40%
        'H' => 25,   // Économique   · 25%
        'L' => 15,   // Promo        · 15%
    ];

    /**
     * Génère l'inventaire initial pour un voyage (à la création).
     */
    public function generateForTrip(int $tripId, int $totalSeats, ?int $basePriceFcfa = null): int
    {
        // Vérifier qu'il n'existe pas déjà
        $existing = Database::selectOne(
            "SELECT COUNT(*) AS c FROM trip_inventory WHERE trip_id = ?",
            [$tripId]
        );
        if ((int)($existing['c'] ?? 0) > 0) {
            return 0; // déjà fait
        }

        // Charger les classes actives configurées
        $configCsv = \CityBus\Core\Setting::getString('voyage.inventory.default_classes', 'Y,B,M,H,L');
        $configCodes = array_filter(array_map('trim', explode(',', $configCsv)));
        $classes = InventoryClass::active();
        $classes = array_filter($classes, fn($c) => in_array($c['code'], $configCodes, true));

        if (!$classes) return 0;

        // Calculer la répartition (n'utilise que les classes activées)
        $distribution = [];
        $totalPct = 0;
        foreach ($classes as $c) {
            $pct = self::DEFAULT_DISTRIBUTION[$c['code']] ?? 0;
            $distribution[$c['code']] = ['class' => $c, 'pct' => $pct];
            $totalPct += $pct;
        }
        if ($totalPct === 0) return 0;

        // Normaliser à 100% si besoin
        $allocated = 0;
        $rows = [];
        $lastCode = null;
        foreach ($distribution as $code => $info) {
            $share = (int)floor($totalSeats * $info['pct'] / $totalPct);
            $rows[$code] = ['class' => $info['class'], 'capacity' => $share];
            $allocated += $share;
            $lastCode = $code;
        }
        // Ajuster pour ne pas perdre de sièges (arrondi)
        if ($lastCode && $allocated < $totalSeats) {
            $rows[$lastCode]['capacity'] += $totalSeats - $allocated;
        }

        // Calculer les prix par classe (selon priorité boarding inverse)
        // Y → +50% / B → +25% / M → ref / H → -20% / L → -40%
        $priceMultipliers = ['Y' => 1.5, 'B' => 1.25, 'M' => 1.0, 'H' => 0.8, 'L' => 0.6];
        $base = (int)($basePriceFcfa ?? 0);

        $count = 0;
        foreach ($rows as $code => $r) {
            if ($r['capacity'] <= 0) continue;
            $mult = $priceMultipliers[$code] ?? 1.0;
            $price = (int)round($base * $mult);
            Database::insert(
                "INSERT INTO trip_inventory
                    (trip_id, class_id, class_code, capacity, price_fcfa, base_price_fcfa)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$tripId, (int)$r['class']['id'], $code, $r['capacity'], $price, $price]
            );
            $count++;
        }

        AuditLog::record('trip.inventory.generate', 'trip', $tripId, [
            'total_seats' => $totalSeats,
            'classes_created' => $count,
        ]);

        return $count;
    }

    /**
     * Ajuste la capacité ou le prix d'une classe pour un voyage donné.
     */
    public function updateClass(int $tripId, string $classCode, array $changes): void
    {
        $current = Database::selectOne(
            "SELECT * FROM trip_inventory WHERE trip_id = ? AND class_code = ?",
            [$tripId, strtoupper($classCode)]
        );
        if (!$current) {
            throw new \RuntimeException("Classe $classCode introuvable pour ce voyage.");
        }

        $allowed = ['capacity', 'price_fcfa', 'overbooking_pct', 'blocked_count', 'bid_price_fcfa'];
        $sets = [];
        $params = [];
        foreach ($changes as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $sets[] = "`$k` = ?";
            $params[] = (int)$v;
        }
        if (!$sets) return;

        // Garde-fou : capacity ne peut pas être inférieure à sold_count
        if (isset($changes['capacity'])) {
            if ((int)$changes['capacity'] < (int)$current['sold_count']) {
                throw new \RuntimeException("La capacité ne peut pas être inférieure aux billets déjà vendus ({$current['sold_count']}).");
            }
        }

        // Trace du changement de prix
        if (isset($changes['price_fcfa']) && (int)$changes['price_fcfa'] !== (int)$current['price_fcfa']) {
            $sets[] = "last_price_change_at = NOW()";
            $sets[] = "last_price_reason = ?";
            $params[] = $changes['price_reason'] ?? 'Ajustement manuel';
        }

        $params[] = (int)$current['id'];
        Database::execute(
            "UPDATE trip_inventory SET " . implode(',', $sets) . " WHERE id = ?",
            $params
        );

        AuditLog::record('trip.inventory.update', 'trip', $tripId, [
            'class' => $classCode,
            'before' => $current,
            'changes' => $changes,
        ]);
    }

    /**
     * Vérifie qu'un voyage a au moins 1 siège dispo (pour vente).
     */
    public function hasAvailability(int $tripId, ?string $classCode = null): bool
    {
        if ($classCode) {
            $row = Database::selectOne(
                "SELECT (capacity - sold_count - reserved_count - blocked_count) AS avail
                 FROM trip_inventory WHERE trip_id = ? AND class_code = ?",
                [$tripId, strtoupper($classCode)]
            );
            return (int)($row['avail'] ?? 0) > 0;
        }
        $row = Database::selectOne(
            "SELECT SUM(capacity - sold_count - reserved_count - blocked_count) AS avail
             FROM trip_inventory WHERE trip_id = ?",
            [$tripId]
        );
        return (int)($row['avail'] ?? 0) > 0;
    }

    /**
     * Décrémente l'inventaire suite à une vente.
     */
    public function recordSale(int $tripId, string $classCode): void
    {
        Database::execute(
            "UPDATE trip_inventory SET sold_count = sold_count + 1
             WHERE trip_id = ? AND class_code = ?",
            [$tripId, strtoupper($classCode)]
        );
    }

    /**
     * Incrémente l'inventaire suite à une annulation.
     */
    public function recordCancellation(int $tripId, string $classCode): void
    {
        Database::execute(
            "UPDATE trip_inventory SET sold_count = GREATEST(0, sold_count - 1)
             WHERE trip_id = ? AND class_code = ?",
            [$tripId, strtoupper($classCode)]
        );
    }

    /**
     * Distribue le prix de base : si un voyage n'avait pas de prix, on ajuste tout l'inventaire.
     */
    public function rebasePrices(int $tripId, int $newBasePrice): void
    {
        $rows = Database::select(
            "SELECT class_code FROM trip_inventory WHERE trip_id = ?", [$tripId]
        );
        $priceMultipliers = ['Y' => 1.5, 'B' => 1.25, 'M' => 1.0, 'H' => 0.8, 'L' => 0.6];
        foreach ($rows as $r) {
            $mult = $priceMultipliers[$r['class_code']] ?? 1.0;
            $price = (int)round($newBasePrice * $mult);
            Database::execute(
                "UPDATE trip_inventory SET price_fcfa = ?, base_price_fcfa = ?,
                                            last_price_change_at = NOW(),
                                            last_price_reason = 'Rebase prix de référence'
                 WHERE trip_id = ? AND class_code = ?",
                [$price, $price, $tripId, $r['class_code']]
            );
        }
        AuditLog::record('trip.inventory.rebase', 'trip', $tripId, ['new_base' => $newBasePrice]);
    }
}
