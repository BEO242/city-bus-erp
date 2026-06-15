<?php

declare(strict_types=1);

namespace CityBus\StateMachines;

/**
 * Machine à états pour les items fret (bagages et colis).
 *
 * Statut opérationnel : enregistre → charge → en_transit → arrive → retire → annule
 * Statut de paiement  : en_attente → paye → rembourse_partiel → rembourse | non_applicable
 *
 * Règles :
 *  - Un colis payé ne peut PAS revenir en_attente
 *  - Un colis remboursé ne peut PAS être re-payé
 *  - Annulation possible depuis : enregistre, charge (pas en_transit/arrive/retire)
 *  - Franchise (is_franchise=1) → payment_status = non_applicable
 *  - Le paiement est requis AVANT le chargement (transition charge bloquée si en_attente)
 */
final class FretStateMachine
{
    /** Transitions opérationnelles autorisées */
    public const TRANSITIONS = [
        'enregistre' => ['charge', 'annule'],
        'charge'     => ['en_transit', 'annule'],
        'en_transit' => ['arrive'],
        'arrive'     => ['retire'],
        'retire'     => [],         // état terminal
        'annule'     => [],         // état terminal
    ];

    /** Transitions de paiement autorisées */
    public const PAYMENT_TRANSITIONS = [
        'en_attente'        => ['paye', 'rembourse'],   // rembourse = annulation sans paiement
        'paye'              => ['rembourse_partiel', 'rembourse'],
        'rembourse_partiel' => ['rembourse'],
        'rembourse'         => [],
        'non_applicable'    => [],                       // franchise, pas de paiement
    ];

    /** Labels affichage */
    public const STATUS_LABELS = [
        'enregistre' => 'Enregistré',
        'charge'     => 'Chargé',
        'en_transit' => 'En transit',
        'arrive'     => 'Arrivé',
        'retire'     => 'Retiré',
        'annule'     => 'Annulé',
    ];

    public const PAYMENT_LABELS = [
        'en_attente'        => 'En attente de paiement',
        'paye'              => 'Payé',
        'rembourse_partiel' => 'Remboursé partiellement',
        'rembourse'         => 'Remboursé',
        'non_applicable'    => 'Franchise (gratuit)',
    ];

    public const STATUS_COLORS = [
        'enregistre' => 'bg-amber-50 text-amber-700 border-amber-200',
        'charge'     => 'bg-blue-50 text-blue-700 border-blue-200',
        'en_transit' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'arrive'     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'retire'     => 'bg-slate-100 text-slate-600',
        'annule'     => 'bg-rose-50 text-rose-600 border-rose-200',
    ];

    public const PAYMENT_COLORS = [
        'en_attente'        => 'bg-orange-50 text-orange-700 border-orange-200',
        'paye'              => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'rembourse_partiel' => 'bg-amber-50 text-amber-700 border-amber-200',
        'rembourse'         => 'bg-rose-50 text-rose-600 border-rose-200',
        'non_applicable'    => 'bg-slate-50 text-slate-500',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function canTransitionPayment(string $from, string $to): bool
    {
        return in_array($to, self::PAYMENT_TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            $fromLabel = self::STATUS_LABELS[$from] ?? $from;
            $toLabel   = self::STATUS_LABELS[$to] ?? $to;
            throw new \RuntimeException(
                "Transition de statut fret interdite : « {$fromLabel} » → « {$toLabel} »."
            );
        }
    }

    public static function assertPaymentTransition(string $from, string $to): void
    {
        if (!self::canTransitionPayment($from, $to)) {
            $fromLabel = self::PAYMENT_LABELS[$from] ?? $from;
            $toLabel   = self::PAYMENT_LABELS[$to] ?? $to;
            throw new \RuntimeException(
                "Transition de paiement fret interdite : « {$fromLabel} » → « {$toLabel} »."
            );
        }
    }

    /**
     * Vérifie qu'on peut charger un colis (paiement requis sauf franchise).
     */
    public static function canLoad(string $status, string $paymentStatus): bool
    {
        if ($status !== 'enregistre') return false;
        // Franchise ou déjà payé → OK pour charger
        return in_array($paymentStatus, ['paye', 'non_applicable'], true);
    }

    public static function canCancel(string $status): bool
    {
        return self::canTransition($status, 'annule');
    }

    public static function canRefund(string $paymentStatus): bool
    {
        return in_array($paymentStatus, ['paye', 'rembourse_partiel'], true);
    }

    public static function isTerminal(string $status): bool
    {
        return empty(self::TRANSITIONS[$status] ?? []);
    }
}
