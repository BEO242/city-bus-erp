<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$f = $filters ?? [];

$typeMeta = [
    'ticket'  => ['label' => 'Billet',  'cls' => 'bg-blue-50 text-blue-700 border-blue-200',   'icon' => 'ticket'],
    'fret'    => ['label' => 'Fret',    'cls' => 'bg-purple-50 text-purple-700 border-purple-200', 'icon' => 'package'],
    'baggage' => ['label' => 'Bagage',  'cls' => 'bg-amber-50 text-amber-700 border-amber-200','icon' => 'luggage'],
];
$statusMeta = [
    'en_attente' => ['label' => 'En attente', 'cls' => 'bg-amber-50 text-amber-700'],
    'approuve'   => ['label' => 'Approuvé',   'cls' => 'bg-blue-50 text-blue-700'],
    'execute'    => ['label' => 'Exécuté',    'cls' => 'bg-emerald-50 text-emerald-700'],
    'rejete'     => ['label' => 'Rejeté',     'cls' => 'bg-red-50 text-red-700'],
];
?>
<?php $view->start('content') ?>

<div class="space-y-5 pb-8">

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Remboursements</h1>
      <p class="text-sm text-slate-500 mt-1"><?= $total ?> remboursement(s) au total</p>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center gap-3 mb-2">
        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center">
          <i data-lucide="undo-2" class="w-4 h-4 text-blue-600"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Aujourd'hui</span>
      </div>
      <div class="text-2xl font-bold text-slate-900"><?= $kpis['today_count'] ?? 0 ?></div>
      <div class="text-xs text-slate-400 mt-0.5"><?= number_format((int)($kpis['today_amount'] ?? 0), 0, ',', ' ') ?> FCFA</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center gap-3 mb-2">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center">
          <i data-lucide="calendar" class="w-4 h-4 text-indigo-600"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Ce mois</span>
      </div>
      <div class="text-2xl font-bold text-slate-900"><?= $kpis['month_count'] ?? 0 ?></div>
      <div class="text-xs text-slate-400 mt-0.5"><?= number_format((int)($kpis['month_amount'] ?? 0), 0, ',', ' ') ?> FCFA</div>
    </div>
    <?php foreach ($totals as $t): $tm = $typeMeta[$t['refund_type']] ?? ['label' => $t['refund_type'], 'cls' => 'bg-slate-50 text-slate-700', 'icon' => 'circle']; ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center gap-3 mb-2">
        <div class="w-9 h-9 rounded-xl <?= $tm['cls'] ?> flex items-center justify-center">
          <i data-lucide="<?= $tm['icon'] ?>" class="w-4 h-4"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide"><?= e($tm['label']) ?></span>
      </div>
      <div class="text-2xl font-bold text-slate-900"><?= (int)$t['count'] ?></div>
      <div class="text-xs text-slate-400 mt-0.5"><?= number_format((int)$t['total_amount'], 0, ',', ' ') ?> FCFA</div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filters -->
  <form method="GET" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
      <select name="type" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Tous les types</option>
        <option value="ticket"  <?= ($f['refund_type'] ?? '') === 'ticket'  ? 'selected' : '' ?>>Billets</option>
        <option value="fret"    <?= ($f['refund_type'] ?? '') === 'fret'    ? 'selected' : '' ?>>Fret</option>
        <option value="baggage" <?= ($f['refund_type'] ?? '') === 'baggage' ? 'selected' : '' ?>>Bagages</option>
      </select>
      <select name="status" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Tous les statuts</option>
        <?php foreach ($statusMeta as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($f['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($v['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="agency_id" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Toutes les agences</option>
        <?php foreach ($agencies as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= ((int)($f['agency_id'] ?? 0)) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_from" value="<?= e($f['date_from'] ?? '') ?>" placeholder="Du"
             class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
      <input type="date" name="date_to" value="<?= e($f['date_to'] ?? '') ?>" placeholder="Au"
             class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
      <div class="flex gap-2">
        <button type="submit"
                class="flex-1 px-4 py-2.5 text-sm rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-dark transition flex items-center justify-center gap-1.5">
          <i data-lucide="filter" class="w-3.5 h-3.5"></i> Filtrer
        </button>
        <a href="<?= url('finance/refunds') ?>"
           class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition flex items-center">
          <i data-lucide="x" class="w-3.5 h-3.5"></i>
        </a>
      </div>
    </div>
  </form>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <?php if (empty($rows)): ?>
      <div class="py-14 text-center">
        <i data-lucide="undo-2" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
        <p class="text-sm text-slate-500">Aucun remboursement trouvé.</p>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Date</th>
              <th class="px-4 py-3 text-left">Type</th>
              <th class="px-4 py-3 text-left">Réf.</th>
              <th class="px-4 py-3 text-right">Montant initial</th>
              <th class="px-4 py-3 text-right">Remboursé</th>
              <th class="px-4 py-3 text-center">%</th>
              <th class="px-4 py-3 text-left">Motif</th>
              <th class="px-4 py-3 text-left">Par</th>
              <th class="px-4 py-3 text-left">Agence</th>
              <th class="px-4 py-3 text-center">Statut</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <?php foreach ($rows as $r):
              $tm = $typeMeta[$r['refund_type']] ?? ['label' => $r['refund_type'], 'cls' => 'bg-slate-50 text-slate-600 border-slate-200', 'icon' => 'circle'];
              $sm = $statusMeta[$r['status']] ?? ['label' => $r['status'], 'cls' => 'bg-slate-50 text-slate-600'];
            ?>
            <tr class="hover:bg-cb-bg/30 transition">
              <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">
                <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border <?= $tm['cls'] ?>">
                  <i data-lucide="<?= $tm['icon'] ?>" class="w-3 h-3"></i>
                  <?= e($tm['label']) ?>
                </span>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-cb-primary font-bold">#<?= (int)$r['reference_id'] ?></td>
              <td class="px-4 py-3 text-right text-slate-600"><?= number_format((int)$r['original_amount_fcfa'], 0, ',', ' ') ?></td>
              <td class="px-4 py-3 text-right font-bold text-red-600"><?= number_format((int)$r['refund_amount_fcfa'], 0, ',', ' ') ?></td>
              <td class="px-4 py-3 text-center text-xs text-slate-500"><?= number_format((float)$r['refund_percent'], 0) ?>%</td>
              <td class="px-4 py-3 text-slate-600 text-xs max-w-[200px] truncate" title="<?= e($r['reason']) ?>"><?= e($r['reason']) ?></td>
              <td class="px-4 py-3 text-slate-500 text-xs"><?= e($r['refunded_by_name'] ?? '—') ?></td>
              <td class="px-4 py-3 text-slate-500 text-xs"><?= e($r['agency_name'] ?? '—') ?></td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $sm['cls'] ?>"><?= e($sm['label']) ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if (($lastPage ?? 1) > 1): ?>
  <div class="flex items-center justify-between bg-white rounded-2xl border border-slate-100 shadow-soft px-5 py-3">
    <p class="text-sm text-slate-500">Page <?= $page ?> / <?= $lastPage ?> (<?= $total ?> résultats)</p>
    <nav class="flex items-center gap-1">
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($f, ['page' => $page - 1])) ?>"
           class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
          <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </a>
      <?php endif; ?>
      <?php if ($page < $lastPage): ?>
        <a href="?<?= http_build_query(array_merge($f, ['page' => $page + 1])) ?>"
           class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
          <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
      <?php endif; ?>
    </nav>
  </div>
  <?php endif; ?>

</div>

<?php $view->end() ?>
