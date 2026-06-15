<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$isEdit = !empty($agency);
$action = $isEdit ? url('referentiel/agencies/'.$agency['id']) : url('referentiel/agencies');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('referentiel/agencies')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1"><i data-lucide="chevron-left" class="w-4 h-4"></i> Retour</a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
  </div>
  <form method="post" action="<?= e($action) ?>" data-dirty-watch="<?= $isEdit ? '1' : '0' ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
      <input name="name" required value="<?= e(old('name', $agency['name'] ?? '')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
      <?php foreach (errors('name') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Ville</label>
        <select name="city_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="">— Sélectionner —</option>
          <?php foreach (($cities ?? []) as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)old('city_id', $agency['city_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>>
              <?= e($c['name']) ?><?= !empty($c['region']) ? ' — '.e($c['region']) : '' ?>
            </option>
          <?php endforeach ?>
        </select>
        <?php foreach (errors('city_id') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
        <select name="type" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <?php foreach (['principale'=>'Principale','point_vente'=>'Point de vente','controle'=>'Contrôle','parking'=>'Parking'] as $k=>$lbl): ?>
            <option value="<?= e($k) ?>" <?= old('type', $agency['type'] ?? '')===$k?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Adresse</label>
      <input name="address" value="<?= e(old('address', $agency['address'] ?? '')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Téléphone</label>
      <input name="phone" value="<?= e(old('phone', $agency['phone'] ?? '')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <?php if ($isEdit): ?>
      <label class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1" <?= ($agency['is_active'] ?? 1)?'checked':'' ?> class="rounded">
        <span class="text-sm">Agence active</span>
      </label>
    <?php endif ?>
    <div class="flex justify-end gap-2 pt-2">
      <a href="<?= e(url('referentiel/agencies')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50">Annuler</a>
      <button data-dirty-submit class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary"><?= $isEdit?'Mettre à jour':'Créer' ?></button>
    </div>
  </form>
</div>
<?php $view->end() ?>
