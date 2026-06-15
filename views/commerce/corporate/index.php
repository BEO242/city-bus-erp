<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Comptes corporate</h1>
      <p class="text-slate-500 text-sm">Entreprises avec tarif négocié et facturation différée.</p>
    </div>
    <?php if (can('corporate.manage')): ?>
      <a href="<?= e(url('commerce/corporate/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white">+ Nouveau compte</a>
    <?php endif ?>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Société</th>
          <th class="px-5 py-3 text-left">Contact</th>
          <th class="px-5 py-3 text-right">Remise</th>
          <th class="px-5 py-3 text-right">Crédit max</th>
          <th class="px-5 py-3 text-right">Solde dû</th>
          <th class="px-5 py-3 text-right">Tickets</th>
          <th class="px-5 py-3 text-center">Actif</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($accounts as $a): ?>
        <tr>
          <td class="px-5 py-3 font-semibold"><?= e($a['company_name']) ?><?php if ($a['legal_id']): ?><div class="text-xs text-slate-400"><?= e($a['legal_id']) ?></div><?php endif ?></td>
          <td class="px-5 py-3"><?= e($a['contact_name']) ?><div class="text-xs text-slate-400"><?= e($a['contact_phone']) ?></div></td>
          <td class="px-5 py-3 text-right font-bold text-emerald-600"><?= number_format((float)$a['discount_percent'], 1, ',', '') ?> %</td>
          <td class="px-5 py-3 text-right"><?= e(fcfa((int)$a['credit_limit_fcfa'])) ?></td>
          <td class="px-5 py-3 text-right <?= (int)$a['current_balance_fcfa'] > 0 ? 'text-amber-600 font-bold' : '' ?>"><?= e(fcfa((int)$a['current_balance_fcfa'])) ?></td>
          <td class="px-5 py-3 text-right"><?= (int)$a['tickets_count'] ?></td>
          <td class="px-5 py-3 text-center"><?= $a['is_active'] ? '✓' : '×' ?></td>
          <td class="px-5 py-3 text-right"><a href="<?= e(url('commerce/corporate/' . $a['id'])) ?>" class="text-cb-primary hover:underline">Voir</a></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$accounts): ?><tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Aucun compte</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
