<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

// Statuts considérés "ouverts à la vente"
$openStatuses = ['planifie', 'valide', 'embarquement'];
?>
<?php $view->start('content') ?>

<div class="space-y-5">

  <!-- En-tête ────────────────────────────────────────────────────────── -->
  <div class="flex items-end justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Sélectionner un voyage</h1>
      <p class="text-slate-500 text-sm">Choisissez le voyage pour vendre des billets passagers.</p>
    </div>
    <a href="<?= e(url('billetterie')) ?>"
       class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition">
      <i data-lucide="list" class="w-4 h-4"></i> Liste des billets
    </a>
  </div>

  <!-- Filtres ─────────────────────────────────────────────────────────── -->
  <div class="flex flex-wrap items-start gap-3">
    <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 grid md:grid-cols-6 gap-2 items-end shadow-soft w-full xl:w-auto">
      <div>
        <label class="block text-xs text-slate-500 mb-1 uppercase">Du</label>
        <div class="flex items-center gap-2">
          <i data-lucide="calendar" class="w-4 h-4 text-slate-400 ml-2"></i>
          <input type="date" name="date_from" value="<?= e($dateFrom ?? '') ?>"
                 class="px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none w-full">
        </div>
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1 uppercase">Au</label>
        <input type="date" name="date_to" value="<?= e($dateTo ?? '') ?>"
               class="px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none w-full">
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1 uppercase">Ligne</label>
        <select name="line_id" class="px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none w-full">
          <option value="0">Toutes</option>
          <?php foreach (($lines ?? []) as $line): ?>
            <option value="<?= e((string)$line['id']) ?>" <?= ((int)($lineId ?? 0) === (int)$line['id']) ? 'selected' : '' ?>>
              <?= e($line['code']) ?> — <?= e($line['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1 uppercase">Statut</label>
        <select name="status" class="px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none w-full">
          <option value="" <?= ($statusFilter ?? '') === '' ? 'selected' : '' ?>>Tous les voyages</option>
          <option value="open" <?= ($statusFilter ?? '') === 'open' ? 'selected' : '' ?>>Ouverts à la vente</option>
          <option value="planifie" <?= ($statusFilter ?? '') === 'planifie' ? 'selected' : '' ?>>Planifié</option>
          <option value="valide" <?= ($statusFilter ?? '') === 'valide' ? 'selected' : '' ?>>Validé</option>
          <option value="embarquement" <?= ($statusFilter ?? '') === 'embarquement' ? 'selected' : '' ?>>Embarquement</option>
        </select>
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1 uppercase">Disponibilité</label>
        <select name="availability" class="px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none w-full">
          <option value="" <?= ($availability ?? '') === '' ? 'selected' : '' ?>>Toutes</option>
          <option value="available" <?= ($availability ?? '') === 'available' ? 'selected' : '' ?>>Avec places libres</option>
          <option value="low" <?= ($availability ?? '') === 'low' ? 'selected' : '' ?>>1 à 10 places</option>
          <option value="full" <?= ($availability ?? '') === 'full' ? 'selected' : '' ?>>Complets</option>
        </select>
      </div>

      <div class="flex gap-2">
        <button class="px-4 py-2 rounded-lg bg-cb-primary text-white text-sm font-medium hover:bg-cb-dark transition w-full">
          Filtrer
        </button>
        <a href="<?= e(url('billetterie/select-trip')) ?>?date_from=<?= e(date('Y-m-d')) ?>&date_to=<?= e(date('Y-m-d')) ?>&status=open"
           class="px-3 py-2 rounded-lg text-slate-500 text-sm hover:bg-slate-50 border border-slate-200 whitespace-nowrap">Aujourd'hui</a>
      </div>
    </form>

    <!-- Badge : mode par défaut ou résultat filtré -->
    <?php if (!($hasFilter ?? false)): ?>
    <div class="flex items-center gap-2 text-xs text-slate-500 bg-white rounded-xl border border-slate-100 px-3 py-2 shadow-soft">
      <i data-lucide="clock" class="w-3.5 h-3.5 text-cb-primary"></i>
      30 derniers voyages enregistrés
    </div>
    <?php endif ?>

    <!-- Stats de la période -->
    <?php if ($trips): ?>
    <?php
      $totalVoyages  = count($trips);
      $totalSieges   = array_sum(array_column($trips, 'seats'));
      $totalVendus   = array_sum(array_column($trips, 'sold_seats'));
      $pctGlobal     = $totalSieges > 0 ? round($totalVendus / $totalSieges * 100) : 0;
    ?>
    <div class="flex items-center gap-3 flex-wrap">
      <div class="bg-white rounded-xl border border-slate-100 shadow-soft px-4 py-2.5 flex items-center gap-2 text-sm">
        <span class="w-6 h-6 bg-cb-bg rounded-lg flex items-center justify-center">
          <i data-lucide="bus" class="w-3.5 h-3.5 text-cb-primary"></i>
        </span>
        <span class="text-slate-500">Voyages :</span>
        <span class="font-bold text-slate-900"><?= $totalVoyages ?></span>
      </div>
      <div class="bg-white rounded-xl border border-slate-100 shadow-soft px-4 py-2.5 flex items-center gap-2 text-sm">
        <span class="w-6 h-6 bg-emerald-50 rounded-lg flex items-center justify-center">
          <i data-lucide="armchair" class="w-3.5 h-3.5 text-emerald-600"></i>
        </span>
        <span class="text-slate-500">Sièges vendus :</span>
        <span class="font-bold text-slate-900"><?= $totalVendus ?> / <?= $totalSieges ?></span>
        <span class="px-1.5 py-0.5 rounded-full text-xs font-semibold <?= $pctGlobal >= 80 ? 'bg-emerald-100 text-emerald-700' : ($pctGlobal >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500') ?>">
          <?= $pctGlobal ?>%
        </span>
      </div>
    </div>
    <?php endif ?>
  </div>

  <!-- Cards de voyages ───────────────────────────────────────────────── -->
  <?php if ($trips):

    // Séparer : ouverts à la vente / fermés
    $tripsOpen   = array_filter($trips, fn($t) => in_array($t['status'], $openStatuses, true));
    $tripsClosed = array_filter($trips, fn($t) => !in_array($t['status'], $openStatuses, true));

    $renderCards = function(array $list, bool $muted = false) use ($openStatuses): void {
      foreach ($list as $t):
        $totalSeats = (int)$t['seats'];
        $sold       = (int)$t['sold_seats'];
        $available  = $totalSeats - $sold;
        $pct        = $totalSeats > 0 ? round(($sold / $totalSeats) * 100) : 0;
        $isOpen     = in_array($t['status'], $openStatuses, true);

        $availCls = $available > 10 ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                  : ($available > 0 ? 'bg-amber-50 text-amber-700 border-amber-200'
                                    : 'bg-rose-50 text-rose-700 border-rose-200');

        $statusLabel = match($t['status']) {
            'planifie'     => 'Planifié',
            'valide'       => 'Validé',
            'embarquement' => 'Embarquement',
            'en_route'     => 'En route',
            'arrive'       => 'Arrivé',
            'cloture'      => 'Clôturé',
            'annule'       => 'Annulé',
            default        => $t['status'],
        };
        $statusCls = match($t['status']) {
            'planifie'     => 'bg-slate-100 text-slate-600',
            'valide'       => 'bg-cyan-50 text-cyan-700',
            'embarquement' => 'bg-cb-bg text-cb-primary',
            'en_route'     => 'bg-blue-50 text-blue-700',
            'arrive'       => 'bg-emerald-100 text-emerald-700',
            'cloture'      => 'bg-emerald-50 text-emerald-600',
            'annule'       => 'bg-red-50 text-red-600',
            default        => 'bg-slate-100 text-slate-600',
        };

        $cardBorder = $muted
          ? 'border-2 border-slate-100 opacity-60'
          : 'border-2 border-slate-100 hover:border-cb-primary hover:shadow-lg';
        $pointer    = $muted ? 'cursor-default' : '';
        $href       = $isOpen ? e(url('billetterie/sale/' . $t['id'])) : '#';
?>
        <<?= $isOpen ? 'a href="' . $href . '"' : 'div' ?> class="group block bg-white rounded-2xl <?= $cardBorder ?> transition p-5 <?= $pointer ?>">

          <!-- Header card -->
          <div class="flex items-start justify-between gap-2 mb-2">
            <div>
              <span class="font-mono text-sm text-cb-primary font-bold"><?= e($t['line_code']) ?></span>
              <h3 class="font-bold text-slate-900 mt-0.5"><?= e($t['line_name']) ?></h3>
            </div>
            <?php if ($isOpen): ?>
            <span class="px-2 py-0.5 rounded-full text-xs border <?= $availCls ?> shrink-0">
              <?= $available ?> libres
            </span>
            <?php endif ?>
          </div>

          <!-- Date + heure + bus -->
          <div class="grid grid-cols-3 gap-2 text-sm text-slate-600 mb-2">
            <div class="flex items-center gap-1.5">
              <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
              <span class="text-xs font-medium"><?= e(date('d/m/Y', strtotime($t['trip_date']))) ?></span>
            </div>
            <div class="flex items-center gap-1.5">
              <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
              <span class="font-medium"><?= e(date('H:i', strtotime($t['departure_scheduled']))) ?></span>
            </div>
            <div class="flex items-center gap-1.5">
              <i data-lucide="bus" class="w-3.5 h-3.5 text-slate-400"></i>
              <span class="font-mono text-xs"><?= e($t['bus_code']) ?></span>
            </div>
          </div>

          <?php if (!empty($t['departure_city_name']) || !empty($t['arrival_city_name'])): ?>
            <div class="mb-2 text-xs text-slate-500 flex items-center gap-2">
              <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i>
              <span><?= e($t['departure_city_name'] ?? '') ?></span>
              <i data-lucide="arrow-right" class="w-3 h-3 text-slate-300"></i>
              <span><?= e($t['arrival_city_name'] ?? '') ?></span>
            </div>
          <?php endif ?>

          <!-- Barre d'occupation -->
          <div class="bg-slate-100 rounded-full h-1.5 overflow-hidden mb-1.5">
            <div class="h-full <?= $isOpen ? 'bg-gradient-to-r from-cb-primary to-cb-dark' : 'bg-slate-300' ?>" style="width: <?= $pct ?>%"></div>
          </div>
          <div class="flex justify-between items-center text-xs text-slate-500">
            <span><?= $sold ?> / <?= $totalSeats ?> sièges</span>
            <span class="px-2 py-0.5 rounded-full <?= $statusCls ?> text-xs font-medium">
              <?= $statusLabel ?>
            </span>
          </div>

          <?php if ($isOpen): ?>
          <!-- Hover hint -->
          <div class="mt-2 text-xs text-cb-primary opacity-0 group-hover:opacity-100 transition flex items-center gap-1">
            <i data-lucide="arrow-right" class="w-3 h-3"></i> Vendre un billet
          </div>
          <?php endif ?>

        </<?= $isOpen ? 'a' : 'div' ?>>
<?php
      endforeach;
    };
  ?>

  <!-- Voyages ouverts à la vente -->
  <?php if ($tripsOpen): ?>
  <?php if ($tripsClosed): ?>
  <div class="flex items-center gap-3 mb-1">
    <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Ouverts à la vente</span>
    <div class="flex-1 h-px bg-emerald-100"></div>
    <span class="text-xs text-slate-400"><?= count($tripsOpen) ?> voyage<?= count($tripsOpen) > 1 ? 's' : '' ?></span>
  </div>
  <?php endif ?>
  <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php $renderCards(array_values($tripsOpen), false) ?>
  </div>
  <?php endif ?>

  <!-- ── Règle : séparateur entre ouverts et fermés ── -->
  <?php if ($tripsOpen && $tripsClosed): ?>
  <div class="flex items-center gap-4 py-1">
    <div class="flex-1 h-px bg-slate-200"></div>
    <div class="flex items-center gap-2 text-xs text-slate-400 font-medium">
      <i data-lucide="lock" class="w-3.5 h-3.5"></i>
      Fermés à la vente
    </div>
    <div class="flex-1 h-px bg-slate-200"></div>
  </div>
  <?php endif ?>

  <!-- Voyages fermés -->
  <?php if ($tripsClosed): ?>
  <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php $renderCards(array_values($tripsClosed), true) ?>
  </div>
  <?php endif ?>

  <?php elseif (!$trips): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center">
    <i data-lucide="calendar-x" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
    <p class="text-slate-400">Aucun voyage trouvé<?= ($hasFilter ?? false) ? ' avec ces filtres.' : '.' ?></p>
    <?php if ($hasFilter ?? false): ?>
    <a href="<?= e(url('billetterie/select-trip')) ?>"
       class="mt-4 inline-flex items-center gap-2 text-sm text-cb-primary hover:underline">
      <i data-lucide="x" class="w-3.5 h-3.5"></i> Effacer les filtres
    </a>
    <?php endif ?>
  </div>
  <?php endif ?>

</div>
<?php $view->end() ?>
