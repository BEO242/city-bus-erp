<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
?>
<?php $view->start('content') ?>
<div class="min-h-screen flex items-center justify-center p-6">
  <div class="text-center max-w-lg">
    <div class="text-7xl font-extrabold text-amber-500">500</div>
    <h1 class="text-2xl font-bold text-slate-900 mt-4">Erreur interne</h1>
    <p class="text-slate-500 mt-2">Une erreur est survenue. L'incident a été enregistré.</p>
    <?php if (!empty($message) && (config('app.debug') || (auth()['role'] ?? '') === 'admin')): ?>
      <pre class="mt-4 text-left text-xs bg-slate-900 text-rose-300 p-4 rounded-xl overflow-auto"><?= e($message) ?></pre>
    <?php endif ?>
    <a href="<?= e(url('dashboard')) ?>" class="inline-flex items-center gap-2 mt-6 px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium">
      <i data-lucide="home" class="w-4 h-4"></i> Retour
    </a>
  </div>
</div>
<?php $view->end() ?>
