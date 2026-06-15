<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$qp = array_filter([
    'q'       => $q       ?? '',
    'line_id' => ($lineId ?? 0) > 0 ? (string)$lineId : '',
    'status'  => $status  ?? '',
]);
?>
<?php $view->start('content') ?>

<div class="space-y-4">

  <!-- En-tête ──────────────────────────────────────────────────────────── -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="package" class="w-6 h-6 text-amber-500"></i> Billets bagages
      </h1>
      <p class="text-slate-500 text-sm"><?= $total ?> billet(s) au total</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <a href="<?= e(url('billetterie')) ?>"
         class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition">
        <i data-lucide="ticket" class="w-4 h-4"></i> Billets passagers
      </a>
      <a href="<?= e(url('billetterie-bagages/select-trip')) ?>"
         class="px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-semibold inline-flex items-center gap-2 hover:bg-amber-600 transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Nouveau billet bagage
      </a>
    </div>
  </div>

  <!-- Filtres ──────────────────────────────────────────────────────────── -->
  <form method="get" action="<?= e(url('billetterie-bagages')) ?>"
        class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4">
    <div class="flex flex-wrap items-end gap-3">
      <div class="flex-1 min-w-48">
        <label class="block text-xs font-medium text-slate-500 mb-1">Recherche</label>
        <div class="relative">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input type="text" name="q" value="<?= e($q) ?>"
                 placeholder="N° billet, passager, téléphone…"
                 class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-amber-400 outline-none">
        </div>
      </div>
      <div class="flex-1 min-w-40">
        <label class="block text-xs font-medium text-slate-500 mb-1">Ligne</label>
        <select name="line_id"
                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-amber-400 outline-none">
          <option value="">Toutes les lignes</option>
          <?php foreach ($lines as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= ($lineId ?? 0) == $l['id'] ? 'selected' : '' ?>>
              <?= e($l['code']) ?> — <?= e($l['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Statut</label>
        <select name="status"
                class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-amber-400 outline-none">
          <option value="">Tous</option>
          <option value="emis"   <?= ($status ?? '') === 'emis'   ? 'selected' : '' ?>>Émis</option>
          <option value="annule" <?= ($status ?? '') === 'annule' ? 'selected' : '' ?>>Annulés</option>
        </select>
      </div>
      <button type="submit"
              class="px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-medium hover:bg-amber-600 transition">
        Filtrer
      </button>
      <?php if ($qp): ?>
        <a href="<?= e(url('billetterie-bagages')) ?>"
           class="px-4 py-2 rounded-xl border border-slate-200 text-slate-500 text-sm hover:bg-slate-50 transition inline-flex items-center gap-1">
          <i data-lucide="x" class="w-3.5 h-3.5"></i> Réinitialiser
        </a>
      <?php endif ?>
    </div>
  </form>

  <!-- Tableau ──────────────────────────────────────────────────────────── -->
  <?php if (empty($tickets)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-12 text-center text-slate-400">
      <i data-lucide="package-x" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
      <p class="font-medium">Aucun billet bagage trouvé</p>
      <a href="<?= e(url('billetterie-bagages/select-trip')) ?>"
         class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500 text-white text-sm font-medium hover:bg-amber-600 transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Émettre le premier
      </a>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100">
            <tr>
              <th class="px-4 py-3 text-left">N° Billet</th>
              <th class="px-4 py-3 text-left">Voyage</th>
              <th class="px-4 py-3 text-left">Passager</th>
              <th class="px-4 py-3 text-left">Nature</th>
              <th class="px-4 py-3 text-right">Poids</th>
              <th class="px-4 py-3 text-right">Total</th>
              <th class="px-4 py-3 text-center">Statut</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <?php foreach ($tickets as $t): ?>
              <tr class="hover:bg-slate-50/50 transition">
                <!-- N° billet -->
                <td class="px-4 py-3">
                  <a href="<?= e(url('billetterie-bagages/' . $t['id'])) ?>"
                     class="font-mono text-sm font-bold text-amber-700 hover:underline">
                    <?= e($t['ticket_number']) ?>
                  </a>
                  <div class="text-xs text-slate-400"><?= e(date('d/m/Y H:i', strtotime($t['sold_at']))) ?></div>
                </td>
                <!-- Voyage -->
                <td class="px-4 py-3">
                  <div class="font-semibold text-slate-800"><?= e($t['line_code']) ?></div>
                  <div class="text-xs text-slate-400"><?= e(date('d/m/Y', strtotime($t['trip_date']))) ?></div>
                </td>
                <!-- Passager -->
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-700"><?= e($t['passenger_name']) ?></div>
                  <?php if ($t['passenger_phone']): ?>
                    <div class="text-xs text-slate-400"><?= e($t['passenger_phone']) ?></div>
                  <?php endif ?>
                </td>
                <!-- Nature -->
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border text-xs font-medium <?= e($t['nature_color']) ?>">
                    <i data-lucide="<?= e($t['nature_icon']) ?>" class="w-3.5 h-3.5"></i>
                    <?= e($t['nature_label']) ?>
                  </span>
                </td>
                <!-- Poids -->
                <td class="px-4 py-3 text-right font-mono">
                  <?= number_format((float)$t['weight_kg'], 2) ?> kg
                </td>
                <!-- Total -->
                <td class="px-4 py-3 text-right font-bold text-amber-700">
                  <?= fcfa((int)$t['total_price_fcfa']) ?>
                </td>
                <!-- Statut -->
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center px-2 py-1 rounded-lg border text-xs font-medium
                    <?= $t['status'] === 'emis' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-600 border-rose-200' ?>">
                    <?= $t['status'] === 'emis' ? 'Émis' : 'Annulé' ?>
                  </span>
                </td>
                <!-- Actions -->
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <a href="<?= e(url('billetterie-bagages/' . $t['id'])) ?>"
                       class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition"
                       title="Voir">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </a>
                    <a href="<?= e(url('billetterie-bagages/' . $t['id'] . '/pdf')) ?>" target="_blank"
                       class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                       title="PDF">
                      <i data-lucide="file-text" class="w-4 h-4"></i>
                    </a>
                    <a href="<?= e(url('billetterie-bagages/' . $t['id'] . '/print')) ?>"
                       class="p-1.5 rounded-lg text-slate-400 hover:text-cb-primary hover:bg-cb-bg transition"
                       title="Imprimer">
                      <i data-lucide="printer" class="w-4 h-4"></i>
                    </a>
                    <?php if ($t['status'] === 'emis'): ?>
                      <form method="post" action="<?= e(url('billetterie-bagages/' . $t['id'] . '/cancel')) ?>"
                            onsubmit="return confirm('Annuler ce billet bagage ?')" class="inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="Annuler">
                          <i data-lucide="x-circle" class="w-4 h-4"></i>
                        </button>
                      </form>
                    <?php endif ?>
                  </div>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if (($lastPage ?? 1) > 1): ?>
        <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-500">
          <span>Page <?= $page ?> / <?= $lastPage ?> — <?= $total ?> billet(s)</span>
          <div class="flex gap-1">
            <?php for ($p = 1; $p <= $lastPage; $p++): ?>
              <a href="<?= e(url('billetterie-bagages') . '?' . http_build_query(array_merge($qp, ['page' => $p]))) ?>"
                 class="px-3 py-1.5 rounded-lg border text-xs <?= $p === $page ? 'bg-amber-500 text-white border-amber-500' : 'border-slate-200 hover:bg-slate-50' ?>">
                <?= $p ?>
              </a>
            <?php endfor ?>
          </div>
        </div>
      <?php endif ?>
    </div>
  <?php endif ?>

</div>
<?php $view->end() ?>
