<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Stop extends BaseModel
{
    protected static string $table = 'stops';
    protected static bool $timestamps = false;
}
