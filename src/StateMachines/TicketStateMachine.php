<?php

declare(strict_types=1);

namespace CityBus\StateMachines;

/**
 * Machine à états pour les billets passagers.
 *
 * Statut opérationnel : emis → embarque → valide → arrive → annule
 * Statut de paiement  : en_attente → paye → rembourse_partiel → rembourse
 *
 * Règles :
 *  - Un billet payé ne peut PAS revenir en_attente
 *  - Un billet remboursé ne peut PAS être re-payé
 *  - L'annulation est possible depuis emis, embarque, valide (mais pas arrive)
 *  - Le remboursement nécessite d'abord l'annulation
 */
final class TicketStateMachine
{
    /** Transitions opérationnelles autorisées : statut_actuel => [statuts_possibles] */
    public const TRANSITIONS = [
        'emis'     => ['embarque', 'valide', 'annule'],
        'embarque' => ['valide', 'annule'],
        'valide'   => ['arrive', 'annule'],
        'arrive'   => [],       // état terminal
        'annule'   => [],       // état terminal
    ];

    /** Transitions de paiement autorisées */
    public const PAYMENT_TRANSITIONS = [
        'en_attente'       => ['paye', 'rembourse'],       // rembourse = annulation sans paiement
        'paye'             => ['rembourse_partiel', 'rembourse'],
        'rembourse_partiel'=> ['rembourse'],
        'rembourse'        => [],                           // état terminal
    ];

    /** Labels affichage */
    public const STATUS_LABELS = [
        'emis'     => 'Émis',
        'embarque' => 'Embarqué',
        'valide'   => 'Validé',
        'arrive'   => 'Arrivé',
        'annule'   => 'Annulé',
    ];

    public const PAYMENT_LABELS = [
        'en_attente'        => 'En attente de paiement',
        'paye'              => 'Payé',
        'rembourse_partiel' => 'Remboursé partiellement',
        'rembourse'         => 'Remboursé',
    ];

    public const STATUS_COLORS = [
        'emis'     => 'bg-amber-50 text-amber-700 border-amber-200',
        'embarque' => 'bg-blue-50 text-blue-700 border-blue-200',
        'valide'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'arrive'   => 'bg-slate-100 text-slate-600',
        'annule'   => 'bg-rose-50 text-rose-600 border-rose-200',
    ];

    public const PAYMENT_COLORS = [
        'en_attente'        => 'bg-orange-50 text-orange-700 border-orange-200',
        'paye'              => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'rembourse_partiel' => 'bg-amber-50 text-amber-700 border-amber-200',
        'rembourse'         => 'bg-rose-50 text-rose-600 border-rose-200',
    ];

    /** Vérifie si la transition opérationnelle est autorisée. */
    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Vérifie si la transition de paiement est autorisée. */
    public static function canTransitionPayment(string $from, string $to): bool
    {
        return in_array($to, self::PAYMENT_TRANSITIONS[$from] ?? [], true);
    }

    /** Applique la transition opérationnelle ou lance une exception. */
    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            $fromLabel = self::STATUS_LABELS[$from] ?? $from;
            $toLabel   = self::STATUS_LABELS[$to] ?? $to;
            throw new \RuntimeException(
                "Transition de statut interdite : « {$fromLabel} » → « {$toLabel} »."
            );
        }
    }

    /** Applique la transition de paiement ou lance une exception. */
    public static function assertPaymentTransition(string $from, string $to): void
    {
        if (!self::canTransitionPayment($from, $to)) {
            $fromLabel = self::PAYMENT_LABELS[$from] ?? $from;
            $toLabel   = self::PAYMENT_LABELS[$to] ?? $to;
            throw new \RuntimeException(
                "Transition de paiement interdite : « {$fromLabel} » → « {$toLabel} »."
            );
        }
    }

    /** Un billet peut-il être annulé dans son état actuel ? */
    public static function canCancel(string $status): bool
    {
        return self::canTransition($status, 'annule');
    }

    /** Un billet peut-il être remboursé dans son état de paiement actuel ? */
    public static function canRefund(string $paymentStatus): bool
    {
        return in_array($paymentStatus, ['paye', 'rembourse_partiel'], true);
    }

    /** Le billet est-il dans un état terminal (plus aucune transition possible) ? */
    public static function isTerminal(string $status): bool
    {
        return empty(self::TRANSITIONS[$status] ?? []);
    }
}
