<?php
/**
 * Fret item detail page
 */
use CityBus\StateMachines\FretStateMachine;

$statuses = FretStateMachine::STATUS_LABELS;
$statusColors = FretStateMachine::STATUS_COLORS;

$currentStatus = $item['status'] ?? 'enregistre';
$statusLabel = $statuses[$currentStatus] ?? $currentStatus;
$statusColor = $statusColors[$currentStatus] ?? 'bg-gray-100 text-gray-800';
$isBagage = ($item['item_type'] ?? '') === 'bagage' || ($item['item_type'] ?? '') === 'baggage';
$isColis = ($item['item_type'] ?? '') === 'colis';

// Payment status
$payStatus = $item['payment_status'] ?? 'en_attente';
$payLabel  = FretStateMachine::PAYMENT_LABELS[$payStatus] ?? $payStatus;
$payColor  = FretStateMachine::PAYMENT_COLORS[$payStatus] ?? 'bg-slate-100 text-slate-600';

$view->extends('layouts/app');
$view->start('content');
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Header bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div class="flex items-center gap-4 flex-wrap">
            <h1 class="text-2xl font-mono font-bold text-gray-900 tracking-wider">
                <?= e($item['tracking_code']) ?>
            </h1>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?= $statusColor ?>">
                <?= e($statusLabel) ?>
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?= $payColor ?>">
                <?= e($payLabel) ?>
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                <?= $isBagage ? 'Bagage passager' : 'Colis' ?>
            </span>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <?php if ($payStatus === 'en_attente' && $currentStatus !== 'annule' && can('fret.edit')): ?>
            <form method="post" action="<?= url('operations/fret/' . $item['id'] . '/pay') ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="payment_method" value="especes">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                    Encaisser (<?= e(fcfa((int)$item['total_price_fcfa'])) ?>)
                </button>
            </form>
            <?php endif; ?>
            <?php if (FretStateMachine::canRefund($payStatus) && can('fret.cancel')): ?>
            <button onclick="document.getElementById('refundModal').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-amber-300 text-amber-700 text-sm font-medium rounded-lg hover:bg-amber-50 transition">
                <i data-lucide="undo-2" class="w-4 h-4"></i>
                Rembourser
            </button>
            <?php endif; ?>
            <?php if (can('fret.print_talon')): ?>
                <a href="<?= url('operations/fret/' . $item['id'] . '/talon') ?>"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Imprimer le talon
                </a>
            <?php endif; ?>
            <a href="<?= url('operations/fret') ?>"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Retour
            </a>
        </div>
    </div>

    <!-- Two-column grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left column -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Card 1: Détails de l'envoi -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="package" class="w-5 h-5 text-indigo-500"></i>
                    Détails de l'envoi
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Type</p>
                        <p class="text-sm text-gray-900"><?= $isBagage ? 'Bagage passager' : 'Colis' ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Catégorie</p>
                        <p class="text-sm text-gray-900 flex items-center gap-2">
                            <?php if (!empty($item['category_color'])): ?>
                                <span class="inline-block w-3 h-3 rounded-full" style="background-color: <?= e($item['category_color']) ?>"></span>
                            <?php endif; ?>
                            <?= e($item['category_label'] ?? '—') ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Poids</p>
                        <p class="text-sm text-gray-900"><?= number_format((float)($item['weight_kg'] ?? 0), 2, ',', ' ') ?> kg</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Pièces</p>
                        <p class="text-sm text-gray-900"><?= (int)($item['pieces_count'] ?? 1) ?></p>
                    </div>
                    <?php if (!empty($item['description'])): ?>
                        <div class="sm:col-span-2">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Description</p>
                            <p class="text-sm text-gray-900"><?= e($item['description']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 2: Expéditeur -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="user" class="w-5 h-5 text-indigo-500"></i>
                    Expéditeur
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Nom</p>
                        <p class="text-sm text-gray-900"><?= e($item['sender_name'] ?? '—') ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Téléphone</p>
                        <p class="text-sm text-gray-900"><?= e($item['sender_phone'] ?? '—') ?></p>
                    </div>
                    <?php if ($isBagage && !empty($item['linked_ticket_number'])): ?>
                        <div class="sm:col-span-2">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Billet associé</p>
                            <p class="text-sm text-gray-900">
                                <span class="font-mono font-medium"><?= e($item['linked_ticket_number']) ?></span>
                                <?php if (!empty($item['linked_passenger_name'])): ?>
                                    — <?= e($item['linked_passenger_name']) ?>
                                <?php endif; ?>
                                <?php if (!empty($item['seat_number'])): ?>
                                    — Siège <?= e($item['seat_number']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 3: Destinataire (colis only) -->
            <?php if ($isColis): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="user-check" class="w-5 h-5 text-indigo-500"></i>
                        Destinataire
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Nom</p>
                            <p class="text-sm text-gray-900"><?= e($item['recipient_name'] ?? '—') ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Téléphone</p>
                            <p class="text-sm text-gray-900"><?= e($item['recipient_phone'] ?? '—') ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Card 4: Origine & Destination -->
            <?php if (!empty($item['origin_agency_name']) || !empty($item['destination_agency_name']) || !empty($item['origin_stop_name']) || !empty($item['destination_stop_name'])): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-5 h-5 text-indigo-500"></i>
                        Origine &amp; Destination
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Origine -->
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Départ</p>
                            <?php if (!empty($item['origin_agency_name'])): ?>
                            <div>
                                <p class="text-xs text-slate-500">Agence</p>
                                <p class="text-sm text-gray-900 font-medium"><?= e($item['origin_agency_name']) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($item['origin_stop_name'])): ?>
                            <div>
                                <p class="text-xs text-slate-500">Arrêt</p>
                                <p class="text-sm text-gray-900 font-medium flex items-center gap-1.5">
                                    <i data-lucide="circle-dot" class="w-3 h-3 text-emerald-500"></i>
                                    <?= e($item['origin_stop_name']) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Destination -->
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-cb-primary uppercase tracking-wide">Arrivée</p>
                            <?php if (!empty($item['destination_agency_name'])): ?>
                            <div>
                                <p class="text-xs text-slate-500">Agence</p>
                                <p class="text-sm text-gray-900 font-medium"><?= e($item['destination_agency_name']) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($item['destination_stop_name'])): ?>
                            <div>
                                <p class="text-xs text-slate-500">Arrêt</p>
                                <p class="text-sm text-gray-900 font-medium flex items-center gap-1.5">
                                    <i data-lucide="map-pin" class="w-3 h-3 text-cb-primary"></i>
                                    <?= e($item['destination_stop_name']) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Card 5: Voyage (if trip linked) -->
            <?php if (!empty($item['trip_date'])): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="bus" class="w-5 h-5 text-indigo-500"></i>
                        Voyage
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Ligne</p>
                            <p class="text-sm text-gray-900">
                                <span class="font-mono font-medium"><?= e($item['line_code'] ?? '') ?></span>
                                <?php if (!empty($item['line_name'])): ?>
                                    — <?= e($item['line_name']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Date</p>
                            <p class="text-sm text-gray-900">
                                <?= date('d/m/Y', strtotime($item['trip_date'])) ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Départ</p>
                            <p class="text-sm text-gray-900">
                                <?= !empty($item['departure_scheduled']) ? date('H:i', strtotime($item['departure_scheduled'])) : '—' ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Right column (Sidebar) -->
        <div class="lg:col-span-1 space-y-6">

            <!-- Card: Tarification -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="banknote" class="w-5 h-5 text-indigo-500"></i>
                    Tarification
                </h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Prix / kg</p>
                        <p class="text-sm text-gray-900"><?= number_format((float)($item['price_per_kg'] ?? 0), 0, ',', ' ') ?> F/kg</p>
                    </div>
                    <?php if (!empty($item['min_price_fcfa']) && (float)$item['min_price_fcfa'] > 0): ?>
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Minimum</p>
                            <p class="text-sm text-gray-900"><?= number_format((float)$item['min_price_fcfa'], 0, ',', ' ') ?> FCFA</p>
                        </div>
                    <?php endif; ?>
                    <div class="pt-2 border-t border-gray-100">
                        <?php if (!empty($item['is_franchise'])): ?>
                            <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-bold bg-green-100 text-green-800">
                                FRANCHISE
                            </span>
                            <p class="text-2xl font-bold text-gray-400 mt-2">0 FCFA</p>
                        <?php else: ?>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Total</p>
                            <p class="text-2xl font-bold text-red-600">
                                <?= number_format((float)($item['total_price_fcfa'] ?? 0), 0, ',', ' ') ?> FCFA
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Card: Statut & Actions -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="activity" class="w-5 h-5 text-indigo-500"></i>
                    Statut & Actions
                </h2>

                <!-- Current status -->
                <div class="mb-4">
                    <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-bold <?= $statusColor ?>">
                        <?= e($statusLabel) ?>
                    </span>
                </div>

                <?php if ($currentStatus !== 'annule'): ?>
                    <?php
                    // Déterminer les transitions autorisées depuis l'état actuel
                    $allowedNext = FretStateMachine::TRANSITIONS[$currentStatus] ?? [];
                    // Retirer 'annule' des boutons de transition (traité séparément)
                    $allowedNext = array_filter($allowedNext, fn($s) => $s !== 'annule');

                    // Icônes & couleurs pour chaque état cible
                    $transitionMeta = [
                        'charge'     => ['icon' => 'package-check', 'color' => 'bg-blue-600 hover:bg-blue-700',     'label' => 'Charger'],
                        'en_transit' => ['icon' => 'truck',         'color' => 'bg-indigo-600 hover:bg-indigo-700', 'label' => 'Mettre en transit'],
                        'arrive'     => ['icon' => 'map-pin',       'color' => 'bg-emerald-600 hover:bg-emerald-700','label' => 'Marquer arrivé'],
                        'retire'     => ['icon' => 'user-check',    'color' => 'bg-slate-700 hover:bg-slate-800',   'label' => 'Marquer retiré'],
                    ];
                    ?>

                    <?php if (!empty($allowedNext)): ?>
                    <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Avancer le statut</label>
                    <div class="space-y-2 mb-4">
                        <?php foreach ($allowedNext as $nextStatus):
                            $meta = $transitionMeta[$nextStatus] ?? ['icon' => 'arrow-right', 'color' => 'bg-indigo-600 hover:bg-indigo-700', 'label' => $statuses[$nextStatus] ?? $nextStatus];
                            // Vérifier si la transition charge est bloquée par le paiement
                            $disabled = ($nextStatus === 'charge' && !FretStateMachine::canLoad($currentStatus, $payStatus));
                        ?>
                        <form action="<?= url('operations/fret/' . $item['id'] . '/status') ?>" method="POST" class="w-full">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                            <button type="submit" <?= $disabled ? 'disabled' : '' ?>
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 <?= $disabled ? 'bg-gray-300 cursor-not-allowed' : $meta['color'] ?> text-white text-sm font-medium rounded-lg transition">
                                <i data-lucide="<?= e($meta['icon']) ?>" class="w-4 h-4"></i>
                                <?= e($meta['label']) ?>
                            </button>
                            <?php if ($disabled): ?>
                            <p class="text-xs text-amber-600 mt-1 flex items-center gap-1">
                                <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                Paiement requis avant le chargement
                            </p>
                            <?php endif; ?>
                        </form>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (FretStateMachine::canCancel($currentStatus) && can('fret.cancel')): ?>
                    <div class="border-t border-gray-200 my-4"></div>

                    <!-- Annulation via bouton + motif -->
                    <div x-data="{ showCancel: false }">
                        <button type="button" @click="showCancel = !showCancel"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition">
                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                            Annuler cet envoi
                        </button>
                        <form x-show="showCancel" x-cloak x-transition
                              action="<?= url('operations/fret/' . $item['id'] . '/cancel') ?>" method="POST" class="mt-3 space-y-3">
                            <?= csrf_field() ?>
                            <textarea name="reason" rows="3" required minlength="5"
                                      placeholder="Motif de l'annulation..."
                                      class="w-full rounded-lg border-gray-300 text-sm focus:ring-red-500 focus:border-red-500"></textarea>
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                Confirmer l'annulation
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="border-t border-gray-200 my-4"></div>

                <!-- Meta info -->
                <div class="space-y-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Enregistré le</p>
                        <p class="text-sm text-gray-900">
                            <?= !empty($item['created_at']) ? date('d/m/Y à H:i', strtotime($item['created_at'])) : '—' ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Par</p>
                        <p class="text-sm text-gray-900"><?= e($item['registered_by_name'] ?? '—') ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Talon imprimé</p>
                        <p class="text-sm text-gray-900">
                            <?php if (!empty($item['talon_printed_at'])): ?>
                                <span class="text-green-600 font-medium">Oui</span>
                                — <?= date('d/m/Y à H:i', strtotime($item['talon_printed_at'])) ?>
                            <?php else: ?>
                                <span class="text-gray-400">Non</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Refund Modal -->
<?php if (FretStateMachine::canRefund($payStatus) && can('fret.cancel')): ?>
<div id="refundModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
             onclick="document.getElementById('refundModal').classList.add('hidden')"></div>

        <!-- Modal panel -->
        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 z-10">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <i data-lucide="undo-2" class="w-5 h-5 text-amber-600"></i>
                    Rembourser cet envoi
                </h3>
                <button onclick="document.getElementById('refundModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="<?= url('operations/fret/' . $item['id'] . '/refund') ?>">
                <?= csrf_field() ?>

                <div class="space-y-4">
                    <!-- Montant payé (info) -->
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Montant payé</p>
                        <p class="text-lg font-bold text-gray-900"><?= number_format((float)($item['total_price_fcfa'] ?? $item['total_price'] ?? 0), 0, ',', ' ') ?> FCFA</p>
                    </div>

                    <!-- Montant à rembourser -->
                    <div>
                        <label for="refund_amount" class="block text-sm font-medium text-gray-700 mb-1">
                            Montant à rembourser (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="refund_amount" id="refund_amount" required
                               min="1" max="<?= (int)($item['total_price_fcfa'] ?? $item['total_price'] ?? 0) ?>"
                               value="<?= (int)($item['total_price_fcfa'] ?? $item['total_price'] ?? 0) ?>"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        <p class="text-xs text-gray-500 mt-1">Laissez le montant total pour un remboursement complet.</p>
                    </div>

                    <!-- Motif -->
                    <div>
                        <label for="refund_reason" class="block text-sm font-medium text-gray-700 mb-1">
                            Motif du remboursement <span class="text-red-500">*</span>
                        </label>
                        <textarea name="reason" id="refund_reason" rows="3" required minlength="5"
                                  placeholder="Ex: Colis non expédié, erreur de tarification..."
                                  class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                    <button type="button"
                            onclick="document.getElementById('refundModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition">
                        Confirmer le remboursement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php $view->end(); ?>
