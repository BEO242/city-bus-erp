<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Driver;
$view->extends('layouts/app');

$qp = array_filter([
    'q'           => $search       ?? '',
    'status'      => $statusFilter ?? '',
    'agency_id'   => $agencyFilter  ? (string)$agencyFilter : '',
    'alerts'      => $alertsFilter ?? '',
    'license_cat' => $licFilter    ?? '',
    'sort'        => $sortField    ?? 'last_name',
    'dir'         => $sortDir      ?? 'asc',
    'per_page'    => ($perPage     ?? 24) !== 24 ? (string)$perPage : '',
], fn($v) => $v !== '' && $v !== null);

function drvPageUrl(array $base, int $p): string {
    return url('referentiel/drivers') . '?' . http_build_query(array_merge($base, ['page' => $p]));
}
function drvSortUrl(array $base, string $field, string $curField, string $curDir): string {
    $dir = ($curField === $field && $curDir === 'asc') ? 'desc' : 'asc';
    return url('referentiel/drivers') . '?' . http_build_query(array_merge($base, ['sort' => $field, 'dir' => $dir, 'page' => 1]));
}
function licExpiryClass(?string $date): string {
    if (empty($date) || $date === '0000-00-00') return '';
    $days = (int)floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0)  return 'text-rose-600 font-bold';
    if ($days <= 15) return 'text-rose-600 font-semibold';
    if ($days <= 45) return 'text-amber-600 font-semibold';
    return 'text-slate-400';
}
function licExpiryBg(?string $date): string {
    if (empty($date) || $date === '0000-00-00') return '';
    $days = (int)floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0)  return 'bg-rose-100 text-rose-700 border-rose-200';
    if ($days <= 15) return 'bg-rose-50 text-rose-600 border-rose-200';
    if ($days <= 45) return 'bg-amber-50 text-amber-700 border-amber-200';
    return 'bg-slate-50 text-slate-500 border-slate-200';
}

$kpis          = $kpis          ?? [];
$alertsCount   = $alertsCount   ?? 0;
$statusCountMap= $statusCountMap ?? [];

$statusChips = [
    'actif'        => ['label' => 'Actifs',       'dot' => 'bg-emerald-500', 'cls' => 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100'],
    'conge'        => ['label' => 'En congé',      'dot' => 'bg-blue-500',    'cls' => 'text-blue-700 bg-blue-50 border-blue-200 hover:bg-blue-100'],
    'en_formation' => ['label' => 'Formation',     'dot' => 'bg-purple-500',  'cls' => 'text-purple-700 bg-purple-50 border-purple-200 hover:bg-purple-100'],
    'suspendu'     => ['label' => 'Suspendus',     'dot' => 'bg-rose-500',    'cls' => 'text-rose-700 bg-rose-50 border-rose-200 hover:bg-rose-100'],
    'accident'     => ['label' => 'Arrêt',         'dot' => 'bg-orange-500',  'cls' => 'text-orange-700 bg-orange-50 border-orange-200 hover:bg-orange-100'],
    'quitte'       => ['label' => 'Quittés',       'dot' => 'bg-slate-400',   'cls' => 'text-slate-600 bg-slate-100 border-slate-200 hover:bg-slate-200'],
];
?>
<?php $view->start('content') ?>

<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js" defer></script>

<div class="space-y-5" x-data="drvIndex()" x-cloak>

  <!-- ── EN-TÊTE ─────────────────────────────────────────────────────────── -->
  <div class="flex items-start justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-black text-slate-900">Chauffeurs</h1>
      <p class="text-sm text-slate-400 mt-0.5"><?= $total ?> résultat(s) affiché(s)</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <!-- Exports -->
      <div class="flex items-center gap-0.5 bg-white rounded-xl border border-slate-200 p-1 shadow-sm">
        <button @click="exportXlsx()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition"><i data-lucide="table-2" class="w-3.5 h-3.5"></i> XLSX</button>
        <button @click="exportPdf()"  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50 transition"><i data-lucide="file-text" class="w-3.5 h-3.5"></i> PDF</button>
      </div>
      <!-- Toggle vue -->
      <div class="flex items-center gap-0.5 bg-white rounded-xl border border-slate-200 p-1 shadow-sm">
        <button @click="setView('grid')" :class="view==='grid'?'bg-cb-bg text-cb-primary shadow-sm':'text-slate-400 hover:text-slate-600'" class="p-1.5 rounded-lg transition" title="Vue grille"><i data-lucide="layout-grid" class="w-4 h-4"></i></button>
        <button @click="setView('list')" :class="view==='list'?'bg-cb-bg text-cb-primary shadow-sm':'text-slate-400 hover:text-slate-600'" class="p-1.5 rounded-lg transition" title="Vue liste"><i data-lucide="list" class="w-4 h-4"></i></button>
      </div>
      <a href="<?= e(url('referentiel/drivers/create')) ?>"
         class="px-4 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-bold inline-flex items-center gap-2 hover:bg-cb-dark transition shadow-sm">
        <i data-lucide="user-plus" class="w-4 h-4"></i> Nouveau chauffeur
      </a>
    </div>
  </div>

  <!-- ── KPI CARDS ──────────────────────────────────────────────────────── -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
        <i data-lucide="user-check" class="w-6 h-6"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-slate-900"><?= (int)($kpis['actif_n'] ?? 0) ?></p>
        <p class="text-xs text-slate-400 font-medium">Actifs</p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-slate-900"><?= $alertsCount ?></p>
        <p class="text-xs text-slate-400 font-medium">Avec alertes</p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
        <i data-lucide="id-card" class="w-6 h-6"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-slate-900"><?= (int)($kpis['lic_expired_n'] ?? 0) + (int)($kpis['lic_soon_n'] ?? 0) ?></p>
        <p class="text-xs text-slate-400 font-medium">Permis critiques</p>
        <?php if((int)($kpis['lic_expired_n']??0)>0):?>
          <p class="text-[10px] text-rose-500 font-semibold"><?= (int)$kpis['lic_expired_n'] ?> expiré(s)</p>
        <?php endif?>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
        <i data-lucide="user-x" class="w-6 h-6"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-slate-900"><?= (int)($kpis['indispo_n'] ?? 0) + (int)($kpis['suspendu_n'] ?? 0) ?></p>
        <p class="text-xs text-slate-400 font-medium">Non disponibles</p>
      </div>
    </div>
  </div>

  <!-- ── CHIPS STATUT RAPIDE ─────────────────────────────────────────────── -->
  <div class="flex items-center gap-2 flex-wrap">
    <a href="<?= e(url('referentiel/drivers').'?'.http_build_query(array_merge($qp,['status'=>'','page'=>1]))) ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition
              <?= empty($statusFilter) ? 'bg-cb-primary text-white border-cb-primary shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
      Tous
      <span class="<?= empty($statusFilter) ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?> px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none"><?= (int)($kpis['total'] ?? 0) ?></span>
    </a>
    <?php foreach($statusChips as $sk => $chip):
      $n = (int)($statusCountMap[$sk] ?? 0);
      if ($n === 0 && $statusFilter !== $sk) continue;
      $active = $statusFilter === $sk;
    ?>
    <a href="<?= e(url('referentiel/drivers').'?'.http_build_query(array_merge($qp,['status'=>$sk,'page'=>1]))) ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition
              <?= $active ? 'bg-cb-primary text-white border-cb-primary shadow-sm' : $chip['cls'] . ' border' ?>">
      <span class="w-1.5 h-1.5 rounded-full <?= $active ? 'bg-white' : $chip['dot'] ?>"></span>
      <?= $chip['label'] ?>
      <span class="<?= $active ? 'bg-white/20 text-white' : 'bg-white/60 text-slate-700' ?> px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none"><?= $n ?></span>
    </a>
    <?php endforeach?>
    <?php if(!empty($alertsFilter) && $alertsFilter==='yes'):?>
    <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-600 text-white border border-rose-600">
      <i data-lucide="alert-triangle" class="w-3 h-3"></i> Avec alertes
      <a href="<?= e(url('referentiel/drivers').'?'.http_build_query(array_merge($qp,['alerts'=>'','page'=>1]))) ?>" class="ml-1 hover:opacity-75"><i data-lucide="x" class="w-3 h-3"></i></a>
    </span>
    <?php endif?>
  </div>

  <!-- ── FILTRES ──────────────────────────────────────────────────────────── -->
  <form method="get" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
      <!-- Recherche -->
      <div class="sm:col-span-2 relative">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
        <input name="q" value="<?= e($search??'') ?>" placeholder="Matricule, nom, téléphone, permis…"
               class="w-full pl-9 pr-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none transition">
      </div>
      <!-- Statut (hidden, géré via chips — garde quand même pour form submit) -->
      <input type="hidden" name="status" value="<?= e($statusFilter??'') ?>">
      <!-- Agence -->
      <select name="agency_id" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none bg-white">
        <option value="">Toutes les agences</option>
        <?php foreach($agencies as $a):?>
          <option value="<?= e($a['id']) ?>" <?= (int)($agencyFilter??0)===(int)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option>
        <?php endforeach?>
      </select>
      <!-- Catégorie permis -->
      <select name="license_cat" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none bg-white">
        <option value="">Toutes catégories</option>
        <?php foreach(Driver::LICENSE_CATEGORIES as $cat):?>
          <option value="<?= e($cat) ?>" <?= ($licFilter??'')===$cat?'selected':'' ?>>Cat. <?= e($cat) ?></option>
        <?php endforeach?>
      </select>
      <!-- Alertes -->
      <select name="alerts" class="px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none bg-white">
        <option value="">Toutes alertes</option>
        <option value="yes" <?= ($alertsFilter??'')==='yes'?'selected':'' ?>>⚠ Avec alertes</option>
        <option value="no"  <?= ($alertsFilter??'')==='no' ?'selected':'' ?>>✓ Sans alertes</option>
      </select>
      <!-- Tri + boutons -->
      <div class="flex gap-2">
        <select name="sort" class="flex-1 px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:border-cb-primary outline-none bg-white min-w-0">
          <option value="last_name"    <?= ($sortField??'last_name')==='last_name'   ?'selected':'' ?>>Tri : Nom</option>
          <option value="matricule"    <?= ($sortField??'last_name')==='matricule'   ?'selected':'' ?>>Matricule</option>
          <option value="hire_date"    <?= ($sortField??'last_name')==='hire_date'   ?'selected':'' ?>>Embauche</option>
          <option value="rating_score" <?= ($sortField??'last_name')==='rating_score'?'selected':'' ?>>Note</option>
          <option value="alerts_n"     <?= ($sortField??'last_name')==='alerts_n'    ?'selected':'' ?>>Alertes</option>
        </select>
        <button type="submit" name="dir" value="<?= ($sortDir??'asc')==='asc'?'desc':'asc' ?>"
                class="px-3 py-2.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition shrink-0"
                title="Inverser le tri">
          <i data-lucide="<?= ($sortDir??'asc')==='asc'?'arrow-up-az':'arrow-down-az' ?>" class="w-4 h-4"></i>
        </button>
      </div>
    </div>
    <!-- Ligne 2 : per-page + actions -->
    <div class="flex items-center justify-between gap-3 mt-3 pt-3 border-t border-slate-100">
      <div class="flex items-center gap-1.5">
        <span class="text-xs text-slate-400">Par page :</span>
        <?php foreach([12,24,48] as $n):?>
          <a href="<?= e(url('referentiel/drivers').'?'.http_build_query(array_merge($qp,['per_page'=>$n,'page'=>1]))) ?>"
             class="px-2.5 py-1 rounded-lg text-xs font-semibold transition
                    <?= ($perPage??24)===$n?'bg-cb-primary text-white shadow-sm':'text-slate-500 hover:bg-slate-100' ?>"><?= $n ?></a>
        <?php endforeach?>
        <a href="<?= e(url('referentiel/drivers').'?'.http_build_query(array_merge($qp,['per_page'=>9999,'page'=>1]))) ?>"
           class="px-2.5 py-1 rounded-lg text-xs font-semibold transition
                  <?= ($perPage??24)===9999?'bg-cb-primary text-white shadow-sm':'text-slate-500 hover:bg-slate-100' ?>">Tous</a>
      </div>
      <div class="flex gap-2">
        <?php $hasFilters = !empty($search)||!empty($statusFilter)||!empty($agencyFilter)||!empty($alertsFilter)||!empty($licFilter); ?>
        <?php if($hasFilters):?>
          <a href="<?= e(url('referentiel/drivers')) ?>"
             class="px-3 py-1.5 text-sm rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition inline-flex items-center gap-1.5">
            <i data-lucide="x" class="w-3.5 h-3.5"></i> Réinitialiser
          </a>
        <?php endif?>
        <button type="submit"
                class="px-4 py-1.5 text-sm rounded-xl bg-cb-primary text-white font-semibold hover:bg-cb-dark transition inline-flex items-center gap-1.5">
          <i data-lucide="search" class="w-3.5 h-3.5"></i> Filtrer
        </button>
      </div>
    </div>
  </form>

  <?php if(empty($drivers)):?>
  <div class="bg-white rounded-2xl border border-slate-100 p-16 text-center">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
      <i data-lucide="users" class="w-8 h-8 text-slate-300"></i>
    </div>
    <p class="font-semibold text-slate-500">Aucun chauffeur trouvé</p>
    <p class="text-sm text-slate-400 mt-1">Modifiez les filtres ou ajoutez un nouveau chauffeur.</p>
    <a href="<?= e(url('referentiel/drivers/create')) ?>"
       class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition">
      <i data-lucide="user-plus" class="w-4 h-4"></i> Nouveau chauffeur
    </a>
  </div>
  <?php else:?>

  <!-- ── VUE GRILLE ─────────────────────────────────────────────────────── -->
  <div x-show="view==='grid'" x-transition.opacity
       class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php foreach($drivers as $d):
      $sc       = Driver::statusClass($d['status']);
      $alertN   = (int)($d['alerts_n'] ?? 0);
      $initials = strtoupper(substr($d['first_name'],0,1).substr($d['last_name'],0,1));
      $rating   = (float)($d['rating_score'] ?? 5.0);
      $licBg    = licExpiryBg($d['license_expiry'] ?? null);
      $gradients= ['from-cb-primary to-blue-700','from-emerald-500 to-teal-600','from-violet-500 to-purple-700','from-amber-500 to-orange-600','from-rose-500 to-pink-600'];
      $grad     = $gradients[crc32($d['matricule']) % count($gradients)];
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 hover:border-cb-primary/40 hover:shadow-lg transition-all duration-200 overflow-hidden flex flex-col group relative">

      <!-- Badge alertes -->
      <?php if($alertN > 0):?>
        <span class="absolute top-2.5 right-2.5 z-10 inline-flex items-center gap-1 px-2 py-1 bg-rose-600 text-white text-[10px] font-black rounded-full shadow-md">
          <i data-lucide="alert-triangle" class="w-2.5 h-2.5"></i> <?= $alertN ?>
        </span>
      <?php endif?>

      <!-- Photo / Avatar -->
      <a href="<?= e(url('referentiel/drivers/'.$d['id'])) ?>" class="block relative">
        <?php if(!empty($d['photo_url'])):?>
          <img src="<?= e($d['photo_url']) ?>" alt="<?= e($initials) ?>"
               class="w-full h-44 object-cover object-top group-hover:scale-105 transition-transform duration-300">
          <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
        <?php else:?>
          <div class="w-full h-44 bg-gradient-to-br <?= $grad ?> flex items-center justify-center relative overflow-hidden">
            <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 30% 70%, white 1px, transparent 1px); background-size:20px 20px;"></div>
            <span class="text-5xl font-black text-white/80 select-none tracking-tight"><?= e($initials) ?></span>
          </div>
        <?php endif?>
      </a>

      <!-- Corps -->
      <div class="p-4 flex-1 flex flex-col gap-3">

        <!-- Identité + statut -->
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="flex items-center gap-1.5 mb-0.5">
              <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 bg-cb-bg text-cb-primary rounded"><?= e($d['matricule']) ?></span>
            </div>
            <h3 class="font-bold text-slate-900 leading-tight truncate text-sm">
              <?= e($d['last_name']) ?> <span class="font-medium"><?= e($d['first_name']) ?></span>
            </h3>
            <p class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
              <i data-lucide="phone" class="w-3 h-3 shrink-0"></i>
              <span class="truncate"><?= e($d['phone']) ?></span>
            </p>
          </div>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border shrink-0 <?= $sc ?>">
            <?= Driver::STATUSES[$d['status']] ?? $d['status'] ?>
          </span>
        </div>

        <!-- Infos clés -->
        <div class="space-y-1.5 text-xs">
          <!-- Permis -->
          <div class="flex items-center gap-2">
            <span class="w-5 h-5 rounded-md bg-slate-100 flex items-center justify-center shrink-0">
              <i data-lucide="id-card" class="w-3 h-3 text-slate-500"></i>
            </span>
            <span class="text-slate-600 font-medium">Cat. <?= e($d['license_categories'] ?: '—') ?></span>
            <?php if(!empty($d['license_expiry']) && $d['license_expiry'] !== '0000-00-00'):?>
              <span class="ml-auto text-[10px] font-mono px-1.5 py-0.5 rounded border <?= $licBg ?>">
                <?= e(date('d/m/Y', strtotime($d['license_expiry']))) ?>
              </span>
            <?php endif?>
          </div>
          <!-- Bus -->
          <?php if(!empty($d['bus_code'])):?>
          <div class="flex items-center gap-2">
            <span class="w-5 h-5 rounded-md bg-slate-100 flex items-center justify-center shrink-0">
              <i data-lucide="bus" class="w-3 h-3 text-slate-500"></i>
            </span>
            <span class="text-slate-600 truncate"><?= e($d['bus_code']) ?> · <?= e($d['bus_plate']) ?></span>
          </div>
          <?php endif?>
          <!-- Agence -->
          <?php if(!empty($d['agency_name'])):?>
          <div class="flex items-center gap-2">
            <span class="w-5 h-5 rounded-md bg-slate-100 flex items-center justify-center shrink-0">
              <i data-lucide="building-2" class="w-3 h-3 text-slate-500"></i>
            </span>
            <span class="text-slate-500 truncate"><?= e($d['agency_name']) ?></span>
          </div>
          <?php endif?>
        </div>

        <!-- Footer : rating + actions -->
        <div class="flex items-center justify-between mt-auto pt-3 border-t border-slate-100">
          <!-- Étoiles -->
          <div class="flex items-center gap-1.5">
            <div class="flex">
              <?php
              $fullStars = (int)floor($rating / 2);
              $halfStar  = ($rating / 2 - $fullStars) >= 0.5;
              for($i = 1; $i <= 5; $i++):
                if($i <= $fullStars): ?>
                  <i data-lucide="star" class="w-3.5 h-3.5 text-amber-400 fill-amber-400"></i>
                <?php elseif($i === $fullStars + 1 && $halfStar): ?>
                  <i data-lucide="star-half" class="w-3.5 h-3.5 text-amber-400 fill-amber-400"></i>
                <?php else: ?>
                  <i data-lucide="star" class="w-3.5 h-3.5 text-slate-200 fill-slate-200"></i>
                <?php endif;
              endfor; ?>
            </div>
            <span class="text-xs font-bold text-slate-700"><?= number_format($rating, 1, ',', '') ?></span>
          </div>
          <!-- Actions -->
          <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <a href="<?= e(url('referentiel/drivers/'.$d['id'])) ?>"
               class="w-7 h-7 rounded-lg bg-cb-bg text-cb-primary flex items-center justify-center hover:bg-cb-primary hover:text-white transition" title="Voir">
              <i data-lucide="eye" class="w-3.5 h-3.5"></i>
            </a>
            <a href="<?= e(url('referentiel/drivers/'.$d['id'].'/edit')) ?>"
               class="w-7 h-7 rounded-lg border border-slate-200 text-slate-400 flex items-center justify-center hover:bg-slate-50 transition" title="Modifier">
              <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
            </a>
            <form method="post" action="<?= e(url('referentiel/drivers/'.$d['id'].'/delete')) ?>"
                  onsubmit="return confirm('Supprimer <?= e(addslashes($d['matricule'])) ?> ?')" class="contents">
              <?= csrf_field() ?>
              <button type="submit"
                      class="w-7 h-7 rounded-lg border border-rose-200 text-rose-400 flex items-center justify-center hover:bg-rose-50 transition" title="Supprimer">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach?>
  </div>

  <!-- ── VUE LISTE ──────────────────────────────────────────────────────── -->
  <div x-show="view==='list'" x-transition.opacity
       class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">
            <th class="px-5 py-3.5 text-left">
              <a href="<?= e(drvSortUrl($qp,'matricule',$sortField??'last_name',$sortDir??'asc')) ?>"
                 class="flex items-center gap-1 hover:text-cb-primary transition">
                Chauffeur
                <?php if(in_array($sortField??'last_name',['matricule','last_name'])):?>
                  <i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i>
                <?php endif?>
              </a>
            </th>
            <th class="px-5 py-3.5 text-left">Contact</th>
            <th class="px-5 py-3.5 text-left">
              <a href="<?= e(drvSortUrl($qp,'status',$sortField??'last_name',$sortDir??'asc')) ?>"
                 class="flex items-center gap-1 hover:text-cb-primary transition">
                Statut
                <?php if(($sortField??'last_name')==='status'):?>
                  <i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i>
                <?php endif?>
              </a>
            </th>
            <th class="px-5 py-3.5 text-left">Permis</th>
            <th class="px-5 py-3.5 text-left">Bus / Agence</th>
            <th class="px-5 py-3.5 text-center">
              <a href="<?= e(drvSortUrl($qp,'rating_score',$sortField??'last_name',$sortDir??'asc')) ?>"
                 class="flex items-center justify-center gap-1 hover:text-cb-primary transition">
                Note
                <?php if(($sortField??'last_name')==='rating_score'):?>
                  <i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i>
                <?php endif?>
              </a>
            </th>
            <th class="px-5 py-3.5 text-center">
              <a href="<?= e(drvSortUrl($qp,'alerts_n',$sortField??'last_name',$sortDir??'asc')) ?>"
                 class="flex items-center justify-center gap-1 hover:text-cb-primary transition">
                Alertes
                <?php if(($sortField??'last_name')==='alerts_n'):?>
                  <i data-lucide="<?= ($sortDir??'asc')==='asc'?'chevron-up':'chevron-down' ?>" class="w-3 h-3"></i>
                <?php endif?>
              </a>
            </th>
            <th class="px-5 py-3.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach($drivers as $d):
            $sc     = Driver::statusClass($d['status']);
            $alertN = (int)($d['alerts_n'] ?? 0);
            $rating = (float)($d['rating_score'] ?? 5.0);
            $licBg  = licExpiryBg($d['license_expiry'] ?? null);
            $initials = strtoupper(substr($d['first_name'],0,1).substr($d['last_name'],0,1));
            $gradients= ['from-cb-primary to-blue-700','from-emerald-500 to-teal-600','from-violet-500 to-purple-700','from-amber-500 to-orange-600','from-rose-500 to-pink-600'];
            $grad     = $gradients[crc32($d['matricule']) % count($gradients)];
          ?>
          <tr class="hover:bg-slate-50/70 transition group">
            <!-- Chauffeur -->
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-3">
                <?php if(!empty($d['photo_url'])):?>
                  <img src="<?= e($d['photo_url']) ?>" alt="<?= e($initials) ?>"
                       class="w-9 h-9 rounded-full object-cover shrink-0 ring-2 ring-white shadow-sm">
                <?php else:?>
                  <div class="w-9 h-9 rounded-full bg-gradient-to-br <?= $grad ?> flex items-center justify-center shrink-0 ring-2 ring-white shadow-sm">
                    <span class="text-xs font-black text-white"><?= e($initials) ?></span>
                  </div>
                <?php endif?>
                <div class="min-w-0">
                  <div class="font-mono text-[10px] text-cb-primary font-bold"><?= e($d['matricule']) ?></div>
                  <div class="font-semibold text-slate-800 text-sm truncate">
                    <?= e($d['last_name']) ?> <?= e($d['first_name']) ?>
                  </div>
                </div>
              </div>
            </td>
            <!-- Contact -->
            <td class="px-5 py-3.5">
              <div class="text-xs text-slate-600 flex items-center gap-1">
                <i data-lucide="phone" class="w-3 h-3 text-slate-400"></i>
                <?= e($d['phone']) ?>
              </div>
              <?php if(!empty($d['email'])):?>
              <div class="text-[10px] text-slate-400 truncate max-w-[140px]"><?= e($d['email']) ?></div>
              <?php endif?>
            </td>
            <!-- Statut -->
            <td class="px-5 py-3.5">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold border <?= $sc ?>">
                <?= Driver::STATUSES[$d['status']] ?? $d['status'] ?>
              </span>
            </td>
            <!-- Permis -->
            <td class="px-5 py-3.5">
              <div class="text-xs font-semibold text-slate-700">Cat. <?= e($d['license_categories'] ?: '—') ?></div>
              <?php if(!empty($d['license_expiry']) && $d['license_expiry'] !== '0000-00-00'):?>
                <span class="inline-block mt-0.5 text-[10px] font-mono px-1.5 py-0.5 rounded border <?= $licBg ?>">
                  <?= e(date('d/m/Y', strtotime($d['license_expiry']))) ?>
                </span>
              <?php endif?>
            </td>
            <!-- Bus / Agence -->
            <td class="px-5 py-3.5">
              <?php if(!empty($d['bus_code'])):?>
                <div class="text-xs font-semibold text-cb-primary font-mono"><?= e($d['bus_code']) ?></div>
                <div class="text-[10px] text-slate-400"><?= e($d['bus_plate'] ?? '') ?></div>
              <?php else:?>
                <span class="text-xs text-slate-300">—</span>
              <?php endif?>
              <?php if(!empty($d['agency_name'])):?>
                <div class="text-[10px] text-slate-400 flex items-center gap-0.5 mt-0.5">
                  <i data-lucide="building-2" class="w-2.5 h-2.5"></i><?= e($d['agency_name']) ?>
                </div>
              <?php endif?>
            </td>
            <!-- Note -->
            <td class="px-5 py-3.5 text-center">
              <div class="flex items-center justify-center gap-1 flex-col">
                <span class="text-sm font-black text-slate-800"><?= number_format($rating, 1, ',', '') ?></span>
                <div class="flex">
                  <?php
                  $full = (int)floor($rating/2);
                  for($i=1;$i<=5;$i++):?>
                    <i data-lucide="star" class="w-2.5 h-2.5 <?= $i<=$full?'text-amber-400 fill-amber-400':'text-slate-200 fill-slate-200' ?>"></i>
                  <?php endfor;?>
                </div>
              </div>
            </td>
            <!-- Alertes -->
            <td class="px-5 py-3.5 text-center">
              <?php if($alertN > 0):?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-xs font-bold">
                  <i data-lucide="alert-triangle" class="w-3 h-3"></i><?= $alertN ?>
                </span>
              <?php else:?>
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 mx-auto"></i>
              <?php endif?>
            </td>
            <!-- Actions -->
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition">
                <a href="<?= e(url('referentiel/drivers/'.$d['id'])) ?>"
                   class="w-8 h-8 rounded-lg bg-cb-bg text-cb-primary flex items-center justify-center hover:bg-cb-primary hover:text-white transition" title="Voir">
                  <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                </a>
                <a href="<?= e(url('referentiel/drivers/'.$d['id'].'/edit')) ?>"
                   class="w-8 h-8 rounded-lg border border-slate-200 text-slate-400 flex items-center justify-center hover:bg-slate-50 transition" title="Modifier">
                  <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </a>
                <form method="post" action="<?= e(url('referentiel/drivers/'.$d['id'].'/delete')) ?>"
                      onsubmit="return confirm('Supprimer <?= e(addslashes($d['matricule'])) ?> ?')" class="contents">
                  <?= csrf_field() ?>
                  <button type="submit"
                          class="w-8 h-8 rounded-lg border border-rose-200 text-rose-400 flex items-center justify-center hover:bg-rose-50 transition" title="Supprimer">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif?>

  <!-- ── TABLE EXPORT (masquée) ────────────────────────────────────────── -->
  <table id="export-data" style="display:none" aria-hidden="true">
    <thead><tr><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Téléphone</th><th>Statut</th><th>Agence</th><th>Permis cat.</th><th>Permis expiry</th><th>Bus</th><th>Note/10</th><th>Alertes</th></tr></thead>
    <tbody>
      <?php foreach($drivers as $d):?>
        <tr>
          <td><?= e($d['matricule']) ?></td>
          <td><?= e($d['last_name']) ?></td>
          <td><?= e($d['first_name']) ?></td>
          <td><?= e($d['phone']) ?></td>
          <td><?= e(Driver::STATUSES[$d['status']] ?? $d['status']) ?></td>
          <td><?= e($d['agency_name'] ?? '') ?></td>
          <td><?= e($d['license_categories'] ?? '') ?></td>
          <td><?= e($d['license_expiry'] ?? '') ?></td>
          <td><?= e(!empty($d['bus_code']) ? $d['bus_code'].' '.$d['bus_plate'] : '') ?></td>
          <td><?= number_format((float)$d['rating_score'],1,',','') ?></td>
          <td><?= (int)($d['alerts_n'] ?? 0) ?></td>
        </tr>
      <?php endforeach?>
    </tbody>
  </table>

  <!-- ── PAGINATION ─────────────────────────────────────────────────────── -->
  <?php if(($lastPage??1) > 1):?>
  <div class="flex items-center justify-between flex-wrap gap-4 bg-white rounded-2xl border border-slate-100 shadow-sm px-5 py-3.5">
    <p class="text-xs text-slate-400">
      Affichage de <strong class="text-slate-700"><?= (($page-1)*($perPage===9999?$total:$perPage))+1 ?></strong>
      à <strong class="text-slate-700"><?= min($page*($perPage===9999?$total:$perPage),$total) ?></strong>
      sur <strong class="text-slate-700"><?= $total ?></strong>
    </p>
    <nav class="flex items-center gap-1">
      <?php if($page>1):?>
        <a href="<?= e(drvPageUrl($qp,$page-1)) ?>"
           class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition">
          <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </a>
      <?php endif?>
      <?php for($p=1;$p<=$lastPage;$p++):
        $show = $p===1 || $p===$lastPage || abs($p-$page)<=2;
        $dots = !$show && abs($p-$page)===3;
        if($dots):?><span class="px-1 text-slate-300 text-xs">…</span>
        <?php elseif($show):?>
          <a href="<?= e(drvPageUrl($qp,$p)) ?>"
             class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition
                    <?= $p===$page ? 'bg-cb-primary text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <?= $p ?>
          </a>
        <?php endif?>
      <?php endfor?>
      <?php if($page<$lastPage):?>
        <a href="<?= e(drvPageUrl($qp,$page+1)) ?>"
           class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition">
          <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
      <?php endif?>
    </nav>
  </div>
  <?php endif?>

</div>

<style>[x-cloak]{display:none!important}</style>
<script>
function drvIndex(){return{
  view: localStorage.getItem('drivers-view') || 'grid',
  setView(v){ this.view=v; localStorage.setItem('drivers-view',v); },
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
      if(i===9){const n=parseFloat(v.replace(',','.'));return isNaN(n)?v:n;}
      if(i===10){const n=parseInt(v);return isNaN(n)?v:n;}
      return v;
    }));
    const aoa=[
      ['City Bus ERP — Chauffeurs'],
      ['Exporté le <?= date('d/m/Y') ?> à <?= date('H:i') ?> · Total : <?= $total ?> chauffeur(s)'],
      [''],h,...data
    ];
    const ws=XLSX.utils.aoa_to_sheet(aoa);
    ws['!cols']=[{wch:12},{wch:16},{wch:16},{wch:14},{wch:14},{wch:18},{wch:12},{wch:12},{wch:16},{wch:8},{wch:8}];
    ws['!merges']=[{s:{r:0,c:0},e:{r:0,c:h.length-1}},{s:{r:1,c:0},e:{r:1,c:h.length-1}}];
    const bT=c=>({top:{style:'thin',color:{rgb:c}},bottom:{style:'thin',color:{rgb:c}},left:{style:'thin',color:{rgb:c}},right:{style:'thin',color:{rgb:c}}});
    if(ws['A1'])ws['A1'].s={font:{bold:true,sz:14,color:{rgb:BLEU}}};
    if(ws['A2'])ws['A2'].s={font:{sz:9,color:{rgb:GREY}}};
    h.forEach((_,i)=>{const ref=XLSX.utils.encode_cell({r:3,c:i});if(ws[ref])ws[ref].s={font:{bold:true,color:{rgb:BLANC},sz:9},fill:{fgColor:{rgb:BLEU}},alignment:{horizontal:'center'},border:bT(BLEU)};});
    data.forEach((row,ri)=>{
      const bg=ri%2===0?'FFFFFF':BGLC;
      row.forEach((_,ci)=>{const ref=XLSX.utils.encode_cell({r:ri+4,c:ci});if(ws[ref])ws[ref].s={fill:{fgColor:{rgb:bg}},font:{sz:9},border:bT(BORD)};});
    });
    XLSX.utils.book_append_sheet(wb,ws,'Chauffeurs');
    XLSX.writeFile(wb,'citybus_chauffeurs_<?= date('Ymd') ?>.xlsx');
  },
  exportPdf(){
    if(typeof window.jspdf==='undefined'){alert('Librairie PDF non encore chargée, réessayez.');return}
    const{jsPDF}=window.jspdf;
    const doc=new jsPDF({orientation:'landscape',format:'a4',unit:'mm'});
    const W=doc.internal.pageSize.getWidth(),H=doc.internal.pageSize.getHeight();
    doc.setFillColor(21,101,192);doc.rect(0,0,W,20,'F');
    doc.setTextColor(255,255,255);
    doc.setFontSize(15);doc.setFont('helvetica','bold');doc.text('City Bus ERP',6,9);
    doc.setFontSize(9);doc.setFont('helvetica','normal');doc.text('Chauffeurs — Référentiel',6,16);
    doc.setFontSize(7.5);doc.setTextColor(180,210,255);
    doc.text('Exporté le <?= date('d/m/Y') ?> à <?= date('H:i') ?>',W-5,9,{align:'right'});
    doc.text('<?= $total ?> chauffeur(s)',W-5,16,{align:'right'});
    doc.autoTable({
      html:'#export-data',startY:24,margin:{left:5,right:5},
      styles:{fontSize:7.5,cellPadding:{top:2.5,bottom:2.5,left:3,right:3},lineColor:[226,232,240],lineWidth:0.2},
      headStyles:{fillColor:[21,101,192],textColor:255,fontStyle:'bold',halign:'center',minCellHeight:9},
      alternateRowStyles:{fillColor:[240,244,255]},
      bodyStyles:{textColor:[30,41,59]},
      didDrawPage(){
        const pN=doc.internal.getCurrentPageInfo().pageNumber,pT=doc.internal.getNumberOfPages();
        doc.setFillColor(248,250,252);doc.rect(0,H-8,W,8,'F');
        doc.setFontSize(6.5);doc.setTextColor(148,163,184);doc.setFont('helvetica','normal');
        doc.text('City Bus ERP — Confidentiel',5,H-2.5);
        doc.text('Page '+pN+' / '+pT,W-5,H-2.5,{align:'right'});
      }
    });
    doc.save('citybus_chauffeurs_<?= date('Ymd') ?>.pdf');
  }
}}
</script>
<?php $view->end() ?>
