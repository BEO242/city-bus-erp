<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$statuses = \CityBus\Models\MaintenanceOrder::STATUSES ?? [];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('flotte/maintenance')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <div class="flex items-start justify-between mt-2">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Ordre #<?= (int)$order['id'] ?></h1>
        <p class="text-slate-500 text-sm">Véhicule <span class="font-semibold"><?= e($order['bus_code']) ?></span> · <?= e($order['plate']) ?> · <?= e(($order['brand'] ?? '') . ' ' . ($order['model'] ?? '')) ?></p>
      </div>
      <div class="flex items-center gap-2">
        <span class="px-3 py-1 rounded-full text-xs font-medium <?= $order['type']==='preventive'?'bg-emerald-50 text-emerald-700':'bg-amber-50 text-amber-700' ?>">
          <?= $order['type']==='preventive'?'Préventive':'Corrective' ?>
        </span>
        <span class="px-3 py-1 rounded-full text-xs font-medium bg-cb-bg text-cb-primary">
          <?= e($statuses[$order['status']] ?? $order['status']) ?>
        </span>
        <?php if (can('flotte.maintenance.edit')): ?>
          <a href="<?= e(url('flotte/maintenance/' . $order['id'] . '/edit')) ?>" class="px-3 py-1.5 rounded-lg bg-cb-primary text-white text-sm hover:bg-cb-secondary inline-flex items-center gap-1">
            <i data-lucide="pencil" class="w-3 h-3"></i> Modifier
          </a>
        <?php endif ?>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    <div class="md:col-span-2 bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-4">
      <h2 class="font-semibold text-slate-900">Description</h2>
      <p class="text-slate-700 whitespace-pre-line"><?= e($order['description']) ?></p>

      <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100">
        <div>
          <p class="text-xs uppercase text-slate-400">Coût estimé</p>
          <p class="font-semibold"><?= e(fcfa((int)($order['estimated_cost'] ?? 0))) ?></p>
        </div>
        <div>
          <p class="text-xs uppercase text-slate-400">Coût réel</p>
          <p class="font-semibold"><?= $order['actual_cost'] !== null ? e(fcfa((int)$order['actual_cost'])) : '—' ?></p>
        </div>
        <div>
          <p class="text-xs uppercase text-slate-400">Date prévue</p>
          <p><?= !empty($order['scheduled_at']) ? e(date('d/m/Y H:i', strtotime((string)$order['scheduled_at']))) : '—' ?></p>
        </div>
        <div>
          <p class="text-xs uppercase text-slate-400">Date d'exécution</p>
          <p><?= !empty($order['done_at']) ? e(date('d/m/Y H:i', strtotime((string)$order['done_at']))) : '—' ?></p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-3">
      <h2 class="font-semibold text-slate-900">Intervenants</h2>
      <div>
        <p class="text-xs uppercase text-slate-400">Mécanicien</p>
        <p><?= !empty($order['meca_first']) ? e($order['meca_first'] . ' ' . $order['meca_last']) . ' (' . e($order['meca_matricule']) . ')' : '—' ?></p>
      </div>
      <div>
        <p class="text-xs uppercase text-slate-400">Créé par</p>
        <p><?= e(trim((string)$order['created_by_name'])) ?: '—' ?></p>
      </div>
      <div>
        <p class="text-xs uppercase text-slate-400">Créé le</p>
        <p><?= e(date('d/m/Y H:i', strtotime((string)$order['created_at']))) ?></p>
      </div>
    </div>
  </div>

  <?php if (can('flotte.maintenance.edit')): ?>
  <?php
  $currentMaintStatus = $order['status'] ?? 'planifie';
  // Transitions autorisées par statut courant
  $maintTransitions = match ($currentMaintStatus) {
      'planifie' => ['en_cours', 'annule'],
      'en_cours' => ['termine', 'annule'],
      default    => [],
  };
  $maintMeta = [
      'en_cours' => ['icon' => 'wrench',       'cls' => 'bg-blue-600 hover:bg-blue-700 text-white',   'label' => 'Démarrer les travaux'],
      'termine'  => ['icon' => 'check-circle',  'cls' => 'bg-emerald-600 hover:bg-emerald-700 text-white', 'label' => 'Marquer terminé'],
      'annule'   => ['icon' => 'x-circle',      'cls' => 'border border-rose-300 text-rose-600 hover:bg-rose-50', 'label' => 'Annuler'],
  ];
  ?>
  <?php if (!empty($maintTransitions)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
    <h2 class="font-semibold text-slate-900 mb-3">Actions</h2>
    <div class="flex flex-wrap items-end gap-3">
      <?php foreach ($maintTransitions as $nextSt):
          $mMeta = $maintMeta[$nextSt] ?? ['icon' => 'arrow-right', 'cls' => 'bg-slate-600 hover:bg-slate-700 text-white', 'label' => $statuses[$nextSt] ?? $nextSt];
      ?>
      <form method="post" action="<?= e(url('flotte/maintenance/' . $order['id'] . '/status')) ?>" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= e($nextSt) ?>">
        <?php if ($nextSt === 'termine'): ?>
        <div class="flex items-end gap-2">
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Coût réel (FCFA)</label>
            <input type="number" name="actual_cost" placeholder="Montant" class="px-3 py-2 rounded-xl border border-slate-200 text-sm w-36">
          </div>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition <?= $mMeta['cls'] ?>">
            <i data-lucide="<?= e($mMeta['icon']) ?>" class="w-4 h-4"></i> <?= e($mMeta['label']) ?>
          </button>
        </div>
        <?php else: ?>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition <?= $mMeta['cls'] ?>">
          <i data-lucide="<?= e($mMeta['icon']) ?>" class="w-4 h-4"></i> <?= e($mMeta['label']) ?>
        </button>
        <?php endif; ?>
      </form>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif ?>
  <?php endif ?>
</div>
<?php $view->end() ?>
