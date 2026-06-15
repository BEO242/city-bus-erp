<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="trending-up" class="w-6 h-6 text-cb-primary"></i> Pricing dynamique
      </h1>
      <p class="text-sm text-slate-500"><?= count($rules) ?> règle(s) configurée(s)</p>
    </div>
    <?php if (can('voyages.pricing.manage')): ?>
      <a href="<?= e(url('voyages/pricing/create')) ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cb-primary text-white font-semibold hover:bg-cb-secondary shadow-soft">
        <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle règle
      </a>
    <?php endif ?>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-4 py-3 text-left">#</th>
          <th class="px-4 py-3 text-left">Nom</th>
          <th class="px-4 py-3 text-left">Type</th>
          <th class="px-4 py-3 text-left">Portée</th>
          <th class="px-4 py-3 text-left">Seuil</th>
          <th class="px-4 py-3 text-left">Effet</th>
          <th class="px-4 py-3 text-center">Priorité</th>
          <th class="px-4 py-3 text-center">Actif</th>
          <th class="px-4 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($rules as $r): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-400">#<?= (int)$r['id'] ?></td>
            <td class="px-4 py-3 font-semibold"><?= e($r['name']) ?>
              <?php if(!empty($r['description'])): ?><div class="text-xs text-slate-500"><?= e($r['description']) ?></div><?php endif ?>
            </td>
            <td class="px-4 py-3 text-xs"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700"><?= e($r['rule_type']) ?></span></td>
            <td class="px-4 py-3 text-xs">
              <?= $r['line_code'] ? '<strong>' . e($r['line_code']) . '</strong>' : '<span class="text-slate-400">Toutes lignes</span>' ?>
              <?= $r['scope_class'] ? ' · classe ' . e($r['scope_class']) : '' ?>
            </td>
            <td class="px-4 py-3 text-xs text-slate-600">
              <?= $r['threshold_min']!==null ? '≥ ' . e($r['threshold_min']) : '' ?>
              <?= $r['threshold_max']!==null ? ' ≤ ' . e($r['threshold_max']) : '' ?>
            </td>
            <td class="px-4 py-3 text-xs">
              <?php if((float)$r['multiplier'] != 1.0): ?>
                <span class="font-bold <?= (float)$r['multiplier']>1?'text-rose-600':'text-emerald-600' ?>">×<?= e($r['multiplier']) ?></span>
              <?php endif ?>
              <?php if((int)$r['delta_fcfa'] !== 0): ?>
                <span class="font-mono"><?= (int)$r['delta_fcfa']>0?'+':'' ?><?= number_format((int)$r['delta_fcfa']) ?> F</span>
              <?php endif ?>
            </td>
            <td class="px-4 py-3 text-center text-slate-500"><?= (int)$r['priority'] ?></td>
            <td class="px-4 py-3 text-center">
              <?php if((int)$r['active']===1): ?>
                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">ON</span>
              <?php else: ?>
                <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-xs font-bold">OFF</span>
              <?php endif ?>
            </td>
            <td class="px-4 py-3 text-right">
              <?php if (can('voyages.pricing.manage')): ?>
                <a href="<?= e(url('voyages/pricing/' . $r['id'] . '/edit')) ?>" class="text-cb-primary hover:underline text-xs">Modifier</a>
                <form method="post" action="<?= e(url('voyages/pricing/' . $r['id'] . '/delete')) ?>" class="inline ml-2" onsubmit="return confirm('Supprimer cette règle ?')">
                  <?= csrf_field() ?>
                  <button class="text-rose-600 hover:underline text-xs">Suppr.</button>
                </form>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (empty($rules)): ?>
          <tr><td colspan="9" class="px-4 py-12 text-center text-slate-400">Aucune règle. Créez-en une pour activer le yield management.</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
