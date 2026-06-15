<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
  $rows   = $rows ?? [];
  $stats  = $stats ?? [];
  $cntOpen     = count(array_filter($rows, fn($r) => $r['status'] === 'open'));
  $cntInProg   = count(array_filter($rows, fn($r) => in_array($r['status'], ['investigating', 'escalated'])));
  $cntCritical = count(array_filter($rows, fn($r) => $r['severity'] === 'critical'));
  $totalComp   = array_sum(array_column($rows, 'compensation_fcfa'));
?>
<div class="space-y-5">

  <!-- Header -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-8 h-8 rounded-xl bg-orange-500 flex items-center justify-center">
          <i data-lucide="message-circle-warning" class="w-4 h-4 text-white"></i>
        </span>
        Réclamations clients
      </h1>
      <p class="text-xs text-slate-400 mt-0.5"><?= count($rows) ?> réclamation(s) · Gestion qualité service</p>
    </div>
    <?php if (can('crm.complaints.manage')): ?>
    <button onclick="document.getElementById('new-complaint-modal').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 transition shadow-sm">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle réclamation
    </button>
    <?php endif ?>
  </div>

  <!-- Stats strip -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-white rounded-2xl border <?= $cntCritical > 0 ? 'border-rose-200' : 'border-slate-100' ?> shadow-sm p-4">
      <p class="text-[10px] text-rose-600 uppercase tracking-widest font-semibold">Critiques</p>
      <p class="text-2xl font-black <?= $cntCritical > 0 ? 'text-rose-700' : 'text-slate-300' ?> mt-1"><?= $cntCritical ?></p>
      <p class="text-[10px] text-slate-400 mt-0.5">Priorité absolue</p>
    </div>
    <div class="bg-white rounded-2xl border border-amber-100 shadow-sm p-4">
      <p class="text-[10px] text-amber-600 uppercase tracking-widest font-semibold">Ouvertes</p>
      <p class="text-2xl font-black text-amber-700 mt-1"><?= $cntOpen ?></p>
      <p class="text-[10px] text-slate-400 mt-0.5">En attente</p>
    </div>
    <div class="bg-white rounded-2xl border border-sky-100 shadow-sm p-4">
      <p class="text-[10px] text-sky-600 uppercase tracking-widest font-semibold">En traitement</p>
      <p class="text-2xl font-black text-sky-700 mt-1"><?= $cntInProg ?></p>
      <p class="text-[10px] text-slate-400 mt-0.5">Assignées</p>
    </div>
    <div class="bg-white rounded-2xl border border-violet-100 shadow-sm p-4">
      <p class="text-[10px] text-violet-600 uppercase tracking-widest font-semibold">Compensations</p>
      <p class="text-2xl font-black text-violet-700 mt-1"><?= number_format(round($totalComp / 1000)) ?>K</p>
      <p class="text-[10px] text-slate-400 mt-0.5">FCFA accordés</p>
    </div>
  </div>

  <!-- Categories breakdown -->
  <?php if (!empty($stats)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
    <h2 class="font-bold text-slate-900 text-sm mb-4 flex items-center gap-2">
      <i data-lucide="pie-chart" class="w-4 h-4 text-slate-500"></i>
      Répartition par catégorie · 30 derniers jours
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <?php
        $maxCat = max(1, max(array_column($stats, 'n')));
        foreach ($stats as $s):
          $pct = round((int)$s['n'] / $maxCat * 100);
      ?>
      <div class="rounded-xl bg-slate-50 border border-slate-100 p-3">
        <p class="text-xs font-semibold text-slate-700 capitalize"><?= e($s['category']) ?></p>
        <p class="text-2xl font-black text-slate-900 mt-1"><?= (int)$s['n'] ?></p>
        <div class="h-1.5 rounded-full bg-slate-200 mt-2">
          <div class="h-full rounded-full bg-cb-primary" style="width:<?= $pct ?>%"></div>
        </div>
        <?php if ((int)$s['comp_total'] > 0): ?>
          <p class="text-[10px] text-slate-400 mt-1"><?= number_format((int)$s['comp_total']) ?> F compensation</p>
        <?php endif ?>
      </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <!-- Filter bar -->
    <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-3 flex-wrap">
      <form method="get" class="flex items-center gap-2 flex-1">
        <div class="flex items-center gap-1 bg-slate-100 rounded-xl p-1 text-xs">
          <?php
            $statFilters = [''=>'Toutes','open'=>'Ouvertes','investigating'=>'En cours','escalated'=>'Escaladées','resolved'=>'Résolues','closed'=>'Fermées'];
            $curSt = $_GET['status'] ?? '';
            foreach ($statFilters as $val => $lbl):
          ?>
            <a href="?status=<?= $val ?>"
               class="px-3 py-1.5 rounded-lg font-semibold transition
                      <?= $curSt === $val ? 'bg-white shadow text-cb-primary' : 'text-slate-500 hover:text-slate-700' ?>">
              <?= $lbl ?>
            </a>
          <?php endforeach ?>
        </div>
        <select name="severity" onchange="this.form.submit()" class="ml-2 px-3 py-1.5 rounded-xl border border-slate-200 text-xs text-slate-600 bg-white">
          <option value="">Toutes sévérités</option>
          <?php foreach (['critical','high','medium','low'] as $sev): ?>
            <option value="<?= $sev ?>" <?= ($_GET['severity'] ?? '') === $sev ? 'selected' : '' ?>><?= ucfirst($sev) ?></option>
          <?php endforeach ?>
        </select>
      </form>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 border-b border-slate-100">
          <tr class="text-[10px] text-slate-400 uppercase tracking-wider">
            <th class="px-5 py-3 text-left font-semibold">Sévérité</th>
            <th class="px-5 py-3 text-left font-semibold">Client</th>
            <th class="px-5 py-3 text-left font-semibold">Catégorie</th>
            <th class="px-5 py-3 text-left font-semibold">Description</th>
            <th class="px-5 py-3 text-center font-semibold">Statut</th>
            <th class="px-5 py-3 text-left font-semibold">Assigné</th>
            <th class="px-5 py-3 text-right font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php
            $filtered = $rows;
            if ($curSt !== '') $filtered = array_filter($rows, fn($r) => $r['status'] === $curSt);
            if (!empty($_GET['severity'])) $filtered = array_filter($filtered, fn($r) => $r['severity'] === $_GET['severity']);
            foreach ($filtered as $r):
              $sevMap = [
                'critical' => ['bg-rose-100 text-rose-700',   '🔴 Critique'],
                'high'     => ['bg-orange-100 text-orange-700','🟠 Haute'],
                'medium'   => ['bg-amber-100 text-amber-700',  '🟡 Moyenne'],
                'low'      => ['bg-sky-100 text-sky-700',      '🔵 Faible'],
              ];
              $stMap = [
                'open'         => 'bg-amber-100 text-amber-700',
                'investigating'=> 'bg-sky-100 text-sky-700',
                'escalated'    => 'bg-rose-100 text-rose-700',
                'resolved'     => 'bg-emerald-100 text-emerald-700',
                'closed'       => 'bg-slate-100 text-slate-500',
              ];
              [$sevCls, $sevLbl] = $sevMap[$r['severity']] ?? ['bg-slate-100 text-slate-500', ucfirst($r['severity'])];
              $stCls = $stMap[$r['status']] ?? 'bg-slate-100 text-slate-500';
              $stLbl = match($r['status']) {
                'open' => 'Ouverte', 'investigating' => 'En cours', 'escalated' => 'Escaladée',
                'resolved' => 'Résolue', 'closed' => 'Fermée', default => ucfirst($r['status'])
              };
          ?>
          <tr class="hover:bg-slate-50 transition">
            <td class="px-5 py-3">
              <span class="text-[10px] font-bold px-2.5 py-1 rounded-full <?= $sevCls ?>"><?= $sevLbl ?></span>
            </td>
            <td class="px-5 py-3">
              <a href="<?= e(url('crm/customers/' . $r['customer_id'])) ?>"
                 class="font-semibold text-xs text-cb-primary hover:underline block">
                <?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?: '—' ?>
              </a>
              <span class="text-[10px] text-slate-400"><?= e($r['phone_display'] ?? '') ?></span>
            </td>
            <td class="px-5 py-3 text-xs text-slate-600 font-medium"><?= e($r['category']) ?></td>
            <td class="px-5 py-3 text-xs text-slate-700 max-w-xs">
              <p class="truncate"><?= e(mb_substr($r['description'], 0, 100)) ?></p>
              <?php if ((int)($r['compensation_fcfa'] ?? 0) > 0): ?>
                <span class="text-[10px] text-violet-600 font-semibold">Compensation: <?= number_format((int)$r['compensation_fcfa']) ?> F</span>
              <?php endif ?>
            </td>
            <td class="px-5 py-3 text-center">
              <span class="text-[10px] font-bold px-2.5 py-1 rounded-full <?= $stCls ?>"><?= $stLbl ?></span>
            </td>
            <td class="px-5 py-3 text-xs text-slate-500"><?= e($r['assigned_name'] ?? '—') ?></td>
            <td class="px-5 py-3 text-right">
              <?php if (can('crm.complaints.manage') && $r['status'] !== 'closed'): ?>
              <div x-data="{ open: false }" class="relative inline-block">
                <button @click="open=!open" class="text-xs bg-slate-100 hover:bg-emerald-100 text-slate-600 hover:text-emerald-700 px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1">
                  <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Résoudre
                </button>
                <div x-show="open" @click.outside="open=false" x-cloak
                     class="absolute right-0 top-8 w-72 bg-white rounded-xl border border-slate-200 shadow-xl z-20 p-4">
                  <form method="post" action="<?= e(url('crm/complaints/' . $r['id'] . '/resolve')) ?>" class="space-y-3">
                    <?= csrf_field() ?>
                    <div>
                      <label class="text-xs font-semibold text-slate-600 block mb-1">Résolution *</label>
                      <textarea name="resolution" required rows="3" placeholder="Description de la résolution…"
                                class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:ring-2 focus:ring-cb-primary/30 outline-none resize-none"></textarea>
                    </div>
                    <div>
                      <label class="text-xs font-semibold text-slate-600 block mb-1">Compensation FCFA</label>
                      <input name="compensation_fcfa" type="number" min="0" placeholder="0"
                             class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 outline-none">
                    </div>
                    <div>
                      <label class="text-xs font-semibold text-slate-600 block mb-1">Code bon (optionnel)</label>
                      <input name="voucher_code" placeholder="BON-XXXX"
                             class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 outline-none">
                    </div>
                    <div class="flex gap-2">
                      <button type="submit" class="flex-1 py-2 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition">
                        Valider résolution
                      </button>
                      <button type="button" @click="open=false" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-600 text-xs hover:bg-slate-200 transition">
                        Annuler
                      </button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($filtered)): ?>
          <tr><td colspan="7" class="px-5 py-14 text-center">
            <i data-lucide="message-circle-check" class="w-10 h-10 text-emerald-200 mx-auto mb-3"></i>
            <p class="text-emerald-600 text-sm font-semibold">Aucune réclamation<?= $curSt ? ' dans ce statut' : '' ?></p>
          </td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $view->end() ?>
