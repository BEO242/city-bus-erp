<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
      <i data-lucide="trending-up" class="w-6 h-6 text-cb-primary"></i> P&L analytique V4
    </h1>
    <form method="get" class="flex items-end gap-2">
      <input type="date" name="from" value="<?= e($from) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
      <input type="date" name="to" value="<?= e($to) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
      <button class="px-4 py-2 rounded-lg bg-cb-primary text-white text-sm">Afficher</button>
      <?php if (can('finance.pnl.recompute')): ?>
        <button name="recompute" value="1" class="px-3 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold">Recalculer</button>
      <?php endif ?>
    </form>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    <?php
      $g = $global;
      $cards = [
        ['Voyages', (int)($g['trips'] ?? 0), 'slate'],
        ['Pax', (int)($g['pax'] ?? 0), 'slate'],
        ['Recettes', number_format((int)($g['revenue'] ?? 0)), 'sky'],
        ['Coûts', number_format((int)($g['cost'] ?? 0)), 'rose'],
        ['Marge', number_format((int)($g['margin'] ?? 0)) . ' (' . ($g['margin_pct'] ?? 0) . '%)', ((int)($g['margin'] ?? 0) > 0) ? 'emerald' : 'rose'],
      ];
      foreach ($cards as [$lbl, $val, $col]):
    ?>
      <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-soft">
        <div class="text-xs font-semibold text-slate-500 uppercase mb-1"><?= e($lbl) ?></div>
        <div class="text-xl font-bold text-<?= $col ?>-700"><?= e($val) ?></div>
      </div>
    <?php endforeach ?>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100"><h2 class="font-bold text-slate-900">Par ligne</h2></div>
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-3 py-2 text-left">Ligne</th>
          <th class="px-3 py-2 text-center">Voyages</th>
          <th class="px-3 py-2 text-center">Pax</th>
          <th class="px-3 py-2 text-right">Recettes</th>
          <th class="px-3 py-2 text-right">Coûts</th>
          <th class="px-3 py-2 text-right">Marge</th>
          <th class="px-3 py-2 text-right">%</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($byLine as $l):
          $col = (int)$l['margin'] > 0 ? 'emerald' : 'rose';
        ?>
          <tr class="hover:bg-slate-50">
            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 text-xs font-mono font-bold mr-2"><?= e($l['code']) ?></span> <?= e($l['name']) ?></td>
            <td class="px-3 py-2 text-center text-xs"><?= (int)$l['trips'] ?></td>
            <td class="px-3 py-2 text-center text-xs"><?= (int)$l['pax'] ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$l['revenue']) ?></td>
            <td class="px-3 py-2 text-right font-mono text-slate-500"><?= number_format((int)$l['cost']) ?></td>
            <td class="px-3 py-2 text-right font-mono font-bold text-<?= $col ?>-700"><?= number_format((int)$l['margin']) ?></td>
            <td class="px-3 py-2 text-right font-bold text-<?= $col ?>-700"><?= e($l['avg_margin_pct']) ?>%</td>
          </tr>
        <?php endforeach ?>
        <?php if (empty($byLine)): ?><tr><td colspan="7" class="px-3 py-12 text-center text-slate-400">Pas de données. Recalculez d'abord.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
