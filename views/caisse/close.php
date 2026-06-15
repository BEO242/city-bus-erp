<?php
/**
 * Fallback – la clôture est gérée via le modal de views/caisse/index.php.
 * Le contrôleur redirige déjà vers 'caisse' avant d'atteindre cette vue.
 *
 * @var \CityBus\Core\View $view
 */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="flex flex-col items-center justify-center py-20 text-center space-y-4">
  <div class="w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center">
    <i data-lucide="alert-triangle" class="w-7 h-7 text-amber-600"></i>
  </div>
  <h2 class="text-lg font-bold text-slate-800">Page déplacée</h2>
  <p class="text-slate-500 text-sm max-w-xs">La clôture de caisse se fait maintenant directement depuis le tableau de bord de caisse.</p>
  <a href="<?= e(url('caisse')) ?>"
     class="px-5 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition inline-flex items-center gap-2">
    <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour à la caisse
  </a>
</div>
<?php $view->end() ?>
