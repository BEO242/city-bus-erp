<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold">Maintenance</h1>
      <p class="text-slate-500 text-sm">Suivi des maintenances réelles, non prévues et préventives.</p>
    </div>
    <a href="<?= e(url('flotte/maintenance/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium inline-flex items-center gap-2">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle maintenance
    </a>
  </div>

  <div class="grid md:grid-cols-4 gap-3">
    <div class="bg-white rounded-2xl border border-slate-100 p-4">
      <p class="text-xs text-slate-500 uppercase">Total</p>
      <p class="text-2xl font-bold text-slate-900"><?= e((string)($stats['total'] ?? 0)) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-4">
      <p class="text-xs text-slate-500 uppercase">Réalisées</p>
      <p class="text-2xl font-bold text-emerald-700"><?= e((string)($stats['done'] ?? 0)) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-4">
      <p class="text-xs text-slate-500 uppercase">Non prévues</p>
      <p class="text-2xl font-bold text-rose-700"><?= e((string)($stats['unplanned'] ?? 0)) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-4">
      <p class="text-xs text-slate-500 uppercase">Préventives</p>
      <p class="text-2xl font-bold text-cb-primary"><?= e((string)($stats['planned'] ?? 0)) ?></p>
    </div>
  </div>

  <form method="get" action="<?= e(url('flotte/maintenance')) ?>" class="bg-white rounded-2xl border border-slate-100 p-4 grid md:grid-cols-6 gap-3">
    <div class="md:col-span-2">
      <label class="block text-xs text-slate-500 mb-1 uppercase">Véhicule</label>
      <select name="bus_id" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm">
        <option value="0">Tous les véhicules</option>
        <?php foreach (($buses ?? []) as $b): ?>
          <option value="<?= e((string)$b['id']) ?>" <?= ((int)($busFilter ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
            <?= e($b['code']) ?> · <?= e($b['plate']) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1 uppercase">Statut</label>
      <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm">
        <option value="" <?= empty($statusFilter) ? 'selected' : '' ?>>Tous</option>
        <option value="planifie" <?= ($statusFilter ?? '') === 'planifie' ? 'selected' : '' ?>>Planifié</option>
        <option value="en_cours" <?= ($statusFilter ?? '') === 'en_cours' ? 'selected' : '' ?>>En cours</option>
        <option value="termine" <?= ($statusFilter ?? '') === 'termine' ? 'selected' : '' ?>>Terminé</option>
        <option value="annule" <?= ($statusFilter ?? '') === 'annule' ? 'selected' : '' ?>>Annulé</option>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1 uppercase">Type</label>
      <select name="type" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm">
        <option value="" <?= empty($typeFilter) ? 'selected' : '' ?>>Tous</option>
        <option value="preventive" <?= ($typeFilter ?? '') === 'preventive' ? 'selected' : '' ?>>Préventive</option>
        <option value="corrective" <?= ($typeFilter ?? '') === 'corrective' ? 'selected' : '' ?>>Corrective</option>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1 uppercase">Du</label>
      <input type="date" name="date_from" value="<?= e((string)($dateFrom ?? '')) ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm">
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1 uppercase">Au</label>
      <input type="date" name="date_to" value="<?= e((string)($dateTo ?? '')) ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm">
    </div>
    <div class="md:col-span-6 flex items-center justify-end gap-2 pt-1">
      <a href="<?= e(url('flotte/maintenance')) ?>" class="px-4 py-2 rounded-xl border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Réinitialiser</a>
      <button type="submit" class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-medium hover:bg-cb-dark">Appliquer les filtres</button>
    </div>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr><th class="px-5 py-3 text-left">N°</th><th class="px-5 py-3 text-left">Véhicule</th><th class="px-5 py-3 text-left">Type</th><th class="px-5 py-3 text-left">Description</th><th class="px-5 py-3 text-left">Date</th><th class="px-5 py-3 text-right">Coût</th><th class="px-5 py-3 text-center">Statut</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($orders as $o):
        $cls = match($o['status']) { 'planifie'=>'bg-slate-100','en_cours'=>'bg-amber-50 text-amber-700','termine'=>'bg-emerald-50 text-emerald-700','annule'=>'bg-rose-50 text-rose-600',default=>'' };
        $statusLabel = match($o['status']) { 'planifie' => 'Planifié', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'annule' => 'Annulé', default => (string)$o['status'] };
        $typeCls = ($o['type'] ?? '') === 'corrective' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-blue-50 text-blue-700 border-blue-200';
        $typeLabel = ($o['type'] ?? '') === 'corrective' ? 'Corrective (non prévue)' : 'Préventive';
        $displayDate = !empty($o['done_at']) ? date('d/m/Y', strtotime((string)$o['done_at'])) : (!empty($o['scheduled_at']) ? date('d/m/Y', strtotime((string)$o['scheduled_at'])) : '—');
        $cost = (int)($o['actual_cost'] ?? 0) > 0 ? (int)$o['actual_cost'] : (int)($o['estimated_cost'] ?? 0);
      ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-3 font-mono text-cb-primary"><?= e($o['order_number']) ?></td>
          <td class="px-5 py-3"><?= e($o['bus_code']) ?> · <?= e($o['plate']) ?></td>
          <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full border text-xs <?= $typeCls ?>"><?= e($typeLabel) ?></span></td>
          <td class="px-5 py-3 text-slate-600 max-w-md truncate"><?= e($o['description']) ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e($displayDate) ?></td>
          <td class="px-5 py-3 text-right font-bold"><?= e(fcfa($cost)) ?></td>
          <td class="px-5 py-3 text-center"><span class="px-2 py-0.5 rounded-full text-xs <?= $cls ?>"><?= e($statusLabel) ?></span></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$orders): ?><tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Aucun ordre</td></tr><?php endif ?>
      </tbody>
    </table>
    <!-- Règle de fin de liste -->
    <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-3 text-xs text-slate-400">
      <div class="flex-1 h-px bg-slate-100"></div>
      <span><?= count($orders ?? []) ?> enregistrement(s) affiché(s)<?= (($dateFrom ?? '') === '' && ($dateTo ?? '') === '') ? ' · 30 derniers' : '' ?></span>
      <div class="flex-1 h-px bg-slate-100"></div>
    </div>
  </div>
</div>
<?php $view->end() ?>
