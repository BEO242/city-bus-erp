<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="space-y-5">

  <div class="flex items-center justify-between gap-3">
    <div class="flex items-center gap-2 text-sm text-slate-500">
      <a href="<?= e(url('voyages/' . $trip['id'])) ?>" class="hover:text-cb-primary inline-flex items-center gap-1">
        <i data-lucide="chevron-left" class="w-4 h-4"></i> Voyage <?= e($trip['trip_code']) ?>
      </a>
      <span>/</span>
      <span class="text-slate-800 font-semibold">Briefing</span>
    </div>
    <?php if (can('voyages.briefing.print')): ?>
      <a href="<?= e(url('voyages/' . $trip['id'] . '/briefing/print')) ?>" target="_blank"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-secondary shadow-soft">
        <i data-lucide="printer" class="w-4 h-4"></i> Imprimer (A4)
      </a>
    <?php endif ?>
  </div>

  <!-- Header -->
  <div class="bg-gradient-to-r from-cb-primary to-cb-secondary text-white rounded-2xl p-6 shadow-soft">
    <div class="flex items-start justify-between gap-4">
      <div>
        <div class="text-xs uppercase tracking-wider opacity-80">Briefing voyage</div>
        <h1 class="text-3xl font-bold mt-1"><?= e($trip['trip_code']) ?></h1>
        <div class="mt-2 text-lg"><?= e($trip['line_code']) ?> — <?= e($trip['departure_city']) ?> → <?= e($trip['arrival_city']) ?></div>
      </div>
      <div class="text-right text-sm">
        <div class="opacity-80">Date</div>
        <div class="text-2xl font-bold"><?= e(date('d/m/Y', strtotime($trip['trip_date']))) ?></div>
        <div class="mt-1 opacity-80">Départ</div>
        <div class="text-xl font-semibold"><?= e(substr($trip['departure_time'] ?? '', 0, 5)) ?></div>
      </div>
    </div>
  </div>

  <!-- Equipage / véhicule -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase mb-2">Véhicule</div>
      <?php if ($trip['bus_id']): ?>
        <div class="text-lg font-bold text-slate-900"><?= e($trip['bus_code']) ?></div>
        <div class="text-sm text-slate-600"><?= e($trip['bus_plate']) ?></div>
        <div class="text-xs text-slate-500 mt-1"><?= e($trip['bus_brand']) ?> <?= e($trip['bus_model']) ?> · <?= (int)$trip['bus_seats'] ?> sièges</div>
      <?php else: ?>
        <div class="text-sm text-rose-600">Non assigné</div>
      <?php endif ?>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase mb-2">Chauffeur</div>
      <?php if ($trip['driver_id']): ?>
        <div class="text-lg font-bold text-slate-900"><?= e($trip['driver_name']) ?></div>
        <div class="text-sm text-slate-600"><?= e($trip['driver_phone'] ?? '—') ?></div>
        <div class="text-xs text-slate-500 mt-1">Permis : <?= e($trip['driver_license'] ?? '—') ?></div>
      <?php else: ?>
        <div class="text-sm text-rose-600">Non assigné</div>
      <?php endif ?>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase mb-2">Receveur</div>
      <?php if ($trip['conductor_id']): ?>
        <div class="text-lg font-bold text-slate-900"><?= e($trip['conductor_name']) ?></div>
        <div class="text-sm text-slate-600"><?= e($trip['conductor_phone'] ?? '—') ?></div>
      <?php else: ?>
        <div class="text-sm text-slate-400">—</div>
      <?php endif ?>
    </div>
  </div>

  <!-- Inventaire -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-bold text-slate-900">Inventaire</h2>
      <div class="text-sm text-slate-600">
        <?= (int)$totalSold ?> / <?= (int)$totalCap ?> sièges vendus
        (<?= $totalCap > 0 ? round($totalSold/$totalCap*100) : 0 ?>%)
      </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
      <?php foreach ($inventory as $inv): ?>
        <?php $avail = max(0, (int)$inv['capacity'] - (int)$inv['sold_count'] - (int)$inv['blocked_count']); ?>
        <div class="rounded-xl p-3 border" style="border-color: <?= e($inv['color_hex'] ?? '#e2e8f0') ?>">
          <div class="flex items-center justify-between">
            <span class="text-lg font-bold" style="color: <?= e($inv['color_hex'] ?? '#0f172a') ?>"><?= e($inv['class_code']) ?></span>
            <span class="text-xs text-slate-500"><?= e($inv['class_name'] ?? '') ?></span>
          </div>
          <div class="mt-2 text-2xl font-bold text-slate-900"><?= (int)$inv['sold_count'] ?>/<?= (int)$inv['capacity'] ?></div>
          <div class="text-xs text-slate-500">Dispo : <?= $avail ?></div>
        </div>
      <?php endforeach ?>
    </div>
  </div>

  <!-- Stops -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
    <h2 class="text-lg font-bold text-slate-900 mb-4">Itinéraire (<?= count($stops) ?> arrêts)</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="text-xs uppercase text-slate-500 border-b border-slate-200">
          <tr>
            <th class="px-3 py-2 text-left">#</th>
            <th class="px-3 py-2 text-left">Arrêt</th>
            <th class="px-3 py-2 text-left">Arrivée prévue</th>
            <th class="px-3 py-2 text-left">Départ prévu</th>
            <th class="px-3 py-2 text-right">Km</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($stops as $i => $s): ?>
            <tr>
              <td class="px-3 py-2 font-mono text-slate-500"><?= $i+1 ?></td>
              <td class="px-3 py-2 font-semibold text-slate-900"><?= e($s['stop_name'] ?? '—') ?></td>
              <td class="px-3 py-2"><?= $s['scheduled_arrival'] ? e(date('H:i', strtotime($s['scheduled_arrival']))) : '—' ?></td>
              <td class="px-3 py-2"><?= $s['scheduled_departure'] ? e(date('H:i', strtotime($s['scheduled_departure']))) : '—' ?></td>
              <td class="px-3 py-2 text-right text-slate-600"><?= e($s['km_from_start'] ?? '—') ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Inspections + documents -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <h2 class="text-lg font-bold text-slate-900 mb-3">Inspections pré-départ</h2>
      <?php if (empty($inspections)): ?>
        <p class="text-sm text-slate-500">Aucune inspection enregistrée.</p>
      <?php else: ?>
        <ul class="space-y-2">
          <?php foreach ($inspections as $insp): ?>
            <li class="text-sm flex items-center justify-between border-b border-slate-100 pb-1">
              <span class="font-semibold"><?= e($insp['status']) ?></span>
              <span class="text-slate-500"><?= e(date('d/m/Y H:i', strtotime($insp['inspected_at']))) ?></span>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <h2 class="text-lg font-bold text-slate-900 mb-3">Documents</h2>
      <?php if (empty($documents)): ?>
        <p class="text-sm text-slate-500">Aucun document attaché.</p>
      <?php else: ?>
        <ul class="space-y-2">
          <?php foreach ($documents as $doc): ?>
            <li class="text-sm flex items-center justify-between">
              <span><?= e($doc['original_name']) ?> <span class="text-xs text-slate-400">(<?= e($doc['doc_type']) ?>)</span></span>
              <a href="<?= e(url($doc['file_path'])) ?>" target="_blank" class="text-cb-primary hover:underline text-xs">Ouvrir</a>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </div>
  </div>

  <!-- Notes -->
  <?php if (!empty($trip['notes'])): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
      <h3 class="font-bold text-amber-900 mb-2"><i data-lucide="alert-triangle" class="inline w-4 h-4"></i> Consignes / Notes</h3>
      <p class="text-sm text-amber-800 whitespace-pre-wrap"><?= e($trip['notes']) ?></p>
    </div>
  <?php endif ?>

</div>

<?php $view->end() ?>
