<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$sections = ['tickets' => 'Billets passagers', 'baggage' => 'Bagages', 'cargo' => 'Fret'];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Déclaration TVA</h1>
      <p class="text-slate-500 text-sm">Ventilation des recettes par taux de TVA pour la période choisie.</p>
    </div>
    <div class="flex gap-2">
      <a href="<?= e(url('finance/tax/vat/export?from=' . $from . '&to=' . $to)) ?>" class="px-4 py-2 rounded-xl bg-slate-900 text-white">Export CSV</a>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <input type="date" name="from" value="<?= e($from) ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <input type="date" name="to"   value="<?= e($to)   ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Calculer</button>
  </form>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Documents</p>
      <p class="text-2xl font-bold"><?= (int)$totals['docs'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Total HT</p>
      <p class="text-xl font-bold"><?= e(fcfa((int)$totals['ht'])) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Total TVA</p>
      <p class="text-xl font-bold text-amber-600"><?= e(fcfa((int)$totals['tax'])) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Total TTC</p>
      <p class="text-xl font-bold text-cb-primary"><?= e(fcfa((int)$totals['ttc'])) ?></p>
    </div>
  </div>

  <?php foreach ($sections as $key => $label): ?>
    <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="font-semibold"><?= e($label) ?></h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
          <tr>
            <th class="px-5 py-2 text-left">Taux</th>
            <th class="px-5 py-2 text-right">Base HT</th>
            <th class="px-5 py-2 text-right">TVA</th>
            <th class="px-5 py-2 text-right">TTC</th>
            <th class="px-5 py-2 text-right">Documents</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($report[$key] as $row): $ht = (int)($row['ht'] ?? 0); $tax = (int)($row['tax'] ?? 0); ?>
          <tr>
            <td class="px-5 py-2 font-medium"><?= number_format((float)($row['rate'] ?? 0), 2, ',', ' ') ?> %</td>
            <td class="px-5 py-2 text-right"><?= e(fcfa($ht)) ?></td>
            <td class="px-5 py-2 text-right"><?= e(fcfa($tax)) ?></td>
            <td class="px-5 py-2 text-right font-semibold"><?= e(fcfa($ht + $tax)) ?></td>
            <td class="px-5 py-2 text-right"><?= (int)($row['count_docs'] ?? 0) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (!$report[$key]): ?>
          <tr><td colspan="5" class="px-5 py-6 text-center text-slate-400">Aucune donnée pour la période.</td></tr>
        <?php endif ?>
        </tbody>
      </table>
    </div>
  <?php endforeach ?>
</div>
<?php $view->end() ?>
