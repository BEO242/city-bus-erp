<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/driver') ?>
<?php $view->start('content') ?>

<a href="<?= e(url('m/driver')) ?>" class="text-xs text-slate-500 inline-flex items-center gap-1 mb-2"><i data-lucide="chevron-left" class="w-3 h-3"></i> Mes voyages</a>

<div class="bg-gradient-to-br from-slate-900 to-slate-700 text-white rounded-2xl p-4 mb-3 shadow">
  <div class="text-xs opacity-70"><?= e($trip['line_code']) ?></div>
  <div class="text-xl font-bold"><?= e($trip['departure_city']) ?> → <?= e($trip['arrival_city']) ?></div>
  <div class="mt-2 flex items-center justify-between text-sm">
    <span><i data-lucide="clock" class="inline w-4 h-4"></i> Départ <?= e(substr($trip['departure_time'] ?? '', 0, 5)) ?></span>
    <span><i data-lucide="bus" class="inline w-4 h-4"></i> <?= e($trip['bus_code'] ?? '—') ?></span>
  </div>
  <div class="mt-1 text-xs opacity-80">Statut : <?= e($trip['status']) ?><?= !empty($trip['sub_status']) ? ' · ' . e($trip['sub_status']) : '' ?></div>
</div>

<h2 class="text-sm font-bold text-slate-700 mb-2 px-1">Itinéraire (<?= count($stops) ?> arrêts)</h2>

<div class="space-y-2">
  <?php foreach ($stops as $i => $s):
    $reached = !empty($s['actual_arrival']);
    $departed = !empty($s['actual_departure']);
    $skipped = (int)$s['is_skipped'];
    $status = $skipped ? 'skipped' : ($departed ? 'done' : ($reached ? 'current' : 'pending'));
    $colors = [
      'skipped' => 'bg-slate-100 text-slate-400 border-slate-200',
      'done'    => 'bg-emerald-50 border-emerald-200',
      'current' => 'bg-amber-50 border-amber-300 ring-2 ring-amber-300',
      'pending' => 'bg-white border-slate-200',
    ];
  ?>
    <div class="rounded-2xl border p-3 <?= $colors[$status] ?>">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
            <?= $status==='done'?'bg-emerald-500 text-white':($status==='current'?'bg-amber-500 text-white':($status==='skipped'?'bg-slate-300 text-slate-600':'bg-slate-200 text-slate-700')) ?>">
            <?= $i+1 ?>
          </span>
          <div>
            <div class="font-bold text-slate-900 text-sm"><?= e($s['stop_name'] ?? '—') ?></div>
            <div class="text-xs text-slate-500">
              Prévu : <?= $s['scheduled_arrival'] ? e(date('H:i', strtotime($s['scheduled_arrival']))) : '—' ?>
              <?php if ($s['scheduled_departure']): ?> → <?= e(date('H:i', strtotime($s['scheduled_departure']))) ?><?php endif ?>
            </div>
          </div>
        </div>
        <?php if ($status==='done'): ?>
          <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
        <?php elseif ($status==='skipped'): ?>
          <i data-lucide="x-circle" class="w-5 h-5 text-slate-400"></i>
        <?php endif ?>
      </div>

      <?php if ($reached): ?>
        <div class="mt-2 text-xs text-emerald-700">
          ✓ Arrivée <?= e(date('H:i', strtotime($s['actual_arrival']))) ?>
          <?php if ((int)$s['delay_min']>0): ?>
            <span class="ml-2 text-rose-600 font-bold">retard +<?= (int)$s['delay_min'] ?> min</span>
          <?php endif ?>
        </div>
      <?php endif ?>
      <?php if ($departed): ?>
        <div class="text-xs text-emerald-700">↗ Départ <?= e(date('H:i', strtotime($s['actual_departure']))) ?>
          <?php if((int)$s['pax_boarded']>0): ?> · <?= (int)$s['pax_boarded'] ?> emb.<?php endif ?>
          <?php if((int)$s['pax_alighted']>0): ?> · <?= (int)$s['pax_alighted'] ?> desc.<?php endif ?>
        </div>
      <?php endif ?>

      <?php if (!$skipped && !$departed && in_array($trip['status'], ['valide','embarquement','en_route','incident'])): ?>
        <div class="mt-2 flex gap-2">
          <?php if (!$reached): ?>
            <form method="post" action="<?= e(url('m/driver/trip/' . $trip['id'] . '/arrive/' . $s['stop_id'])) ?>" class="flex-1">
              <?= csrf_field() ?>
              <button class="w-full px-3 py-2 rounded-lg bg-emerald-500 text-white text-sm font-bold active:bg-emerald-600">
                <i data-lucide="map-pin" class="inline w-4 h-4"></i> Arrivée
              </button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= e(url('m/driver/trip/' . $trip['id'] . '/depart/' . $s['stop_id'])) ?>" class="flex-1 flex gap-1">
              <?= csrf_field() ?>
              <input name="pax_boarded" type="number" min="0" placeholder="Emb" class="w-14 px-1 py-2 rounded border border-slate-200 text-sm text-center">
              <input name="pax_alighted" type="number" min="0" placeholder="Des" class="w-14 px-1 py-2 rounded border border-slate-200 text-sm text-center">
              <button class="flex-1 px-3 py-2 rounded-lg bg-cb-primary text-white text-sm font-bold active:bg-cb-secondary">
                <i data-lucide="arrow-right" class="inline w-4 h-4"></i> Départ
              </button>
            </form>
          <?php endif ?>
        </div>
      <?php endif ?>
    </div>
  <?php endforeach ?>
</div>

<?php $view->end() ?>
