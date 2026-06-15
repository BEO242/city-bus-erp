<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Tableau de bord Fret</h1>
      <p class="text-slate-500 text-sm">Vue d'ensemble de l'activité colis & fret.</p>
    </div>
    <div class="flex gap-2">
      <a href="<?= e(url('cargo/parcels/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary inline-flex items-center gap-2">
        <i data-lucide="package-plus" class="w-4 h-4"></i> Déposer un colis
      </a>
      <a href="<?= e(url('cargo/parcels')) ?>" class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50">Tous les colis</a>
    </div>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Aujourd'hui</p>
      <p class="text-3xl font-bold text-slate-900 mt-1"><?= (int)($kpis['today_count'] ?? 0) ?></p>
      <p class="text-xs text-slate-500 mt-1"><?= e(fcfa((int)($kpis['today_revenue'] ?? 0))) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">À charger</p>
      <p class="text-3xl font-bold text-amber-600 mt-1"><?= (int)($kpis['pending_deposits'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">En transit</p>
      <p class="text-3xl font-bold text-cb-primary mt-1"><?= (int)($kpis['in_transit'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Retraits en retard</p>
      <p class="text-3xl font-bold text-rose-600 mt-1"><?= (int)($kpis['overdue_pickups'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Recherche QR</p>
      <form method="post" action="<?= e(url('cargo/parcels/lookup')) ?>" class="mt-2">
        <?= csrf_field() ?>
        <div class="flex gap-1">
          <input name="code" placeholder="N° ou QR…" class="w-full px-2 py-1.5 text-sm rounded-lg border border-slate-200">
          <button class="px-2 rounded-lg bg-slate-900 text-white"><i data-lucide="search" class="w-3 h-3"></i></button>
        </div>
      </form>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-4">Top trajets (30 jours)</h2>
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($topRoutes as $r): ?>
            <tr>
              <td class="py-2"><?= e($r['origin']) ?> → <?= e($r['destination']) ?></td>
              <td class="py-2 text-right text-slate-500"><?= (int)$r['c'] ?> colis</td>
              <td class="py-2 text-right font-semibold"><?= e(fcfa((int)$r['revenue'])) ?></td>
            </tr>
          <?php endforeach ?>
          <?php if (!$topRoutes): ?>
            <tr><td class="py-6 text-center text-slate-400">Aucune donnée</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-4">Évolution mensuelle (6 mois)</h2>
      <?php $maxRev = max(array_column($monthlyRevenue, 'revenue') ?: [1]); ?>
      <div class="space-y-2">
        <?php foreach ($monthlyRevenue as $m): $pct = round(((int)$m['revenue'] / max(1, $maxRev)) * 100); ?>
          <div>
            <div class="flex justify-between text-sm">
              <span><?= e($m['ym']) ?></span>
              <span class="text-slate-500"><?= (int)$m['c'] ?> · <?= e(fcfa((int)$m['revenue'])) ?></span>
            </div>
            <div class="bg-slate-100 rounded h-2 overflow-hidden mt-1">
              <div class="bg-cb-primary h-full" style="width: <?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>

  <?php if ($overdue): ?>
  <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6">
    <h2 class="font-semibold text-rose-900 mb-3 flex items-center gap-2">
      <i data-lucide="alert-triangle" class="w-4 h-4"></i> Colis arrivés non retirés (>delai)
    </h2>
    <table class="w-full text-sm">
      <thead class="text-xs uppercase text-rose-700">
        <tr><th class="text-left py-2">N°</th><th class="text-left">Destinataire</th><th class="text-left">Agence</th><th class="text-right">Arrivé</th><th></th></tr>
      </thead>
      <tbody class="divide-y divide-rose-100">
        <?php foreach ($overdue as $p): ?>
          <tr>
            <td class="py-2"><a href="<?= e(url('cargo/parcels/' . $p['id'])) ?>" class="text-rose-700 hover:underline"><?= e($p['parcel_number']) ?></a></td>
            <td class="py-2"><?= e($p['recipient_name']) ?> · <?= e($p['recipient_phone']) ?></td>
            <td class="py-2"><?= e($p['dest_name']) ?></td>
            <td class="py-2 text-right"><?= e(date('d/m/Y', strtotime((string)$p['updated_at']))) ?></td>
            <td class="py-2 text-right"><a href="<?= e(url('cargo/parcels/' . $p['id'])) ?>" class="text-rose-700 hover:underline">Voir</a></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>
</div>
<?php $view->end() ?>
