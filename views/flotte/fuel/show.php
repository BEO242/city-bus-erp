<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('flotte/fuel')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <div class="flex items-start justify-between mt-2">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Plein du <?= e(date('d/m/Y H:i', strtotime((string)$log['logged_at']))) ?></h1>
        <p class="text-slate-500 text-sm">Véhicule <span class="font-semibold"><?= e($log['bus_code']) ?></span> · <?= e($log['plate']) ?> · <?= e(($log['brand'] ?? '') . ' ' . ($log['model'] ?? '')) ?></p>
      </div>
      <?php if (can('flotte.fuel.log')): ?>
        <div class="flex gap-2">
          <a href="<?= e(url('flotte/fuel/' . $log['id'] . '/edit')) ?>" class="px-3 py-1.5 rounded-lg bg-cb-primary text-white text-sm hover:bg-cb-secondary">Modifier</a>
          <form method="post" action="<?= e(url('flotte/fuel/' . $log['id'] . '/delete')) ?>" onsubmit="return confirm('Supprimer ce plein ?')">
            <?= csrf_field() ?>
            <button class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-sm hover:bg-rose-100">Supprimer</button>
          </form>
        </div>
      <?php endif ?>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Litres</p>
      <p class="text-3xl font-bold text-slate-900 mt-1"><?= number_format((float)$log['liters'], 2, ',', ' ') ?> <span class="text-base font-medium text-slate-500">L</span></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Coût total</p>
      <p class="text-3xl font-bold text-slate-900 mt-1"><?= e(fcfa((int)$log['total_cost'])) ?></p>
      <p class="text-xs text-slate-500 mt-1"><?= e(fcfa((int)$log['price_per_liter'])) ?> / litre</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Conso. depuis dernier plein</p>
      <p class="text-3xl font-bold text-slate-900 mt-1">
        <?= $consumption !== null ? number_format($consumption, 2, ',', ' ') . ' <span class="text-base font-medium text-slate-500">L/100km</span>' : '<span class="text-slate-400">—</span>' ?>
      </p>
      <?php if ($previous): ?>
        <p class="text-xs text-slate-500 mt-1">Précédent : <?= e(date('d/m/Y', strtotime((string)$previous['logged_at']))) ?></p>
      <?php endif ?>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-3">
    <h2 class="font-semibold text-slate-900">Détails</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
      <div>
        <p class="text-xs uppercase text-slate-400">Station</p>
        <p><?= e($log['station_name'] ?? '—') ?></p>
      </div>
      <div>
        <p class="text-xs uppercase text-slate-400">Km au plein</p>
        <p><?= !empty($log['km_at_fill']) ? number_format((int)$log['km_at_fill'], 0, ',', ' ') . ' km' : '—' ?></p>
      </div>
      <div>
        <p class="text-xs uppercase text-slate-400">Enregistré par</p>
        <p><?= e(trim((string)$log['logged_by_name'])) ?: '—' ?></p>
      </div>
      <?php if (!empty($log['notes'])): ?>
      <div class="col-span-full">
        <p class="text-xs uppercase text-slate-400">Notes</p>
        <p class="text-slate-700 whitespace-pre-line"><?= e($log['notes']) ?></p>
      </div>
      <?php endif ?>
    </div>
  </div>
</div>
<?php $view->end() ?>
