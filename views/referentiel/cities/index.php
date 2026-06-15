<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Villes</h1>
      <p class="text-slate-500 text-sm">Référentiel géographique : villes desservies par les agences et les lignes.</p>
    </div>
    <?php if (can('referentiel.create')): ?>
    <a href="<?= e(url('referentiel/cities/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary inline-flex items-center gap-2">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle ville
    </a>
    <?php endif ?>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <div class="relative flex-1">
      <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
      <input name="q" value="<?= e($q) ?>" placeholder="Rechercher (nom, slug, région)…" class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
    </div>
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Filtrer</button>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Nom</th>
          <th class="px-5 py-3 text-left">Slug</th>
          <th class="px-5 py-3 text-left">Région</th>
          <th class="px-5 py-3 text-center">Ordre</th>
          <th class="px-5 py-3 text-center">Agences</th>
          <th class="px-5 py-3 text-center">Lignes</th>
          <th class="px-5 py-3 text-center">Statut</th>
          <th class="px-5 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($cities as $c): ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-3 font-semibold text-slate-900"><?= e($c['name']) ?></td>
          <td class="px-5 py-3"><code class="text-xs bg-slate-100 px-2 py-0.5 rounded"><?= e($c['slug']) ?></code></td>
          <td class="px-5 py-3 text-slate-600"><?= e($c['region'] ?? '—') ?></td>
          <td class="px-5 py-3 text-center text-slate-500"><?= (int)$c['display_order'] ?></td>
          <td class="px-5 py-3 text-center"><?= (int)$c['agencies_count'] ?></td>
          <td class="px-5 py-3 text-center"><?= (int)$c['lines_count'] ?></td>
          <td class="px-5 py-3 text-center">
            <?php if ($c['is_active']): ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-700">Active</span>
            <?php else: ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-500">Inactive</span>
            <?php endif ?>
          </td>
          <td class="px-5 py-3 text-right space-x-2">
            <?php if (can('referentiel.edit')): ?>
              <a href="<?= e(url('referentiel/cities/'.$c['id'].'/edit')) ?>" class="text-cb-primary hover:underline">Modifier</a>
            <?php endif ?>
            <?php if (can('referentiel.delete') && (int)$c['agencies_count']===0 && (int)$c['lines_count']===0): ?>
              <form method="post" action="<?= e(url('referentiel/cities/'.$c['id'].'/delete')) ?>" class="inline" onsubmit="return confirm('Supprimer cette ville ?')">
                <?= csrf_field() ?>
                <button class="text-rose-600 hover:underline">Supprimer</button>
              </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$cities): ?>
        <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Aucune ville</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
