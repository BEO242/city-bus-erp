<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
    <i data-lucide="map" class="w-6 h-6 text-cb-primary"></i> Tarifs O-D
  </h1>

  <form method="get" class="bg-white rounded-2xl p-4 shadow-soft border border-slate-100 flex items-end gap-3">
    <div class="flex-1">
      <label class="block text-xs font-semibold text-slate-600 mb-1">Ligne</label>
      <select name="line_id" class="w-full px-3 py-2 rounded-lg border border-slate-200">
        <option value="0">— Choisir —</option>
        <?php foreach ($lines as $l): ?>
          <option value="<?= (int)$l['id'] ?>" <?= $lineId==$l['id']?'selected':'' ?>><?= e($l['code']) ?> · <?= e($l['name']) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <button class="px-4 py-2 rounded-lg bg-cb-primary text-white font-semibold">Afficher</button>
    <?php if ($lineId > 0 && can('od_fares.manage')): ?>
      <form method="post" action="<?= e(url('pnr/od-fares/bulk')) ?>" class="flex items-end gap-2 ml-auto" onsubmit="return confirm('Générer tous les couples O-D × classes pour cette ligne ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="line_id" value="<?= $lineId ?>">
        <input name="base_price" type="number" min="100" value="5000" class="w-28 px-2 py-2 rounded-lg border border-slate-200 text-sm" placeholder="Prix base">
        <button class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold"><i data-lucide="zap" class="inline w-4 h-4"></i> Générer</button>
      </form>
    <?php endif ?>
  </form>

  <?php if ($lineId > 0): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-600">
          <tr>
            <th class="px-3 py-2 text-left">De</th>
            <th class="px-3 py-2 text-left">À</th>
            <th class="px-3 py-2 text-center">Classe</th>
            <th class="px-3 py-2 text-center">Pax</th>
            <th class="px-3 py-2 text-center">Fare basis</th>
            <th class="px-3 py-2 text-right">Prix FCFA</th>
            <th class="px-3 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($fares as $f): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-3 py-2"><?= e($f['from_name']) ?></td>
              <td class="px-3 py-2"><?= e($f['to_name']) ?></td>
              <td class="px-3 py-2 text-center font-mono font-bold"><?= e($f['booking_class']) ?></td>
              <td class="px-3 py-2 text-center text-xs"><?= e($f['pax_type']) ?></td>
              <td class="px-3 py-2 text-center text-xs"><?= e($f['fare_basis']) ?></td>
              <td class="px-3 py-2 text-right">
                <?php if (can('od_fares.manage')): ?>
                  <form method="post" action="<?= e(url('pnr/od-fares/' . $f['id'])) ?>" class="inline-flex items-center gap-1">
                    <?= csrf_field() ?>
                    <input name="base_price_fcfa" type="number" min="0" value="<?= (int)$f['base_price_fcfa'] ?>" class="w-24 px-1 py-1 text-right rounded border border-slate-200 font-mono">
                    <button class="text-xs text-emerald-700 hover:underline">OK</button>
                  </form>
                <?php else: ?>
                  <span class="font-mono"><?= number_format((int)$f['base_price_fcfa']) ?></span>
                <?php endif ?>
              </td>
              <td class="px-3 py-2 text-right">
                <?php if (can('od_fares.manage')): ?>
                  <form method="post" action="<?= e(url('pnr/od-fares/' . $f['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Supprimer ?')">
                    <?= csrf_field() ?>
                    <button class="text-xs text-rose-600 hover:underline">Suppr.</button>
                  </form>
                <?php endif ?>
              </td>
            </tr>
          <?php endforeach ?>
          <?php if (empty($fares)): ?>
            <tr><td colspan="7" class="px-3 py-12 text-center text-slate-400">Aucun tarif. Utilisez "Générer" pour créer la grille O-D × classes.</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>
<?php $view->end() ?>
