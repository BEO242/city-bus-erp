<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$totalEnc  = (int)($kpis['total_enc']  ?? 0);
$totalDec  = (int)($kpis['total_dec']  ?? 0);
$soldeNet  = $totalEnc - $totalDec;
$todayNet  = (int)($kpis['today_net']  ?? 0);

$sourceLabels = [
    'tresorerie' => ['label' => 'Trésorerie', 'cls' => 'bg-indigo-100 text-indigo-700'],
    'vente'      => ['label' => 'Vente',       'cls' => 'bg-emerald-100 text-emerald-700'],
    'colis'      => ['label' => 'Colis',        'cls' => 'bg-amber-100 text-amber-700'],
    'autre'      => ['label' => 'Autre',        'cls' => 'bg-slate-100 text-slate-600'],
];
$statusMap = [
    'pending'   => ['label' => 'En attente',  'cls' => 'bg-amber-100 text-amber-700',   'dot' => 'bg-amber-500'],
    'confirmed' => ['label' => 'Confirmée',   'cls' => 'bg-emerald-100 text-emerald-700','dot' => 'bg-emerald-500'],
    'rejected'  => ['label' => 'Rejetée',     'cls' => 'bg-rose-100 text-rose-700',     'dot' => 'bg-rose-500'],
];
?>

<div class="space-y-5">

  <!-- ── En-tête + boutons d'action ── -->
  <div class="flex items-start justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-black text-slate-900">Transactions</h1>
      <p class="text-slate-400 text-sm mt-0.5"><?= number_format($total, 0, ',', ' ') ?> transaction(s) trouvée(s)</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <?php if (can('finance.treasury.manage')): ?>
      <a href="<?= e(url('finance/treasury/transaction?type=encaissement')) ?>"
         class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition shadow-sm">
        <i data-lucide="circle-plus" class="w-4 h-4"></i> Encaissement
      </a>
      <a href="<?= e(url('finance/treasury/transaction?type=decaissement')) ?>"
         class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-rose-600 text-white text-sm font-bold hover:bg-rose-700 transition shadow-sm">
        <i data-lucide="circle-minus" class="w-4 h-4"></i> Décaissement
      </a>
      <?php endif ?>
      <?php if (can('finance.treasury.validate') && $pendingCount > 0): ?>
      <a href="?status=pending"
         class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition shadow-sm">
        <i data-lucide="clock" class="w-4 h-4"></i>
        Confirmations
        <span class="bg-white text-amber-600 text-xs font-black px-2 py-0.5 rounded-full leading-none"><?= $pendingCount ?></span>
      </a>
      <?php endif ?>
    </div>
  </div>

  <!-- ── KPI cards ── -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Total Entrées -->
    <div class="rounded-2xl bg-emerald-600 p-5 text-white relative overflow-hidden">
      <div class="absolute right-4 top-4 opacity-20">
        <i data-lucide="arrow-down-circle" class="w-12 h-12"></i>
      </div>
      <p class="text-emerald-200 text-xs font-semibold uppercase tracking-wider mb-2">Total Entrées</p>
      <p class="text-3xl font-black leading-none"><?= number_format($totalEnc, 0, ',', ' ') ?></p>
      <p class="text-emerald-300 text-xs mt-1 font-mono">FCFA</p>
    </div>
    <!-- Total Sorties -->
    <div class="rounded-2xl bg-rose-600 p-5 text-white relative overflow-hidden">
      <div class="absolute right-4 top-4 opacity-20">
        <i data-lucide="arrow-up-circle" class="w-12 h-12"></i>
      </div>
      <p class="text-rose-200 text-xs font-semibold uppercase tracking-wider mb-2">Total Sorties</p>
      <p class="text-3xl font-black leading-none"><?= number_format($totalDec, 0, ',', ' ') ?></p>
      <p class="text-rose-300 text-xs mt-1 font-mono">FCFA</p>
    </div>
    <!-- Solde Net -->
    <div class="rounded-2xl bg-blue-600 p-5 text-white relative overflow-hidden">
      <div class="absolute right-4 top-4 opacity-20">
        <i data-lucide="wallet" class="w-12 h-12"></i>
      </div>
      <p class="text-blue-200 text-xs font-semibold uppercase tracking-wider mb-2">Solde Net</p>
      <p class="text-3xl font-black leading-none"><?= number_format($soldeNet, 0, ',', ' ') ?></p>
      <p class="text-blue-300 text-xs mt-1 font-mono">FCFA</p>
    </div>
    <!-- Aujourd'hui -->
    <div class="rounded-2xl bg-cyan-500 p-5 text-white relative overflow-hidden">
      <div class="absolute right-4 top-4 opacity-20">
        <i data-lucide="calendar-check" class="w-12 h-12"></i>
      </div>
      <p class="text-cyan-100 text-xs font-semibold uppercase tracking-wider mb-2">Aujourd'hui</p>
      <p class="text-3xl font-black leading-none"><?= number_format(abs($todayNet), 0, ',', ' ') ?></p>
      <p class="text-cyan-200 text-xs mt-1 font-mono">FCFA <?= $todayNet < 0 ? '(net sortie)' : '(net entrée)' ?></p>
    </div>
  </div>

  <!-- ── Filtres ── -->
  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-5 space-y-4">
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
      <!-- Recherche -->
      <div class="lg:col-span-2">
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Recherche</label>
        <div class="relative">
          <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400"></i>
          <input name="q" value="<?= e($q) ?>" placeholder="Libellé, description, référence..."
                 class="w-full pl-8 pr-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        </div>
      </div>
      <!-- Type -->
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Type</label>
        <select name="type" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
          <option value="">Tous</option>
          <option value="encaissement" <?= $type === 'encaissement' ? 'selected' : '' ?>>Encaissement</option>
          <option value="decaissement" <?= $type === 'decaissement' ? 'selected' : '' ?>>Décaissement</option>
        </select>
      </div>
      <!-- Statut -->
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Statut</label>
        <select name="status" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
          <option value="all_except_rejected" <?= $status === 'all_except_rejected' ? 'selected' : '' ?>>Tous (sauf rejetés)</option>
          <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>En attente</option>
          <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmées</option>
          <option value="rejected"  <?= $status === 'rejected'  ? 'selected' : '' ?>>Rejetées</option>
        </select>
      </div>
      <!-- Source -->
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Source</label>
        <select name="source" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
          <option value="">Toutes</option>
          <option value="tresorerie" <?= $source === 'tresorerie' ? 'selected' : '' ?>>Trésorerie</option>
          <option value="vente"      <?= $source === 'vente'      ? 'selected' : '' ?>>Vente</option>
          <option value="colis"      <?= $source === 'colis'      ? 'selected' : '' ?>>Colis</option>
          <option value="autre"      <?= $source === 'autre'      ? 'selected' : '' ?>>Autre</option>
        </select>
      </div>
      <!-- Catégorie -->
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Catégorie</label>
        <?php
        // Grouper par type (encaissement / décaissement)
        $catByType = ['encaissement' => [], 'decaissement' => []];
        foreach ($categories as $c) {
            $catByType[$c['type']][] = $c;
        }
        ?>
        <select name="category" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
          <option value="">Toutes</option>
          <?php if (!empty($catByType['encaissement'])): ?>
          <optgroup label="⬇ Encaissements">
            <?php foreach ($catByType['encaissement'] as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['label']) ?></option>
            <?php endforeach ?>
          </optgroup>
          <?php endif ?>
          <?php if (!empty($catByType['decaissement'])): ?>
          <optgroup label="⬆ Décaissements">
            <?php foreach ($catByType['decaissement'] as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['label']) ?></option>
            <?php endforeach ?>
          </optgroup>
          <?php endif ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <!-- Période -->
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Période</label>
        <div class="flex items-center gap-2">
          <input type="date" name="date_from" value="<?= e($dateFrom) ?>"
                 class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
          <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
          <input type="date" name="date_to" value="<?= e($dateTo) ?>"
                 class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
      <!-- Plage horaire -->
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Plage horaire</label>
        <div class="flex items-center gap-2">
          <input type="time" name="time_from" value="<?= e($timeFrom) ?>"
                 class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
          <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
          <input type="time" name="time_to" value="<?= e($timeTo) ?>"
                 class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2 pt-1">
      <button type="submit"
              class="px-5 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-bold hover:bg-cb-dark transition inline-flex items-center gap-2">
        <i data-lucide="search" class="w-4 h-4"></i> Filtrer
      </button>
      <a href="<?= e(url('finance/treasury/transactions')) ?>"
         class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition inline-flex items-center gap-2">
        <i data-lucide="x" class="w-4 h-4"></i> Réinitialiser
      </a>
    </div>
  </form>

  <!-- ── Tableau ── -->
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">

    <!-- Barre de titre + exports -->
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-2">
        <i data-lucide="arrow-left-right" class="w-4 h-4 text-cb-primary"></i>
        <span class="font-bold text-slate-700">Transactions</span>
        <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-full"><?= number_format($total, 0, ',', ' ') ?></span>
      </div>
      <div class="flex items-center gap-2">
        <a href="<?= e(url('finance/treasury/categories')) ?>"
           class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50 transition inline-flex items-center gap-1.5">
          <i data-lucide="tag" class="w-3.5 h-3.5"></i> Catégories
        </a>
        <a href="<?= e(url('finance/treasury/report')) ?>"
           class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50 transition inline-flex items-center gap-1.5">
          <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i> Rapport
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'pdf'])) ?>"
           class="px-3 py-1.5 rounded-lg border border-rose-200 text-rose-600 text-xs font-semibold hover:bg-rose-50 transition inline-flex items-center gap-1.5">
          <i data-lucide="file-text" class="w-3.5 h-3.5"></i> PDF
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>"
           class="px-3 py-1.5 rounded-lg border border-emerald-200 text-emerald-600 text-xs font-semibold hover:bg-emerald-50 transition inline-flex items-center gap-1.5">
          <i data-lucide="table-2" class="w-3.5 h-3.5"></i> Excel
        </a>
      </div>
    </div>

    <?php if (!$transactions): ?>
      <div class="p-12 text-center text-slate-400">
        <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
        <p class="font-semibold">Aucune transaction trouvée</p>
        <p class="text-sm mt-1">Modifiez les filtres ou créez une nouvelle transaction.</p>
      </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-[10px] font-bold text-slate-500 uppercase tracking-wider bg-slate-50 border-b border-slate-100">
            <th class="px-5 py-3">Date opération</th>
            <th class="px-5 py-3">Type</th>
            <th class="px-5 py-3">Libellé / Description</th>
            <th class="px-5 py-3 text-right">Montant</th>
            <th class="px-5 py-3 text-center">Statut</th>
            <th class="px-5 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($transactions as $tx):
            $isIn   = $tx['type'] === 'encaissement';
            $txStat = $statusMap[$tx['tx_status'] ?? 'pending'] ?? $statusMap['pending'];
            $src    = $tx['cat_source'] ?? 'tresorerie';
            $srcBadge = $sourceLabels[$src] ?? $sourceLabels['autre'];
          ?>
          <tr class="hover:bg-slate-50/60 transition group">

            <!-- Date -->
            <td class="px-5 py-3.5 whitespace-nowrap">
              <p class="text-sm font-semibold text-slate-800"><?= e(date('d/m/Y', strtotime($tx['created_at']))) ?></p>
              <p class="text-xs text-slate-400"><?= e(date('H:i', strtotime($tx['created_at']))) ?></p>
              <?php if (!empty($tx['confirmed_at']) && $tx['tx_status'] === 'confirmed'): ?>
              <p class="text-[10px] text-slate-300 mt-0.5">
                <i data-lucide="check" class="w-2.5 h-2.5 inline"></i> <?= e(date('d/m H:i', strtotime($tx['confirmed_at']))) ?>
              </p>
              <?php endif ?>
            </td>

            <!-- Type (2 badges) -->
            <td class="px-5 py-3.5">
              <div class="flex flex-col gap-1">
                <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-md w-fit
                             <?= $isIn ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                  <i data-lucide="<?= $isIn ? 'arrow-down' : 'arrow-up' ?>" class="w-3 h-3"></i>
                  <?= $isIn ? 'Entrée' : 'Sortie' ?>
                </span>
                <span class="inline-flex items-center text-[10px] font-semibold px-2 py-0.5 rounded-md w-fit <?= $srcBadge['cls'] ?>">
                  <?= $srcBadge['label'] ?>
                </span>
              </div>
            </td>

            <!-- Libellé / Description -->
            <td class="px-5 py-3.5 max-w-xs">
              <p class="font-semibold text-slate-800 truncate">
                <?= e($tx['description'] ?: $tx['cat_label']) ?>
              </p>
              <?php if ($tx['description'] && $tx['cat_label']): ?>
              <p class="text-xs text-slate-400 truncate mt-0.5"><?= e($tx['cat_label']) ?></p>
              <?php endif ?>
              <?php if (!empty($tx['reference'])): ?>
              <p class="text-[11px] text-cb-primary font-mono mt-0.5 hover:underline cursor-pointer truncate">
                Réf: <?= e($tx['reference']) ?>
              </p>
              <?php endif ?>
              <?php if (!empty($tx['rejection_reason']) && $tx['tx_status'] === 'rejected'): ?>
              <p class="text-[11px] text-rose-500 mt-0.5 truncate">↳ <?= e($tx['rejection_reason']) ?></p>
              <?php endif ?>
            </td>

            <!-- Montant -->
            <td class="px-5 py-3.5 text-right whitespace-nowrap">
              <span class="text-base font-black <?= $isIn ? 'text-emerald-700' : 'text-rose-700' ?>">
                <?= $isIn ? '+' : '-' ?><?= number_format((int)$tx['amount_fcfa'], 0, ',', ' ') ?> F
              </span>
            </td>

            <!-- Statut -->
            <td class="px-5 py-3.5 text-center">
              <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full <?= $txStat['cls'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $txStat['dot'] ?>
                             <?= ($tx['tx_status'] ?? 'pending') === 'pending' ? 'animate-pulse' : '' ?>"></span>
                <?= $txStat['label'] ?>
              </span>
            </td>

            <!-- Actions -->
            <td class="px-5 py-3.5 text-right">
              <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition">
                <?php if (can('finance.treasury.validate') && ($tx['tx_status'] ?? 'pending') === 'pending'): ?>
                <!-- Confirmer -->
                <form method="post" action="<?= e(url('finance/treasury/transaction/' . $tx['id'] . '/approve')) ?>">
                  <?= csrf_field() ?>
                  <button type="submit" title="Confirmer"
                          class="w-8 h-8 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-700 flex items-center justify-center transition">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
                <!-- Rejeter -->
                <form method="post" action="<?= e(url('finance/treasury/transaction/' . $tx['id'] . '/reject')) ?>">
                  <?= csrf_field() ?>
                  <button type="submit" title="Rejeter"
                          class="w-8 h-8 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 flex items-center justify-center transition">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
                <?php endif ?>
                <!-- Voir détail -->
                <a href="<?= e(url('finance/treasury/transactions?q=' . urlencode($tx['reference'] ?? ''))) ?>"
                   title="Voir"
                   class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition">
                  <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                </a>
                <!-- Note -->
                <?php if (can('finance.treasury.manage') && ($tx['tx_status'] ?? 'pending') === 'pending'): ?>
                <a href="<?= e(url('finance/treasury/transaction?edit=' . $tx['id'])) ?>"
                   title="Modifier"
                   class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition">
                  <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </a>
                <?php endif ?>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between flex-wrap gap-3">
      <p class="text-xs text-slate-400">
        Page <?= $page ?> sur <?= $pages ?> — <?= number_format($total, 0, ',', ' ') ?> résultat(s)
      </p>
      <div class="flex gap-1">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
           class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
          <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
        </a>
        <?php endif ?>
        <?php
        $start = max(1, $page - 2);
        $end   = min($pages, $page + 2);
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
           class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition
                  <?= $p === $page ? 'bg-cb-primary text-white shadow-sm' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
          <?= $p ?>
        </a>
        <?php endfor ?>
        <?php if ($page < $pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
           class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
          <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </a>
        <?php endif ?>
      </div>
    </div>
    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
