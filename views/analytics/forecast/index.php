<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
  $forecasts = $forecasts ?? [];
  // Group by line for the chart
  $byLine = [];
  foreach ($forecasts as $f) {
    $key = $f['line_code'] ?? '?';
    $byLine[$key][] = $f;
  }
  // Top lines by total expected pax
  $lineTotals = [];
  foreach ($byLine as $code => $rows) { $lineTotals[$code] = array_sum(array_column($rows, 'expected_pax')); }
  arsort($lineTotals);
  $topLines = array_slice($lineTotals, 0, 5, true);
?>
<div class="space-y-5">

  <!-- Header -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-8 h-8 rounded-xl bg-violet-600 flex items-center justify-center">
          <i data-lucide="trending-up" class="w-4 h-4 text-white"></i>
        </span>
        Prévisions de demande
      </h1>
      <p class="text-xs text-slate-400 mt-0.5">Forecast voyageurs basé sur la moyenne mobile 4 semaines</p>
    </div>
    <form method="get" class="flex items-center gap-2">
      <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-xl px-3 py-1.5">
        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
        <input type="date" name="from" value="<?= e($from) ?>" class="text-sm border-none outline-none bg-transparent w-32">
        <span class="text-slate-300">→</span>
        <input type="date" name="to" value="<?= e($to) ?>" class="text-sm border-none outline-none bg-transparent w-32">
      </div>
      <button class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700 transition">
        <i data-lucide="search" class="w-3.5 h-3.5 inline mr-1"></i>Afficher
      </button>
      <?php if (can('forecast.compute')): ?>
        <button name="recompute" value="1" class="px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-bold hover:bg-amber-600 transition flex items-center gap-1.5">
          <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>Recalculer
        </button>
      <?php endif ?>
    </form>
  </div>

  <?php if (!empty($forecasts)): ?>
  <!-- Top lines chart -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-sm">Prévisions journalières — Total toutes lignes</h3>
        <p class="text-[10px] text-slate-400 mt-0.5">Passagers attendus par date</p>
      </div>
      <div class="p-4"><canvas id="forecastChart" height="180"></canvas></div>
    </div>

    <!-- Top 5 lines by pax -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-sm">Top 5 lignes attendues</h3>
        <p class="text-[10px] text-slate-400 mt-0.5">Sur la période sélectionnée</p>
      </div>
      <div class="p-5 space-y-3">
        <?php
          $maxPax = max(1, max(array_values($topLines)));
          foreach ($topLines as $code => $total):
            $pct = round($total / $maxPax * 100);
        ?>
          <div>
            <div class="flex justify-between text-xs mb-1">
              <span class="font-bold text-slate-900 font-mono"><?= e($code) ?></span>
              <span class="text-violet-700 font-bold"><?= number_format($total) ?> pax</span>
            </div>
            <div class="h-2 rounded-full bg-slate-100">
              <div class="h-full rounded-full bg-violet-500" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>
  <?php endif ?>

  <!-- Detail table -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <div>
        <h3 class="font-bold text-slate-900 text-sm">Détail des prévisions</h3>
        <p class="text-[10px] text-slate-400 mt-0.5"><?= count($forecasts) ?> prévisions sur la période</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 border-b border-slate-100">
          <tr class="text-[10px] text-slate-400 uppercase tracking-wider">
            <th class="px-5 py-3 text-left font-semibold">Date</th>
            <th class="px-5 py-3 text-left font-semibold">Ligne</th>
            <th class="px-5 py-3 text-right font-semibold">Pax prévus</th>
            <th class="px-5 py-3 text-right font-semibold">Confiance</th>
            <th class="px-5 py-3 text-left font-semibold">Méthode</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($forecasts as $f):
            $conf = (int)($f['confidence_pct'] ?? 0);
            $confCls = $conf >= 80 ? 'bg-emerald-100 text-emerald-700' : ($conf >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600');
          ?>
          <tr class="hover:bg-violet-50/20 transition">
            <td class="px-5 py-3 text-xs font-semibold text-slate-700">
              <?= e(date('D d/m/Y', strtotime($f['forecast_date']))) ?>
            </td>
            <td class="px-5 py-3">
              <span class="font-mono font-black text-xs text-violet-700"><?= e($f['line_code'] ?? '—') ?></span>
              <?php if (!empty($f['line_name'])): ?>
                <span class="text-xs text-slate-500 ml-1.5"><?= e($f['line_name']) ?></span>
              <?php endif ?>
            </td>
            <td class="px-5 py-3 text-right">
              <span class="font-black text-lg text-violet-700"><?= number_format((int)$f['expected_pax']) ?></span>
              <span class="text-xs text-slate-400 ml-1">pax</span>
            </td>
            <td class="px-5 py-3 text-right">
              <span class="text-[10px] font-bold px-2.5 py-1 rounded-full <?= $confCls ?>"><?= $conf ?>%</span>
            </td>
            <td class="px-5 py-3 text-xs text-slate-500"><?= e($f['method'] ?? 'moving_avg') ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($forecasts)): ?>
          <tr><td colspan="5" class="px-5 py-14 text-center">
            <i data-lucide="trending-up" class="w-10 h-10 text-violet-200 mx-auto mb-3"></i>
            <p class="text-slate-400 text-sm">Aucune prévision disponible pour cette période.</p>
            <p class="text-slate-300 text-xs mt-1">Cliquez sur <strong>Recalculer</strong> pour générer les forecasts.</p>
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
  const forecasts = <?= json_encode(array_values($forecasts ?? [])) ?>;
  if (!forecasts.length) return;

  // Aggregate by date
  const byDate = {};
  forecasts.forEach(f => {
    if (!byDate[f.forecast_date]) byDate[f.forecast_date] = 0;
    byDate[f.forecast_date] += parseInt(f.expected_pax || 0);
  });
  const labels = Object.keys(byDate).sort().map(d => {
    const dt = new Date(d);
    return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
  });
  const data = Object.keys(byDate).sort().map(d => byDate[d]);

  const ctx = document.getElementById('forecastChart');
  if (ctx) {
    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Pax prévus',
          data,
          borderColor: '#7c3aed',
          backgroundColor: 'rgba(124,58,237,.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#7c3aed',
          pointRadius: 4,
        }],
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ctx.raw + ' pax' } },
        },
        scales: {
          x: { grid: { display: false } },
          y: { min: 0, grid: { color: '#f1f5f9' } },
        },
      },
    });
  }
})();
</script>
<?php $view->end() ?>
