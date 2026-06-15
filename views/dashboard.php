<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$user = auth();
$flash = \CityBus\Core\Session::pullFlash();
$maxAgencyCa = !empty($byAgency) ? max(max(array_column($byAgency, 'total')), 1) : 1;
?>

<?php $view->start('styles') ?>
<style>
  .s-track { display:flex; transition: transform .55s cubic-bezier(.77,0,.175,1); will-change:transform; }
  .s-slide  { flex: 0 0 100%; min-width: 0; }
  .acc-body  { display:grid; grid-template-rows:0fr; transition: grid-template-rows .4s cubic-bezier(.4,0,.2,1); }
  .acc-body.open { grid-template-rows:1fr; }
  .acc-inner { overflow:hidden; }
  @keyframes progressSlide { from { transform:scaleX(0) } to { transform:scaleX(1) } }
  .slide-progress { transform-origin:left; animation: progressSlide 7s linear forwards; }
  @keyframes growBar { from { width:0 } }
  .grow-bar { animation: growBar .8s ease forwards; }
  .acc-open-indicator { border-left: 3px solid transparent; transition: border-color .3s; }
  .acc-open-indicator.is-open { border-left-color: #F9A825; }
  .diag-yellow { background-image: repeating-linear-gradient(45deg,transparent,transparent 12px,rgba(249,168,37,.07) 12px,rgba(249,168,37,.07) 14px); }
</style>
<?php $view->end() ?>

<?php $view->start('content') ?>
<div class="space-y-5">

  <!-- Titre -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-xl font-black text-slate-900">Tableau de bord</h1>
      <p class="text-xs text-slate-400 mt-0.5 flex items-center gap-1.5">
        <i data-lucide="calendar" class="w-3.5 h-3.5 text-cb-primary"></i>
        <?= e(date_fr(date('Y-m-d'))) ?> · Bienvenue, <strong><?= e($user['first_name'] ?? '') ?></strong>
      </p>
    </div>
    <div class="flex items-center gap-2">
      <div class="flex items-center gap-1.5 text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 px-3 py-1.5 rounded-full font-semibold">
        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
        Synchronisé
      </div>
      <button onclick="location.reload()" class="flex items-center gap-1.5 text-xs text-slate-500 bg-white border border-slate-200 hover:border-cb-primary hover:text-cb-primary px-3 py-1.5 rounded-full transition">
        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Actualiser
      </button>
    </div>
  </div>

  <!-- ▶ V4 Platform Intelligence Strip -->
  <?php $v4 = $v4 ?? []; ?>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    <a href="<?= e(url('ops/gps/map')) ?>"
       class="group flex items-center gap-3 p-3.5 rounded-2xl border transition hover:shadow-md hover:-translate-y-0.5
              <?= ($v4['gps_alerts_open'] ?? 0) > 0 ? 'bg-rose-50 border-rose-200' : 'bg-white border-slate-100' ?>">
      <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0
                  <?= ($v4['gps_alerts_open'] ?? 0) > 0 ? 'bg-rose-100' : 'bg-emerald-100' ?>">
        <i data-lucide="map-pin" class="w-4 h-4 <?= ($v4['gps_alerts_open'] ?? 0) > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"></i>
      </div>
      <div class="min-w-0">
        <p class="text-lg font-black <?= ($v4['gps_alerts_open'] ?? 0) > 0 ? 'text-rose-700' : 'text-slate-900' ?>"><?= e($v4['gps_alerts_open'] ?? 0) ?></p>
        <p class="text-[10px] text-slate-400 font-semibold leading-tight">Alertes GPS</p>
      </div>
    </a>

    <a href="<?= e(url('billetterie/reservations')) ?>"
       class="group flex items-center gap-3 p-3.5 rounded-2xl bg-white border border-slate-100 hover:border-cb-yellow hover:shadow-md hover:-translate-y-0.5 transition">
      <div class="w-9 h-9 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
        <i data-lucide="bookmark" class="w-4 h-4 text-violet-600"></i>
      </div>
      <div class="min-w-0">
        <p class="text-lg font-black text-slate-900"><?= e($v4['pnr_today'] ?? 0) ?></p>
        <p class="text-[10px] text-slate-400 font-semibold leading-tight">PNR aujourd'hui</p>
      </div>
    </a>

    <a href="<?= e(url('cargo')) ?>"
       class="group flex items-center gap-3 p-3.5 rounded-2xl bg-white border border-slate-100 hover:border-cb-yellow hover:shadow-md hover:-translate-y-0.5 transition">
      <div class="w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
        <i data-lucide="package" class="w-4 h-4 text-amber-600"></i>
      </div>
      <div class="min-w-0">
        <p class="text-lg font-black text-slate-900"><?= e($v4['cargo_transit'] ?? 0) ?></p>
        <p class="text-[10px] text-slate-400 font-semibold leading-tight">Colis en transit</p>
      </div>
    </a>

    <a href="<?= e(url('finance/invoices')) ?>"
       class="group flex items-center gap-3 p-3.5 rounded-2xl bg-white border border-slate-100 hover:border-cb-yellow hover:shadow-md hover:-translate-y-0.5 transition">
      <div class="w-9 h-9 rounded-xl bg-sky-100 flex items-center justify-center shrink-0">
        <i data-lucide="file-text" class="w-4 h-4 text-sky-600"></i>
      </div>
      <div class="min-w-0">
        <p class="text-lg font-black text-slate-900"><?= e($v4['invoices_pending'] ?? 0) ?></p>
        <p class="text-[10px] text-slate-400 font-semibold leading-tight">Factures ouvertes</p>
      </div>
    </a>

    <a href="<?= e(url('crm/complaints')) ?>"
       class="group flex items-center gap-3 p-3.5 rounded-2xl bg-white border border-slate-100 hover:border-cb-yellow hover:shadow-md hover:-translate-y-0.5 transition
              <?= ($v4['complaints_open'] ?? 0) > 0 ? '!bg-amber-50 !border-amber-200' : '' ?>">
      <div class="w-9 h-9 rounded-xl bg-orange-100 flex items-center justify-center shrink-0">
        <i data-lucide="message-circle-warning" class="w-4 h-4 text-orange-600"></i>
      </div>
      <div class="min-w-0">
        <p class="text-lg font-black text-slate-900"><?= e($v4['complaints_open'] ?? 0) ?></p>
        <p class="text-[10px] text-slate-400 font-semibold leading-tight">Réclamations</p>
      </div>
    </a>

    <a href="<?= e(url('admin/notifications')) ?>"
       class="group flex items-center gap-3 p-3.5 rounded-2xl bg-white border border-slate-100 hover:border-cb-yellow hover:shadow-md hover:-translate-y-0.5 transition">
      <div class="w-9 h-9 rounded-xl bg-teal-100 flex items-center justify-center shrink-0">
        <i data-lucide="send" class="w-4 h-4 text-teal-600"></i>
      </div>
      <div class="min-w-0">
        <p class="text-lg font-black text-slate-900"><?= e($v4['notif_sent_today'] ?? 0) ?></p>
        <p class="text-[10px] text-slate-400 font-semibold leading-tight">SMS/Email envoyés</p>
      </div>
    </a>
  </div>

  <!-- Layout : SLIDER + ACCORDÉON -->
  <div class="flex flex-col lg:flex-row gap-5">

    <!-- ═══ SLIDER ═══ -->
    <div
      x-data="{
        cur: 0, total: 3, tid: null, key: 0,
        init() { this.startAuto() },
        startAuto() { clearInterval(this.tid); this.tid = setInterval(()=>{ this.go((this.cur+1)%this.total) }, 7000) },
        go(i) { this.cur = i; this.key++; this.startAuto() },
        prev() { this.go((this.cur-1+this.total)%this.total) },
        next() { this.go((this.cur+1)%this.total) },
      }"
      class="flex-1 min-w-0"
    >
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <!-- Onglets + navigation -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
          <div class="flex gap-1">
            <button @click="go(0)" class="text-xs px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
              :class="cur===0 ? 'bg-cb-primary text-white shadow-sm' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700'">
              <i data-lucide="layout-grid" class="w-3 h-3"></i> KPIs
            </button>
            <button @click="go(1)" class="text-xs px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
              :class="cur===1 ? 'bg-cb-primary text-white shadow-sm' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700'">
              <i data-lucide="map-pin" class="w-3 h-3"></i> Agences
            </button>
            <button @click="go(2)" class="text-xs px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
              :class="cur===2 ? 'bg-cb-primary text-white shadow-sm' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700'">
              <i data-lucide="navigation" class="w-3 h-3"></i> Voyages
            </button>
          </div>
          <div class="flex items-center gap-1">
            <button @click="prev()" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-cb-bg hover:text-cb-primary flex items-center justify-center text-slate-400 transition">
              <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
            </button>
            <button @click="next()" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-cb-bg hover:text-cb-primary flex items-center justify-center text-slate-400 transition">
              <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            </button>
          </div>
        </div>

        <!-- Barre de progression -->
        <div class="h-0.5 bg-slate-100 overflow-hidden">
          <div :key="key" class="h-full bg-gradient-to-r from-cb-primary to-cb-accent slide-progress"></div>
        </div>

        <!-- Slides -->
        <div class="overflow-hidden">
          <div class="s-track" :style="'transform:translateX(-' + cur*100 + '%)'">

            <!-- Slide 1 — KPIs -->
            <div class="s-slide px-5 py-4">
              <div class="space-y-3">
                <?php
                  $objJour = 2500000;
                  $pctObj  = $objJour > 0 ? min(round($kpis['ca_jour'] / $objJour * 100), 200) : 0;
                ?>
                <div class="rounded-2xl bg-gradient-to-r from-cb-primary to-cb-secondary diag-yellow p-5 text-white relative overflow-hidden">
                  <div class="absolute right-5 top-2 text-[72px] font-black opacity-[.07] select-none leading-none pointer-events-none">₣</div>
                  <div class="flex items-start justify-between">
                    <div>
                      <p class="text-[10px] text-white/60 uppercase tracking-widest font-semibold">Chiffre d'affaires · Aujourd'hui</p>
                      <p class="text-3xl font-black mt-1"><?= e(number_format($kpis['ca_jour'], 0, ',', ' ')) ?> <span class="text-cb-yellow text-lg font-bold">FCFA</span></p>
                    </div>
                    <div class="bg-white/15 rounded-xl px-3 py-1.5 text-xs font-bold flex items-center gap-1.5 shrink-0">
                      <i data-lucide="trending-up" class="w-3.5 h-3.5 text-cb-yellow"></i>
                      <span class="text-cb-yellow"><?= $pctObj ?>%</span>
                      <span class="opacity-60">obj.</span>
                    </div>
                  </div>
                  <div class="mt-3">
                    <div class="flex justify-between text-[10px] opacity-60 mb-1">
                      <span>Objectif : <?= e(number_format($objJour, 0, ',', ' ')) ?> FCFA</span>
                      <span><?= $pctObj ?>%</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-white/20">
                      <div class="h-full rounded-full bg-cb-yellow" style="width:<?= min($pctObj, 100) ?>%"></div>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                  <div class="rounded-xl border border-slate-100 p-3.5 bg-slate-50 hover:border-cb-yellow hover:shadow-sm transition">
                    <div class="flex items-center gap-2 mb-2">
                      <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center">
                        <i data-lucide="ticket" class="w-3.5 h-3.5 text-amber-600"></i>
                      </div>
                      <span class="text-[10px] text-slate-500 font-semibold">Tickets</span>
                    </div>
                    <p class="text-xl font-black text-slate-900"><?= e(number_format($kpis['tickets_jour'], 0, ',', ' ')) ?></p>
                    <p class="text-[9px] text-slate-400 mt-1">Aujourd'hui</p>
                  </div>
                  <div class="rounded-xl border border-slate-100 p-3.5 bg-slate-50 hover:border-cb-yellow hover:shadow-sm transition">
                    <div class="flex items-center gap-2 mb-2">
                      <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center">
                        <i data-lucide="bus" class="w-3.5 h-3.5 text-emerald-600"></i>
                      </div>
                      <span class="text-[10px] text-slate-500 font-semibold">Flotte</span>
                    </div>
                    <p class="text-xl font-black text-slate-900"><?= e($kpis['bus_dispo']) ?><span class="text-sm text-slate-400 font-normal">/<?= e($kpis['bus_total']) ?></span></p>
                    <p class="text-[9px] text-slate-400 mt-1">Disponibles</p>
                  </div>
                  <div class="rounded-xl border border-slate-100 p-3.5 bg-slate-50 hover:border-cb-yellow hover:shadow-sm transition">
                    <div class="flex items-center gap-2 mb-2">
                      <div class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center">
                        <i data-lucide="route" class="w-3.5 h-3.5 text-violet-600"></i>
                      </div>
                      <span class="text-[10px] text-slate-500 font-semibold">Voyages</span>
                    </div>
                    <p class="text-xl font-black text-slate-900"><?= e($kpis['voyages_jour']) ?></p>
                    <p class="text-[9px] text-slate-400 mt-1"><?= e($kpis['voyages_route']) ?> en route</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Slide 2 — Agences -->
            <div class="s-slide px-5 py-4">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">CA ce mois</p>
                  <h3 class="text-base font-bold text-slate-900 mt-0.5">Performance par agence</h3>
                </div>
                <span class="text-xs bg-cb-bg text-cb-primary font-bold px-3 py-1 rounded-full border border-cb-bg">
                  <?= e(number_format(array_sum(array_column($byAgency, 'total')), 0, ',', ' ')) ?> F
                </span>
              </div>
              <?php if ($byAgency): ?>
              <div class="space-y-2.5">
                <?php foreach ($byAgency as $ag):
                  $pct = round((int)$ag['total'] / $maxAgencyCa * 100);
                ?>
                <div class="flex items-center gap-3">
                  <div class="w-28 shrink-0">
                    <p class="text-xs font-semibold text-slate-700 truncate"><?= e($ag['name']) ?></p>
                    <p class="text-[10px] text-slate-400"><?= e($ag['city']) ?></p>
                  </div>
                  <div class="flex-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-cb-primary to-cb-accent grow-bar" style="width:<?= $pct ?>%"></div>
                  </div>
                  <span class="text-[11px] font-bold text-slate-700 shrink-0 w-24 text-right">
                    <?= e(number_format((int)$ag['total'], 0, ',', ' ')) ?> F
                  </span>
                </div>
                <?php endforeach ?>
              </div>
              <?php else: ?>
                <p class="text-sm text-slate-400 text-center py-6">Aucune donnée ce mois</p>
              <?php endif ?>
            </div>

            <!-- Slide 3 — Voyages actifs -->
            <div class="s-slide px-5 py-4">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Temps réel</p>
                  <h3 class="text-base font-bold text-slate-900 mt-0.5">Voyages du jour</h3>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 px-2.5 py-1 rounded-full font-semibold">
                  <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                  <?= e($kpis['voyages_route']) ?> en route
                </div>
              </div>
              <?php if (!empty($activeTrips)): ?>
              <div class="space-y-2">
                <?php foreach ($activeTrips as $v):
                  $statusCls = match($v['status']) {
                    'en_route'     => 'bg-emerald-100 text-emerald-700',
                    'embarquement' => 'bg-amber-100 text-amber-700',
                    default        => 'bg-slate-100 text-slate-600',
                  };
                  $statusLbl = match($v['status']) {
                    'en_route'     => 'En route',
                    'embarquement' => 'Embarquement',
                    'planifie'     => 'Planifié',
                    default        => ucfirst($v['status']),
                  };
                  $paxPct = $v['capacity'] > 0 ? round($v['pax_count'] / $v['capacity'] * 100) : 0;
                ?>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100 hover:border-cb-yellow hover:shadow-sm transition">
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <span class="font-mono text-[11px] text-cb-primary font-bold">VYG-<?= e($v['id']) ?></span>
                      <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full shrink-0 <?= $statusCls ?>"><?= $statusLbl ?></span>
                    </div>
                    <p class="text-sm font-semibold text-slate-800 mt-0.5"><?= e($v['line_name']) ?></p>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                      <?= e($v['bus_code'] ?? '') ?>
                      <?php if ($v['driver_name']): ?> · <?= e($v['driver_name']) ?><?php endif ?>
                      · Dép. <?= e(substr($v['departure_time'] ?? '', 0, 5)) ?>
                    </p>
                  </div>
                  <div class="text-right shrink-0">
                    <p class="text-sm font-black text-slate-800"><?= e($v['pax_count']) ?>/<?= e($v['capacity']) ?></p>
                    <div class="mt-1 w-16 h-1 rounded-full bg-slate-200">
                      <div class="h-full rounded-full bg-cb-primary" style="width:<?= $paxPct ?>%"></div>
                    </div>
                  </div>
                </div>
                <?php endforeach ?>
              </div>
              <?php else: ?>
              <div class="flex flex-col items-center justify-center py-8 text-slate-400 gap-2">
                <i data-lucide="bus" class="w-10 h-10 opacity-20"></i>
                <p class="text-sm"><?= $kpis['voyages_jour'] > 0 ? e($kpis['voyages_jour']) . ' voyage(s) programmé(s)' : 'Aucun voyage aujourd\'hui' ?></p>
              </div>
              <?php endif ?>
            </div>

            <!-- Slide 4 — CA 7 derniers jours -->
            <div class="s-slide px-5 py-4">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Évolution</p>
                  <h3 class="text-base font-bold text-slate-900 mt-0.5">CA 7 derniers jours</h3>
                </div>
              </div>
              <canvas id="caChart" height="120"></canvas>
            </div>

          </div>
        </div>
        <!-- /slides -->

      </div>
    </div>
    <!-- /SLIDER -->

    <!-- ═══ ACCORDÉON ═══ -->
    <div x-data="{ open: 1 }" class="w-full lg:w-80 xl:w-96 shrink-0">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-bold text-slate-700">Détails du jour</h2>
      </div>

      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden divide-y divide-slate-100">

        <!-- 1 — Résumé -->
        <div class="acc-open-indicator" :class="open===1 ? 'is-open' : ''">
          <button @click="open = open===1 ? null : 1"
            class="w-full flex items-center gap-3 px-4 py-3.5 text-left hover:bg-slate-50 transition">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-all duration-300"
                 :class="open===1 ? 'bg-cb-primary text-white shadow-sm' : 'bg-cb-bg text-cb-primary'">
              <i data-lucide="sun" class="w-4 h-4"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-800" :class="open===1 ? '!text-cb-primary' : ''">Résumé de la journée</p>
              <p class="text-[10px] text-slate-400 mt-0.5"><?= e(date_fr(date('Y-m-d'))) ?></p>
            </div>
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-300 transition-transform shrink-0"
               :class="open===1 ? 'rotate-180 !text-cb-primary' : ''"></i>
          </button>
          <div class="acc-body" :class="open===1 ? 'open' : ''">
            <div class="acc-inner">
              <div class="px-4 pb-4 space-y-2.5">
                <div class="grid grid-cols-2 gap-2">
                  <div class="text-center p-3 rounded-xl bg-cb-bg border border-cb-bg">
                    <p class="text-xl font-black text-cb-primary"><?= e(number_format($kpis['tickets_jour'], 0, ',', ' ')) ?></p>
                    <p class="text-[10px] text-cb-secondary mt-0.5 font-semibold">Tickets</p>
                  </div>
                  <div class="text-center p-3 rounded-xl bg-cb-yellowlt border border-amber-100">
                    <p class="text-xl font-black text-cb-yellowdk"><?= e(round($kpis['ca_jour'] / 1000)) ?>K</p>
                    <p class="text-[10px] text-cb-yellowdk mt-0.5 font-semibold">FCFA</p>
                  </div>
                </div>
                <div class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                  <i data-lucide="navigation" class="w-4 h-4 text-slate-400 shrink-0"></i>
                  <span class="text-xs text-slate-600"><?= e($kpis['voyages_jour']) ?> voyage(s) · <?= e($kpis['voyages_route']) ?> en route</span>
                </div>
                <div class="flex items-center gap-2 p-2.5 rounded-xl bg-emerald-50 border border-emerald-100">
                  <i data-lucide="bus" class="w-4 h-4 text-emerald-500 shrink-0"></i>
                  <span class="text-xs text-emerald-700"><?= e($kpis['bus_dispo']) ?> véhicule(s) dispo · <?= e($kpis['bus_total'] - $kpis['bus_dispo']) ?> hors service</span>
                </div>
                <div class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                  <i data-lucide="users" class="w-4 h-4 text-slate-400 shrink-0"></i>
                  <span class="text-xs text-slate-600"><?= e($kpis['employees']) ?> employés actifs</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 2 — Alertes -->
        <div class="acc-open-indicator" :class="open===2 ? 'is-open' : ''">
          <button @click="open = open===2 ? null : 2"
            class="w-full flex items-center gap-3 px-4 py-3.5 text-left hover:bg-slate-50 transition">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-all duration-300"
                 :class="open===2 ? 'bg-cb-primary text-white shadow-sm' : 'bg-amber-100 text-amber-600'">
              <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-800" :class="open===2 ? '!text-cb-primary' : ''">Alertes système</p>
              <p class="text-[10px] text-slate-400 mt-0.5"><?= count($alerts) ?> alerte(s) active(s)</p>
            </div>
            <?php if ($alerts): ?>
              <span class="text-[10px] bg-cb-primary text-white font-black px-2 py-0.5 rounded-full mr-1 shrink-0"><?= count($alerts) ?></span>
            <?php endif ?>
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-300 transition-transform shrink-0"
               :class="open===2 ? 'rotate-180 !text-cb-primary' : ''"></i>
          </button>
          <div class="acc-body" :class="open===2 ? 'open' : ''">
            <div class="acc-inner">
              <div class="px-4 pb-4 space-y-2">
                <?php if ($alerts): foreach ($alerts as $a):
                  $aCls = match($a['type'] ?? 'warning') {
                    'danger'  => 'bg-cb-bg border-cb-primary/30',
                    'warning' => 'bg-amber-50 border-amber-200',
                    default   => 'bg-slate-50 border-slate-200',
                  };
                  $aDot = match($a['type'] ?? 'warning') {
                    'danger'  => 'bg-cb-primary animate-pulse',
                    'warning' => 'bg-amber-500',
                    default   => 'bg-slate-400',
                  };
                ?>
                <div class="flex items-start gap-2.5 p-3 rounded-xl border <?= $aCls ?>">
                  <div class="w-1.5 h-1.5 rounded-full mt-1 shrink-0 <?= $aDot ?>"></div>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-700"><?= e($a['message']) ?></p>
                  </div>
                </div>
                <?php endforeach; else: ?>
                <div class="text-center py-6 text-slate-400">
                  <i data-lucide="check-circle" class="w-8 h-8 mx-auto opacity-30 mb-2"></i>
                  <p class="text-sm">Aucune alerte</p>
                </div>
                <?php endif ?>
              </div>
            </div>
          </div>
        </div>

        <!-- 3 — Accès rapides -->
        <div class="acc-open-indicator" :class="open===3 ? 'is-open' : ''">
          <button @click="open = open===3 ? null : 3"
            class="w-full flex items-center gap-3 px-4 py-3.5 text-left hover:bg-slate-50 transition">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-all duration-300"
                 :class="open===3 ? 'bg-cb-primary text-white shadow-sm' : 'bg-violet-100 text-violet-600'">
              <i data-lucide="zap" class="w-4 h-4"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-800" :class="open===3 ? '!text-cb-primary' : ''">Accès rapides</p>
              <p class="text-[10px] text-slate-400 mt-0.5">Actions fréquentes</p>
            </div>
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-300 transition-transform shrink-0"
               :class="open===3 ? 'rotate-180 !text-cb-primary' : ''"></i>
          </button>
          <div class="acc-body" :class="open===3 ? 'open' : ''">
            <div class="acc-inner">
              <div class="px-4 pb-4">
                <div class="grid grid-cols-3 gap-2">
                  <a href="<?= e(url('billetterie/select-trip')) ?>" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-cb-bg hover:bg-cb-primary group transition">
                    <i data-lucide="ticket" class="w-5 h-5 text-cb-primary group-hover:text-white transition"></i>
                    <span class="text-[10px] font-bold text-cb-primary group-hover:text-white transition leading-tight text-center">Ticket</span>
                  </a>
                  <a href="<?= e(url('voyages/create')) ?>" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-cb-yellowlt hover:bg-cb-yellow group transition">
                    <i data-lucide="bus" class="w-5 h-5 text-cb-yellowdk group-hover:text-white transition"></i>
                    <span class="text-[10px] font-bold text-cb-yellowdk group-hover:text-white transition leading-tight text-center">Voyage</span>
                  </a>
                  <a href="<?= e(url('caisse')) ?>" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-emerald-50 hover:bg-emerald-600 group transition">
                    <i data-lucide="wallet" class="w-5 h-5 text-emerald-600 group-hover:text-white transition"></i>
                    <span class="text-[10px] font-bold text-emerald-600 group-hover:text-white transition leading-tight text-center">Caisse</span>
                  </a>
                  <a href="<?= e(url('reporting')) ?>" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-violet-50 hover:bg-violet-600 group transition">
                    <i data-lucide="bar-chart-2" class="w-5 h-5 text-violet-600 group-hover:text-white transition"></i>
                    <span class="text-[10px] font-bold text-violet-600 group-hover:text-white transition leading-tight text-center">Rapport</span>
                  </a>
                  <a href="<?= e(url('flotte/maintenance')) ?>" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-slate-100 hover:bg-slate-600 group transition">
                    <i data-lucide="wrench" class="w-5 h-5 text-slate-500 group-hover:text-white transition"></i>
                    <span class="text-[10px] font-bold text-slate-500 group-hover:text-white transition leading-tight text-center">Flotte</span>
                  </a>
                  <a href="<?= e(url('controle')) ?>" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-sky-50 hover:bg-sky-600 group transition">
                    <i data-lucide="scan-line" class="w-5 h-5 text-sky-600 group-hover:text-white transition"></i>
                    <span class="text-[10px] font-bold text-sky-600 group-hover:text-white transition leading-tight text-center">Contrôle</span>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 4 — Flotte -->
        <div class="acc-open-indicator" :class="open===4 ? 'is-open' : ''">
          <button @click="open = open===4 ? null : 4"
            class="w-full flex items-center gap-3 px-4 py-3.5 text-left hover:bg-slate-50 transition">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-all duration-300"
                 :class="open===4 ? 'bg-cb-primary text-white shadow-sm' : 'bg-emerald-100 text-emerald-600'">
              <i data-lucide="bus" class="w-4 h-4"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-800" :class="open===4 ? '!text-cb-primary' : ''">État de la flotte</p>
              <p class="text-[10px] text-slate-400 mt-0.5"><?= e($kpis['bus_dispo']) ?> / <?= e($kpis['bus_total']) ?> disponibles</p>
            </div>
            <span class="text-[10px] bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-full mr-1 shrink-0"><?= e($kpis['bus_dispo']) ?>/<?= e($kpis['bus_total']) ?></span>
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-300 transition-transform shrink-0"
               :class="open===4 ? 'rotate-180 !text-cb-primary' : ''"></i>
          </button>
          <div class="acc-body" :class="open===4 ? 'open' : ''">
            <div class="acc-inner">
              <div class="px-4 pb-4 space-y-2">
                <?php
                  $busEnVoyage    = $kpis['voyages_route'];
                  $busMaintenance = max(0, $kpis['bus_total'] - $kpis['bus_dispo'] - $busEnVoyage);
                  $busFleetPct    = $kpis['bus_total'] > 0 ? round($kpis['bus_dispo'] / $kpis['bus_total'] * 100) : 0;
                ?>
                <div class="grid grid-cols-3 gap-1.5 text-center">
                  <div class="rounded-lg bg-emerald-50 p-2 border border-emerald-100">
                    <p class="text-lg font-black text-emerald-600"><?= e($kpis['bus_dispo']) ?></p>
                    <p class="text-[9px] text-emerald-700 font-semibold">Dispo.</p>
                  </div>
                  <div class="rounded-lg bg-amber-50 p-2 border border-amber-100">
                    <p class="text-lg font-black text-amber-600"><?= e($busEnVoyage) ?></p>
                    <p class="text-[9px] text-amber-700 font-semibold">Voyage</p>
                  </div>
                  <div class="rounded-lg bg-cb-bg p-2 border border-cb-bg">
                    <p class="text-lg font-black text-cb-primary"><?= e($busMaintenance) ?></p>
                    <p class="text-[9px] text-cb-secondary font-semibold">H.S./Maint.</p>
                  </div>
                </div>
                <div>
                  <div class="flex justify-between text-[10px] text-slate-400 mb-1">
                    <span>Taux disponibilité</span><span><?= $busFleetPct ?>%</span>
                  </div>
                  <div class="h-2 rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500 grow-bar" style="width:<?= $busFleetPct ?>%"></div>
                  </div>
                </div>
                <a href="<?= e(url('flotte/maintenance')) ?>" class="flex items-center justify-center gap-2 mt-1 text-xs text-cb-primary hover:underline font-semibold">
                  Voir la flotte complète <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <!-- /ACCORDÉON -->

  </div>
  <!-- /layout -->

  <!-- ▶ Activité récente (tabbed : Tickets | PNR | GPS) -->
  <div x-data="{ tab: 'tickets' }" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <!-- En-tête avec onglets -->
    <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 flex-wrap gap-3">
      <div class="flex items-center gap-1 bg-slate-100 rounded-xl p-1">
        <button @click="tab='tickets'"
          :class="tab==='tickets' ? 'bg-white shadow text-cb-primary font-bold' : 'text-slate-500 hover:text-slate-700'"
          class="px-3 py-1.5 rounded-lg text-xs transition flex items-center gap-1.5">
          <i data-lucide="ticket" class="w-3.5 h-3.5"></i> Tickets
          <span class="font-black ml-0.5"><?= e($kpis['tickets_jour']) ?></span>
        </button>
        <button @click="tab='pnr'"
          :class="tab==='pnr' ? 'bg-white shadow text-violet-600 font-bold' : 'text-slate-500 hover:text-slate-700'"
          class="px-3 py-1.5 rounded-lg text-xs transition flex items-center gap-1.5">
          <i data-lucide="bookmark" class="w-3.5 h-3.5"></i> PNR / Résa
          <span class="font-black ml-0.5"><?= e($v4['pnr_today'] ?? 0) ?></span>
        </button>
        <button @click="tab='gps'"
          :class="tab==='gps' ? 'bg-white shadow font-bold ' + (<?= ($v4['gps_alerts_open'] ?? 0) > 0 ? 'true' : 'false' ?> ? 'text-rose-600' : 'text-emerald-600') : 'text-slate-500 hover:text-slate-700'"
          class="px-3 py-1.5 rounded-lg text-xs transition flex items-center gap-1.5">
          <i data-lucide="map-pin" class="w-3.5 h-3.5"></i> GPS
          <?php if (($v4['gps_alerts_open'] ?? 0) > 0): ?>
            <span class="font-black ml-0.5 text-rose-600"><?= e($v4['gps_alerts_open']) ?></span>
          <?php endif ?>
        </button>
      </div>
      <div class="flex gap-2">
        <div x-show="tab==='tickets'" class="flex items-center gap-2">
          <span class="text-[10px] bg-emerald-50 text-emerald-700 font-bold px-2 py-1 rounded-full border border-emerald-100">
            <?= e(number_format(round($kpis['ca_jour'] / 1000))) ?>K FCFA
          </span>
          <a href="<?= e(url('billetterie')) ?>" class="flex items-center gap-1 text-xs text-cb-primary font-semibold hover:underline">
            Voir tout <i data-lucide="arrow-right" class="w-3 h-3"></i>
          </a>
        </div>
        <div x-show="tab==='pnr'" class="flex items-center gap-2">
          <a href="<?= e(url('billetterie/reservations')) ?>" class="flex items-center gap-1 text-xs text-violet-600 font-semibold hover:underline">
            Gérer les PNR <i data-lucide="arrow-right" class="w-3 h-3"></i>
          </a>
        </div>
        <div x-show="tab==='gps'" class="flex items-center gap-2">
          <a href="<?= e(url('ops/gps/map')) ?>" class="flex items-center gap-1 text-xs text-cb-primary font-semibold hover:underline">
            Ouvrir la carte <i data-lucide="arrow-right" class="w-3 h-3"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- KPI bar -->
    <div class="grid grid-cols-3 divide-x divide-slate-100 bg-slate-50 border-b border-slate-100">
      <div class="px-5 py-2.5 text-center">
        <p class="text-lg font-black text-cb-primary"><?= e(number_format($kpis['tickets_jour'], 0, ',', ' ')) ?></p>
        <p class="text-[10px] text-slate-400">Billets vendus</p>
      </div>
      <div class="px-5 py-2.5 text-center">
        <p class="text-lg font-black text-emerald-600"><?= e(number_format(round($kpis['ca_jour'] / 1000))) ?>K</p>
        <p class="text-[10px] text-slate-400">FCFA encaissés</p>
      </div>
      <div class="px-5 py-2.5 text-center">
        <?php $ticketMoyen = $kpis['tickets_jour'] > 0 ? round($kpis['ca_jour'] / $kpis['tickets_jour']) : 0; ?>
        <p class="text-lg font-black text-amber-600"><?= e(number_format($ticketMoyen, 0, ',', ' ')) ?></p>
        <p class="text-[10px] text-slate-400">Ticket moyen (F)</p>
      </div>
    </div>

    <!-- Tab: Tickets -->
    <div x-show="tab==='tickets'" class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/60 border-b border-slate-100">
          <tr class="text-left text-[10px] text-slate-400 uppercase tracking-wider">
            <th class="px-5 py-3 font-semibold">N° Ticket</th>
            <th class="px-5 py-3 font-semibold">Passager</th>
            <th class="px-5 py-3 font-semibold">Ligne</th>
            <th class="px-5 py-3 font-semibold text-right">Prix FCFA</th>
            <th class="px-5 py-3 font-semibold">Vendu</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php if ($recentSales): foreach ($recentSales as $s): ?>
          <tr class="hover:bg-cb-bg/30 transition">
            <td class="px-5 py-3 font-mono text-xs text-cb-primary font-bold"><?= e($s['ticket_number']) ?></td>
            <td class="px-5 py-3 font-medium text-slate-700"><?= e($s['passenger_name']) ?></td>
            <td class="px-5 py-3 text-slate-600"><?= e($s['line_name']) ?></td>
            <td class="px-5 py-3 text-right font-black text-slate-900"><?= e(number_format((int)$s['price_fcfa'], 0, ',', ' ')) ?></td>
            <td class="px-5 py-3 text-slate-400 text-xs"><?= e(date('d/m H:i', strtotime($s['sold_at']))) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Aucune vente récente</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>

    <!-- Tab: PNR -->
    <div x-show="tab==='pnr'" x-cloak class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/60 border-b border-slate-100">
          <tr class="text-left text-[10px] text-slate-400 uppercase tracking-wider">
            <th class="px-5 py-3 font-semibold">Code PNR</th>
            <th class="px-5 py-3 font-semibold">Contact</th>
            <th class="px-5 py-3 font-semibold text-center">Pax</th>
            <th class="px-5 py-3 font-semibold text-right">Montant</th>
            <th class="px-5 py-3 font-semibold">Statut</th>
            <th class="px-5 py-3 font-semibold">Créé</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php $recentPnr = $recentPnr ?? []; if ($recentPnr): foreach ($recentPnr as $p):
            $pnrCls = match($p['status'] ?? '') {
              'confirmed','ticketed' => 'bg-emerald-100 text-emerald-700',
              'pending'              => 'bg-amber-100 text-amber-700',
              'cancelled'            => 'bg-rose-100 text-rose-700',
              default                => 'bg-slate-100 text-slate-600',
            };
            $pnrLbl = match($p['status'] ?? '') {
              'confirmed' => 'Confirmé', 'ticketed' => 'Ticketé',
              'pending'   => 'En attente', 'cancelled' => 'Annulé', default => ucfirst($p['status'] ?? ''),
            };
          ?>
          <tr class="hover:bg-violet-50/30 transition">
            <td class="px-5 py-3">
              <a href="<?= e(url('billetterie/reservations/' . urlencode($p['pnr_code']))) ?>"
                 class="font-mono text-xs text-violet-700 font-bold hover:underline"><?= e($p['pnr_code']) ?></a>
            </td>
            <td class="px-5 py-3">
              <p class="font-medium text-slate-700 text-xs"><?= e($p['contact_name']) ?></p>
              <p class="text-[10px] text-slate-400"><?= e($p['contact_phone']) ?></p>
            </td>
            <td class="px-5 py-3 text-center font-bold text-slate-700"><?= e($p['pax_count']) ?></td>
            <td class="px-5 py-3 text-right font-black text-slate-900 text-xs"><?= e(number_format((int)$p['total_ttc'], 0, ',', ' ')) ?> F</td>
            <td class="px-5 py-3"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $pnrCls ?>"><?= $pnrLbl ?></span></td>
            <td class="px-5 py-3 text-slate-400 text-xs"><?= e(date('d/m H:i', strtotime($p['created_at']))) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">
            Aucune réservation PNR récente ·
            <a href="<?= e(url('billetterie/reservations')) ?>" class="text-violet-600 hover:underline font-semibold">Gérer les PNR</a>
          </td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>

    <!-- Tab: GPS Alerts -->
    <div x-show="tab==='gps'" x-cloak class="p-5">
      <?php if (($v4['gps_alerts_open'] ?? 0) > 0): ?>
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2 text-rose-700">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            <span class="text-sm font-bold"><?= e($v4['gps_alerts_open']) ?> alerte(s) GPS ouverte(s)</span>
          </div>
          <a href="<?= e(url('ops/gps/map')) ?>" class="text-xs text-cb-primary font-semibold hover:underline flex items-center gap-1">
            Voir la carte <i data-lucide="arrow-right" class="w-3 h-3"></i>
          </a>
        </div>
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800 flex items-start gap-3">
          <i data-lucide="map-pin" class="w-5 h-5 shrink-0 mt-0.5"></i>
          <div>
            <p class="font-bold">Alertes en attente d'acquittement</p>
            <p class="text-xs mt-1 opacity-75">Rendez-vous sur la carte GPS ou dans le module ops/gps pour voir le détail et acquitter les alertes.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="flex flex-col items-center justify-center py-8 text-slate-400 gap-3">
          <div class="w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center">
            <i data-lucide="map-pin" class="w-7 h-7 text-emerald-500"></i>
          </div>
          <div class="text-center">
            <p class="font-bold text-emerald-700">Flotte sans alerte GPS</p>
            <p class="text-xs mt-1">Tous les véhicules signalent normalement.</p>
          </div>
          <a href="<?= e(url('ops/gps/map')) ?>" class="flex items-center gap-2 text-xs bg-emerald-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-emerald-700 transition">
            <i data-lucide="map" class="w-3.5 h-3.5"></i> Ouvrir la carte GPS
          </a>
        </div>
      <?php endif ?>
    </div>
  </div>

</div>
<?php $view->end() ?>

<?php $view->start('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function() {
  const raw = <?= json_encode($chart ?? []) ?>;
  // Remplir les 7 derniers jours (avec 0 si absent)
  const labels = [], data = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date(); d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    const match = raw.find(r => r.d === key);
    labels.push(key.slice(5)); // MM-DD
    data.push(match ? parseInt(match.total) : 0);
  }
  const ctx = document.getElementById('caChart');
  if (ctx) {
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{ label: 'CA (FCFA)', data, backgroundColor: '#4F46E5', borderRadius: 6 }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { ticks: { callback: v => v.toLocaleString() } } }
      }
    });
  }
})();
</script>
<?php $view->end() ?>
