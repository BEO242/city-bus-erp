<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Sale extends BaseModel
{
    protected static string $table = 'sales';
    protected static bool $timestamps = false;
}
