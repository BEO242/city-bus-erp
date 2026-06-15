<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$months = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Tableau de bord RH</h1>
      <p class="text-slate-500 text-sm">Effectifs, masse salariale, alertes documents et anniversaires.</p>
    </div>
    <form method="get" class="flex gap-2">
      <select name="month" class="px-3 py-2 rounded-xl border border-slate-200">
        <?php foreach ($months as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= $month===$k?'selected':'' ?>><?= e($lbl) ?></option>
        <?php endforeach ?>
      </select>
      <select name="year" class="px-3 py-2 rounded-xl border border-slate-200">
        <?php for ($y = (int)date('Y') - 3; $y <= (int)date('Y') + 1; $y++): ?>
          <option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option>
        <?php endfor ?>
      </select>
      <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">OK</button>
    </form>
  </div>

  <!-- KPIs Effectifs -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Total employés</p>
      <p class="text-3xl font-bold text-slate-900 mt-1"><?= (int)$totals['total'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Actifs</p>
      <p class="text-3xl font-bold text-emerald-600 mt-1"><?= (int)$totals['actifs'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">En congé</p>
      <p class="text-3xl font-bold text-amber-600 mt-1"><?= (int)$totals['conges'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Inactifs</p>
      <p class="text-3xl font-bold text-slate-500 mt-1"><?= (int)$totals['inactifs'] ?></p>
    </div>
  </div>

  <!-- KPIs Paie -->
  <div class="bg-gradient-to-br from-cb-primary to-cb-secondary rounded-2xl p-6 text-white">
    <p class="text-sm opacity-80">Paie · <?= e($months[$month]) ?> <?= (int)$year ?></p>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-4">
      <div>
        <p class="text-xs opacity-80 uppercase">Bulletins</p>
        <p class="text-2xl font-bold mt-1"><?= (int)$payrollMonth['payslips'] ?></p>
      </div>
      <div>
        <p class="text-xs opacity-80 uppercase">Masse brute</p>
        <p class="text-2xl font-bold mt-1"><?= e(fcfa((int)$payrollMonth['gross_total'])) ?></p>
      </div>
      <div>
        <p class="text-xs opacity-80 uppercase">Net total</p>
        <p class="text-2xl font-bold mt-1"><?= e(fcfa((int)$payrollMonth['net_total'])) ?></p>
      </div>
      <div>
        <p class="text-xs opacity-80 uppercase">Payés</p>
        <p class="text-2xl font-bold mt-1"><?= (int)$payrollMonth['paid_count'] ?> / <?= (int)$payrollMonth['payslips'] ?></p>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- Par poste -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-4">Effectifs actifs par poste</h2>
      <div class="space-y-2">
        <?php $totalActifs = max(1, (int)$totals['actifs']); foreach ($byPosition as $row): $pct = round(((int)$row['n'] / $totalActifs) * 100); ?>
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="font-medium"><?= e(ucfirst($row['position'])) ?></span>
              <span class="text-slate-500"><?= (int)$row['n'] ?> · <?= $pct ?>%</span>
            </div>
            <div class="bg-slate-100 rounded-full h-2 overflow-hidden">
              <div class="bg-cb-primary h-full rounded-full" style="width: <?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach ?>
        <?php if (!$byPosition): ?>
          <p class="text-slate-400 text-sm">Aucun employé actif.</p>
        <?php endif ?>
      </div>
    </div>

    <!-- Par agence -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-4">Effectifs par agence</h2>
      <div class="space-y-2">
        <?php foreach ($byAgency as $row): ?>
          <div class="flex justify-between items-center py-1.5 border-b border-slate-100 last:border-0">
            <span class="text-sm"><?= e($row['agency_name']) ?></span>
            <span class="px-2 py-0.5 rounded-full bg-cb-bg text-cb-primary text-xs font-medium"><?= (int)$row['n'] ?></span>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>

  <!-- Alertes documents -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
        <i data-lucide="id-card" class="w-4 h-4 text-rose-600"></i> Permis expirant
      </h2>
      <div class="space-y-2">
        <?php foreach ($licenseAlerts as $a): $days = (strtotime($a['license_expiry']) - time()) / 86400; ?>
          <div class="flex justify-between items-center py-1.5 border-b border-slate-100 last:border-0">
            <span class="text-sm"><?= e($a['name']) ?></span>
            <span class="text-xs <?= $days < 7 ? 'text-rose-600 font-bold' : 'text-amber-600' ?>"><?= e(date('d/m/Y', strtotime($a['license_expiry']))) ?></span>
          </div>
        <?php endforeach ?>
        <?php if (!$licenseAlerts): ?>
          <p class="text-slate-400 text-sm">✓ Aucun permis expirant prochainement.</p>
        <?php endif ?>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
        <i data-lucide="heart-pulse" class="w-4 h-4 text-amber-600"></i> Visites médicales
      </h2>
      <div class="space-y-2">
        <?php foreach ($medicalAlerts as $a): $days = (strtotime($a['medical_cert_expiry']) - time()) / 86400; ?>
          <div class="flex justify-between items-center py-1.5 border-b border-slate-100 last:border-0">
            <span class="text-sm"><?= e($a['name']) ?></span>
            <span class="text-xs <?= $days < 7 ? 'text-rose-600 font-bold' : 'text-amber-600' ?>"><?= e(date('d/m/Y', strtotime($a['medical_cert_expiry']))) ?></span>
          </div>
        <?php endforeach ?>
        <?php if (!$medicalAlerts): ?>
          <p class="text-slate-400 text-sm">✓ Aucune visite médicale expirante.</p>
        <?php endif ?>
      </div>
    </div>
  </div>

  <!-- Anniversaires -->
  <?php if ($anniversaries): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
    <h2 class="font-semibold text-slate-900 mb-3 flex items-center gap-2">
      <i data-lucide="party-popper" class="w-4 h-4 text-cb-primary"></i> Anniversaires d'embauche · <?= e($months[$month]) ?>
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <?php foreach ($anniversaries as $a): ?>
        <div class="bg-cb-bg/40 rounded-xl p-3 flex items-center gap-3">
          <div class="bg-cb-primary text-white rounded-lg w-10 h-10 flex items-center justify-center font-bold">
            <?= (int)$a['years'] ?>
          </div>
          <div>
            <p class="font-medium text-slate-900 text-sm"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></p>
            <p class="text-xs text-slate-500"><?= e($a['position']) ?> · <?= e(date('d/m', strtotime($a['hire_date']))) ?></p>
          </div>
        </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>
</div>
<?php $view->end() ?>
