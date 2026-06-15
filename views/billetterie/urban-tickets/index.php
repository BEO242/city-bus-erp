<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$statusMeta = [
    'planifiee' => ['label' => 'Planifiée',  'cls' => 'bg-amber-50 text-amber-700',    'dot' => 'bg-amber-500'],
    'en_cours'  => ['label' => 'En cours',   'cls' => 'bg-blue-50 text-blue-700',       'dot' => 'bg-blue-500'],
    'cloturee'  => ['label' => 'Clôturée',   'cls' => 'bg-emerald-50 text-emerald-700', 'dot' => 'bg-emerald-500'],
    'annulee'   => ['label' => 'Annulée',    'cls' => 'bg-rose-50 text-rose-600',       'dot' => 'bg-rose-400'],
];
?>
<?php $view->start('content') ?>
<div class="max-w-6xl mx-auto space-y-5">

  <!-- En-tête -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="bus" class="w-6 h-6 text-cb-primary"></i> Tickets urbains pré-imprimés
      </h1>
      <p class="text-slate-500 text-sm mt-0.5">Carnets de tickets physiques pour le réseau urbain — impression A4, système anti-fraude par symbole secret.</p>
    </div>
    <?php if (can('billetterie.preprint')): ?>
    <a href="<?= e(url('billetterie/urban-tickets/create')) ?>"
       class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-semibold inline-flex items-center gap-2 hover:bg-cb-dark transition shadow-soft text-sm">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle série
    </a>
    <?php endif ?>
  </div>

  <!-- KPI -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-soft">
      <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1 flex items-center gap-1">
        <i data-lucide="layers" class="w-3 h-3"></i> Total séries
      </div>
      <div class="text-xl font-black text-slate-800 tabular-nums"><?= (int)($stats['total_series'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-soft">
      <div class="text-[10px] text-blue-600 font-bold uppercase tracking-wider mb-1 flex items-center gap-1">
        <i data-lucide="ticket" class="w-3 h-3"></i> Tickets générés
      </div>
      <div class="text-xl font-black text-blue-700 tabular-nums"><?= number_format((int)($stats['total_tickets'] ?? 0), 0, ',', ' ') ?></div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-soft">
      <div class="text-[10px] text-emerald-600 font-bold uppercase tracking-wider mb-1 flex items-center gap-1">
        <i data-lucide="coins" class="w-3 h-3"></i> Recette attendue
      </div>
      <div class="text-xl font-black text-emerald-700 tabular-nums"><?= number_format((int)($stats['total_expected'] ?? 0), 0, ',', ' ') ?> <span class="text-xs font-normal text-emerald-400">F</span></div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-soft">
      <div class="text-[10px] text-cb-primary font-bold uppercase tracking-wider mb-1 flex items-center gap-1">
        <i data-lucide="banknote" class="w-3 h-3"></i> Recette réelle
      </div>
      <div class="text-xl font-black text-cb-primary tabular-nums"><?= number_format((int)($stats['total_actual'] ?? 0), 0, ',', ' ') ?> <span class="text-xs font-normal text-rose-300">F</span></div>
    </div>
  </div>

  <!-- Filtres -->
  <form method="get" class="bg-white rounded-xl border border-slate-100 p-4 shadow-soft">
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 items-end">
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Statut</label>
        <select name="status" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
          <option value="">Tous</option>
          <?php foreach ($statusMeta as $key => $meta): ?>
          <option value="<?= e($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Du</label>
        <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Au</label>
        <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Bus</label>
        <input type="text" name="bus_code" value="<?= e($filters['bus_code'] ?? '') ?>" placeholder="CB-007"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
      </div>
      <div>
        <button type="submit" class="w-full px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-700 transition inline-flex items-center justify-center gap-1.5">
          <i data-lucide="search" class="w-3.5 h-3.5"></i> Filtrer
        </button>
      </div>
    </div>
  </form>

  <!-- Tableau des séries -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <?php if (empty($series)): ?>
    <div class="text-center py-12">
      <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
        <i data-lucide="bus" class="w-7 h-7 text-slate-300"></i>
      </div>
      <p class="text-slate-500 font-medium">Aucune série trouvée</p>
      <p class="text-slate-400 text-sm mt-1">Créez votre première série de tickets urbains.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-[10px] text-slate-500 uppercase tracking-wider bg-slate-50">
          <tr>
            <th class="text-left px-4 py-3">Série</th>
            <th class="text-left px-3 py-3">Date</th>
            <th class="text-center px-3 py-3">Symbole</th>
            <th class="text-left px-3 py-3">Route</th>
            <th class="text-left px-3 py-3">Bus</th>
            <th class="text-center px-3 py-3">Plage N°</th>
            <th class="text-right px-3 py-3">Tickets</th>
            <th class="text-right px-3 py-3">Vendus</th>
            <th class="text-center px-3 py-3">Statut</th>
            <th class="text-right px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($series as $s):
            $sm = $statusMeta[$s['status']] ?? $statusMeta['planifiee'];
          ?>
          <tr class="hover:bg-slate-50/60 transition">
            <td class="px-4 py-3">
              <a href="<?= e(url('billetterie/urban-tickets/' . $s['id'])) ?>" class="font-semibold text-cb-primary hover:underline font-mono text-xs">
                <?= e($s['series_code']) ?>
              </a>
              <div class="text-[10px] text-slate-400 mt-0.5">par <?= e($s['creator_name'] ?? '—') ?></div>
            </td>
            <td class="px-3 py-3 text-slate-700 whitespace-nowrap"><?= date('d/m/Y', strtotime($s['ticket_date'])) ?></td>
            <td class="px-3 py-3 text-center">
              <span class="text-xl" title="Symbole secret"><?= e($s['symbol_char']) ?></span>
            </td>
            <td class="px-3 py-3 text-slate-700 text-xs">
              <span class="font-semibold"><?= e($s['departure']) ?></span>
              <span class="text-slate-400 mx-1">&rarr;</span>
              <span class="font-semibold"><?= e($s['arrival']) ?></span>
            </td>
            <td class="px-3 py-3 text-slate-600 font-mono text-xs"><?= e($s['bus_code']) ?></td>
            <td class="px-3 py-3 text-center font-mono text-xs text-slate-500">
              <?= str_pad((string)$s['num_start'], 4, '0', STR_PAD_LEFT) ?>–<?= str_pad((string)$s['num_end'], 4, '0', STR_PAD_LEFT) ?>
            </td>
            <td class="px-3 py-3 text-right font-mono font-semibold text-slate-700"><?= number_format((int)$s['ticket_count'], 0, ',', ' ') ?></td>
            <td class="px-3 py-3 text-right font-mono <?= (int)$s['tickets_sold'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-400' ?>">
              <?= (int)$s['tickets_sold'] > 0 ? number_format((int)$s['tickets_sold'], 0, ',', ' ') : '—' ?>
            </td>
            <td class="px-3 py-3 text-center">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold <?= $sm['cls'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $sm['dot'] ?>"></span>
                <?= e($sm['label']) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <a href="<?= e(url('billetterie/urban-tickets/' . $s['id'])) ?>"
                   class="p-1.5 rounded-lg hover:bg-slate-100 transition text-slate-400 hover:text-slate-700" title="Détails">
                  <i data-lucide="eye" class="w-4 h-4"></i>
                </a>
                <?php if ($s['pdf_path']): ?>
                <a href="<?= e(url('billetterie/urban-tickets/' . $s['id'] . '/pdf')) ?>"
                   class="p-1.5 rounded-lg hover:bg-blue-50 transition text-blue-400 hover:text-blue-700" title="Télécharger PDF">
                  <i data-lucide="download" class="w-4 h-4"></i>
                </a>
                <?php endif ?>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
