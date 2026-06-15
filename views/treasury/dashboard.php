<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">

  <!-- En-tête -->
  <div class="flex justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold">Trésorerie</h1>
      <p class="text-slate-500 text-sm">Vue d'ensemble des caisses et opérations financières.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <a href="<?= e(url('finance/treasury/transaction?type=encaissement')) ?>"
         class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-medium inline-flex items-center gap-2 text-sm hover:bg-emerald-700 transition">
        <i data-lucide="arrow-down-circle" class="w-4 h-4"></i> Encaissement
      </a>
      <a href="<?= e(url('finance/treasury/transaction?type=decaissement')) ?>"
         class="px-4 py-2.5 rounded-xl bg-rose-600 text-white font-medium inline-flex items-center gap-2 text-sm hover:bg-rose-700 transition">
        <i data-lucide="arrow-up-circle" class="w-4 h-4"></i> Décaissement
      </a>
      <a href="<?= e(url('finance/treasury/transfer')) ?>"
         class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
        <i data-lucide="arrow-left-right" class="w-4 h-4 text-amber-500"></i> Virement
      </a>
      <a href="<?= e(url('finance/treasury/categories')) ?>"
         class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
        <i data-lucide="tags" class="w-4 h-4 text-cb-primary"></i> Catégories
      </a>
    </div>
  </div>

  <!-- KPI du jour -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
          <i data-lucide="arrow-down-circle" class="w-5 h-5 text-emerald-600"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 font-semibold">Encaissements du jour</p>
          <p class="text-xl font-black text-emerald-700"><?= number_format((int)$dayStats['total_in'], 0, ',', ' ') ?> <span class="text-sm font-semibold">FCFA</span></p>
        </div>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center">
          <i data-lucide="arrow-up-circle" class="w-5 h-5 text-rose-600"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 font-semibold">Décaissements du jour</p>
          <p class="text-xl font-black text-rose-700"><?= number_format((int)$dayStats['total_out'], 0, ',', ' ') ?> <span class="text-sm font-semibold">FCFA</span></p>
        </div>
      </div>
    </div>
    <?php $dayNet = (int)$dayStats['total_in'] - (int)$dayStats['total_out']; ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-5">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl <?= $dayNet >= 0 ? 'bg-blue-50' : 'bg-orange-50' ?> flex items-center justify-center">
          <i data-lucide="scale" class="w-5 h-5 <?= $dayNet >= 0 ? 'text-blue-600' : 'text-orange-500' ?>"></i>
        </div>
        <div>
          <p class="text-xs text-slate-500 font-semibold">Solde net du jour</p>
          <p class="text-xl font-black <?= $dayNet >= 0 ? 'text-blue-700' : 'text-orange-600' ?> tabular-nums">
            <?= ($dayNet >= 0 ? '+' : '') . number_format(abs($dayNet), 0, ',', ' ') ?>
            <span class="text-xs font-semibold text-slate-400">FCFA</span>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Caisses ouvertes -->
  <div class="bg-white rounded-2xl border border-slate-100 p-5">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-bold text-slate-700 flex items-center gap-2">
        <i data-lucide="wallet" class="w-4 h-4 text-cb-primary"></i> Caisses ouvertes
      </h2>
      <a href="<?= e(url('finance/treasury/closure')) ?>" class="text-sm text-cb-primary font-semibold hover:underline">Clôturer une caisse</a>
    </div>
    <?php if ($registers): ?>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
      <?php foreach ($registers as $reg): ?>
      <div class="border border-slate-100 rounded-xl p-4 hover:border-cb-primary hover:shadow-soft transition">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-slate-500"><?= e($reg['agency_name']) ?></span>
          <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-semibold">Ouverte</span>
        </div>
        <p class="text-lg font-black text-slate-800"><?= number_format((int)$reg['solde'], 0, ',', ' ') ?> <span class="text-xs font-semibold text-slate-500">FCFA</span></p>
        <p class="text-xs text-slate-400 mt-1">
          <i data-lucide="user" class="w-3 h-3 inline"></i> <?= e($reg['cashier_first'] . ' ' . $reg['cashier_last']) ?>
          · Ouverte <?= e(date('H:i', strtotime($reg['opened_at']))) ?>
        </p>
        <div class="mt-3 flex gap-2">
          <a href="<?= e(url('finance/treasury/closure?register=' . $reg['id'])) ?>"
             class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold transition">
            <i data-lucide="lock" class="w-3 h-3 inline"></i> Clôturer
          </a>
        </div>
      </div>
      <?php endforeach ?>
    </div>
    <?php else: ?>
    <p class="text-slate-400 text-sm py-4 text-center">Aucune caisse ouverte actuellement.</p>
    <?php endif ?>
  </div>

  <!-- Virements en attente -->
  <?php if ($pendingTransfers): ?>
  <div class="bg-white rounded-2xl border border-amber-200 p-5">
    <h2 class="font-bold text-amber-700 flex items-center gap-2 mb-4">
      <i data-lucide="alert-triangle" class="w-4 h-4"></i> Virements en attente de validation (<?= count($pendingTransfers) ?>)
    </h2>
    <div class="space-y-2">
      <?php foreach ($pendingTransfers as $tf): ?>
      <div class="flex items-center justify-between gap-3 p-3 bg-amber-50 rounded-xl">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold"><?= e($tf['from_agency']) ?> → <?= e($tf['to_agency']) ?></p>
          <p class="text-xs text-slate-500">
            <?= number_format((int)$tf['amount_fcfa'], 0, ',', ' ') ?> FCFA
            · Par <?= e($tf['init_first'] . ' ' . $tf['init_last']) ?>
            · <?= e(date('d/m H:i', strtotime($tf['created_at']))) ?>
            <?php if ($tf['motif']): ?> · <?= e($tf['motif']) ?><?php endif ?>
          </p>
        </div>
        <?php if (can('finance.treasury.validate')): ?>
        <div class="flex gap-1 shrink-0">
          <form method="post" action="<?= e(url('finance/treasury/transfer/' . $tf['id'] . '/validate')) ?>" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="valider">
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700" onclick="return confirm('Valider ce virement ?')">
              <i data-lucide="check" class="w-3 h-3 inline"></i> Valider
            </button>
          </form>
          <form method="post" action="<?= e(url('finance/treasury/transfer/' . $tf['id'] . '/validate')) ?>" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="rejeter">
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-100 text-rose-700 text-xs font-semibold hover:bg-rose-200" onclick="return confirm('Rejeter ce virement ?')">
              <i data-lucide="x" class="w-3 h-3 inline"></i> Rejeter
            </button>
          </form>
        </div>
        <?php endif ?>
      </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>

  <!-- Dernières transactions -->
  <div class="bg-white rounded-2xl border border-slate-100">
    <div class="flex items-center justify-between p-5 pb-3">
      <h2 class="font-bold text-slate-700 flex items-center gap-2">
        <i data-lucide="list" class="w-4 h-4 text-cb-primary"></i> Dernières transactions
      </h2>
      <a href="<?= e(url('finance/treasury/transactions')) ?>" class="text-sm text-cb-primary font-semibold hover:underline">Voir tout →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-t border-slate-100 text-left text-xs text-slate-500 uppercase">
            <th class="px-5 py-2.5">Date</th>
            <th class="px-5 py-2.5">Type</th>
            <th class="px-5 py-2.5">Catégorie</th>
            <th class="px-5 py-2.5">Caisse</th>
            <th class="px-5 py-2.5 text-right">Montant</th>
            <th class="px-5 py-2.5">Par</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTx as $tx):
            $isIn = $tx['type'] === 'encaissement';
            $colorMap = [
              'slate'=>'bg-slate-100 text-slate-700','red'=>'bg-red-100 text-red-700','orange'=>'bg-orange-100 text-orange-700',
              'amber'=>'bg-amber-100 text-amber-700','green'=>'bg-emerald-100 text-emerald-700','blue'=>'bg-blue-100 text-blue-700',
              'violet'=>'bg-violet-100 text-violet-700','pink'=>'bg-pink-100 text-pink-700',
            ];
            $badge = $colorMap[$tx['cat_color']] ?? $colorMap['slate'];
          ?>
          <tr class="border-t border-slate-50 hover:bg-slate-50/50">
            <td class="px-5 py-3 text-slate-500 whitespace-nowrap"><?= e(date('d/m/Y H:i', strtotime($tx['created_at']))) ?></td>
            <td class="px-5 py-3">
              <span class="inline-flex items-center gap-1 text-xs font-semibold <?= $isIn ? 'text-emerald-700' : 'text-rose-700' ?>">
                <i data-lucide="<?= $isIn ? 'arrow-down-circle' : 'arrow-up-circle' ?>" class="w-3.5 h-3.5"></i>
                <?= $isIn ? 'Encaissement' : 'Décaissement' ?>
              </span>
            </td>
            <td class="px-5 py-3">
              <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?= $badge ?>"><?= e($tx['cat_label']) ?></span>
            </td>
            <td class="px-5 py-3 text-slate-600"><?= e($tx['agency_name']) ?></td>
            <td class="px-5 py-3 text-right font-bold whitespace-nowrap <?= $isIn ? 'text-emerald-700' : 'text-rose-700' ?>">
              <?= $isIn ? '+' : '-' ?><?= number_format((int)$tx['amount_fcfa'], 0, ',', ' ') ?>
            </td>
            <td class="px-5 py-3 text-slate-500 text-xs"><?= e($tx['first_name'] . ' ' . substr($tx['last_name'], 0, 1) . '.') ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (!$recentTx): ?>
          <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Aucune transaction enregistrée.</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Liens rapides -->
  <div class="flex gap-3 flex-wrap">
    <a href="<?= e(url('finance/treasury/closures')) ?>"
       class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
      <i data-lucide="history" class="w-4 h-4 text-slate-400"></i> Historique clôtures
    </a>
    <a href="<?= e(url('finance/treasury/transactions')) ?>"
       class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
      <i data-lucide="search" class="w-4 h-4 text-slate-400"></i> Rechercher une transaction
    </a>
  </div>
</div>
<?php $view->end() ?>
