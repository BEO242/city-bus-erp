<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/driver') ?>
<?php $view->start('content') ?>

<h2 class="text-sm font-bold text-slate-700 mb-2 px-1">Prochains arrêts (<?= count($rows) ?>)</h2>

<?php if (empty($rows)): ?>
  <div class="bg-white rounded-2xl p-8 text-center shadow-sm border border-slate-100">
    <i data-lucide="check-circle-2" class="w-12 h-12 mx-auto text-emerald-400 mb-2"></i>
    <p class="text-sm text-slate-500">Aucun arrêt en attente.</p>
  </div>
<?php else: ?>
  <div class="space-y-2">
    <?php foreach ($rows as $r): ?>
      <a href="<?= e(url('m/driver/trip/' . $r['trip_id'])) ?>" class="block bg-white rounded-2xl p-3 shadow-sm border border-slate-100 active:bg-slate-50">
        <div class="flex items-center justify-between">
          <div>
            <div class="font-bold text-slate-900 text-sm"><?= e($r['stop_name'] ?? '—') ?></div>
            <div class="text-xs text-slate-500"><?= e($r['trip_code']) ?> · prévu <?= $r['scheduled_arrival'] ? e(date('H:i', strtotime($r['scheduled_arrival']))) : '—' ?></div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
        </div>
      </a>
    <?php endforeach ?>
  </div>
<?php endif ?>

<?php $view->end() ?>
