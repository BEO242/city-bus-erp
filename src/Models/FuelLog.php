<?php

declare(strict_types=1);

namespace CityBus\Models;

final class FuelLog extends BaseModel
{
    protected static string $table = 'fuel_logs';
    protected static bool $timestamps = false;
}
