<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$u = $user ?? [];
$roles = ['admin','superviseur','caissier','controleur','rh','flotte','agent'];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <a href="<?= e(url('users')) ?>" class="text-sm text-slate-500 inline-flex items-center gap-1"><i data-lucide="chevron-left" class="w-4 h-4"></i> Retour</a>
  <h1 class="text-2xl font-bold"><?= isset($user) ? 'Modifier' : 'Nouvel' ?> utilisateur</h1>

  <form method="post" action="<?= e(isset($user) ? url('users/'.$user['id']) : url('users')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft">
    <?= csrf_field() ?>
    <?php if (isset($user)): ?><input type="hidden" name="_method" value="PUT"><?php endif ?>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Prénom *</label>
        <input name="first_name" required value="<?= e($u['first_name'] ?? old('first_name')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Nom *</label>
        <input name="last_name" required value="<?= e($u['last_name'] ?? old('last_name')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div class="col-span-2">
        <label class="block text-sm font-medium mb-1">Email *</label>
        <input type="email" name="email" required value="<?= e($u['email'] ?? old('email')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Rôle *</label>
        <select name="role" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r ?>" <?= ($u['role'] ?? '')===$r?'selected':'' ?>><?= e($r) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Agence</label>
        <select name="agency_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="">—</option>
          <?php foreach ($agencies as $a): ?>
            <option value="<?= e($a['id']) ?>" <?= ($u['agency_id'] ?? '')===$a['id']?'selected':'' ?>><?= e($a['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="col-span-2">
        <label class="block text-sm font-medium mb-1">
          Mot de passe <?= isset($user) ? '<span class="text-xs text-slate-400">(laisser vide pour ne pas changer)</span>' : '*' ?>
        </label>
        <input type="password" name="password" <?= isset($user) ? '' : 'required' ?> minlength="6" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <?php if (isset($user)): ?>
      <div class="col-span-2">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="is_active" value="1" <?= !empty($u['is_active'])?'checked':'' ?> class="w-4 h-4">
          <span class="text-sm">Compte actif</span>
        </label>
      </div>
      <?php endif ?>
    </div>
    <div class="flex justify-end gap-2">
      <a href="<?= e(url('users')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium">Enregistrer</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
