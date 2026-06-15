<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$severityColors = [
  'mineur'   => 'bg-slate-100 text-slate-700',
  'modere'   => 'bg-amber-100 text-amber-700',
  'grave'    => 'bg-orange-100 text-orange-700',
  'critique' => 'bg-rose-100 text-rose-700',
];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('flotte/incidents')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <div class="flex items-start justify-between mt-2">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Incident #<?= (int)$incident['id'] ?></h1>
        <p class="text-slate-500 text-sm">Survenu le <?= e(date('d/m/Y H:i', strtotime((string)$incident['occurred_at']))) ?></p>
      </div>
      <div class="flex items-center gap-2">
        <span class="px-3 py-1 rounded-full text-xs font-medium bg-cb-bg text-cb-primary"><?= e($types[$incident['type']] ?? $incident['type']) ?></span>
        <span class="px-3 py-1 rounded-full text-xs font-medium <?= e($severityColors[$incident['severity']] ?? 'bg-slate-100') ?>"><?= e($severities[$incident['severity']] ?? $incident['severity']) ?></span>
        <?php if ($incident['resolved']): ?>
          <span class="px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Résolu</span>
        <?php else: ?>
          <span class="px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700">En cours</span>
        <?php endif ?>
        <a href="<?= e(url('flotte/incidents/' . $incident['id'] . '/edit')) ?>" class="px-3 py-1.5 rounded-lg bg-cb-primary text-white text-sm hover:bg-cb-secondary">Modifier</a>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    <div class="md:col-span-2 bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-4">
      <div>
        <h2 class="font-semibold text-slate-900 mb-2">Description</h2>
        <p class="text-slate-700 whitespace-pre-line"><?= e($incident['description']) ?></p>
      </div>
      <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100">
        <div>
          <p class="text-xs uppercase text-slate-400">Lieu</p>
          <p><?= e($incident['location'] ?? '—') ?></p>
        </div>
        <div>
          <p class="text-xs uppercase text-slate-400">Coût</p>
          <p class="font-semibold"><?= e(fcfa((int)$incident['cost_fcfa'])) ?></p>
        </div>
      </div>

      <?php if ($incident['resolved']): ?>
        <div class="pt-4 border-t border-slate-100">
          <h3 class="font-semibold text-slate-900 mb-1">Résolution</h3>
          <p class="text-xs text-slate-400 mb-2">Résolu le <?= e(date('d/m/Y H:i', strtotime((string)$incident['resolved_at']))) ?></p>
          <p class="text-slate-700 whitespace-pre-line"><?= e($incident['resolution_notes'] ?? '') ?></p>
          <form method="post" action="<?= e(url('flotte/incidents/' . $incident['id'] . '/reopen')) ?>" class="mt-3">
            <?= csrf_field() ?>
            <button class="text-sm text-amber-700 hover:underline">Rouvrir l'incident</button>
          </form>
        </div>
      <?php else: ?>
        <form method="post" action="<?= e(url('flotte/incidents/' . $incident['id'] . '/resolve')) ?>" class="pt-4 border-t border-slate-100 space-y-2">
          <?= csrf_field() ?>
          <h3 class="font-semibold text-slate-900">Marquer comme résolu</h3>
          <textarea name="resolution_notes" rows="3" placeholder="Notes de résolution (optionnel)" class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
          <button class="px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">Résoudre</button>
        </form>
      <?php endif ?>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-3">
      <h2 class="font-semibold text-slate-900">Sujet</h2>
      <?php if ($incident['subject_type'] === 'bus' && $incident['bus_code']): ?>
        <div>
          <p class="text-xs uppercase text-slate-400">Véhicule</p>
          <p class="font-semibold"><?= e($incident['bus_code']) ?></p>
          <p class="text-sm text-slate-500"><?= e($incident['plate']) ?> · <?= e(($incident['brand'] ?? '') . ' ' . ($incident['model'] ?? '')) ?></p>
          <a href="<?= e(url('referentiel/vehicules/' . $incident['bus_id'])) ?>" class="text-xs text-cb-primary hover:underline">Voir la fiche →</a>
        </div>
      <?php elseif ($incident['subject_type'] === 'driver' && $incident['driver_first']): ?>
        <div>
          <p class="text-xs uppercase text-slate-400">Chauffeur</p>
          <p class="font-semibold"><?= e($incident['driver_first'] . ' ' . $incident['driver_last']) ?></p>
          <p class="text-sm text-slate-500"><?= e($incident['driver_matricule']) ?> · <?= e($incident['driver_phone'] ?? '') ?></p>
          <a href="<?= e(url('referentiel/drivers/' . $incident['driver_id'])) ?>" class="text-xs text-cb-primary hover:underline">Voir la fiche →</a>
        </div>
      <?php endif ?>

      <div class="pt-3 border-t border-slate-100">
        <p class="text-xs uppercase text-slate-400">Déclaré par</p>
        <p><?= e(trim((string)$incident['reporter_name'])) ?: '—' ?></p>
        <p class="text-xs text-slate-400">Le <?= e(date('d/m/Y H:i', strtotime((string)$incident['created_at']))) ?></p>
      </div>
    </div>
  </div>
</div>
<?php $view->end() ?>
