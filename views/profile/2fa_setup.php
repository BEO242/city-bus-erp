<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="max-w-2xl mx-auto">
  <div class="flex items-center gap-3 mb-6">
    <a href="<?= e(url('profile')) ?>" class="p-2 rounded-lg hover:bg-white"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
    <h1 class="text-2xl font-bold text-slate-800">Authentification 2FA</h1>
  </div>

  <?php if (empty($enabled)): ?>
    <div class="bg-white rounded-2xl shadow-soft p-6">
      <div class="flex items-start gap-3 mb-5 p-3 rounded-lg bg-cb-bg text-cb-dark text-sm">
        <i data-lucide="info" class="w-4 h-4 mt-0.5 shrink-0"></i>
        <div>
          <p class="font-semibold">Activer l'authentification à deux facteurs</p>
          <p class="text-xs mt-1">Scannez le QR code avec Google Authenticator, Microsoft Authenticator ou Authy, puis saisissez le code de vérification ci-dessous.</p>
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-5 items-center">
        <div class="text-center">
          <img src="<?= e($qr) ?>" alt="QR 2FA" class="mx-auto rounded-lg border border-slate-100 bg-white p-2">
          <p class="text-[11px] text-slate-400 mt-2">Code secret manuel :</p>
          <p class="font-mono text-xs bg-slate-50 px-2 py-1 rounded mt-1 break-all"><?= e($secret) ?></p>
        </div>

        <form method="post" action="<?= e(url('profile/2fa')) ?>" class="space-y-3">
          <?= csrf_field() ?>
          <label class="text-xs font-semibold text-slate-500">Code de vérification (6 chiffres)</label>
          <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 text-center text-lg font-mono tracking-widest focus:border-cb-primary outline-none">
          <button class="w-full py-2.5 rounded-lg bg-cb-primary text-white font-semibold hover:bg-cb-secondary">
            Activer 2FA
          </button>
        </form>
      </div>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-2xl shadow-soft p-6">
      <div class="flex items-center gap-3 p-4 rounded-lg bg-emerald-50 text-emerald-800 mb-5">
        <i data-lucide="shield-check" class="w-6 h-6"></i>
        <div>
          <p class="font-semibold">2FA activée</p>
          <p class="text-xs"><?= (int)($codesLeft ?? 0) ?> code(s) de récupération restant(s).</p>
        </div>
      </div>

      <h3 class="font-semibold text-slate-800 mb-2">Désactiver 2FA</h3>
      <p class="text-xs text-slate-500 mb-3">Saisissez votre mot de passe pour confirmer.</p>
      <form method="post" action="<?= e(url('profile/2fa/disable')) ?>" class="flex gap-2">
        <?= csrf_field() ?>
        <input type="password" name="current_password" required placeholder="Mot de passe actuel" autocomplete="current-password"
               class="flex-1 px-3 py-2 rounded-lg border border-slate-200">
        <button onclick="return confirm('Désactiver la 2FA ?')" class="px-4 py-2 rounded-lg bg-rose-50 text-rose-600 font-semibold hover:bg-rose-100">
          Désactiver
        </button>
      </form>
    </div>
  <?php endif ?>
</div>

<?php $view->end() ?>
