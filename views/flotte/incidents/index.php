<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$f = $filters;
$severityColors = [
  'mineur'   => 'bg-slate-100 text-slate-700',
  'modere'   => 'bg-amber-100 text-amber-700',
  'grave'    => 'bg-orange-100 text-orange-700',
  'critique' => 'bg-rose-100 text-rose-700',
];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Incidents</h1>
      <p class="text-slate-500 text-sm">Suivi des accidents, pannes, infractions et altercations sur la flotte.</p>
    </div>
    <a href="<?= e(url('flotte/incidents/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary inline-flex items-center gap-2">
      <i data-lucide="plus" class="w-4 h-4"></i> Déclarer un incident
    </a>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Total</p>
      <p class="text-3xl font-bold text-slate-900 mt-1"><?= (int)$stats['total'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Résolus</p>
      <p class="text-3xl font-bold text-emerald-600 mt-1"><?= (int)$stats['resolved_count'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Graves / Critiques</p>
      <p class="text-3xl font-bold text-rose-600 mt-1"><?= (int)$stats['critical_count'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Coût total</p>
      <p class="text-2xl font-bold text-slate-900 mt-1"><?= e(fcfa((int)$stats['total_cost'])) ?></p>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 grid grid-cols-2 md:grid-cols-7 gap-2">
    <select name="type" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="">Tous types</option>
      <?php foreach ($types as $k => $lbl): ?>
        <option value="<?= e($k) ?>" <?= $f['type']===$k?'selected':'' ?>><?= e($lbl) ?></option>
      <?php endforeach ?>
    </select>
    <select name="severity" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="">Toutes gravités</option>
      <?php foreach ($severities as $k => $lbl): ?>
        <option value="<?= e($k) ?>" <?= $f['severity']===$k?'selected':'' ?>><?= e($lbl) ?></option>
      <?php endforeach ?>
    </select>
    <select name="resolved" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="">Tous</option>
      <option value="no"  <?= $f['resolved']==='no'?'selected':'' ?>>Non résolus</option>
      <option value="yes" <?= $f['resolved']==='yes'?'selected':'' ?>>Résolus</option>
    </select>
    <select name="bus_id" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="0">Tous bus</option>
      <?php foreach ($buses as $b): ?>
        <option value="<?= (int)$b['id'] ?>" <?= (int)$f['busId']===(int)$b['id']?'selected':'' ?>><?= e($b['code']) ?></option>
      <?php endforeach ?>
    </select>
    <input type="date" name="from" value="<?= e($f['from']) ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <input type="date" name="to"   value="<?= e($f['to']) ?>"   class="px-3 py-2 rounded-xl border border-slate-200">
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Filtrer</button>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Date</th>
          <th class="px-5 py-3 text-left">Type / Gravité</th>
          <th class="px-5 py-3 text-left">Sujet</th>
          <th class="px-5 py-3 text-left">Lieu</th>
          <th class="px-5 py-3 text-right">Coût</th>
          <th class="px-5 py-3 text-center">Statut</th>
          <th class="px-5 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($incidents as $i): ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-3 whitespace-nowrap"><?= e(date('d/m/Y H:i', strtotime((string)$i['occurred_at']))) ?></td>
          <td class="px-5 py-3">
            <div class="font-medium"><?= e($types[$i['type']] ?? $i['type']) ?></div>
            <span class="px-2 py-0.5 rounded-full text-xs <?= e($severityColors[$i['severity']] ?? 'bg-slate-100') ?>"><?= e($severities[$i['severity']] ?? $i['severity']) ?></span>
          </td>
          <td class="px-5 py-3">
            <?php if ($i['subject_type'] === 'bus' && $i['bus_code']): ?>
              <i data-lucide="bus" class="w-3 h-3 inline"></i> <?= e($i['bus_code']) ?> · <?= e($i['plate']) ?>
            <?php elseif ($i['subject_type'] === 'driver' && $i['driver_first']): ?>
              <i data-lucide="user" class="w-3 h-3 inline"></i> <?= e($i['driver_first'] . ' ' . $i['driver_last']) ?>
              <div class="text-xs text-slate-400"><?= e($i['driver_matricule']) ?></div>
            <?php else: ?>
              <span class="text-slate-400">—</span>
            <?php endif ?>
          </td>
          <td class="px-5 py-3 text-slate-600"><?= e($i['location'] ?? '—') ?></td>
          <td class="px-5 py-3 text-right"><?= (int)$i['cost_fcfa'] > 0 ? e(fcfa((int)$i['cost_fcfa'])) : '<span class="text-slate-400">—</span>' ?></td>
          <td class="px-5 py-3 text-center">
            <?php if ($i['resolved']): ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-700">Résolu</span>
            <?php else: ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700">En cours</span>
            <?php endif ?>
          </td>
          <td class="px-5 py-3 text-right">
            <a href="<?= e(url('flotte/incidents/' . $i['id'])) ?>" class="text-cb-primary hover:underline">Détail</a>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$incidents): ?>
        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Aucun incident</td></tr>
      <?php endif ?>
      </tbody>
    </table>
    <!-- Règle de fin de liste -->
    <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-3 text-xs text-slate-400">
      <div class="flex-1 h-px bg-slate-100"></div>
      <span><?= count($incidents ?? []) ?> enregistrement(s) affiché(s)<?= (($from ?? '') === '' && ($to ?? '') === '') ? ' · 30 derniers' : '' ?></span>
      <div class="flex-1 h-px bg-slate-100"></div>
    </div>
    <?php if ($lastPage > 1): ?>
    <div class="p-4 flex justify-between items-center border-t border-slate-100">
      <span class="text-sm text-slate-500"><?= (int)$total ?> incident(s) · page <?= (int)$page ?> / <?= (int)$lastPage ?></span>
      <div class="flex gap-2">
        <?php if ($page > 1): ?>
          <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">Précédent</a>
        <?php endif ?>
        <?php if ($page < $lastPage): ?>
          <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">Suivant</a>
        <?php endif ?>
      </div>
    </div>
    <?php endif ?>
  </div>
</div>
<?php $view->end() ?>
