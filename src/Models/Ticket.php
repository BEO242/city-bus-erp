<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Ticket extends BaseModel
{
    protected static string $table = 'tickets';
    protected static bool $softDeletes = true;

    public const STATUSES = [
        'emis'    => 'Émis',
        'valide'  => 'Validé',
        'arrive'  => 'Arrivé',
        'annule'  => 'Annulé',
    ];
}
