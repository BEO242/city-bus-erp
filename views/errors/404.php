<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
?>
<?php $view->start('content') ?>
<div class="min-h-screen flex items-center justify-center p-6">
  <div class="text-center max-w-md">
    <div class="text-7xl font-extrabold text-cb-primary">404</div>
    <h1 class="text-2xl font-bold text-slate-900 mt-4">Page introuvable</h1>
    <p class="text-slate-500 mt-2">La page que vous cherchez n'existe pas ou a été déplacée.</p>
    <a href="<?= e(url('dashboard')) ?>"
       class="inline-flex items-center gap-2 mt-6 px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary">
      <i data-lucide="home" class="w-4 h-4"></i> Retour au tableau de bord
    </a>
  </div>
</div>
<?php $view->end() ?>
