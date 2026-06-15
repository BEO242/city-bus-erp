<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Line;
$view->extends('layouts/app');

// Paramètres courants pour construire les liens de pagination/tri
$qp = array_filter([
    'q'        => $search       ?? '',
    'status'   => $statusFilter ?? '',
    'type'     => $typeFilter   ?? '',
    'city'     => $cityFilter   ?? '',
    'alerts'   => $alertsFilter ?? '',
    'sort'     => $sortField    ?? 'code',
    'dir'      => $sortDir      ?? 'asc',
    'per_page' => ($perPage     ?? 24) !== 24 ? (string)$perPage : '',
], fn($v) => $v !== '' && $v !== null);

function lnPageUrl(array $base, int $p): string {
    return url('referentiel/lines') . '?' . http_build_query(array_merge($base, ['page' => $p]));
}
function lnSortUrl(array $base, string $field, string $curField, string $curDir): string {
    $dir = ($curField === $field && $curDir === 'asc') ? 'desc' : 'asc';
    return url('referentiel/lines') . '?' . http_build_query(array_merge($base, ['sort' => $field, 'dir' => $dir, 'page' => 1]));
}
?>
<?php $view->start('content') ?>

<!-- CDN export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js" defer></script>

<div class="space-y-4" x-data="lineIndex()" x-cloak>

  <!-- EN-TÊTE -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Lignes</h1>
      <p class="text-slate-500 text-sm">
        <?= $total ?> ligne(s) au total
        <?php if (($lastPage ?? 1) > 1): ?>&middot; page <?= $page ?> / <?= $lastPage ?><?php endif ?>
      </p>
    </div>
    <div class="flex items-center flex-wrap gap-2">
      <div class="flex items-center gap-0.5 bg-white rounded-xl border border-slate-200 p-1">
        <button @click="exportXlsx()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition"><i data-lucide="table-2" class="w-3.5 h-3.5"></i> XLSX</button>
        <button @click="exportPdf()"  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50 transition"><i data-lucide="file-text" class="w-3.5 h-3.5"></i> PDF</button>
        <button @click="exportDocx()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-700 hover:bg-blue-50 transition"><i data-lucide="file-type-2" class="w-3.5 h-3.5"></i> DOCX</button>
      </div>
      <div class="flex items-center gap-0.5 bg-white rounded-xl border border-slate-200 p-1">
        <button @click="setView('grid')" :class="view==='grid'?'bg-cb-bg text-cb-primary shadow-sm':'text-slate-400 hover:text-slate-600'" class="p-1.5 rounded-lg transition" title="Vue grille"><i data-lucide="layout-grid" class="w-4 h-4"></i></button>
        <button @click="setView('list')" :class="view==='list'?'bg-cb-bg text-cb-primary shadow-sm':'text-slate-400 hover:text-slate-600'" class="p-1.5 rounded-lg transition" title="Vue liste"><i data-lucide="list" class="w-4 h-4"></i></button>
      </div>
      <a href="<?= e(url('referentiel/lines/create')) ?>" class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-medium inline-flex items-center gap-2 hover:bg-cb-dark transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle ligne
      </a>
    </div>
  </div>

  <!-- FILTRES -->
  <form method="get" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
      <div class="sm:col-span-2 xl:col-span-2 relative">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
        <input name="q" value="<?= e($search ?? '') ?>" placeholder="Code, nom..." class="w-full pl-9 pr-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none transition">
      </div>
      <select name="status" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Tous les statuts</option>
        <option value="active"   <?= ($statusFilter ?? '')==='active'   ? 'selected' : '' ?>>Actives</option>
        <option value="inactive" <?= ($statusFilter ?? '')==='inactive' ? 'selected' : '' ?>>Désactivées</option>
      </select>
      <select name="type" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Tous les types</option>
        <?php foreach (Line::LINE_TYPES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($typeFilter ?? '')===$k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach ?>
      </select>
      <select name="city" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Toutes les villes</option>
        <?php foreach (Line::CITIES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($cityFilter ?? '')===$k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach ?>
      </select>
      <select name="alerts" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Toutes alertes</option>
        <option value="yes" <?= ($alertsFilter ?? '')==='yes' ? 'selected' : '' ?>>⚠ Avec alertes</option>
        <option value="no"  <?= ($alertsFilter ?? '')==='no'  ? 'selected' : '' ?>>✔ Sans alertes</option>
      </select>
      <div class="flex gap-1.5">
        <select name="sort" class="flex-1 px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
          <option value="code"           <?= ($sortField??'code')==='code'           ?'selected':'' ?>>Tri : Code</option>
          <option value="name"           <?= ($sortField??'code')==='name'           ?'selected':'' ?>>Nom</option>
          <option value="departure_city" <?= ($sortField??'code')==='departure_city' ?'selected':'' ?>>Départ</option>
          <option value="distance_km"    <?= ($sortField??'code')==='distance_km'    ?'selected':'' ?>>Distance</option>
          <option value="duration_hours" <?= ($sortField??'code')==='duration_hours' ?'selected':'' ?>>Durée</option>
          <option value="stops_count"    <?= ($sortField??'code')==='stops_count'    ?'selected':'' ?>>Nb arrêts</option>
          <option value="is_active"      <?= ($sortField??'code')==='is_active'      ?'selected':'' ?>>Statut</option>
          <option value="created_at"     <?= ($sortField??'code')==='created_at'     ?'selected':'' ?>>Date d'ajout</option>
          <option value="alerts_n"       <?= ($sortField??'code')==='alerts_n'       ?'selected':'' ?>>Alertes</option>
        </select>
        <button type="submit" name="dir" value="<?= ($sortDir??'asc')==='asc'?'desc':'asc' ?>" title="Inverser le tri"
                class="px-3 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
          <i data-lucide="<?= ($sortDir??'asc')==='asc'?'arrow-up-az':'arrow-down-az' ?>" class="w-4 h-4"></i>
        </button>
      </div>
    </div>
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div class="flex items-center gap-1">
        <span class="text-xs text-slate-400 mr-1">Par page :</span>
        <?php foreach ([12,24,48] as $n): ?>
          <a href="<?= e(url('referentiel/lines').'?'.http_build_query(array_merge($qp,['per_page'=>$n,'page'=>1]))) ?>"
             class="px-2.5 py-1 rounded-lg text-xs font-medium transition <?= ($perPage??24)===$n?'bg-cb-primary text-white':'text-slate-600 hover:bg-slate-100' ?>"><?= $n ?></a>
        <?php endforeach ?>
        <a href="<?= e(url('referentiel/lines').'?'.http_build_query(array_merge($qp,['per_page'=>9999,'page'=>1]))) ?>"
           class="px-2.5 py-1 rounded-lg text-xs font-medium transition <?= ($perPage??24)===9999?'bg-cb-primary text-white':'text-slate-600 hover:bg-slate-100' ?>">Tous</a>
      </div>
      <div class="flex gap-2">
        <?php $hasFilters = !empty($search)||!empty($statusFilter)||!empty($typeFilter)||!empty($cityFilter)||!empty($alertsFilter)||(!empty($sortField)&&$sortField!=='code'); ?>
        <?php if ($hasFilters): ?>
          <a href="<?= e(url('referentiel/lines')) ?>" class="px-3 py-1.5 text-sm rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition flex items-center gap-1.5">
            <i data-lucide="x" class="w-3.5 h-3.5"></i> Réinitialiser
          </a>
        <?php endif ?>
        <button type="submit" class="px-4 py-1.5 text-sm rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-dark transition flex items-center gap-1.5">
          <i data-lucide="filter" class="w-3.5 h-3.5"></i> Filtrer
        </button>
      </div>
    </div>
  </form>

  <?php if (empty($lines)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center">
      <i data-lucide="map" class="w-14 h-14 mx-auto text-slate-200 mb-3"></i>
      <p class="text-slate-500 text-sm">Aucune ligne ne correspond aux critères.</p>
    </div>
  <?php else: ?>

  <!-- VUE GRILLE -->
  <div x-show="view==='grid'" x-transition.opacity class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-4">
    <?php foreach ($lines as $l):
      $sc = Line::statusClass(Line::statusOf($l));
      $alertN = (int)($l['alerts_n'] ?? 0);
      $warnN  = (int)($l['warns_n']  ?? 0);
    ?>
      <div class="bg-white rounded-2xl border border-slate-100 hover:border-cb-primary hover:shadow-soft transition overflow-hidden flex flex-col relative">
        <?php if ($alertN > 0): ?>
          <span class="absolute top-2 right-2 z-10 inline-flex items-center gap-1 px-2 py-0.5 bg-rose-600 text-white text-xs font-bold rounded-full shadow"><i data-lucide="alert-triangle" class="w-3 h-3"></i> <?= $alertN ?></span>
        <?php elseif ($warnN > 0): ?>
          <span class="absolute top-2 right-2 z-10 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-500 text-white text-xs font-bold rounded-full shadow"><i data-lucide="alert-circle" class="w-3 h-3"></i> <?= $warnN ?></span>
        <?php endif ?>
        <a href="<?= e(url('referentiel/lines/'.$l['id'])) ?>" class="block">
          <div class="w-full h-24 bg-gradient-to-br from-cb-bg to-slate-100 flex items-center justify-center relative">
            <i data-lucide="map" class="w-12 h-12 text-cb-primary opacity-30"></i>
            <span class="absolute bottom-2 left-3 text-xs font-mono px-2 py-0.5 bg-white/80 backdrop-blur text-cb-primary rounded font-bold"><?= e($l['code']) ?></span>
          </div>
        </a>
        <div class="p-4 flex-1 flex flex-col">
          <div class="flex justify-between items-start gap-2">
            <div class="min-w-0 flex-1">
              <h3 class="font-bold truncate" title="<?= e($l['name']) ?>"><?= e($l['name']) ?></h3>
              <p class="text-xs text-slate-500 truncate flex items-center gap-1 mt-0.5">
                <i data-lucide="map-pin" class="w-3 h-3 text-cb-primary shrink-0"></i>
                <?= e(Line::tripLabel($l)) ?>
              </p>
            </div>
            <div class="flex flex-col gap-1 items-end shrink-0">
              <span class="px-2 py-0.5 rounded-full text-xs h-fit border <?= $sc ?>"><?= e(Line::STATUSES[Line::statusOf($l)]) ?></span>
              <?php $lt = $l['line_type'] ?? 'interurbain'; ?>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border <?= Line::LINE_TYPE_COLORS[$lt] ?? '' ?>">
                <i data-lucide="<?= e(Line::LINE_TYPE_ICONS[$lt] ?? 'route') ?>" class="w-3 h-3 inline -mt-px"></i>
                <?= e(Line::LINE_TYPES[$lt] ?? $lt) ?>
              </span>
            </div>
          </div>
          <div class="grid grid-cols-3 gap-1.5 mt-3 pt-3 border-t border-slate-100 text-center text-xs">
            <div><div class="text-slate-400">Distance</div><div class="font-bold"><?= e($l['distance_km'] ?? '—') ?> km</div></div>
            <div><div class="text-slate-400">Durée</div><div class="font-bold"><?= e($l['duration_hours'] ?? '—') ?>h</div></div>
            <div><div class="text-slate-400">Arrêts</div><div class="font-bold"><?= (int)($l['stops_count'] ?? 0) ?></div></div>
          </div>
          <div class="grid grid-cols-2 gap-1.5 mt-1.5 text-center text-xs">
            <div class="bg-cb-bg/50 rounded-lg py-1.5"><div class="text-slate-400">Tarifs actifs</div><div class="font-bold text-cb-primary"><?= (int)($l['tariffs_active'] ?? 0) ?></div></div>
            <div class="bg-slate-50 rounded-lg py-1.5"><div class="text-slate-400">Voyages</div><div class="font-bold text-slate-700"><?= (int)($l['trips_total'] ?? 0) ?></div></div>
          </div>
          <div class="flex gap-1.5 mt-3 pt-3 border-t border-slate-100">
            <a href="<?= e(url('referentiel/lines/'.$l['id'])) ?>" class="flex-1 text-center px-2 py-1.5 rounded-lg bg-cb-bg text-cb-primary text-xs font-medium hover:bg-cb-primary hover:text-white transition">
              <i data-lucide="eye" class="w-3.5 h-3.5 inline"></i> Fiche
            </a>
            <a href="<?= e(url('referentiel/lines/'.$l['id'].'/edit')) ?>" class="flex-1 text-center px-2 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-medium hover:bg-slate-50 transition">
              <i data-lucide="pencil" class="w-3.5 h-3.5 inline"></i> Modifier
            </a>
            <form method="post" action="<?= e(url('referentiel/lines/'.$l['id'].'/delete')) ?>" onsubmit="return confirm('Supprimer la ligne <?= e(addslashes($l['code'])) ?> ?')" class="contents">
              <?= csrf_field() ?><button type="submit" class="px-2 py-1.5 rounded-lg border border-rose-200 text-rose-500 text-xs hover:bg-rose-50 transition" title="Supprimer"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach ?>
  </div>

  <!-- VUE LISTE -->
  <div x-show="view==='list'" x-transition.opacity class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200 text-xs text-slate-500 uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left"><a href="<?= e(lnSortUrl($qp,'code',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center gap-1 hover:text-cb-primary">Code <?php if(($sortField??'code')==='code'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-left"><a href="<?= e(lnSortUrl($qp,'name',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center gap-1 hover:text-cb-primary">Nom <?php if(($sortField??'code')==='name'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-left">Itinéraire</th>
            <th class="px-4 py-3 text-right"><a href="<?= e(lnSortUrl($qp,'distance_km',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center justify-end gap-1 hover:text-cb-primary">Distance <?php if(($sortField??'code')==='distance_km'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-right"><a href="<?= e(lnSortUrl($qp,'duration_hours',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center justify-end gap-1 hover:text-cb-primary">Durée <?php if(($sortField??'code')==='duration_hours'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-center"><a href="<?= e(lnSortUrl($qp,'stops_count',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center justify-center gap-1 hover:text-cb-primary">Arrêts <?php if(($sortField??'code')==='stops_count'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-center">Tarifs</th>
            <th class="px-4 py-3 text-left"><a href="<?= e(lnSortUrl($qp,'line_type',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center gap-1 hover:text-cb-primary">Type <?php if(($sortField??'code')==='line_type'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-left"><a href="<?= e(lnSortUrl($qp,'is_active',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center gap-1 hover:text-cb-primary">Statut <?php if(($sortField??'code')==='is_active'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-center"><a href="<?= e(lnSortUrl($qp,'alerts_n',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center justify-center gap-1 hover:text-cb-primary">Alertes <?php if(($sortField??'code')==='alerts_n'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($lines as $l):
            $sc = Line::statusClass(Line::statusOf($l));
            $alertN = (int)($l['alerts_n'] ?? 0);
            $warnN  = (int)($l['warns_n']  ?? 0);
          ?>
            <tr class="hover:bg-cb-bg/50 transition">
              <td class="px-4 py-3"><span class="font-mono text-xs text-cb-primary font-bold"><?= e($l['code']) ?></span></td>
              <td class="px-4 py-3 font-medium text-slate-700 max-w-[200px] truncate"><?= e($l['name']) ?></td>
              <td class="px-4 py-3 text-slate-500 text-xs"><?= e(Line::tripLabel($l)) ?></td>
              <td class="px-4 py-3 text-right text-slate-600 font-mono text-xs"><?= e($l['distance_km'] ?? '—') ?> km</td>
              <td class="px-4 py-3 text-right text-slate-600 text-xs"><?= e($l['duration_hours'] ?? '—') ?>h</td>
              <td class="px-4 py-3 text-center text-slate-600"><?= (int)($l['stops_count'] ?? 0) ?></td>
              <td class="px-4 py-3 text-center text-cb-primary font-semibold"><?= (int)($l['tariffs_active'] ?? 0) ?></td>
              <?php $lt = $l['line_type'] ?? 'interurbain'; ?>
              <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border <?= Line::LINE_TYPE_COLORS[$lt] ?? '' ?>"><?= e(Line::LINE_TYPES[$lt] ?? $lt) ?></span></td>
              <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs border <?= $sc ?>"><?= e(Line::STATUSES[Line::statusOf($l)]) ?></span></td>
              <td class="px-4 py-3 text-center">
                <?php if ($alertN > 0): ?>
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-xs font-bold"><i data-lucide="alert-triangle" class="w-3 h-3"></i><?= $alertN ?></span>
                <?php elseif ($warnN > 0): ?>
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-bold"><i data-lucide="alert-circle" class="w-3 h-3"></i><?= $warnN ?></span>
                <?php else: ?>
                  <span class="text-emerald-500"><i data-lucide="check-circle" class="w-4 h-4 inline"></i></span>
                <?php endif ?>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1">
                  <a href="<?= e(url('referentiel/lines/'.$l['id'])) ?>" class="p-1.5 rounded-lg bg-cb-bg text-cb-primary hover:bg-cb-primary hover:text-white transition" title="Voir la fiche"><i data-lucide="eye" class="w-3.5 h-3.5"></i></a>
                  <a href="<?= e(url('referentiel/lines/'.$l['id'].'/edit')) ?>" class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition" title="Modifier"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a>
                  <form method="post" action="<?= e(url('referentiel/lines/'.$l['id'].'/delete')) ?>" onsubmit="return confirm('Supprimer la ligne <?= e(addslashes($l['code'])) ?> ?')" class="inline">
                    <?= csrf_field() ?><button type="submit" class="p-1.5 rounded-lg border border-rose-200 text-rose-500 hover:bg-rose-50 transition" title="Supprimer"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif ?>

  <!-- TABLE EXPORT (masquée) -->
  <table id="export-data" style="display:none" aria-hidden="true">
    <thead><tr><th>Code</th><th>Nom</th><th>Départ</th><th>Arrivée</th><th>Distance (km)</th><th>Durée (h)</th><th>Arrêts</th><th>Tarifs actifs</th><th>Voyages</th><th>Statut</th><th>Alertes</th></tr></thead>
    <tbody>
      <?php foreach ($lines as $l): ?>
        <tr>
          <td><?= e($l['code']) ?></td>
          <td><?= e($l['name']) ?></td>
          <td><?= e(Line::CITIES[$l['departure_city']] ?? $l['departure_city']) ?></td>
          <td><?= e(Line::CITIES[$l['arrival_city']] ?? $l['arrival_city']) ?></td>
          <td><?= e($l['distance_km'] ?? '') ?></td>
          <td><?= e($l['duration_hours'] ?? '') ?></td>
          <td><?= (int)($l['stops_count'] ?? 0) ?></td>
          <td><?= (int)($l['tariffs_active'] ?? 0) ?></td>
          <td><?= (int)($l['trips_total'] ?? 0) ?></td>
          <td><?= e(Line::STATUSES[Line::statusOf($l)]) ?></td>
          <td><?= (int)($l['alerts_n'] ?? 0) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <!-- PAGINATION -->
  <?php if (($lastPage??1) > 1): ?>
  <div class="flex items-center justify-between flex-wrap gap-4 bg-white rounded-2xl border border-slate-100 shadow-soft px-5 py-3">
    <p class="text-sm text-slate-500">
      Affichage de <strong><?= (($page-1)*($perPage===9999?$total:$perPage))+1 ?></strong> à <strong><?= min($page*($perPage===9999?$total:$perPage),$total) ?></strong> sur <strong><?= $total ?></strong> résultats
    </p>
    <nav class="flex items-center gap-1">
      <?php if($page>1):?><a href="<?= e(lnPageUrl($qp,$page-1)) ?>" class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition"><i data-lucide="chevron-left" class="w-4 h-4"></i></a><?php endif?>
      <?php for($p=1;$p<=$lastPage;$p++):
        $show = $p===1 || $p===$lastPage || abs($p-$page)<=2;
        $dots = !$show && abs($p-$page)===3;
        if($dots):?><span class="px-1 text-slate-400">…</span><?php
        elseif($show):?><a href="<?= e(lnPageUrl($qp,$p)) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition <?= $p===$page?'bg-cb-primary text-white':'text-slate-600 hover:bg-slate-100' ?>"><?= $p ?></a><?php endif?>
      <?php endfor?>
      <?php if($page<$lastPage):?><a href="<?= e(lnPageUrl($qp,$page+1)) ?>" class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition"><i data-lucide="chevron-right" class="w-4 h-4"></i></a><?php endif?>
    </nav>
  </div>
  <?php endif ?>
</div>

<style>[x-cloak]{display:none!important}</style>
<script>
function lineIndex(){return{
  view: localStorage.getItem('lines-view') || 'grid',
  setView(v){ this.view=v; localStorage.setItem('lines-view',v); },
  _rows(){
    const t = document.getElementById('export-data');
    const h = [...t.querySelectorAll('thead th')].map(c => c.textContent.trim());
    const r = [...t.querySelectorAll('tbody tr')].map(tr => [...tr.querySelectorAll('td')].map(td => td.textContent.trim()));
    return {h, r};
  },
  exportXlsx(){
    if (typeof XLSX === 'undefined') { alert('Librairie XLSX non encore chargée, réessayez.'); return; }
    const {h, r} = this._rows();
    const wb = XLSX.utils.book_new();
    const BLEU='1565C0', BLANC='FFFFFF', BGLC='F0F4FF', GREY='64748B', BORD='CBD5E1';
    const data = r.map(row => row.map((v,i) => {
      if (i===4||i===5) { const n=parseFloat(v); return isNaN(n)?v:n; }
      if (i===6||i===7||i===8||i===10) { const n=parseInt(v); return isNaN(n)?v:n; }
      return v;
    }));
    const aoa = [
      ['City Bus ERP \u2014 Lignes'],
      ['Exporté le <?= date('d/m/Y') ?> à <?= date('H:i') ?> · Total : <?= $total ?> ligne(s)'],
      [''],
      h, ...data
    ];
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    ws['!cols'] = [{wch:10},{wch:30},{wch:14},{wch:14},{wch:11},{wch:9},{wch:8},{wch:11},{wch:10},{wch:14},{wch:9}];
    ws['!rows'] = [{hpt:22},{hpt:13},{hpt:5},{hpt:18}];
    ws['!merges'] = [{s:{r:0,c:0},e:{r:0,c:h.length-1}},{s:{r:1,c:0},e:{r:1,c:h.length-1}}];
    const bT = (c) => ({top:{style:'thin',color:{rgb:c}},bottom:{style:'thin',color:{rgb:c}},left:{style:'thin',color:{rgb:c}},right:{style:'thin',color:{rgb:c}}});
    if (ws['A1']) ws['A1'].s = {font:{bold:true,sz:14,color:{rgb:BLEU}},alignment:{horizontal:'left',vertical:'center'}};
    if (ws['A2']) ws['A2'].s = {font:{sz:9,color:{rgb:GREY}},alignment:{horizontal:'left',vertical:'center'}};
    h.forEach((_,i) => {
      const ref = XLSX.utils.encode_cell({r:3,c:i});
      if (ws[ref]) ws[ref].s = {font:{bold:true,color:{rgb:BLANC},sz:9},fill:{fgColor:{rgb:BLEU}},alignment:{horizontal:'center',vertical:'center'},border:bT(BLEU)};
    });
    data.forEach((row,ri) => {
      const bg = ri%2===0 ? 'FFFFFF' : BGLC;
      row.forEach((_,ci) => {
        const ref = XLSX.utils.encode_cell({r:ri+4,c:ci});
        if (ws[ref]) ws[ref].s = {fill:{fgColor:{rgb:bg}},font:{sz:9},alignment:{horizontal:(ci>=4&&ci<=10)?'right':'left',vertical:'center'},border:bT(BORD)};
      });
    });
    XLSX.utils.book_append_sheet(wb, ws, 'Lignes');
    XLSX.writeFile(wb, 'citybus_lignes_<?= date('Ymd') ?>.xlsx');
  },
  exportPdf(){
    if (typeof window.jspdf === 'undefined') { alert('Librairie PDF non encore chargée, réessayez.'); return; }
    const {jsPDF} = window.jspdf;
    const doc = new jsPDF({orientation:'landscape', format:'a4', unit:'mm'});
    const W = doc.internal.pageSize.getWidth(), H = doc.internal.pageSize.getHeight();
    doc.setFillColor(21,101,192); doc.rect(0,0,W,20,'F');
    doc.setFillColor(13,71,161); doc.rect(0,0,14,20,'F');
    doc.setTextColor(255,255,255);
    doc.setFontSize(8); doc.setFont('helvetica','bold'); doc.text('LGN',7,11,{align:'center'});
    doc.setFontSize(15); doc.text('City Bus ERP',17,9);
    doc.setFontSize(9); doc.setFont('helvetica','normal'); doc.text('Lignes \u2014 Référentiel itinéraires',17,15);
    doc.setFontSize(7.5); doc.setTextColor(180,210,255);
    doc.text('Exporté le <?= date('d/m/Y') ?> à <?= date('H:i') ?>', W-5, 9, {align:'right'});
    doc.text('<?= $total ?> ligne(s) au total', W-5, 15, {align:'right'});
    doc.setFillColor(240,244,255); doc.rect(0,20,W,10,'F');
    doc.setDrawColor(210,220,240); doc.line(0,30,W,30);
    const si = [['Total','<?= $total ?>'],['Page','<?= $page ?>/<?= $lastPage ?? 1 ?>'],['Tri','<?= addslashes($sortField ?? 'code') ?>'],['Ordre','<?= $sortDir ?? 'asc' ?>']];
    let sx = 6;
    si.forEach(([l,v]) => {
      doc.setFontSize(7); doc.setTextColor(100,116,139); doc.setFont('helvetica','normal'); doc.text(l+':',sx,26.5);
      doc.setTextColor(21,101,192); doc.setFont('helvetica','bold'); doc.text(v,sx+13,26.5);
      sx += 60;
    });
    doc.autoTable({
      html: '#export-data', startY: 33, margin: {left:5,right:5},
      styles: {fontSize:7.5, cellPadding:{top:2.5,bottom:2.5,left:2.5,right:2.5}, lineColor:[226,232,240], lineWidth:0.2, overflow:'linebreak'},
      headStyles: {fillColor:[21,101,192], textColor:255, fontStyle:'bold', halign:'center', valign:'middle', minCellHeight:9},
      alternateRowStyles: {fillColor:[240,244,255]},
      bodyStyles: {textColor:[30,41,59]},
      columnStyles: {0:{halign:'center',cellWidth:18}, 1:{cellWidth:'auto'}, 2:{cellWidth:24}, 3:{cellWidth:24}, 4:{halign:'right',cellWidth:18}, 5:{halign:'right',cellWidth:14}, 6:{halign:'center',cellWidth:14}, 7:{halign:'center',cellWidth:18}, 8:{halign:'center',cellWidth:16}, 9:{halign:'center',cellWidth:22}, 10:{halign:'center',cellWidth:14}},
      didDrawPage(){
        const pN = doc.internal.getCurrentPageInfo().pageNumber, pT = doc.internal.getNumberOfPages();
        doc.setFillColor(248,250,252); doc.rect(0,H-8,W,8,'F');
        doc.setDrawColor(226,232,240); doc.line(0,H-8,W,H-8);
        doc.setFontSize(6.5); doc.setTextColor(148,163,184); doc.setFont('helvetica','normal');
        doc.text('City Bus ERP \u2014 Document confidentiel', 5, H-2.5);
        doc.text('Page '+pN+' / '+pT, W-5, H-2.5, {align:'right'});
      }
    });
    doc.save('citybus_lignes_<?= date('Ymd') ?>.pdf');
  },
  exportDocx(){
    const {h, r} = this._rows();
    const tHead = '<tr>' + h.map(c => '<th>'+c+'</th>').join('') + '</tr>';
    const tBody = r.map((row,i) => '<tr class="'+(i%2===0?'':'alt')+'">' + row.map(c => '<td>'+c+'</td>').join('') + '</tr>').join('');
    const html = `<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="UTF-8">
<style>
  @page{margin:1.5cm 2cm;mso-page-orientation:landscape}
  body{font-family:Calibri,Arial,sans-serif;font-size:10pt;color:#1E293B;margin:0}
  .hdr{background:#1565C0;color:#fff;padding:10px 14px;border-bottom:3px solid #0D47A1}
  .hdr-t{font-size:15pt;font-weight:bold;color:#fff;margin:0 0 3px}
  .hdr-s{font-size:8.5pt;color:#C8DCFF;margin:0}
  .meta{background:#F0F4FF;padding:6px 14px;border-bottom:1px solid #1565C0;font-size:8pt}
  .meta b{color:#1565C0}
  h2{color:#1565C0;font-size:11pt;margin:14px 0 6px;border-bottom:2px solid #1565C0;padding-bottom:3px}
  table{border-collapse:collapse;width:100%;font-size:8pt}
  th{background:#1565C0;color:#fff;font-weight:bold;padding:6px 8px;text-align:center;border:1px solid #1565C0}
  td{border:1px solid #CBD5E1;padding:5px 8px;vertical-align:middle}
  .alt td{background:#F0F4FF}
  .ftr{margin-top:14px;padding-top:7px;border-top:1px solid #CBD5E1;font-size:7.5pt;color:#94A3B8}
</style></head>
<body>
<div class="hdr"><p class="hdr-t">City Bus ERP &mdash; Lignes</p><p class="hdr-s">Référentiel itinéraires &bull; Exporté le <?= date('d/m/Y') ?> à <?= date('H:i') ?></p></div>
<div class="meta">Total&nbsp;: <b><?= $total ?> ligne(s)</b> &nbsp;&mdash;&nbsp; Tri&nbsp;: <b><?= e($sortField ?? 'code') ?></b> &nbsp;&mdash;&nbsp; Ordre&nbsp;: <b><?= e($sortDir ?? 'asc') ?></b></div>
<h2>Liste des lignes</h2>
<table><thead>${tHead}</thead><tbody>${tBody}</tbody></table>
<div class="ftr">City Bus ERP &mdash; Document confidentiel, usage interne uniquement &nbsp;&bull;&nbsp; Exporté le <?= date('d/m/Y') ?> à <?= date('H:i') ?></div>
</body></html>`;
    const blob = new Blob([html], {type:'application/msword'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'citybus_lignes_<?= date('Ymd') ?>.doc';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(a.href), 1500);
  }
}}
</script>
<?php $view->end() ?>
