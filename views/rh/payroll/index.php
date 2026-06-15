<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold">Paie</h1>
      <p class="text-slate-500 text-sm">Génération mensuelle des salaires.</p>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2 items-center flex-wrap">
    <select name="month" class="px-3 py-2 rounded-xl border border-slate-200">
      <?php for ($m=1; $m<=12; $m++): ?>
        <option value="<?= $m ?>" <?= (int)$month===$m?'selected':'' ?>><?= e(sprintf('%02d', $m)) ?></option>
      <?php endfor ?>
    </select>
    <select name="year" class="px-3 py-2 rounded-xl border border-slate-200">
      <?php for ($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
        <option value="<?= $y ?>" <?= (int)$year===$y?'selected':'' ?>><?= $y ?></option>
      <?php endfor ?>
    </select>
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Afficher</button>

    <?php if (can('rh.payroll')): ?>
      <form method="post" action="<?= e(url('rh/payroll/run')) ?>" class="ml-auto" onsubmit="return confirm('Générer la paie pour <?= e(sprintf('%02d/%d', $month, $year)) ?> ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="year" value="<?= e($year) ?>">
        <button class="px-4 py-2 rounded-xl bg-cb-primary text-white font-medium inline-flex items-center gap-2">
          <i data-lucide="play" class="w-4 h-4"></i> Générer la paie
        </button>
      </form>
    <?php endif ?>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr><th class="px-5 py-3 text-left">Employé</th><th class="px-5 py-3 text-left">Poste</th><th class="px-5 py-3 text-right">Brut</th><th class="px-5 py-3 text-right">Déductions</th><th class="px-5 py-3 text-right">Net</th><th class="px-5 py-3 text-center">Statut</th><th></th></tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($payrolls as $p): ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-3 font-medium"><?= e($p['first_name']) ?> <?= e($p['last_name']) ?></td>
          <td class="px-5 py-3 text-slate-500"><?= e($p['position']) ?></td>
          <td class="px-5 py-3 text-right"><?= e(fcfa((int)$p['gross'])) ?></td>
          <td class="px-5 py-3 text-right text-rose-600">-<?= e(fcfa((int)$p['deductions'])) ?></td>
          <td class="px-5 py-3 text-right font-bold text-emerald-600"><?= e(fcfa((int)$p['net'])) ?></td>
          <td class="px-5 py-3 text-center"><span class="px-2 py-0.5 rounded-full text-xs <?= $p['status']==='paye'?'bg-emerald-50 text-emerald-700':'bg-amber-50 text-amber-700' ?>"><?= e($p['status']) ?></span></td>
          <td class="px-5 py-3 text-right space-x-2">
            <a href="<?= e(url('rh/payroll/'.$p['id'].'/payslip')) ?>" target="_blank" class="text-cb-primary text-xs hover:underline">Bulletin</a>
            <?php if ($p['status'] !== 'paye' && can('rh.payroll')): ?>
              <form method="post" action="<?= e(url('rh/payroll/'.$p['id'].'/paid')) ?>" class="inline">
                <?= csrf_field() ?>
                <button class="text-emerald-600 text-xs hover:underline">Marquer payé</button>
              </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$payrolls): ?><tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Aucune paie pour cette période</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
