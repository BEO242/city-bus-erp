<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$firstTicket  = $tickets[0];
$agencyName   = $firstTicket['agency_name'] ?? '—';
$batchShort   = substr($batch_id, 0, 8);
$createdAt    = $firstTicket['pre_printed_at'] ?? $firstTicket['created_at'] ?? null;
$hasPdf       = !empty($firstTicket['pdf_path']);
$ppType       = $firstTicket['preprint_type'] ?? 'billet';
$ppTypeMeta   = [
  'billet'       => ['label' => 'Billet passager', 'cls' => 'bg-blue-50 text-blue-700 border-blue-200',    'icon' => 'ticket'],
  'talon_bagage' => ['label' => 'Talon bagage',    'cls' => 'bg-amber-50 text-amber-700 border-amber-200', 'icon' => 'luggage'],
  'talon_colis'  => ['label' => 'Talon colis',     'cls' => 'bg-purple-50 text-purple-700 border-purple-200','icon' => 'package'],
];
$ppMeta = $ppTypeMeta[$ppType] ?? $ppTypeMeta['billet'];
$isTalon = in_array($ppType, ['talon_bagage', 'talon_colis'], true);

$statusCounts = ['disponible' => 0, 'active' => 0, 'annule' => 0];
foreach ($tickets as $t) { @$statusCounts[$t['status']]++; }
?>
<?php $view->start('content') ?>
<div class="max-w-5xl mx-auto space-y-5">

  <!-- Fil d'Ariane -->
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('billetterie/preprint')) ?>" class="hover:text-cb-primary transition inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Pré-imprimés
    </a>
    <span>/</span>
    <span class="text-slate-800 font-medium font-mono"><?= e($batchShort) ?>…</span>
  </div>

  <!-- En-tête -->
  <div class="bg-gradient-to-br from-cb-primary to-cb-dark text-white rounded-2xl p-6 shadow-soft">
    <div class="flex items-center justify-between gap-4 flex-wrap">
      <div class="flex items-center gap-4">
        <span class="w-14 h-14 bg-white/15 rounded-2xl flex items-center justify-center shrink-0">
          <i data-lucide="tickets" class="w-7 h-7"></i>
        </span>
        <div>
          <p class="text-white/70 text-xs uppercase tracking-wide mb-1">Lot de supports pré-imprimés</p>
          <div class="flex items-center gap-3 mb-1">
            <h1 class="text-xl font-bold font-mono"><?= e($batchShort) ?>…</h1>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border <?= $ppMeta['cls'] ?>">
              <i data-lucide="<?= $ppMeta['icon'] ?>" class="w-3 h-3"></i>
              <?= e($ppMeta['label']) ?>
            </span>
          </div>
          <p class="text-white/70 text-sm mt-1">
            <?= count($tickets) ?> supports
            &middot; <?= e($agencyName) ?>
            <?php if ($createdAt): ?>&middot; <?= e(date('d/m/Y H:i', strtotime($createdAt))) ?><?php endif ?>
          </p>
          <?php if ($trip): ?>
          <div class="mt-3 flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-1.5 bg-white/15 rounded-xl px-3 py-1.5 text-xs">
              <i data-lucide="route" class="w-3 h-3"></i>
              <?= e($trip['line_name']) ?>
            </span>
            <span class="inline-flex items-center gap-1.5 bg-white/15 rounded-xl px-3 py-1.5 text-xs">
              <i data-lucide="calendar" class="w-3 h-3"></i>
              <?= e(date('d/m/Y', strtotime($trip['trip_date']))) ?>
              à <?= e(substr($trip['departure_scheduled'], 0, 5)) ?>
            </span>
            <span class="inline-flex items-center gap-1.5 bg-white/15 rounded-xl px-3 py-1.5 text-xs">
              <i data-lucide="map-pin" class="w-3 h-3"></i>
              <?= e($trip['departure_city']) ?> → <?= e($trip['arrival_city']) ?>
            </span>
            <a href="<?= e(url('voyages/'.$firstTicket['trip_id'])) ?>"
               class="inline-flex items-center gap-1.5 bg-white/15 hover:bg-white/25 rounded-xl px-3 py-1.5 text-xs transition font-mono font-semibold">
              <i data-lucide="external-link" class="w-3 h-3"></i>
              <?= e($trip['trip_code']) ?>
            </a>
          </div>
          <?php endif ?>
        </div>
      </div>
      <?php if ($hasPdf): ?>
      <a href="<?= e(url('billetterie/preprint/batch/'.$batch_id.'/pdf')) ?>"
         class="px-4 py-2.5 rounded-xl bg-white/20 hover:bg-white/30 text-white font-semibold inline-flex items-center gap-2 transition">
        <i data-lucide="download" class="w-4 h-4"></i> Télécharger le PDF
      </a>
      <?php endif ?>
    </div>
  </div>

  <!-- KPI -->
  <div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center shrink-0">
        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500">Disponibles</p>
        <p class="text-2xl font-bold text-emerald-600"><?= $statusCounts['disponible'] ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-10 h-10 bg-cb-bg rounded-xl flex items-center justify-center shrink-0">
        <i data-lucide="ticket" class="w-5 h-5 text-cb-primary"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500">Activés (vendus)</p>
        <p class="text-2xl font-bold text-cb-primary"><?= $statusCounts['active'] ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 flex items-center gap-4">
      <span class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center shrink-0">
        <i data-lucide="x-circle" class="w-5 h-5 text-rose-500"></i>
      </span>
      <div>
        <p class="text-xs text-slate-500">Annulés</p>
        <p class="text-2xl font-bold text-rose-500"><?= $statusCounts['annule'] ?></p>
      </div>
    </div>
  </div>

  <!-- Tableau -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
      <h2 class="font-semibold text-slate-800 flex items-center gap-2">
        <i data-lucide="list" class="w-4 h-4 text-slate-400"></i>
        Supports du lot
      </h2>
      <span class="text-xs text-slate-400"><?= count($tickets) ?> entrée(s)</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
          <tr>
            <th class="px-5 py-3 text-left">#</th>
            <th class="px-5 py-3 text-left">N° Support</th>
            <th class="px-5 py-3 text-center">Code</th>
            <?php if (!$isTalon): ?><th class="px-5 py-3 text-center">Siège</th><?php endif ?>
            <th class="px-5 py-3 text-left">QR Code</th>
            <th class="px-5 py-3 text-center">Statut</th>
            <th class="px-5 py-3 text-left">Activé le</th>
            <th class="px-5 py-3 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
        <?php foreach ($tickets as $i => $t):
          $statusMeta = match($t['status']) {
            'disponible' => ['cls' => 'bg-emerald-50 text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Disponible'],
            'active'     => ['cls' => 'bg-cb-bg text-cb-primary',       'dot' => 'bg-cb-primary',   'label' => 'Activé'],
            'annule'     => ['cls' => 'bg-rose-50 text-rose-600',        'dot' => 'bg-rose-400',     'label' => 'Annulé'],
            default      => ['cls' => 'bg-slate-100 text-slate-500',     'dot' => 'bg-slate-400',     'label' => $t['status']],
          };
        ?>
          <tr class="hover:bg-slate-50/60 transition">
            <td class="px-5 py-3 text-slate-400 text-xs"><?= $i + 1 ?></td>
            <td class="px-5 py-3 font-mono font-semibold text-cb-primary text-xs"><?= e($t['pre_print_number']) ?></td>
            <td class="px-5 py-3 text-center">
              <?php if (!empty($t['short_code'])): ?>
                <span class="font-mono font-bold text-xs bg-slate-100 px-2 py-1 rounded-lg tracking-wider"><?= e($t['short_code']) ?></span>
              <?php else: ?>
                <span class="text-slate-300">—</span>
              <?php endif ?>
            </td>
            <?php if (!$isTalon): ?>
            <td class="px-5 py-3 text-center">
              <?php if (!empty($t['seat_number'])): ?>
                <span class="inline-block bg-slate-100 text-slate-700 font-bold rounded-lg px-2 py-0.5 text-xs font-mono"><?= (int)$t['seat_number'] ?></span>
              <?php else: ?>
                <span class="text-slate-300">—</span>
              <?php endif ?>
            </td>
            <?php endif ?>
            <td class="px-5 py-3 font-mono text-xs text-slate-400"><?= e(substr($t['qr_code'], 0, 18)) ?>…</td>
            <td class="px-5 py-3 text-center">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusMeta['cls'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $statusMeta['dot'] ?>"></span>
                <?= $statusMeta['label'] ?>
              </span>
            </td>
            <td class="px-5 py-3 text-slate-500 text-xs">
              <?= !empty($t['activated_at']) ? e(date('d/m/Y H:i', strtotime($t['activated_at']))) : '<span class="text-slate-300">—</span>' ?>
            </td>
            <td class="px-5 py-3 text-right">
              <?php if ($t['status'] === 'disponible' && can('billetterie.preprint')): ?>
              <button type="button"
                      onclick="document.getElementById('cancel-form-<?= $t['id'] ?>').classList.toggle('hidden')"
                      class="text-xs text-rose-500 hover:text-rose-700 font-medium transition">
                Annuler
              </button>
              <?php endif ?>
            </td>
          </tr>
          <?php if ($t['status'] === 'disponible' && can('billetterie.preprint')): ?>
          <tr id="cancel-form-<?= $t['id'] ?>" class="hidden bg-rose-50/40">
            <td colspan="<?= $isTalon ? 7 : 8 ?>" class="px-5 py-3">
              <form method="post" action="<?= e(url('billetterie/preprint/'.$t['id'].'/cancel')) ?>"
                    onsubmit="return confirm('Confirmer l\'annulation définitive de ce support ? Cette action est irréversible.');"
                    class="flex items-center gap-2">
                <?= csrf_field() ?>
                <input name="reason" required minlength="5"
                       placeholder="Motif d'annulation (5 caractères min)"
                       class="cb-input flex-1 text-sm py-2">
                <button type="submit"
                        x-data="{loading:false}" x-on:click="loading=true"
                        x-bind:disabled="loading"
                        class="px-4 py-2 rounded-xl bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition disabled:opacity-60">
                  <span x-show="!loading">Confirmer l'annulation</span>
                  <span x-show="loading" x-cloak>Traitement…</span>
                </button>
                <button type="button"
                        onclick="document.getElementById('cancel-form-<?= $t['id'] ?>').classList.add('hidden')"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition">
                  Annuler
                </button>
              </form>
            </td>
          </tr>
          <?php endif ?>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $view->end() ?>
