<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$typeMeta = [
    'passage_arret' => ['label' => 'Passage arrêt',  'cls' => 'bg-cb-bg text-cb-primary border-cb-primary/20',   'icon' => 'map-pin'],
    'passage_final' => ['label' => 'Passage final',  'cls' => 'bg-indigo-50 text-indigo-700 border-indigo-200',  'icon' => 'flag'],
    'arret_route'   => ['label' => 'Arrêt route',    'cls' => 'bg-teal-50 text-teal-700 border-teal-200',        'icon' => 'map-pin'],
    'passager'      => ['label' => 'Passager',        'cls' => 'bg-cb-bg text-cb-primary border-cb-primary/20',   'icon' => 'user'],
];
$tm = $typeMeta[$ticket['ticket_type'] ?? ''] ?? ['label' => ucfirst(str_replace('_', ' ', $ticket['ticket_type'] ?? '')), 'cls' => 'bg-slate-100 text-slate-600 border-slate-200', 'icon' => 'ticket'];

$statusMeta = [
    'emis'     => ['label' => 'Émis',     'cls' => 'bg-cb-bg text-cb-primary border border-cb-primary/20',     'dot' => 'bg-cb-primary'],
    'embarque' => ['label' => 'Embarqué', 'cls' => 'bg-blue-50 text-blue-700 border border-blue-200',         'dot' => 'bg-blue-500'],
    'valide'   => ['label' => 'Validé',   'cls' => 'bg-emerald-50 text-emerald-700 border border-emerald-200', 'dot' => 'bg-emerald-500'],
    'arrive'   => ['label' => 'Arrivé',   'cls' => 'bg-emerald-100 text-emerald-800 border border-emerald-300','dot' => 'bg-emerald-600'],
    'annule'   => ['label' => 'Annulé',   'cls' => 'bg-rose-50 text-rose-600 border border-rose-200',          'dot' => 'bg-rose-400'],
];
$sm = $statusMeta[$ticket['status']] ?? $statusMeta['emis'];

$soldBy = trim(($ticket['sold_by_first'] ?? '') . ' ' . ($ticket['sold_by_last'] ?? '')) ?: '—';
?>
<?php
use CityBus\StateMachines\TicketStateMachine;
$paymentMeta = TicketStateMachine::PAYMENT_COLORS;
$paymentLabels = TicketStateMachine::PAYMENT_LABELS;
$pStatus = $ticket['payment_status'] ?? 'paye';
$pLabel  = $paymentLabels[$pStatus] ?? $pStatus;
$pColor  = $paymentMeta[$pStatus] ?? 'bg-slate-100 text-slate-600';
?>
<?php $view->start('content') ?>
<div class="space-y-5" x-data="{ cancelOpen: false, refundOpen: false, payOpen: false }" x-cloak>

  <!-- Fil d'Ariane -->
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('billetterie')) ?>" class="hover:text-cb-primary transition inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Billets passagers
    </a>
    <span>/</span>
    <span class="text-slate-800 font-mono font-semibold"><?= e($ticket['ticket_number']) ?></span>
  </div>

  <!-- En-tête -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
    <div class="flex flex-wrap gap-4 items-start justify-between">
      <div class="flex items-start gap-4">
        <div class="w-14 h-14 rounded-2xl bg-cb-bg flex items-center justify-center shrink-0">
          <i data-lucide="ticket" class="w-7 h-7 text-cb-primary"></i>
        </div>
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-2xl font-bold font-mono text-slate-900"><?= e($ticket['ticket_number']) ?></h1>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-semibold <?= $sm['cls'] ?>">
              <span class="w-1.5 h-1.5 rounded-full <?= $sm['dot'] ?>"></span>
              <?= $sm['label'] ?>
            </span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?= $pColor ?>"><?= e($pLabel) ?></span>
            <span class="px-2 py-0.5 rounded-full text-xs border <?= $tm['cls'] ?>"><?= $tm['label'] ?></span>
          </div>
          <p class="text-slate-500 text-sm mt-1 flex flex-wrap items-center gap-3">
            <span class="flex items-center gap-1">
              <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
              Émis le <?= e(date('d/m/Y à H:i', strtotime($ticket['sold_at']))) ?>
            </span>
            <span class="flex items-center gap-1">
              <i data-lucide="user-check" class="w-3.5 h-3.5"></i>
              Par <?= e($soldBy) ?>
            </span>
          </p>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="<?= e(url('billetterie/'.$ticket['id'].'/pdf')) ?>" target="_blank"
           class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-semibold inline-flex items-center gap-2 hover:bg-cb-dark transition shadow-soft">
          <i data-lucide="file-text" class="w-4 h-4"></i> Voir PDF
        </a>
        <a href="<?= e(url('billetterie/'.$ticket['id'].'/reprint')) ?>"
           class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-semibold inline-flex items-center gap-2 hover:bg-slate-50 transition">
          <i data-lucide="printer" class="w-4 h-4"></i> Réimprimer
        </a>
        <?php if ($pStatus === 'en_attente' && $ticket['status'] !== 'annule' && can('billetterie.create')): ?>
        <button @click="payOpen = true"
                class="px-4 py-2.5 rounded-xl border border-emerald-200 text-emerald-600 font-semibold inline-flex items-center gap-2 hover:bg-emerald-50 transition">
          <i data-lucide="credit-card" class="w-4 h-4"></i> Encaisser
        </button>
        <?php endif ?>
        <?php if (TicketStateMachine::canRefund($pStatus) && can('billetterie.cancel')): ?>
        <button @click="refundOpen = true"
                class="px-4 py-2.5 rounded-xl border border-amber-200 text-amber-600 font-semibold inline-flex items-center gap-2 hover:bg-amber-50 transition">
          <i data-lucide="undo-2" class="w-4 h-4"></i> Rembourser
        </button>
        <?php endif ?>
        <?php if ($ticket['status'] !== 'annule' && can('billetterie.cancel')): ?>
        <button @click="cancelOpen = true"
                class="px-4 py-2.5 rounded-xl border border-rose-200 text-rose-600 font-semibold inline-flex items-center gap-2 hover:bg-rose-50 transition">
          <i data-lucide="x-circle" class="w-4 h-4"></i> Annuler
        </button>
        <?php endif ?>

        <?php
        // Boutons de transition opérationnelle (pas de dropdown !)
        $allowedTicketTransitions = TicketStateMachine::TRANSITIONS[$ticket['status']] ?? [];
        $allowedTicketTransitions = array_filter($allowedTicketTransitions, fn($s) => $s !== 'annule'); // annulation traitée séparément
        $ticketTransitionMeta = [
            'embarque' => ['icon' => 'log-in',       'color' => 'bg-blue-600 hover:bg-blue-700 text-white',          'label' => 'Embarquer'],
            'valide'   => ['icon' => 'check-circle',  'color' => 'bg-emerald-600 hover:bg-emerald-700 text-white',    'label' => 'Valider'],
            'arrive'   => ['icon' => 'flag',           'color' => 'bg-slate-700 hover:bg-slate-800 text-white',        'label' => 'Marquer arrivé'],
        ];
        ?>
        <?php if (!empty($allowedTicketTransitions) && can('billetterie.create')): ?>
        <?php foreach ($allowedTicketTransitions as $nextSt):
            $tmeta = $ticketTransitionMeta[$nextSt] ?? ['icon' => 'arrow-right', 'color' => 'bg-indigo-600 hover:bg-indigo-700 text-white', 'label' => TicketStateMachine::STATUS_LABELS[$nextSt] ?? $nextSt];
        ?>
        <form method="post" action="<?= e(url('billetterie/'.$ticket['id'].'/status')) ?>" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="status" value="<?= e($nextSt) ?>">
          <button type="submit"
                  class="px-4 py-2.5 rounded-xl font-semibold inline-flex items-center gap-2 transition shadow-soft <?= $tmeta['color'] ?>">
            <i data-lucide="<?= e($tmeta['icon']) ?>" class="w-4 h-4"></i> <?= e($tmeta['label']) ?>
          </button>
        </form>
        <?php endforeach; ?>
        <?php endif ?>
      </div>
    </div>
    <?php if ($ticket['status'] === 'annule'): ?>
    <div class="mt-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-700 flex items-start gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i>
      <span>Annulé<?php if (!empty($ticket['cancelled_at'])): ?> le <?= e(date('d/m/Y H:i', strtotime($ticket['cancelled_at']))) ?><?php endif ?>
        <?php if (!empty($ticket['cancel_reason'])): ?> · <?= e($ticket['cancel_reason']) ?><?php endif ?></span>
    </div>
    <?php endif ?>
  </div>

  <!-- KPI -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Passager</span>
        <span class="w-7 h-7 bg-cb-bg rounded-lg flex items-center justify-center">
          <i data-lucide="user" class="w-3.5 h-3.5 text-cb-primary"></i>
        </span>
      </div>
      <div class="font-bold text-slate-900 truncate"><?= e($ticket['passenger_name']) ?></div>
      <div class="text-xs text-slate-400"><?= e($ticket['passenger_phone'] ?? '—') ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Siège</span>
        <span class="w-7 h-7 bg-cb-bg rounded-lg flex items-center justify-center">
          <i data-lucide="armchair" class="w-3.5 h-3.5 text-cb-primary"></i>
        </span>
      </div>
      <div class="font-bold text-2xl text-cb-primary"><?= !empty($ticket['seat_number']) ? (int)$ticket['seat_number'] : '—' ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Prix</span>
        <span class="w-7 h-7 bg-emerald-50 rounded-lg flex items-center justify-center">
          <i data-lucide="banknote" class="w-3.5 h-3.5 text-emerald-600"></i>
        </span>
      </div>
      <div class="font-bold text-xl text-emerald-600"><?= e(fcfa((int)$ticket['price_fcfa'])) ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Bagages</span>
        <span class="w-7 h-7 bg-amber-50 rounded-lg flex items-center justify-center">
          <i data-lucide="package" class="w-3.5 h-3.5 text-amber-600"></i>
        </span>
      </div>
      <div class="font-bold text-2xl text-amber-600"><?= count($baggageTickets ?? []) ?></div>
      <div class="text-xs text-slate-400">billet(s) bagage</div>
    </div>
  </div>

  <!-- Corps 2/3 + 1/3 -->
  <div class="grid lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-5">

      <!-- Détails billet -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2 pb-4 mb-4 border-b border-slate-100">
          <i data-lucide="ticket" class="w-4 h-4 text-cb-primary"></i> Détails du billet
        </h2>
        <div class="grid sm:grid-cols-2 gap-x-8 text-sm">
          <div class="flex justify-between py-2.5 border-b border-slate-50">
            <span class="text-slate-500">Nom passager</span>
            <span class="font-semibold text-slate-800"><?= e($ticket['passenger_name']) ?></span>
          </div>
          <div class="flex justify-between py-2.5 border-b border-slate-50">
            <span class="text-slate-500">Téléphone</span>
            <span><?= e($ticket['passenger_phone'] ?? '—') ?></span>
          </div>
          <div class="flex justify-between py-2.5 border-b border-slate-50">
            <span class="text-slate-500">Type</span>
            <span class="px-2 py-0.5 rounded-full text-xs border <?= $tm['cls'] ?>"><?= $tm['label'] ?></span>
          </div>
          <div class="flex justify-between py-2.5 border-b border-slate-50">
            <span class="text-slate-500">Catégorie</span>
            <span class="capitalize"><?= e($ticket['passenger_category'] ?? '—') ?></span>
          </div>
          <div class="flex justify-between py-2.5 border-b border-slate-50">
            <span class="text-slate-500">Classe</span>
            <span class="capitalize"><?= e($ticket['travel_class'] ?? '—') ?></span>
          </div>
          <div class="flex justify-between py-2.5 border-b border-slate-50">
            <span class="text-slate-500">Prix</span>
            <span class="font-bold text-emerald-600"><?= e(fcfa((int)$ticket['price_fcfa'])) ?></span>
          </div>
          <?php if (!empty($ticket['boarding_stop_name'])): ?>
          <div class="flex justify-between py-2.5 border-b border-slate-50">
            <span class="text-slate-500">Montée</span>
            <span><?= e($ticket['boarding_stop_name']) ?></span>
          </div>
          <?php endif ?>
          <?php if (!empty($ticket['alighting_stop_name'])): ?>
          <div class="flex justify-between py-2.5 border-b border-slate-50">
            <span class="text-slate-500">Descente</span>
            <span><?= e($ticket['alighting_stop_name']) ?></span>
          </div>
          <?php endif ?>
          <div class="flex justify-between py-2.5 border-b border-slate-50 sm:col-span-2">
            <span class="text-slate-500">Vendu par</span>
            <span><?= e($soldBy) ?></span>
          </div>
        </div>
      </div>

      <!-- Bagages associés -->
      <?php if (!empty($baggageTickets)): ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2">
            <i data-lucide="package" class="w-4 h-4 text-amber-500"></i> Billets bagages associés
          </h2>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            <?= count($baggageTickets) ?>
          </span>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
            <tr>
              <th class="px-4 py-3 text-left">N° billet</th>
              <th class="px-4 py-3 text-left">Nature</th>
              <th class="px-4 py-3 text-right">Masse</th>
              <th class="px-4 py-3 text-right">Prix</th>
              <th class="px-4 py-3 text-center">Statut</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($baggageTickets as $bt): ?>
            <tr class="hover:bg-slate-50/60 transition">
              <td class="px-4 py-3 font-mono text-xs font-semibold text-amber-700"><?= e($bt['ticket_number']) ?></td>
              <td class="px-4 py-3 text-slate-600"><?= e($bt['nature_label'] ?? '—') ?></td>
              <td class="px-4 py-3 text-right"><?= !empty($bt['weight_kg']) ? number_format((float)$bt['weight_kg'], 1) . ' kg' : '—' ?></td>
              <td class="px-4 py-3 text-right font-semibold"><?= e(fcfa((int)$bt['total_price_fcfa'])) ?></td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs border <?= $bt['status'] === 'emis' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-600 border-rose-200' ?>">
                  <?= $bt['status'] === 'emis' ? 'Émis' : 'Annulé' ?>
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <a href="<?= e(url('billetterie-bagages/' . $bt['id'])) ?>"
                   class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition inline-flex">
                  <i data-lucide="eye" class="w-4 h-4"></i>
                </a>
              </td>
            </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php endif ?>

    </div>

    <!-- Colonne latérale voyage -->
    <div class="space-y-5">
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-4">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2 pb-3 border-b border-slate-100">
          <i data-lucide="route" class="w-4 h-4 text-cb-primary"></i> Voyage
        </h2>
        <div class="space-y-3 text-sm">
          <div>
            <p class="text-xs text-slate-400 uppercase tracking-wide mb-0.5">Ligne</p>
            <p class="font-semibold text-slate-900"><?= e($ticket['line_code']) ?> — <?= e($ticket['line_name']) ?></p>
          </div>
          <div>
            <p class="text-xs text-slate-400 uppercase tracking-wide mb-0.5">Date</p>
            <p class="font-medium"><?= e(date('d/m/Y', strtotime($ticket['trip_date']))) ?></p>
          </div>
          <div>
            <p class="text-xs text-slate-400 uppercase tracking-wide mb-0.5">Départ prévu</p>
            <p class="font-medium"><?= e(date('H:i', strtotime($ticket['departure_scheduled']))) ?></p>
          </div>
          <div>
            <p class="text-xs text-slate-400 uppercase tracking-wide mb-0.5">Véhicule</p>
            <p class="font-mono font-semibold text-cb-primary"><?= e($ticket['bus_code']) ?></p>
          </div>
        </div>
        <a href="<?= e(url('voyages/' . $ticket['trip_id'])) ?>"
           class="mt-2 w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-semibold inline-flex items-center justify-center gap-2 hover:bg-slate-50 transition">
          <i data-lucide="external-link" class="w-4 h-4"></i> Voir le voyage
        </a>
      </div>

      <?php if (can('billetterie.create') && $ticket['status'] !== 'annule'): ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
        <h2 class="font-semibold text-slate-800 text-sm flex items-center gap-2 pb-3 mb-3 border-b border-slate-100">
          <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i> Actions rapides
        </h2>
        <a href="<?= e(url('billetterie-bagages/sale?passenger_ticket_id=' . $ticket['id'])) ?>"
           class="w-full px-4 py-2.5 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 text-sm font-semibold inline-flex items-center gap-2 hover:bg-amber-100 transition">
          <i data-lucide="package-plus" class="w-4 h-4"></i> Ajouter un bagage
        </a>
      </div>
      <?php endif ?>
    </div>
  </div>

  <!-- Modal paiement -->
  <?php if ($pStatus === 'en_attente' && $ticket['status'] !== 'annule' && can('billetterie.create')): ?>
  <div x-show="payOpen" x-cloak
       class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @keydown.escape.window="payOpen = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4" @click.stop>
      <h3 class="text-lg font-bold text-slate-900">Encaisser le billet</h3>
      <p class="text-sm text-slate-500">Montant : <span class="font-bold text-emerald-600"><?= e(fcfa((int)$ticket['price_fcfa'])) ?></span></p>
      <form method="post" action="<?= e(url('billetterie/'.$ticket['id'].'/pay')) ?>" class="space-y-3">
        <?= csrf_field() ?>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Mode de paiement</label>
          <select name="payment_method" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm">
            <option value="especes">Espèces</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="carte">Carte</option>
            <option value="virement">Virement</option>
          </select>
        </div>
        <div class="flex gap-2 justify-end pt-1">
          <button type="button" @click="payOpen = false"
                  class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
            Annuler
          </button>
          <button type="submit"
                  class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition">
            Confirmer le paiement
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif ?>

  <!-- Modal remboursement -->
  <?php if (TicketStateMachine::canRefund($pStatus) && can('billetterie.cancel')): ?>
  <div x-show="refundOpen" x-cloak
       class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @keydown.escape.window="refundOpen = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4" @click.stop>
      <h3 class="text-lg font-bold text-slate-900">Rembourser le billet</h3>
      <?php
        $maxRefundable = (int)($ticket['paid_amount_fcfa'] ?? $ticket['price_fcfa']) - (int)($ticket['refund_amount_fcfa'] ?? 0);
      ?>
      <p class="text-sm text-slate-500">Maximum remboursable : <span class="font-bold"><?= e(fcfa($maxRefundable)) ?></span></p>
      <form method="post" action="<?= e(url('billetterie/'.$ticket['id'].'/refund')) ?>" class="space-y-3">
        <?= csrf_field() ?>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Montant (FCFA) <span class="text-rose-500">*</span></label>
          <input type="number" name="refund_amount" required min="1" max="<?= $maxRefundable ?>" value="<?= $maxRefundable ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Motif <span class="text-rose-500">*</span></label>
          <textarea name="reason" required minlength="5" rows="2" placeholder="Raison du remboursement..."
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm resize-none"></textarea>
        </div>
        <div class="flex gap-2 justify-end pt-1">
          <button type="button" @click="refundOpen = false"
                  class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
            Fermer
          </button>
          <button type="submit"
                  class="px-4 py-2.5 rounded-xl bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 transition">
            Confirmer le remboursement
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif ?>

  <!-- Modal annulation -->
  <?php if ($ticket['status'] !== 'annule' && can('billetterie.cancel')): ?>
  <div x-show="cancelOpen" x-cloak
       class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       @keydown.escape.window="cancelOpen = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4" @click.stop>
      <div class="flex items-start justify-between">
        <div>
          <h3 class="text-lg font-bold text-slate-900">Annuler le billet</h3>
          <p class="text-sm text-slate-500 mt-1">Billet <span class="font-mono font-semibold text-cb-primary"><?= e($ticket['ticket_number']) ?></span></p>
        </div>
        <button @click="cancelOpen = false" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 transition">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-700 flex items-start gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0"></i>
        <span>Cette action est irréversible. Le billet sera marqué comme annulé.</span>
      </div>
      <form method="post" action="<?= e(url('billetterie/'.$ticket['id'].'/cancel')) ?>" class="space-y-3">
        <?= csrf_field() ?>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Motif <span class="text-rose-500">*</span></label>
          <textarea name="reason" required minlength="5" rows="3"
                    placeholder="Raison de l'annulation…"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-rose-400 focus:ring-2 focus:ring-rose-100 outline-none text-sm resize-none"></textarea>
        </div>
        <div class="flex gap-2 justify-end pt-1">
          <button type="button" @click="cancelOpen = false"
                  class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
            Fermer
          </button>
          <button type="submit"
                  class="px-4 py-2.5 rounded-xl bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700 transition">
            Confirmer l'annulation
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif ?>

</div>
<?php $view->end() ?>
