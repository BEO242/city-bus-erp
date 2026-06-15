<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5" x-data="{ autoRefresh: true }" x-init="
  setInterval(() => { if (autoRefresh) location.reload(); }, 60000)
">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Tour de contrôle</h1>
      <p class="text-slate-500 text-sm">Vue temps réel des opérations · auto-refresh 60s · <?= e(date('H:i:s')) ?></p>
    </div>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" x-model="autoRefresh"> Auto-refresh
    </label>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft"><p class="text-xs text-slate-400">Aujourd'hui</p><p class="text-3xl font-bold"><?= (int)($kpis['total_today'] ?? 0) ?></p></div>
    <div class="bg-cb-primary text-white rounded-2xl p-5 shadow-soft"><p class="text-xs opacity-80">En cours</p><p class="text-3xl font-bold"><?= (int)($kpis['in_progress'] ?? 0) ?></p></div>
    <div class="bg-emerald-500 text-white rounded-2xl p-5 shadow-soft"><p class="text-xs opacity-80">Terminés</p><p class="text-3xl font-bold"><?= (int)($kpis['done'] ?? 0) ?></p></div>
    <div class="bg-rose-500 text-white rounded-2xl p-5 shadow-soft"><p class="text-xs opacity-80">Incidents</p><p class="text-3xl font-bold"><?= (int)($kpis['incidents'] ?? 0) ?></p></div>
    <div class="bg-slate-700 text-white rounded-2xl p-5 shadow-soft"><p class="text-xs opacity-80">Annulés</p><p class="text-3xl font-bold"><?= (int)($kpis['cancelled'] ?? 0) ?></p></div>
  </div>

  <?php if ($alerts): ?>
  <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6">
    <h2 class="font-semibold text-rose-900 mb-3 flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4"></i> Alertes actives (<?= count($alerts) ?>)
    </h2>
    <div class="space-y-2">
      <?php foreach ($alerts as $a): ?>
        <div class="flex justify-between items-center p-2 bg-white rounded-xl">
          <div>
            <span class="font-mono text-xs px-2 py-0.5 rounded <?= $a['severity']==='critical' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' ?>"><?= e($a['alert_type']) ?></span>
            <span class="font-semibold ml-2"><?= e($a['bus_code']) ?></span>
            <span class="text-sm text-slate-600 ml-2"><?= e($a['description']) ?></span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400"><?= e(date('H:i', strtotime((string)$a['occurred_at']))) ?></span>
            <?php if (can('gps.alerts.acknowledge')): ?>
            <form method="post" action="<?= e(url('ops/alerts/' . $a['id'] . '/ack')) ?>" class="inline">
              <?= csrf_field() ?>
              <button class="text-xs text-cb-primary hover:underline">Acquitter</button>
            </form>
            <?php endif ?>
          </div>
        </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100"><h2 class="font-semibold">Voyages d'aujourd'hui (<?= count($trips) ?>)</h2></div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-3 py-2 text-left">Voyage</th>
          <th class="px-3 py-2 text-left">Ligne</th>
          <th class="px-3 py-2 text-left">Véhicule</th>
          <th class="px-3 py-2 text-center">Départ prévu</th>
          <th class="px-3 py-2 text-center">Statut</th>
          <th class="px-3 py-2 text-right">Retard</th>
          <th class="px-3 py-2 text-right">Pax</th>
          <th class="px-3 py-2 text-center">Charge</th>
          <th class="px-3 py-2 text-center">GPS</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($trips as $t): ?>
          <tr class="<?= $t['gps_stale'] ? 'bg-rose-50/30' : '' ?>">
            <td class="px-3 py-2 font-mono"><a href="<?= e(url('voyages/' . $t['id'])) ?>" class="text-cb-primary hover:underline"><?= e($t['trip_code']) ?></a></td>
            <td class="px-3 py-2"><?= e($t['line_code']) ?></td>
            <td class="px-3 py-2 text-xs"><?= e($t['bus_code'] ?? '—') ?></td>
            <td class="px-3 py-2 text-center font-mono"><?= e(substr((string)$t['departure_scheduled'], 0, 5)) ?></td>
            <td class="px-3 py-2 text-center">
              <span class="px-2 py-0.5 rounded text-xs <?= match($t['status']) {
                'en_route' => 'bg-cb-bg text-cb-primary',
                'embarquement' => 'bg-amber-100 text-amber-700',
                'cloture' => 'bg-emerald-100 text-emerald-700',
                'annule' => 'bg-rose-100 text-rose-700',
                'incident' => 'bg-rose-200 text-rose-800',
                default => 'bg-slate-100',
              } ?>"><?= e($t['status']) ?></span>
            </td>
            <td class="px-3 py-2 text-right">
              <?php if ($t['delay_min'] !== null): ?>
                <span class="<?= abs($t['delay_min']) > 15 ? 'text-rose-600 font-bold' : ($t['delay_min'] > 0 ? 'text-amber-600' : 'text-emerald-600') ?>"><?= ($t['delay_min'] > 0 ? '+' : '') . $t['delay_min'] ?> min</span>
              <?php else: ?>—<?php endif ?>
            </td>
            <td class="px-3 py-2 text-right"><?= (int)$t['pax_count'] ?>/<?= (int)$t['seats'] ?></td>
            <td class="px-3 py-2 text-center">
              <span class="<?= $t['load_factor_pct'] >= 80 ? 'text-emerald-600 font-bold' : ($t['load_factor_pct'] >= 50 ? 'text-amber-600' : 'text-slate-500') ?>"><?= $t['load_factor_pct'] ?>%</span>
            </td>
            <td class="px-3 py-2 text-center text-xs">
              <?php if (!$t['gps_at']): ?><span class="text-slate-300">—</span>
              <?php elseif ($t['gps_stale']): ?><span class="text-rose-600 font-bold">⚠ <?= $t['gps_age_min'] ?>min</span>
              <?php else: ?><span class="text-emerald-600">✓ <?= $t['gps_age_min'] ?>min</span><?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (!$trips): ?><tr><td colspan="9" class="py-12 text-center text-slate-400">Aucun voyage aujourd'hui</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
