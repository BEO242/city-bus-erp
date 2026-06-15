<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
  $entries    = $entries ?? [];
  $totDebit   = array_sum(array_column($entries, 'sum_debit'));
  $totCredit  = array_sum(array_column($entries, 'sum_credit'));
  $unposted   = count(array_filter($entries, fn($e) => !$e['posted_at']));
  $journalLabels = ['sales'=>'Ventes','purchases'=>'Achats','cash'=>'Caisse','bank'=>'Banque','salary'=>'Paie','misc'=>'Divers'];
?>
<div class="space-y-5">

  <!-- Header -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-8 h-8 rounded-xl bg-emerald-700 flex items-center justify-center">
          <i data-lucide="book-open" class="w-4 h-4 text-white"></i>
        </span>
        Journal · <span class="text-emerald-700"><?= e($journalLabels[$journal] ?? strtoupper($journal)) ?></span>
      </h1>
      <p class="text-xs text-slate-400 mt-0.5">Écritures comptables SYSCOHADA · <?= e(date_fr($from)) ?> → <?= e(date_fr($to)) ?></p>
    </div>
    <div class="flex items-center gap-2">
      <?php if (can('finance.accounting.export')): ?>
        <a href="<?= e(url('finance/accounting-v4/export?journal=' . urlencode($journal) . '&from=' . $from . '&to=' . $to)) ?>"
           class="flex items-center gap-1.5 px-3 py-2 text-xs bg-white border border-slate-200 text-slate-600 rounded-xl hover:border-emerald-500 hover:text-emerald-700 transition font-semibold">
          <i data-lucide="download" class="w-3.5 h-3.5"></i> Exporter CSV
        </a>
      <?php endif ?>
    </div>
  </div>

  <!-- Filter bar + stats -->
  <div class="grid grid-cols-1 lg:grid-cols-4 gap-3">
    <!-- Filter form -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
      <form method="get" class="flex items-center gap-2 flex-wrap">
        <select name="journal" onchange="this.form.submit()"
                class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 bg-slate-50">
          <?php foreach ($journalLabels as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $journal === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach ?>
        </select>
        <input type="date" name="from" value="<?= e($from) ?>"
               class="px-3 py-2 rounded-xl border border-slate-200 text-sm bg-slate-50">
        <input type="date" name="to" value="<?= e($to) ?>"
               class="px-3 py-2 rounded-xl border border-slate-200 text-sm bg-slate-50">
        <button class="px-4 py-2 rounded-xl bg-emerald-700 text-white text-sm font-semibold hover:bg-emerald-800 transition">
          <i data-lucide="search" class="w-3.5 h-3.5 inline mr-1"></i>Afficher
        </button>
      </form>
    </div>

    <!-- Totals -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
        <i data-lucide="arrow-down-circle" class="w-4 h-4 text-emerald-700"></i>
      </div>
      <div>
        <p class="text-[10px] text-slate-400 uppercase font-semibold">Total débit</p>
        <p class="text-lg font-black text-emerald-700"><?= number_format($totDebit) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-sky-100 flex items-center justify-center shrink-0">
        <i data-lucide="arrow-up-circle" class="w-4 h-4 text-sky-700"></i>
      </div>
      <div>
        <p class="text-[10px] text-slate-400 uppercase font-semibold">Total crédit</p>
        <p class="text-lg font-black text-sky-700"><?= number_format($totCredit) ?></p>
      </div>
    </div>
  </div>

  <!-- Equilibrium check -->
  <?php $diff = abs($totDebit - $totCredit); ?>
  <?php if ($diff > 0 && count($entries) > 0): ?>
  <div class="flex items-center gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <span>Déséquilibre de <strong><?= number_format($diff) ?> FCFA</strong> entre débit et crédit sur ce journal.</span>
  </div>
  <?php elseif (count($entries) > 0): ?>
  <div class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 border border-emerald-100 rounded-xl text-xs text-emerald-700 font-semibold">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    Journal équilibré — Débit = Crédit = <?= number_format($totDebit) ?> FCFA
  </div>
  <?php endif ?>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
      <p class="text-sm font-bold text-slate-700"><?= count($entries) ?> écriture(s)</p>
      <?php if ($unposted > 0): ?>
        <span class="text-xs bg-amber-100 text-amber-700 font-bold px-3 py-1 rounded-full"><?= $unposted ?> brouillon(s)</span>
      <?php endif ?>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 border-b border-slate-100">
          <tr class="text-[10px] text-slate-400 uppercase tracking-wider">
            <th class="px-5 py-3 text-left font-semibold">Date</th>
            <th class="px-5 py-3 text-left font-semibold">Référence</th>
            <th class="px-5 py-3 text-left font-semibold">Libellé</th>
            <th class="px-5 py-3 text-center font-semibold">Lgn.</th>
            <th class="px-5 py-3 text-right font-semibold">Débit</th>
            <th class="px-5 py-3 text-right font-semibold">Crédit</th>
            <th class="px-5 py-3 text-center font-semibold">Statut</th>
            <th class="px-5 py-3 text-right font-semibold">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($entries as $e):
            $posted = !empty($e['posted_at']);
          ?>
          <tr class="hover:bg-slate-50 transition <?= !$posted ? 'bg-amber-50/30' : '' ?>">
            <td class="px-5 py-3 text-xs font-semibold text-slate-600"><?= e(date('d/m/Y', strtotime($e['entry_date']))) ?></td>
            <td class="px-5 py-3 font-mono text-xs text-violet-700 font-bold"><?= e($e['reference'] ?? '—') ?></td>
            <td class="px-5 py-3 text-sm text-slate-800 max-w-xs truncate"><?= e($e['label']) ?></td>
            <td class="px-5 py-3 text-center text-xs text-slate-400"><?= (int)$e['line_count'] ?></td>
            <td class="px-5 py-3 text-right font-mono text-sm text-emerald-700 font-semibold"><?= number_format((int)$e['sum_debit']) ?></td>
            <td class="px-5 py-3 text-right font-mono text-sm text-sky-700 font-semibold"><?= number_format((int)$e['sum_credit']) ?></td>
            <td class="px-5 py-3 text-center">
              <?php if ($posted): ?>
                <span class="inline-flex items-center gap-1 text-[10px] bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-full">
                  <i data-lucide="check-circle-2" class="w-3 h-3"></i> Posté
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 text-[10px] bg-amber-100 text-amber-700 font-bold px-2 py-0.5 rounded-full">
                  <i data-lucide="circle-dashed" class="w-3 h-3"></i> Brouillon
                </span>
              <?php endif ?>
            </td>
            <td class="px-5 py-3 text-right">
              <?php if (!$posted && can('finance.accounting.post')): ?>
                <form method="post" action="<?= e(url('finance/accounting-v4/' . $e['id'] . '/post')) ?>" class="inline">
                  <?= csrf_field() ?>
                  <button class="text-xs text-emerald-700 font-semibold hover:underline flex items-center gap-1">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i> Valider
                  </button>
                </form>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($entries)): ?>
          <tr><td colspan="8" class="px-5 py-14 text-center">
            <i data-lucide="book-open" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
            <p class="text-slate-400 text-sm">Aucune écriture sur ce journal pour la période.</p>
          </td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $view->end() ?>
