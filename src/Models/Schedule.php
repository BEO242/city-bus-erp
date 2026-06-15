<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Schedule extends BaseModel
{
    protected static string $table = 'schedules';
    protected static bool $timestamps = false;
}
