<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$isEdit = !empty($city);
$action = $isEdit ? url('referentiel/cities/'.$city['id']) : url('referentiel/cities');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('referentiel/cities')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
  </div>
  <form method="post" action="<?= e($action) ?>" data-dirty-watch="<?= $isEdit ? '1' : '0' ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft max-w-2xl">
    <?= csrf_field() ?>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nom *</label>
        <input name="name" required value="<?= e(old('name', $city['name'] ?? '')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <?php foreach (errors('name') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Slug *</label>
        <input name="slug" required value="<?= e(old('slug', $city['slug'] ?? '')) ?>" placeholder="ex: brazzaville" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <p class="text-xs text-slate-400 mt-1">Identifiant technique (minuscules, _ autorisé).</p>
        <?php foreach (errors('slug') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Région</label>
        <input name="region" value="<?= e(old('region', $city['region'] ?? '')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Ordre d'affichage</label>
        <input type="number" name="display_order" value="<?= e(old('display_order', $city['display_order'] ?? 100)) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
      </div>
    </div>
    <label class="flex items-center gap-2">
      <input type="checkbox" name="is_active" value="1" <?= ((int)($city['is_active'] ?? 1)===1)?'checked':'' ?> class="rounded">
      <span class="text-sm">Ville active</span>
    </label>
    <div class="flex justify-end gap-2 pt-2">
      <a href="<?= e(url('referentiel/cities')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50">Annuler</a>
      <button data-dirty-submit class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary"><?= $isEdit?'Mettre à jour':'Créer' ?></button>
    </div>
  </form>
</div>
<?php $view->end() ?>
