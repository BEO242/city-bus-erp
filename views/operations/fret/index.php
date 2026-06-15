<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$f = $filters;
$statusMap = [
  'enregistre' => ['label' => 'Enregistré',  'bg' => 'bg-amber-100 text-amber-700',   'dot' => 'bg-amber-400'],
  'charge'     => ['label' => 'Chargé',      'bg' => 'bg-blue-100 text-blue-700',     'dot' => 'bg-blue-400'],
  'en_transit' => ['label' => 'En transit',   'bg' => 'bg-indigo-100 text-indigo-700', 'dot' => 'bg-indigo-400'],
  'arrive'     => ['label' => 'Arrivé',       'bg' => 'bg-emerald-50 text-emerald-700','dot' => 'bg-emerald-400'],
  'retire'     => ['label' => 'Retiré',       'bg' => 'bg-slate-100 text-slate-600',   'dot' => 'bg-slate-400'],
  'annule'     => ['label' => 'Annulé',       'bg' => 'bg-rose-100 text-rose-700',     'dot' => 'bg-rose-400'],
];
?>
<?php $view->start('content') ?>
<div class="space-y-5">

  <!-- Page header -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 inline-flex items-center gap-2">
        <i data-lucide="package" class="w-6 h-6 text-cb-primary"></i>
        Fret &amp; Bagages
      </h1>
      <p class="text-slate-500 text-sm"><?= number_format((int)$total, 0, ',', ' ') ?> élément(s) enregistré(s)</p>
    </div>
    <?php if (can('fret.create')): ?>
    <div class="flex gap-2">
      <a href="<?= e(url('operations/fret/create?mode=baggage')) ?>" class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 font-medium inline-flex items-center gap-2 text-sm">
        <i data-lucide="plus" class="w-4 h-4"></i> Enregistrer un bagage
      </a>
      <a href="<?= e(url('operations/fret/create?mode=colis')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary inline-flex items-center gap-2 text-sm">
        <i data-lucide="plus" class="w-4 h-4"></i> Enregistrer un colis
      </a>
    </div>
    <?php endif ?>
  </div>

  <!-- Status summary bar -->
  <div class="flex flex-wrap gap-2">
    <?php foreach ($statusMap as $sKey => $sMeta): ?>
      <?php $cnt = $statusCounts[$sKey] ?? 0; ?>
      <a href="?<?= e(http_build_query(array_merge($_GET, ['status' => $sKey, 'page' => 1]))) ?>"
         class="px-3 py-1.5 rounded-xl text-xs font-medium inline-flex items-center gap-1.5 transition
                <?= ($f['status'] ?? '') === $sKey ? $sMeta['bg'] . ' ring-2 ring-offset-1 ring-slate-300' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        <span class="w-2 h-2 rounded-full <?= e($sMeta['dot']) ?>"></span>
        <?= e($sMeta['label']) ?>
        <span class="ml-0.5 font-bold"><?= (int)$cnt ?></span>
      </a>
    <?php endforeach ?>
    <?php if (!empty($f['status'])): ?>
      <a href="?<?= e(http_build_query(array_diff_key($_GET, ['status' => '', 'page' => '']))) ?>" class="px-3 py-1.5 rounded-xl text-xs text-slate-500 hover:text-slate-700 inline-flex items-center gap-1">
        <i data-lucide="x" class="w-3 h-3"></i> Tout afficher
      </a>
    <?php endif ?>
  </div>

  <!-- Filters bar -->
  <details class="group bg-white rounded-2xl border border-slate-100" <?= (!empty($f['q']) || !empty($f['item_type']) || !empty($f['category_slug']) || !empty($f['date_from']) || !empty($f['date_to'])) ? 'open' : '' ?>>
    <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-slate-600 flex items-center gap-2">
      <i data-lucide="filter" class="w-4 h-4"></i> Filtres avancés
      <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition group-open:rotate-180"></i>
    </summary>
    <form method="get" class="px-4 pb-4 grid grid-cols-2 md:grid-cols-7 gap-2">
      <input name="q" value="<?= e($f['q'] ?? '') ?>" placeholder="Code, nom, téléphone…" class="md:col-span-2 px-3 py-2 rounded-xl border border-slate-200 text-sm">
      <select name="item_type" class="px-3 py-2 rounded-xl border border-slate-200 text-sm">
        <option value="">Tous types</option>
        <option value="baggage" <?= ($f['item_type'] ?? '') === 'baggage' ? 'selected' : '' ?>>Bagage</option>
        <option value="colis" <?= ($f['item_type'] ?? '') === 'colis' ? 'selected' : '' ?>>Colis</option>
      </select>
      <select name="category_slug" class="px-3 py-2 rounded-xl border border-slate-200 text-sm">
        <option value="">Toutes catégories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= e($cat['slug']) ?>" <?= ($f['category_slug'] ?? '') === $cat['slug'] ? 'selected' : '' ?>><?= e($cat['label']) ?></option>
        <?php endforeach ?>
      </select>
      <input type="date" name="date_from" value="<?= e($f['date_from'] ?? '') ?>" class="px-3 py-2 rounded-xl border border-slate-200 text-sm">
      <input type="date" name="date_to" value="<?= e($f['date_to'] ?? '') ?>" class="px-3 py-2 rounded-xl border border-slate-200 text-sm">
      <div class="flex gap-2">
        <button class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium">Filtrer</button>
        <a href="<?= e(url('operations/fret')) ?>" class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-500 hover:text-slate-700 inline-flex items-center">Réinitialiser</a>
      </div>
    </form>
  </details>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <?php if ($rows): ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
          <tr>
            <th class="px-5 py-3 text-left">Code</th>
            <th class="px-5 py-3 text-left">Type</th>
            <th class="px-5 py-3 text-left">Catégorie</th>
            <th class="px-5 py-3 text-left">Expéditeur</th>
            <th class="px-5 py-3 text-right">Poids</th>
            <th class="px-5 py-3 text-right">Prix</th>
            <th class="px-5 py-3 text-center">Statut</th>
            <th class="px-5 py-3 text-right">Date</th>
            <th class="px-5 py-3 text-left">Voyage</th>
            <th class="px-5 py-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($rows as $row): ?>
          <tr class="hover:bg-cb-bg/40">
            <td class="px-5 py-3">
              <div class="inline-flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full <?= $row['item_type'] === 'baggage' ? 'bg-amber-400' : 'bg-blue-400' ?>"></span>
                <span class="font-mono text-cb-primary"><?= e($row['tracking_code']) ?></span>
              </div>
            </td>
            <td class="px-5 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs <?= $row['item_type'] === 'baggage' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' ?>">
                <?= $row['item_type'] === 'baggage' ? 'Bagage' : 'Colis' ?>
              </span>
            </td>
            <td class="px-5 py-3">
              <div class="inline-flex items-center gap-1.5 text-sm">
                <span class="w-2 h-2 rounded-full" style="background-color: <?= e($row['category_color'] ?? '#94a3b8') ?>"></span>
                <?= e($row['category_label'] ?? '—') ?>
              </div>
            </td>
            <td class="px-5 py-3">
              <div class="text-sm"><?= e($row['sender_name']) ?></div>
              <div class="text-xs text-slate-400"><?= e($row['sender_phone']) ?></div>
            </td>
            <td class="px-5 py-3 text-right">
              <?= number_format((float)$row['weight_kg'], 1, ',', ' ') ?> kg
              <span class="text-xs text-slate-400">(<?= (int)$row['pieces_count'] ?>)</span>
            </td>
            <td class="px-5 py-3 text-right font-semibold">
              <?php if ($row['is_franchise']): ?>
                <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-700">Franchise</span>
              <?php else: ?>
                <?= number_format((int)$row['total_price_fcfa'], 0, ',', ' ') ?> FCFA
              <?php endif ?>
            </td>
            <td class="px-5 py-3 text-center">
              <?php $st = $statusMap[$row['status']] ?? ['label' => $row['status'], 'bg' => 'bg-slate-100 text-slate-600']; ?>
              <span class="px-2 py-0.5 rounded-full text-xs <?= e($st['bg']) ?>">
                <?= e($st['label']) ?>
              </span>
            </td>
            <td class="px-5 py-3 text-right text-xs text-slate-500">
              <?= e(date('d/m/Y H:i', strtotime((string)$row['created_at']))) ?>
            </td>
            <td class="px-5 py-3 text-sm">
              <?php if (!empty($row['line_code'])): ?>
                <span class="text-slate-700"><?= e($row['line_code']) ?></span>
                <span class="text-xs text-slate-400"><?= e(date('d/m', strtotime((string)$row['trip_date']))) ?></span>
              <?php else: ?>
                <span class="text-slate-300">&mdash;</span>
              <?php endif ?>
            </td>
            <td class="px-5 py-3 text-center">
              <a href="<?= e(url('operations/fret/' . $row['id'])) ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-cb-primary" title="Voir">
                <i data-lucide="eye" class="w-4 h-4"></i>
              </a>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($lastPage > 1): ?>
    <div class="p-4 flex justify-between items-center border-t border-slate-100">
      <span class="text-sm text-slate-500"><?= number_format((int)$total, 0, ',', ' ') ?> élément(s) · page <?= (int)$page ?> / <?= (int)$lastPage ?></span>
      <div class="flex gap-2">
        <?php if ($page > 1): ?>
          <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-sm">Précédent</a>
        <?php endif ?>
        <?php if ($page < $lastPage): ?>
          <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-sm">Suivant</a>
        <?php endif ?>
      </div>
    </div>
    <?php endif ?>

    <?php else: ?>
    <!-- Empty state -->
    <div class="px-5 py-16 text-center">
      <i data-lucide="package" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
      <p class="text-slate-500 mb-4">Aucun élément enregistré</p>
      <?php if (can('fret.create')): ?>
        <a href="<?= e(url('operations/fret/create?mode=colis')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary inline-flex items-center gap-2 text-sm">
          <i data-lucide="plus" class="w-4 h-4"></i> Enregistrer un envoi
        </a>
      <?php endif ?>
    </div>
    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
