<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$qp = array_filter([
    'line_id'  => ($lineFilter   ?? 0) > 0 ? (string)$lineFilter : '',
    'nature_id'=> ($natureFilter ?? 0) > 0 ? (string)$natureFilter : '',
    'status'   => $statusFilter  ?? '',
], fn($v) => $v !== '' && $v !== null);
?>
<?php $view->start('content') ?>

<div class="space-y-4">

  <!-- En-tête ──────────────────────────────────────────────────────────── -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Tarifs bagages</h1>
      <p class="text-slate-500 text-sm"><?= count($tariffs) ?> tarif(s) bagage</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <a href="<?= e(url('referentiel/tariffs')) ?>"
         class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition">
        <i data-lucide="ticket" class="w-4 h-4"></i> Tarifs passagers
      </a>
      <a href="<?= e(url('referentiel/baggage-tariffs/create')) ?>"
         class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-medium inline-flex items-center gap-2 hover:bg-cb-dark transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Nouveau tarif bagage
      </a>
    </div>
  </div>

  <!-- Filtres ──────────────────────────────────────────────────────────── -->
  <form method="get" action="<?= e(url('referentiel/baggage-tariffs')) ?>"
        class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4">
    <div class="flex flex-wrap items-end gap-3">
      <div class="flex-1 min-w-40">
        <label class="block text-xs font-medium text-slate-500 mb-1">Ligne</label>
        <select name="line_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none">
          <option value="">Toutes les lignes</option>
          <?php foreach ($lines as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= ($lineFilter ?? 0) == $l['id'] ? 'selected' : '' ?>>
              <?= e($l['code']) ?> — <?= e($l['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="flex-1 min-w-40">
        <label class="block text-xs font-medium text-slate-500 mb-1">Nature de bagage</label>
        <select name="nature_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none">
          <option value="">Toutes les natures</option>
          <?php foreach ($baggageNatures as $slug => $n): ?>
            <option value="<?= (int)$n['id'] ?>" <?= ($natureFilter ?? 0) == $n['id'] ? 'selected' : '' ?>>
              <?= e($n['label']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Statut</label>
        <select name="status" class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none">
          <option value="">Tous</option>
          <option value="active"   <?= ($statusFilter ?? '') === 'active'   ? 'selected' : '' ?>>Actifs</option>
          <option value="inactive" <?= ($statusFilter ?? '') === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
        </select>
      </div>
      <button type="submit" class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-medium hover:bg-cb-dark transition">
        Filtrer
      </button>
      <?php if ($qp): ?>
        <a href="<?= e(url('referentiel/baggage-tariffs')) ?>"
           class="px-4 py-2 rounded-xl border border-slate-200 text-slate-500 text-sm hover:bg-slate-50 transition inline-flex items-center gap-1">
          <i data-lucide="x" class="w-3.5 h-3.5"></i> Réinitialiser
        </a>
      <?php endif ?>
    </div>
  </form>

  <!-- Liste ────────────────────────────────────────────────────────────── -->
  <?php if (empty($tariffs)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-12 text-center text-slate-400">
      <i data-lucide="package" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
      <p class="font-medium">Aucun tarif bagage trouvé</p>
      <a href="<?= e(url('referentiel/baggage-tariffs/create')) ?>"
         class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-medium hover:bg-cb-dark transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Créer le premier
      </a>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100">
            <tr>
              <th class="px-4 py-3 text-left">Ligne</th>
              <th class="px-4 py-3 text-left">Nature de bagage</th>
              <th class="px-4 py-3 text-left">Libellé</th>
              <th class="px-4 py-3 text-left">Formule tarifaire</th>
              <th class="px-4 py-3 text-left">Contraintes</th>
              <th class="px-4 py-3 text-center">Validité</th>
              <th class="px-4 py-3 text-center">Statut</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <?php foreach ($tariffs as $t): ?>
              <?php
                $nature    = $t['nature_slug'] ?? '';
                $natureClr = $t['nature_color'] ?? 'bg-slate-100 text-slate-600';
                $natureIco = $t['nature_icon']  ?? 'package';
                $vStatus   = $t['validity_status'];
              ?>
              <tr class="hover:bg-slate-50/50 transition">
                <!-- Ligne -->
                <td class="px-4 py-3">
                  <div class="font-semibold text-slate-800"><?= e($t['line_code']) ?></div>
                  <div class="text-xs text-slate-400"><?= e($t['line_name']) ?></div>
                </td>
                <!-- Nature -->
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border text-xs font-medium <?= e($natureClr) ?>">
                    <i data-lucide="<?= e($natureIco) ?>" class="w-3.5 h-3.5"></i>
                    <?= e($t['nature_label'] ?? $nature) ?>
                  </span>
                </td>
                <!-- Libellé -->
                <td class="px-4 py-3 text-slate-700 max-w-48">
                  <div class="truncate"><?= e($t['label']) ?></div>
                </td>
                <!-- Formule -->
                <td class="px-4 py-3">
                  <div class="text-sm font-mono text-slate-700"><?= e($t['formula']) ?></div>
                  <?php if ((int)$t['bracket_mode']): ?>
                    <div class="mt-1 space-y-0.5">
                      <?php foreach ($t['brackets'] as $b): ?>
                        <div class="inline-flex items-center gap-1 text-xs text-slate-500">
                          <span class="font-medium"><?= number_format((float)$b['weight_from_kg'], 1) ?></span>
                          <span>–</span>
                          <span class="font-medium"><?= $b['weight_to_kg'] !== null ? number_format((float)$b['weight_to_kg'], 1) . ' kg' : '∞' ?></span>
                          <span class="text-cb-primary font-semibold"><?= e(fcfa((int)$b['price_fcfa'])) ?></span>
                        </div><br>
                      <?php endforeach ?>
                    </div>
                  <?php endif ?>
                </td>
                <!-- Contraintes -->
                <td class="px-4 py-3 text-xs text-slate-500 space-y-0.5">
                  <?php if ($t['max_weight_kg'] !== null): ?>
                    <div class="flex items-center gap-1"><i data-lucide="weight" class="w-3 h-3"></i> max <?= number_format((float)$t['max_weight_kg'], 1) ?> kg</div>
                  <?php endif ?>
                  <?php if ($t['max_length_cm'] || $t['max_width_cm'] || $t['max_height_cm']): ?>
                    <div class="flex items-center gap-1"><i data-lucide="ruler" class="w-3 h-3"></i>
                      <?= implode(' × ', array_filter([
                        $t['max_length_cm'] ? $t['max_length_cm'] . 'cm' : null,
                        $t['max_width_cm']  ? $t['max_width_cm']  . 'cm' : null,
                        $t['max_height_cm'] ? $t['max_height_cm'] . 'cm' : null,
                      ])) ?>
                    </div>
                  <?php endif ?>
                  <?php if ($t['max_girth_cm']): ?>
                    <div>Périmètre max : <?= (int)$t['max_girth_cm'] ?> cm</div>
                  <?php endif ?>
                  <?php if (!$t['max_weight_kg'] && !$t['max_length_cm'] && !$t['max_width_cm'] && !$t['max_height_cm'] && !$t['max_girth_cm']): ?>
                    <span class="text-slate-300">—</span>
                  <?php endif ?>
                </td>
                <!-- Validité -->
                <td class="px-4 py-3 text-center">
                  <?php
                    $vClass = match($vStatus) {
                      'actif'     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                      'expire'    => 'bg-rose-50 text-rose-600 border-rose-200',
                      'futur'     => 'bg-sky-50 text-sky-700 border-sky-200',
                      default     => 'bg-slate-50 text-slate-500 border-slate-200',
                    };
                    $vLabel = match($vStatus) {
                      'actif'     => 'En cours',
                      'expire'    => 'Expiré',
                      'futur'     => 'À venir',
                      default     => 'Permanent',
                    };
                  ?>
                  <span class="inline-flex items-center px-2 py-1 rounded-lg border text-xs font-medium <?= $vClass ?>">
                    <?= $vLabel ?>
                  </span>
                  <?php if ($t['valid_from'] || $t['valid_until']): ?>
                    <div class="text-xs text-slate-400 mt-0.5">
                      <?php if ($t['valid_from']): ?>du <?= e(date('d/m/Y', strtotime($t['valid_from']))) ?><?php endif ?>
                      <?php if ($t['valid_until']): ?><br>au <?= e(date('d/m/Y', strtotime($t['valid_until']))) ?><?php endif ?>
                    </div>
                  <?php endif ?>
                </td>
                <!-- Statut actif -->
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center px-2 py-1 rounded-lg border text-xs font-medium
                    <?= $t['is_active'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' ?>">
                    <?= $t['is_active'] ? 'Actif' : 'Inactif' ?>
                  </span>
                </td>
                <!-- Actions -->
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <a href="<?= e(url('referentiel/baggage-tariffs/' . $t['id'] . '/edit')) ?>"
                       class="p-1.5 rounded-lg text-slate-400 hover:text-cb-primary hover:bg-cb-bg transition"
                       title="Modifier">
                      <i data-lucide="pencil" class="w-4 h-4"></i>
                    </a>
                    <form method="post" action="<?= e(url('referentiel/baggage-tariffs/' . $t['id'] . '/delete')) ?>"
                          onsubmit="return confirm('Supprimer ce tarif bagage ?')" class="inline">
                      <?= csrf_field() ?>
                      <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="Supprimer">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif ?>

</div>

<?php $view->end() ?>
