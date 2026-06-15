<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Validation extends BaseModel
{
    protected static string $table = 'validations';
    protected static bool $timestamps = false;
}
