<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">

  <!-- En-tête -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-8 h-8 rounded-xl bg-cb-primary flex items-center justify-center">
          <i data-lucide="package" class="w-4 h-4 text-white"></i>
        </span>
        Tarifs fret
      </h1>
      <p class="text-xs text-slate-400 mt-0.5">
        Barèmes au kg appliqués au calcul des prix colis · <?= count($tariffs) ?> tarif<?= count($tariffs) !== 1 ? 's' : '' ?>
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= e(url('cargo/categories')) ?>"
         class="flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
        <i data-lucide="tags" class="w-4 h-4"></i> Catégories
      </a>
      <?php if (can('cargo.manage') || can('referentiel.manage') || can('cargo.tariffs')): ?>
      <a href="<?= e(url('cargo/tariffs/create')) ?>"
         class="flex items-center gap-2 px-4 py-2 bg-cb-primary text-white rounded-xl text-sm font-semibold hover:bg-cb-dark transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Nouveau tarif
      </a>
      <?php endif ?>
    </div>
  </div>

  <?php if (empty($tariffs)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm text-center py-16">
    <i data-lucide="package" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
    <p class="text-slate-400 text-sm font-semibold">Aucun tarif fret configuré</p>
    <?php if (can('cargo.manage') || can('referentiel.manage')): ?>
    <a href="<?= e(url('cargo/tariffs/create')) ?>"
       class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-cb-primary text-white text-sm rounded-xl font-semibold hover:bg-cb-dark transition">
      <i data-lucide="plus" class="w-4 h-4"></i> Créer le premier tarif
    </a>
    <?php endif ?>
  </div>

  <?php else: ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50/80 border-b border-slate-100">
        <tr class="text-[10px] text-slate-400 uppercase tracking-wider">
          <th class="px-5 py-3 text-left font-semibold">Catégorie</th>
          <th class="px-5 py-3 text-left font-semibold">Libellé</th>
          <th class="px-5 py-3 text-right font-semibold">Prix / kg</th>
          <th class="px-5 py-3 text-right font-semibold">Minimum</th>
          <th class="px-5 py-3 text-center font-semibold">Validité</th>
          <th class="px-5 py-3 text-center font-semibold">Statut</th>
          <?php if (can('cargo.manage') || can('referentiel.manage')): ?>
          <th class="px-5 py-3"></th>
          <?php endif ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php
          $prevCat = null;
          foreach ($tariffs as $t):
            $isNewCat  = $t['category'] !== $prevCat;
            $prevCat   = $t['category'];
            $active    = (bool)$t['is_active'];
            $expired   = !empty($t['valid_until']) && $t['valid_until'] < date('Y-m-d');
            $statusCls = !$active ? 'bg-slate-100 text-slate-400'
                       : ($expired  ? 'bg-rose-100 text-rose-700'
                       : 'bg-emerald-100 text-emerald-700');
            $statusLbl = !$active ? 'Inactif' : ($expired ? 'Expiré' : 'Actif');
            $catLabel  = $categories[$t['category']] ?? ucfirst($t['category']);
        ?>
        <?php if ($isNewCat): ?>
        <tr class="bg-slate-50/60">
          <td colspan="<?= (can('cargo.manage') || can('referentiel.manage')) ? 7 : 6 ?>" class="px-5 py-2">
            <span class="font-black text-xs text-cb-primary uppercase tracking-wide">
              <i data-lucide="tag" class="w-3 h-3 inline mr-1"></i><?= e($catLabel) ?>
            </span>
          </td>
        </tr>
        <?php endif ?>
        <tr class="hover:bg-slate-50 transition <?= !$active ? 'opacity-60' : '' ?>">
          <td class="px-5 py-3 pl-8">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-cb-bg text-cb-primary">
              <?= e($catLabel) ?>
            </span>
          </td>
          <td class="px-5 py-3 text-slate-700 font-medium text-xs">
            <?= !empty($t['label']) ? e($t['label']) : '<span class="text-slate-300">—</span>' ?>
          </td>
          <td class="px-5 py-3 text-right font-black text-base text-slate-900">
            <?= number_format((int)$t['price_per_kg'], 0, ',', ' ') ?>
            <span class="text-xs font-normal text-slate-400">F/kg</span>
          </td>
          <td class="px-5 py-3 text-right text-xs font-semibold text-slate-700">
            <?php if ((int)$t['min_price_fcfa'] > 0): ?>
              <?= number_format((int)$t['min_price_fcfa'], 0, ',', ' ') ?>
              <span class="text-slate-400 font-normal">FCFA min</span>
            <?php else: ?>
              <span class="text-slate-300">—</span>
            <?php endif ?>
          </td>
          <td class="px-5 py-3 text-center text-xs text-slate-400">
            <?php if (!empty($t['valid_from']) || !empty($t['valid_until'])): ?>
              <?= !empty($t['valid_from']) ? date('d/m/Y', strtotime($t['valid_from'])) : '…' ?>
              →
              <?= !empty($t['valid_until']) ? date('d/m/Y', strtotime($t['valid_until'])) : '∞' ?>
            <?php else: ?>
              <span class="text-slate-300">Permanent</span>
            <?php endif ?>
          </td>
          <td class="px-5 py-3 text-center">
            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full <?= $statusCls ?>"><?= $statusLbl ?></span>
          </td>
          <?php if (can('cargo.manage') || can('referentiel.manage')): ?>
          <td class="px-5 py-3 text-right">
            <div class="flex items-center justify-end gap-1">
              <a href="<?= e(url('cargo/tariffs/'.$t['id'].'/edit')) ?>"
                 class="p-1.5 rounded-lg text-slate-400 hover:text-cb-primary hover:bg-cb-bg transition">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
              </a>
              <form method="post" action="<?= e(url('cargo/tariffs/'.$t['id'].'/delete')) ?>"
                    onsubmit="return confirm('Supprimer ce tarif fret ?')">
                <?= csrf_field() ?>
                <button class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </form>
            </div>
          </td>
          <?php endif ?>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <!-- Note pédagogique -->
  <div class="flex items-start gap-3 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 text-xs text-amber-700">
    <i data-lucide="info" class="w-4 h-4 mt-0.5 shrink-0"></i>
    <span>
      <strong>Calcul du prix :</strong> <code class="bg-amber-100 px-1 py-0.5 rounded">max(montant minimum, poids × prix/kg)</code>.
      Si aucun tarif ne correspond à la catégorie saisie, le prix est calculé à 0 et doit être corrigé manuellement.
    </span>
  </div>
  <?php endif ?>

</div>
<?php $view->end() ?>
