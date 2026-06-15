<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="space-y-5">
  <div class="flex items-center gap-3 mb-6">
    <a href="<?= e(url('profile')) ?>" class="p-2 rounded-lg hover:bg-white"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
    <h1 class="text-2xl font-bold text-slate-800">Changer le mot de passe</h1>
  </div>

  <form method="post" action="<?= e(url('profile/password')) ?>" data-dirty-watch="1" class="bg-white rounded-2xl shadow-soft p-6 space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="text-xs font-semibold text-slate-500">Mot de passe actuel *</label>
      <input type="password" name="current_password" required autocomplete="current-password"
             class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none">
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-500">Nouveau mot de passe *</label>
      <input type="password" name="password" required autocomplete="new-password"
             class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none">
      <p class="text-[11px] text-slate-400 mt-1">12+ caractères, majuscule, minuscule, chiffre, symbole. Pas réutilisable.</p>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-500">Confirmer *</label>
      <input type="password" name="password_confirmation" required autocomplete="new-password"
             class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none">
    </div>
    <button data-dirty-submit class="w-full py-2.5 rounded-lg bg-cb-primary text-white font-semibold hover:bg-cb-secondary">
      Mettre à jour
    </button>
  </form>
</div>

<?php $view->end() ?>
