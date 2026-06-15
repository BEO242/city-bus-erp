<?php

declare(strict_types=1);

namespace CityBus\Models;

final class DailyClosure extends BaseModel
{
    protected static string $table = 'daily_closures';
    protected static bool $timestamps = false;
}
