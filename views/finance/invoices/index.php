<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
  // Summary stats from rows
  $rows       = $rows ?? [];
  $totTtc     = array_sum(array_column($rows, 'total_ttc'));
  $totPaid    = array_sum(array_column(array_filter($rows, fn($r) => $r['status'] === 'paid'), 'total_ttc'));
  $cntOverdue = count(array_filter($rows, fn($r) => $r['status'] === 'overdue'));
  $cntIssued  = count(array_filter($rows, fn($r) => $r['status'] === 'issued'));
?>
<div class="space-y-5">

  <!-- Header -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-8 h-8 rounded-xl bg-sky-600 flex items-center justify-center">
          <i data-lucide="file-text" class="w-4 h-4 text-white"></i>
        </span>
        Factures
      </h1>
      <p class="text-xs text-slate-400 mt-0.5"><?= count($rows) ?> facture(s) · <?= e(date_fr(date('Y-m-d'))) ?></p>
    </div>
    <?php if (can('finance.invoices.create')): ?>
    <a href="<?= e(url('finance/invoices/create')) ?>"
       class="flex items-center gap-2 px-4 py-2 bg-sky-600 text-white rounded-xl text-sm font-semibold hover:bg-sky-700 transition shadow-sm">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle facture
    </a>
    <?php endif ?>
  </div>

  <!-- Stats strip -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
      <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Total émis</p>
      <p class="text-2xl font-black text-slate-900 mt-1"><?= number_format(round($totTtc / 1000)) ?>K</p>
      <p class="text-[10px] text-slate-400 mt-0.5">FCFA TTC</p>
    </div>
    <div class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-4">
      <p class="text-[10px] text-emerald-600 uppercase tracking-widest font-semibold">Encaissé</p>
      <p class="text-2xl font-black text-emerald-700 mt-1"><?= number_format(round($totPaid / 1000)) ?>K</p>
      <p class="text-[10px] text-emerald-500 mt-0.5">FCFA payé</p>
    </div>
    <div class="bg-white rounded-2xl border <?= $cntIssued > 0 ? 'border-sky-200' : 'border-slate-100' ?> shadow-sm p-4">
      <p class="text-[10px] text-sky-600 uppercase tracking-widest font-semibold">En attente</p>
      <p class="text-2xl font-black text-sky-700 mt-1"><?= $cntIssued ?></p>
      <p class="text-[10px] text-sky-500 mt-0.5">facture(s) émises</p>
    </div>
    <div class="bg-white rounded-2xl border <?= $cntOverdue > 0 ? 'border-rose-200' : 'border-slate-100' ?> shadow-sm p-4">
      <p class="text-[10px] text-rose-600 uppercase tracking-widest font-semibold">En retard</p>
      <p class="text-2xl font-black <?= $cntOverdue > 0 ? 'text-rose-700' : 'text-slate-300' ?> mt-1"><?= $cntOverdue ?></p>
      <p class="text-[10px] text-rose-400 mt-0.5">facture(s) échues</p>
    </div>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <!-- Filters bar -->
    <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-3 flex-wrap">
      <div class="flex items-center gap-1.5 bg-slate-100 rounded-xl p-1 text-xs">
        <?php
          $statuses = ['all'=>'Tout','issued'=>'Émises','paid'=>'Payées','overdue'=>'Échues','void'=>'Annulées'];
          $curFilter = $_GET['status'] ?? 'all';
          foreach ($statuses as $val => $lbl):
        ?>
          <a href="?status=<?= $val ?>"
             class="px-3 py-1.5 rounded-lg font-semibold transition
                    <?= $curFilter === $val ? 'bg-white shadow text-cb-primary' : 'text-slate-500 hover:text-slate-700' ?>">
            <?= $lbl ?>
          </a>
        <?php endforeach ?>
      </div>
      <div class="ml-auto text-xs text-slate-400"><?= count($rows) ?> résultat(s)</div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 border-b border-slate-100">
          <tr class="text-[10px] text-slate-400 uppercase tracking-wider">
            <th class="px-5 py-3 text-left font-semibold">N° Facture</th>
            <th class="px-5 py-3 text-left font-semibold">Type</th>
            <th class="px-5 py-3 text-left font-semibold">Client</th>
            <th class="px-5 py-3 text-right font-semibold">HT</th>
            <th class="px-5 py-3 text-right font-semibold">TVA</th>
            <th class="px-5 py-3 text-right font-semibold">TTC</th>
            <th class="px-5 py-3 text-center font-semibold">Statut</th>
            <th class="px-5 py-3 text-left font-semibold">Émise</th>
            <th class="px-5 py-3 text-right font-semibold">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php
            $filtered = $curFilter === 'all' ? $rows : array_filter($rows, fn($r) => $r['status'] === $curFilter);
            foreach ($filtered as $r):
              $stMap = [
                'paid'      => ['bg-emerald-100 text-emerald-700', 'Payée'],
                'partial'   => ['bg-amber-100 text-amber-700',   'Partiel'],
                'overdue'   => ['bg-rose-100 text-rose-700',     'Échue'],
                'issued'    => ['bg-sky-100 text-sky-700',       'Émise'],
                'draft'     => ['bg-slate-100 text-slate-500',   'Brouillon'],
                'void'      => ['bg-slate-100 text-slate-400',   'Annulée'],
                'cancelled' => ['bg-slate-100 text-slate-400',   'Annulée'],
              ];
              [$stCls, $stLbl] = $stMap[$r['status']] ?? ['bg-slate-100 text-slate-500', ucfirst($r['status'])];
              $typeMap = ['sale'=>'Vente','refund'=>'Remboursement','corporate'=>'Corporate','credit_note'=>'Avoir','proforma'=>'Proforma'];
              $clientName = trim(($r['corporate_name'] ?? '') ?: (($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')));
          ?>
          <tr class="hover:bg-slate-50 transition group">
            <td class="px-5 py-3">
              <a href="<?= e(url('finance/invoices/' . $r['id'])) ?>"
                 class="font-mono text-xs font-bold text-sky-700 hover:underline group-hover:text-cb-primary transition">
                <?= e($r['invoice_number']) ?>
              </a>
            </td>
            <td class="px-5 py-3">
              <span class="text-xs text-slate-500"><?= e($typeMap[$r['type']] ?? ucfirst($r['type'])) ?></span>
            </td>
            <td class="px-5 py-3 text-xs text-slate-700 font-medium"><?= e($clientName ?: '—') ?></td>
            <td class="px-5 py-3 text-right font-mono text-xs text-slate-500"><?= number_format((int)$r['total_ht']) ?></td>
            <td class="px-5 py-3 text-right font-mono text-xs text-slate-400"><?= number_format((int)$r['total_tax']) ?></td>
            <td class="px-5 py-3 text-right font-mono text-sm font-black text-slate-900"><?= number_format((int)$r['total_ttc']) ?></td>
            <td class="px-5 py-3 text-center">
              <span class="text-[10px] font-bold px-2.5 py-1 rounded-full <?= $stCls ?>"><?= $stLbl ?></span>
            </td>
            <td class="px-5 py-3 text-xs text-slate-400"><?= e(date('d/m/Y', strtotime($r['issued_at']))) ?></td>
            <td class="px-5 py-3 text-right">
              <div class="flex items-center gap-2 justify-end">
                <a href="<?= e(url('finance/invoices/' . $r['id'])) ?>"
                   class="text-xs text-slate-500 hover:text-cb-primary transition flex items-center gap-1">
                  <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                </a>
                <?php if (!in_array($r['status'], ['paid', 'void', 'cancelled']) && can('finance.invoices.create')): ?>
                  <form method="post" action="<?= e(url('finance/invoices/' . $r['id'] . '/pay')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button class="text-xs text-emerald-600 hover:text-emerald-800 font-semibold flex items-center gap-1">
                      <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Payé
                    </button>
                  </form>
                <?php endif ?>
                <?php if (in_array($r['status'], ['draft','issued']) && can('finance.invoices.cancel')): ?>
                  <form method="post" action="<?= e(url('finance/invoices/' . $r['id'] . '/void')) ?>"
                        onsubmit="return confirm('Annuler cette facture ?')" class="inline">
                    <?= csrf_field() ?>
                    <button class="text-xs text-rose-400 hover:text-rose-600 transition">
                      <i data-lucide="x-circle" class="w-3.5 h-3.5"></i>
                    </button>
                  </form>
                <?php endif ?>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($filtered)): ?>
          <tr><td colspan="9" class="px-5 py-14 text-center">
            <i data-lucide="file-text" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
            <p class="text-slate-400 text-sm">Aucune facture<?= $curFilter !== 'all' ? ' dans ce statut' : '' ?>.</p>
          </td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $view->end() ?>
