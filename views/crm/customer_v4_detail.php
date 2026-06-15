<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app');
$tierColor = match($c['tier'] ?? 'basic') { 'platinum'=>'slate', 'gold'=>'amber', 'silver'=>'sky', default=>'slate' };
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="bg-gradient-to-r from-cb-primary to-cb-secondary text-white rounded-2xl p-6 shadow-soft">
    <div class="flex items-start justify-between">
      <div>
        <div class="text-xs uppercase opacity-80">FFN <?= e($c['frequent_flyer_number'] ?? '—') ?></div>
        <h1 class="text-2xl font-bold mt-1"><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: 'Client') ?></h1>
        <div class="text-sm opacity-90 mt-1"><?= e($c['phone_display'] ?? '') ?> · <?= e($c['email'] ?? '') ?></div>
      </div>
      <div class="text-right">
        <div class="px-3 py-1 rounded-full bg-white/20 inline-block uppercase text-xs font-bold"><?= e($c['tier']) ?></div>
        <div class="mt-2 text-3xl font-bold"><?= number_format((int)($c['loyalty_points'] ?? 0)) ?> pts</div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php foreach ([['Voyages',(int)$c['total_trips']],['Segments',(int)$c['total_segments']],['Distance km',(int)$c['total_distance_km']],['Dépensé FCFA',number_format((int)$c['total_spent'])]] as [$lbl,$val]): ?>
      <div class="bg-white rounded-xl p-4 border border-slate-100 shadow-soft text-center">
        <div class="text-xs uppercase font-semibold text-slate-500"><?= e($lbl) ?></div>
        <div class="text-xl font-bold text-slate-900 mt-1"><?= e($val) ?></div>
      </div>
    <?php endforeach ?>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4 text-center">
      <div class="text-xs text-slate-500 uppercase mb-1">RFM Recency</div>
      <div class="text-3xl font-bold text-cb-primary"><?= e($c['rfm_recency'] ?? '—') ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4 text-center">
      <div class="text-xs text-slate-500 uppercase mb-1">RFM Frequency</div>
      <div class="text-3xl font-bold text-cb-primary"><?= e($c['rfm_frequency'] ?? '—') ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4 text-center">
      <div class="text-xs text-slate-500 uppercase mb-1">RFM Monetary</div>
      <div class="text-3xl font-bold text-cb-primary"><?= e($c['rfm_monetary'] ?? '—') ?></div>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100"><h2 class="font-bold text-slate-900">Historique voyages</h2></div>
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">Voyage</th>
          <th class="px-3 py-2 text-left">Trajet</th>
          <th class="px-3 py-2 text-center">Cl</th>
          <th class="px-3 py-2 text-right">Km</th>
          <th class="px-3 py-2 text-right">FCFA</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($c['history'] as $h): ?>
          <tr>
            <td class="px-3 py-2 text-xs"><?= e(date('d/m/Y', strtotime($h['flown_at']))) ?></td>
            <td class="px-3 py-2 font-mono text-xs"><?= e($h['trip_code'] ?? '—') ?></td>
            <td class="px-3 py-2 text-xs"><?= e($h['departure_city'] ?? '—') ?> → <?= e($h['arrival_city'] ?? '—') ?></td>
            <td class="px-3 py-2 text-center font-mono"><?= e($h['booking_class'] ?? '—') ?></td>
            <td class="px-3 py-2 text-right text-xs"><?= number_format((int)$h['distance_km']) ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$h['revenue_fcfa']) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (empty($c['history'])): ?><tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Pas d'historique enregistré.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($c['complaints'])): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100"><h2 class="font-bold text-slate-900">Réclamations</h2></div>
      <ul class="divide-y divide-slate-100">
        <?php foreach ($c['complaints'] as $cp): ?>
          <li class="px-4 py-3 text-sm">
            <div class="flex items-center justify-between">
              <span class="px-2 py-0.5 rounded text-xs font-bold uppercase bg-rose-100 text-rose-700"><?= e($cp['severity']) ?></span>
              <span class="text-xs text-slate-500"><?= e(date('d/m/Y', strtotime($cp['opened_at']))) ?></span>
            </div>
            <div class="mt-1 text-slate-700"><?= e($cp['category']) ?> — <?= e($cp['description']) ?></div>
            <?php if ($cp['resolution']): ?><div class="text-xs text-emerald-700 mt-1">Résolution : <?= e($cp['resolution']) ?></div><?php endif ?>
          </li>
        <?php endforeach ?>
      </ul>
    </div>
  <?php endif ?>
</div>
<?php $view->end() ?>
