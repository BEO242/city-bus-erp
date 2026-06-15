<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/public'); ?>
<?php $view->start('content') ?>
<div class="max-w-3xl mx-auto">
  <div class="bg-white rounded-2xl shadow-soft border border-slate-100 p-6 mb-4">
    <div class="flex items-center justify-between mb-3">
      <span class="font-mono text-cb-primary text-lg"><?= e($trip['trip_code']) ?></span>
      <span class="text-xs text-slate-500"><?= e(date('d/m/Y', strtotime($trip['trip_date']))) ?></span>
    </div>
    <h1 class="text-2xl font-bold text-slate-900"><?= e($trip['departure_city']) ?> → <?= e($trip['arrival_city']) ?></h1>
    <p class="text-slate-600 mt-1">Départ <?= e(substr($trip['departure_time'] ?? '', 0, 5)) ?> · <?= e($trip['line_code']) ?></p>
  </div>

  <h2 class="text-xl font-bold text-slate-900 mb-3">Choisissez votre classe</h2>

  <div class="space-y-2">
    <?php foreach ($inventory as $inv):
      $avail = max(0, (int)$inv['capacity'] - (int)$inv['sold_count'] - (int)$inv['blocked_count']);
      $disabled = $avail === 0;
    ?>
      <div class="bg-white rounded-2xl shadow-soft border border-slate-100 p-4 flex items-center justify-between <?= $disabled?'opacity-50':'' ?>">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-xl" style="background: <?= e($inv['color_hex'] ?? '#64748b') ?>">
            <?= e($inv['class_code']) ?>
          </div>
          <div>
            <div class="font-bold text-slate-900"><?= e($inv['class_name'] ?? 'Classe ' . $inv['class_code']) ?></div>
            <div class="text-xs text-slate-500">
              <?= $inv['priority_boarding']?'Embarquement prioritaire · ':'' ?>
              Remboursable à <?= (int)$inv['refund_policy_pct'] ?>%
            </div>
            <div class="text-xs text-slate-400 mt-1"><?= $avail ?> place(s) disponible(s)</div>
          </div>
        </div>
        <div class="text-right">
          <div class="text-2xl font-bold text-cb-primary"><?= number_format((int)$inv['price_fcfa']) ?> <span class="text-sm">FCFA</span></div>
          <?php if (!$disabled): ?>
            <a href="<?= e(url('public/booking/checkout/' . $trip['id'] . '?class=' . $inv['class_code'])) ?>" class="mt-2 inline-block px-4 py-2 rounded-lg bg-cb-primary text-white font-bold hover:bg-cb-secondary">
              Réserver
            </a>
          <?php else: ?>
            <span class="px-4 py-2 rounded-lg bg-slate-200 text-slate-600 font-bold">Complet</span>
          <?php endif ?>
        </div>
      </div>
    <?php endforeach ?>
  </div>
</div>
<?php $view->end() ?>
