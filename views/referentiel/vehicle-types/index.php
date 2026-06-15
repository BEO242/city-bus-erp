<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="space-y-5">

  <!-- En-tete -->
  <div class="flex items-end justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Types de vehicules</h1>
      <p class="text-slate-500 text-sm"><?= count($types) ?> type(s) configures</p>
    </div>
    <div class="flex gap-2">
      <a href="<?= e(url('referentiel/vehicules')) ?>"
         class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Vehicules
      </a>
      <?php if (\CityBus\Core\Auth::can('referentiel.create')): ?>
      <a href="<?= e(url('referentiel/vehicle-types/create')) ?>"
         class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-medium inline-flex items-center gap-2 hover:bg-cb-dark transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Nouveau type
      </a>
      <?php endif ?>
    </div>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
        <tr>
          <th class="text-left px-5 py-3">Code</th>
          <th class="text-left px-5 py-3">Libelle</th>
          <th class="text-left px-5 py-3">Description</th>
          <th class="text-center px-5 py-3">Vehicules</th>
          <th class="text-center px-5 py-3">Ordre</th>
          <th class="text-center px-5 py-3">Actif</th>
          <th class="text-right px-5 py-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($types as $t): ?>
        <tr class="hover:bg-slate-50/60 transition">
          <td class="px-5 py-3">
            <div class="flex items-center gap-2">
              <i data-lucide="<?= e($t['icon'] ?: 'truck') ?>" class="w-4 h-4 text-cb-primary"></i>
              <span class="font-mono text-xs font-bold text-slate-700"><?= e($t['code']) ?></span>
            </div>
          </td>
          <td class="px-5 py-3 font-semibold text-slate-900"><?= e($t['label']) ?></td>
          <td class="px-5 py-3 text-slate-500 text-xs"><?= e($t['description'] ?? '') ?></td>
          <td class="px-5 py-3 text-center">
            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold
              <?= (int)$t['vehicles_count'] > 0 ? 'bg-cb-bg text-cb-primary' : 'bg-slate-100 text-slate-400' ?>">
              <?= (int)$t['vehicles_count'] ?>
            </span>
          </td>
          <td class="px-5 py-3 text-center text-slate-400 text-xs"><?= (int)$t['sort_order'] ?></td>
          <td class="px-5 py-3 text-center">
            <?php if ($t['is_active']): ?>
              <span class="inline-flex items-center gap-1 text-xs text-emerald-600"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i></span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 text-xs text-slate-400"><i data-lucide="x-circle" class="w-3.5 h-3.5"></i></span>
            <?php endif ?>
          </td>
          <td class="px-5 py-3 text-right">
            <div class="flex items-center justify-end gap-1">
              <?php if (\CityBus\Core\Auth::can('referentiel.edit')): ?>
              <a href="<?= e(url('referentiel/vehicle-types/' . $t['id'] . '/edit')) ?>"
                 class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition" title="Modifier">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
              </a>
              <?php endif ?>
              <?php if (\CityBus\Core\Auth::can('referentiel.delete')): ?>
              <form method="post" action="<?= e(url('referentiel/vehicle-types/' . $t['id'] . '/delete')) ?>"
                    onsubmit="return confirm('Supprimer le type « <?= e(addslashes($t['label'])) ?> » ?')" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="p-1.5 rounded-lg border border-rose-200 text-rose-500 hover:bg-rose-50 transition" title="Supprimer">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </form>
              <?php endif ?>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (empty($types)): ?>
        <tr>
          <td colspan="7" class="px-5 py-12 text-center text-slate-400">
            <i data-lucide="truck" class="w-8 h-8 mx-auto text-slate-300 mb-2"></i>
            <p>Aucun type de vehicule configure.</p>
          </td>
        </tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>

</div>
<?php $view->end() ?>
