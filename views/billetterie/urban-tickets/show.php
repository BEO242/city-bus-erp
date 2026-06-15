<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$statusMeta = [
    'planifiee' => ['label' => 'Planifiée',  'cls' => 'bg-amber-50 text-amber-700 border-amber-200',      'dot' => 'bg-amber-500'],
    'en_cours'  => ['label' => 'En cours',   'cls' => 'bg-blue-50 text-blue-700 border-blue-200',          'dot' => 'bg-blue-500'],
    'cloturee'  => ['label' => 'Clôturée',   'cls' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
    'annulee'   => ['label' => 'Annulée',    'cls' => 'bg-rose-50 text-rose-600 border-rose-200',          'dot' => 'bg-rose-400'],
];
$sm = $statusMeta[$series['status']] ?? $statusMeta['planifiee'];
$isEditable = in_array($series['status'], ['planifiee', 'en_cours']);
$revenue = (int)$series['revenue_expected'];
$actual  = $series['revenue_actual'] !== null ? (int)$series['revenue_actual'] : null;
$ecart   = $actual !== null ? $actual - $revenue : null;
?>
<?php $view->start('content') ?>
<div class="max-w-5xl mx-auto space-y-5" x-data="{ showCloseModal: false }">

  <!-- En-tête -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <div class="flex items-center gap-3 mb-1">
        <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2 font-mono">
          <i data-lucide="bus" class="w-6 h-6 text-cb-primary"></i>
          <?= e($series['series_code']) ?>
        </h1>
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border <?= $sm['cls'] ?>">
          <span class="w-1.5 h-1.5 rounded-full <?= $sm['dot'] ?>"></span>
          <?= e($sm['label']) ?>
        </span>
      </div>
      <p class="text-slate-500 text-sm">
        Créée par <?= e($series['creator_name'] ?? '—') ?> le <?= date('d/m/Y à H:i', strtotime($series['created_at'])) ?>
      </p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <a href="<?= e(url('billetterie/urban-tickets')) ?>"
         class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-semibold inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Liste
      </a>
      <?php if ($series['pdf_path']): ?>
      <a href="<?= e(url('billetterie/urban-tickets/' . $series['id'] . '/pdf')) ?>"
         class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold inline-flex items-center gap-2 hover:bg-blue-700 transition text-sm shadow-soft">
        <i data-lucide="download" class="w-4 h-4"></i> Télécharger PDF
      </a>
      <?php endif ?>
    </div>
  </div>

  <div class="grid lg:grid-cols-3 gap-5">

    <!-- Colonne gauche : Détails -->
    <div class="lg:col-span-2 space-y-4">

      <!-- Infos série -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2 text-sm mb-4">
          <i data-lucide="clipboard-list" class="w-4 h-4 text-cb-primary"></i> Détails de la série
        </h2>
        <div class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-slate-500">Date d'utilisation</span>
            <span class="font-semibold text-slate-800"><?= date('d/m/Y', strtotime($series['ticket_date'])) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Bus affecté</span>
            <span class="font-semibold font-mono text-slate-800"><?= e($series['bus_code']) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Route</span>
            <span class="font-semibold text-slate-800"><?= e($series['departure']) ?> &rarr; <?= e($series['arrival']) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Réseau</span>
            <span class="text-slate-700"><?= e($series['network_label']) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Prix unitaire</span>
            <span class="font-bold font-mono text-slate-800"><?= number_format((int)$series['price_fcfa'], 0, ',', ' ') ?> FCFA</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Symbole secret</span>
            <span class="text-2xl leading-none" title="Symbole anti-fraude"><?= e($series['symbol_char']) ?></span>
          </div>
        </div>
      </div>

      <!-- Numérotation -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2 text-sm mb-4">
          <i data-lucide="hash" class="w-4 h-4 text-cb-primary"></i> Numérotation
        </h2>
        <div class="grid sm:grid-cols-3 gap-4">
          <div class="text-center p-3 bg-slate-50 rounded-xl">
            <div class="text-[10px] text-slate-500 uppercase font-bold tracking-wider mb-1">Plage</div>
            <div class="font-mono font-bold text-slate-800">
              <?= str_pad((string)$series['num_start'], 4, '0', STR_PAD_LEFT) ?> – <?= str_pad((string)$series['num_end'], 4, '0', STR_PAD_LEFT) ?>
            </div>
          </div>
          <div class="text-center p-3 bg-blue-50 rounded-xl">
            <div class="text-[10px] text-blue-600 uppercase font-bold tracking-wider mb-1">Tickets</div>
            <div class="text-xl font-black text-blue-700"><?= number_format((int)$series['ticket_count'], 0, ',', ' ') ?></div>
          </div>
          <div class="text-center p-3 bg-slate-50 rounded-xl">
            <div class="text-[10px] text-slate-500 uppercase font-bold tracking-wider mb-1">Pages A4</div>
            <div class="text-xl font-black text-slate-700"><?= (int)$series['page_count'] ?></div>
          </div>
        </div>
      </div>

      <!-- Finances -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2 text-sm mb-4">
          <i data-lucide="coins" class="w-4 h-4 text-cb-primary"></i> Rapprochement financier
        </h2>
        <div class="grid sm:grid-cols-3 gap-4">
          <div class="text-center p-3 bg-emerald-50 rounded-xl">
            <div class="text-[10px] text-emerald-600 uppercase font-bold tracking-wider mb-1">Recette attendue</div>
            <div class="text-lg font-black text-emerald-700 font-mono"><?= number_format($revenue, 0, ',', ' ') ?> <span class="text-xs font-normal">F</span></div>
          </div>
          <div class="text-center p-3 <?= $actual !== null ? 'bg-blue-50' : 'bg-slate-50' ?> rounded-xl">
            <div class="text-[10px] <?= $actual !== null ? 'text-blue-600' : 'text-slate-400' ?> uppercase font-bold tracking-wider mb-1">Recette réelle</div>
            <div class="text-lg font-black <?= $actual !== null ? 'text-blue-700' : 'text-slate-300' ?> font-mono">
              <?= $actual !== null ? number_format($actual, 0, ',', ' ') . ' <span class="text-xs font-normal">F</span>' : '—' ?>
            </div>
          </div>
          <div class="text-center p-3 <?= $ecart !== null ? ($ecart >= 0 ? 'bg-emerald-50' : 'bg-rose-50') : 'bg-slate-50' ?> rounded-xl">
            <div class="text-[10px] <?= $ecart !== null ? ($ecart >= 0 ? 'text-emerald-600' : 'text-rose-600') : 'text-slate-400' ?> uppercase font-bold tracking-wider mb-1">Écart</div>
            <div class="text-lg font-black <?= $ecart !== null ? ($ecart >= 0 ? 'text-emerald-700' : 'text-rose-700') : 'text-slate-300' ?> font-mono">
              <?php if ($ecart !== null): ?>
                <?= ($ecart >= 0 ? '+' : '') . number_format($ecart, 0, ',', ' ') ?> <span class="text-xs font-normal">F</span>
              <?php else: ?>—<?php endif ?>
            </div>
          </div>
        </div>
        <?php if ((int)$series['tickets_sold'] > 0): ?>
        <div class="mt-3 text-sm text-slate-600 text-center">
          <span class="font-semibold"><?= number_format((int)$series['tickets_sold'], 0, ',', ' ') ?></span> tickets vendus
          sur <?= number_format((int)$series['ticket_count'], 0, ',', ' ') ?>
          (<?= round((int)$series['tickets_sold'] / (int)$series['ticket_count'] * 100) ?>%)
        </div>
        <?php endif ?>
      </div>

      <!-- Actions -->
      <?php if ($isEditable && can('billetterie.preprint')): ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2 text-sm">
          <i data-lucide="settings" class="w-4 h-4 text-cb-primary"></i> Actions
        </h2>
        <div class="flex flex-wrap gap-2">
          <?php if ($series['status'] === 'planifiee'): ?>
          <form method="post" action="<?= e(url('billetterie/urban-tickets/' . $series['id'] . '/start')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 transition inline-flex items-center gap-2">
              <i data-lucide="play" class="w-4 h-4"></i> Démarrer la série
            </button>
          </form>
          <?php endif ?>

          <button @click="showCloseModal = true"
                  class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition inline-flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i> Clôturer
          </button>

          <form method="post" action="<?= e(url('billetterie/urban-tickets/' . $series['id'] . '/cancel')) ?>"
                onsubmit="return confirm('Annuler cette série ? Cette action est irréversible.')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="px-4 py-2 rounded-xl border border-rose-200 text-rose-600 text-sm font-semibold hover:bg-rose-50 transition inline-flex items-center gap-2">
              <i data-lucide="x-circle" class="w-4 h-4"></i> Annuler la série
            </button>
          </form>
        </div>
      </div>
      <?php endif ?>
    </div>

    <!-- Colonne droite : Preview ticket -->
    <div>
      <div class="sticky top-24 space-y-4">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2 text-sm">
          <i data-lucide="eye" class="w-4 h-4 text-cb-primary"></i> Aperçu du ticket
        </h2>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4">
          <div class="border-2 border-dashed border-slate-300 rounded-xl overflow-hidden" style="max-width:220px; margin:0 auto;">
            <div class="bg-slate-900 text-white px-3 py-2 flex items-center justify-between">
              <span class="font-bold text-xs tracking-wide">CITY BUS</span>
              <span class="font-bold text-xs"><?= number_format((int)$series['price_fcfa'], 0, ',', ' ') ?> FCFA</span>
            </div>
            <div class="text-center text-[10px] text-slate-500 py-1 border-b border-slate-200"><?= e($series['network_label']) ?></div>
            <div class="text-center font-mono font-bold text-sm py-1.5 tracking-wide text-slate-800">
              CB-<?= e($series['date_code']) ?>-<?= str_pad((string)$series['num_start'], 4, '0', STR_PAD_LEFT) ?>
            </div>
            <div class="border border-slate-800 rounded-lg mx-3 px-2 py-1.5 text-center text-xs font-bold text-slate-900">
              <?= e($series['departure']) ?> <span class="text-slate-400">&rarr;</span> <?= e($series['arrival']) ?>
            </div>
            <div class="flex items-center justify-between px-3 py-2">
              <div class="text-[10px] text-slate-600 leading-relaxed">
                <div>Date : <?= date('d/m/Y', strtotime($series['ticket_date'])) ?></div>
                <div>Bus : <span class="font-semibold"><?= e($series['bus_code']) ?></span></div>
              </div>
              <div class="w-10 h-10 border-2 border-slate-800 rounded-lg flex items-center justify-center text-2xl leading-none">
                <?= e($series['symbol_char']) ?>
              </div>
            </div>
            <div class="bg-slate-100 text-center text-[9px] text-slate-500 py-1">1 voyage &middot; non remboursable</div>
          </div>
        </div>

        <!-- Chronologie -->
        <?php if ($series['status'] === 'cloturee' && $series['closed_at']): ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4 text-xs space-y-2">
          <div class="font-semibold text-slate-700 flex items-center gap-1.5">
            <i data-lucide="clock" class="w-3.5 h-3.5 text-cb-primary"></i> Chronologie
          </div>
          <div class="space-y-1.5 text-slate-600">
            <div class="flex justify-between">
              <span>Créée</span>
              <span class="font-mono"><?= date('d/m/y H:i', strtotime($series['created_at'])) ?></span>
            </div>
            <div class="flex justify-between">
              <span>Clôturée</span>
              <span class="font-mono"><?= date('d/m/y H:i', strtotime($series['closed_at'])) ?></span>
            </div>
            <?php if (!empty($series['closer_name'])): ?>
            <div class="flex justify-between">
              <span>Par</span>
              <span class="font-semibold"><?= e($series['closer_name']) ?></span>
            </div>
            <?php endif ?>
          </div>
        </div>
        <?php endif ?>
      </div>
    </div>
  </div>

  <!-- Modal de clôture -->
  <div x-show="showCloseModal" x-transition x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div @click.away="showCloseModal = false"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 space-y-4">
      <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i> Clôturer la série
      </h3>
      <p class="text-sm text-slate-600">
        Saisissez le nombre de tickets vendus et la recette encaissée pour rapprocher les comptes.
      </p>
      <form method="post" action="<?= e(url('billetterie/urban-tickets/' . $series['id'] . '/close')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="space-y-3">
          <div>
            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Tickets vendus *</label>
            <input type="number" name="tickets_sold" min="0" max="<?= (int)$series['ticket_count'] ?>" required
                   placeholder="0" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
          </div>
          <div>
            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Recette encaissée (FCFA)</label>
            <input type="number" name="revenue_actual" min="0" step="1"
                   placeholder="<?= (int)$series['revenue_expected'] ?>"
                   class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
          </div>
        </div>
        <div class="flex items-center justify-end gap-2 mt-4">
          <button type="button" @click="showCloseModal = false"
                  class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
            Annuler
          </button>
          <button type="submit"
                  class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition inline-flex items-center gap-2">
            <i data-lucide="check" class="w-4 h-4"></i> Clôturer
          </button>
        </div>
      </form>
    </div>
  </div>

</div>
<?php $view->end() ?>
