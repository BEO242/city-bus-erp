<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
$flash = \CityBus\Core\Session::pullFlash();
?>
<?php $view->start('content') ?>
<div class="min-h-screen bg-gradient-to-br from-cb-bg to-white flex items-center justify-center p-8">
  <div class="max-w-md text-center space-y-4">
    <div class="text-6xl">🎉</div>
    <h1 class="text-2xl font-bold text-slate-900">Merci pour votre avis !</h1>
    <p class="text-slate-500">Vos retours nous permettent d'améliorer continuellement le service CITY BUS.</p>
  </div>
</div>
<?php $view->end() ?>
