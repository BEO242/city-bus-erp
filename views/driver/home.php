<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/driver');
$mins = function(int $m): string { return floor($m/60).'h'.str_pad((string)($m%60),2,'0',STR_PAD_LEFT); };
?>
<?php $view->start('content') ?>

<!-- HOS résumé compact -->
<div class="bg-white rounded-2xl p-4 shadow-sm mb-3 border border-slate-100">
  <div class="flex items-center justify-between mb-2">
    <span class="text-xs uppercase font-bold text-slate-500">Mes heures aujourd'hui</span>
    <a href="<?= e(url('m/driver/hos')) ?>" class="text-xs text-cb-primary">Détail →</a>
  </div>
  <div class="grid grid-cols-3 gap-2 text-center">
    <?php foreach ([['daily','24h',$hos['drive_24h_min'],$hos['limits']['daily_drive']],['weekly','7j',$hos['drive_7d_min'],$hos['limits']['weekly_drive']],['continuous','Continu',$hos['continuous_min'],$hos['limits']['continuous']]] as [$k,$lbl,$v,$lim]):
      $pct = $lim>0?round($v/$lim*100):0; $col = $pct>=100?'rose':($pct>=80?'amber':($pct>=50?'sky':'emerald'));
    ?>
      <div>
        <div class="text-[10px] text-slate-500 uppercase"><?= e($lbl) ?></div>
        <div class="text-base font-bold text-<?= $col ?>-700"><?= $mins($v) ?></div>
        <div class="h-1 rounded bg-slate-100 mt-0.5"><div class="h-full bg-<?= $col ?>-500 rounded" style="width: <?= min(100,$pct) ?>%"></div></div>
      </div>
    <?php endforeach ?>
  </div>
</div>

<h2 class="text-sm font-bold text-slate-700 mt-2 mb-2 px-1">Voyages assignés</h2>

<?php if (empty($trips)): ?>
  <div class="bg-white rounded-2xl p-8 text-center shadow-sm border border-slate-100">
    <i data-lucide="calendar-x" class="w-12 h-12 mx-auto text-slate-300 mb-2"></i>
    <p class="text-sm text-slate-500">Aucun voyage assigné aujourd'hui.</p>
  </div>
<?php else: ?>
  <div class="space-y-2">
    <?php foreach ($trips as $t): ?>
      <a href="<?= e(url('m/driver/trip/' . $t['id'])) ?>" class="block bg-white rounded-2xl p-4 shadow-sm border border-slate-100 active:bg-slate-50">
        <div class="flex items-center justify-between mb-2">
          <span class="font-mono font-bold text-cb-primary"><?= e($t['trip_code']) ?></span>
          <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-700"><?= e($t['status']) ?></span>
        </div>
        <div class="text-base font-bold text-slate-900"><?= e($t['departure_city']) ?> → <?= e($t['arrival_city']) ?></div>
        <div class="flex items-center gap-3 mt-1 text-xs text-slate-500">
          <span><i data-lucide="clock" class="inline w-3 h-3"></i> <?= e(substr($t['departure_time'] ?? '', 0, 5)) ?></span>
          <span><i data-lucide="bus" class="inline w-3 h-3"></i> <?= e($t['bus_code'] ?? '—') ?></span>
          <span class="ml-auto px-2 py-0.5 rounded bg-blue-100 text-blue-700 font-mono"><?= e($t['line_code']) ?></span>
        </div>
      </a>
    <?php endforeach ?>
  </div>
<?php endif ?>

<?php $view->end() ?>
