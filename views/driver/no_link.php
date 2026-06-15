<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/driver') ?>
<?php $view->start('content') ?>
<div class="bg-white rounded-2xl p-8 text-center shadow-sm border border-slate-100 mt-8">
  <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-amber-100 flex items-center justify-center">
    <i data-lucide="user-x" class="w-8 h-8 text-amber-600"></i>
  </div>
  <h2 class="text-lg font-bold text-slate-900 mb-2">Compte non lié</h2>
  <p class="text-sm text-slate-500 mb-4">Votre compte utilisateur n'est rattaché à aucun chauffeur. Contactez l'administration.</p>
  <form method="post" action="<?= e(url('logout')) ?>">
    <?= csrf_field() ?>
    <button class="px-4 py-2 rounded-lg bg-rose-500 text-white font-semibold">Déconnexion</button>
  </form>
</div>
<?php $view->end() ?>
