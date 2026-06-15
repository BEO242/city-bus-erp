<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
$flash = \CityBus\Core\Session::pullFlash();
?>
<?php $view->start('content') ?>
<div class="min-h-screen flex items-center justify-center p-8 bg-cb-bg/30">
  <div class="w-full max-w-md bg-white rounded-2xl border border-slate-100 shadow-soft p-8">
    <div class="flex items-center gap-3 mb-6">
      <div class="bg-cb-primary text-white rounded-xl p-2.5">
        <i data-lucide="lock-keyhole" class="w-6 h-6"></i>
      </div>
      <span class="font-bold text-xl text-cb-primary">Nouveau mot de passe</span>
    </div>

    <?php foreach ($flash as $type => $msgs): foreach ($msgs as $msg): ?>
      <div class="mb-4 p-3 rounded-xl <?= $type==='danger'?'bg-rose-50 border border-rose-200 text-rose-700':'bg-emerald-50 border border-emerald-200 text-emerald-700' ?>">
        <?= e($msg) ?>
      </div>
    <?php endforeach; endforeach ?>

    <form method="post" action="<?= e(url('reset-password')) ?>" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nouveau mot de passe</label>
        <input type="password" name="password" required minlength="8"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        <p class="text-xs text-slate-400 mt-1">Au moins 12 caractères, minuscule, majuscule, chiffre et caractère spécial.</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirmer</label>
        <input type="password" name="password_confirmation" required minlength="8"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
      </div>
      <button class="w-full bg-cb-primary text-white font-semibold py-3 rounded-xl hover:bg-cb-secondary transition">
        Mettre à jour le mot de passe
      </button>
    </form>

    <p class="text-center text-sm text-slate-500 mt-6">
      <a href="<?= e(url('login')) ?>" class="text-cb-primary hover:underline">← Retour à la connexion</a>
    </p>
  </div>
</div>
<?php $view->end() ?>
