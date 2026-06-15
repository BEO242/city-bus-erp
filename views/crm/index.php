<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">CRM passagers</h1>
      <p class="text-slate-500 text-sm">Dossier client unifié, dédupliqué par numéro de téléphone.</p>
    </div>
    <div class="flex gap-2">
      <?php if (can('crm.edit')): ?>
        <form method="post" action="<?= e(url('crm/customers/backfill')) ?>" onsubmit="return confirm('Lancer le backfill ?')">
          <?= csrf_field() ?>
          <button class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50">Backfill historique</button>
        </form>
      <?php endif ?>
      <?php if (can('crm.export')): ?>
        <a href="<?= e(url('crm/customers/export')) ?>" class="px-4 py-2 rounded-xl bg-slate-900 text-white">Export CSV</a>
      <?php endif ?>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <div class="relative flex-1">
      <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
      <input name="q" value="<?= e($q) ?>" placeholder="Téléphone, nom, email…" class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200">
    </div>
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Rechercher</button>
  </form>

  <?php if (!$q): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
    <h2 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
      <i data-lucide="trophy" class="w-4 h-4 text-amber-600"></i> Top 10 clients
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
      <?php foreach ($top as $i => $c): ?>
        <a href="<?= e(url('crm/customers/' . $c['id'])) ?>" class="flex items-center justify-between p-3 rounded-xl bg-cb-bg/30 hover:bg-cb-bg/60">
          <div class="flex items-center gap-3">
            <span class="w-7 h-7 bg-cb-primary text-white rounded-full flex items-center justify-center text-xs font-bold"><?= $i+1 ?></span>
            <div>
              <p class="font-semibold"><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?: e($c['phone_display']) ?></p>
              <p class="text-xs text-slate-500"><?= (int)$c['total_trips'] ?> voyages · <?= e($c['phone_display']) ?></p>
            </div>
          </div>
          <span class="font-bold text-cb-primary"><?= e(fcfa((int)$c['total_spent'])) ?></span>
        </a>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Nom</th>
          <th class="px-5 py-3 text-left">Téléphone</th>
          <th class="px-5 py-3 text-left">Email</th>
          <th class="px-5 py-3 text-right">Voyages</th>
          <th class="px-5 py-3 text-right">Total dépensé</th>
          <th class="px-5 py-3 text-right">Dernier voyage</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($data['items'] as $c): ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-3 font-semibold"><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: '—') ?></td>
          <td class="px-5 py-3 font-mono text-xs"><?= e($c['phone_display'] ?? $c['phone_norm']) ?></td>
          <td class="px-5 py-3 text-slate-500"><?= e($c['email'] ?? '—') ?></td>
          <td class="px-5 py-3 text-right"><?= (int)$c['total_trips'] ?></td>
          <td class="px-5 py-3 text-right font-bold"><?= e(fcfa((int)$c['total_spent'])) ?></td>
          <td class="px-5 py-3 text-right text-xs text-slate-500"><?= $c['last_trip_at'] ? e(date('d/m/Y', strtotime((string)$c['last_trip_at']))) : '—' ?></td>
          <td class="px-5 py-3 text-right"><a href="<?= e(url('crm/customers/' . $c['id'])) ?>" class="text-cb-primary hover:underline">Voir</a></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$data['items']): ?>
        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Aucun client</td></tr>
      <?php endif ?>
      </tbody>
    </table>
    <?php if ($data['pages'] > 1): ?>
    <div class="p-4 flex justify-between items-center border-t border-slate-100">
      <span class="text-sm text-slate-500"><?= (int)$data['total'] ?> clients · page <?= (int)$data['page'] ?> / <?= (int)$data['pages'] ?></span>
      <div class="flex gap-2">
        <?php if ($data['page'] > 1): ?><a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $data['page'] - 1]))) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200">‹</a><?php endif ?>
        <?php if ($data['page'] < $data['pages']): ?><a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $data['page'] + 1]))) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200">›</a><?php endif ?>
      </div>
    </div>
    <?php endif ?>
  </div>
</div>
<?php $view->end() ?>
