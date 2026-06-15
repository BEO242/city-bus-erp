<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center gap-3">
    <a href="<?= e(url('billetterie-bagages')) ?>"
       class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold">Choisir un voyage</h1>
      <p class="text-slate-500 text-sm">Sélectionnez le voyage pour émettre un billet bagage.</p>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2 items-center w-fit shadow-soft">
    <i data-lucide="calendar" class="w-4 h-4 text-slate-400 ml-2"></i>
    <input type="date" name="date" value="<?= e($date) ?>"
           class="px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none">
    <button class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm">Filtrer</button>
  </form>

  <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($trips as $t): ?>
      <a href="<?= e(url('billetterie-bagages/sale/' . $t['id'])) ?>"
         class="block bg-white rounded-2xl border-2 border-transparent hover:border-amber-400 hover:shadow-soft transition p-5">
        <div class="flex justify-between items-start mb-2">
          <span class="font-mono text-sm text-amber-600 font-bold"><?= e($t['line_code']) ?></span>
          <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
            <i data-lucide="package" class="w-3 h-3 inline-block -mt-0.5"></i>
            <?= (int)$t['baggage_count'] ?> bagage(s)
          </span>
        </div>
        <h3 class="font-bold text-slate-800"><?= e($t['line_name']) ?></h3>
        <div class="flex items-center gap-1.5 text-sm text-slate-500 mt-1">
          <i data-lucide="clock" class="w-3.5 h-3.5"></i>
          <?= e(date('H:i', strtotime($t['departure_scheduled']))) ?>
          <span>·</span>
          <i data-lucide="bus" class="w-3.5 h-3.5"></i>
          <?= e($t['bus_code']) ?>
          <span>·</span>
          <span class="capitalize text-xs px-1.5 py-0.5 rounded
            <?= $t['status'] === 'embarquement' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
            <?= e($t['status']) ?>
          </span>
        </div>
      </a>
    <?php endforeach ?>
    <?php if (!$trips): ?>
      <div class="md:col-span-3 bg-white rounded-2xl border border-slate-100 p-12 text-center text-slate-400">
        <i data-lucide="calendar-x" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
        <p>Aucun voyage disponible à cette date.</p>
      </div>
    <?php endif ?>
  </div>
</div>
<?php $view->end() ?>
