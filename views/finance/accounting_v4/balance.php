<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900">Balance comptable</h1>
    <form method="get" class="flex items-end gap-2">
      <input type="date" name="from" value="<?= e($from) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
      <input type="date" name="to" value="<?= e($to) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
      <button class="px-4 py-2 rounded-lg bg-cb-primary text-white text-sm">Afficher</button>
      <a href="<?= e(url('finance/accounting-v4/export?from=' . urlencode($from) . '&to=' . urlencode($to))) ?>" class="px-3 py-2 rounded-lg border border-slate-300 text-sm font-semibold">CSV SYSCOHADA</a>
    </form>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-3 py-2 text-left">Compte</th>
          <th class="px-3 py-2 text-left">Libellé</th>
          <th class="px-3 py-2 text-right">Débit</th>
          <th class="px-3 py-2 text-right">Crédit</th>
          <th class="px-3 py-2 text-right">Solde</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php $td=0;$tc=0; foreach ($rows as $r): $td += (int)$r['total_debit']; $tc += (int)$r['total_credit']; ?>
          <tr>
            <td class="px-3 py-2 font-mono font-bold"><?= e($r['account_code']) ?></td>
            <td class="px-3 py-2 text-sm"><?= e($r['label'] ?? '—') ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$r['total_debit']) ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$r['total_credit']) ?></td>
            <td class="px-3 py-2 text-right font-mono <?= (int)$r['solde'] >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>"><?= number_format((int)$r['solde']) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot class="bg-slate-100 font-bold">
        <tr>
          <td colspan="2" class="px-3 py-2 text-right">Totaux</td>
          <td class="px-3 py-2 text-right font-mono"><?= number_format($td) ?></td>
          <td class="px-3 py-2 text-right font-mono"><?= number_format($tc) ?></td>
          <td class="px-3 py-2 text-right font-mono"><?= number_format($td - $tc) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php $view->end() ?>
