<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Employee extends BaseModel
{
    protected static string $table = 'employees';
    protected static bool $softDeletes = true;
}
