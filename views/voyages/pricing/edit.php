<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="max-w-3xl mx-auto space-y-5">
  <h1 class="text-2xl font-bold text-slate-900"><?= $rule ? 'Modifier règle' : 'Nouvelle règle pricing' ?></h1>

  <form method="post" action="<?= e(url($rule ? 'voyages/pricing/' . $rule['id'] : 'voyages/pricing')) ?>" class="bg-white rounded-2xl p-6 shadow-soft border border-slate-100 space-y-4">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Nom *</label>
        <input name="name" required value="<?= e($rule['name'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Type *</label>
        <select name="rule_type" required class="w-full px-3 py-2 rounded-lg border border-slate-200">
          <?php foreach (['load_factor'=>'Load factor (%)','days_to_departure'=>'Jours avant départ','time_of_day'=>'Heure du jour','day_of_week'=>'Jour de semaine (1-7)','class'=>'Classe','line'=>'Ligne'] as $k=>$lbl): ?>
            <option value="<?= e($k) ?>" <?= ($rule['rule_type']??'')===$k?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Ligne (optionnel)</label>
        <select name="scope_line_id" class="w-full px-3 py-2 rounded-lg border border-slate-200">
          <option value="">Toutes</option>
          <?php foreach ($lines as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= ($rule['scope_line_id']??null)==$l['id']?'selected':'' ?>><?= e($l['code']) ?> · <?= e($l['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Classe (optionnel)</label>
        <select name="scope_class" class="w-full px-3 py-2 rounded-lg border border-slate-200">
          <option value="">Toutes</option>
          <?php foreach (['Y','B','M','H','L'] as $c): ?>
            <option value="<?= $c ?>" <?= ($rule['scope_class']??null)===$c?'selected':'' ?>><?= $c ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Seuil min</label>
        <input name="threshold_min" type="number" step="0.01" value="<?= e($rule['threshold_min'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Seuil max</label>
        <input name="threshold_max" type="number" step="0.01" value="<?= e($rule['threshold_max'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Multiplicateur (1.0 = 0%)</label>
        <input name="multiplier" type="number" step="0.001" value="<?= e($rule['multiplier'] ?? '1.000') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Delta FCFA</label>
        <input name="delta_fcfa" type="number" value="<?= (int)($rule['delta_fcfa'] ?? 0) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Priorité (plus bas = en premier)</label>
        <input name="priority" type="number" value="<?= (int)($rule['priority'] ?? 100) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Valable du / au</label>
        <div class="flex gap-2">
          <input name="valid_from" type="date" value="<?= e($rule['valid_from'] ?? '') ?>" class="flex-1 px-3 py-2 rounded-lg border border-slate-200">
          <input name="valid_until" type="date" value="<?= e($rule['valid_until'] ?? '') ?>" class="flex-1 px-3 py-2 rounded-lg border border-slate-200">
        </div>
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
        <textarea name="description" rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-200"><?= e($rule['description'] ?? '') ?></textarea>
      </div>
      <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="active" value="1" <?= ($rule['active']??1)?'checked':'' ?>>
          <span class="text-sm font-semibold">Règle active</span>
        </label>
      </div>
    </div>
    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
      <a href="<?= e(url('voyages/pricing')) ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-sm">Annuler</a>
      <button class="px-5 py-2 rounded-lg bg-cb-primary text-white font-semibold hover:bg-cb-secondary"><?= $rule ? 'Mettre à jour' : 'Créer' ?></button>
    </div>
  </form>
</div>
<?php $view->end() ?>
