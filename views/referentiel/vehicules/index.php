<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Bus;
$view->extends('layouts/app');

// Paramètres courants pour construire les liens de pagination/tri
$qp = array_filter([
    'q'          => $search       ?? '',
    'status'     => $statusFilter ?? '',
    'agency_id'  => $agencyFilter  ? (string)$agencyFilter : '',
    'alerts'     => $alertsFilter ?? '',
    'sort'       => $sortField    ?? 'code',
    'dir'        => $sortDir      ?? 'asc',
    'per_page'   => ($perPage     ?? 24) !== 24 ? (string)$perPage : '',
], fn($v) => $v !== '' && $v !== null);

function busPageUrl(array $base, int $p): string {
    return url('referentiel/vehicules') . '?' . http_build_query(array_merge($base, ['page' => $p]));
}
function busSortUrl(array $base, string $field, string $curField, string $curDir): string {
    $dir = ($curField === $field && $curDir === 'asc') ? 'desc' : 'asc';
    return url('referentiel/vehicules') . '?' . http_build_query(array_merge($base, ['sort' => $field, 'dir' => $dir, 'page' => 1]));
}
?>
<?php $view->start('content') ?>

<!-- CDN export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js" defer></script>

<div class="space-y-4" x-data="busIndex()" x-cloak>

  <!-- EN-TÊTE -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Flotte de véhicules</h1>
      <p class="text-slate-500 text-sm">
        <?= $total ?> véhicule(s) au total
        <?php if (($lastPage ?? 1) > 1): ?>
          &middot; page <?= $page ?> / <?= $lastPage ?>
        <?php endif ?>
      </p>
    </div>
    <div class="flex items-center flex-wrap gap-2">
      <!-- Exports -->
      <div class="flex items-center gap-0.5 bg-white rounded-xl border border-slate-200 p-1">
        <button @click="exportXlsx()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition">
          <i data-lucide="table-2" class="w-3.5 h-3.5"></i> XLSX
        </button>
        <button @click="exportPdf()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50 transition">
          <i data-lucide="file-text" class="w-3.5 h-3.5"></i> PDF
        </button>
        <button @click="exportDocx()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-blue-700 hover:bg-blue-50 transition">
          <i data-lucide="file-type-2" class="w-3.5 h-3.5"></i> DOCX
        </button>
      </div>
      <!-- Bascule vue -->
      <div class="flex items-center gap-0.5 bg-white rounded-xl border border-slate-200 p-1">
        <button @click="setView('grid')" :class="view==='grid'?'bg-cb-bg text-cb-primary shadow-sm':'text-slate-400 hover:text-slate-600'" class="p-1.5 rounded-lg transition" title="Vue grille"><i data-lucide="layout-grid" class="w-4 h-4"></i></button>
        <button @click="setView('list')" :class="view==='list'?'bg-cb-bg text-cb-primary shadow-sm':'text-slate-400 hover:text-slate-600'" class="p-1.5 rounded-lg transition" title="Vue liste"><i data-lucide="list" class="w-4 h-4"></i></button>
      </div>
      <a href="<?= e(url('referentiel/vehicle-types')) ?>"
         class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition">
        <i data-lucide="settings-2" class="w-4 h-4"></i> Types
      </a>
      <a href="<?= e(url('referentiel/vehicules/create')) ?>" class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-medium inline-flex items-center gap-2 hover:bg-cb-dark transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Nouveau véhicule
      </a>
    </div>
  </div>

  <!-- FILTRES -->
  <form method="get" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
      <div class="sm:col-span-2 xl:col-span-2 relative">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
        <input name="q" value="<?= e($search ?? '') ?>" placeholder="Code, plaque, marque, modèle..." class="w-full pl-9 pr-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none transition">
      </div>
      <select name="status" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Tous les statuts</option>
        <?php foreach (Bus::STATUSES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($statusFilter ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach ?>
      </select>
      <select name="agency_id" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Toutes les agences</option>
        <?php foreach ($agencies as $a): ?>
          <option value="<?= e($a['id']) ?>" <?= (int)($agencyFilter ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
        <?php endforeach ?>
      </select>
      <select name="alerts" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <option value="">Toutes alertes</option>
        <option value="yes" <?= ($alertsFilter ?? '') === 'yes' ? 'selected' : '' ?>>⚠ Avec alertes</option>
        <option value="no"  <?= ($alertsFilter ?? '') === 'no'  ? 'selected' : '' ?>>✔ Sans alertes</option>
      </select>
      <div class="flex gap-1.5">
        <select name="sort" class="flex-1 px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
          <option value="code"       <?= ($sortField??'code')==='code'       ?'selected':'' ?>>Tri : Code</option>
          <option value="plate"      <?= ($sortField??'code')==='plate'      ?'selected':'' ?>>Plaque</option>
          <option value="brand"      <?= ($sortField??'code')==='brand'      ?'selected':'' ?>>Marque</option>
          <option value="year"       <?= ($sortField??'code')==='year'       ?'selected':'' ?>>Année</option>
          <option value="km_current" <?= ($sortField??'code')==='km_current' ?'selected':'' ?>>Kilométrage</option>
          <option value="status"     <?= ($sortField??'code')==='status'     ?'selected':'' ?>>Statut</option>
          <option value="created_at" <?= ($sortField??'code')==='created_at' ?'selected':'' ?>>Date d'ajout</option>
          <option value="alerts_n"   <?= ($sortField??'code')==='alerts_n'   ?'selected':'' ?>>Alertes</option>
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
          <a href="<?= e(url('referentiel/vehicules').'?'.http_build_query(array_merge($qp,['per_page'=>$n,'page'=>1]))) ?>"
             class="px-2.5 py-1 rounded-lg text-xs font-medium transition <?= ($perPage??24)===$n?'bg-cb-primary text-white':'text-slate-600 hover:bg-slate-100' ?>"><?= $n ?></a>
        <?php endforeach ?>
        <a href="<?= e(url('referentiel/vehicules').'?'.http_build_query(array_merge($qp,['per_page'=>9999,'page'=>1]))) ?>"
           class="px-2.5 py-1 rounded-lg text-xs font-medium transition <?= ($perPage??24)===9999?'bg-cb-primary text-white':'text-slate-600 hover:bg-slate-100' ?>">Tous</a>
      </div>
      <div class="flex gap-2">
        <?php $hasFilters = !empty($search)||!empty($statusFilter)||!empty($agencyFilter)||!empty($alertsFilter)||(!empty($sortField)&&$sortField!=='code'); ?>
        <?php if ($hasFilters): ?>
          <a href="<?= e(url('referentiel/vehicules')) ?>" class="px-3 py-1.5 text-sm rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition flex items-center gap-1.5">
            <i data-lucide="x" class="w-3.5 h-3.5"></i> Réinitialiser
          </a>
        <?php endif ?>
        <button type="submit" class="px-4 py-1.5 text-sm rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-dark transition flex items-center gap-1.5">
          <i data-lucide="filter" class="w-3.5 h-3.5"></i> Filtrer
        </button>
      </div>
    </div>
  </form>

  <?php if (empty($buses)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center">
      <i data-lucide="bus" class="w-14 h-14 mx-auto text-slate-200 mb-3"></i>
      <p class="text-slate-500 text-sm">Aucun bus ne correspond aux critères.</p>
    </div>
  <?php else: ?>

  <!-- VUE GRILLE -->
  <div x-show="view==='grid'" x-transition.opacity class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php foreach ($buses as $b):
      $sc=$b['status']==='en_service'?'bg-emerald-50 text-emerald-700 border-emerald-200':
         ($b['status']==='maintenance'?'bg-amber-50 text-amber-700 border-amber-200':
         ($b['status']==='hors_service'?'bg-rose-50 text-rose-700 border-rose-200':'bg-slate-50 text-slate-600 border-slate-200'));
      $sc=Bus::statusClass($b['status']); $alertN=(int)($b['alerts_n']??0); ?>
      <div class="bg-white rounded-2xl border border-slate-100 hover:border-cb-primary hover:shadow-soft transition overflow-hidden flex flex-col relative">
        <?php if ($alertN>0): ?><span class="absolute top-2 right-2 z-10 inline-flex items-center gap-1 px-2 py-0.5 bg-rose-600 text-white text-xs font-bold rounded-full shadow"><i data-lucide="alert-triangle" class="w-3 h-3"></i> <?= $alertN ?></span><?php endif ?>
        <a href="<?= e(url('referentiel/vehicules/'.$b['id'])) ?>">
          <?php if (!empty($b['cover_url'])): ?>
            <img src="<?= e($b['cover_url']) ?>" alt="<?= e($b['code']) ?>" class="w-full h-36 object-cover">
          <?php else: ?>
            <div class="w-full h-36 bg-gradient-to-br from-cb-bg to-slate-100 flex items-center justify-center"><i data-lucide="bus" class="w-12 h-12 text-slate-200"></i></div>
          <?php endif ?>
        </a>
        <div class="p-4 flex-1 flex flex-col">
          <div class="flex justify-between items-start gap-2">
            <div class="min-w-0">
              <span class="text-xs font-mono px-2 py-0.5 bg-cb-bg text-cb-primary rounded"><?= e($b['code']) ?></span>
              <h3 class="font-bold mt-1.5 truncate"><?= e($b['plate']) ?></h3>
              <p class="text-xs text-slate-500 truncate"><?= e(trim($b['brand'].' '.$b['model'])) ?> · <?= e($b['year']) ?></p>
            </div>
            <span class="px-2 py-0.5 rounded-full text-xs h-fit border <?= $sc ?> shrink-0"><?= e(str_replace('_',' ',$b['status'])) ?></span>
          </div>
          <div class="grid grid-cols-3 gap-1.5 mt-3 pt-3 border-t border-slate-100 text-center text-xs">
            <div><div class="text-slate-400">Places</div><div class="font-bold"><?= e($b['seats']) ?></div></div>
            <div><div class="text-slate-400">Km</div><div class="font-bold"><?= number_format((int)$b['km_current'],0,',',' ') ?></div></div>
            <div><div class="text-slate-400">Agence</div><div class="font-medium truncate"><?= e($b['agency_name']??'—') ?></div></div>
          </div>
          <?php if (!empty($b['primary_driver_name'])): ?>
            <div class="text-xs text-slate-500 mt-2 flex items-center gap-1.5 truncate">
              <i data-lucide="user" class="w-3 h-3 text-cb-primary shrink-0"></i><span class="truncate"><?= e($b['primary_driver_name']) ?></span>
            </div>
          <?php endif ?>
          <div class="flex gap-1.5 mt-3 pt-3 border-t border-slate-100">
            <a href="<?= e(url('referentiel/vehicules/'.$b['id'])) ?>" class="flex-1 text-center px-2 py-1.5 rounded-lg bg-cb-bg text-cb-primary text-xs font-medium hover:bg-cb-primary hover:text-white transition">
              <i data-lucide="eye" class="w-3.5 h-3.5 inline"></i> Fiche
            </a>
            <a href="<?= e(url('referentiel/vehicules/'.$b['id'].'/edit')) ?>" class="flex-1 text-center px-2 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-medium hover:bg-slate-50 transition">
              <i data-lucide="pencil" class="w-3.5 h-3.5 inline"></i> Modifier
            </a>
            <form method="post" action="<?= e(url('referentiel/vehicules/'.$b['id'].'/delete')) ?>" onsubmit="return confirm('Supprimer <?= e(addslashes($b['code'])) ?> ?')" class="contents">
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
            <th class="px-4 py-3 text-left"><a href="<?= e(busSortUrl($qp,'code',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center gap-1 hover:text-cb-primary">Code / Plaque <?php if(($sortField??'code')==='code'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-left"><a href="<?= e(busSortUrl($qp,'brand',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center gap-1 hover:text-cb-primary">Marque / Modèle <?php if(($sortField??'code')==='brand'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-left"><a href="<?= e(busSortUrl($qp,'year',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center gap-1 hover:text-cb-primary">Année <?php if(($sortField??'code')==='year'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-right"><a href="<?= e(busSortUrl($qp,'km_current',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center justify-end gap-1 hover:text-cb-primary">Km <?php if(($sortField??'code')==='km_current'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-left"><a href="<?= e(busSortUrl($qp,'status',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center gap-1 hover:text-cb-primary">Statut <?php if(($sortField??'code')==='status'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-left">Agence</th>
            <th class="px-4 py-3 text-left">Chauffeur</th>
            <th class="px-4 py-3 text-center"><a href="<?= e(busSortUrl($qp,'alerts_n',$sortField??'code',$sortDir??'asc')) ?>" class="flex items-center justify-center gap-1 hover:text-cb-primary">Alertes <?php if(($sortField??'code')==='alerts_n'):?><i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i><?php endif?></a></th>
            <th class="px-4 py-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($buses as $b):
            $sc=Bus::statusClass($b['status']); $alertN=(int)($b['alerts_n']??0); ?>
            <tr class="hover:bg-cb-bg/50 transition">
              <td class="px-4 py-3"><div class="font-mono text-xs text-cb-primary font-bold"><?= e($b['code']) ?></div><div class="text-slate-600 text-xs mt-0.5"><?= e($b['plate']) ?></div></td>
              <td class="px-4 py-3 text-slate-700"><?= e(trim($b['brand'].' '.$b['model'])) ?></td>
              <td class="px-4 py-3 text-slate-500"><?= e($b['year']) ?></td>
              <td class="px-4 py-3 text-right text-slate-600 font-mono text-xs"><?= number_format((int)$b['km_current'],0,',',' ') ?></td>
              <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs border <?= $sc ?>"><?= e(str_replace('_',' ',$b['status'])) ?></span></td>
              <td class="px-4 py-3 text-slate-600 text-xs"><?= e($b['agency_name']??'—') ?></td>
              <td class="px-4 py-3 text-slate-500 text-xs max-w-[140px] truncate"><?= e($b['primary_driver_name']??'—') ?></td>
              <td class="px-4 py-3 text-center">
                <?php if($alertN>0):?><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-xs font-bold"><i data-lucide="alert-triangle" class="w-3 h-3"></i><?= $alertN ?></span>
                <?php else:?><span class="text-emerald-500"><i data-lucide="check-circle" class="w-4 h-4 inline"></i></span><?php endif?>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1">
                  <a href="<?= e(url('referentiel/vehicules/'.$b['id'])) ?>" class="p-1.5 rounded-lg bg-cb-bg text-cb-primary hover:bg-cb-primary hover:text-white transition" title="Voir la fiche"><i data-lucide="eye" class="w-3.5 h-3.5"></i></a>
                  <a href="<?= e(url('referentiel/vehicules/'.$b['id'].'/edit')) ?>" class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition" title="Modifier"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a>
                  <form method="post" action="<?= e(url('referentiel/vehicules/'.$b['id'].'/delete')) ?>" onsubmit="return confirm('Supprimer <?= e(addslashes($b['code'])) ?> ?')" class="inline">
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
    <thead><tr><th>Code</th><th>Plaque</th><th>Marque</th><th>Modèle</th><th>Année</th><th>Places</th><th>Km</th><th>Carburant</th><th>Statut</th><th>Agence</th><th>Chauffeur principal</th><th>Alertes</th></tr></thead>
    <tbody>
      <?php foreach ($buses as $b): ?>
        <tr><td><?= e($b['code']) ?></td><td><?= e($b['plate']) ?></td><td><?= e($b['brand']) ?></td><td><?= e($b['model']) ?></td><td><?= e($b['year']) ?></td><td><?= e($b['seats']) ?></td><td><?= (int)$b['km_current'] ?></td><td><?= e($b['fuel_type']??'') ?></td><td><?= e(str_replace('_',' ',$b['status'])) ?></td><td><?= e($b['agency_name']??'') ?></td><td><?= e($b['primary_driver_name']??'') ?></td><td><?= (int)($b['alerts_n']??0) ?></td></tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <!-- PAGINATION -->
  <?php if (($lastPage??1)>1): ?>
  <div class="flex items-center justify-between flex-wrap gap-4 bg-white rounded-2xl border border-slate-100 shadow-soft px-5 py-3">
    <p class="text-sm text-slate-500">
      Affichage de <strong><?= (($page-1)*($perPage===9999?$total:$perPage))+1 ?></strong> à <strong><?= min($page*($perPage===9999?$total:$perPage),$total) ?></strong> sur <strong><?= $total ?></strong> résultats
    </p>
    <nav class="flex items-center gap-1">
      <?php if($page>1):?><a href="<?= e(busPageUrl($qp,$page-1)) ?>" class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition"><i data-lucide="chevron-left" class="w-4 h-4"></i></a><?php endif?>
      <?php for($p=1;$p<=$lastPage;$p++):
        $show=$p===1||$p===$lastPage||abs($p-$page)<=2;
        $dots=!$show&&abs($p-$page)===3;
        if($dots):?><span class="px-1 text-slate-400">…</span><?php
        elseif($show):?><a href="<?= e(busPageUrl($qp,$p)) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition <?= $p===$page?'bg-cb-primary text-white':'text-slate-600 hover:bg-slate-100' ?>"><?= $p ?></a><?php endif?>
      <?php endfor?>
      <?php if($page<$lastPage):?><a href="<?= e(busPageUrl($qp,$page+1)) ?>" class="p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition"><i data-lucide="chevron-right" class="w-4 h-4"></i></a><?php endif?>
    </nav>
  </div>
  <?php endif ?>
</div>

<style>[x-cloak]{display:none!important}</style>
<script>
function busIndex(){return{
  view:localStorage.getItem('buses-view')||'grid',
  setView(v){this.view=v;localStorage.setItem('buses-view',v)},
  _rows(){
    const t=document.getElementById('export-data');
    const h=[...t.querySelectorAll('thead th')].map(c=>c.textContent.trim());
    const r=[...t.querySelectorAll('tbody tr')].map(tr=>[...tr.querySelectorAll('td')].map(td=>td.textContent.trim()));
    return{h,r};
  },
  exportXlsx(){
    if(typeof XLSX==='undefined'){alert('Librairie XLSX non encore chargée, réessayez.');return}
    const{h,r}=this._rows();
    const wb=XLSX.utils.book_new();
    const BLEU='1565C0',BLANC='FFFFFF',BGLC='F0F4FF',GREY='64748B',BORD='CBD5E1';
    const data=r.map(row=>row.map((v,i)=>{
      if(i===6){const n=parseFloat(v.replace(/[\s\u00a0]/g,''));return isNaN(n)?v:n;}
      if(i===4||i===5||i===11){const n=parseInt(v);return isNaN(n)?v:n;}
      return v;
    }));
    const aoa=[
      ['City Bus ERP \u2014 Flotte de bus'],
      ['Export\u00e9 le <?= date('d/m/Y') ?> \u00e0 <?= date('H:i') ?> \u00b7 Total : <?= $total ?> v\u00e9hicule(s)'],
      [''],
      h,...data
    ];
    const ws=XLSX.utils.aoa_to_sheet(aoa);
    ws['!cols']=[{wch:10},{wch:14},{wch:14},{wch:14},{wch:7},{wch:7},{wch:13},{wch:10},{wch:16},{wch:18},{wch:22},{wch:8}];
    ws['!rows']=[{hpt:22},{hpt:13},{hpt:5},{hpt:18}];
    ws['!merges']=[{s:{r:0,c:0},e:{r:0,c:h.length-1}},{s:{r:1,c:0},e:{r:1,c:h.length-1}}];
    const bT=(c)=>({top:{style:'thin',color:{rgb:c}},bottom:{style:'thin',color:{rgb:c}},left:{style:'thin',color:{rgb:c}},right:{style:'thin',color:{rgb:c}}});
    if(ws['A1'])ws['A1'].s={font:{bold:true,sz:14,color:{rgb:BLEU}},alignment:{horizontal:'left',vertical:'center'}};
    if(ws['A2'])ws['A2'].s={font:{sz:9,color:{rgb:GREY}},alignment:{horizontal:'left',vertical:'center'}};
    h.forEach((_,i)=>{const ref=XLSX.utils.encode_cell({r:3,c:i});if(ws[ref])ws[ref].s={font:{bold:true,color:{rgb:BLANC},sz:9},fill:{fgColor:{rgb:BLEU}},alignment:{horizontal:'center',vertical:'center'},border:bT(BLEU)};});
    data.forEach((row,ri)=>{
      const bg=ri%2===0?'FFFFFF':BGLC;
      row.forEach((_,ci)=>{const ref=XLSX.utils.encode_cell({r:ri+4,c:ci});if(ws[ref])ws[ref].s={fill:{fgColor:{rgb:bg}},font:{sz:9},alignment:{horizontal:ci===6?'right':'left',vertical:'center'},border:bT(BORD)};});
    });
    XLSX.utils.book_append_sheet(wb,ws,'Flotte de bus');
    XLSX.writeFile(wb,'citybus_flotte_<?= date('Ymd') ?>.xlsx');
  },
  exportPdf(){
    if(typeof window.jspdf==='undefined'){alert('Librairie PDF non encore chargée, réessayez.');return}
    const{jsPDF}=window.jspdf;
    const doc=new jsPDF({orientation:'landscape',format:'a4',unit:'mm'});
    const W=doc.internal.pageSize.getWidth(),H=doc.internal.pageSize.getHeight();
    // ─── Header ──────────────────────────────────────────
    doc.setFillColor(21,101,192);doc.rect(0,0,W,20,'F');
    doc.setFillColor(13,71,161);doc.rect(0,0,14,20,'F');
    doc.setTextColor(255,255,255);
    doc.setFontSize(8);doc.setFont('helvetica','bold');doc.text('BUS',7,11,{align:'center'});
    doc.setFontSize(15);doc.text('City Bus ERP',17,9);
    doc.setFontSize(9);doc.setFont('helvetica','normal');doc.text('Flotte de bus \u2014 R\u00e9f\u00e9rentiel v\u00e9hicules',17,15);
    doc.setFontSize(7.5);doc.setTextColor(180,210,255);
    doc.text('Export\u00e9 le <?= date('d/m/Y') ?> \u00e0 <?= date('H:i') ?>',W-5,9,{align:'right'});
    doc.text('<?= $total ?> v\u00e9hicule(s) au total',W-5,15,{align:'right'});
    // ─── Barre stats ─────────────────────────────────────
    doc.setFillColor(240,244,255);doc.rect(0,20,W,10,'F');
    doc.setDrawColor(210,220,240);doc.line(0,30,W,30);
    const si=[['Total','<?= $total ?>'],['Page','<?= $page ?>/<?= $lastPage ?? 1 ?>'],['Tri','<?= addslashes($sortField ?? 'code') ?>'],['Ordre','<?= $sortDir ?? 'asc' ?>']];
    let sx=6;
    si.forEach(([l,v])=>{
      doc.setFontSize(7);doc.setTextColor(100,116,139);doc.setFont('helvetica','normal');doc.text(l+':',sx,26.5);
      doc.setTextColor(21,101,192);doc.setFont('helvetica','bold');doc.text(v,sx+13,26.5);
      sx+=60;
    });
    // ─── Tableau ─────────────────────────────────────────
    doc.autoTable({
      html:'#export-data',startY:33,margin:{left:5,right:5},
      styles:{fontSize:7.5,cellPadding:{top:2.5,bottom:2.5,left:2.5,right:2.5},lineColor:[226,232,240],lineWidth:0.2,overflow:'linebreak'},
      headStyles:{fillColor:[21,101,192],textColor:255,fontStyle:'bold',halign:'center',valign:'middle',minCellHeight:9},
      alternateRowStyles:{fillColor:[240,244,255]},
      bodyStyles:{textColor:[30,41,59]},
      columnStyles:{0:{halign:'center',cellWidth:16},1:{cellWidth:18},2:{cellWidth:18},3:{cellWidth:16},4:{halign:'center',cellWidth:12},5:{halign:'center',cellWidth:10},6:{halign:'right',cellWidth:16},7:{halign:'center',cellWidth:14},8:{halign:'center',cellWidth:18},9:{cellWidth:'auto'},10:{cellWidth:'auto'},11:{halign:'center',cellWidth:12}},
      didDrawPage(){
        const pN=doc.internal.getCurrentPageInfo().pageNumber,pT=doc.internal.getNumberOfPages();
        doc.setFillColor(248,250,252);doc.rect(0,H-8,W,8,'F');
        doc.setDrawColor(226,232,240);doc.line(0,H-8,W,H-8);
        doc.setFontSize(6.5);doc.setTextColor(148,163,184);doc.setFont('helvetica','normal');
        doc.text('City Bus ERP \u2014 Document confidentiel',5,H-2.5);
        doc.text('Page '+pN+' / '+pT,W-5,H-2.5,{align:'right'});
      }
    });
    doc.save('citybus_flotte_<?= date('Ymd') ?>.pdf');
  },
  exportDocx(){
    const{h,r}=this._rows();
    const tHead='<tr>'+h.map(c=>'<th>'+c+'</th>').join('')+'</tr>';
    const tBody=r.map((row,i)=>'<tr class="'+(i%2===0?'':'alt')+'">'+row.map(c=>'<td>'+c+'</td>').join('')+'</tr>').join('');
    const html=`<!DOCTYPE html>
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
<div class="hdr"><p class="hdr-t">City Bus ERP &mdash; Flotte de bus</p><p class="hdr-s">R&eacute;f&eacute;rentiel v&eacute;hicules &bull; Export&eacute; le <?= date('d/m/Y') ?> &agrave; <?= date('H:i') ?></p></div>
<div class="meta">Total&nbsp;: <b><?= $total ?> v&eacute;hicule(s)</b> &nbsp;&mdash;&nbsp; Tri&nbsp;: <b><?= e($sortField ?? 'code') ?></b> &nbsp;&mdash;&nbsp; Ordre&nbsp;: <b><?= e($sortDir ?? 'asc') ?></b></div>
<h2>Liste de la flotte</h2>
<table><thead>${tHead}</thead><tbody>${tBody}</tbody></table>
<div class="ftr">City Bus ERP &mdash; Document confidentiel, usage interne uniquement &nbsp;&bull;&nbsp; Export&eacute; le <?= date('d/m/Y') ?> &agrave; <?= date('H:i') ?></div>
</body></html>`;
    const blob=new Blob([html],{type:'application/msword'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);a.download='citybus_flotte_<?= date('Ymd') ?>.doc';
    document.body.appendChild(a);a.click();document.body.removeChild(a);
    setTimeout(()=>URL.revokeObjectURL(a.href),1500);
  }
}}
</script>
<?php $view->end() ?>
