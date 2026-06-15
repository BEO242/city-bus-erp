<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
use CityBus\Models\Trip;

// Couleurs selon statut
$statusColors = [
  'planifie'     => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400'],
  'valide'       => ['bg' => 'bg-cyan-100',  'text' => 'text-cyan-700',  'dot' => 'bg-cyan-500'],
  'embarquement' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500'],
  'en_route'     => ['bg' => 'bg-cb-primary','text' => 'text-white',     'dot' => 'bg-white'],
  'arrive'       => ['bg' => 'bg-emerald-100','text' => 'text-emerald-700','dot' => 'bg-emerald-500'],
  'cloture'      => ['bg' => 'bg-emerald-200','text' => 'text-emerald-800','dot' => 'bg-emerald-600'],
  'incident'     => ['bg' => 'bg-rose-100',  'text' => 'text-rose-700',  'dot' => 'bg-rose-500'],
  'retourne'     => ['bg' => 'bg-orange-100','text' => 'text-orange-700','dot' => 'bg-orange-500'],
  'litige'       => ['bg' => 'bg-purple-100','text' => 'text-purple-700','dot' => 'bg-purple-500'],
  'annule'       => ['bg' => 'bg-rose-100',  'text' => 'text-rose-600',  'dot' => 'bg-rose-400'],
];

$tolerance = (int)\CityBus\Core\Setting::getInt('voyage.delay_tolerance_minutes', 15);
?>
<?php $view->start('content') ?>

<div class="space-y-5">

  <!-- ─────────── EN-TÊTE ─────────── -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="bus" class="w-6 h-6 text-cb-primary"></i>
        Voyages
      </h1>
      <p class="text-sm text-slate-500 mt-1">Planification et suivi opérationnel</p>
    </div>
    <div class="flex gap-2">
      <?php if (can('voyages.export')): ?>
        <a href="<?= e(url('voyages/export.csv?date_from=' . $dateFrom . '&date_to=' . $dateTo)) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
          <i data-lucide="download" class="w-4 h-4"></i> Export CSV
        </a>
      <?php endif ?>
      <?php if (can('voyages.create')): ?>
        <a href="<?= e(url('voyages/create')) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-secondary shadow-soft">
          <i data-lucide="plus" class="w-4 h-4"></i> Nouveau voyage
        </a>
      <?php endif ?>
    </div>
  </div>

  <!-- ─────────── KPIs ─────────── -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Voyages période</div>
      <div class="text-3xl font-bold text-slate-900 mt-1"><?= (int)($kpiRow['total_count'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">En cours</div>
      <div class="text-3xl font-bold text-cb-primary mt-1"><?= (int)($kpiRow['in_progress'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Clôturés</div>
      <div class="text-3xl font-bold text-emerald-600 mt-1"><?= (int)($kpiRow['closed_count'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Incidents</div>
      <div class="text-3xl font-bold text-rose-600 mt-1"><?= (int)($kpiRow['incident_count'] ?? 0) ?></div>
    </div>
  </div>

  <!-- ─────────── FILTRES ─────────── -->
  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-4 shadow-soft">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Du</label>
        <input type="date" name="date_from" value="<?= e($dateFrom) ?>" placeholder="jj/mm/aaaa"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Au</label>
        <input type="date" name="date_to" value="<?= e($dateTo) ?>" placeholder="jj/mm/aaaa"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Statut</label>
        <select name="status" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
          <option value="">Tous</option>
          <?php foreach (Trip::STATUSES as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Ligne</label>
        <select name="line_id" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
          <option value="">Toutes</option>
          <?php foreach ($lines as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= (int)$lineId === (int)$l['id'] ? 'selected' : '' ?>>
              <?= e($l['code']) ?> · <?= e($l['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Recherche</label>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Code, bus, chauffeur…"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
      </div>
    </div>
    <div class="flex justify-end gap-2 mt-4">
      <a href="<?= e(url('voyages')) ?>" class="px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-50">Réinitialiser</a>
      <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium">
        <i data-lucide="search" class="w-4 h-4"></i> Filtrer
      </button>
    </div>
  </form>

  <!-- ─────────── LISTE TRIPS ─────────── -->
  <?php if (!$trips): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center shadow-soft">
      <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-100 flex items-center justify-center">
        <i data-lucide="bus" class="w-8 h-8 text-slate-400"></i>
      </div>
      <p class="text-slate-500 font-medium">Aucun voyage trouvé</p>
      <?php if (can('voyages.create')): ?>
        <a href="<?= e(url('voyages/create')) ?>"
           class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-cb-bg text-cb-primary text-sm font-semibold hover:bg-cb-primary hover:text-white">
          <i data-lucide="plus" class="w-4 h-4"></i> Planifier un voyage
        </a>
      <?php endif ?>
    </div>
  <?php else: ?>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-semibold text-slate-900"><?= count($trips) ?> voyage(s)</h2>
        <?php if (isset($lastPage) && $lastPage > 1): ?>
          <span class="text-xs text-slate-500">Page <?= (int)$page ?> / <?= (int)$lastPage ?> · <?= (int)$total ?> total</span>
        <?php endif ?>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="text-left px-4 py-3 font-semibold uppercase text-xs tracking-wide">Code</th>
              <th class="text-left px-4 py-3 font-semibold uppercase text-xs tracking-wide">Date / Heure</th>
              <th class="text-left px-4 py-3 font-semibold uppercase text-xs tracking-wide">Ligne</th>
              <th class="text-left px-4 py-3 font-semibold uppercase text-xs tracking-wide">Véhicule</th>
              <th class="text-left px-4 py-3 font-semibold uppercase text-xs tracking-wide">Chauffeur</th>
              <th class="text-center px-4 py-3 font-semibold uppercase text-xs tracking-wide">Statut</th>
              <th class="text-right px-4 py-3 font-semibold uppercase text-xs tracking-wide">Pax</th>
              <th class="text-right px-4 py-3 font-semibold uppercase text-xs tracking-wide">Recettes</th>
              <th class="text-right px-4 py-3 font-semibold uppercase text-xs tracking-wide">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($trips as $t):
              $sm = $statusColors[$t['status']] ?? $statusColors['planifie'];
              $sold = (int)($t['sold_count'] ?? 0);
              $seats = (int)($t['bus_seats'] ?? 0);
              $delay = (int)($t['delay_minutes'] ?? 0);
            ?>
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                  <a href="<?= e(url('voyages/' . $t['id'])) ?>"
                     class="font-mono text-sm text-cb-primary font-semibold hover:underline">
                    <?= e($t['trip_code']) ?>
                  </a>
                </td>
                <td class="px-4 py-3">
                  <div class="text-slate-900"><?= e(date('d/m/Y', strtotime($t['trip_date']))) ?></div>
                  <div class="text-xs text-slate-500 font-mono"><?= e(substr($t['departure_scheduled'], 0, 5)) ?></div>
                </td>
                <td class="px-4 py-3">
                  <div class="font-semibold text-slate-900"><?= e($t['line_code']) ?></div>
                  <div class="text-xs text-slate-500 truncate max-w-xs"><?= e($t['line_name']) ?></div>
                </td>
                <td class="px-4 py-3 font-mono text-xs">
                  <?= e($t['bus_code']) ?>
                  <div class="text-slate-400"><?= e($t['bus_plate'] ?? '') ?></div>
                </td>
                <td class="px-4 py-3 text-slate-700">
                  <?= e(trim(($t['driver_first'] ?? '') . ' ' . ($t['driver_last'] ?? ''))) ?: '—' ?>
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold <?= $sm['bg'] ?> <?= $sm['text'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $sm['dot'] ?>"></span>
                    <?= e(Trip::STATUSES[$t['status']] ?? $t['status']) ?>
                  </span>
                  <?php if ($delay > $tolerance): ?>
                    <div class="text-xs text-rose-600 font-bold mt-1">+<?= $delay ?>min</div>
                  <?php endif ?>
                </td>
                <td class="px-4 py-3 text-right font-mono">
                  <?= $sold ?> / <?= $seats ?>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-slate-900">
                  <?= e(fcfa((int)($t['revenue'] ?? 0))) ?>
                </td>
                <td class="px-4 py-3 text-right">
                  <a href="<?= e(url('voyages/' . $t['id'])) ?>" class="text-cb-primary hover:underline text-xs font-medium">
                    Voir →
                  </a>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>

      <!-- Règle de fin de liste -->
      <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-3 text-xs text-slate-400">
        <div class="flex-1 h-px bg-slate-100"></div>
        <span><?= count($trips) ?> enregistrement(s) affiché(s)<?= ($dateFrom === '' && $dateTo === '') ? ' · 30 derniers' : '' ?></span>
        <div class="flex-1 h-px bg-slate-100"></div>
      </div>

      <!-- Pagination -->
      <?php if (isset($lastPage) && $lastPage > 1): ?>
        <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between">
          <span class="text-xs text-slate-500"><?= (int)$total ?> voyage(s) au total</span>
          <div class="flex gap-1">
            <?php
              $params = $_GET;
              $url = function ($p) use ($params) {
                $params['page'] = $p;
                return '?' . http_build_query($params);
              };
            ?>
            <?php if ($page > 1): ?>
              <a href="<?= e($url(1)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs hover:bg-slate-50">«</a>
              <a href="<?= e($url($page - 1)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs hover:bg-slate-50">‹</a>
            <?php endif ?>
            <span class="px-3 py-1.5 rounded-lg bg-cb-primary text-white text-xs font-bold"><?= $page ?></span>
            <?php if ($page < $lastPage): ?>
              <a href="<?= e($url($page + 1)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs hover:bg-slate-50">›</a>
              <a href="<?= e($url($lastPage)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs hover:bg-slate-50">»</a>
            <?php endif ?>
          </div>
        </div>
      <?php endif ?>
    </div>

  <?php endif ?>

</div>

<?php $view->end() ?>
