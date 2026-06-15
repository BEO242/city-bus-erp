<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$statusColor = function(string $s): string {
  return match(strtolower($s)) {
    'en_route'   => 'bg-emerald-500',
    'boarding','embarquement' => 'bg-amber-500',
    'planifie','planifié','prep','prepare' => 'bg-sky-500',
    'retarde','retardé' => 'bg-rose-500',
    'incident'   => 'bg-rose-700',
    'annule','annulé' => 'bg-slate-400',
    'termine','terminé','arrive','arrivé' => 'bg-slate-500',
    default      => 'bg-slate-300',
  };
};
?>
<?php $view->start('content') ?>

<div class="space-y-5" x-data="{ autoRefresh: true }" x-init="setInterval(() => { if (autoRefresh) location.reload(); }, 60000)">

  <!-- Header -->
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="radar" class="w-7 h-7 text-cb-primary"></i>
        Operations Control Center
      </h1>
      <p class="text-sm text-slate-500">Vue temps réel des opérations · auto-refresh 60s</p>
    </div>
    <form method="get" class="flex items-center gap-2">
      <input type="date" name="date" value="<?= e($date) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
      <button class="px-4 py-2 rounded-lg bg-cb-primary text-white text-sm font-semibold hover:bg-cb-secondary">Actualiser</button>
      <label class="flex items-center gap-1 text-xs text-slate-600 ml-2">
        <input type="checkbox" x-model="autoRefresh"> Auto
      </label>
    </form>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
    <?php
      $cards = [
        ['Total',       $kpi['total'],       'list',          'slate'],
        ['Planifiés',   $kpi['planned'],     'calendar',      'sky'],
        ['En cours',    $kpi['in_progress'], 'truck',         'emerald'],
        ['Terminés',    $kpi['completed'],   'check-circle',  'slate'],
        ['Retardés',    $kpi['delayed'],     'clock',         'amber'],
        ['Incidents',   $kpi['incidents'],   'alert-triangle','rose'],
      ];
      foreach ($cards as [$lbl, $val, $icon, $col]):
    ?>
      <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-soft">
        <div class="flex items-center justify-between mb-1">
          <div class="text-xs font-semibold text-slate-500 uppercase"><?= e($lbl) ?></div>
          <i data-lucide="<?= e($icon) ?>" class="w-4 h-4 text-<?= $col ?>-500"></i>
        </div>
        <div class="text-2xl font-bold text-<?= $col ?>-700"><?= (int)$val ?></div>
      </div>
    <?php endforeach ?>
  </div>

  <!-- Alertes -->
  <?php if (!empty($alerts)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <h2 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">
        <i data-lucide="alert-octagon" class="w-5 h-5 text-rose-500"></i>
        Alertes (<?= count($alerts) ?>)
      </h2>
      <ul class="divide-y divide-slate-100">
        <?php foreach ($alerts as $a): ?>
          <li class="py-2 flex items-center justify-between text-sm">
            <div class="flex items-center gap-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $a['level']==='danger'?'bg-rose-100 text-rose-700':'bg-amber-100 text-amber-700' ?>">
                <?= strtoupper($a['level']) ?>
              </span>
              <a href="<?= e(url('voyages/' . $a['trip_id'])) ?>" class="font-mono font-bold text-cb-primary hover:underline"><?= e($a['trip_code']) ?></a>
              <span class="text-slate-700"><?= e($a['msg']) ?></span>
            </div>
            <a href="<?= e(url('voyages/' . $a['trip_id'])) ?>" class="text-xs text-slate-400 hover:text-cb-primary">Ouvrir →</a>
          </li>
        <?php endforeach ?>
      </ul>
    </div>
  <?php endif ?>

  <!-- Timeline / liste voyages -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
      <h2 class="text-lg font-bold text-slate-900">Voyages du <?= e(date('d/m/Y', strtotime($date))) ?></h2>
      <span class="text-xs text-slate-500"><?= count($trips) ?> voyage(s)</span>
    </div>

    <?php if (empty($trips)): ?>
      <div class="p-12 text-center text-slate-400">Aucun voyage planifié pour cette date.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-xs text-slate-600 uppercase">
            <tr>
              <th class="px-4 py-3 text-left">Code</th>
              <th class="px-4 py-3 text-left">Ligne</th>
              <th class="px-4 py-3 text-left">Itinéraire</th>
              <th class="px-4 py-3 text-left">Départ</th>
              <th class="px-4 py-3 text-left">Véhicule</th>
              <th class="px-4 py-3 text-left">Chauffeur</th>
              <th class="px-4 py-3 text-left">Position</th>
              <th class="px-4 py-3 text-left">Retard</th>
              <th class="px-4 py-3 text-left">Statut</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($trips as $t): ?>
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-mono font-bold text-cb-primary">
                  <a href="<?= e(url('voyages/' . $t['id'])) ?>" class="hover:underline"><?= e($t['trip_code']) ?></a>
                </td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 text-xs font-mono font-bold"><?= e($t['line_code']) ?></span></td>
                <td class="px-4 py-3"><?= e($t['departure_city']) ?> → <?= e($t['arrival_city']) ?></td>
                <td class="px-4 py-3 font-mono"><?= e(substr($t['departure_time'] ?? '', 0, 5)) ?></td>
                <td class="px-4 py-3 text-xs"><?= e($t['bus_code'] ?? '—') ?><?php if($t['bus_plate']): ?><br><span class="text-slate-400"><?= e($t['bus_plate']) ?></span><?php endif ?></td>
                <td class="px-4 py-3 text-xs"><?= e($t['driver_name'] ?? '—') ?></td>
                <td class="px-4 py-3 text-xs">
                  <?php if ($t['current_stop_name']): ?>
                    <div><i data-lucide="map-pin" class="inline w-3 h-3 text-emerald-600"></i> <?= e($t['current_stop_name']) ?></div>
                  <?php endif ?>
                  <?php if ($t['next_stop_name']): ?>
                    <div class="text-slate-500"><i data-lucide="arrow-right" class="inline w-3 h-3"></i> <?= e($t['next_stop_name']) ?></div>
                  <?php endif ?>
                  <?php if (!$t['current_stop_name'] && !$t['next_stop_name']): ?>—<?php endif ?>
                </td>
                <td class="px-4 py-3">
                  <?php $d = (int)$t['delay_minutes']; ?>
                  <?php if ($d > 0): ?>
                    <span class="px-2 py-0.5 rounded text-xs font-bold <?= $d>=30?'bg-rose-100 text-rose-700':'bg-amber-100 text-amber-700' ?>">+<?= $d ?> min</span>
                  <?php else: ?>
                    <span class="text-xs text-emerald-600">À l'heure</span>
                  <?php endif ?>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1.5 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full <?= $statusColor($t['status']) ?>"></span>
                    <?= e($t['status']) ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <a href="<?= e(url('voyages/' . $t['id'] . '/tracking')) ?>" class="inline-flex items-center gap-1 px-2 py-1 rounded bg-cb-primary text-white text-xs hover:bg-cb-secondary">
                    <i data-lucide="map" class="w-3 h-3"></i> Suivi
                  </a>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </div>

</div>

<?php $view->end() ?>
