<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="alert-octagon" class="w-6 h-6 text-rose-600"></i> Événements IROP ouverts
      </h1>
      <p class="text-sm text-slate-500"><?= count($events) ?> événement(s) actif(s)</p>
    </div>
  </div>

  <?php if (empty($events)): ?>
    <div class="bg-white rounded-2xl p-12 text-center border border-slate-100 shadow-soft">
      <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-100 flex items-center justify-center">
        <i data-lucide="check-circle-2" class="w-8 h-8 text-emerald-600"></i>
      </div>
      <h2 class="text-xl font-bold text-slate-900 mb-2">Aucun IROP actif</h2>
      <p class="text-sm text-slate-500">Tous les voyages opèrent normalement.</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php foreach ($events as $ev):
        $sevColor = match($ev['severity']) { 'critical'=>'rose', 'high'=>'orange', 'medium'=>'amber', 'low'=>'sky', default=>'slate' };
      ?>
        <a href="<?= e(url('voyages/irop/' . $ev['id'])) ?>" class="block bg-white rounded-2xl p-5 border border-slate-100 shadow-soft hover:border-<?= $sevColor ?>-300 transition">
          <div class="flex items-start justify-between mb-2">
            <div>
              <span class="px-2 py-0.5 rounded text-xs font-bold bg-<?= $sevColor ?>-100 text-<?= $sevColor ?>-700 uppercase"><?= e($ev['severity']) ?></span>
              <span class="ml-2 px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700"><?= e($ev['irop_type']) ?></span>
              <span class="ml-2 px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700"><?= e($ev['status']) ?></span>
            </div>
            <span class="text-xs text-slate-400">#<?= (int)$ev['id'] ?></span>
          </div>
          <div class="flex items-center gap-2 text-sm">
            <span class="font-mono font-bold text-cb-primary"><?= e($ev['trip_code']) ?></span>
            <span class="text-slate-600"><?= e($ev['line_code']) ?> · <?= e($ev['departure_city']) ?> → <?= e($ev['arrival_city']) ?></span>
          </div>
          <p class="text-sm text-slate-700 mt-2 line-clamp-2"><?= e($ev['reason']) ?></p>
          <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
            <span><i data-lucide="users" class="inline w-3 h-3"></i> <?= (int)$ev['impact_pax'] ?> pax</span>
            <?php if ((int)$ev['delay_minutes']>0): ?>
              <span class="text-rose-600"><i data-lucide="clock" class="inline w-3 h-3"></i> +<?= (int)$ev['delay_minutes'] ?> min</span>
            <?php endif ?>
            <span><?= e(date('d/m H:i', strtotime($ev['opened_at']))) ?></span>
          </div>
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
<?php $view->end() ?>
