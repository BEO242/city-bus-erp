<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class BaggageTariff extends BaseModel
{
    protected static string $table = 'baggage_tariffs';

    /**
     * Retourne les tranches de poids pour un tarif bagage (bracket_mode = 1).
     * @return array<int,array{id,baggage_tariff_id,weight_from_kg,weight_to_kg,price_fcfa,sort_order}>
     */
    public static function bracketsFor(int $baggageTariffId): array
    {
        return Database::select(
            "SELECT id, baggage_tariff_id, weight_from_kg, weight_to_kg, price_fcfa, sort_order
               FROM baggage_tariff_brackets
              WHERE baggage_tariff_id = ?
              ORDER BY sort_order ASC, weight_from_kg ASC",
            [$baggageTariffId]
        );
    }

    /**
     * Calcule le prix pour un poids donné selon le tarif.
     * Retourne null si le poids dépasse les limites ou le tarif est non applicable.
     */
    public static function calculatePrice(array $tariff, float $weightKg): ?int
    {
        if ($weightKg < 0) {
            return null;
        }
        if ($tariff['max_weight_kg'] !== null && $weightKg > (float)$tariff['max_weight_kg']) {
            return null;
        }

        $base = (int)($tariff['base_fee_fcfa'] ?? 0);

        if ((int)($tariff['bracket_mode'] ?? 0) === 1) {
            $brackets = self::bracketsFor((int)$tariff['id']);
            foreach ($brackets as $b) {
                $from = (float)$b['weight_from_kg'];
                $to   = $b['weight_to_kg'] !== null ? (float)$b['weight_to_kg'] : PHP_FLOAT_MAX;
                if ($weightKg >= $from && $weightKg <= $to) {
                    return $base + (int)$b['price_fcfa'];
                }
            }
            return null; // hors tranches
        }

        $perKg = (int)($tariff['per_kg_fcfa'] ?? 0);
        return $base + (int)ceil($weightKg * $perKg);
    }

    /**
     * Vérifie si les dimensions d'un colis dépassent les limites du tarif.
     * Retourne true si le colis est hors gabarit.
     */
    public static function isOversize(array $tariff, ?int $lengthCm, ?int $widthCm, ?int $heightCm): bool
    {
        if ($tariff['max_length_cm'] !== null && $lengthCm !== null && $lengthCm > (int)$tariff['max_length_cm']) {
            return true;
        }
        if ($tariff['max_width_cm'] !== null && $widthCm !== null && $widthCm > (int)$tariff['max_width_cm']) {
            return true;
        }
        if ($tariff['max_height_cm'] !== null && $heightCm !== null && $heightCm > (int)$tariff['max_height_cm']) {
            return true;
        }
        if ($tariff['max_girth_cm'] !== null && $lengthCm !== null && $widthCm !== null) {
            $girth = 2 * ($lengthCm + $widthCm);
            if ($girth > (int)$tariff['max_girth_cm']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retourne le statut de validité temporelle.
     * @return 'actif'|'expire'|'futur'|'permanent'
     */
    public static function validityStatus(array $tariff): string
    {
        $today = date('Y-m-d');
        $from  = $tariff['valid_from']  ?? null;
        $until = $tariff['valid_until'] ?? null;

        if (empty($from) && empty($until)) {
            return 'permanent';
        }
        if (!empty($from) && $from > $today) {
            return 'futur';
        }
        if (!empty($until) && $until < $today) {
            return 'expire';
        }
        return 'actif';
    }

    /** Badge CSS pour le statut de validité. */
    public static function validityClass(string $status): string
    {
        return match ($status) {
            'actif'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'expire' => 'bg-rose-50 text-rose-600 border-rose-200',
            'futur'  => 'bg-sky-50 text-sky-700 border-sky-200',
            default  => 'bg-slate-50 text-slate-600 border-slate-200',
        };
    }

    /** Label du statut de validité. */
    public static function validityLabel(string $status): string
    {
        return match ($status) {
            'actif'  => 'En cours',
            'expire' => 'Expiré',
            'futur'  => 'À venir',
            default  => 'Permanent',
        };
    }

    /**
     * Résumé lisible de la formule tarifaire.
     * Ex : "500 FCFA fixe + 200 FCFA/kg" ou "500 FCFA fixe + tranches de poids"
     */
    public static function formulaSummary(array $tariff): string
    {
        $parts = [];
        $base  = (int)($tariff['base_fee_fcfa'] ?? 0);

        if ($base > 0) {
            $parts[] = fcfa($base) . ' fixe';
        }

        if ((int)($tariff['bracket_mode'] ?? 0) === 1) {
            $parts[] = 'tranches de poids';
        } elseif (!empty($tariff['per_kg_fcfa'])) {
            $parts[] = fcfa((int)$tariff['per_kg_fcfa']) . '/kg';
        }

        if (!empty($tariff['volume_surcharge_fcfa'])) {
            $parts[] = '+' . fcfa((int)$tariff['volume_surcharge_fcfa']) . ' hors gabarit';
        }

        return $parts ? implode(' + ', $parts) : fcfa(0);
    }

    /**
     * Résout LE tarif bagage actif pour (ligne, nature, date).
     */
    public static function resolve(int $lineId, int $baggageNatureId, ?string $date = null): ?array
    {
        $date = $date ?: date('Y-m-d');

        $rows = Database::select(
            "SELECT bt.*, l.code AS line_code, l.name AS line_name
               FROM baggage_tariffs bt
               INNER JOIN bus_lines l ON l.id = bt.line_id
              WHERE bt.line_id = ?
                AND JSON_CONTAINS(bt.baggage_nature_ids, ?)
                AND bt.is_active = 1
                AND (bt.valid_from  IS NULL OR bt.valid_from  <= ?)
                AND (bt.valid_until IS NULL OR bt.valid_until >= ?)
              ORDER BY (bt.valid_from IS NOT NULL) DESC,
                       (bt.valid_until IS NOT NULL) DESC,
                       bt.id DESC
              LIMIT 1",
            [$lineId, json_encode($baggageNatureId), $date, $date]
        );

        return $rows[0] ?? null;
    }

    /**
     * Vérifie si un autre tarif bagage actif chevauche le périmètre + plage.
     */
    public static function overlapExists(
        int $lineId,
        int $baggageNatureId,
        ?string $validFrom,
        ?string $validUntil,
        ?int $excludeId = null
    ): ?array {
        $sql = "SELECT id, valid_from, valid_until, label
                  FROM baggage_tariffs
                 WHERE line_id = ?
                   AND JSON_CONTAINS(baggage_nature_ids, ?)
                   AND is_active = 1";
        $params = [$lineId, json_encode($baggageNatureId)];

        if ($excludeId !== null) {
            $sql      .= " AND id <> ?";
            $params[] = $excludeId;
        }
        if ($validFrom !== null && $validFrom !== '') {
            $sql      .= " AND (valid_until IS NULL OR valid_until >= ?)";
            $params[] = $validFrom;
        }
        if ($validUntil !== null && $validUntil !== '') {
            $sql      .= " AND (valid_from IS NULL OR valid_from <= ?)";
            $params[] = $validUntil;
        }

        $sql .= " LIMIT 1";

        return Database::selectOne($sql, $params) ?: null;
    }
}
