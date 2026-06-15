<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app');
$d = $declaration;
?>
<?php $view->start('content') ?>
<div class="space-y-5 max-w-4xl mx-auto">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
      <i data-lucide="receipt" class="w-6 h-6 text-cb-primary"></i> Déclaration TVA
    </h1>
    <form method="get" class="flex items-center gap-2">
      <input type="month" name="month" value="<?= e($month) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
      <button class="px-4 py-2 rounded-lg bg-cb-primary text-white text-sm font-semibold">Calculer</button>
      <a href="<?= e(url('finance/tax/export?month=' . urlencode($month))) ?>" class="px-3 py-2 rounded-lg border border-slate-300 text-sm font-semibold">CSV</a>
    </form>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
    <h2 class="font-bold text-slate-900 mb-3">Période <?= e($d['period']) ?></h2>
    <div class="grid grid-cols-3 gap-4">
      <div class="text-center p-4 rounded bg-slate-50">
        <div class="text-xs uppercase text-slate-500">Total HT</div>
        <div class="text-2xl font-bold mt-1"><?= number_format((int)$d['total_ht']) ?></div>
      </div>
      <div class="text-center p-4 rounded bg-amber-50">
        <div class="text-xs uppercase text-amber-700">TVA collectée</div>
        <div class="text-2xl font-bold text-amber-800 mt-1"><?= number_format((int)$d['total_tax']) ?></div>
      </div>
      <div class="text-center p-4 rounded bg-slate-100">
        <div class="text-xs uppercase text-slate-500">Total TTC</div>
        <div class="text-2xl font-bold mt-1"><?= number_format((int)$d['total_ttc']) ?></div>
      </div>
    </div>

    <h3 class="font-bold text-slate-900 mt-6 mb-2">Ventilation par taux</h3>
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr><th class="px-3 py-2 text-left">Taux %</th><th class="px-3 py-2 text-right">HT</th><th class="px-3 py-2 text-right">TVA</th><th class="px-3 py-2 text-right">TTC</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($d['by_rate'] as $r): ?>
          <tr>
            <td class="px-3 py-2 font-mono font-bold"><?= e($r['tax_pct']) ?>%</td>
            <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$r['ht']) ?></td>
            <td class="px-3 py-2 text-right font-mono text-amber-700"><?= number_format((int)$r['tax']) ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$r['ht'] + (int)$r['tax']) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
