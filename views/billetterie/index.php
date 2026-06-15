<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$qp = array_filter([
    'q'          => $q          ?? '',
    'line_id'    => ($lineFilter ?? 0) > 0 ? (string)$lineFilter : '',
    'status'     => $statusFilter ?? '',
    'date_from'  => $dateFrom    ?? '',
    'date_until' => $dateUntil   ?? '',
], fn($v) => $v !== '' && $v !== null);

$statusBadge = fn(string $s) => match ($s) {
    'emis'   => 'bg-cb-bg text-cb-primary border-cb-primary/20',
    'valide' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'arrive' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
    'annule' => 'bg-rose-50 text-rose-600 border-rose-200',
    default  => 'bg-slate-100 text-slate-600 border-slate-200',
};
$statusLabel = fn(string $s) => match ($s) {
    'emis'   => 'Émis',
    'valide' => 'Validé',
    'arrive' => 'Arrivé',
    'annule' => 'Annulé',
    default  => $s,
};
$typeBadge = fn(string $t) => match ($t) {
    'passage_arret', 'passager' => 'bg-cb-bg text-cb-primary border-cb-primary/20',
    'passage_final'             => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    'arret_route'               => 'bg-teal-50 text-teal-700 border-teal-200',
    default                     => 'bg-slate-100 text-slate-500 border-slate-200',
};
$typeLabel = fn(string $t) => match ($t) {
    'passage_arret' => 'Arrêt',
    'passage_final' => 'Final',
    'arret_route'   => 'Route',
    'passager'      => 'Passager',
    default         => ucfirst(str_replace('_', ' ', $t)),
};

// KPI rapides à partir des billets courants (full count via controller)
$kpiEmis   = $stats['emis']   ?? 0;
$kpiValide = $stats['valide'] ?? 0;
$kpiAnnule = $stats['annule'] ?? 0;
$kpiRevenu = $stats['revenu'] ?? 0;
?>
<?php $view->start('content') ?>

<div class="space-y-4">

  <!-- En-tête ───────────────────────────────────────────────────────── -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="ticket" class="w-6 h-6 text-cb-primary"></i> Billets passagers
      </h1>
      <p class="text-slate-500 text-sm"><?= (int)$total ?> billet(s) au total</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <a href="<?= e(url('billetterie-bagages')) ?>"
         class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition">
        <i data-lucide="luggage" class="w-4 h-4"></i> Billets bagages
      </a>
      <a href="<?= e(url('billetterie/select-trip')) ?>"
         class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-medium inline-flex items-center gap-2 hover:bg-cb-dark transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Vendre un billet
      </a>
    </div>
  </div>

  <!-- KPI ───────────────────────────────────────────────────────────── -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-cb-bg rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="ticket" class="w-5 h-5 text-cb-primary"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500 font-medium">Émis</p>
        <p class="text-2xl font-bold text-cb-primary"><?= (int)$kpiEmis ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-emerald-50 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500 font-medium">Validés</p>
        <p class="text-2xl font-bold text-emerald-600"><?= (int)$kpiValide ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-rose-50 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="x-circle" class="w-5 h-5 text-rose-500"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500 font-medium">Annulés</p>
        <p class="text-2xl font-bold text-rose-500"><?= (int)$kpiAnnule ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-11 h-11 bg-emerald-50 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="banknote" class="w-5 h-5 text-emerald-600"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500 font-medium">Recettes</p>
        <p class="text-xl font-bold text-emerald-600"><?= e(fcfa((int)$kpiRevenu)) ?></p>
      </div>
    </div>
  </div>

  <!-- Filtres ───────────────────────────────────────────────────────── -->
  <form method="get" action="<?= e(url('billetterie')) ?>"
        class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
      <div class="lg:col-span-2">
        <label class="block text-xs font-medium text-slate-500 mb-1">Recherche</label>
        <div class="relative">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input name="q" value="<?= e($q) ?>" placeholder="Numéro, passager, téléphone…"
                 class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Ligne</label>
        <select name="line_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none">
          <option value="">Toutes</option>
          <?php foreach ($lines as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= ($lineFilter ?? 0) == $l['id'] ? 'selected' : '' ?>>
              <?= e($l['code']) ?> — <?= e($l['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Statut</label>
        <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none">
          <option value="">Tous</option>
          <?php foreach (['emis'=>'Émis','valide'=>'Validé','arrive'=>'Arrivé','annule'=>'Annulé'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($statusFilter ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="flex items-end gap-2">
        <button class="flex-1 px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
          Filtrer
        </button>
        <?php if (!empty($qp)): ?>
          <a href="<?= e(url('billetterie')) ?>"
             class="px-3 py-2 rounded-xl border border-slate-200 text-slate-500 text-sm hover:bg-slate-50">
            <i data-lucide="x" class="w-4 h-4"></i>
          </a>
        <?php endif ?>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-3 mt-3">
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Voyage du</label>
        <input type="date" name="date_from" value="<?= e($dateFrom ?? '') ?>"
               class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">au</label>
        <input type="date" name="date_until" value="<?= e($dateUntil ?? '') ?>"
               class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none">
      </div>
    </div>
  </form>

  <!-- Tableau ───────────────────────────────────────────────────────── -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
          <tr>
            <th class="px-4 py-3 text-left">N° billet</th>
            <th class="px-4 py-3 text-left">Passager</th>
            <th class="px-4 py-3 text-left">Voyage</th>
            <th class="px-4 py-3 text-left">Périmètre</th>
            <th class="px-4 py-3 text-center">Siège</th>
            <th class="px-4 py-3 text-right">Prix</th>
            <th class="px-4 py-3 text-center">Statut</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($tickets as $t): ?>
            <tr class="hover:bg-cb-bg/40 transition">
              <td class="px-4 py-3">
                <a href="<?= e(url('billetterie/' . $t['id'])) ?>"
                   class="font-mono text-cb-primary hover:underline font-semibold">
                  <?= e($t['ticket_number']) ?>
                </a>
                <div class="text-xs text-slate-400">
                  <?= e(date('d/m/Y H:i', strtotime($t['sold_at']))) ?>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="font-medium text-slate-800"><?= e($t['passenger_name']) ?></div>
                <?php if (!empty($t['passenger_phone'])): ?>
                  <div class="text-xs text-slate-400"><?= e($t['passenger_phone']) ?></div>
                <?php endif ?>
              </td>
              <td class="px-4 py-3">
                <div class="font-mono text-xs text-cb-primary"><?= e($t['line_code']) ?></div>
                <div class="text-xs text-slate-500"><?= e($t['line_name']) ?></div>
                <div class="text-xs text-slate-400">
                  <?= e(date('d/m', strtotime($t['trip_date']))) ?>
                  <?= e(date('H:i', strtotime($t['departure_scheduled']))) ?>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs border <?= $typeBadge($t['ticket_type'] ?? '') ?>">
                  <?= $typeLabel($t['ticket_type'] ?? '') ?>
                </span>
                <?php if (!empty($t['passenger_category'])): ?>
                <div class="text-xs text-slate-400 mt-0.5">
                  <?= e(ucfirst($t['passenger_category'])) ?>
                  <?php if (!empty($t['travel_class'])): ?> · <?= e($t['travel_class']) ?><?php endif ?>
                </div>
                <?php endif ?>
              </td>
              <td class="px-4 py-3 text-center">
                <?php if (!empty($t['seat_number'])): ?>
                  <span class="font-mono font-bold text-slate-800"><?= (int)$t['seat_number'] ?></span>
                <?php else: ?>
                  <span class="text-slate-300">—</span>
                <?php endif ?>
              </td>
              <td class="px-4 py-3 text-right font-bold text-slate-900">
                <?= e(fcfa((int)$t['price_fcfa'])) ?>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs border <?= $statusBadge($t['status']) ?>">
                  <?= e($statusLabel($t['status'])) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex items-center gap-1">
                  <a href="<?= e(url('billetterie/' . $t['id'])) ?>"
                     title="Voir"
                     class="p-1.5 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                  </a>
                  <a href="<?= e(url('billetterie/' . $t['id'] . '/pdf')) ?>" target="_blank"
                     title="PDF"
                     class="p-1.5 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach ?>
          <?php if (!$tickets): ?>
            <tr>
              <td colspan="8" class="px-4 py-12 text-center">
                <i data-lucide="ticket-x" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                <p class="text-slate-400">Aucun billet trouvé</p>
              </td>
            </tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>

    <!-- Règle de fin de liste -->
    <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-3 text-xs text-slate-400">
      <div class="flex-1 h-px bg-slate-100"></div>
      <span><?= count($tickets) ?> enregistrement(s) affiché(s)<?= (($dateFrom ?? '') === '' && ($dateUntil ?? '') === '') ? ' · 30 derniers' : '' ?></span>
      <div class="flex-1 h-px bg-slate-100"></div>
    </div>

    <!-- Pagination ──────────────────────────────────────────────────── -->
    <?php if (($lastPage ?? 1) > 1): ?>
      <div class="p-4 flex items-center justify-between border-t border-slate-100">
        <span class="text-xs text-slate-500">
          Page <?= (int)$page ?> / <?= (int)$lastPage ?> · <?= (int)$total ?> billet(s)
        </span>
        <div class="flex items-center gap-1">
          <?php
            $base = url('billetterie');
            $qs   = fn(int $p) => $base . '?' . http_build_query(array_merge($qp, ['page' => $p]));
          ?>
          <a href="<?= e($qs(max(1, (int)$page - 1))) ?>"
             class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50 <?= $page <= 1 ? 'opacity-30 pointer-events-none' : '' ?>">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
          </a>
          <span class="px-3 py-1.5 text-sm text-slate-600">
            <?= (int)$page ?> / <?= (int)$lastPage ?>
          </span>
          <a href="<?= e($qs(min((int)$lastPage, (int)$page + 1))) ?>"
             class="px-3 py-1.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50 <?= $page >= $lastPage ? 'opacity-30 pointer-events-none' : '' ?>">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
          </a>
        </div>
      </div>
    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
