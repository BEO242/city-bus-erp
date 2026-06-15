<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
?>
<?php $view->start('content') ?>
<div class="min-h-screen flex items-center justify-center p-6">
  <div class="max-w-lg text-center">
    <div class="mx-auto w-16 h-16 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-5">
      <i data-lucide="wrench" class="w-8 h-8"></i>
    </div>
    <h1 class="text-2xl font-bold text-slate-800 mb-2">Maintenance en cours</h1>
    <p class="text-slate-600"><?= e($message ?? 'Application en maintenance.') ?></p>
  </div>
</div>
<?php $view->end() ?>
