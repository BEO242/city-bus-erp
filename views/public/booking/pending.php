<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/public'); ?>
<?php $view->start('content') ?>
<div class="max-w-xl mx-auto bg-amber-100 text-amber-900 rounded-2xl p-8 text-center">
  <i data-lucide="clock" class="w-16 h-16 mx-auto mb-3"></i>
  <h1 class="text-2xl font-bold">Paiement en attente</h1>
  <p class="mt-2">Vérifiez votre téléphone : un message vous a été envoyé pour valider le paiement.</p>
  <p class="mt-4 text-sm">PNR #<?= (int)$pnr_id ?></p>
</div>
<?php $view->end() ?>
