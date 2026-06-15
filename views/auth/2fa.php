<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
?>
<?php $view->start('content') ?>
<div class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-cb-bg via-white to-cb-bg">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-soft p-8">
    <div class="text-center mb-6">
      <div class="inline-flex w-14 h-14 rounded-2xl bg-cb-primary/10 text-cb-primary items-center justify-center mb-3">
        <i data-lucide="shield-check" class="w-7 h-7"></i>
      </div>
      <h2 class="text-xl font-bold text-slate-800">Authentification à deux facteurs</h2>
      <p class="text-sm text-slate-500 mt-1">Saisissez le code à 6 chiffres généré par votre application, ou un code de récupération.</p>
    </div>

    <form method="post" action="<?= e(url('login/2fa')) ?>" class="space-y-4">
      <?= csrf_field() ?>
      <input name="code" inputmode="numeric" maxlength="20" required autofocus autocomplete="off"
             placeholder="123456"
             class="w-full px-4 py-3 rounded-xl border border-slate-200 text-center text-xl font-mono tracking-widest focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
      <button class="w-full py-3 rounded-xl bg-cb-primary text-white font-semibold hover:bg-cb-secondary">
        Vérifier
      </button>
      <a href="<?= e(url('logout')) ?>" class="block text-center text-xs text-slate-400 hover:text-cb-primary">Annuler la connexion</a>
    </form>
  </div>
</div>
<?php $view->end() ?>
