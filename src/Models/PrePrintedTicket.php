<?php

declare(strict_types=1);

namespace CityBus\Models;

final class PrePrintedTicket extends BaseModel
{
    protected static string $table = 'pre_printed_tickets';
    protected static bool $softDeletes = true;
}
