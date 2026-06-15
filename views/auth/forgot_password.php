<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
$flash = \CityBus\Core\Session::pullFlash();
?>
<?php $view->start('content') ?>
<div class="min-h-screen flex items-center justify-center p-8 bg-cb-bg/30">
  <div class="w-full max-w-md bg-white rounded-2xl border border-slate-100 shadow-soft p-8">
    <div class="flex items-center gap-3 mb-6">
      <div class="bg-cb-primary text-white rounded-xl p-2.5">
        <i data-lucide="key-round" class="w-6 h-6"></i>
      </div>
      <span class="font-bold text-xl text-cb-primary">Mot de passe oublié</span>
    </div>

    <p class="text-slate-500 text-sm mb-6">
      Saisissez votre adresse e-mail. Si elle correspond à un compte actif, vous recevrez un lien pour
      définir un nouveau mot de passe.
    </p>

    <?php foreach ($flash as $type => $msgs): foreach ($msgs as $msg): ?>
      <div class="mb-4 p-3 rounded-xl <?= $type==='danger'?'bg-rose-50 border border-rose-200 text-rose-700':'bg-emerald-50 border border-emerald-200 text-emerald-700' ?>">
        <?= e($msg) ?>
      </div>
    <?php endforeach; endforeach ?>

    <form method="post" action="<?= e(url('forgot-password')) ?>" class="space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Adresse e-mail</label>
        <input type="email" name="email" required autofocus
               value="<?= e(old('email')) ?>"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
      </div>
      <button class="w-full bg-cb-primary text-white font-semibold py-3 rounded-xl hover:bg-cb-secondary transition">
        Envoyer le lien de réinitialisation
      </button>
    </form>

    <p class="text-center text-sm text-slate-500 mt-6">
      <a href="<?= e(url('login')) ?>" class="text-cb-primary hover:underline">← Retour à la connexion</a>
    </p>
  </div>
</div>
<?php $view->end() ?>
