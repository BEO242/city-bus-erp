<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Transforme les données brutes du journal d'audit en libellés lisibles
 * pour un utilisateur non-technicien.
 */
final class AuditPresenter
{
    // ── Libellés des actions ─────────────────────────────────────────────────

    private const ACTION_LABELS = [
        // Authentification
        'login.success'               => 'Connexion réussie',
        'login.failed'                => 'Tentative de connexion échouée',
        'login.locked'                => 'Compte verrouillé (trop de tentatives)',
        'logout'                      => 'Déconnexion',
        // Utilisateurs
        'user.create'                 => 'Nouveau compte utilisateur créé',
        'user.update'                 => 'Compte utilisateur modifié',
        'user.delete'                 => 'Compte utilisateur supprimé',
        'user.password.reset'         => 'Mot de passe réinitialisé',
        'user.password.change'        => 'Mot de passe modifié',
        'user.2fa.enabled'            => 'Double authentification activée',
        'user.2fa.disabled'           => 'Double authentification désactivée',
        'user.2fa.reset'              => 'Double authentification réinitialisée (admin)',
        // Billets
        'ticket.create'               => 'Billet vendu',
        'ticket.cancel'               => 'Billet annulé',
        'ticket.reprint'              => 'Billet réimprimé',
        'ticket.validate'             => 'Billet validé (contrôle)',
        // Bagages
        'baggage.create'              => 'Billet bagage créé',
        'baggage.cancel'              => 'Billet bagage annulé',
        // Voyages
        'trip.create'                 => 'Voyage planifié',
        'trip.update'                 => 'Voyage modifié',
        'trip.delete'                 => 'Voyage supprimé',
        'trip.status'                 => 'Statut du voyage modifié',
        'trip.departure'              => 'Voyage parti',
        'trip.arrived'                => 'Voyage arrivé à destination',
        'trip.cancelled'              => 'Voyage annulé',
        'trip.incident'               => 'Incident signalé sur le voyage',
        'trip.incident.notify'        => 'Notification d\'incident envoyée à l\'admin',
        // Caisse
        'caisse.open'                 => 'Caisse ouverte',
        'caisse.close'                => 'Caisse clôturée',
        'caisse.auto_close'           => 'Caisse clôturée automatiquement',
        // Pré-imprimés
        'preprint.validate'           => 'Pré-imprimé validé',
        'preprint.create'             => 'Pré-imprimé créé',
        // Paie
        'payroll.run'                 => 'Fiche de paie calculée',
        // Contrôle
        'controle.scan'               => 'Contrôle billet effectué',
        'controle.validate'           => 'Billet validé à l\'entrée',
        'controle.reject'             => 'Billet refusé au contrôle',
        // Maintenance & carburant
        'maintenance.create'          => 'Maintenance enregistrée',
        'maintenance.update'          => 'Maintenance mise à jour',
        'fuel.create'                 => 'Ravitaillement en carburant enregistré',
        // Référentiel
        'bus.create'                  => 'Bus ajouté à la flotte',
        'bus.update'                  => 'Bus modifié',
        'bus.delete'                  => 'Bus supprimé',
        'driver.create'               => 'Chauffeur enregistré',
        'driver.update'               => 'Chauffeur modifié',
        'driver.delete'               => 'Chauffeur supprimé',
        'agency.create'               => 'Agence créée',
        'agency.update'               => 'Agence modifiée',
        'line.create'                 => 'Ligne créée',
        'line.update'                 => 'Ligne modifiée',
        'tariff.create'               => 'Tarif créé',
        'tariff.update'               => 'Tarif modifié',
        'tariff.delete'               => 'Tarif supprimé',
        // Paramètres
        'settings.save'               => 'Paramètres enregistrés',
        'settings.smtp.test'          => 'Test d\'envoi e-mail effectué',
        'settings.import'             => 'Paramètres importés',
        'settings.export'             => 'Paramètres exportés',
        // Sécurité / sessions
        'security.session_expired'    => 'Session expirée (inactivité)',
        'rate_limit.exceeded'         => 'Trop de requêtes — IP bloquée temporairement',
    ];

    // ── Libellés des entités ─────────────────────────────────────────────────

    private const ENTITY_LABELS = [
        'ticket'          => 'Billet',
        'trip'            => 'Voyage',
        'user'            => 'Utilisateur',
        'driver'          => 'Chauffeur',
        'bus'             => 'Bus',
        'tariff'          => 'Tarif',
        'agency'          => 'Agence',
        'line'            => 'Ligne',
        'caisse'          => 'Caisse',
        'cash_register'   => 'Caisse',
        'maintenance'     => 'Maintenance',
        'fuel'            => 'Ravitaillement',
        'baggage_ticket'  => 'Billet bagage',
        'setting'         => 'Paramètre',
        'payroll'         => 'Fiche de paie',
        'preprint'        => 'Pré-imprimé',
        'note'            => 'Note',
        'media'           => 'Fichier',
    ];

    // ── Libellés des clés de détails ─────────────────────────────────────────

    private const DETAIL_KEYS = [
        'number'           => 'Numéro',
        'ticket_number'    => 'N° billet',
        'price'            => 'Prix (FCFA)',
        'price_fcfa'       => 'Prix (FCFA)',
        'amount'           => 'Montant (FCFA)',
        'trip_id'          => 'Voyage n°',
        'ticket_id'        => 'Billet n°',
        'email'            => 'Adresse e-mail',
        'reason'           => 'Motif',
        'from'             => 'Ancien statut',
        'to'               => 'Nouveau statut',
        'result'           => 'Résultat',
        'status'           => 'Statut',
        'scope'            => 'Type / Catégorie / Classe',
        'tariff_id'        => 'Tarif n°',
        'severity'         => 'Gravité',
        'error'            => 'Erreur',
        'ip'               => 'Adresse IP',
        'refund_amount'    => 'Montant remboursé (FCFA)',
        'refund_pct'       => 'Taux de remboursement (%)',
        'label'            => 'Libellé',
        'category'         => 'Catégorie',
        'setting_key'      => 'Paramètre',
        'to_email'         => 'Destinataire test',
        'count'            => 'Nombre',
        'expires_at'       => 'Expiration',
        'bus_id'           => 'Bus n°',
        'driver_id'        => 'Chauffeur n°',
        'user_id'          => 'Utilisateur n°',
    ];

    // ── Libellés des valeurs de statut ───────────────────────────────────────

    private const STATUS_VALUES = [
        'planifie'     => 'Planifié',
        'embarquement' => 'Embarquement en cours',
        'en_route'     => 'En route',
        'arrive'       => 'Arrivé',
        'cloture'      => 'Clôturé',
        'incident'     => 'Incident',
        'annule'       => 'Annulé',
        'emis'         => 'Émis',
        'valide'       => 'Validé',
        'ok'           => 'Réussi',
        'failed'       => 'Échoué',
    ];

    // ── API publique ─────────────────────────────────────────────────────────

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? self::guessActionLabel($action);
    }

    public static function entityLabel(string $entity): string
    {
        return self::ENTITY_LABELS[$entity] ?? ucfirst($entity);
    }

    public static function detailKey(string $key): string
    {
        return self::DETAIL_KEYS[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    public static function detailValue(string $key, mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $s = (string)$value;
        // Traduire les valeurs de statut connues
        if (isset(self::STATUS_VALUES[$s])) return self::STATUS_VALUES[$s];
        // Formater les montants si la clé le suggère
        if (str_contains($key, 'price') || str_contains($key, 'amount') || str_contains($key, 'fcfa')) {
            if (is_numeric($s)) return number_format((float)$s, 0, ',', ' ') . ' FCFA';
        }
        // Formater les pourcentages
        if (str_contains($key, 'pct') || str_contains($key, 'rate')) {
            if (is_numeric($s)) return $s . '%';
        }
        // Booléens
        if ($s === '1' || strtolower($s) === 'true')  return 'Oui';
        if ($s === '0' || strtolower($s) === 'false') return 'Non';
        return $s;
    }

    /**
     * Déduit un libellé lisible depuis le code action (fallback).
     * Ex: "tariff.category.update" → "Catégorie tarif modifiée"
     */
    private static function guessActionLabel(string $action): string
    {
        $verbMap = [
            'create'   => 'Créé(e)',
            'update'   => 'Modifié(e)',
            'delete'   => 'Supprimé(e)',
            'open'     => 'Ouvert(e)',
            'close'    => 'Clôturé(e)',
            'cancel'   => 'Annulé(e)',
            'validate' => 'Validé(e)',
            'run'      => 'Calculé(e)',
            'reset'    => 'Réinitialisé(e)',
            'import'   => 'Importé(e)',
            'export'   => 'Exporté(e)',
            'enable'   => 'Activé(e)',
            'disable'  => 'Désactivé(e)',
        ];
        $parts  = explode('.', $action);
        $verb   = strtolower(end($parts));
        $noun   = implode(' › ', array_slice($parts, 0, -1));
        $vLabel = $verbMap[$verb] ?? $verb;
        if ($noun) return ucfirst($noun) . ' — ' . $vLabel;
        return ucfirst($action);
    }

    // ── Détection de l'appareil depuis le User-Agent ─────────────────────────

    /**
     * Retourne une description lisible du poste : "Chrome sur Windows 10",
     * "Firefox sur Android", "Safari sur iPhone", etc.
     */
    public static function deviceLabel(string $ua): string
    {
        if ($ua === '') return 'Appareil inconnu';

        $os      = self::detectOs($ua);
        $browser = self::detectBrowser($ua);

        if ($browser && $os) return "{$browser} sur {$os}";
        if ($browser)        return $browser;
        if ($os)             return $os;
        return 'Navigateur non identifié';
    }

    private static function detectOs(string $ua): string
    {
        // Ordre important : les sous-types avant les génériques
        return match(true) {
            str_contains($ua, 'iPhone')                   => 'iPhone (iOS)',
            str_contains($ua, 'iPad')                     => 'iPad (iOS)',
            str_contains($ua, 'Android')                  => 'Android',
            str_contains($ua, 'Windows NT 10.0')          => 'Windows 10/11',
            str_contains($ua, 'Windows NT 6.3')           => 'Windows 8.1',
            str_contains($ua, 'Windows NT 6.2')           => 'Windows 8',
            str_contains($ua, 'Windows NT 6.1')           => 'Windows 7',
            str_contains($ua, 'Windows')                  => 'Windows',
            str_contains($ua, 'Mac OS X')                 => 'macOS',
            str_contains($ua, 'Linux')                    => 'Linux',
            str_contains($ua, 'CrOS')                     => 'Chromebook',
            default                                       => '',
        };
    }

    private static function detectBrowser(string $ua): string
    {
        // Edg doit précéder Chrome (Edge moderne se présente aussi comme Chrome)
        return match(true) {
            str_contains($ua, 'Edg/')                     => 'Microsoft Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome/') && !str_contains($ua, 'Chromium') => 'Chrome',
            str_contains($ua, 'Chromium/')                => 'Chromium',
            str_contains($ua, 'Firefox/')                 => 'Firefox',
            str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome') => 'Safari',
            str_contains($ua, 'MSIE') || str_contains($ua, 'Trident/') => 'Internet Explorer',
            default                                       => '',
        };
    }

    // ── Badge couleur ────────────────────────────────────────────────────────

    public static function badgeClass(string $action): string
    {
        return match(true) {
            str_contains($action, '.create') || $action === 'login.success' || $action === 'caisse.open'
                                                              => 'bg-emerald-100 text-emerald-700',
            str_contains($action, '.delete') || str_contains($action, '.cancel') || $action === 'login.locked'
                                                              => 'bg-rose-100 text-rose-700',
            str_contains($action, '.update') || str_contains($action, '.reset') || str_contains($action, '.save')
                                                              => 'bg-amber-100 text-amber-700',
            str_starts_with($action, 'login.') || str_starts_with($action, 'logout')
                                                              => 'bg-blue-100 text-blue-700',
            str_contains($action, '.close')                   => 'bg-violet-100 text-violet-700',
            str_contains($action, 'incident')                 => 'bg-orange-100 text-orange-700',
            str_contains($action, 'ticket') || str_contains($action, 'baggage')
                                                              => 'bg-teal-100 text-teal-700',
            str_contains($action, 'trip') || str_contains($action, 'voyage')
                                                              => 'bg-indigo-100 text-indigo-700',
            default                                           => 'bg-slate-100 text-slate-700',
        };
    }
}
