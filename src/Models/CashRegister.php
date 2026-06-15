<?php

declare(strict_types=1);

namespace CityBus\Models;

final class CashRegister extends BaseModel
{
    protected static string $table = 'cash_registers';
    protected static bool $timestamps = false;
}
