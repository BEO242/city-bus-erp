<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('styles') ?>
<style>
  .kpi-gauge { position:relative; display:inline-flex; align-items:center; justify-content:center; }
  .kpi-ring { transform:rotate(-90deg); transform-origin:50% 50%; }
  @keyframes dashIn { from { stroke-dashoffset: 301 } }
  .ring-anim { animation: dashIn .9s ease forwards; }
</style>
<?php $view->end() ?>
<?php $view->start('content') ?>
<?php
  $latest    = !empty($timeline) ? end($timeline) : [];
  $prevRow   = count($timeline) >= 2 ? $timeline[count($timeline)-2] : [];
  $timeline  = array_reverse($timeline);

  // Helper: trend arrow
  $trend = function(float $cur, float $prev, bool $lowerIsBetter = false): string {
    if ($prev == 0) return '';
    $delta = $cur - $prev;
    if ($lowerIsBetter) $delta = -$delta;
    if ($delta > 0.5)  return '<span class="text-emerald-600 text-xs font-bold">↑</span>';
    if ($delta < -0.5) return '<span class="text-rose-600 text-xs font-bold">↓</span>';
    return '<span class="text-slate-400 text-xs">→</span>';
  };
?>
<div class="space-y-5">

  <!-- Header -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-8 h-8 rounded-xl bg-cb-primary flex items-center justify-center">
          <i data-lucide="gauge" class="w-4 h-4 text-white"></i>
        </span>
        Analytique & KPIs
      </h1>
      <p class="text-xs text-slate-400 mt-0.5">Performance opérationnelle · <?= e(date_fr($from ?? date('Y-m-d', strtotime('-7 days')))) ?> → <?= e(date_fr($to ?? date('Y-m-d'))) ?></p>
    </div>
    <form method="get" class="flex items-center gap-2 flex-wrap">
      <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-xl px-3 py-1.5">
        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
        <input type="date" name="from" value="<?= e($from ?? date('Y-m-d', strtotime('-7 days'))) ?>"
               class="text-sm border-none outline-none bg-transparent w-32">
        <span class="text-slate-300">→</span>
        <input type="date" name="to" value="<?= e($to ?? date('Y-m-d')) ?>"
               class="text-sm border-none outline-none bg-transparent w-32">
      </div>
      <button class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-secondary transition">
        <i data-lucide="search" class="w-3.5 h-3.5 inline mr-1"></i>Afficher
      </button>
      <?php if (can('kpi.recompute')): ?>
        <button name="recompute" value="1" class="px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition flex items-center gap-1.5">
          <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>Recalculer
        </button>
      <?php endif ?>
    </form>
  </div>

  <?php if ($latest): ?>

  <!-- ─── Scorecard row ─── -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

    <!-- Load Factor -->
    <?php $lf = (float)($latest['load_factor_pct'] ?? 0); $lfColor = $lf >= 80 ? 'emerald' : ($lf >= 60 ? 'amber' : 'rose'); ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-col items-center gap-2">
      <div class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Load Factor</div>
      <div class="kpi-gauge w-20 h-20">
        <svg width="80" height="80" viewBox="0 0 100 100">
          <circle cx="50" cy="50" r="42" fill="none" stroke="#f1f5f9" stroke-width="10"/>
          <circle cx="50" cy="50" r="42" fill="none" stroke="<?= $lfColor === 'emerald' ? '#10b981' : ($lfColor === 'amber' ? '#f59e0b' : '#ef4444') ?>"
                  stroke-width="10" stroke-linecap="round" class="kpi-ring ring-anim"
                  stroke-dasharray="<?= round($lf / 100 * 264, 1) ?> 264"/>
        </svg>
        <span class="absolute text-lg font-black text-<?= $lfColor ?>-700"><?= round($lf) ?>%</span>
      </div>
      <div class="text-[10px] text-slate-400">
        <?= $trend($lf, (float)($prevRow['load_factor_pct'] ?? $lf)) ?>
        <?php if (!empty($prevRow)): ?> vs <?= round((float)($prevRow['load_factor_pct'] ?? $lf)) ?>% <?php endif ?>
      </div>
    </div>

    <!-- OTP -->
    <?php $otp = (float)($latest['otp_pct'] ?? 0); $otpColor = $otp >= 85 ? 'emerald' : ($otp >= 70 ? 'amber' : 'rose'); ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-col items-center gap-2">
      <div class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Ponctualité OTP</div>
      <div class="kpi-gauge w-20 h-20">
        <svg width="80" height="80" viewBox="0 0 100 100">
          <circle cx="50" cy="50" r="42" fill="none" stroke="#f1f5f9" stroke-width="10"/>
          <circle cx="50" cy="50" r="42" fill="none" stroke="<?= $otpColor === 'emerald' ? '#10b981' : ($otpColor === 'amber' ? '#f59e0b' : '#ef4444') ?>"
                  stroke-width="10" stroke-linecap="round" class="kpi-ring ring-anim"
                  stroke-dasharray="<?= round($otp / 100 * 264, 1) ?> 264"/>
        </svg>
        <span class="absolute text-lg font-black text-<?= $otpColor ?>-700"><?= round($otp) ?>%</span>
      </div>
      <div class="text-[10px] text-slate-400">Cible ≥ 85%</div>
    </div>

    <!-- Cancellation -->
    <?php $can = (float)($latest['cancellation_rate'] ?? 0); $canColor = $can <= 3 ? 'emerald' : ($can <= 8 ? 'amber' : 'rose'); ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-col items-center gap-2">
      <div class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Annulations</div>
      <div class="text-3xl font-black text-<?= $canColor ?>-700 mt-2"><?= round($can, 1) ?>%</div>
      <div class="w-full h-1.5 rounded-full bg-slate-100 mt-1">
        <div class="h-full rounded-full bg-<?= $canColor ?>-500" style="width:<?= min($can * 5, 100) ?>%"></div>
      </div>
      <div class="text-[10px] text-slate-400">Seuil critique 8%</div>
    </div>

    <!-- Marge -->
    <?php $marge = (float)($latest['margin_pct'] ?? 0); $margeColor = $marge >= 15 ? 'emerald' : ($marge >= 5 ? 'amber' : 'rose'); ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-col items-center gap-2">
      <div class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Marge nette</div>
      <div class="text-3xl font-black mt-2 <?= $marge >= 0 ? 'text-'.$margeColor.'-700' : 'text-rose-700' ?>">
        <?= $marge >= 0 ? '+' : '' ?><?= round($marge, 1) ?>%
      </div>
      <div class="text-[10px] text-<?= $margeColor ?>-600 font-semibold">
        <?= $marge >= 15 ? '✓ Excellent' : ($marge >= 5 ? '~ Correct' : '↓ Sous seuil') ?>
      </div>
    </div>

    <!-- RASK -->
    <?php $rask = (int)($latest['rask'] ?? 0); ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-col items-center gap-2">
      <div class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">RASK</div>
      <div class="text-xs text-slate-300 -mt-1">Recette/siège-km</div>
      <div class="text-2xl font-black text-violet-700 mt-1"><?= number_format($rask) ?></div>
      <div class="text-[10px] text-slate-400">FCFA / siège·km</div>
    </div>

    <!-- CASK -->
    <?php $cask = (int)($latest['cask'] ?? 0); ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-col items-center gap-2">
      <div class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">CASK</div>
      <div class="text-xs text-slate-300 -mt-1">Coût/siège-km</div>
      <div class="text-2xl font-black text-slate-700 mt-1"><?= number_format($cask) ?></div>
      <?php $spread = $rask - $cask; ?>
      <div class="text-[10px] <?= $spread >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> font-semibold">
        Spread <?= $spread >= 0 ? '+' : '' ?><?= number_format($spread) ?>
      </div>
    </div>
  </div>

  <!-- ─── Revenue vs Cost bar chart ─── -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <div>
          <h3 class="font-bold text-slate-900 text-sm">Recettes vs Coûts sur la période</h3>
          <p class="text-[10px] text-slate-400 mt-0.5">Évolution quotidienne FCFA</p>
        </div>
        <div class="flex items-center gap-3 text-xs">
          <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-emerald-500 inline-block"></span>Recettes</span>
          <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-rose-400 inline-block"></span>Coûts</span>
        </div>
      </div>
      <div class="p-4"><canvas id="revCostChart" height="180"></canvas></div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-sm">Répartition recettes</h3>
        <p class="text-[10px] text-slate-400 mt-0.5">Par type sur la période</p>
      </div>
      <div class="p-4 flex flex-col gap-2">
        <?php
          $totRev = max(1, array_sum(array_column($timeline, 'revenue_total')));
          $revenueBreakdown = [
            ['label' => 'Voyageurs', 'key' => 'revenue_pax', 'color' => 'bg-cb-primary'],
            ['label' => 'Bagages',   'key' => 'revenue_baggage', 'color' => 'bg-amber-500'],
            ['label' => 'Fret',      'key' => 'revenue_parcel', 'color' => 'bg-violet-500'],
            ['label' => 'Autres',    'key' => 'revenue_other', 'color' => 'bg-slate-400'],
          ];
          foreach ($revenueBreakdown as $rb):
            $sum = array_sum(array_column($timeline, $rb['key']));
            $pct = $totRev > 0 ? round($sum / $totRev * 100) : 0;
            if ($sum <= 0) continue;
        ?>
          <div>
            <div class="flex justify-between text-xs mb-1">
              <span class="text-slate-600 font-medium"><?= $rb['label'] ?></span>
              <span class="text-slate-500 font-mono"><?= number_format($sum) ?> F <span class="text-slate-400">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 rounded-full bg-slate-100">
              <div class="h-full rounded-full <?= $rb['color'] ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach ?>
        <?php if (empty($timeline)): ?>
          <p class="text-sm text-slate-400 text-center py-6">Aucune donnée disponible</p>
        <?php endif ?>
      </div>
    </div>
  </div>

  <!-- ─── Load factor chart ─── -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-sm">Load Factor & OTP</h3>
        <p class="text-[10px] text-slate-400 mt-0.5">Évolution quotidienne (%)</p>
      </div>
      <div class="p-4"><canvas id="lfOtpChart" height="160"></canvas></div>
    </div>

    <!-- Per-line KPIs -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-sm">KPIs par ligne</h3>
        <p class="text-[10px] text-slate-400 mt-0.5">Dernière snapshot disponible</p>
      </div>
      <?php if (!empty($perLine)): ?>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead class="bg-slate-50 border-b border-slate-100">
              <tr class="text-[10px] text-slate-400 uppercase tracking-wider">
                <th class="px-4 py-2.5 text-left font-semibold">Ligne</th>
                <th class="px-4 py-2.5 text-right font-semibold">Load%</th>
                <th class="px-4 py-2.5 text-right font-semibold">OTP%</th>
                <th class="px-4 py-2.5 text-right font-semibold">Recettes</th>
                <th class="px-4 py-2.5 text-right font-semibold">Marge%</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php foreach ($perLine as $pl):
                $plLf    = (float)($pl['load_factor_pct'] ?? 0);
                $plMarge = (float)($pl['margin_pct'] ?? 0);
              ?>
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-2.5 font-bold text-cb-primary"><?= e($pl['line_code'] ?? '—') ?></td>
                <td class="px-4 py-2.5 text-right">
                  <span class="font-mono font-bold <?= $plLf >= 80 ? 'text-emerald-600' : ($plLf >= 60 ? 'text-amber-600' : 'text-rose-600') ?>"><?= round($plLf) ?>%</span>
                </td>
                <td class="px-4 py-2.5 text-right font-mono"><?= round((float)($pl['otp_pct'] ?? 0)) ?>%</td>
                <td class="px-4 py-2.5 text-right font-mono text-slate-600"><?= number_format((int)($pl['revenue_total'] ?? 0)) ?></td>
                <td class="px-4 py-2.5 text-right font-bold <?= $plMarge >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                  <?= $plMarge >= 0 ? '+' : '' ?><?= round($plMarge, 1) ?>%
                </td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="flex flex-col items-center justify-center py-10 text-slate-400">
          <i data-lucide="route" class="w-10 h-10 opacity-20 mb-2"></i>
          <p class="text-sm">Aucun KPI par ligne disponible</p>
          <p class="text-xs mt-1">Recalculez pour générer les snapshots.</p>
        </div>
      <?php endif ?>
    </div>
  </div>

  <?php endif // $latest ?>

  <!-- ─── Historical table ─── -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <div>
        <h3 class="font-bold text-slate-900 text-sm">Tableau d'évolution détaillé</h3>
        <p class="text-[10px] text-slate-400 mt-0.5"><?= count($timeline) ?> jours de données</p>
      </div>
      <?php if (!empty($timeline)): ?>
        <div class="text-xs text-slate-400">
          Moy. Load: <strong class="text-slate-700"><?= round(array_sum(array_column($timeline,'load_factor_pct')) / count($timeline), 1) ?>%</strong>
          · Moy. OTP: <strong class="text-slate-700"><?= round(array_sum(array_column($timeline,'otp_pct')) / count($timeline), 1) ?>%</strong>
        </div>
      <?php endif ?>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 border-b border-slate-100">
          <tr class="text-[10px] text-slate-400 uppercase tracking-wider text-right">
            <th class="px-4 py-3 text-left font-semibold">Date</th>
            <th class="px-4 py-3 font-semibold">Voyages</th>
            <th class="px-4 py-3 font-semibold">Load%</th>
            <th class="px-4 py-3 font-semibold">OTP%</th>
            <th class="px-4 py-3 font-semibold">Annul.%</th>
            <th class="px-4 py-3 font-semibold">Recettes</th>
            <th class="px-4 py-3 font-semibold">Coûts</th>
            <th class="px-4 py-3 font-semibold">Marge%</th>
            <th class="px-4 py-3 font-semibold">RASK</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($timeline as $row):
            $rowMarge = (float)($row['margin_pct'] ?? 0);
            $rowLf    = (float)($row['load_factor_pct'] ?? 0);
          ?>
          <tr class="hover:bg-cb-bg/20 transition">
            <td class="px-4 py-2.5 text-xs font-semibold text-slate-700"><?= e(date('D d/m', strtotime($row['snapshot_date'] ?? 'now'))) ?></td>
            <td class="px-4 py-2.5 text-right text-xs text-slate-500"><?= e($row['trips_total'] ?? '—') ?></td>
            <td class="px-4 py-2.5 text-right">
              <span class="font-mono text-xs font-bold <?= $rowLf >= 80 ? 'text-emerald-600' : ($rowLf >= 60 ? 'text-amber-600' : 'text-rose-600') ?>">
                <?= round($rowLf, 1) ?>%
              </span>
            </td>
            <td class="px-4 py-2.5 text-right font-mono text-xs text-slate-600"><?= round((float)($row['otp_pct'] ?? 0), 1) ?>%</td>
            <td class="px-4 py-2.5 text-right font-mono text-xs text-slate-500"><?= round((float)($row['cancellation_rate'] ?? 0), 1) ?>%</td>
            <td class="px-4 py-2.5 text-right font-mono text-xs text-emerald-700 font-semibold"><?= number_format((int)($row['revenue_total'] ?? 0)) ?></td>
            <td class="px-4 py-2.5 text-right font-mono text-xs text-slate-500"><?= number_format((int)($row['cost_total'] ?? 0)) ?></td>
            <td class="px-4 py-2.5 text-right">
              <span class="font-bold text-xs <?= $rowMarge >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                <?= $rowMarge >= 0 ? '+' : '' ?><?= round($rowMarge, 1) ?>%
              </span>
            </td>
            <td class="px-4 py-2.5 text-right font-mono text-xs text-violet-700"><?= number_format((int)($row['rask'] ?? 0)) ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($timeline)): ?>
          <tr><td colspan="9" class="px-5 py-14 text-center">
            <i data-lucide="bar-chart-2" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
            <p class="text-slate-400 text-sm">Aucun snapshot KPI sur cette période.</p>
            <p class="text-slate-300 text-xs mt-1">Cliquez sur <strong>Recalculer</strong> pour générer les KPIs.</p>
          </td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $view->end() ?>

<?php $view->start('scripts') ?>
<script>
(function () {
  const timeline = <?= json_encode(array_values(array_map(fn($r) => [
    'date'    => $r['snapshot_date'] ?? '',
    'rev'     => (int)($r['revenue_total'] ?? 0),
    'cost'    => (int)($r['cost_total'] ?? 0),
    'lf'      => (float)($r['load_factor_pct'] ?? 0),
    'otp'     => (float)($r['otp_pct'] ?? 0),
  ], array_reverse($timeline ?? [])))) ?>;

  const labels = timeline.map(r => {
    const d = new Date(r.date);
    return d.toLocaleDateString('fr-FR', {day:'2-digit', month:'2-digit'});
  });

  // Revenue vs Cost
  const rc = document.getElementById('revCostChart');
  if (rc && timeline.length) {
    new Chart(rc, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Recettes', data: timeline.map(r => r.rev),  backgroundColor: 'rgba(16,185,129,.85)', borderRadius: 4 },
          { label: 'Coûts',    data: timeline.map(r => r.cost), backgroundColor: 'rgba(239,68,68,.6)',   borderRadius: 4 },
        ],
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.raw.toLocaleString('fr-FR') + ' FCFA' } } },
        scales: {
          x: { grid: { display: false } },
          y: { ticks: { callback: v => (v/1000).toFixed(0) + 'K' }, grid: { color: '#f1f5f9' } },
        },
      },
    });
  }

  // LF & OTP line
  const lo = document.getElementById('lfOtpChart');
  if (lo && timeline.length) {
    new Chart(lo, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Load Factor', data: timeline.map(r => r.lf),  borderColor: '#C62828', backgroundColor: 'rgba(198,40,40,.08)', fill: true, tension: .4, pointRadius: 3 },
          { label: 'OTP',         data: timeline.map(r => r.otp), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.06)', fill: false, tension: .4, pointRadius: 3, borderDash: [4,3] },
        ],
      },
      options: {
        responsive: true,
        plugins: { tooltip: { mode: 'index', intersect: false } },
        scales: {
          x: { grid: { display: false } },
          y: { min: 0, max: 100, ticks: { callback: v => v + '%' }, grid: { color: '#f1f5f9' } },
        },
      },
    });
  }
})();
</script>
<?php $view->end() ?>
