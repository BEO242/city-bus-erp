<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Parcel extends BaseModel
{
    protected static string $table = 'parcels';
    protected static bool $softDeletes = true;

    public const STATUSES = [
        'depose'      => 'Déposé',
        'en_transit'  => 'En transit',
        'arrive'      => 'Arrivé à destination',
        'retire'      => 'Retiré',
        'perdu'       => 'Perdu',
        'endommage'   => 'Endommagé',
        'retourne'    => 'Retourné',
    ];

    /** Catégories par défaut (utilisées comme fallback si parcel_tariffs est vide) */
    public const TYPES = [
        'document'    => 'Document',
        'colis'       => 'Colis standard',
        'aliments'    => 'Aliments',
        'fragile'     => 'Fragile',
        'electronique'=> 'Électronique',
        'textile'     => 'Textile / Vêtements',
        'medicament'  => 'Médicaments',
        'special'     => 'Spécial / Hors gabarit',
    ];

    public const PAYMENT_METHODS = [
        'especes'        => 'Espèces',
        'mobile_money'   => 'Mobile Money',
        'carte'          => 'Carte bancaire',
        'virement'       => 'Virement',
        'a_destination'  => 'À destination',
    ];
}
