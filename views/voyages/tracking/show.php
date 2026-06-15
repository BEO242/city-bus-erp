<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="space-y-5">

  <!-- Breadcrumb -->
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('voyages/' . $trip['id'])) ?>" class="hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i>
      Voyage <?= e($trip['trip_code']) ?>
    </a>
    <span>/</span>
    <span class="text-slate-800 font-semibold">Suivi en temps réel</span>
  </div>

  <!-- Header + progression globale -->
  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
    <div class="flex items-start justify-between flex-wrap gap-4 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
          <i data-lucide="map-pin" class="w-6 h-6 text-cb-primary"></i>
          Progression du voyage
        </h1>
        <p class="text-sm text-slate-500 mt-1">
          <?= e($trip['departure_city']) ?> → <?= e($trip['arrival_city']) ?>
        </p>
      </div>
      <div class="text-right">
        <div class="text-xs font-semibold text-slate-500 uppercase">Progression</div>
        <div class="text-3xl font-bold text-cb-primary"><?= $progress ?>%</div>
        <div class="text-xs text-slate-500"><?= $reached ?> / <?= $totalStops ?> arrêts</div>
      </div>
    </div>

    <!-- Progress bar -->
    <div class="bg-slate-100 rounded-full h-3 overflow-hidden">
      <div class="h-full bg-gradient-to-r from-cb-primary to-cb-secondary transition-all" style="width: <?= $progress ?>%"></div>
    </div>

    <?php if (!empty($trip['current_eta'])): ?>
      <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 text-sm">
        <i data-lucide="clock" class="w-4 h-4"></i>
        ETA destination : <strong><?= e(date('d/m/Y H:i', strtotime($trip['current_eta']))) ?></strong>
      </div>
    <?php endif ?>
  </div>

  <!-- Timeline arrêts -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100">
      <h2 class="font-semibold text-slate-900">Itinéraire détaillé</h2>
    </div>
    <div class="p-5">

      <div class="relative">
        <!-- Ligne verticale -->
        <div class="absolute left-6 top-2 bottom-2 w-0.5 bg-slate-200"></div>

        <?php foreach ($stops as $i => $stop):
          $isReached = !empty($stop['actual_arrival']);
          $isCurrent = !$isReached && !$stop['is_skipped'] && (
            $i === 0 || !empty($stops[$i - 1]['actual_departure'])
          );
          $delay = (int)$stop['total_delay_min'];

          // Couleur du marqueur selon statut
          if ($stop['is_skipped']) {
            $dotColor = 'bg-slate-300 border-slate-400';
            $dotIcon = 'x';
          } elseif ($isReached) {
            $dotColor = $delay > 15 ? 'bg-amber-100 border-amber-500' : 'bg-emerald-100 border-emerald-500';
            $dotIcon = 'check';
          } elseif ($isCurrent) {
            $dotColor = 'bg-cb-primary border-cb-primary animate-pulse';
            $dotIcon = 'navigation';
          } else {
            $dotColor = 'bg-white border-slate-300';
            $dotIcon = 'circle';
          }
        ?>
          <div class="relative flex items-start gap-4 mb-6 last:mb-0">
            <!-- Marqueur -->
            <div class="relative z-10 w-12 h-12 rounded-full border-2 <?= $dotColor ?> flex items-center justify-center shrink-0">
              <i data-lucide="<?= $dotIcon ?>" class="w-5 h-5 <?= $isCurrent ? 'text-white' : ($isReached ? 'text-emerald-600' : 'text-slate-400') ?>"></i>
            </div>

            <!-- Contenu -->
            <div class="flex-1 min-w-0 pt-2">
              <div class="flex items-start justify-between flex-wrap gap-2">
                <div>
                  <h3 class="font-bold text-slate-900 flex items-center gap-2">
                    <?= e($stop['stop_name']) ?>
                    <?php if ($stop['is_skipped']): ?>
                      <span class="text-xs px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 font-medium">SAUTÉ</span>
                    <?php elseif ($isCurrent): ?>
                      <span class="text-xs px-2 py-0.5 rounded-full bg-cb-primary text-white font-medium">EN COURS</span>
                    <?php elseif ($isReached): ?>
                      <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium">PASSÉ</span>
                    <?php endif ?>
                  </h3>
                  <p class="text-xs text-slate-500 mt-0.5">
                    <?php if (!empty($stop['distance_from_origin_km'])): ?>
                      KM <?= number_format((float)$stop['distance_from_origin_km'], 1, ',', ' ') ?>
                    <?php endif ?>
                    <?php if ($delay > 0): ?>
                      · <span class="text-amber-600 font-bold">+<?= $delay ?>min</span>
                    <?php endif ?>
                  </p>
                </div>

                <?php if (!$stop['is_skipped'] && can('voyages.tracking.update') && !$isReached): ?>
                  <details>
                    <summary class="cursor-pointer text-xs text-cb-primary font-semibold hover:underline">
                      Actions ▾
                    </summary>
                    <div class="mt-2 p-3 border border-slate-200 rounded-lg bg-slate-50 space-y-2 min-w-[280px]">
                      <form method="post" action="<?= e(url('voyages/' . $trip['id'] . '/tracking/' . $stop['stop_id'] . '/arrival')) ?>" class="flex gap-2">
                        <?= csrf_field() ?>
                        <input type="datetime-local" name="actual_time" class="flex-1 px-2 py-1 text-xs border border-slate-200 rounded">
                        <button class="px-3 py-1 text-xs bg-emerald-600 text-white rounded font-semibold">Arrivée</button>
                      </form>
                      <form method="post" action="<?= e(url('voyages/' . $trip['id'] . '/tracking/' . $stop['stop_id'] . '/departure')) ?>" class="grid grid-cols-2 gap-1">
                        <?= csrf_field() ?>
                        <input type="number" name="pax_boarded" placeholder="Embarqués" class="px-2 py-1 text-xs border border-slate-200 rounded">
                        <input type="number" name="pax_alighted" placeholder="Descendus" class="px-2 py-1 text-xs border border-slate-200 rounded">
                        <button class="col-span-2 px-3 py-1 text-xs bg-cb-primary text-white rounded font-semibold">Départ</button>
                      </form>
                      <form method="post" action="<?= e(url('voyages/' . $trip['id'] . '/tracking/' . $stop['stop_id'] . '/skip')) ?>" class="flex gap-2">
                        <?= csrf_field() ?>
                        <input name="reason" placeholder="Motif saut" required class="flex-1 px-2 py-1 text-xs border border-slate-200 rounded">
                        <button class="px-3 py-1 text-xs bg-slate-600 text-white rounded font-semibold">Sauter</button>
                      </form>
                    </div>
                  </details>
                <?php endif ?>
              </div>

              <!-- Horaires -->
              <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 text-sm">
                <?php if (!empty($stop['scheduled_arrival'])): ?>
                <div>
                  <div class="text-xs text-slate-500">Arrivée prévue</div>
                  <div class="font-mono"><?= e(date('H:i', strtotime($stop['scheduled_arrival']))) ?></div>
                </div>
                <?php endif ?>
                <?php if (!empty($stop['actual_arrival'])): ?>
                <div>
                  <div class="text-xs text-slate-500">Arrivée réelle</div>
                  <div class="font-mono <?= $delay > 15 ? 'text-rose-600 font-bold' : 'text-emerald-600' ?>">
                    <?= e(date('H:i', strtotime($stop['actual_arrival']))) ?>
                  </div>
                </div>
                <?php endif ?>
                <?php if (!empty($stop['scheduled_departure'])): ?>
                <div>
                  <div class="text-xs text-slate-500">Départ prévu</div>
                  <div class="font-mono"><?= e(date('H:i', strtotime($stop['scheduled_departure']))) ?></div>
                </div>
                <?php endif ?>
                <?php if (!empty($stop['actual_departure'])): ?>
                <div>
                  <div class="text-xs text-slate-500">Départ réel</div>
                  <div class="font-mono"><?= e(date('H:i', strtotime($stop['actual_departure']))) ?></div>
                </div>
                <?php endif ?>
              </div>

              <!-- Stats PAX/Cargo si l'arrêt a été quitté -->
              <?php if (!empty($stop['actual_departure'])): ?>
              <div class="flex flex-wrap gap-3 mt-3 text-xs">
                <?php if ((int)$stop['pax_boarded'] > 0): ?>
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-emerald-50 text-emerald-700">
                    <i data-lucide="user-plus" class="w-3 h-3"></i> +<?= (int)$stop['pax_boarded'] ?> PAX
                  </span>
                <?php endif ?>
                <?php if ((int)$stop['pax_alighted'] > 0): ?>
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-amber-50 text-amber-700">
                    <i data-lucide="user-minus" class="w-3 h-3"></i> -<?= (int)$stop['pax_alighted'] ?> PAX
                  </span>
                <?php endif ?>
                <?php if ((int)$stop['parcels_loaded'] > 0): ?>
                  <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-cyan-50 text-cyan-700">
                    <i data-lucide="package-plus" class="w-3 h-3"></i> +<?= (int)$stop['parcels_loaded'] ?> colis
                  </span>
                <?php endif ?>
              </div>
              <?php endif ?>

              <?php if ($stop['is_skipped'] && !empty($stop['skip_reason'])): ?>
                <div class="mt-2 p-2 rounded bg-slate-50 border border-slate-200 text-xs text-slate-600">
                  <strong>Motif saut :</strong> <?= e($stop['skip_reason']) ?>
                </div>
              <?php endif ?>

              <?php if (!empty($stop['notes'])): ?>
                <div class="mt-2 p-2 rounded bg-amber-50 border border-amber-200 text-xs text-amber-800">
                  <i data-lucide="message-square" class="w-3 h-3 inline"></i> <?= e($stop['notes']) ?>
                </div>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      </div>

    </div>
  </div>

  <!-- Journal d'événements -->
  <?php if (!empty($events)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100">
        <h2 class="font-semibold text-slate-900">Journal d'événements</h2>
      </div>
      <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
        <?php foreach ($events as $ev): ?>
          <div class="px-5 py-3 flex items-start gap-3 hover:bg-slate-50">
            <div class="text-xs font-mono text-slate-400 shrink-0 w-32">
              <?= e(date('d/m H:i:s', strtotime($ev['occurred_at']))) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-sm text-slate-900"><?= e($ev['event_type']) ?></div>
              <?php if (!empty($ev['stop_name'])): ?>
                <div class="text-xs text-slate-500"><?= e($ev['stop_name']) ?></div>
              <?php endif ?>
              <?php if (!empty($ev['notes'])): ?>
                <div class="text-xs text-slate-600 mt-1"><?= e($ev['notes']) ?></div>
              <?php endif ?>
              <?php if (!empty($ev['actor_name'])): ?>
                <div class="text-xs text-slate-400 mt-0.5">par <?= e($ev['actor_name']) ?></div>
              <?php endif ?>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  <?php endif ?>

</div>

<?php $view->end() ?>
