<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$dayNames = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
$shiftColors = [
  'voyage' => 'bg-cb-bg text-cb-primary',
  'agence' => 'bg-emerald-50 text-emerald-700',
  'conge'  => 'bg-amber-50 text-amber-700',
  'absent' => 'bg-rose-50 text-rose-700',
];
$shiftLabels = ['voyage'=>'Voyage','agence'=>'Agence','conge'=>'Congé','absent'=>'Absent'];
$prevWeek = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek = date('Y-m-d', strtotime($weekStart . ' +7 days'));
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Planning</h1>
      <p class="text-slate-500 text-sm">Affectations hebdomadaires des employés.</p>
    </div>
    <div class="flex gap-2 items-center">
      <a href="?week=<?= e($prevWeek) ?>" class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50">
        <i data-lucide="chevron-left" class="w-4 h-4"></i>
      </a>
      <span class="font-medium px-3">Semaine du <?= e(date('d/m/Y', strtotime($weekStart))) ?> au <?= e(date('d/m/Y', strtotime($weekEnd))) ?></span>
      <a href="?week=<?= e($nextWeek) ?>" class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50">
        <i data-lucide="chevron-right" class="w-4 h-4"></i>
      </a>
      <a href="?week=<?= e(date('Y-m-d', strtotime('monday this week'))) ?>" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm">Aujourd'hui</a>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <input type="hidden" name="week" value="<?= e($weekStart) ?>">
    <select name="position" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="">Tous postes</option>
      <?php foreach ($positions as $p): ?>
        <option value="<?= e($p) ?>" <?= $positionFilter===$p?'selected':'' ?>><?= e(ucfirst($p)) ?></option>
      <?php endforeach ?>
    </select>
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Filtrer</button>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft overflow-x-auto">
    <table class="w-full text-sm border-collapse">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs uppercase text-slate-600 sticky left-0 bg-slate-50 z-10">Employé</th>
          <?php foreach ($days as $i => $d): ?>
            <th class="px-3 py-3 text-center text-xs uppercase text-slate-600 min-w-[140px]">
              <?= $dayNames[$i] ?> <span class="text-slate-400"><?= e(date('d/m', strtotime($d))) ?></span>
            </th>
          <?php endforeach ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($employees as $emp): ?>
          <tr>
            <td class="px-4 py-3 sticky left-0 bg-white z-10 border-r border-slate-100">
              <div class="font-medium text-slate-900"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
              <div class="text-xs text-slate-500"><?= e($emp['matricule']) ?> · <?= e($emp['position']) ?></div>
            </td>
            <?php foreach ($days as $d): $key = $emp['id'] . '|' . $d; $items = $grid[$key] ?? []; ?>
              <td class="px-2 py-2 align-top">
                <?php foreach ($items as $s): ?>
                  <div class="<?= e($shiftColors[$s['shift_type']] ?? 'bg-slate-100') ?> rounded-lg px-2 py-1 text-xs mb-1 flex justify-between items-center">
                    <span>
                      <?= e($shiftLabels[$s['shift_type']] ?? $s['shift_type']) ?>
                      <?php if ($s['trip_code']): ?> · <?= e($s['line_code']) ?> <?php endif ?>
                    </span>
                    <?php if (can('rh.edit')): ?>
                      <form method="post" action="<?= e(url('rh/schedule/' . $s['id'] . '/delete?week=' . $weekStart)) ?>" class="inline">
                        <?= csrf_field() ?>
                        <button class="text-slate-500 hover:text-rose-600" title="Supprimer">×</button>
                      </form>
                    <?php endif ?>
                  </div>
                <?php endforeach ?>
                <?php if (can('rh.edit')): ?>
                  <details class="text-xs">
                    <summary class="cursor-pointer text-slate-400 hover:text-cb-primary">+ Ajouter</summary>
                    <form method="post" action="<?= e(url('rh/schedule?week=' . $weekStart)) ?>" class="mt-1 space-y-1">
                      <?= csrf_field() ?>
                      <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                      <input type="hidden" name="schedule_date" value="<?= e($d) ?>">
                      <select name="shift_type" class="w-full px-1.5 py-1 text-xs rounded border border-slate-200">
                        <?php foreach ($shiftLabels as $k => $lbl): ?>
                          <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                        <?php endforeach ?>
                      </select>
                      <button class="w-full px-2 py-1 text-xs rounded bg-cb-primary text-white">OK</button>
                    </form>
                  </details>
                <?php endif ?>
              </td>
            <?php endforeach ?>
          </tr>
        <?php endforeach ?>
        <?php if (!$employees): ?>
          <tr><td colspan="<?= count($days) + 1 ?>" class="px-4 py-12 text-center text-slate-400">Aucun employé</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
