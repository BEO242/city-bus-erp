<?php

declare(strict_types=1);

namespace CityBus\Models;

final class MaintenanceOrder extends BaseModel
{
    protected static string $table = 'maintenance_orders';

    public const STATUSES = [
        'planifie'  => 'Planifié',
        'en_cours'  => 'En cours',
        'termine'   => 'Terminé',
        'annule'    => 'Annulé',
    ];
}
