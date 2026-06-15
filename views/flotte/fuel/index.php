<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold">Carburant</h1>
      <p class="text-slate-500 text-sm">Consommation et coûts par bus.</p>
    </div>
    <a href="<?= e(url('flotte/fuel/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium inline-flex items-center gap-2">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouveau plein
    </a>
  </div>

  <?php if (!empty($stats)): ?>
    <div class="grid md:grid-cols-3 gap-3">
      <?php foreach ($stats as $s): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-4">
            <div class="font-bold"><?= e($s['bus_code'] ?? $s['code'] ?? '—') ?></div>
            <div class="text-xs text-slate-500">Total: <?= e(number_format((float)($s['total_liters'] ?? $s['total_l'] ?? 0), 1, ',', ' ')) ?> L · <?= e(fcfa((int)($s['total_cost'] ?? 0))) ?></div>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr><th class="px-5 py-3 text-left">Date</th><th class="px-5 py-3 text-left">Véhicule</th><th class="px-5 py-3 text-right">Litres</th><th class="px-5 py-3 text-right">Prix/L</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3 text-right">Km</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($logs as $l): ?>
        <tr class="hover:bg-cb-bg/40">
            <td class="px-5 py-3 text-slate-600"><?= e(date('d/m/Y', strtotime((string)($l['log_date'] ?? $l['logged_at'] ?? 'now')))) ?></td>
            <td class="px-5 py-3"><?= e($l['bus_code'] ?? $l['code'] ?? '—') ?></td>
          <td class="px-5 py-3 text-right"><?= e(number_format((float)$l['liters'], 1, ',', ' ')) ?></td>
          <td class="px-5 py-3 text-right"><?= e(fcfa((int)$l['price_per_liter'])) ?></td>
          <td class="px-5 py-3 text-right font-bold"><?= e(fcfa((int)$l['total_cost'])) ?></td>
          <td class="px-5 py-3 text-right text-slate-500"><?= e($l['km'] ?? '—') ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$logs): ?><tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">Aucun enregistrement</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
