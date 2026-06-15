<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$logTypes = [
  'conduite' => 'Conduite', 'pause' => 'Pause',
  'repos_quotidien' => 'Repos quotidien', 'repos_hebdo' => 'Repos hebdo',
  'disponibilite' => 'Disponibilité', 'autre' => 'Autre',
];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('rh/hos')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($driver['first_name'] . ' ' . $driver['last_name']) ?></h1>
    <p class="text-slate-500 text-sm"><?= e($driver['matricule']) ?> · <?= e($driver['phone']) ?></p>
  </div>

  <?php foreach ($status['warnings'] as $w): ?>
    <div class="rounded-2xl border p-4 <?= $w['level']==='critical' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800' ?>">
      <strong><?= $w['level']==='critical' ? '🛑 BLOQUANT' : '⚠ ATTENTION' ?> :</strong> <?= e($w['message']) ?>
    </div>
  <?php endforeach ?>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Aujourd'hui</p>
      <p class="text-3xl font-bold mt-1"><?= number_format($status['today_minutes']/60, 1, ',', '') ?>h</p>
      <p class="text-xs text-slate-500">/ <?= $status['limits']['daily_max']/60 ?>h max</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">7 jours</p>
      <p class="text-3xl font-bold mt-1"><?= number_format($status['week_minutes']/60, 1, ',', '') ?>h</p>
      <p class="text-xs text-slate-500">/ <?= $status['limits']['weekly_max']/60 ?>h max</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">14 jours</p>
      <p class="text-3xl font-bold mt-1"><?= number_format($status['biweek_minutes']/60, 1, ',', '') ?>h</p>
      <p class="text-xs text-slate-500">/ <?= $status['limits']['biweekly_max']/60 ?>h max</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Conduite continue</p>
      <p class="text-3xl font-bold mt-1"><?= $status['continuous_minutes'] ? number_format($status['continuous_minutes']/60, 1, ',', '') . 'h' : '—' ?></p>
      <p class="text-xs text-slate-500">Pause à <?= $status['limits']['continuous_max']/60 ?>h</p>
    </div>
  </div>

  <?php if (can('hos.edit')): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
    <h2 class="font-semibold text-slate-900 mb-3">Saisir une session</h2>
    <form method="post" action="<?= e(url('rh/hos/' . $driver['id'] . '/log')) ?>" class="grid grid-cols-1 md:grid-cols-5 gap-2">
      <?= csrf_field() ?>
      <select name="log_type" required class="px-3 py-2 rounded-xl border border-slate-200">
        <?php foreach ($logTypes as $k => $lbl): ?>
          <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
        <?php endforeach ?>
      </select>
      <input type="datetime-local" name="start_at" required class="px-3 py-2 rounded-xl border border-slate-200">
      <input type="datetime-local" name="end_at" placeholder="Fin (vide = en cours)" class="px-3 py-2 rounded-xl border border-slate-200">
      <input name="location" placeholder="Lieu" class="px-3 py-2 rounded-xl border border-slate-200">
      <button class="px-4 py-2 rounded-xl bg-cb-primary text-white">Enregistrer</button>
    </form>
  </div>
  <?php endif ?>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
      <h2 class="font-semibold">Historique 14 jours</h2>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-2 text-left">Type</th>
          <th class="px-5 py-2 text-left">Début</th>
          <th class="px-5 py-2 text-left">Fin</th>
          <th class="px-5 py-2 text-right">Durée</th>
          <th class="px-5 py-2 text-left">Voyage</th>
          <th class="px-5 py-2 text-left">Source</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="px-5 py-2"><?= e($logTypes[$l['log_type']] ?? $l['log_type']) ?></td>
          <td class="px-5 py-2"><?= e(date('d/m H:i', strtotime((string)$l['start_at']))) ?></td>
          <td class="px-5 py-2"><?= $l['end_at'] ? e(date('d/m H:i', strtotime((string)$l['end_at']))) : '<em>en cours</em>' ?></td>
          <td class="px-5 py-2 text-right font-mono"><?= $l['duration_minutes'] ? number_format($l['duration_minutes']/60, 2, ',', '') . 'h' : '—' ?></td>
          <td class="px-5 py-2"><?= e($l['trip_code'] ?? '—') ?></td>
          <td class="px-5 py-2 text-xs text-slate-500"><?= e($l['source']) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$logs): ?>
        <tr><td colspan="6" class="px-5 py-6 text-center text-slate-400">Aucune saisie sur les 14 derniers jours</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
