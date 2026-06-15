<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/driver');
$mins = function(int $m): string { return floor($m/60).'h'.str_pad((string)($m%60),2,'0',STR_PAD_LEFT); };
?>
<?php $view->start('content') ?>

<?php if ($hos['current']): ?>
  <div class="bg-emerald-500 text-white rounded-2xl p-4 mb-3 shadow">
    <div class="text-xs uppercase opacity-80">Service en cours</div>
    <div class="text-2xl font-bold uppercase mt-1"><?= e($hos['current']['duty_type']) ?></div>
    <div class="text-sm opacity-90 mt-1">depuis <?= e(date('d/m H:i', strtotime($hos['current']['started_at']))) ?></div>
    <form method="post" action="<?= e(url('m/driver/hos/end/' . $hos['current']['id'])) ?>" class="mt-3">
      <?= csrf_field() ?>
      <button class="w-full px-3 py-2 rounded-lg bg-white text-emerald-700 font-bold active:bg-slate-100">Clôturer</button>
    </form>
  </div>
<?php else: ?>
  <div class="bg-white rounded-2xl p-4 mb-3 shadow-sm border border-slate-100">
    <div class="text-xs uppercase font-bold text-slate-500 mb-2">Démarrer un service</div>
    <form method="post" action="<?= e(url('m/driver/hos/start')) ?>" class="grid grid-cols-2 gap-2">
      <?= csrf_field() ?>
      <button name="duty_type" value="drive" class="px-3 py-3 rounded-lg bg-emerald-500 text-white font-bold active:bg-emerald-600">
        <i data-lucide="navigation" class="inline w-4 h-4"></i> Conduite
      </button>
      <button name="duty_type" value="rest" class="px-3 py-3 rounded-lg bg-sky-500 text-white font-bold active:bg-sky-600">
        <i data-lucide="bed" class="inline w-4 h-4"></i> Repos
      </button>
      <button name="duty_type" value="break" class="px-3 py-3 rounded-lg bg-amber-500 text-white font-bold active:bg-amber-600">
        <i data-lucide="coffee" class="inline w-4 h-4"></i> Pause
      </button>
      <button name="duty_type" value="other_work" class="px-3 py-3 rounded-lg bg-slate-500 text-white font-bold active:bg-slate-600">
        <i data-lucide="wrench" class="inline w-4 h-4"></i> Autre
      </button>
    </form>
  </div>
<?php endif ?>

<div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
  <div class="text-xs uppercase font-bold text-slate-500 mb-3">Mes limites</div>
  <?php
    $rows = [
      ['Conduite 24h', $hos['drive_24h_min'], $hos['limits']['daily_drive']],
      ['Conduite 7j',  $hos['drive_7d_min'],  $hos['limits']['weekly_drive']],
      ['Conduite 14j', $hos['drive_14d_min'], $hos['limits']['biweekly_drive']],
      ['Continu',      $hos['continuous_min'],$hos['limits']['continuous']],
    ];
    foreach ($rows as [$lbl, $v, $lim]):
      $pct = $lim>0?round($v/$lim*100,1):0; $col = $pct>=100?'rose':($pct>=80?'amber':($pct>=50?'sky':'emerald'));
  ?>
    <div class="mb-3">
      <div class="flex items-center justify-between text-xs mb-1">
        <span class="font-semibold text-slate-700"><?= e($lbl) ?></span>
        <span class="font-mono"><?= $mins($v) ?> / <?= $mins($lim) ?> · <strong class="text-<?= $col ?>-700"><?= $pct ?>%</strong></span>
      </div>
      <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full bg-<?= $col ?>-500" style="width: <?= min(100,$pct) ?>%"></div>
      </div>
    </div>
  <?php endforeach ?>
</div>

<?php if (!empty($logs)): ?>
  <h2 class="text-sm font-bold text-slate-700 mt-4 mb-2 px-1">Mes 48 dernières heures</h2>
  <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <?php foreach (array_slice($logs, 0, 20) as $l):
      $col = match($l['duty_type']){'drive'=>'emerald','rest'=>'sky','break'=>'amber',default=>'slate'};
    ?>
      <div class="flex items-center justify-between px-3 py-2 border-b border-slate-100 last:border-0 text-xs">
        <span class="px-2 py-0.5 rounded font-bold uppercase bg-<?= $col ?>-100 text-<?= $col ?>-700"><?= e($l['duty_type']) ?></span>
        <span class="font-mono text-slate-700">
          <?= e(date('d/m H:i', strtotime($l['started_at']))) ?>
          → <?= $l['ended_at'] ? e(date('H:i', strtotime($l['ended_at']))) : '<span class="text-emerald-600">en cours</span>' ?>
        </span>
        <span class="font-mono text-slate-500"><?= $l['duration_min'] ? $mins((int)$l['duration_min']) : '—' ?></span>
      </div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<?php $view->end() ?>
