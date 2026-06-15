<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Rôles</h1>
    <p class="text-sm text-slate-500 mt-1"><?= count($roles) ?> rôle(s)</p>
  </div>
  <?php if (can('admin.roles.manage')): ?>
    <a href="<?= e(url('admin/roles/create')) ?>"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cb-primary text-white font-semibold hover:bg-cb-secondary">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouveau rôle
    </a>
  <?php endif ?>
</div>

<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php foreach ($roles as $r): ?>
    <div class="bg-white rounded-2xl shadow-soft p-5">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h3 class="font-semibold text-slate-800 flex items-center gap-2">
            <?= e($r['label']) ?>
            <?php if ((int)$r['is_system'] === 1): ?>
              <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">SYSTÈME</span>
            <?php endif ?>
          </h3>
          <p class="text-xs text-slate-500 font-mono mt-0.5"><?= e($r['slug']) ?></p>
          <?php if ($r['description']): ?>
            <p class="text-sm text-slate-600 mt-2"><?= e($r['description']) ?></p>
          <?php endif ?>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3 mt-4 text-center">
        <div class="p-2 rounded-lg bg-cb-bg">
          <div class="text-lg font-bold text-cb-primary"><?= (int)$r['users_count'] ?></div>
          <div class="text-[10px] uppercase text-slate-500">Utilisateurs</div>
        </div>
        <div class="p-2 rounded-lg bg-emerald-50">
          <div class="text-lg font-bold text-emerald-600"><?= (int)$r['perms_count'] ?></div>
          <div class="text-[10px] uppercase text-slate-500">Permissions</div>
        </div>
      </div>
      <div class="flex items-center gap-2 mt-4">
        <?php if (can('admin.roles.manage')): ?>
          <a href="<?= e(url('admin/roles/'.$r['id'].'/edit')) ?>"
             class="flex-1 text-center px-3 py-1.5 rounded-lg bg-cb-bg text-cb-primary text-sm font-semibold hover:bg-cb-accent/20">Modifier</a>
          <?php if ((int)$r['is_system'] === 0): ?>
            <form method="post" action="<?= e(url('admin/roles/'.$r['id'].'/delete')) ?>" onsubmit="return confirm('Supprimer ce rôle ?')">
              <?= csrf_field() ?>
              <button class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-600 text-sm hover:bg-rose-100"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
            </form>
          <?php endif ?>
        <?php endif ?>
      </div>
    </div>
  <?php endforeach ?>
</div>

<?php $view->end() ?>
