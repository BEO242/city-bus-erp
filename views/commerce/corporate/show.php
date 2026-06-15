<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('commerce/corporate')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($account['company_name']) ?></h1>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Remise négociée</p>
      <p class="text-2xl font-bold text-emerald-600"><?= number_format((float)$account['discount_percent'], 1, ',', '') ?>%</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Crédit max</p>
      <p class="text-xl font-bold"><?= e(fcfa((int)$account['credit_limit_fcfa'])) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Solde dû</p>
      <p class="text-xl font-bold <?= (int)$account['current_balance_fcfa'] > 0 ? 'text-amber-600' : '' ?>"><?= e(fcfa((int)$account['current_balance_fcfa'])) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Délai paiement</p>
      <p class="text-xl font-bold"><?= (int)$account['payment_terms_days'] ?> j</p>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100"><h2 class="font-semibold">Tickets récents (<?= count($tickets) ?>)</h2></div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
        <tr><th class="px-3 py-2 text-left">Ticket</th><th class="px-3 py-2 text-left">Voyage</th><th class="px-3 py-2 text-left">Passager</th><th class="px-3 py-2 text-right">Prix</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($tickets as $t): ?>
          <tr><td class="px-3 py-2 font-mono text-xs"><?= e($t['ticket_number']) ?></td>
              <td class="px-3 py-2"><?= e($t['line_code']) ?> · <?= e($t['trip_code']) ?></td>
              <td class="px-3 py-2"><?= e($t['passenger_name']) ?></td>
              <td class="px-3 py-2 text-right"><?= e(fcfa((int)$t['price_fcfa'])) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
