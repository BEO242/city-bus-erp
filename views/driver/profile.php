<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/driver') ?>
<?php $view->start('content') ?>
<div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
  <div class="flex items-center gap-3">
    <div class="w-14 h-14 rounded-full bg-cb-primary text-white flex items-center justify-center text-xl font-bold">
      <?= e(strtoupper(substr($driver['first_name'],0,1).substr($driver['last_name'],0,1))) ?>
    </div>
    <div>
      <div class="text-lg font-bold text-slate-900"><?= e($driver['first_name'].' '.$driver['last_name']) ?></div>
      <div class="text-xs text-slate-500">Matricule <?= e($driver['matricule']) ?></div>
    </div>
  </div>
  <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
    <div><dt class="text-xs text-slate-500">Téléphone</dt><dd class="font-semibold"><?= e($driver['phone'] ?? '—') ?></dd></div>
    <div><dt class="text-xs text-slate-500">Email</dt><dd class="font-semibold text-xs"><?= e($driver['email'] ?? '—') ?></dd></div>
    <div><dt class="text-xs text-slate-500">Permis</dt><dd class="font-semibold"><?= e($driver['license_number'] ?? '—') ?></dd></div>
    <div><dt class="text-xs text-slate-500">Permis expire</dt><dd class="font-semibold"><?= $driver['license_expiry'] ? e(date('d/m/Y', strtotime($driver['license_expiry']))) : '—' ?></dd></div>
    <div><dt class="text-xs text-slate-500">Statut</dt><dd class="font-semibold"><?= e($driver['status']) ?></dd></div>
    <div><dt class="text-xs text-slate-500">Embauché le</dt><dd class="font-semibold"><?= e(date('d/m/Y', strtotime($driver['hire_date']))) ?></dd></div>
  </dl>
</div>

<form method="post" action="<?= e(url('logout')) ?>" class="mt-4">
  <?= csrf_field() ?>
  <button class="w-full px-4 py-3 rounded-2xl bg-rose-500 text-white font-bold active:bg-rose-600">
    <i data-lucide="log-out" class="inline w-4 h-4"></i> Déconnexion
  </button>
</form>
<?php $view->end() ?>
