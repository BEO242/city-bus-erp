<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$isEdit = !empty($user);
?>
<?php $view->start('content') ?>

<div class="space-y-5">
  <div class="flex items-center gap-3 mb-6">
    <a href="<?= e(url('admin/users')) ?>" class="p-2 rounded-lg hover:bg-white"><i data-lucide="arrow-left" class="w-5 h-5"></i></a>
    <h1 class="text-2xl font-bold text-slate-800"><?= e($title) ?></h1>
  </div>

  <form method="post" action="<?= e($isEdit ? url('admin/users/'.$user['id']) : url('admin/users')) ?>" class="space-y-6">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl shadow-soft p-6 grid md:grid-cols-2 gap-4">
      <div>
        <label class="text-xs font-semibold text-slate-500">Prénom *</label>
        <input name="first_name" value="<?= e(old('first_name', $user['first_name'] ?? '')) ?>" required
               class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none">
        <?php if ($err = errors('first_name')): ?><p class="text-xs text-rose-600 mt-1"><?= e($err[0] ?? '') ?></p><?php endif ?>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-500">Nom *</label>
        <input name="last_name" value="<?= e(old('last_name', $user['last_name'] ?? '')) ?>" required
               class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-500">Email *</label>
        <input type="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" required autocomplete="off"
               class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200">
        <?php if ($err = errors('email')): ?><p class="text-xs text-rose-600 mt-1"><?= e($err[0] ?? '') ?></p><?php endif ?>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-500">Téléphone</label>
        <input name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>"
               class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200">
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-500">Rôle *</label>
        <select name="role_id" required class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200">
          <option value="">— Sélectionner —</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= e($r['id']) ?>" <?= (int)old('role_id', $user['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['label']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-500">Agence</label>
        <select name="agency_id" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200">
          <option value="">— Aucune —</option>
          <?php foreach ($agencies as $a): ?>
            <option value="<?= e($a['id']) ?>" <?= (int)old('agency_id', $user['agency_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-soft p-6">
      <h3 class="font-semibold text-slate-700 mb-3 flex items-center gap-2"><i data-lucide="key" class="w-4 h-4"></i> Mot de passe</h3>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-semibold text-slate-500"><?= $isEdit ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe *' ?></label>
          <input type="password" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>
                 class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200">
          <p class="text-[11px] text-slate-400 mt-1">12+ caractères, majuscule, minuscule, chiffre, symbole.</p>
          <?php if ($err = errors('password')): ?><p class="text-xs text-rose-600 mt-1"><?= e(implode(' ', (array)$err)) ?></p><?php endif ?>
        </div>
        <div class="flex flex-col gap-2 mt-6">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="must_change_password" value="1" <?= !empty($user['must_change_password']) ? 'checked' : '' ?>>
            <span class="text-sm text-slate-600">Forcer changement à la prochaine connexion</span>
          </label>
          <?php if ($isEdit): ?>
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" name="is_active" value="1" <?= !empty($user['is_active']) ? 'checked' : '' ?>>
              <span class="text-sm text-slate-600">Compte actif</span>
            </label>
          <?php endif ?>
        </div>
      </div>
    </div>

    <?php if ($isEdit && !empty($permsByModule)): ?>
      <div class="bg-white rounded-2xl shadow-soft p-6" x-data="{ open: false }">
        <button type="button" @click="open=!open" class="w-full flex items-center justify-between">
          <h3 class="font-semibold text-slate-700 flex items-center gap-2"><i data-lucide="shield" class="w-4 h-4"></i> Permissions personnalisées</h3>
          <i data-lucide="chevron-down" class="w-4 h-4" x-bind:class="open && 'rotate-180'"></i>
        </button>
        <p class="text-xs text-slate-400 mt-1">Surcharges au-dessus du rôle (laisser <em>par défaut</em> pour hériter du rôle).</p>
        <div x-show="open" x-collapse class="mt-4 space-y-5">
          <?php foreach ($permsByModule as $module => $perms): ?>
            <div>
              <h4 class="text-xs uppercase tracking-wider text-slate-500 mb-2"><?= e($module) ?></h4>
              <div class="grid md:grid-cols-2 gap-2">
                <?php foreach ($perms as $p):
                  $current = $overrides[$p['id']] ?? null;
                ?>
                  <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 text-sm">
                    <span class="text-slate-700"><?= e($p['label']) ?></span>
                    <select name="overrides[<?= e($p['id']) ?>]" class="text-xs px-2 py-1 rounded border border-slate-200">
                      <option value="">Hérité</option>
                      <option value="grant"  <?= $current === '1' || $current === 1 ? 'selected' : '' ?>>Accorder</option>
                      <option value="revoke" <?= $current === '0' || $current === 0 ? 'selected' : '' ?>>Refuser</option>
                    </select>
                  </div>
                <?php endforeach ?>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      </div>
    <?php endif ?>

    <div class="flex items-center justify-end gap-3">
      <a href="<?= e(url('admin/users')) ?>" class="px-4 py-2 rounded-lg bg-white border border-slate-200 hover:bg-slate-50">Annuler</a>
      <button class="px-5 py-2 rounded-lg bg-cb-primary text-white font-semibold hover:bg-cb-secondary">
        <?= $isEdit ? 'Enregistrer' : 'Créer' ?>
      </button>
    </div>
  </form>
</div>

<?php $view->end() ?>
