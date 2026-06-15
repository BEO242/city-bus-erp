<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="space-y-5 max-w-3xl mx-auto">

  <!-- En-tête -->
  <div class="flex items-center gap-3">
    <a href="<?= e(url('billetterie-bagages')) ?>"
       class="p-2 rounded-lg text-slate-500 hover:text-amber-500 hover:bg-amber-50 transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold flex items-center gap-2">
        <i data-lucide="package" class="w-6 h-6 text-amber-500"></i>
        <?= e($ticket['ticket_number']) ?>
      </h1>
      <p class="text-sm text-slate-500">Billet bagage émis le <?= e(date('d/m/Y à H:i', strtotime($ticket['sold_at']))) ?></p>
    </div>
    <div class="ml-auto flex items-center gap-2">
      <a href="<?= e(url('billetterie-bagages/' . $ticket['id'] . '/pdf')) ?>" target="_blank"
         class="px-3 py-2 rounded-xl border border-rose-200 text-rose-600 text-sm font-medium inline-flex items-center gap-2 hover:bg-rose-50 transition">
        <i data-lucide="file-text" class="w-4 h-4"></i> PDF
      </a>
      <a href="<?= e(url('billetterie-bagages/' . $ticket['id'] . '/print')) ?>"
         class="px-3 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition">
        <i data-lucide="printer" class="w-4 h-4"></i> Imprimer
      </a>
    </div>
  </div>

  <!-- Statut -->
  <div class="flex items-center gap-3">
    <span class="px-3 py-1.5 rounded-xl border text-sm font-semibold
      <?= $ticket['status'] === 'emis' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-600 border-rose-200' ?>">
      <?= $ticket['status'] === 'emis' ? '✓ Émis' : '✗ Annulé' ?>
    </span>
    <?php if ($ticket['status'] === 'annule' && $ticket['cancelled_at']): ?>
      <span class="text-sm text-slate-400">
        Annulé le <?= e(date('d/m/Y H:i', strtotime($ticket['cancelled_at']))) ?>
        <?php if ($ticket['cancel_reason']): ?> · <?= e($ticket['cancel_reason']) ?><?php endif ?>
      </span>
    <?php endif ?>
  </div>

  <div class="grid sm:grid-cols-2 gap-5">

    <!-- Voyage & nature -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h3 class="font-semibold text-slate-800 flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="route" class="w-4 h-4 text-amber-500"></i> Voyage
      </h3>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between">
          <span class="text-slate-500">Ligne</span>
          <span class="font-semibold"><?= e($ticket['line_code']) ?> — <?= e($ticket['line_name']) ?></span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Date</span>
          <span><?= e(date('d/m/Y', strtotime($ticket['trip_date']))) ?></span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Départ</span>
          <span><?= e(date('H:i', strtotime($ticket['departure_scheduled']))) ?></span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-slate-500">Nature</span>
          <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border text-xs font-medium <?= e($ticket['nature_color']) ?>">
            <i data-lucide="<?= e($ticket['nature_icon']) ?>" class="w-3.5 h-3.5"></i>
            <?= e($ticket['nature_label']) ?>
          </span>
        </div>
        <?php if ($ticket['description']): ?>
          <div class="flex justify-between">
            <span class="text-slate-500">Description</span>
            <span class="text-right max-w-48 text-slate-700"><?= e($ticket['description']) ?></span>
          </div>
        <?php endif ?>
      </div>
    </div>

    <!-- Passager -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h3 class="font-semibold text-slate-800 flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="user" class="w-4 h-4 text-amber-500"></i> Passager propriétaire
      </h3>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between">
          <span class="text-slate-500">Nom</span>
          <span class="font-semibold"><?= e($ticket['passenger_name']) ?></span>
        </div>
        <?php if ($ticket['passenger_phone']): ?>
          <div class="flex justify-between">
            <span class="text-slate-500">Téléphone</span>
            <span><?= e($ticket['passenger_phone']) ?></span>
          </div>
        <?php endif ?>
        <?php if ($ticket['passenger_ticket_number']): ?>
          <div class="flex justify-between items-center">
            <span class="text-slate-500">Billet passager</span>
            <a href="<?= e(url('billetterie/' . $ticket['passenger_ticket_id'])) ?>"
               class="font-mono text-xs text-cb-primary hover:underline">
              <?= e($ticket['passenger_ticket_number']) ?>
              <?php if ($ticket['passenger_seat']): ?>· Siège <?= (int)$ticket['passenger_seat'] ?><?php endif ?>
            </a>
          </div>
        <?php endif ?>
        <div class="flex justify-between">
          <span class="text-slate-500">Vendu par</span>
          <span><?= e($ticket['sold_by_first'] . ' ' . $ticket['sold_by_last']) ?></span>
        </div>
      </div>
    </div>

    <!-- Mesures physiques -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h3 class="font-semibold text-slate-800 flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="ruler" class="w-4 h-4 text-amber-500"></i> Mesures
      </h3>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between">
          <span class="text-slate-500">Poids</span>
          <span class="font-bold font-mono"><?= number_format((float)$ticket['weight_kg'], 2) ?> kg</span>
        </div>
        <?php if ($ticket['length_cm'] || $ticket['width_cm'] || $ticket['height_cm']): ?>
          <div class="flex justify-between">
            <span class="text-slate-500">Dimensions</span>
            <span class="font-mono text-xs">
              <?= implode(' × ', array_filter([
                $ticket['length_cm'] ? $ticket['length_cm'] . 'cm' : null,
                $ticket['width_cm']  ? $ticket['width_cm']  . 'cm' : null,
                $ticket['height_cm'] ? $ticket['height_cm'] . 'cm' : null,
              ])) ?>
            </span>
          </div>
        <?php endif ?>
      </div>
    </div>

    <!-- Prix -->
    <div class="bg-amber-50 rounded-2xl border border-amber-200 shadow-soft p-5 space-y-3">
      <h3 class="font-semibold text-amber-900 flex items-center gap-2 pb-3 border-b border-amber-200">
        <i data-lucide="receipt" class="w-4 h-4 text-amber-600"></i> Tarification
      </h3>
      <div class="space-y-2 text-sm">
        <?php if ((int)$ticket['base_fee_fcfa'] > 0): ?>
          <div class="flex justify-between">
            <span class="text-amber-800">Frais fixes</span>
            <span><?= fcfa((int)$ticket['base_fee_fcfa']) ?></span>
          </div>
        <?php endif ?>
        <?php if ((int)$ticket['weight_fee_fcfa'] > 0): ?>
          <div class="flex justify-between">
            <span class="text-amber-800">Frais poids</span>
            <span><?= fcfa((int)$ticket['weight_fee_fcfa']) ?></span>
          </div>
        <?php endif ?>
        <?php if ((int)$ticket['volume_surcharge_fcfa'] > 0): ?>
          <div class="flex justify-between">
            <span class="text-amber-800">Surcharge gabarit</span>
            <span><?= fcfa((int)$ticket['volume_surcharge_fcfa']) ?></span>
          </div>
        <?php endif ?>
        <div class="flex justify-between pt-2 border-t border-amber-200">
          <span class="font-bold text-amber-900">Total perçu</span>
          <span class="text-xl font-bold text-amber-700"><?= fcfa((int)$ticket['total_price_fcfa']) ?></span>
        </div>
      </div>
    </div>

  </div>

  <!-- Annulation -->
  <?php if ($ticket['status'] === 'emis'): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <h3 class="font-semibold text-rose-700 mb-3 flex items-center gap-2">
        <i data-lucide="x-circle" class="w-4 h-4"></i> Annuler ce billet
      </h3>
      <form method="post" action="<?= e(url('billetterie-bagages/' . $ticket['id'] . '/cancel')) ?>"
            onsubmit="return confirm('Confirmer l\'annulation de ce billet bagage ?')">
        <?= csrf_field() ?>
        <div class="flex gap-3 items-end">
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 mb-1">Motif (optionnel)</label>
            <input type="text" name="reason" maxlength="255"
                   placeholder="Ex : Erreur de saisie, client absent…"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-rose-300 outline-none text-sm">
          </div>
          <button type="submit"
                  class="px-4 py-2 rounded-xl bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">
            Annuler le billet
          </button>
        </div>
      </form>
    </div>
  <?php endif ?>

</div>
<?php $view->end() ?>
