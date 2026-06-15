<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app');
$mins = function(int $m): string { return floor($m/60).'h'.str_pad((string)($m%60),2,'0',STR_PAD_LEFT); };
$st = $status;
?>
<?php $view->start('content') ?>
<div class="space-y-5">

  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('hos')) ?>" class="hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> HOS
    </a>
    <span>/</span>
    <span class="text-slate-800 font-semibold"><?= e($driver['first_name'].' '.$driver['last_name']) ?></span>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php
      $cards = [
        ['Conduite 24h', $mins($st['drive_24h_min']), $mins($st['limits']['daily_drive']), $st['pct']['daily']],
        ['Conduite 7j',  $mins($st['drive_7d_min']),  $mins($st['limits']['weekly_drive']), $st['pct']['weekly']],
        ['Conduite 14j', $mins($st['drive_14d_min']), $mins($st['limits']['biweekly_drive']), $st['pct']['biweekly']],
        ['Continu',      $mins($st['continuous_min']),$mins($st['limits']['continuous']), $st['limits']['continuous']>0?round($st['continuous_min']/$st['limits']['continuous']*100,1):0],
      ];
      foreach ($cards as [$lbl, $val, $lim, $pct]):
        $col = $pct >= 100 ? 'rose' : ($pct >= 80 ? 'amber' : ($pct >= 50 ? 'sky' : 'emerald'));
    ?>
      <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-soft">
        <div class="text-xs uppercase font-semibold text-slate-500"><?= e($lbl) ?></div>
        <div class="text-2xl font-bold text-<?= $col ?>-700 mt-1"><?= e($val) ?></div>
        <div class="text-xs text-slate-400">/ <?= e($lim) ?></div>
        <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full bg-<?= $col ?>-500" style="width: <?= min(100, $pct) ?>%"></div>
        </div>
        <div class="text-xs text-<?= $col ?>-700 font-bold text-right mt-0.5"><?= e($pct) ?>%</div>
      </div>
    <?php endforeach ?>
  </div>

  <!-- Service en cours / actions -->
  <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-soft">
    <h2 class="font-bold text-slate-900 mb-3">Service en cours</h2>
    <?php if ($st['current']): ?>
      <div class="flex items-center justify-between bg-slate-50 rounded-lg p-4">
        <div>
          <span class="px-3 py-1 rounded-full uppercase font-bold text-sm
            <?= $st['current']['duty_type']==='drive'?'bg-emerald-500 text-white':'bg-slate-300 text-slate-800' ?>">
            <?= e($st['current']['duty_type']) ?>
          </span>
          <span class="ml-3 text-sm text-slate-700">depuis <?= e(date('d/m/Y H:i', strtotime($st['current']['started_at']))) ?></span>
          <?php if (!empty($st['current']['location'])): ?>
            <span class="ml-3 text-xs text-slate-500"><i data-lucide="map-pin" class="inline w-3 h-3"></i> <?= e($st['current']['location']) ?></span>
          <?php endif ?>
        </div>
        <?php if (can('hos.log')): ?>
          <form method="post" action="<?= e(url('hos/driver/' . $driver['id'] . '/end/' . $st['current']['id'])) ?>">
            <?= csrf_field() ?>
            <button class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700">Clôturer</button>
          </form>
        <?php endif ?>
      </div>
    <?php else: ?>
      <p class="text-sm text-slate-500">Aucun service en cours.</p>
    <?php endif ?>

    <?php if (can('hos.log')): ?>
      <form method="post" action="<?= e(url('hos/driver/' . $driver['id'] . '/start')) ?>" class="mt-4 flex flex-wrap items-end gap-2 pt-4 border-t border-slate-100">
        <?= csrf_field() ?>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
          <select name="duty_type" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <option value="drive">Conduite</option>
            <option value="rest">Repos</option>
            <option value="break">Pause</option>
            <option value="other_work">Autre travail</option>
            <option value="available">Disponibilité</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Lieu</label>
          <input name="location" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Voyage (optionnel)</label>
          <input name="trip_id" type="number" class="px-3 py-2 rounded-lg border border-slate-200 text-sm" placeholder="ID">
        </div>
        <button class="px-4 py-2 rounded-lg bg-cb-primary text-white text-sm font-semibold hover:bg-cb-secondary">
          <i data-lucide="play" class="inline w-4 h-4"></i> Démarrer service
        </button>
      </form>
    <?php endif ?>
  </div>

  <!-- Violations -->
  <?php if (!empty($violations)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-bold text-slate-900 flex items-center gap-2">
          <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i> Violations
        </h2>
        <span class="text-xs text-slate-500"><?= count($violations) ?></span>
      </div>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-600">
          <tr>
            <th class="px-3 py-2 text-left">Date</th>
            <th class="px-3 py-2 text-left">Règle</th>
            <th class="px-3 py-2 text-left">Sévérité</th>
            <th class="px-3 py-2 text-left">Description</th>
            <th class="px-3 py-2 text-center">Acquit.</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($violations as $v):
            $sev = $v['severity']; $col = match($sev){'critical'=>'rose','major'=>'orange','minor'=>'amber','warning'=>'sky',default=>'slate'};
          ?>
            <tr>
              <td class="px-3 py-2 text-xs"><?= e(date('d/m H:i', strtotime($v['detected_at']))) ?></td>
              <td class="px-3 py-2 font-mono text-xs"><?= e($v['rule_code']) ?></td>
              <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold uppercase bg-<?= $col ?>-100 text-<?= $col ?>-700"><?= e($sev) ?></span></td>
              <td class="px-3 py-2 text-xs text-slate-700"><?= e($v['description']) ?></td>
              <td class="px-3 py-2 text-center">
                <?php if ((int)$v['acknowledged']): ?>
                  <span class="text-emerald-600 text-xs">✓ <?= e($v['ack_name'] ?? '') ?></span>
                <?php else: ?>
                  <span class="text-rose-600 text-xs">—</span>
                <?php endif ?>
              </td>
              <td class="px-3 py-2 text-right">
                <?php if (!(int)$v['acknowledged'] && can('hos.violations')): ?>
                  <form method="post" action="<?= e(url('hos/violation/' . $v['id'] . '/ack')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button class="text-emerald-700 text-xs hover:underline">Acquitter</button>
                  </form>
                <?php endif ?>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

  <!-- Logs -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
      <h2 class="font-bold text-slate-900">Journal de service</h2>
      <form method="get" class="flex gap-1">
        <input type="date" name="from" value="<?= e($from) ?>" class="px-2 py-1 rounded border border-slate-200 text-xs">
        <input type="date" name="to" value="<?= e($to) ?>" class="px-2 py-1 rounded border border-slate-200 text-xs">
        <button class="px-2 py-1 rounded bg-slate-700 text-white text-xs">→</button>
      </form>
    </div>
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-3 py-2 text-left">Type</th>
          <th class="px-3 py-2 text-left">Début</th>
          <th class="px-3 py-2 text-left">Fin</th>
          <th class="px-3 py-2 text-right">Durée</th>
          <th class="px-3 py-2 text-left">Voyage</th>
          <th class="px-3 py-2 text-left">Lieu</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($logs as $l):
          $col = match($l['duty_type']){'drive'=>'emerald','rest'=>'sky','break'=>'amber','other_work'=>'slate',default=>'slate'};
        ?>
          <tr>
            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold uppercase bg-<?= $col ?>-100 text-<?= $col ?>-700"><?= e($l['duty_type']) ?></span></td>
            <td class="px-3 py-2 text-xs font-mono"><?= e(date('d/m H:i', strtotime($l['started_at']))) ?></td>
            <td class="px-3 py-2 text-xs font-mono"><?= $l['ended_at'] ? e(date('d/m H:i', strtotime($l['ended_at']))) : '<span class="text-emerald-600">en cours</span>' ?></td>
            <td class="px-3 py-2 text-right text-xs font-mono"><?= $l['duration_min'] ? $mins((int)$l['duration_min']) : '—' ?></td>
            <td class="px-3 py-2 text-xs"><?= e($l['trip_code'] ?? '—') ?></td>
            <td class="px-3 py-2 text-xs text-slate-500"><?= e($l['location'] ?? '') ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (empty($logs)): ?>
          <tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Aucun log sur la période.</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
