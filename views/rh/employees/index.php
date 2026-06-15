<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold">Personnel</h1>
      <p class="text-slate-500 text-sm">Employés et fiches RH.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= e(url('rh/positions')) ?>"
         class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
        <i data-lucide="briefcase" class="w-4 h-4 text-cb-primary"></i> Postes
      </a>
      <?php if (can('rh.create')): ?>
      <a href="<?= e(url('rh/employees/create')) ?>"
         class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium inline-flex items-center gap-2 text-sm hover:bg-cb-dark transition">
        <i data-lucide="user-plus" class="w-4 h-4"></i> Nouvel employé
      </a>
      <?php endif ?>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2 flex-wrap">
    <input name="q" value="<?= e($q ?? '') ?>" placeholder="Nom, matricule, téléphone…"
           class="flex-1 min-w-40 px-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
    <select name="position" class="px-3 py-2 rounded-xl border border-slate-200 text-sm bg-white focus:border-cb-primary outline-none">
      <option value="">Tous postes</option>
      <?php foreach ($allPositions as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= ($position ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach ?>
    </select>
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-700 transition">Filtrer</button>
  </form>

  <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($employees as $em): ?>
      <div class="bg-white rounded-2xl border border-slate-100 hover:border-cb-primary hover:shadow-soft p-5 transition flex items-center gap-4 <?= $em['deleted_at'] ? 'opacity-60' : '' ?>">
        <a href="<?= e(url('rh/employees/'.$em['id'])) ?>" class="flex items-center gap-4 flex-1 min-w-0">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cb-primary to-cb-secondary text-white flex items-center justify-center font-bold shrink-0">
            <?= e(strtoupper(($em['first_name'][0] ?? '').($em['last_name'][0] ?? ''))) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-bold truncate"><?= e($em['first_name']) ?> <?= e($em['last_name']) ?></div>
            <div class="text-xs text-slate-500"><?= e($em['matricule']) ?> · <?= e($em['position']) ?></div>
          </div>
        </a>
        <div class="flex items-center gap-1 shrink-0">
          <span class="text-xs px-2 py-0.5 rounded-full <?= $em['deleted_at'] ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700' ?>"><?= $em['deleted_at'] ? 'inactif' : 'actif' ?></span>
          <?php if (can('rh.edit')): ?>
          <a href="<?= e(url('rh/employees/'.$em['id'].'/edit')) ?>" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-cb-primary" title="Modifier"><i data-lucide="pencil" class="w-4 h-4"></i></a>
          <form method="post" action="<?= e(url('rh/employees/'.$em['id'].'/toggle')) ?>" onsubmit="return confirm('Confirmer ?')">
            <?= csrf_field() ?>
            <button type="submit" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-amber-500" title="<?= $em['deleted_at'] ? 'Réactiver' : 'Désactiver' ?>"><i data-lucide="<?= $em['deleted_at'] ? 'user-check' : 'user-x' ?>" class="w-4 h-4"></i></button>
          </form>
          <?php endif ?>
        </div>
      </div>
    <?php endforeach ?>
    <?php if (!$employees): ?>
      <div class="md:col-span-3 bg-white rounded-2xl border border-slate-100 p-12 text-center text-slate-400">Aucun employé</div>
    <?php endif ?>
  </div>

  <?php include BASE_PATH.'/views/components/_pagination.php'; ?>
</div>
<?php $view->end() ?>
