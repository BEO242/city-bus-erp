<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
    <i data-lucide="banknote" class="w-6 h-6 text-cb-primary"></i> Ma caisse
  </h1>

  <?php if ($current): ?>
    <div class="bg-emerald-500 text-white rounded-2xl p-5 shadow-soft">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-xs uppercase opacity-80">Caisse ouverte #<?= (int)$current['id'] ?></div>
          <div class="text-2xl font-bold mt-1">depuis <?= e(date('d/m H:i', strtotime($current['opened_at']))) ?></div>
          <div class="text-sm opacity-90 mt-1">Solde initial : <?= number_format((int)$current['opening_balance']) ?> FCFA</div>
        </div>
        <?php if (can('caisse.drawers.close')): ?>
          <form method="post" action="<?= e(url('caisse/drawer/' . $current['id'] . '/close')) ?>" class="flex items-end gap-2" onsubmit="return confirm('Clôturer la caisse ?')">
            <?= csrf_field() ?>
            <input name="declared_cash" type="number" min="0" required placeholder="Espèces comptées" class="px-3 py-2 rounded-lg text-slate-900 text-sm">
            <button class="px-4 py-2 rounded-lg bg-white text-emerald-700 font-bold">Clôturer</button>
          </form>
        <?php endif ?>
      </div>
    </div>

    <?php if (!empty($summary['by_method'])): ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100"><h2 class="font-bold text-slate-900">Mouvements par mode</h2></div>
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-600">
            <tr>
              <th class="px-3 py-2 text-left">Mode</th>
              <th class="px-3 py-2 text-left">Type</th>
              <th class="px-3 py-2 text-center">N</th>
              <th class="px-3 py-2 text-right">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($summary['by_method'] as $b): ?>
              <tr><td class="px-3 py-2 font-mono"><?= e($b['payment_method']) ?></td>
                <td class="px-3 py-2 text-xs"><?= e($b['movement_type']) ?></td>
                <td class="px-3 py-2 text-center"><?= (int)$b['n'] ?></td>
                <td class="px-3 py-2 text-right font-mono font-bold"><?= number_format((int)$b['total']) ?></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  <?php else: ?>
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-soft">
      <h2 class="font-bold text-slate-900 mb-3">Ouvrir une caisse</h2>
      <?php if (can('caisse.drawers.open')): ?>
        <form method="post" action="<?= e(url('caisse/drawer/open')) ?>" class="flex items-end gap-2">
          <?= csrf_field() ?>
          <div class="flex-1">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Solde initial (FCFA)</label>
            <input name="opening_balance" type="number" min="0" value="0" class="w-full px-3 py-2 rounded-lg border border-slate-200">
          </div>
          <div class="flex-1">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Code caisse (optionnel)</label>
            <input name="drawer_code" class="w-full px-3 py-2 rounded-lg border border-slate-200" placeholder="CAI-001">
          </div>
          <button class="px-5 py-2 rounded-lg bg-cb-primary text-white font-semibold">Ouvrir</button>
        </form>
      <?php endif ?>
    </div>
  <?php endif ?>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100"><h2 class="font-bold text-slate-900">Historique caisses</h2></div>
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-3 py-2 text-left">Ouverte</th>
          <th class="px-3 py-2 text-left">Fermée</th>
          <th class="px-3 py-2 text-right">Initial</th>
          <th class="px-3 py-2 text-right">Attendu</th>
          <th class="px-3 py-2 text-right">Déclaré</th>
          <th class="px-3 py-2 text-right">Variance</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($history as $h):
          $vCol = ((int)$h['variance']) === 0 ? 'emerald' : (abs((int)$h['variance']) > 5000 ? 'rose' : 'amber');
        ?>
          <tr>
            <td class="px-3 py-2 text-xs"><?= e(date('d/m H:i', strtotime($h['opened_at']))) ?></td>
            <td class="px-3 py-2 text-xs"><?= $h['closed_at'] ? e(date('d/m H:i', strtotime($h['closed_at']))) : '<em>en cours</em>' ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$h['opening_balance']) ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= $h['expected_cash_close'] !== null ? number_format((int)$h['expected_cash_close']) : '—' ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= $h['declared_cash_close'] !== null ? number_format((int)$h['declared_cash_close']) : '—' ?></td>
            <td class="px-3 py-2 text-right font-mono font-bold text-<?= $vCol ?>-700">
              <?= $h['variance'] !== null ? number_format((int)$h['variance']) : '—' ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
