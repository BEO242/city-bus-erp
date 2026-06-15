<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Codes promo</h1>
      <p class="text-slate-500 text-sm">Réductions ponctuelles applicables à la vente.</p>
    </div>
    <?php if (can('promo.manage')): ?>
      <a href="<?= e(url('commerce/promo/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white"><i data-lucide="plus" class="w-4 h-4 inline"></i> Nouveau code</a>
    <?php endif ?>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Code</th>
          <th class="px-5 py-3 text-left">Libellé</th>
          <th class="px-5 py-3 text-left">Type</th>
          <th class="px-5 py-3 text-right">Valeur</th>
          <th class="px-5 py-3 text-right">Utilisations</th>
          <th class="px-5 py-3 text-left">Validité</th>
          <th class="px-5 py-3 text-center">Actif</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($promos as $p): ?>
        <tr>
          <td class="px-5 py-3 font-mono text-cb-primary"><?= e($p['code']) ?></td>
          <td class="px-5 py-3"><?= e($p['label']) ?></td>
          <td class="px-5 py-3 text-xs"><?= e($p['discount_type']) ?></td>
          <td class="px-5 py-3 text-right font-semibold"><?= $p['discount_type']==='percent' ? (int)$p['discount_value'].'%' : e(fcfa((int)$p['discount_value'])) ?></td>
          <td class="px-5 py-3 text-right"><?= (int)$p['used_count'] ?><?= $p['max_uses'] ? ' / '.(int)$p['max_uses'] : '' ?></td>
          <td class="px-5 py-3 text-xs"><?= $p['valid_until'] ? e(date('d/m/Y', strtotime((string)$p['valid_until']))) : '∞' ?></td>
          <td class="px-5 py-3 text-center"><?= $p['is_active'] ? '✓' : '×' ?></td>
          <td class="px-5 py-3 text-right">
            <?php if (can('promo.manage') && $p['is_active']): ?>
              <form method="post" action="<?= e(url('commerce/promo/' . $p['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Désactiver ?')">
                <?= csrf_field() ?>
                <button class="text-rose-600 hover:underline text-xs">Désactiver</button>
              </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$promos): ?>
        <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Aucun code</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
