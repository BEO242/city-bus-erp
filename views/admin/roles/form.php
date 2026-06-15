<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$isEdit = !empty($role);
$selected = array_map('intval', $rolePerms);
?>
<?php $view->start('content') ?>

<div class="space-y-5">
  <div class="flex items-center gap-3 mb-6">
    <a href="<?= e(url('admin/roles')) ?>" class="p-2 rounded-lg hover:bg-white"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
    <h1 class="text-2xl font-bold text-slate-800"><?= e($title) ?></h1>
  </div>

  <form method="post" action="<?= e($isEdit ? url('admin/roles/'.$role['id']) : url('admin/roles')) ?>" class="space-y-5">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl shadow-soft p-6 grid md:grid-cols-3 gap-4">
      <div>
        <label class="text-xs font-semibold text-slate-500">Slug *</label>
        <input name="slug" value="<?= e(old('slug', $role['slug'] ?? '')) ?>"
               <?= $isEdit ? 'readonly disabled' : 'required' ?>
               class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 font-mono text-sm <?= $isEdit ? 'bg-slate-50 text-slate-400' : '' ?>">
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-500">Libellé *</label>
        <input name="label" value="<?= e(old('label', $role['label'] ?? '')) ?>" required
               class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-500">Ordre</label>
        <input type="number" name="sort_order" value="<?= e(old('sort_order', $role['sort_order'] ?? 100)) ?>"
               class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div class="md:col-span-3">
        <label class="text-xs font-semibold text-slate-500">Description</label>
        <input name="description" value="<?= e(old('description', $role['description'] ?? '')) ?>"
               class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200">
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-soft p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="shield-check" class="w-5 h-5"></i> Permissions</h3>
        <div class="flex gap-2 text-xs">
          <button type="button" onclick="document.querySelectorAll('.perm-cb').forEach(c=>c.checked=true)" class="px-2 py-1 rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100">Tout cocher</button>
          <button type="button" onclick="document.querySelectorAll('.perm-cb').forEach(c=>c.checked=false)" class="px-2 py-1 rounded bg-slate-100 text-slate-600 hover:bg-slate-200">Tout décocher</button>
        </div>
      </div>

      <div class="space-y-5">
        <?php foreach ($permsByModule as $module => $perms): ?>
          <div class="border border-slate-100 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-bold uppercase tracking-wider text-cb-primary"><?= e($module) ?></h4>
              <button type="button" onclick="this.closest('div.border').querySelectorAll('.perm-cb').forEach(c=>c.checked=true)" class="text-xs text-slate-400 hover:text-cb-primary">Tout</button>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
              <?php foreach ($perms as $p): ?>
                <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 cursor-pointer">
                  <input type="checkbox" name="permissions[]" value="<?= e($p['id']) ?>"
                         class="perm-cb rounded text-cb-primary focus:ring-cb-primary"
                         <?= in_array((int)$p['id'], $selected, true) ? 'checked' : '' ?>>
                  <span class="text-sm text-slate-700"><?= e($p['label']) ?></span>
                </label>
              <?php endforeach ?>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3">
      <a href="<?= e(url('admin/roles')) ?>" class="px-4 py-2 rounded-lg bg-white border border-slate-200 hover:bg-slate-50">Annuler</a>
      <button class="px-5 py-2 rounded-lg bg-cb-primary text-white font-semibold hover:bg-cb-secondary">
        <?= $isEdit ? 'Enregistrer' : 'Créer' ?>
      </button>
    </div>
  </form>
</div>

<?php $view->end() ?>
