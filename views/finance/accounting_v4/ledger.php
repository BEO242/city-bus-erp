<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <h1 class="text-2xl font-bold text-slate-900">Grand livre · compte <?= e($account) ?></h1>
  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-4 shadow-soft flex items-end gap-2">
    <select name="account" class="px-3 py-2 rounded-lg border border-slate-200 text-sm flex-1 max-w-xs">
      <?php foreach ($accounts as $a): ?>
        <option value="<?= e($a['code']) ?>" <?= $account===$a['code']?'selected':'' ?>><?= e($a['code']) ?> · <?= e($a['label']) ?></option>
      <?php endforeach ?>
    </select>
    <input type="date" name="from" value="<?= e($from) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
    <input type="date" name="to" value="<?= e($to) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
    <button class="px-4 py-2 rounded-lg bg-cb-primary text-white text-sm">Afficher</button>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">Référence</th>
          <th class="px-3 py-2 text-left">Libellé</th>
          <th class="px-3 py-2 text-right">Débit</th>
          <th class="px-3 py-2 text-right">Crédit</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php $td=0;$tc=0; foreach ($lines as $l): $td += (int)$l['debit']; $tc += (int)$l['credit']; ?>
          <tr><td class="px-3 py-2 text-xs"><?= e(date('d/m/Y', strtotime($l['entry_date']))) ?></td>
            <td class="px-3 py-2 text-xs font-mono"><?= e($l['reference'] ?: '—') ?></td>
            <td class="px-3 py-2"><?= e($l['label']) ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= (int)$l['debit'] > 0 ? number_format((int)$l['debit']) : '' ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= (int)$l['credit'] > 0 ? number_format((int)$l['credit']) : '' ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot class="bg-slate-100 font-bold">
        <tr>
          <td colspan="3" class="px-3 py-2 text-right">Totaux</td>
          <td class="px-3 py-2 text-right font-mono"><?= number_format($td) ?></td>
          <td class="px-3 py-2 text-right font-mono"><?= number_format($tc) ?></td>
        </tr>
        <tr>
          <td colspan="3" class="px-3 py-2 text-right">Solde</td>
          <td colspan="2" class="px-3 py-2 text-right font-mono <?= ($td-$tc)>=0?'text-emerald-700':'text-rose-700' ?>"><?= number_format($td - $tc) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php $view->end() ?>
