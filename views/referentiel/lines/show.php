<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Line;
use CityBus\Models\Tariff;
$view->extends('layouts/app');

$status = Line::statusOf($line);
$sc     = Line::statusClass($status);
$alertN = count(array_filter($alerts, fn($a) => $a['level'] === 'danger'));
$warnN  = count(array_filter($alerts, fn($a) => $a['level'] === 'warn'));

// Statuts voyages — couleurs
$tripStatusCls = [
    'planifie'     => 'bg-slate-100 text-slate-600',
    'embarquement' => 'bg-amber-50 text-amber-700',
    'en_route'     => 'bg-blue-50 text-blue-700',
    'arrive'       => 'bg-emerald-50 text-emerald-700',
    'cloture'      => 'bg-emerald-50 text-emerald-700',
    'annule'       => 'bg-rose-50 text-rose-700',
];
$tripStatusLabel = [
    'planifie'     => 'Planifié',
    'embarquement' => 'Embarquement',
    'en_route'     => 'En route',
    'arrive'       => 'Arrivé',
    'cloture'      => 'Clôturé',
    'annule'       => 'Annulé',
];

// Calculs dérivés
$ticketTotal   = (int)($ticketStats['total_n']     ?? 0);
$ticketCancels = (int)($ticketStats['cancelled_n'] ?? 0);
$passengers    = (int)($ticketStats['passengers_n'] ?? 0);
$cancelRate    = $ticketTotal > 0 ? round($ticketCancels / $ticketTotal * 100, 1) : 0;
$tariffActifs  = count(array_filter($tariffs, fn($t) => !empty($t['is_active'])));
?>
<?php $view->start('content') ?>
<div class="space-y-6">

  <!-- ─── En-tête ─────────────────────────────────────────────────────────── -->
  <div class="flex items-center gap-4 flex-wrap">
    <a href="<?= e(url('referentiel/lines')) ?>"
       class="text-slate-500 hover:text-cb-primary p-2 rounded-lg hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-2xl font-bold text-slate-900"><?= e($line['name']) ?></h1>
        <span class="font-mono text-xs px-2.5 py-1 bg-cb-bg text-cb-primary rounded-full"><?= e($line['code']) ?></span>
        <?php
          $lt = $line['line_type'] ?? 'interurbain';
          $ltCls  = Line::LINE_TYPE_COLORS[$lt] ?? 'bg-slate-50 text-slate-700 border-slate-200';
          $ltIcon = Line::LINE_TYPE_ICONS[$lt] ?? 'route';
          $ltLabel = Line::LINE_TYPES[$lt] ?? $lt;
        ?>
        <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-semibold border <?= $ltCls ?>">
          <i data-lucide="<?= $ltIcon ?>" class="w-3 h-3"></i>
          <?= e($ltLabel) ?>
        </span>
        <span class="text-xs px-2.5 py-1 rounded-full font-semibold border <?= $sc ?>">
          <?= e(Line::STATUSES[$status]) ?>
        </span>
      </div>
      <p class="text-sm text-slate-500 mt-0.5 flex items-center gap-1.5">
        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-cb-primary"></i>
        <?= e(Line::tripLabel($line)) ?>
        <?php if ($lt === 'urbain' && !empty($line['city_name'])): ?>
          <span class="text-slate-400 mx-1">·</span>
          <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-400"></i>
          <span class="text-slate-500"><?= e($line['city_name']) ?></span>
        <?php endif ?>
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= e(url('referentiel/lines/'.$line['id'].'/edit')) ?>"
         class="px-4 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-2">
        <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
      </a>
      <form method="post" action="<?= e(url('referentiel/lines/'.$line['id'].'/delete')) ?>"
            onsubmit="return confirm('Supprimer la ligne <?= e(addslashes($line['code'])) ?> ? Cette action est irréversible.')">
        <?= csrf_field() ?>
        <button type="submit"
                class="px-4 py-2.5 rounded-xl border border-rose-200 text-rose-600 text-sm font-semibold hover:bg-rose-50 transition flex items-center gap-2">
          <i data-lucide="trash-2" class="w-4 h-4"></i>
        </button>
      </form>
    </div>
  </div>

  <!-- ─── Alertes ──────────────────────────────────────────────────────────── -->
  <?php if (!empty($alerts)): ?>
  <div class="space-y-2">
    <?php foreach ($alerts as $a):
      $cls = match ($a['level']) {
        'danger' => 'bg-rose-50 border-rose-200 text-rose-800',
        'warn'   => 'bg-amber-50 border-amber-200 text-amber-800',
        default  => 'bg-blue-50 border-blue-200 text-blue-800',
      };
    ?>
      <div class="flex items-center gap-3 px-4 py-3 rounded-xl border <?= $cls ?> text-sm">
        <i data-lucide="<?= e($a['icon']) ?>" class="w-4 h-4 shrink-0"></i>
        <div class="flex-1">
          <span class="font-semibold"><?= e($a['label']) ?></span>
          <span class="opacity-75 ml-2"><?= e($a['detail']) ?></span>
        </div>
      </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <!-- ─── Hero — bannière + stats rapides ─────────────────────────────────── -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="w-full h-32 bg-gradient-to-br from-cb-primary to-blue-800 flex items-center justify-center relative px-6">
      <div class="absolute inset-0 opacity-10 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCI+PHBhdGggZD0iTTAgMzAgUTMwIDAgNjAgMzAgUTMwIDYwIDAgMzAiIGZpbGw9Im5vbmUiIHN0cm9rZT0id2hpdGUiIHN0cm9rZS13aWR0aD0iMSIvPjwvc3ZnPg==')]"></div>
      <div class="relative flex items-center gap-4">
        <span class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center">
          <i data-lucide="map" class="w-8 h-8 text-white"></i>
        </span>
        <div>
          <div class="text-2xl font-bold text-white font-mono"><?= e($line['code']) ?></div>
          <div class="text-blue-100 text-sm mt-0.5"><?= e(Line::tripLabel($line)) ?></div>
        </div>
      </div>
      <?php if ($alertN > 0): ?>
        <span class="absolute top-3 right-3 inline-flex items-center gap-1 px-3 py-1 bg-rose-600 text-white text-xs font-bold rounded-full shadow-lg">
          <i data-lucide="alert-triangle" class="w-3 h-3"></i> <?= $alertN ?> alerte(s)
        </span>
      <?php elseif ($warnN > 0): ?>
        <span class="absolute top-3 right-3 inline-flex items-center gap-1 px-3 py-1 bg-amber-500 text-white text-xs font-bold rounded-full shadow-lg">
          <i data-lucide="alert-circle" class="w-3 h-3"></i> <?= $warnN ?> avertissement(s)
        </span>
      <?php endif ?>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 divide-x divide-slate-100 text-center">
      <div class="py-4 px-3">
        <div class="text-2xl font-bold text-cb-primary"><?= e($line['distance_km'] ?? '—') ?></div>
        <div class="text-xs text-slate-400 mt-0.5">km</div>
      </div>
      <div class="py-4 px-3">
        <div class="text-2xl font-bold text-slate-800"><?= e($line['duration_hours'] ?? '—') ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Heures</div>
      </div>
      <div class="py-4 px-3">
        <div class="text-2xl font-bold text-slate-800"><?= count($stops) ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Arrêts</div>
      </div>
      <div class="py-4 px-3">
        <div class="text-2xl font-bold text-slate-800"><?= (int)$stats['trips_total'] ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Voyages</div>
      </div>
      <div class="py-4 px-3">
        <div class="text-2xl font-bold text-slate-800"><?= (int)$stats['trips_30d'] ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Voyages 30j</div>
      </div>
      <div class="py-4 px-3">
        <div class="text-2xl font-bold text-slate-800"><?= $passengers ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Passagers</div>
      </div>
      <div class="py-4 px-3 sm:col-span-2 lg:col-span-1">
        <div class="text-xl font-bold text-emerald-600"><?= e(fcfa($stats['revenue'])) ?></div>
        <div class="text-xs text-slate-400 mt-0.5">FCFA encaissés</div>
      </div>
      <div class="py-4 px-3 hidden lg:block">
        <div class="text-2xl font-bold text-cb-primary"><?= $tariffActifs ?></div>
        <div class="text-xs text-slate-400 mt-0.5">Tarifs actifs</div>
      </div>
    </div>
  </div>

  <!-- ─── Cartes d'information en 2 colonnes ──────────────────────────────── -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Identification -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3">
        <i data-lucide="info" class="w-4 h-4 text-cb-primary"></i> Identification
      </h2>
      <?php
      $rows = [
        ['Code',          $line['code']],
        ['Nom',           $line['name']],
        ['Ville départ',  $line['departure_city_name'] ?? ($line['departure_city'] ?? '')],
        ['Ville arrivée', $line['arrival_city_name']   ?? ($line['arrival_city']   ?? '')],
        ['Distance',      !empty($line['distance_km'])    ? $line['distance_km'].' km'   : null],
        ['Durée estimée', !empty($line['duration_hours']) ? $line['duration_hours'].' h' : null],
        ['Créé le',       !empty($line['created_at'])  ? date('d/m/Y', strtotime($line['created_at']))  : null],
        ['Modifié le',    !empty($line['updated_at'])  ? date('d/m/Y', strtotime($line['updated_at']))  : null],
      ];
      foreach ($rows as [$label, $val]):
        if ($val === null || $val === '') continue;
      ?>
        <div class="flex justify-between text-sm gap-3">
          <span class="text-slate-400 shrink-0"><?= $label ?></span>
          <span class="font-medium text-slate-800 text-right"><?= e($val) ?></span>
        </div>
      <?php endforeach ?>
    </div>

    <!-- Activité -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3">
        <i data-lucide="activity" class="w-4 h-4 text-cb-primary"></i> Activité
      </h2>
      <?php
      $actRows = [
        ['Total voyages',      $stats['trips_total']],
        ['Voyages clôturés',   $stats['trips_done']],
        ['Voyages actifs',     $stats['trips_active']],
        ['Voyages (30j)',      $stats['trips_30d']],
        ['Bus distincts',      $stats['buses_used']],
        ['Chauffeurs distincts',$stats['drivers_used']],
        ['Tickets émis',       $ticketTotal],
        ['Passagers (directs)', $passengers],
        ['Tickets annulés',    $ticketCancels . ($cancelRate > 0 ? ' ('.$cancelRate.' %)' : '')],
      ];
      foreach ($actRows as [$label, $val]):
        if ($val === null || $val === '' || $val == 0) continue;
      ?>
        <div class="flex justify-between text-sm gap-3">
          <span class="text-slate-400 shrink-0"><?= $label ?></span>
          <span class="font-medium text-slate-800"><?= e($val) ?></span>
        </div>
      <?php endforeach ?>
    </div>

    <!-- Performance financière -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3">
        <i data-lucide="banknote" class="w-4 h-4 text-cb-primary"></i> Performance financière
      </h2>
      <div class="flex justify-between text-sm">
        <span class="text-slate-400">Revenus confirmés (valide / embarqué)</span>
        <span class="font-bold text-emerald-600"><?= e(fcfa($stats['revenue'])) ?></span>
      </div>
      <div class="flex justify-between text-sm">
        <span class="text-slate-400">Revenus bruts (hors annulés)</span>
        <span class="font-medium text-slate-800"><?= e(fcfa((int)($ticketStats['revenue_gross'] ?? 0))) ?></span>
      </div>
      <?php if (!empty($revenueByType)): ?>
        <div class="pt-2 border-t border-slate-100">
          <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Répartition par type</div>
          <?php foreach ($revenueByType as $r):
            $tSlug = $r['ticket_type'] ?? '';
            $tCls = Tariff::typeColors()[$tSlug] ?? 'bg-slate-50 text-slate-600 border-slate-200';
            $tIco = Tariff::typeIcons()[$tSlug]  ?? 'tag';
          ?>
            <div class="flex items-center justify-between py-1.5 text-sm">
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded border text-xs font-medium <?= $tCls ?>">
                <i data-lucide="<?= e($tIco) ?>" class="w-3 h-3"></i>
                <?= e(Tariff::types()[$tSlug] ?? $tSlug) ?>
              </span>
              <div class="flex items-center gap-3">
                <span class="text-slate-400 text-xs"><?= (int)$r['count_n'] ?> ticket(s)</span>
                <span class="font-bold text-cb-primary"><?= e(fcfa((int)$r['revenue'])) ?></span>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      <?php else: ?>
        <p class="text-sm text-slate-400">Aucun ticket émis sur cette ligne.</p>
      <?php endif ?>
    </div>

    <!-- Prochain voyage -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3">
        <i data-lucide="calendar-clock" class="w-4 h-4 text-cb-primary"></i> Prochain voyage
      </h2>
      <?php if ($nextTrip): ?>
        <div class="flex flex-col gap-3">
          <div class="flex items-center gap-3 p-3 rounded-xl bg-cb-bg">
            <div class="w-10 h-10 rounded-xl bg-cb-primary text-white flex items-center justify-center shrink-0">
              <i data-lucide="bus" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-bold text-slate-800 text-sm"><?= e(date('d/m/Y', strtotime($nextTrip['trip_date']))) ?> <span class="text-cb-primary"><?= e(substr($nextTrip['departure_scheduled'],0,5)) ?></span></div>
              <?php if (!empty($nextTrip['trip_code'])): ?>
                <div class="text-xs font-mono text-slate-500"><?= e($nextTrip['trip_code']) ?></div>
              <?php endif ?>
            </div>
            <span class="px-2 py-0.5 rounded-full text-xs <?= $tripStatusCls[$nextTrip['status']] ?? 'bg-slate-100' ?>"><?= e($tripStatusLabel[$nextTrip['status']] ?? $nextTrip['status']) ?></span>
          </div>
          <div class="space-y-2">
            <?php if (!empty($nextTrip['bus_code'])): ?>
              <div class="flex justify-between text-sm">
                <span class="text-slate-400">Bus</span>
                <span class="font-mono text-xs font-bold text-cb-primary"><?= e($nextTrip['bus_code']) ?> <span class="text-slate-500 font-normal"><?= e($nextTrip['bus_plate'] ?? '') ?></span></span>
              </div>
            <?php endif ?>
            <?php if (!empty($nextTrip['driver_name'])): ?>
              <div class="flex justify-between text-sm">
                <span class="text-slate-400">Chauffeur</span>
                <span class="font-medium text-slate-800"><?= e($nextTrip['driver_name']) ?></span>
              </div>
            <?php endif ?>
            <div class="flex justify-between text-sm">
              <span class="text-slate-400">Dans</span>
              <?php
                $diffDays = (int)floor((strtotime($nextTrip['trip_date']) - strtotime(date('Y-m-d'))) / 86400);
                if ($diffDays === 0) $diffLabel = '<span class="font-bold text-emerald-600">Aujourd\'hui</span>';
                elseif ($diffDays === 1) $diffLabel = '<span class="font-bold text-blue-600">Demain</span>';
                else $diffLabel = '<span class="font-medium text-slate-800">'.$diffDays.' jour(s)</span>';
              ?>
              <span><?= $diffLabel ?></span>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="py-6 text-center">
          <i data-lucide="calendar-x" class="w-10 h-10 mx-auto text-slate-200 mb-2"></i>
          <p class="text-sm text-slate-400">Aucun voyage à venir planifié.</p>
          <a href="<?= e(url('voyages/create')) ?>" class="mt-2 inline-flex items-center gap-1.5 text-xs text-cb-primary hover:underline">
            <i data-lucide="plus" class="w-3 h-3"></i> Planifier un voyage
          </a>
        </div>
      <?php endif ?>
    </div>

    <!-- Top bus utilisés -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3">
        <i data-lucide="bus" class="w-4 h-4 text-cb-primary"></i> Bus les plus actifs
      </h2>
      <?php if (!empty($topBuses)): ?>
        <div class="space-y-2">
          <?php $maxB = max(array_column($topBuses, 'trips_n')); ?>
          <?php foreach ($topBuses as $i => $b): $pct = $maxB > 0 ? round($b['trips_n'] / $maxB * 100) : 0; ?>
            <a href="<?= e(url('referentiel/vehicules/'.$b['id'])) ?>" class="flex items-center gap-3 p-2 rounded-xl hover:bg-cb-bg/50 transition group">
              <span class="w-7 h-7 rounded-lg bg-cb-bg text-cb-primary flex items-center justify-center font-bold text-xs shrink-0"><?= $i+1 ?></span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between text-sm">
                  <span class="font-mono font-bold text-cb-primary group-hover:underline"><?= e($b['code']) ?></span>
                  <span class="text-xs text-slate-500"><?= (int)$b['trips_n'] ?> voyage(s)</span>
                </div>
                <div class="text-xs text-slate-400 truncate"><?= e($b['plate'] ?? '') ?> <?= e(!empty($b['brand']) ? '· '.$b['brand'].' '.$b['model'] : '') ?></div>
                <div class="mt-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-cb-primary rounded-full" style="width:<?= $pct ?>%"></div></div>
              </div>
            </a>
          <?php endforeach ?>
        </div>
      <?php else: ?>
        <div class="py-6 text-center">
          <i data-lucide="bus" class="w-8 h-8 mx-auto text-slate-200 mb-1"></i>
          <p class="text-xs text-slate-400">Aucun voyage enregistré.</p>
        </div>
      <?php endif ?>
    </div>

    <!-- Top chauffeurs -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3">
        <i data-lucide="user-check" class="w-4 h-4 text-cb-primary"></i> Chauffeurs les plus actifs
      </h2>
      <?php if (!empty($topDrivers)): ?>
        <div class="space-y-2">
          <?php $maxD = max(array_column($topDrivers, 'trips_n')); ?>
          <?php foreach ($topDrivers as $i => $d): $pct = $maxD > 0 ? round($d['trips_n'] / $maxD * 100) : 0; ?>
            <a href="<?= e(url('referentiel/drivers/'.$d['id'])) ?>" class="flex items-center gap-3 p-2 rounded-xl hover:bg-cb-bg/50 transition group">
              <div class="w-7 h-7 rounded-full bg-cb-primary text-white flex items-center justify-center font-bold text-xs shrink-0">
                <?= strtoupper(substr($d['first_name'],0,1).substr($d['last_name'],0,1)) ?>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between text-sm">
                  <span class="font-medium text-slate-800 group-hover:text-cb-primary truncate"><?= e($d['last_name']) ?> <?= e($d['first_name']) ?></span>
                  <span class="text-xs text-slate-500 shrink-0"><?= (int)$d['trips_n'] ?> voyage(s)</span>
                </div>
                <div class="text-xs text-slate-400"><?= e($d['matricule'] ?? '') ?></div>
                <div class="mt-1 h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-500 rounded-full" style="width:<?= $pct ?>%"></div></div>
              </div>
            </a>
          <?php endforeach ?>
        </div>
      <?php else: ?>
        <div class="py-6 text-center">
          <i data-lucide="user-x" class="w-8 h-8 mx-auto text-slate-200 mb-1"></i>
          <p class="text-xs text-slate-400">Aucun chauffeur enregistré.</p>
        </div>
      <?php endif ?>
    </div>
  </div>

  <!-- ─── Arrêts ────────────────────────────────────────────────────────────── -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
      <h3 class="font-semibold flex items-center gap-2">
        <i data-lucide="map-pin" class="w-4 h-4 text-cb-primary"></i>
        Arrêts intermédiaires
        <span class="text-slate-400 text-sm font-normal">(<?= count($stops) ?>)</span>
      </h3>
    </div>
    <?php if (empty($stops)): ?>
      <div class="p-10 text-center">
        <i data-lucide="map-pin-off" class="w-10 h-10 mx-auto text-slate-200 mb-2"></i>
        <p class="text-sm text-slate-400">Aucun arrêt enregistré pour cette ligne.</p>
      </div>
    <?php else: ?>
      <!-- Visualisation timeline -->
      <div class="px-6 py-4">
        <div class="flex items-center gap-2 mb-4">
          <span class="w-3 h-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100 shrink-0"></span>
          <span class="text-sm font-medium text-slate-700"><?= e($line['departure_city_name'] ?? $line['departure_city']) ?></span>
          <span class="text-xs text-slate-400">(Départ)</span>
        </div>
        <div class="border-l-2 border-dashed border-slate-200 ml-1.5 pl-5 space-y-1">
          <?php foreach ($stops as $stopIdx => $s): ?>
            <div class="relative flex items-center gap-3 py-2 hover:bg-cb-bg/30 rounded-xl px-2 transition -ml-5 pl-7 group">
              <span class="absolute left-0 w-3 h-3 rounded-full bg-cb-primary border-2 border-white ring-2 ring-cb-primary/30 group-hover:ring-cb-primary/60 transition"></span>
              <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-xs flex items-center justify-center font-bold shrink-0"><?= $stopIdx + 1 ?></span>
              <div class="flex-1 min-w-0">
                <div class="font-medium text-slate-800 text-sm"><?= e($s['name']) ?></div>
                <?php if (!empty($s['agency_name'])): ?>
                  <div class="text-xs text-slate-500 flex items-center gap-1">
                    <i data-lucide="building-2" class="w-3 h-3"></i><?= e($s['agency_name']) ?>
                  </div>
                <?php endif ?>
              </div>
              <span class="text-xs font-mono text-slate-500 shrink-0">
                <?= !empty($s['km_from_origin']) ? e($s['km_from_origin']).' km' : '—' ?>
              </span>
            </div>
          <?php endforeach ?>
        </div>
        <div class="flex items-center gap-2 mt-4">
          <span class="w-3 h-3 rounded-full bg-rose-500 ring-4 ring-rose-100 shrink-0"></span>
          <span class="text-sm font-medium text-slate-700"><?= e($line['arrival_city_name'] ?? $line['arrival_city']) ?></span>
          <span class="text-xs text-slate-400">(Arrivée)</span>
          <?php if (!empty($line['distance_km'])): ?>
            <span class="ml-auto text-xs text-slate-400"><?= e($line['distance_km']) ?> km total</span>
          <?php endif ?>
        </div>
      </div>
    <?php endif ?>
  </div>

  <!-- ─── Tarifs ────────────────────────────────────────────────────────────── -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
      <h3 class="font-semibold flex items-center gap-2">
        <i data-lucide="tags" class="w-4 h-4 text-cb-primary"></i>
        Grille tarifaire
        <span class="text-slate-400 text-sm font-normal">(<?= count($tariffs) ?> tarif(s))</span>
      </h3>
      <a href="<?= e(url('referentiel/tariffs/create')) ?>?line_id=<?= (int)$line['id'] ?>"
         class="text-xs px-3 py-1.5 rounded-lg bg-cb-bg text-cb-primary font-medium hover:bg-cb-primary hover:text-white transition inline-flex items-center gap-1.5">
        <i data-lucide="plus" class="w-3 h-3"></i> Ajouter un tarif
      </a>
    </div>
    <?php if (empty($tariffs)): ?>
      <div class="p-10 text-center">
        <i data-lucide="tag" class="w-10 h-10 mx-auto text-slate-200 mb-2"></i>
        <p class="text-sm text-slate-400">Aucun tarif défini pour cette ligne.</p>
      </div>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
          <tr>
            <th class="px-5 py-3 text-left">Type</th>
            <th class="px-5 py-3 text-right">Prix</th>
            <th class="px-5 py-3 text-center">Actif</th>
            <th class="px-5 py-3 text-right">Tickets émis</th>
            <th class="px-5 py-3 text-right">Revenus</th>
            <th class="px-5 py-3 text-right"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php
          // Indexer revenueByType par ticket_type pour lookup rapide
          $revIdx = [];
          foreach ($revenueByType as $r) $revIdx[$r['ticket_type']] = $r;
          foreach ($tariffs as $t):
            $tSlug = $t['ticket_type'] ?? '';
            $tCls  = Tariff::typeColors()[$tSlug] ?? 'bg-slate-50 text-slate-600 border-slate-200';
            $tIco  = Tariff::typeIcons()[$tSlug]  ?? 'tag';
            $tRev  = $revIdx[$t['ticket_type']] ?? null;
          ?>
            <tr class="hover:bg-cb-bg/30 transition">
              <td class="px-5 py-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-medium <?= $tCls ?>">
                  <i data-lucide="<?= e($tIco) ?>" class="w-3 h-3"></i>
                  <?= e(Tariff::types()[$tSlug] ?? $tSlug) ?>
                </span>
              </td>
              <td class="px-5 py-3 text-right font-bold text-cb-primary"><?= e(fcfa((int)$t['price_fcfa'])) ?></td>
              <td class="px-5 py-3 text-center">
                <?= (int)$t['is_active'] === 1
                    ? '<span class="text-emerald-500"><i data-lucide="check-circle" class="w-4 h-4 inline"></i></span>'
                    : '<span class="text-slate-300"><i data-lucide="x-circle" class="w-4 h-4 inline"></i></span>' ?>
              </td>
              <td class="px-5 py-3 text-right text-slate-600">
                <?= $tRev ? (int)$tRev['count_n'].' ticket(s)' : '—' ?>
              </td>
              <td class="px-5 py-3 text-right font-semibold text-emerald-600">
                <?= $tRev ? e(fcfa((int)$tRev['revenue'])) : '—' ?>
              </td>
              <td class="px-5 py-3 text-right">
                <a href="<?= e(url('referentiel/tariffs/'.$t['id'].'/edit')) ?>" class="text-cb-primary hover:underline text-xs">Modifier</a>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    <?php endif ?>
  </div>

  <!-- ─── Voyages récents ───────────────────────────────────────────────────── -->
  <?php if (!empty($recentTrips)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
    <h2 class="font-semibold text-slate-700 flex items-center gap-2 mb-4">
      <i data-lucide="route" class="w-4 h-4 text-cb-primary"></i>
      Voyages récents
      <span class="text-xs bg-cb-bg text-cb-primary px-2 py-0.5 rounded-full ml-1"><?= count($recentTrips) ?></span>
    </h2>
    <div class="overflow-x-auto -mx-5">
      <table class="w-full text-sm">
        <thead class="text-xs uppercase text-slate-400 bg-slate-50">
          <tr>
            <th class="px-5 py-2 text-left">Date</th>
            <th class="px-5 py-2 text-left">Heure départ</th>
            <th class="px-5 py-2 text-left">Bus</th>
            <th class="px-5 py-2 text-left">Immatriculation</th>
            <th class="px-5 py-2 text-left">Chauffeur</th>
            <th class="px-5 py-2 text-left">Statut</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($recentTrips as $tr): ?>
            <tr class="hover:bg-slate-50 transition">
              <td class="px-5 py-2.5 whitespace-nowrap font-medium"><?= e(date('d/m/Y', strtotime($tr['trip_date']))) ?></td>
              <td class="px-5 py-2.5 text-slate-500"><?= e(substr($tr['departure_scheduled'] ?? '', 0, 5)) ?></td>
              <td class="px-5 py-2.5"><span class="font-mono text-xs text-cb-primary font-bold"><?= e($tr['bus_code'] ?? '—') ?></span></td>
              <td class="px-5 py-2.5 text-slate-500 text-xs"><?= e($tr['bus_plate'] ?? '—') ?></td>
              <td class="px-5 py-2.5 text-slate-700"><?= e($tr['driver_name'] ?? '—') ?></td>
              <td class="px-5 py-2.5">
                <span class="px-2 py-0.5 rounded-full text-xs <?= $tripStatusCls[$tr['status']] ?? 'bg-slate-100 text-slate-600' ?>">
                  <?= e($tripStatusLabel[$tr['status']] ?? $tr['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif ?>

  <!-- ─── Notes & observations ─────────────────────────────────────────────── -->
  <div id="notes" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="message-square-text" class="w-4 h-4 text-cb-primary"></i>
        Notes &amp; observations
        <span class="text-xs font-normal text-slate-400 ml-1">(<?= count($notes ?? []) ?>)</span>
      </h2>
    </div>

    <!-- Formulaire d'ajout -->
    <form method="post" action="<?= e(url('referentiel/lines/'.$line['id'].'/notes')) ?>" class="space-y-3">
      <?= csrf_field() ?>
      <textarea name="content" rows="3" maxlength="2000"
                placeholder="Ajouter une observation, un suivi, une remarque opérationnelle..."
                class="cb-input w-full resize-none"></textarea>
      <div class="flex justify-end">
        <button type="submit"
                class="px-4 py-2 bg-cb-primary text-white rounded-xl text-sm font-medium hover:bg-cb-dark transition flex items-center gap-2">
          <i data-lucide="send" class="w-4 h-4"></i> Enregistrer la note
        </button>
      </div>
    </form>

    <!-- Historique des notes -->
    <?php if (empty($notes)): ?>
      <p class="text-sm text-slate-400 text-center py-4 border-t border-slate-100">Aucune note pour le moment.</p>
    <?php else: ?>
      <div class="space-y-3 border-t border-slate-100 pt-4">
        <?php foreach ($notes as $note):
          $currentUser = auth();
          $canDelete   = (int)($currentUser['id'] ?? 0) === (int)$note['author_id']
                      || in_array($currentUser['role'] ?? '', ['admin', 'super_admin', 'superadmin'], true);
          $authorName = trim(($note['author_first_name'] ?? '') . ' ' . ($note['author_last_name'] ?? ''))
                     ?: ($note['author_name'] ?? ($note['author_email'] ?? 'Utilisateur'));
        ?>
          <div class="flex gap-3 p-4 bg-slate-50 rounded-xl border border-slate-100">
            <div class="w-9 h-9 rounded-full bg-cb-primary text-white flex items-center justify-center font-bold text-sm shrink-0">
              <?= e(strtoupper(substr($authorName, 0, 1))) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <span class="font-semibold text-sm text-slate-800"><?= e($authorName) ?></span>
                  <span class="text-xs text-slate-400 ml-2 capitalize"><?= e($note['author_role'] ?? '') ?></span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <time class="text-xs text-slate-400"><?= e(date('d/m/Y H:i', strtotime($note['created_at']))) ?></time>
                  <?php if ($canDelete): ?>
                    <form method="post"
                          action="<?= e(url('referentiel/lines/'.$line['id'].'/notes/'.$note['id'].'/delete')) ?>"
                          onsubmit="return confirm('Supprimer cette note ?')">
                      <?= csrf_field() ?>
                      <button type="submit" class="text-slate-300 hover:text-rose-500 transition p-1 rounded">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                      </button>
                    </form>
                  <?php endif ?>
                </div>
              </div>
              <p class="text-sm text-slate-700 mt-1.5 whitespace-pre-line"><?= e($note['content']) ?></p>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
