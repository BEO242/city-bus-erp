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
      <a href="<?= e(url('referentiel/tariffs?tab=cargo')) ?>"
         class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
      </a>
      <div>
        <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
          <span class="w-8 h-8 rounded-xl bg-cb-primary flex items-center justify-center">
            <i data-lucide="tags" class="w-4 h-4 text-white"></i>
          </span>
          Catégories fret
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">
          <?= count($categories) ?> catégorie<?= count($categories) !== 1 ? 's' : '' ?> ·
          Chaque catégorie peut avoir un ou plusieurs tarifs associés
        </p>
      </div>
    </div>
    <?php if (can('cargo.tariffs')): ?>
    <a href="<?= e(url('cargo/categories/create')) ?>"
       class="flex items-center gap-2 px-4 py-2 bg-cb-primary text-white rounded-xl text-sm font-semibold hover:bg-cb-dark transition">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle catégorie
    </a>
    <?php endif ?>
  </div>

  <?php if (empty($categories)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm text-center py-16">
    <i data-lucide="tags" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
    <p class="text-slate-400 text-sm font-semibold">Aucune catégorie configurée</p>
    <?php if (can('cargo.tariffs')): ?>
    <a href="<?= e(url('cargo/categories/create')) ?>"
       class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-cb-primary text-white text-sm rounded-xl font-semibold hover:bg-cb-dark transition">
      <i data-lucide="plus" class="w-4 h-4"></i> Créer la première catégorie
    </a>
    <?php endif ?>
  </div>

  <?php else: ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($categories as $cat):
      $cls    = $colorClasses[$cat['color']] ?? $colorClasses['slate'];
      $active = (bool)$cat['is_active'];
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden
                <?= !$active ? 'opacity-60' : '' ?> hover:shadow-md transition-shadow">

      <!-- Header coloré -->
      <div class="px-5 py-4 flex items-center justify-between gap-3
                  <?= $cls['bg'] ?>">
        <div class="flex items-center gap-3">
          <span class="w-9 h-9 rounded-xl flex items-center justify-center
                       <?= $cls['bg'] ?> ring-2 <?= $cls['ring'] ?>">
            <i data-lucide="tag" class="w-4 h-4 <?= $cls['text'] ?>"></i>
          </span>
          <div>
            <p class="font-black text-sm <?= $cls['text'] ?>"><?= e($cat['label']) ?></p>
            <code class="text-[11px] text-slate-500 font-mono"><?= e($cat['slug']) ?></code>
          </div>
        </div>
        <?php if (!$active): ?>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-500">Inactif</span>
        <?php endif ?>
      </div>

      <!-- Corps -->
      <div class="px-5 py-4 space-y-3">

        <!-- Tarification -->
        <div class="flex items-baseline gap-3">
          <span class="text-2xl font-black text-slate-900">
            <?= number_format((int)$cat['price_per_kg'], 0, ',', ' ') ?>
          </span>
          <span class="text-xs text-slate-400 font-medium">F/kg</span>
          <?php if ((int)$cat['min_price_fcfa'] > 0): ?>
          <span class="text-xs text-slate-500 ml-auto">
            min <strong class="text-slate-700"><?= number_format((int)$cat['min_price_fcfa'], 0, ',', ' ') ?> F</strong>
          </span>
          <?php endif ?>
        </div>

        <?php if (!empty($cat['description'])): ?>
        <p class="text-xs text-slate-500"><?= e($cat['description']) ?></p>
        <?php endif ?>

        <!-- Stats -->
        <div class="flex items-center gap-4 text-xs text-slate-500">
          <span class="flex items-center gap-1">
            <i data-lucide="package" class="w-3.5 h-3.5 text-slate-400"></i>
            <strong class="text-slate-800"><?= (int)$cat['parcel_count'] ?></strong> colis
          </span>
          <span class="flex items-center gap-1 ml-auto text-slate-300">
            <i data-lucide="arrow-up-down" class="w-3 h-3"></i>
            <?= (int)$cat['sort_order'] ?>
          </span>
        </div>

        <!-- Actions -->
        <?php if (can('cargo.tariffs')): ?>
        <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
          <a href="<?= e(url('cargo/categories/'.$cat['id'].'/edit')) ?>"
             class="flex-1 flex items-center justify-center gap-1.5 py-1.5 rounded-lg text-xs font-semibold
                    text-cb-primary hover:bg-cb-bg transition">
            <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Modifier
          </a>
          <form method="post" action="<?= e(url('cargo/categories/'.$cat['id'].'/delete')) ?>"
                onsubmit="return confirm('Supprimer la catégorie « <?= e(addslashes($cat['label'])) ?> » ?')">
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
  <?php endif ?>

</div>
<?php $view->end() ?>
