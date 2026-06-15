<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Journal comptable</h1>
      <p class="text-slate-500 text-sm">Écritures SYSCOHADA générées automatiquement à chaque transaction.</p>
    </div>
    <div class="flex gap-2">
      <a href="<?= e(url('finance/accounting/export?from=' . $from . '&to=' . $to)) ?>" class="px-4 py-2 rounded-xl bg-slate-900 text-white">Export CSV</a>
      <a href="<?= e(url('finance/accounting/export-sage?from=' . $from . '&to=' . $to)) ?>" class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50">Export Sage</a>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 grid grid-cols-2 md:grid-cols-5 gap-2">
    <input type="date" name="from" value="<?= e($from) ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <input type="date" name="to"   value="<?= e($to)   ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <select name="journal" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="">Tous journaux</option>
      <?php foreach ($journals as $k => $lbl): ?>
        <option value="<?= e($k) ?>" <?= $journal===$k?'selected':'' ?>><?= e($k) ?> · <?= e($lbl) ?></option>
      <?php endforeach ?>
    </select>
    <select name="account" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="">Tous comptes</option>
      <?php foreach ($accounts as $a): ?>
        <option value="<?= e($a['code']) ?>" <?= $account===$a['code']?'selected':'' ?>><?= e($a['code']) ?> · <?= e($a['label']) ?></option>
      <?php endforeach ?>
    </select>
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Filtrer</button>
  </form>

  <div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Débit total</p>
      <p class="text-xl font-bold"><?= e(fcfa($totalDebit)) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Crédit total</p>
      <p class="text-xl font-bold"><?= e(fcfa($totalCredit)) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Équilibre</p>
      <p class="text-xl font-bold <?= ($totalDebit === $totalCredit) ? 'text-emerald-600' : 'text-rose-600' ?>"><?= e(fcfa($totalDebit - $totalCredit)) ?></p>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100"><h2 class="font-semibold">Écritures (<?= count($entries) ?>)</h2></div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
          <tr>
            <th class="px-3 py-2 text-left">Date</th>
            <th class="px-3 py-2 text-left">Jrn</th>
            <th class="px-3 py-2 text-left">Compte</th>
            <th class="px-3 py-2 text-left">Libellé</th>
            <th class="px-3 py-2 text-left">Pièce</th>
            <th class="px-3 py-2 text-left">Tiers</th>
            <th class="px-3 py-2 text-right">Débit</th>
            <th class="px-3 py-2 text-right">Crédit</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($entries as $e): ?>
          <tr class="hover:bg-cb-bg/40">
            <td class="px-3 py-1.5"><?= e(date('d/m/Y', strtotime((string)$e['entry_date']))) ?></td>
            <td class="px-3 py-1.5"><span class="px-1.5 py-0.5 rounded text-xs bg-slate-100"><?= e($e['journal_code']) ?></span></td>
            <td class="px-3 py-1.5 font-mono text-xs"><?= e($e['account_code']) ?><br><span class="text-slate-400 text-[10px]"><?= e($e['account_label'] ?? '') ?></span></td>
            <td class="px-3 py-1.5"><?= e($e['label']) ?></td>
            <td class="px-3 py-1.5 font-mono text-xs"><?= e($e['reference'] ?? '') ?></td>
            <td class="px-3 py-1.5"><?= e($e['third_party'] ?? '') ?></td>
            <td class="px-3 py-1.5 text-right font-mono"><?= (int)$e['debit_fcfa']  > 0 ? number_format((int)$e['debit_fcfa'], 0, ',', ' ') : '' ?></td>
            <td class="px-3 py-1.5 text-right font-mono"><?= (int)$e['credit_fcfa'] > 0 ? number_format((int)$e['credit_fcfa'], 0, ',', ' ') : '' ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (!$entries): ?>
          <tr><td colspan="8" class="py-12 text-center text-slate-400">Aucune écriture pour la période</td></tr>
        <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100"><h2 class="font-semibold">Balance par compte</h2></div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-3 py-2 text-left">Compte</th>
          <th class="px-3 py-2 text-left">Libellé</th>
          <th class="px-3 py-2 text-right">Débit</th>
          <th class="px-3 py-2 text-right">Crédit</th>
          <th class="px-3 py-2 text-right">Solde</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($totals as $t): ?>
        <tr>
          <td class="px-3 py-1.5 font-mono"><?= e($t['account_code']) ?></td>
          <td class="px-3 py-1.5"><?= e($t['account_label'] ?? '—') ?></td>
          <td class="px-3 py-1.5 text-right"><?= number_format((int)$t['total_debit'], 0, ',', ' ') ?></td>
          <td class="px-3 py-1.5 text-right"><?= number_format((int)$t['total_credit'], 0, ',', ' ') ?></td>
          <td class="px-3 py-1.5 text-right font-bold"><?= number_format((int)$t['balance'], 0, ',', ' ') ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
