<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/public'); ?>
<?php $view->start('content') ?>
<form method="get" class="bg-white rounded-2xl p-4 shadow-soft border border-slate-100 flex flex-wrap items-end gap-3 mb-6">
  <select name="from" class="px-3 py-2 rounded-lg border border-slate-300">
    <?php foreach ($cities as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $fromCity==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
    <?php endforeach ?>
  </select>
  <span>→</span>
  <select name="to" class="px-3 py-2 rounded-lg border border-slate-300">
    <?php foreach ($cities as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $toCity==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
    <?php endforeach ?>
  </select>
  <input type="date" name="date" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>" class="px-3 py-2 rounded-lg border border-slate-300">
  <input type="number" name="pax" value="<?= $pax ?>" min="1" max="9" class="w-16 px-3 py-2 rounded-lg border border-slate-300">
  <button class="px-4 py-2 rounded-lg bg-cb-primary text-white font-bold">Rechercher</button>
</form>

<h1 class="text-2xl font-bold text-slate-900 mb-4"><?= count($trips) ?> voyage(s) trouvé(s)</h1>

<div class="space-y-3">
  <?php foreach ($trips as $t): ?>
    <div class="bg-white rounded-2xl shadow-soft border border-slate-100 p-5 flex items-center justify-between hover:border-cb-primary transition">
      <div class="flex-1">
        <div class="flex items-center gap-3">
          <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 text-xs font-mono font-bold"><?= e($t['line_code']) ?></span>
          <span class="font-mono text-cb-primary"><?= e($t['trip_code']) ?></span>
        </div>
        <div class="mt-2 flex items-center gap-4">
          <div>
            <div class="text-2xl font-bold"><?= e(substr($t['departure_time'] ?? '', 0, 5)) ?></div>
            <div class="text-xs text-slate-500"><?= e($t['departure_city']) ?></div>
          </div>
          <div class="text-slate-400">
            <i data-lucide="arrow-right" class="w-5 h-5"></i>
            <div class="text-xs"><?= e($t['estimated_duration_minutes'] ?? '') ?> min</div>
          </div>
          <div>
            <div class="text-2xl font-bold"><?= $t['arrival_scheduled'] ? e(substr($t['arrival_scheduled'], 0, 5)) : '~' ?></div>
            <div class="text-xs text-slate-500"><?= e($t['arrival_city']) ?></div>
          </div>
        </div>
        <div class="mt-2 text-xs text-slate-500">
          <?= e($t['bus_brand']) ?> <?= e($t['bus_model']) ?>
        </div>
      </div>
      <div class="text-right">
        <?php if ($t['min_price']): ?>
          <div class="text-xs text-slate-500">à partir de</div>
          <div class="text-2xl font-bold text-cb-primary"><?= number_format((int)$t['min_price']) ?> <span class="text-sm">FCFA</span></div>
        <?php endif ?>
        <a href="<?= e(url('public/booking/trip/' . $t['id'])) ?>" class="mt-2 inline-block px-5 py-2 rounded-lg bg-cb-primary text-white font-bold hover:bg-cb-secondary">
          Voir
        </a>
      </div>
    </div>
  <?php endforeach ?>
  <?php if (empty($trips)): ?>
    <div class="bg-white rounded-2xl p-12 text-center shadow-soft border border-slate-100">
      <i data-lucide="search-x" class="w-12 h-12 mx-auto text-slate-400 mb-3"></i>
      <p class="text-slate-600">Aucun voyage trouvé.</p>
    </div>
  <?php endif ?>
</div>
<?php $view->end() ?>
