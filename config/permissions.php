<?php
/**
 * Permissions et rôles du système
 */

declare(strict_types=1);

return [
    'roles' => [
        'admin'        => 'Administrateur-Gérant',
        'raf'          => 'Resp. Administratif et Financier',
        'exploitation' => 'Resp. Exploitation',
        'chef_agence'  => 'Chef d\'agence',
        'caissier'     => 'Caissier Bus',
        'controleur'   => 'Contrôleur',
        'mecanicien'   => 'Chef mécanicien',
        'chauffeur'    => 'Chauffeur',
    ],

    'permissions' => [
        // Auth
        'users.view', 'users.create', 'users.edit', 'users.delete',
        // Référentiel
        'referentiel.view', 'referentiel.create', 'referentiel.edit', 'referentiel.delete',
        // Voyages
        'voyages.view', 'voyages.create', 'voyages.edit', 'voyages.close',
        // Billetterie
        'billetterie.view', 'billetterie.create', 'billetterie.cancel', 'billetterie.reprint', 'billetterie.preprint',
        // Contrôle
        'controle.validate', 'controle.view',
        // Caisse
        'caisse.view', 'caisse.open', 'caisse.close', 'caisse.validate',
        // Flotte
        'flotte.view', 'flotte.maintenance.create', 'flotte.maintenance.edit', 'flotte.fuel.log',
        // RH
        'rh.view', 'rh.create', 'rh.edit', 'rh.payroll',
        // Reporting
        'reporting.view', 'reporting.export',
    ],

    // Mapping rôle → permissions
    'role_permissions' => [
        'admin' => ['*'],
        'raf' => [
            'users.view', 'reporting.view', 'reporting.export',
            'caisse.view', 'caisse.validate',
            'rh.view', 'rh.payroll',
            'flotte.view',
        ],
        'exploitation' => [
            'referentiel.view', 'referentiel.create', 'referentiel.edit',
            'voyages.view', 'voyages.create', 'voyages.edit', 'voyages.close',
            'flotte.view', 'flotte.maintenance.create', 'flotte.maintenance.edit', 'flotte.fuel.log',
            'reporting.view',
        ],
        'chef_agence' => [
            'voyages.view',
            'billetterie.view', 'billetterie.create', 'billetterie.cancel', 'billetterie.reprint', 'billetterie.preprint',
            'caisse.view', 'caisse.open', 'caisse.close', 'caisse.validate',
            'rh.view',
            'reporting.view',
        ],
        'caissier' => [
            'voyages.view',
            'billetterie.view', 'billetterie.create',
            'caisse.view', 'caisse.open', 'caisse.close',
        ],
        'controleur' => [
            'controle.validate', 'controle.view',
        ],
        'mecanicien' => [
            'flotte.view', 'flotte.maintenance.create', 'flotte.maintenance.edit',
        ],
        'chauffeur' => [
            'voyages.view',
        ],
    ],
];
