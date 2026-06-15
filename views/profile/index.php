<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="space-y-5">
  <div class="bg-white rounded-2xl shadow-soft p-6">
    <div class="flex items-center gap-4">
      <div class="w-16 h-16 rounded-full bg-cb-primary text-white flex items-center justify-center text-xl font-bold">
        <?= e(strtoupper(substr($user['first_name'],0,1) . substr($user['last_name'],0,1))) ?>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-800"><?= e($user['first_name'].' '.$user['last_name']) ?></h1>
        <p class="text-sm text-slate-500"><?= e($user['email']) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= e($user['role_label'] ?? $user['role']) ?><?= $user['agency_name'] ? ' · '.e($user['agency_name']) : '' ?></p>
      </div>
    </div>
  </div>

  <div class="grid md:grid-cols-2 gap-4">
    <a href="<?= e(url('profile/password')) ?>" class="bg-white rounded-2xl shadow-soft p-5 hover:shadow-md transition flex items-start gap-3">
      <div class="p-2 rounded-lg bg-cb-bg text-cb-primary"><i data-lucide="key-round" class="w-5 h-5"></i></div>
      <div>
        <h3 class="font-semibold text-slate-800">Changer le mot de passe</h3>
        <p class="text-xs text-slate-500 mt-1">Dernière modification : <?= e($user['password_changed_at'] ? date('d/m/Y', strtotime($user['password_changed_at'])) : '—') ?></p>
      </div>
    </a>
    <a href="<?= e(url('profile/2fa')) ?>" class="bg-white rounded-2xl shadow-soft p-5 hover:shadow-md transition flex items-start gap-3">
      <div class="p-2 rounded-lg <?= $has2fa ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' ?>">
        <i data-lucide="<?= $has2fa ? 'shield-check' : 'shield-off' ?>" class="w-5 h-5"></i>
      </div>
      <div>
        <h3 class="font-semibold text-slate-800">Authentification 2FA</h3>
        <p class="text-xs <?= $has2fa ? 'text-emerald-600' : 'text-amber-600' ?> mt-1"><?= $has2fa ? 'Activée' : 'Non activée' ?></p>
      </div>
    </a>
  </div>

  <div class="bg-white rounded-2xl shadow-soft p-6">
    <h3 class="font-semibold text-slate-700 mb-3 flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4"></i> Connexions récentes</h3>
    <div class="space-y-2">
      <?php foreach ($history as $h): ?>
        <div class="flex items-center justify-between text-sm p-2 rounded-lg bg-slate-50">
          <div>
            <span class="font-medium text-slate-700"><?= e(date('d/m/Y H:i', strtotime($h['logged_in_at']))) ?></span>
            <span class="text-xs text-slate-400 ml-2"><?= e($h['ip_address']) ?></span>
          </div>
          <span class="text-xs text-slate-400 truncate max-w-xs"><?= e(mb_substr($h['user_agent'] ?? '', 0, 60)) ?></span>
        </div>
      <?php endforeach ?>
      <?php if (empty($history)): ?>
        <p class="text-sm text-slate-400 text-center py-3">Aucun historique disponible.</p>
      <?php endif ?>
    </div>
  </div>
</div>

<?php $view->end() ?>
