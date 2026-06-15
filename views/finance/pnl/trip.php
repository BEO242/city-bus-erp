<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$marginColor = (int)$pnl['margin_net'] >= 0 ? 'text-emerald-600' : 'text-rose-600';
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('finance/pnl')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <div class="flex items-start justify-between mt-2">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">P&L · <?= e($trip['trip_code']) ?></h1>
        <p class="text-slate-500 text-sm">
          Ligne <?= e($trip['line_code']) ?> · <?= e($trip['line_name']) ?> · Véhicule <?= e($trip['bus_code']) ?> · <?= e(date('d/m/Y', strtotime((string)$trip['trip_date']))) ?>
        </p>
      </div>
      <form method="post" action="<?= e(url('finance/pnl/trip/' . $trip['id'] . '/recompute')) ?>">
        <?= csrf_field() ?>
        <button class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm">Recalculer</button>
      </form>
    </div>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5">
      <p class="text-xs uppercase text-emerald-700">Recettes</p>
      <p class="text-2xl font-bold text-emerald-900"><?= e(fcfa((int)$pnl['revenue_total'])) ?></p>
      <p class="text-xs text-emerald-700 mt-1">HT : <?= e(fcfa((int)$pnl['revenue_ht'])) ?> · TVA : <?= e(fcfa((int)$pnl['tax_total'])) ?></p>
    </div>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
      <p class="text-xs uppercase text-amber-700">Coûts directs</p>
      <p class="text-2xl font-bold text-amber-900"><?= e(fcfa((int)$pnl['cost_direct_total'])) ?></p>
    </div>
    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
      <p class="text-xs uppercase text-slate-700">Coûts indirects</p>
      <p class="text-2xl font-bold text-slate-900"><?= e(fcfa((int)$pnl['cost_indirect_total'])) ?></p>
    </div>
    <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Marge nette</p>
      <p class="text-2xl font-bold <?= $marginColor ?>"><?= e(fcfa((int)$pnl['margin_net'])) ?></p>
      <p class="text-xs text-slate-500">Contributive : <?= e(fcfa((int)$pnl['margin_contribution'])) ?></p>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-3">Détail recettes</h2>
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <tr><td class="py-2">Billets passagers (<?= (int)$pnl['passengers_count'] ?>)</td><td class="py-2 text-right font-semibold"><?= e(fcfa((int)$pnl['revenue_tickets'])) ?></td></tr>
          <tr><td class="py-2">Bagages</td><td class="py-2 text-right font-semibold"><?= e(fcfa((int)$pnl['revenue_baggage'])) ?></td></tr>
          <tr><td class="py-2">Fret (<?= (int)$pnl['parcels_count'] ?> colis)</td><td class="py-2 text-right font-semibold"><?= e(fcfa((int)$pnl['revenue_cargo'])) ?></td></tr>
          <tr class="border-t-2 border-slate-300"><td class="py-2 font-bold">TOTAL TTC</td><td class="py-2 text-right font-bold"><?= e(fcfa((int)$pnl['revenue_total'])) ?></td></tr>
        </tbody>
      </table>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-3">Détail coûts</h2>
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <tr><td class="py-2 text-amber-700">Carburant (<?= number_format((float)$pnl['fuel_liters'], 1, ',', ' ') ?> L)</td><td class="py-2 text-right"><?= e(fcfa((int)$pnl['cost_fuel'])) ?></td></tr>
          <tr><td class="py-2 text-amber-700">Primes équipage</td><td class="py-2 text-right"><?= e(fcfa((int)$pnl['cost_crew_bonus'])) ?></td></tr>
          <tr><td class="py-2 text-amber-700">Péages</td><td class="py-2 text-right"><?= e(fcfa((int)$pnl['cost_tolls'])) ?></td></tr>
          <tr><td class="py-2 text-amber-700">Divers</td><td class="py-2 text-right"><?= e(fcfa((int)$pnl['cost_misc'])) ?></td></tr>
          <tr class="border-t border-amber-200"><td class="py-2 font-semibold">Sous-total directs</td><td class="py-2 text-right font-semibold text-amber-700"><?= e(fcfa((int)$pnl['cost_direct_total'])) ?></td></tr>
          <tr><td class="py-2 text-slate-500">Amortissement</td><td class="py-2 text-right"><?= e(fcfa((int)$pnl['cost_depreciation'])) ?></td></tr>
          <tr><td class="py-2 text-slate-500">Assurance</td><td class="py-2 text-right"><?= e(fcfa((int)$pnl['cost_insurance'])) ?></td></tr>
          <tr><td class="py-2 text-slate-500">Maintenance</td><td class="py-2 text-right"><?= e(fcfa((int)$pnl['cost_maintenance'])) ?></td></tr>
          <tr><td class="py-2 text-slate-500">Frais structure</td><td class="py-2 text-right"><?= e(fcfa((int)$pnl['cost_overhead'])) ?></td></tr>
          <tr class="border-t border-slate-300"><td class="py-2 font-semibold">Sous-total indirects</td><td class="py-2 text-right font-semibold text-slate-700"><?= e(fcfa((int)$pnl['cost_indirect_total'])) ?></td></tr>
          <tr class="border-t-2 border-slate-300"><td class="py-2 font-bold">TOTAL COÛTS</td><td class="py-2 text-right font-bold"><?= e(fcfa((int)$pnl['cost_direct_total'] + (int)$pnl['cost_indirect_total'])) ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
    <h2 class="font-semibold text-slate-900 mb-3">Volumétrie</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div><p class="text-xs text-slate-400">Distance</p><p class="font-bold"><?= $pnl['distance_km'] ? number_format((float)$pnl['distance_km'], 1, ',', ' ') . ' km' : '—' ?></p></div>
      <div><p class="text-xs text-slate-400">Passagers</p><p class="font-bold"><?= (int)$pnl['passengers_count'] ?></p></div>
      <div><p class="text-xs text-slate-400">Colis</p><p class="font-bold"><?= (int)$pnl['parcels_count'] ?></p></div>
      <div><p class="text-xs text-slate-400">Taux remplissage</p><p class="font-bold"><?= $pnl['load_factor_pct'] !== null ? number_format((float)$pnl['load_factor_pct'], 1, ',', '') . '%' : '—' ?></p></div>
    </div>
    <p class="text-xs text-slate-400 mt-4">Calculé le <?= e(date('d/m/Y H:i', strtotime((string)$pnl['computed_at']))) ?></p>
  </div>
</div>
<?php $view->end() ?>
