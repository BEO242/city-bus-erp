<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app');
$pctColor = function(float $p): string {
  if ($p >= 100) return 'rose';
  if ($p >= 80) return 'amber';
  if ($p >= 50) return 'sky';
  return 'emerald';
};
$mins = function(int $m): string { return floor($m/60).'h'.str_pad((string)($m%60),2,'0',STR_PAD_LEFT); };
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="clock-9" class="w-6 h-6 text-cb-primary"></i> Hours of Service
      </h1>
      <p class="text-sm text-slate-500">Conformité temps de conduite chauffeurs · <?= count($fleet) ?> chauffeur(s)</p>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-4 py-3 text-left">Chauffeur</th>
          <th class="px-4 py-3 text-left">Service en cours</th>
          <th class="px-4 py-3 text-center">24h</th>
          <th class="px-4 py-3 text-center">7 jours</th>
          <th class="px-4 py-3 text-center">14 jours</th>
          <th class="px-4 py-3 text-center">Continu</th>
          <th class="px-4 py-3 text-center">Violations</th>
          <th class="px-4 py-3 text-right">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($fleet as $row):
          $st = $row['status']; $d = $row['driver'];
        ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3">
              <div class="font-semibold text-slate-900"><?= e($d['first_name'].' '.$d['last_name']) ?></div>
              <div class="text-xs text-slate-500"><?= e($d['phone'] ?? '') ?></div>
            </td>
            <td class="px-4 py-3 text-xs">
              <?php if ($st['current']): ?>
                <span class="px-2 py-0.5 rounded font-bold uppercase
                  <?= $st['current']['duty_type']==='drive'?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-600' ?>">
                  <?= e($st['current']['duty_type']) ?>
                </span>
                <div class="text-slate-500 mt-1">depuis <?= e(date('H:i', strtotime($st['current']['started_at']))) ?></div>
              <?php else: ?>
                <span class="text-slate-400">—</span>
              <?php endif ?>
            </td>
            <?php foreach (['daily','weekly','biweekly'] as $w):
              $p = $st['pct'][$w]; $col = $pctColor($p);
              $minutes = $w==='daily'?$st['drive_24h_min']:($w==='weekly'?$st['drive_7d_min']:$st['drive_14d_min']);
              $lim = $w==='daily'?$st['limits']['daily_drive']:($w==='weekly'?$st['limits']['weekly_drive']:$st['limits']['biweekly_drive']);
            ?>
              <td class="px-4 py-3 text-center">
                <div class="text-xs font-mono"><?= $mins($minutes) ?> / <?= $mins($lim) ?></div>
                <div class="mt-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-full bg-<?= $col ?>-500" style="width: <?= min(100, $p) ?>%"></div>
                </div>
                <div class="text-xs text-<?= $col ?>-700 font-bold mt-0.5"><?= e($p) ?>%</div>
              </td>
            <?php endforeach ?>
            <td class="px-4 py-3 text-center">
              <?php $cp = $st['limits']['continuous']>0 ? $st['continuous_min']/$st['limits']['continuous']*100 : 0; $col = $pctColor($cp); ?>
              <div class="text-xs font-mono"><?= $mins($st['continuous_min']) ?></div>
              <div class="text-xs text-<?= $col ?>-700 font-bold"><?= round($cp) ?>%</div>
            </td>
            <td class="px-4 py-3 text-center">
              <?php if ($row['unack_violations'] > 0): ?>
                <span class="px-2 py-1 rounded-full bg-rose-100 text-rose-700 text-xs font-bold"><?= $row['unack_violations'] ?></span>
              <?php else: ?>
                <span class="text-emerald-600 text-xs">✓</span>
              <?php endif ?>
            </td>
            <td class="px-4 py-3 text-right">
              <a href="<?= e(url('hos/driver/' . $d['id'])) ?>" class="text-cb-primary text-xs hover:underline">Détail →</a>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (empty($fleet)): ?>
          <tr><td colspan="8" class="px-4 py-12 text-center text-slate-400">Aucun chauffeur.</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
