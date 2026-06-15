<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Partenaires commerciaux</h1>
      <p class="text-slate-500 text-sm">Revendeurs externes avec commissionnement.</p>
    </div>
    <?php if (can('partners.manage')): ?>
      <a href="<?= e(url('commerce/partners/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white">+ Nouveau partenaire</a>
    <?php endif ?>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Nom</th>
          <th class="px-5 py-3 text-left">Code</th>
          <th class="px-5 py-3 text-right">Commission</th>
          <th class="px-5 py-3 text-right">Tickets</th>
          <th class="px-5 py-3 text-right">Total commissions</th>
          <th class="px-5 py-3 text-center">Actif</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($partners as $p): ?>
        <tr>
          <td class="px-5 py-3 font-semibold"><?= e($p['name']) ?></td>
          <td class="px-5 py-3 font-mono"><?= e($p['code']) ?></td>
          <td class="px-5 py-3 text-right"><?= number_format((float)$p['commission_percent'], 2, ',', '') ?> %</td>
          <td class="px-5 py-3 text-right"><?= (int)$p['tickets_count'] ?></td>
          <td class="px-5 py-3 text-right font-bold text-cb-primary"><?= e(fcfa((int)$p['commission_total'])) ?></td>
          <td class="px-5 py-3 text-center"><?= $p['is_active'] ? '✓' : '×' ?></td>
          <td class="px-5 py-3 text-right"><a href="<?= e(url('commerce/partners/' . $p['id'])) ?>" class="text-cb-primary hover:underline">Voir</a></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$partners): ?><tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Aucun partenaire</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
