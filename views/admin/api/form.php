<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('admin/api')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
  </div>
  <form method="post" action="<?= e(url('admin/api')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft max-w-xl">
    <?= csrf_field() ?>
    <input name="name" required placeholder="Nom du client *" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    <textarea name="description" rows="2" placeholder="Description" class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
    <div>
      <label class="block text-sm text-slate-500 mb-1">Scopes (séparés par espace)</label>
      <input name="scopes" required value="read" placeholder="read write" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <div>
      <label class="block text-sm text-slate-500 mb-1">Rate limit / minute</label>
      <input type="number" name="rate_limit_per_min" value="60" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <button class="w-full px-4 py-2.5 rounded-xl bg-cb-primary text-white">Créer le client</button>
  </form>
</div>
<?php $view->end() ?>
