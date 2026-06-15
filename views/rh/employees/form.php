<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$em = $employee ?? [];
$agencies = $agencies ?? [];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <a href="<?= e(url('rh/employees')) ?>" class="text-sm text-slate-500 inline-flex items-center gap-1"><i data-lucide="chevron-left" class="w-4 h-4"></i> Retour</a>
  <h1 class="text-2xl font-bold"><?= isset($employee) ? 'Modifier' : 'Nouvel' ?> employé</h1>

  <form method="post" action="<?= e(isset($employee) ? url('rh/employees/'.$employee['id']) : url('rh/employees')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft">
    <?= csrf_field() ?>
    <?php if (isset($employee)): ?><input type="hidden" name="_method" value="PUT"><?php endif ?>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Prénom *</label>
        <input name="first_name" required value="<?= e($em['first_name'] ?? old('first_name')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Nom *</label>
        <input name="last_name" required value="<?= e($em['last_name'] ?? old('last_name')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Matricule *</label>
        <input name="matricule" required value="<?= e($em['matricule'] ?? old('matricule')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Agence de rattachement *</label>
        <select name="agency_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="">— Sélectionner —</option>
          <?php foreach ($agencies as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= ((int)($em['agency_id'] ?? old('agency_id'))===(int)$a['id'])?'selected':'' ?>>
              <?= e($a['name']) ?> (<?= e(ucfirst(str_replace('_',' ',$a['city']))) ?>)
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Poste *</label>
        <select name="position" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <?php foreach (['chauffeur','convoyeur','controleur','caissier','agent','superviseur','admin'] as $p): ?>
            <option value="<?= $p ?>" <?= (($em['position'] ?? old('position'))===$p)?'selected':'' ?>><?= e($p) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Téléphone</label>
        <input name="phone" value="<?= e($em['phone'] ?? old('phone')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input type="email" name="email" value="<?= e($em['email'] ?? old('email')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Date d'embauche *</label>
        <input type="date" name="hire_date" required value="<?= e($em['hire_date'] ?? old('hire_date', date('Y-m-d'))) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Salaire de base (FCFA) *</label>
        <input type="number" name="salary_base" required min="0" value="<?= e($em['salary_base'] ?? old('salary_base', 0)) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Prime journalière (FCFA)</label>
        <input type="number" name="daily_bonus" min="0" value="<?= e($em['daily_bonus'] ?? old('daily_bonus', 0)) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
    </div>
    <div class="flex justify-end gap-2">
      <a href="<?= e(url('rh/employees')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium">Enregistrer</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
