<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('commerce/partners')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($partner['name']) ?></h1>
    <p class="text-slate-500 text-sm font-mono"><?= e($partner['code']) ?> · Commission <?= number_format((float)$partner['commission_percent'], 2, ',', '') ?>%</p>
  </div>

  <div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft"><p class="text-xs text-slate-400">Tickets</p><p class="text-2xl font-bold"><?= (int)$stats['tickets'] ?></p></div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft"><p class="text-xs text-slate-400">CA</p><p class="text-xl font-bold"><?= e(fcfa((int)$stats['revenue'])) ?></p></div>
    <div class="bg-cb-primary text-white rounded-2xl p-5 shadow-soft"><p class="text-xs opacity-80">Commissions dues</p><p class="text-2xl font-bold"><?= e(fcfa((int)$stats['commission'])) ?></p></div>
  </div>

  <?php if (can('partners.manage')): ?>
  <form method="post" action="<?= e(url('commerce/partners/' . $partner['id'] . '/payout')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft flex gap-2 items-end">
    <?= csrf_field() ?>
    <div><label class="text-xs text-slate-500">Du</label><input type="date" name="from" value="<?= date('Y-m-01') ?>" class="px-3 py-2 rounded-xl border border-slate-200"></div>
    <div><label class="text-xs text-slate-500">Au</label><input type="date" name="to" value="<?= date('Y-m-d') ?>" class="px-3 py-2 rounded-xl border border-slate-200"></div>
    <button class="px-4 py-2 rounded-xl bg-cb-primary text-white">Générer payout</button>
  </form>
  <?php endif ?>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100"><h2 class="font-semibold">Payouts</h2></div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
        <tr><th class="px-3 py-2 text-left">Période</th><th class="px-3 py-2 text-right">Tickets</th><th class="px-3 py-2 text-right">CA</th><th class="px-3 py-2 text-right">Commission</th><th class="px-3 py-2 text-center">Statut</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($payouts as $po): ?>
        <tr>
          <td class="px-3 py-2"><?= e($po['period_from']) ?> → <?= e($po['period_to']) ?></td>
          <td class="px-3 py-2 text-right"><?= (int)$po['tickets_count'] ?></td>
          <td class="px-3 py-2 text-right"><?= e(fcfa((int)$po['revenue_fcfa'])) ?></td>
          <td class="px-3 py-2 text-right font-bold"><?= e(fcfa((int)$po['commission_fcfa'])) ?></td>
          <td class="px-3 py-2 text-center"><span class="text-xs px-2 py-0.5 rounded <?= $po['status']==='paid'?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700' ?>"><?= e($po['status']) ?></span></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
