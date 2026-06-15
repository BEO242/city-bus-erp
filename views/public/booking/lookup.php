<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/public'); ?>
<?php $view->start('content') ?>
<div class="max-w-xl mx-auto">
  <h1 class="text-2xl font-bold text-slate-900 mb-4">Retrouver ma réservation</h1>

  <form method="get" class="bg-white rounded-2xl p-5 shadow-soft border border-slate-100 mb-6">
    <label class="block text-xs font-bold uppercase text-slate-600 mb-2">Code PNR</label>
    <div class="flex gap-2">
      <input name="pnr" value="<?= e($code ?? '') ?>" placeholder="ABCDEF" class="flex-1 px-3 py-3 rounded-lg border border-slate-300 font-mono uppercase">
      <button class="px-5 py-3 rounded-lg bg-cb-primary text-white font-bold">Rechercher</button>
    </div>
  </form>

  <?php if (isset($pnr) && $pnr): ?>
    <div class="bg-white rounded-2xl p-5 shadow-soft border border-slate-100">
      <div class="flex items-center justify-between mb-3">
        <span class="font-mono text-xl font-bold text-cb-primary"><?= e($pnr['pnr_code']) ?></span>
        <span class="px-2 py-0.5 rounded text-xs font-bold uppercase bg-slate-100"><?= e($pnr['status']) ?></span>
      </div>
      <?php foreach ($pnr['segments'] as $s): ?>
        <div class="border-l-4 border-cb-primary pl-3 mb-3">
          <div class="font-bold"><?= e($s['from_name']) ?> → <?= e($s['to_name']) ?></div>
          <div class="text-sm text-slate-600"><?= e(date('d/m/Y', strtotime($s['trip_date']))) ?> à <?= e(substr($s['departure_time']??'',0,5)) ?></div>
        </div>
      <?php endforeach ?>
    </div>
  <?php elseif (isset($code) && $code): ?>
    <div class="bg-rose-100 text-rose-800 rounded-2xl p-4 text-sm">Aucune réservation trouvée pour <?= e($code) ?></div>
  <?php endif ?>
</div>
<?php $view->end() ?>
