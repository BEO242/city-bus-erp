<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
?>
<?php $view->start('content') ?>
<div class="min-h-screen flex items-center justify-center p-6">
  <div class="text-center max-w-md">
    <div class="text-7xl font-extrabold text-rose-600">403</div>
    <h1 class="text-2xl font-bold text-slate-900 mt-4">Accès refusé</h1>
    <p class="text-slate-500 mt-2">Vous n'avez pas la permission requise pour accéder à cette ressource.</p>
    <?php if (!empty($permission)): ?>
      <p class="text-xs text-slate-400 mt-2">Permission requise : <code class="bg-slate-100 px-2 py-0.5 rounded"><?= e($permission) ?></code></p>
    <?php endif ?>
    <a href="<?= e(url('dashboard')) ?>"
       class="inline-flex items-center gap-2 mt-6 px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary">
      <i data-lucide="home" class="w-4 h-4"></i> Retour
    </a>
  </div>
</div>
<?php $view->end() ?>
