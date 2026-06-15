<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$isEdit = !empty($checkpoint);
$action = $isEdit ? url('controle/checkpoints/'.$checkpoint['id']) : url('controle/checkpoints');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('controle/checkpoints')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
  </div>
  <form method="post" action="<?= e($action) ?>" data-dirty-watch="<?= $isEdit ? '1' : '0' ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft max-w-2xl">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Nom *</label>
      <input name="name" required value="<?= e(old('name', $checkpoint['name'] ?? '')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
      <?php foreach (errors('name') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Agence *</label>
        <select name="agency_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="">— Sélectionner —</option>
          <?php foreach ($agencies as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= ((int)old('agency_id', $checkpoint['agency_id'] ?? 0) === (int)$a['id'])?'selected':'' ?>><?= e($a['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Ligne (optionnel)</label>
        <select name="line_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="">— Tous les voyages —</option>
          <?php foreach ($lines as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= ((int)old('line_id', $checkpoint['line_id'] ?? 0) === (int)$l['id'])?'selected':'' ?>>
              <?= e($l['code']) ?> · <?= e($l['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
        <p class="text-xs text-slate-400 mt-1">Si renseigné, le poste ne validera que les billets de cette ligne.</p>
      </div>
    </div>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Km sur la ligne (optionnel)</label>
      <input type="number" step="0.01" name="km_on_line" value="<?= e(old('km_on_line', $checkpoint['km_on_line'] ?? '')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <label class="flex items-center gap-2">
      <input type="checkbox" name="is_active" value="1" <?= ((int)($checkpoint['is_active'] ?? 1) === 1) ? 'checked':'' ?> class="rounded">
      <span class="text-sm">Poste actif</span>
    </label>
    <div class="flex justify-end gap-2 pt-2">
      <a href="<?= e(url('controle/checkpoints')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50">Annuler</a>
      <button data-dirty-submit class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary"><?= $isEdit?'Mettre à jour':'Créer' ?></button>
    </div>
  </form>
</div>
<?php $view->end() ?>
