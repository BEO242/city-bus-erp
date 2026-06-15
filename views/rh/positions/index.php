<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$colorClasses = [
  'slate'  => ['bg' => 'bg-slate-100',  'text' => 'text-slate-700',  'ring' => 'ring-slate-300'],
  'red'    => ['bg' => 'bg-red-100',    'text' => 'text-red-700',    'ring' => 'ring-red-300'],
  'orange' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'ring' => 'ring-orange-300'],
  'amber'  => ['bg' => 'bg-amber-100',  'text' => 'text-amber-700',  'ring' => 'ring-amber-300'],
  'green'  => ['bg' => 'bg-emerald-100','text' => 'text-emerald-700','ring' => 'ring-emerald-300'],
  'blue'   => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'ring' => 'ring-blue-300'],
  'violet' => ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'ring' => 'ring-violet-300'],
  'pink'   => ['bg' => 'bg-pink-100',   'text' => 'text-pink-700',   'ring' => 'ring-pink-300'],
];
?>
<div class="space-y-5">

  <!-- En-tête -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div class="flex items-center gap-3">
      <a href="<?= e(url('rh/employees')) ?>"
         class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
      </a>
      <div>
        <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
          <span class="w-8 h-8 rounded-xl bg-cb-primary flex items-center justify-center">
            <i data-lucide="briefcase" class="w-4 h-4 text-white"></i>
          </span>
          Postes &amp; fonctions
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">
          <?= count($positions) ?> poste<?= count($positions) !== 1 ? 's' : '' ?> configuré<?= count($positions) !== 1 ? 's' : '' ?>
        </p>
      </div>
    </div>
    <?php if (can('rh.create')): ?>
    <a href="<?= e(url('rh/positions/create')) ?>"
       class="flex items-center gap-2 px-4 py-2 bg-cb-primary text-white rounded-xl text-sm font-semibold hover:bg-cb-dark transition">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouveau poste
    </a>
    <?php endif ?>
  </div>

  <?php if (empty($positions)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm text-center py-16">
    <i data-lucide="briefcase" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
    <p class="text-slate-400 text-sm font-semibold">Aucun poste configuré</p>
    <?php if (can('rh.create')): ?>
    <a href="<?= e(url('rh/positions/create')) ?>"
       class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-cb-primary text-white text-sm rounded-xl font-semibold hover:bg-cb-dark transition">
      <i data-lucide="plus" class="w-4 h-4"></i> Créer le premier poste
    </a>
    <?php endif ?>
  </div>

  <?php else: ?>

  <?php foreach ($byDept as $dept => $deptPositions): ?>
  <div class="space-y-3">
    <!-- Séparateur département -->
    <div class="flex items-center gap-3">
      <span class="text-xs font-black text-slate-400 uppercase tracking-widest"><?= e($dept) ?></span>
      <div class="flex-1 h-px bg-slate-100"></div>
      <span class="text-xs text-slate-400"><?= count($deptPositions) ?> poste<?= count($deptPositions) !== 1 ? 's' : '' ?></span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($deptPositions as $pos):
        $cls    = $colorClasses[$pos['color']] ?? $colorClasses['slate'];
        $active = (bool)$pos['is_active'];
        $count  = (int)$pos['employee_count'];
      ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden
                  <?= !$active ? 'opacity-60' : '' ?> hover:shadow-md transition-shadow">

        <!-- Header coloré -->
        <div class="px-5 py-4 <?= $cls['bg'] ?> flex items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-xl flex items-center justify-center
                         <?= $cls['bg'] ?> ring-2 <?= $cls['ring'] ?>">
              <i data-lucide="briefcase" class="w-4 h-4 <?= $cls['text'] ?>"></i>
            </span>
            <div>
              <p class="font-black text-sm <?= $cls['text'] ?>"><?= e($pos['label']) ?></p>
              <code class="text-[11px] text-slate-500 font-mono"><?= e($pos['code']) ?></code>
            </div>
          </div>
          <?php if (!$active): ?>
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-500">Inactif</span>
          <?php endif ?>
        </div>

        <!-- Corps -->
        <div class="px-5 py-4 space-y-3">

          <!-- Stat employés -->
          <div class="flex items-center gap-2 text-sm">
            <i data-lucide="users" class="w-4 h-4 text-slate-400"></i>
            <span class="font-black text-slate-900"><?= $count ?></span>
            <span class="text-slate-400 text-xs">employé<?= $count !== 1 ? 's' : '' ?> actif<?= $count !== 1 ? 's' : '' ?></span>
          </div>

          <?php if (!empty($pos['description'])): ?>
          <p class="text-xs text-slate-500"><?= e($pos['description']) ?></p>
          <?php endif ?>

          <!-- Actions -->
          <?php if (can('rh.edit')): ?>
          <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
            <a href="<?= e(url('rh/positions/'.$pos['id'].'/edit')) ?>"
               class="flex-1 flex items-center justify-center gap-1.5 py-1.5 rounded-lg text-xs font-semibold
                      text-cb-primary hover:bg-cb-bg transition">
              <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Modifier
            </a>
            <form method="post" action="<?= e(url('rh/positions/'.$pos['id'].'/delete')) ?>"
                  onsubmit="return confirm('Supprimer le poste « <?= e(addslashes($pos['label'])) ?> » ?')">
              <?= csrf_field() ?>
              <button type="submit"
                      class="flex items-center justify-center p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                      title="Supprimer">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
              </button>
            </form>
          </div>
          <?php endif ?>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endforeach ?>

  <?php endif ?>
</div>
<?php $view->end() ?>
