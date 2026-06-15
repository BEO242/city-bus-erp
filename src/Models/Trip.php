<?php

declare(strict_types=1);

namespace CityBus\Models;

final class Trip extends BaseModel
{
    protected static string $table = 'trips';

    /** Cycle de vie complet — couvre tous les cas réels. */
    public const STATUSES = [
        'planifie'     => 'Planifié',
        'valide'       => 'Validé',         // confirmé par exploitation, prêt à embarquer
        'embarquement' => 'Embarquement',
        'en_route'     => 'En route',
        'arrive'       => 'Arrivé',
        'cloture'      => 'Clôturé',
        'incident'     => 'Incident',
        'retourne'     => 'Retourné',       // demi-tour suite incident
        'litige'       => 'En litige',      // post-clôture, contestation
        'annule'       => 'Annulé',
    ];

    public const TYPES = [
        'commercial'  => 'Commercial',
        'fret'        => 'Fret uniquement',
        'affretement' => 'Affrètement',
        'interne'     => 'Interne',
        'formation'   => 'Formation',
        'test'        => 'Essai',
    ];

    public const PRIORITIES = [
        'normale' => 'Normale',
        'vip'     => 'VIP',
        'express' => 'Express',
        'convoi'  => 'Convoi',
    ];

    public const DELAY_REASONS = [
        'mecanique'        => 'Panne mécanique',
        'traffic'          => 'Trafic / route',
        'meteo'            => 'Météo',
        'accident'         => 'Accident',
        'controle'         => 'Contrôle administratif',
        'retard_chauffeur' => 'Retard chauffeur',
        'autre'            => 'Autre',
    ];

    /** Statuts considérés comme "actifs" (voyage en cours / à venir). */
    public const ACTIVE_STATUSES = ['planifie','valide','embarquement','en_route'];

    /** Statuts terminaux (plus de transitions sortantes). */
    public const TERMINAL_STATUSES = ['cloture','annule'];

    public static function isActive(string $status): bool
    {
        return in_array($status, self::ACTIVE_STATUSES, true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }

    /** Couleur Tailwind associée au statut. */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'planifie'     => 'slate',
            'valide'       => 'cyan',
            'embarquement' => 'amber',
            'en_route'     => 'cb-primary',
            'arrive'       => 'emerald',
            'cloture'      => 'emerald',
            'incident'     => 'rose',
            'retourne'     => 'orange',
            'litige'       => 'purple',
            'annule'       => 'rose',
            default        => 'slate',
        };
    }
}
