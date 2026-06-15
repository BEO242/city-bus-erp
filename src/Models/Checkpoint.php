<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Checkpoint extends BaseModel
{
    protected static string $table = 'checkpoints';
    protected static bool $timestamps = false;
}
