<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <a href="<?= e(url('flotte/fuel')) ?>" class="text-sm text-slate-500 inline-flex items-center gap-1"><i data-lucide="chevron-left" class="w-4 h-4"></i> Retour</a>
  <h1 class="text-2xl font-bold">Nouveau plein de carburant</h1>

  <form method="post" action="<?= e(url('flotte/fuel')) ?>" x-data="{ liters:0, price:0 }" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium mb-1">Bus</label>
      <select name="bus_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
        <?php foreach ($buses as $b): ?>
          <option value="<?= e($b['id']) ?>"><?= e($b['code']) ?> — <?= e($b['plate']) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Date</label>
      <input type="date" name="log_date" required value="<?= e(date('Y-m-d')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Litres</label>
        <input type="number" step="0.01" name="liters" x-model.number="liters" required min="0" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Prix/Litre (FCFA)</label>
        <input type="number" name="price_per_liter" x-model.number="price" required min="0" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
    </div>
    <div class="bg-cb-bg rounded-xl p-3 flex justify-between font-bold">
      <span>Total</span>
      <span class="text-cb-primary" x-text="new Intl.NumberFormat('fr-FR').format(Math.round(liters*price)) + ' FCFA'"></span>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Kilométrage (optionnel)</label>
      <input type="number" name="km" min="0" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <div class="flex justify-end gap-2">
      <a href="<?= e(url('flotte/fuel')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium">Enregistrer</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
