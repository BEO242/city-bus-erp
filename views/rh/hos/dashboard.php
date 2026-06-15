<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Conformité HOS</h1>
    <p class="text-slate-500 text-sm">Heures de conduite et de repos des chauffeurs · seuils légaux et alertes.</p>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Chauffeur</th>
          <th class="px-5 py-3 text-right">Aujourd'hui</th>
          <th class="px-5 py-3 text-right">7 jours</th>
          <th class="px-5 py-3 text-right">14 jours</th>
          <th class="px-5 py-3 text-right">Conduite continue</th>
          <th class="px-5 py-3 text-center">Statut</th>
          <th class="px-5 py-3 text-right"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($rows as $r): $st = $r['status']; $d = $r['driver']; ?>
        <?php
          $todayPct  = (int)round(($st['today_minutes']  / max(1, $st['limits']['daily_max'])) * 100);
          $weekPct   = (int)round(($st['week_minutes']   / max(1, $st['limits']['weekly_max'])) * 100);
          $biwPct    = (int)round(($st['biweek_minutes'] / max(1, $st['limits']['biweekly_max'])) * 100);
        ?>
        <tr class="<?= $st['blocking'] ? 'bg-rose-50/30' : '' ?>">
          <td class="px-5 py-3">
            <div class="font-semibold"><?= e($d['first_name'] . ' ' . $d['last_name']) ?></div>
            <div class="text-xs text-slate-400"><?= e($d['matricule']) ?></div>
          </td>
          <td class="px-5 py-3 text-right">
            <div class="font-mono"><?= number_format($st['today_minutes']/60, 1, ',', '') ?>h</div>
            <div class="bg-slate-100 rounded-full h-1.5 mt-1 overflow-hidden"><div class="<?= $todayPct >= 100 ? 'bg-rose-500' : ($todayPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500') ?> h-full" style="width: <?= min(100, $todayPct) ?>%"></div></div>
          </td>
          <td class="px-5 py-3 text-right">
            <div class="font-mono"><?= number_format($st['week_minutes']/60, 1, ',', '') ?>h</div>
            <div class="bg-slate-100 rounded-full h-1.5 mt-1 overflow-hidden"><div class="<?= $weekPct >= 100 ? 'bg-rose-500' : ($weekPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500') ?> h-full" style="width: <?= min(100, $weekPct) ?>%"></div></div>
          </td>
          <td class="px-5 py-3 text-right">
            <div class="font-mono"><?= number_format($st['biweek_minutes']/60, 1, ',', '') ?>h</div>
            <div class="bg-slate-100 rounded-full h-1.5 mt-1 overflow-hidden"><div class="<?= $biwPct >= 100 ? 'bg-rose-500' : ($biwPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500') ?> h-full" style="width: <?= min(100, $biwPct) ?>%"></div></div>
          </td>
          <td class="px-5 py-3 text-right font-mono"><?= $st['continuous_minutes'] > 0 ? number_format($st['continuous_minutes']/60, 1, ',', '') . 'h' : '—' ?></td>
          <td class="px-5 py-3 text-center">
            <?php if ($st['blocking']): ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-rose-100 text-rose-700">⚠ Bloquant</span>
            <?php elseif (!empty($st['warnings'])): ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">⚠ Attention</span>
            <?php else: ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">✓ OK</span>
            <?php endif ?>
          </td>
          <td class="px-5 py-3 text-right">
            <a href="<?= e(url('rh/hos/' . $d['id'])) ?>" class="text-cb-primary hover:underline">Détail</a>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
