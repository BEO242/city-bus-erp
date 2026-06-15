<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

final class CurrencyService
{
    public function base(): array
    {
        $code = Setting::getString('currency.base_code', 'XAF');
        return Database::selectOne("SELECT * FROM currencies WHERE code = ?", [$code])
            ?? ['code' => 'XAF', 'symbol' => 'FCFA', 'rate_to_base' => 1, 'decimals' => 0];
    }

    public function active(): array
    {
        return Database::select("SELECT * FROM currencies WHERE is_active = 1 ORDER BY is_base DESC, code");
    }

    /** Convertit un montant exprimé dans la devise de base vers une autre. */
    public function convert(int $amountBase, string $targetCode): float
    {
        if ($targetCode === Setting::getString('currency.base_code', 'XAF')) {
            return (float)$amountBase;
        }
        $rate = (float)(Database::selectOne("SELECT rate_to_base FROM currencies WHERE code = ?", [$targetCode])['rate_to_base'] ?? 1);
        if ($rate <= 0) return (float)$amountBase;
        return $amountBase / $rate;
    }

    public function format(int $amountBase, ?string $targetCode = null): string
    {
        $target = $targetCode ?: Setting::getString('currency.base_code', 'XAF');
        $row = Database::selectOne("SELECT * FROM currencies WHERE code = ?", [$target]);
        if (!$row) return number_format($amountBase, 0, ',', ' ') . ' ' . $target;
        $value = $this->convert($amountBase, $target);
        return number_format($value, (int)$row['decimals'], ',', ' ') . ' ' . $row['symbol'];
    }
}
