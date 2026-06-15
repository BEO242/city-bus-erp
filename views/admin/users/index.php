<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Utilisateurs</h1>
    <p class="text-sm text-slate-500 mt-1"><?= count($users) ?> compte(s)</p>
  </div>
  <?php if (can('admin.users.create')): ?>
    <a href="<?= e(url('admin/users/create')) ?>"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cb-primary text-white font-semibold hover:bg-cb-secondary">
      <i data-lucide="user-plus" class="w-4 h-4"></i> Nouvel utilisateur
    </a>
  <?php endif ?>
</div>

<form method="get" class="bg-white rounded-2xl shadow-soft p-4 mb-5 flex flex-wrap gap-3 items-end">
  <div class="flex-1 min-w-[200px]">
    <label class="text-xs font-semibold text-slate-500">Recherche</label>
    <input name="q" value="<?= e($q) ?>" placeholder="Nom, email…"
           class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
  </div>
  <div>
    <label class="text-xs font-semibold text-slate-500">Rôle</label>
    <select name="role" class="mt-1 px-3 py-2 rounded-lg border border-slate-200">
      <option value="">Tous</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= e($r['slug']) ?>" <?= $roleF === $r['slug'] ? 'selected' : '' ?>><?= e($r['label']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div>
    <label class="text-xs font-semibold text-slate-500">Statut</label>
    <select name="status" class="mt-1 px-3 py-2 rounded-lg border border-slate-200">
      <?php foreach (['' => 'Tous', 'active' => 'Actif', 'inactive' => 'Inactif', 'locked' => 'Verrouillé'] as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <button class="px-4 py-2 rounded-lg bg-cb-bg text-cb-primary font-semibold hover:bg-cb-accent/20">
    Filtrer
  </button>
</form>

<div class="bg-white rounded-2xl shadow-soft overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
      <tr>
        <th class="text-left px-4 py-3">Utilisateur</th>
        <th class="text-left px-4 py-3">Rôle</th>
        <th class="text-left px-4 py-3">Agence</th>
        <th class="text-left px-4 py-3">Statut</th>
        <th class="text-left px-4 py-3">2FA</th>
        <th class="text-left px-4 py-3">Dernière connexion</th>
        <th class="text-right px-4 py-3">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
    <?php foreach ($users as $u):
      $isLocked = $u['locked_until'] && strtotime($u['locked_until']) > time();
    ?>
      <tr class="hover:bg-slate-50/50">
        <td class="px-4 py-3">
          <div class="font-semibold text-slate-800"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
          <div class="text-xs text-slate-500"><?= e($u['email']) ?></div>
        </td>
        <td class="px-4 py-3"><span class="px-2 py-1 rounded-md bg-cb-bg text-cb-primary text-xs font-semibold"><?= e($u['role_label'] ?? $u['role']) ?></span></td>
        <td class="px-4 py-3 text-slate-600"><?= e($u['agency_name'] ?? '—') ?></td>
        <td class="px-4 py-3">
          <?php if ($isLocked): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-xs font-semibold">
              <i data-lucide="lock" class="w-3 h-3"></i> Verrouillé
            </span>
          <?php elseif ((int)$u['is_active'] === 1): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-xs font-semibold">
              <i data-lucide="check" class="w-3 h-3"></i> Actif
            </span>
          <?php else: ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-xs font-semibold">Inactif</span>
          <?php endif ?>
        </td>
        <td class="px-4 py-3">
          <?php if ((int)$u['two_factor_enabled'] === 1): ?>
            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
          <?php else: ?>
            <i data-lucide="shield-off" class="w-4 h-4 text-slate-300"></i>
          <?php endif ?>
        </td>
        <td class="px-4 py-3 text-xs text-slate-500"><?= e($u['last_login_at'] ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—') ?></td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <?php if (can('admin.users.edit')): ?>
              <a href="<?= e(url('admin/users/'.$u['id'].'/edit')) ?>" class="p-2 rounded-lg hover:bg-cb-bg text-cb-primary" title="Modifier"><i data-lucide="edit-3" class="w-4 h-4"></i></a>
            <?php endif ?>
            <?php if ($isLocked && can('admin.users.unlock')): ?>
              <form method="post" action="<?= e(url('admin/users/'.$u['id'].'/unlock')) ?>" class="inline">
                <?= csrf_field() ?>
                <button class="p-2 rounded-lg hover:bg-amber-50 text-amber-600" title="Déverrouiller"><i data-lucide="unlock" class="w-4 h-4"></i></button>
              </form>
            <?php endif ?>
            <?php if (can('admin.users.reset_pwd')): ?>
              <form method="post" action="<?= e(url('admin/users/'.$u['id'].'/reset-password')) ?>" class="inline" onsubmit="return confirm('Réinitialiser le mot de passe ?')">
                <?= csrf_field() ?>
                <button class="p-2 rounded-lg hover:bg-cb-bg text-slate-600" title="Réinitialiser mdp"><i data-lucide="key-round" class="w-4 h-4"></i></button>
              </form>
            <?php endif ?>
            <?php if ((int)$u['two_factor_enabled'] === 1 && can('admin.users.edit')): ?>
              <form method="post" action="<?= e(url('admin/users/'.$u['id'].'/reset-2fa')) ?>" class="inline" onsubmit="return confirm('Désactiver 2FA pour cet utilisateur ?')">
                <?= csrf_field() ?>
                <button class="p-2 rounded-lg hover:bg-rose-50 text-rose-500" title="Reset 2FA"><i data-lucide="shield-off" class="w-4 h-4"></i></button>
              </form>
            <?php endif ?>
            <?php if (can('admin.users.delete') && (int)$u['id'] !== auth()['id']): ?>
              <form method="post" action="<?= e(url('admin/users/'.$u['id'].'/delete')) ?>" class="inline" onsubmit="return confirm('Désactiver ce compte ?')">
                <?= csrf_field() ?>
                <button class="p-2 rounded-lg hover:bg-rose-50 text-rose-600" title="Supprimer"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
              </form>
            <?php endif ?>
          </div>
        </td>
      </tr>
    <?php endforeach ?>
    <?php if (empty($users)): ?>
      <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun utilisateur.</td></tr>
    <?php endif ?>
    </tbody>
  </table>
</div>

<?php $view->end() ?>
