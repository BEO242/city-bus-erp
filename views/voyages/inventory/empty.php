<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="space-y-5">

  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('voyages/' . $trip['id'])) ?>" class="hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i>
      Voyage <?= e($trip['trip_code']) ?>
    </a>
    <span>/</span>
    <span class="text-slate-800 font-semibold">Inventaire</span>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center shadow-soft">
    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-100 flex items-center justify-center">
      <i data-lucide="layers" class="w-8 h-8 text-amber-600"></i>
    </div>
    <h2 class="text-xl font-bold text-slate-900 mb-2">Aucun inventaire généré</h2>
    <p class="text-sm text-slate-500 mb-6 max-w-md mx-auto">
      Ce voyage n'a pas encore d'inventaire par classe. Cliquez ci-dessous pour répartir
      automatiquement les <?= (int)$trip['seats'] ?> sièges du bus entre les classes Y/B/M/H/L.
    </p>
    <?php if (can('voyages.inventory.manage')): ?>
      <form method="post" action="<?= e(url('voyages/' . $trip['id'] . '/inventory/regenerate')) ?>">
        <?= csrf_field() ?>
        <button class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-cb-primary text-white font-semibold hover:bg-cb-secondary shadow-soft">
          <i data-lucide="zap" class="w-4 h-4"></i>
          Générer l'inventaire
        </button>
      </form>
    <?php endif ?>
  </div>

</div>

<?php $view->end() ?>
