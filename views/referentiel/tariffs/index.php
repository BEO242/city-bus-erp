<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
  $tab           = $tab ?? 'passagers';
  $lineFilter    = (int)($lineFilter ?? 0);
  $lines         = $lines ?? [];
  $tariffs       = $tariffs ?? [];
  $parcelTariffs = $parcelTariffs ?? [];

  // Grouper les tarifs passagers par ligne
  $byLine = [];
  foreach ($tariffs as $t) {
      $byLine[$t['line_code']][] = $t;
  }

  $catLabels = [
    'adulte'    => 'Adulte',
    'enfant'    => 'Enfant',
    'etudiant'  => 'Étudiant',
    'senior'    => 'Senior',
    'vip'       => 'VIP',
  ];
  $typeLabels = [
    'aller_simple'  => 'Aller simple',
    'aller_retour'  => 'Aller-retour',
    'abonnement'    => 'Abonnement',
    'groupe'        => 'Groupe',
  ];
?>
<div class="space-y-5">

  <!-- En-tête -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-8 h-8 rounded-xl bg-cb-primary flex items-center justify-center">
          <i data-lucide="tags" class="w-4 h-4 text-white"></i>
        </span>
        Grille tarifaire
      </h1>
      <p class="text-xs text-slate-400 mt-0.5">
        <?= count($tariffs) ?> tarifs passagers · <?= count($parcelTariffs) ?> catégorie<?= count($parcelTariffs) !== 1 ? 's' : '' ?> fret
      </p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <!-- Filtre ligne -->
      <form method="get" class="flex items-center gap-2">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <select name="line_id" onchange="this.form.submit()"
                class="px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 bg-white">
          <option value="0">Toutes les lignes</option>
          <?php foreach ($lines as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $lineFilter === (int)$l['id'] ? 'selected' : '' ?>>
              <?= e($l['code']) ?> — <?= e($l['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </form>

      <?php if (can('referentiel.manage')): ?>
      <?php if ($tab === 'passagers'): ?>
        <a href="<?= e(url('referentiel/tariffs/create'.($lineFilter ? '?line_id='.$lineFilter : ''))) ?>"
           class="flex items-center gap-2 px-4 py-2 bg-cb-primary text-white rounded-xl text-sm font-semibold hover:bg-cb-dark transition">
          <i data-lucide="plus" class="w-4 h-4"></i> Nouveau tarif
        </a>
      <?php elseif ($tab === 'cargo'): ?>
        <a href="<?= e(url('cargo/categories/create')) ?>"
           class="flex items-center gap-2 px-4 py-2 bg-cb-primary text-white rounded-xl text-sm font-semibold hover:bg-cb-dark transition">
          <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle catégorie fret
        </a>
      <?php endif ?>
      <?php endif ?>
    </div>
  </div>

  <!-- Onglets -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex border-b border-slate-100 px-4 pt-4 gap-0.5 overflow-x-auto">
      <?php foreach ([
        ['passagers', 'ticket',  'Passagers', count($tariffs)],
        ['cargo',     'package', 'Fret',      count($parcelTariffs)],
      ] as [$key, $icon, $label, $count]): ?>
      <a href="?tab=<?= $key ?>&line_id=<?= $lineFilter ?>"
         class="flex items-center gap-1.5 px-4 pb-3 text-xs border-b-2 transition whitespace-nowrap
                <?= $tab === $key ? 'border-cb-primary text-cb-primary font-bold' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
        <i data-lucide="<?= $icon ?>" class="w-3.5 h-3.5"></i>
        <?= $label ?>
        <?php if ($count > 0): ?>
          <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none"><?= $count ?></span>
        <?php endif ?>
      </a>
      <?php endforeach ?>
    </div>

    <!-- ── ONGLET PASSAGERS ─────────────────────────────────────────── -->
    <?php if ($tab === 'passagers'): ?>
    <div class="p-0">
      <?php if (empty($tariffs)): ?>
        <div class="text-center py-16">
          <i data-lucide="ticket" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
          <p class="text-slate-400 text-sm font-semibold">Aucun tarif passager configuré</p>
          <?php if (can('referentiel.manage')): ?>
          <a href="<?= e(url('referentiel/tariffs/create')) ?>"
             class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-cb-primary text-white text-sm rounded-xl font-semibold hover:bg-cb-dark transition">
            <i data-lucide="plus" class="w-4 h-4"></i> Créer le premier tarif
          </a>
          <?php endif ?>
        </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50/80 border-b border-slate-100">
            <tr class="text-[10px] text-slate-400 uppercase tracking-wider">
              <th class="px-5 py-3 text-left font-semibold">Ligne / Destination</th>
              <th class="px-5 py-3 text-left font-semibold">Type billet</th>
              <th class="px-5 py-3 text-left font-semibold">Passager</th>
              <th class="px-5 py-3 text-left font-semibold">Classe</th>
              <th class="px-5 py-3 text-right font-semibold">Prix FCFA</th>
              <th class="px-5 py-3 text-center font-semibold">Validité</th>
              <th class="px-5 py-3 text-center font-semibold">Statut</th>
              <?php if (can('referentiel.manage')): ?><th class="px-5 py-3"></th><?php endif ?>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <?php
              $prevLine = null;
              foreach ($tariffs as $t):
                $isNewLine = $t['line_code'] !== $prevLine;
                $prevLine  = $t['line_code'];
                $active    = (bool)$t['is_active'];
                $expired   = !empty($t['valid_until']) && $t['valid_until'] < date('Y-m-d');
                $statusCls = !$active ? 'bg-slate-100 text-slate-400' : ($expired ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700');
                $statusLbl = !$active ? 'Inactif' : ($expired ? 'Expiré' : 'Actif');
            ?>
            <?php if ($isNewLine): ?>
            <tr class="bg-slate-50/60">
              <td colspan="<?= can('referentiel.manage') ? 8 : 7 ?>" class="px-5 py-2">
                <span class="font-black text-xs text-cb-primary uppercase tracking-wide"><?= e($t['line_code']) ?></span>
                <span class="text-xs text-slate-400 ml-2"><?= e($t['line_name']) ?></span>
              </td>
            </tr>
            <?php endif ?>
            <tr class="hover:bg-slate-50 transition <?= !$active ? 'opacity-60' : '' ?>">
              <td class="px-5 py-3 pl-8">
                <?php
                  // Construire le libellé origine → destination
                  $originLabel = $t['origin_stop_name'] ?? $t['departure_city_name'] ?? null;
                  $destLabel   = $t['destination_stop_name'] ?? null;
                  $fullDest    = $t['arrival_city_name'] ?? null;
                  $isSegment   = !empty($t['origin_stop_id']) || !empty($t['destination_stop_id']);
                ?>
                <?php if ($isSegment): ?>
                  <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full mt-0.5">
                    <i data-lucide="map-pin" class="w-2.5 h-2.5"></i>
                    <?= e($originLabel ?? 'Départ') ?> → <?= e($destLabel ?? $fullDest ?? 'Terminus') ?>
                  </span>
                <?php elseif (!empty($fullDest)): ?>
                  <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full mt-0.5">
                    <i data-lucide="flag" class="w-2.5 h-2.5"></i>
                    <?= e($t['departure_city_name'] ?? '') ?> → <?= e($fullDest) ?>
                  </span>
                <?php endif ?>
              </td>
              <td class="px-5 py-3 text-xs font-semibold text-slate-700">
                <?= e($typeLabels[$t['ticket_type']] ?? ucfirst(str_replace('_',' ',$t['ticket_type']))) ?>
              </td>
              <td class="px-5 py-3 text-xs text-slate-600">
                <?php
                  $cats = json_decode($t['passenger_categories'] ?? '[]', true) ?: [];
                  if (empty($cats)) {
                      echo 'Tous';
                  } else {
                      $catStr = implode(', ', array_map(fn($c) => $catLabels[$c] ?? ucfirst($c), $cats));
                      echo e($catStr);
                  }
                ?>
              </td>
              <td class="px-5 py-3 text-xs text-slate-500">
                <?= e(ucfirst($t['travel_class'] ?? '—')) ?>
              </td>
              <td class="px-5 py-3 text-right font-black text-base text-slate-900">
                <?= number_format((int)$t['price_fcfa'], 0, ',', ' ') ?>
              </td>
              <td class="px-5 py-3 text-center text-xs text-slate-400">
                <?php if (!empty($t['valid_from']) || !empty($t['valid_until'])): ?>
                  <?= e((!empty($t['valid_from']) ? date('d/m/Y', strtotime($t['valid_from'])) : '…')) ?>
                  →
                  <?= e((!empty($t['valid_until']) ? date('d/m/Y', strtotime($t['valid_until'])) : '∞')) ?>
                <?php else: ?>
                  <span class="text-slate-300">Permanent</span>
                <?php endif ?>
              </td>
              <td class="px-5 py-3 text-center">
                <span class="text-[10px] font-bold px-2.5 py-1 rounded-full <?= $statusCls ?>"><?= $statusLbl ?></span>
              </td>
              <?php if (can('referentiel.manage')): ?>
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <a href="<?= e(url('referentiel/tariffs/'.$t['id'].'/edit')) ?>"
                     class="p-1.5 rounded-lg text-slate-400 hover:text-cb-primary hover:bg-cb-bg transition">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                  </a>
                  <form method="post" action="<?= e(url('referentiel/tariffs/'.$t['id'].'/delete')) ?>"
                        onsubmit="return confirm('Supprimer ce tarif ?')">
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
      <?php endif ?>
    </div>

    <!-- ── ONGLET FRET (catégories = source unique de tarification) ── -->
    <?php elseif ($tab === 'cargo'): ?>
    <?php
      $fretColorClasses = [
        'slate'  => ['bg' => 'bg-slate-100',  'text' => 'text-slate-700'],
        'red'    => ['bg' => 'bg-red-100',    'text' => 'text-red-700'],
        'orange' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700'],
        'amber'  => ['bg' => 'bg-amber-100',  'text' => 'text-amber-700'],
        'green'  => ['bg' => 'bg-emerald-100','text' => 'text-emerald-700'],
        'blue'   => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700'],
        'violet' => ['bg' => 'bg-violet-100', 'text' => 'text-violet-700'],
        'pink'   => ['bg' => 'bg-pink-100',   'text' => 'text-pink-700'],
      ];
    ?>
    <div class="p-0">
      <?php if (empty($parcelTariffs)): ?>
        <div class="text-center py-16">
          <i data-lucide="package" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
          <p class="text-slate-400 text-sm font-semibold">Aucune catégorie fret configurée</p>
          <?php if (can('referentiel.manage')): ?>
          <a href="<?= e(url('cargo/categories/create')) ?>"
             class="inline-flex items-center gap-2 mt-3 px-4 py-2 bg-cb-primary text-white text-sm rounded-xl font-semibold hover:bg-cb-dark transition">
            <i data-lucide="plus" class="w-4 h-4"></i> Créer la première catégorie
          </a>
          <?php endif ?>
        </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50/80 border-b border-slate-100">
            <tr class="text-[10px] text-slate-400 uppercase tracking-wider">
              <th class="px-5 py-3 text-left font-semibold">Catégorie</th>
              <th class="px-5 py-3 text-right font-semibold">Prix / kg</th>
              <th class="px-5 py-3 text-right font-semibold">Minimum</th>
              <th class="px-5 py-3 text-center font-semibold">Colis</th>
              <th class="px-5 py-3 text-center font-semibold">Statut</th>
              <?php if (can('referentiel.manage')): ?><th class="px-5 py-3"></th><?php endif ?>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <?php foreach ($parcelTariffs as $pt):
                $active    = (bool)$pt['is_active'];
                $statusCls = $active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400';
                $statusLbl = $active ? 'Actif' : 'Inactif';
                $cls       = $fretColorClasses[$pt['color'] ?? 'slate'] ?? $fretColorClasses['slate'];
            ?>
            <tr class="hover:bg-slate-50 transition <?= !$active ? 'opacity-60' : '' ?>">
              <td class="px-5 py-3">
                <div class="flex items-center gap-2.5">
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $cls['bg'] ?> <?= $cls['text'] ?>">
                    <i data-lucide="tag" class="w-3 h-3"></i>
                    <?= e($pt['label']) ?>
                  </span>
                  <code class="text-[10px] text-slate-400 font-mono"><?= e($pt['category']) ?></code>
                </div>
              </td>
              <td class="px-5 py-3 text-right font-black text-slate-900">
                <?= number_format((int)$pt['price_per_kg'], 0, ',', ' ') ?>
                <span class="text-xs font-normal text-slate-400">F/kg</span>
              </td>
              <td class="px-5 py-3 text-right text-xs text-slate-600">
                <?= (int)$pt['min_price_fcfa'] > 0
                    ? number_format((int)$pt['min_price_fcfa'], 0, ',', ' ').' F'
                    : '<span class="text-slate-300">—</span>' ?>
              </td>
              <td class="px-5 py-3 text-center text-xs text-slate-600">
                <strong class="text-slate-800"><?= (int)($pt['parcel_count'] ?? 0) ?></strong>
              </td>
              <td class="px-5 py-3 text-center">
                <span class="text-[10px] font-bold px-2.5 py-1 rounded-full <?= $statusCls ?>"><?= $statusLbl ?></span>
              </td>
              <?php if (can('referentiel.manage')): ?>
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <a href="<?= e(url('cargo/categories/'.$pt['id'].'/edit')) ?>"
                     class="p-1.5 rounded-lg text-slate-400 hover:text-cb-primary hover:bg-cb-bg transition">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                  </a>
                  <form method="post" action="<?= e(url('cargo/categories/'.$pt['id'].'/delete')) ?>"
                        onsubmit="return confirm('Supprimer la catégorie « <?= e(addslashes($pt['label'])) ?> » ?')">
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
      <?php endif ?>
    </div>

    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
