<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('voyages/' . $trip['id'])) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour voyage
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2">Pré-vérification</h1>
    <p class="text-slate-500 text-sm"><?= e($trip['trip_code']) ?> · Véhicule <?= e($trip['bus_code'] ?? '—') ?> · <?= e($trip['plate'] ?? '') ?></p>
  </div>

  <form method="post" action="<?= e(url('flotte/inspections/' . $trip['id'])) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft max-w-3xl">
    <?= csrf_field() ?>
    <h2 class="font-semibold text-slate-900">Checklist</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
      <?php foreach ($fields as $f => $lbl): ?>
        <label class="flex items-center gap-2 px-3 py-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-cb-bg/30 has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-300">
          <input type="checkbox" name="<?= e($f) ?>" value="1" <?= !empty($inspection[$f]) ? 'checked' : '' ?>>
          <span><?= e($lbl) ?></span>
        </label>
      <?php endforeach ?>
    </div>

    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100">
      <div><label class="block text-sm text-slate-500 mb-1">Kilométrage</label><input type="number" name="odometer_km" value="<?= e($inspection['odometer_km'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200"></div>
      <div><label class="block text-sm text-slate-500 mb-1">Niveau carburant (%)</label><input type="number" min="0" max="100" name="fuel_level_pct" value="<?= e($inspection['fuel_level_pct'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200"></div>
    </div>

    <textarea name="remarks" rows="3" placeholder="Remarques / défauts mineurs constatés" class="w-full px-3 py-2 rounded-xl border border-slate-200"><?= e($inspection['remarks'] ?? '') ?></textarea>

    <div class="flex justify-end gap-2">
      <a href="<?= e(url('voyages/' . $trip['id'])) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white">Enregistrer</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
