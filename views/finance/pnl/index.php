<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Profitabilité par voyage / ligne</h1>
      <p class="text-slate-500 text-sm">Analyse de la marge contributive et nette de chaque ligne sur la période.</p>
    </div>
    <div class="flex gap-2">
      <a href="<?= e(url('finance/pnl/export?from=' . $from . '&to=' . $to)) ?>" class="px-4 py-2 rounded-xl bg-slate-900 text-white">Export CSV</a>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <input type="date" name="from" value="<?= e($from) ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <input type="date" name="to"   value="<?= e($to)   ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Calculer</button>
  </form>

  <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Voyages</p>
      <p class="text-2xl font-bold"><?= (int)$totals['trips'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Recettes</p>
      <p class="text-xl font-bold text-emerald-600"><?= e(fcfa((int)$totals['revenue'])) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Coûts directs</p>
      <p class="text-xl font-bold text-amber-600"><?= e(fcfa((int)$totals['cost_direct'])) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Marge contributive</p>
      <p class="text-xl font-bold"><?= e(fcfa((int)$totals['margin_contrib'])) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Marge nette</p>
      <p class="text-xl font-bold <?= $totals['margin_net'] >= 0 ? 'text-cb-primary' : 'text-rose-600' ?>"><?= e(fcfa((int)$totals['margin_net'])) ?></p>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
      <h2 class="font-semibold">Par ligne</h2>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-2 text-left">Ligne</th>
          <th class="px-5 py-2 text-right">Voyages</th>
          <th class="px-5 py-2 text-right">Recettes</th>
          <th class="px-5 py-2 text-right">Coûts directs</th>
          <th class="px-5 py-2 text-right">Coûts indirects</th>
          <th class="px-5 py-2 text-right">Marge contrib.</th>
          <th class="px-5 py-2 text-right">Marge nette</th>
          <th class="px-5 py-2 text-right">Charge moy.</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($byLine as $r): ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-2"><code class="text-xs bg-slate-100 px-2 py-0.5 rounded"><?= e($r['code']) ?></code> <?= e($r['name']) ?></td>
          <td class="px-5 py-2 text-right"><?= (int)$r['trips_count'] ?></td>
          <td class="px-5 py-2 text-right"><?= e(fcfa((int)$r['revenue'])) ?></td>
          <td class="px-5 py-2 text-right text-amber-600"><?= e(fcfa((int)$r['cost_direct'])) ?></td>
          <td class="px-5 py-2 text-right text-slate-500"><?= e(fcfa((int)$r['cost_indirect'])) ?></td>
          <td class="px-5 py-2 text-right font-semibold <?= (int)$r['margin_contrib'] >= 0 ? '' : 'text-rose-600' ?>"><?= e(fcfa((int)$r['margin_contrib'])) ?></td>
          <td class="px-5 py-2 text-right font-bold <?= (int)$r['margin_net'] >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= e(fcfa((int)$r['margin_net'])) ?></td>
          <td class="px-5 py-2 text-right"><?= number_format((float)$r['avg_load'], 1, ',', '') ?>%</td>
        </tr>
      <?php endforeach ?>
      <?php if (!$byLine): ?>
        <tr><td colspan="8" class="px-5 py-6 text-center text-slate-400">Aucune donnée pour la période</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
        <i data-lucide="trophy" class="w-4 h-4 text-emerald-600"></i> Top 10 voyages les plus rentables
      </h2>
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($best as $b): ?>
          <tr>
            <td class="py-2"><a href="<?= e(url('finance/pnl/trip/' . $b['trip_id'])) ?>" class="text-cb-primary hover:underline"><?= e($b['trip_code']) ?></a> <span class="text-xs text-slate-500"><?= e($b['line_code']) ?></span></td>
            <td class="py-2 text-right text-emerald-600 font-bold"><?= e(fcfa((int)$b['margin_net'])) ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i> Voyages à perte
      </h2>
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($worst as $w): ?>
          <tr>
            <td class="py-2"><a href="<?= e(url('finance/pnl/trip/' . $w['trip_id'])) ?>" class="text-cb-primary hover:underline"><?= e($w['trip_code']) ?></a> <span class="text-xs text-slate-500"><?= e($w['line_code']) ?></span></td>
            <td class="py-2 text-right <?= (int)$w['margin_net'] < 0 ? 'text-rose-600 font-bold' : '' ?>"><?= e(fcfa((int)$w['margin_net'])) ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $view->end() ?>
