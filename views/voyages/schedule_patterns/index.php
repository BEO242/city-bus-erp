<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$dayNames = [1=>'L',2=>'M',3=>'M',4=>'J',5=>'V',6=>'S',7=>'D'];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Horaires récurrents</h1>
      <p class="text-slate-500 text-sm">Génère automatiquement les voyages futurs.</p>
    </div>
    <div class="flex gap-2">
      <?php if (can('voyages.schedule.manage')): ?>
        <form method="post" action="<?= e(url('voyages/schedules/generate-all')) ?>" class="inline">
          <?= csrf_field() ?>
          <button class="px-4 py-2 rounded-xl bg-amber-500 text-white">Générer tous les voyages</button>
        </form>
        <a href="<?= e(url('voyages/schedules/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white">+ Nouveau pattern</a>
      <?php endif ?>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Pattern</th>
          <th class="px-5 py-3 text-left">Ligne</th>
          <th class="px-5 py-3 text-left">Véhicule</th>
          <th class="px-5 py-3 text-left">Jours</th>
          <th class="px-5 py-3 text-center">Départ</th>
          <th class="px-5 py-3 text-left">Validité</th>
          <th class="px-5 py-3 text-left">Généré jusqu'à</th>
          <th class="px-5 py-3 text-center">Actif</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($patterns as $p): $days = array_map('intval', explode(',', (string)$p['days_of_week'])); ?>
        <tr>
          <td class="px-5 py-3 font-medium"><?= e($p['label']) ?></td>
          <td class="px-5 py-3"><?= e($p['line_code']) ?> · <?= e($p['line_name']) ?></td>
          <td class="px-5 py-3 text-xs"><?= e($p['bus_code'] ?? '—') ?></td>
          <td class="px-5 py-3">
            <?php for ($i = 1; $i <= 7; $i++): ?>
              <span class="<?= in_array($i, $days, true) ? 'bg-cb-primary text-white' : 'bg-slate-100 text-slate-400' ?> w-5 h-5 rounded text-xs inline-flex items-center justify-center mr-0.5"><?= $dayNames[$i] ?></span>
            <?php endfor ?>
          </td>
          <td class="px-5 py-3 text-center font-mono"><?= e(substr((string)$p['departure_time'], 0, 5)) ?></td>
          <td class="px-5 py-3 text-xs"><?= e($p['valid_from']) ?> → <?= e($p['valid_until'] ?? '∞') ?></td>
          <td class="px-5 py-3 text-xs"><?= e($p['last_generated_until'] ?? '—') ?></td>
          <td class="px-5 py-3 text-center"><?= $p['is_active'] ? '✓' : '×' ?></td>
          <td class="px-5 py-3 text-right space-x-2">
            <?php if (can('voyages.schedule.manage') && $p['is_active']): ?>
              <form method="post" action="<?= e(url('voyages/schedules/' . $p['id'] . '/generate')) ?>" class="inline">
                <?= csrf_field() ?>
                <button class="text-xs text-cb-primary hover:underline">Générer</button>
              </form>
              <form method="post" action="<?= e(url('voyages/schedules/' . $p['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Désactiver ?')">
                <?= csrf_field() ?>
                <button class="text-xs text-rose-600 hover:underline">Désactiver</button>
              </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$patterns): ?><tr><td colspan="9" class="px-5 py-12 text-center text-slate-400">Aucun pattern</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
