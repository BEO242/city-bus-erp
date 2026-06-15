<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold">Utilisateurs</h1>
      <p class="text-slate-500 text-sm">Comptes d'accès au système.</p>
    </div>
    <a href="<?= e(url('users/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium inline-flex items-center gap-2">
      <i data-lucide="user-plus" class="w-4 h-4"></i> Nouvel utilisateur
    </a>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr><th class="px-5 py-3 text-left">Nom</th><th class="px-5 py-3 text-left">Email</th><th class="px-5 py-3 text-left">Rôle</th><th class="px-5 py-3 text-left">Agence</th><th class="px-5 py-3 text-center">Statut</th><th></th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($users as $u): ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-3 font-medium"><?= e($u['first_name']) ?> <?= e($u['last_name']) ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e($u['email']) ?></td>
          <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-cb-bg text-cb-primary"><?= e($u['role']) ?></span></td>
          <td class="px-5 py-3 text-slate-500"><?= e($u['agency_name'] ?? '—') ?></td>
          <td class="px-5 py-3 text-center"><span class="px-2 py-0.5 rounded-full text-xs <?= $u['is_active']?'bg-emerald-50 text-emerald-700':'bg-rose-50 text-rose-600' ?>"><?= $u['is_active']?'actif':'désactivé' ?></span></td>
          <td class="px-5 py-3 text-right">
            <a href="<?= e(url('users/'.$u['id'].'/edit')) ?>" class="text-cb-primary text-xs hover:underline">Modifier</a>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$users): ?><tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">Aucun utilisateur</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
