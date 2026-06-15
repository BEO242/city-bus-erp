<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Agency extends BaseModel
{
    protected static string $table = 'agencies';

    public const TYPES = [
        'principale'  => 'Agence principale',
        'point_vente' => 'Point de vente',
        'controle'    => 'Poste de contrôle',
        'parking'     => 'Parking',
    ];

    public const CITIES = [
        'brazzaville'  => 'Brazzaville',
        'pointe_noire' => 'Pointe-Noire',
    ];
}
