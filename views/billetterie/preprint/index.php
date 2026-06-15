<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$statusMeta = [
  'disponible' => ['label' => 'Disponible', 'cls' => 'bg-emerald-50 text-emerald-700', 'dot' => 'bg-emerald-500'],
  'active'     => ['label' => 'Activé',     'cls' => 'bg-cb-bg text-cb-primary',       'dot' => 'bg-cb-primary'],
  'annule'     => ['label' => 'Annulé',     'cls' => 'bg-rose-50 text-rose-600',        'dot' => 'bg-rose-400'],
];
$ppTypeMeta = [
  'billet'       => ['label' => 'Billet',  'cls' => 'bg-blue-50 text-blue-700 border-blue-200',   'icon' => 'ticket'],
  'talon_bagage' => ['label' => 'Bagage',  'cls' => 'bg-amber-50 text-amber-700 border-amber-200','icon' => 'luggage'],
  'talon_colis'  => ['label' => 'Colis',   'cls' => 'bg-purple-50 text-purple-700 border-purple-200','icon' => 'package'],
];
?>
<?php $view->start('content') ?>
<div class="max-w-6xl mx-auto space-y-5">

  <!-- En-tête -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="tickets" class="w-6 h-6 text-cb-primary"></i> Tickets pré-imprimés
      </h1>
      <p class="text-slate-500 text-sm mt-0.5">Supports physiques : billets, talons bagage et talons colis.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <?php if (can('admin')): ?>
      <a href="<?= e(url('billetterie/preprint/config')) ?>"
         class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold inline-flex items-center gap-2 hover:bg-slate-50 transition">
        <i data-lucide="settings-2" class="w-4 h-4"></i> Paramètres
      </a>
      <?php endif ?>
      <?php if (can('billetterie.preprint')): ?>
      <a href="<?= e(url('billetterie/preprint/create')) ?>"
         class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-semibold inline-flex items-center gap-2 hover:bg-cb-dark transition shadow-soft">
        <i data-lucide="plus" class="w-4 h-4"></i> Générer un lot
      </a>
      <?php endif ?>
    </div>
  </div>

  <!-- KPI -->
  <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
    <!-- Par statut -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-emerald-50 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500">Disponibles</p>
        <p class="text-2xl font-bold text-emerald-600"><?= e($stats['dispo'] ?? 0) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-cb-bg rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="ticket" class="w-5 h-5 text-cb-primary"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500">Activés</p>
        <p class="text-2xl font-bold text-cb-primary"><?= e($stats['actives'] ?? 0) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-rose-50 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="x-circle" class="w-5 h-5 text-rose-500"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500">Annulés</p>
        <p class="text-2xl font-bold text-rose-500"><?= e($stats['annules'] ?? 0) ?></p>
      </div>
    </div>
    <!-- Par type -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-blue-50 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="ticket" class="w-5 h-5 text-blue-600"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500">Billets</p>
        <p class="text-2xl font-bold text-blue-600"><?= e($stats['billets'] ?? 0) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-amber-50 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="luggage" class="w-5 h-5 text-amber-600"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500">T. Bagage</p>
        <p class="text-2xl font-bold text-amber-600"><?= e($stats['talons_bagage'] ?? 0) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-purple-50 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="package" class="w-5 h-5 text-purple-600"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500">T. Colis</p>
        <p class="text-2xl font-bold text-purple-600"><?= e($stats['talons_colis'] ?? 0) ?></p>
      </div>
    </div>
  </div>

  <!-- Filtres -->
  <form method="get" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4">
    <div class="flex flex-wrap gap-3 items-end">
      <div>
        <label class="cb-label">Type</label>
        <select name="preprint_type" class="cb-input w-44">
          <option value="">Tous les types</option>
          <?php foreach ($preprintTypes as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= ($preprintType ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="cb-label">Statut</label>
        <select name="status" class="cb-input w-44">
          <option value="">Tous les statuts</option>
          <?php foreach ($statusMeta as $k => $m): ?>
            <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= e($m['label']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="flex gap-2 ml-auto">
        <a href="<?= e(url('billetterie/preprint')) ?>"
           class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition text-sm font-medium">
          Réinitialiser
        </a>
        <button type="submit"
                class="px-5 py-2.5 rounded-xl bg-slate-900 text-white font-medium text-sm hover:bg-slate-700 transition inline-flex items-center gap-2">
          <i data-lucide="search" class="w-4 h-4"></i> Filtrer
        </button>
      </div>
    </div>
  </form>

  <!-- Tableau -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <?php if (!$tickets): ?>
      <div class="text-center py-16">
        <i data-lucide="ticket" class="w-10 h-10 mx-auto text-slate-200 mb-3"></i>
        <p class="text-slate-400 font-semibold">Aucun support pré-imprimé</p>
        <p class="text-slate-400 text-sm mt-1">Générez un lot pour commencer.</p>
      </div>
    <?php else: ?>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr>
          <th class="px-4 py-3 text-left">N° Support</th>
          <th class="px-4 py-3 text-left">Code</th>
          <th class="px-4 py-3 text-center">Type</th>
          <th class="px-4 py-3 text-left">Lot</th>
          <th class="px-4 py-3 text-left">Voyage</th>
          <th class="px-4 py-3 text-left">Agence</th>
          <th class="px-4 py-3 text-center">Statut</th>
          <th class="px-4 py-3 text-left">Date</th>
          <th class="px-4 py-3 text-right">PDF</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
      <?php foreach ($tickets as $t):
        $sm = $statusMeta[$t['status']] ?? $statusMeta['disponible'];
        $pt = $ppTypeMeta[$t['preprint_type'] ?? 'billet'] ?? $ppTypeMeta['billet'];
      ?>
        <tr class="hover:bg-slate-50/60 transition">
          <td class="px-4 py-3 font-mono font-semibold text-cb-primary text-xs"><?= e($t['pre_print_number']) ?></td>
          <td class="px-4 py-3">
            <?php if (!empty($t['short_code'])): ?>
              <span class="font-mono font-bold text-xs bg-slate-100 px-2 py-1 rounded-lg tracking-wider"><?= e($t['short_code']) ?></span>
            <?php else: ?>
              <span class="text-slate-300 text-xs">—</span>
            <?php endif ?>
          </td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border <?= $pt['cls'] ?>">
              <i data-lucide="<?= $pt['icon'] ?>" class="w-3 h-3"></i>
              <?= e($pt['label']) ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <a href="<?= e(url('billetterie/preprint/batch/'.$t['batch_id'])) ?>"
               class="text-xs font-mono text-slate-500 hover:text-cb-primary transition">
              <?= e(substr($t['batch_id'], 0, 8)) ?>…
            </a>
          </td>
          <td class="px-4 py-3">
            <?php if (!empty($t['trip_code'])): ?>
              <span class="text-xs font-mono text-cb-primary"><?= e($t['trip_code']) ?></span>
              <?php if (!empty($t['departure_city'])): ?>
                <p class="text-xs text-slate-400"><?= e($t['departure_city']) ?> → <?= e($t['arrival_city']) ?></p>
              <?php endif ?>
            <?php else: ?>
              <span class="text-slate-300 text-xs">—</span>
            <?php endif ?>
          </td>
          <td class="px-4 py-3 text-slate-700 text-xs"><?= e($t['agency_name']) ?></td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $sm['cls'] ?>">
              <span class="w-1.5 h-1.5 rounded-full <?= $sm['dot'] ?>"></span>
              <?= $sm['label'] ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-500 text-xs"><?= e(date('d/m/Y', strtotime($t['created_at']))) ?></td>
          <td class="px-4 py-3 text-right">
            <?php if (!empty($t['pdf_path'])): ?>
            <a href="<?= e(url('billetterie/preprint/batch/'.$t['batch_id'].'/pdf')) ?>"
               class="inline-flex items-center gap-1 text-xs text-cb-primary hover:text-cb-dark transition font-medium">
              <i data-lucide="download" class="w-3.5 h-3.5"></i> PDF
            </a>
            <?php else: ?>
            <span class="text-xs text-slate-300">—</span>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    </div>
    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
