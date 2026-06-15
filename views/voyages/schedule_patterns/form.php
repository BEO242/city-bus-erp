<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$dayLabels = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('voyages/schedules')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
  </div>
  <form method="post" action="<?= e(url('voyages/schedules')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft max-w-3xl">
    <?= csrf_field() ?>
    <input name="label" required placeholder="Libellé (ex: BZV-PNR matin)" value="<?= e(old('label')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">

    <div class="grid grid-cols-3 gap-4">
      <select name="line_id" required class="px-3 py-2.5 rounded-xl border border-slate-200">
        <option value="">Ligne *</option>
        <?php foreach ($lines as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['code']) ?> · <?= e($l['name']) ?></option><?php endforeach ?>
      </select>
      <select name="bus_id" class="px-3 py-2.5 rounded-xl border border-slate-200">
        <option value="">Véhicule (optionnel)</option>
        <?php foreach ($buses as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['code']) ?> · <?= e($b['plate']) ?></option><?php endforeach ?>
      </select>
      <input type="number" name="base_price_fcfa" placeholder="Prix de base FCFA" class="px-3 py-2.5 rounded-xl border border-slate-200">
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-2">Jours de circulation</label>
      <div class="flex gap-2 flex-wrap">
        <?php foreach ($dayLabels as $k => $lbl): ?>
          <label class="flex items-center gap-1 px-3 py-2 rounded-xl border border-slate-200 cursor-pointer hover:bg-cb-bg/30">
            <input type="checkbox" name="days_of_week[]" value="<?= $k ?>" checked>
            <span class="text-sm"><?= e($lbl) ?></span>
          </label>
        <?php endforeach ?>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Départ *</label>
        <input type="time" name="departure_time" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Arrivée prévue</label>
        <input type="time" name="arrival_time" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Valide du *</label>
        <input type="date" name="valid_from" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Valide jusqu'au</label>
        <input type="date" name="valid_until" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Anticipation (jours)</label>
        <input type="number" name="auto_generate_days" value="14" min="1" max="60" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
    </div>

    <textarea name="notes" rows="2" placeholder="Notes" class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked> <span class="text-sm">Actif</span></label>
    <div class="flex justify-end gap-2">
      <a href="<?= e(url('voyages/schedules')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white">Créer</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
