<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="space-y-5">

  <!-- Breadcrumb -->
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('voyages/' . $trip['id'])) ?>" class="hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i>
      Voyage <?= e($trip['trip_code']) ?>
    </a>
    <span>/</span>
    <span class="text-slate-800 font-semibold">Inventaire & classes</span>
  </div>

  <!-- Header -->
  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
    <div class="flex items-start justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
          <i data-lucide="layers" class="w-6 h-6 text-cb-primary"></i>
          Inventaire du voyage
        </h1>
        <p class="text-sm text-slate-500 mt-1">
          <?= e($trip['line_code']) ?> · <?= e($trip['line_name']) ?>
          · <?= e(date('d/m/Y', strtotime($trip['trip_date']))) ?>
          · Bus <?= e($trip['bus_code']) ?> (<?= (int)$trip['seats'] ?> places)
        </p>
      </div>
      <?php if (can('voyages.inventory.manage')): ?>
        <form method="post" action="<?= e(url('voyages/' . $trip['id'] . '/inventory/regenerate')) ?>" onsubmit="return confirm('Régénérer effacera l\'inventaire actuel. Continuer ?')">
          <?= csrf_field() ?>
          <button class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-slate-200 rounded-lg hover:bg-slate-50">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i> Régénérer
          </button>
        </form>
      <?php endif ?>
    </div>
  </div>

  <!-- KPIs globaux -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase">Capacité totale</div>
      <div class="text-2xl font-bold text-slate-900 mt-1"><?= (int)$totalCapacity ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase">Vendus</div>
      <div class="text-2xl font-bold text-emerald-600 mt-1"><?= (int)$totalSold ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase">Réservés</div>
      <div class="text-2xl font-bold text-amber-600 mt-1"><?= (int)$totalReserved ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase">Disponibles</div>
      <div class="text-2xl font-bold text-slate-900 mt-1"><?= (int)$totalAvail ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase">Charge</div>
      <div class="text-2xl font-bold mt-1 <?= $loadFactor >= 80 ? 'text-emerald-600' : ($loadFactor >= 50 ? 'text-amber-600' : 'text-slate-900') ?>"><?= $loadFactor ?>%</div>
    </div>
  </div>

  <!-- Recettes prévisionnelles -->
  <div class="bg-gradient-to-r from-cb-primary to-cb-secondary text-white rounded-2xl p-5 shadow-soft">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-xs font-semibold uppercase opacity-90">Recettes générées</div>
        <div class="text-3xl font-bold mt-1"><?= e(fcfa($totalRevenue)) ?></div>
      </div>
      <i data-lucide="trending-up" class="w-12 h-12 opacity-50"></i>
    </div>
  </div>

  <!-- Détail par classe -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100">
      <h2 class="font-semibold text-slate-900">Classes d'inventaire</h2>
    </div>
    <div class="divide-y divide-slate-100">
      <?php foreach ($inventory as $cls):
        $available = (int)$cls['available'];
        $loadCls = $cls['capacity'] > 0 ? round((int)$cls['sold_count'] / (int)$cls['capacity'] * 100) : 0;
      ?>
        <div class="p-5">
          <div class="flex items-start justify-between flex-wrap gap-4 mb-3">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-xl flex items-center justify-center font-bold text-white text-lg shadow-soft"
                   style="background-color: <?= e($cls['color_hex']) ?>">
                <?= e($cls['class_code']) ?>
              </div>
              <div>
                <div class="font-bold text-slate-900"><?= e($cls['label']) ?></div>
                <div class="text-xs text-slate-500">
                  Flexibilité : <?= e(\CityBus\Models\InventoryClass::FLEXIBILITY[$cls['flexibility']] ?? $cls['flexibility']) ?>
                  · Embarquement priorité <?= (int)$cls['priority_boarding'] ?>
                </div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-2xl font-bold text-slate-900"><?= e(fcfa((int)$cls['price_fcfa'])) ?></div>
              <?php if ((int)$cls['price_fcfa'] !== (int)$cls['base_price_fcfa']): ?>
                <div class="text-xs text-slate-500">Base : <?= e(fcfa((int)$cls['base_price_fcfa'])) ?></div>
              <?php endif ?>
            </div>
          </div>

          <!-- Stats classe -->
          <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-3">
            <div>
              <div class="text-xs text-slate-500">Capacité</div>
              <div class="font-bold"><?= (int)$cls['capacity'] ?></div>
            </div>
            <div>
              <div class="text-xs text-slate-500">Vendus</div>
              <div class="font-bold text-emerald-600"><?= (int)$cls['sold_count'] ?></div>
            </div>
            <div>
              <div class="text-xs text-slate-500">Réservés</div>
              <div class="font-bold text-amber-600"><?= (int)$cls['reserved_count'] ?></div>
            </div>
            <div>
              <div class="text-xs text-slate-500">Bloqués</div>
              <div class="font-bold text-slate-700"><?= (int)$cls['blocked_count'] ?></div>
            </div>
            <div>
              <div class="text-xs text-slate-500">Disponibles</div>
              <div class="font-bold <?= $available > 0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $available ?></div>
            </div>
          </div>

          <!-- Barre de charge -->
          <div class="bg-slate-100 rounded-full h-2 overflow-hidden mb-3">
            <div class="h-full rounded-full transition-all"
                 style="width: <?= min(100, $loadCls) ?>%; background-color: <?= e($cls['color_hex']) ?>"></div>
          </div>

          <?php if (can('voyages.inventory.manage')): ?>
            <details class="mt-3 border-t border-slate-100 pt-3">
              <summary class="cursor-pointer text-xs text-slate-500 hover:text-cb-primary font-medium">
                Modifier cette classe
              </summary>
              <form method="post" action="<?= e(url('voyages/' . $trip['id'] . '/inventory/' . $cls['class_code'])) ?>" class="mt-3 grid grid-cols-2 md:grid-cols-5 gap-2 items-end">
                <?= csrf_field() ?>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Capacité</label>
                  <input type="number" name="capacity" value="<?= (int)$cls['capacity'] ?>" min="<?= (int)$cls['sold_count'] ?>"
                         class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Prix FCFA</label>
                  <input type="number" name="price_fcfa" value="<?= (int)$cls['price_fcfa'] ?>"
                         class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Bloqués</label>
                  <input type="number" name="blocked_count" value="<?= (int)$cls['blocked_count'] ?>"
                         class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Surbook %</label>
                  <input type="number" name="overbooking_pct" value="<?= (int)$cls['overbooking_pct'] ?>" min="0" max="50"
                         class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-cb-primary text-white text-sm font-semibold hover:bg-cb-secondary">
                  Enregistrer
                </button>
              </form>
            </details>
          <?php endif ?>
        </div>
      <?php endforeach ?>
    </div>
  </div>

</div>

<?php $view->end() ?>
