<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <a href="<?= e(url('rh/employees')) ?>" class="text-sm text-slate-500 inline-flex items-center gap-1"><i data-lucide="chevron-left" class="w-4 h-4"></i> Retour</a>

  <div class="bg-gradient-to-br from-cb-primary to-cb-secondary text-white rounded-2xl p-6 shadow-soft flex items-center gap-5">
    <div class="w-20 h-20 rounded-2xl bg-white/20 flex items-center justify-center text-3xl font-bold">
      <?= e(strtoupper(($employee['first_name'][0] ?? '').($employee['last_name'][0] ?? ''))) ?>
    </div>
    <div class="flex-1">
      <h1 class="text-2xl font-bold"><?= e($employee['first_name']) ?> <?= e($employee['last_name']) ?></h1>
      <p class="opacity-80"><?= e($employee['position']) ?> · <?= e($employee['matricule']) ?></p>
      <p class="text-sm opacity-80 mt-1"><?= e($employee['phone'] ?? '—') ?> · <?= e($employee['email'] ?? '—') ?></p>
    </div>
    <a href="<?= e(url('rh/employees/'.$employee['id'].'/edit')) ?>" class="px-4 py-2 rounded-xl bg-white/20 hover:bg-white/30 text-sm font-medium">Modifier</a>
  </div>

  <div class="grid md:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5"><p class="text-xs text-slate-500">Salaire de base</p><p class="text-xl font-bold mt-1"><?= e(fcfa((int)($employee['salary_base'] ?? 0))) ?></p><?php if (!empty($employee['daily_bonus'])): ?><p class="text-xs text-slate-500 mt-1">+ Prime journalière : <?= e(fcfa((int)$employee['daily_bonus'])) ?></p><?php endif ?></div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5"><p class="text-xs text-slate-500">Date d'embauche</p><p class="font-bold mt-1"><?= e(date_fr($employee['hire_date'])) ?></p></div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5"><p class="text-xs text-slate-500">Statut</p><p class="font-bold mt-1"><?= e($employee['status']) ?></p></div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
    <h3 class="font-semibold mb-3">Historique des paies</h3>
    <table class="w-full text-sm">
      <thead class="text-xs uppercase text-slate-500"><tr><th class="text-left py-2">Période</th><th class="text-right">Brut</th><th class="text-right">Déductions</th><th class="text-right">Net</th><th class="text-center">Statut</th><th></th></tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($payrolls as $p): ?>
          <tr>
            <td class="py-2"><?= e(sprintf('%02d/%d', $p['month'], $p['year'])) ?></td>
            <td class="text-right"><?= e(fcfa((int)$p['gross'])) ?></td>
            <td class="text-right text-rose-600">-<?= e(fcfa((int)$p['deductions'])) ?></td>
            <td class="text-right font-bold text-emerald-600"><?= e(fcfa((int)$p['net'])) ?></td>
            <td class="text-center"><span class="px-2 py-0.5 rounded-full text-xs <?= $p['status']==='paye'?'bg-emerald-50 text-emerald-700':'bg-amber-50 text-amber-700' ?>"><?= e($p['status']) ?></span></td>
            <td class="text-right"><a href="<?= e(url('rh/payroll/'.$p['id'].'/payslip')) ?>" class="text-cb-primary text-xs hover:underline">Bulletin</a></td>
          </tr>
        <?php endforeach ?>
        <?php if (!$payrolls): ?><tr><td colspan="6" class="py-6 text-center text-slate-400">Aucune paie</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
