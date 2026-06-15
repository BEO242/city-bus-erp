<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$nps = (float)($summary['nps'] ?? 0);
$npsColor = $nps >= 50 ? 'text-emerald-600' : ($nps >= 0 ? 'text-amber-600' : 'text-rose-600');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Avis clients</h1>
    <p class="text-slate-500 text-sm">NPS et notes par critère.</p>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <input type="date" name="from" value="<?= e($from) ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <input type="date" name="to"   value="<?= e($to)   ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">OK</button>
  </form>

  <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Réponses</p>
      <p class="text-3xl font-bold"><?= (int)($summary['responses'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">NPS</p>
      <p class="text-3xl font-bold <?= $npsColor ?>"><?= $nps ?></p>
      <p class="text-xs text-slate-500"><?= (int)($summary['promoters'] ?? 0) ?> P · <?= (int)($summary['passives'] ?? 0) ?> N · <?= (int)($summary['detractors'] ?? 0) ?> D</p>
    </div>
    <?php foreach (['avg_overall' => 'Globale', 'avg_punctuality' => 'Ponctualité', 'avg_comfort' => 'Confort'] as $k => $lbl): ?>
      <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
        <p class="text-xs uppercase text-slate-400"><?= e($lbl) ?></p>
        <p class="text-3xl font-bold"><?= number_format((float)($summary[$k] ?? 0), 1, ',', '') ?> <span class="text-sm text-slate-400">/5</span></p>
      </div>
    <?php endforeach ?>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <div class="px-5 py-3 bg-slate-50 border-b border-slate-100"><h2 class="font-semibold">Avis récents</h2></div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">Voyage</th>
          <th class="px-3 py-2 text-left">Client</th>
          <th class="px-3 py-2 text-center">NPS</th>
          <th class="px-3 py-2 text-center">Globale</th>
          <th class="px-3 py-2 text-left">Commentaire</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="px-3 py-2 text-xs"><?= e(date('d/m H:i', strtotime((string)$r['submitted_at']))) ?></td>
          <td class="px-3 py-2 text-xs"><?= e($r['trip_code'] ?? '—') ?></td>
          <td class="px-3 py-2"><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?: '—' ?></td>
          <td class="px-3 py-2 text-center">
            <span class="px-2 py-0.5 rounded text-xs <?= (int)$r['nps_score'] >= 9 ? 'bg-emerald-100 text-emerald-700' : ((int)$r['nps_score'] <= 6 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') ?>"><?= (int)$r['nps_score'] ?></span>
          </td>
          <td class="px-3 py-2 text-center"><?= str_repeat('⭐', (int)$r['rating_overall']) ?></td>
          <td class="px-3 py-2 text-sm text-slate-600 truncate max-w-md" title="<?= e($r['comment'] ?? '') ?>"><?= e(mb_substr((string)($r['comment'] ?? ''), 0, 80)) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$recent): ?><tr><td colspan="6" class="py-12 text-center text-slate-400">Aucun avis</td></tr><?php endif ?>
      </tbody>
    </table>
    <!-- Règle de fin de liste -->
    <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-3 text-xs text-slate-400">
      <div class="flex-1 h-px bg-slate-100"></div>
      <span><?= count($recent ?? []) ?> avis affiché(s)<?= (($from ?? '') === '' && ($to ?? '') === '') ? ' · 30 derniers' : '' ?></span>
      <div class="flex-1 h-px bg-slate-100"></div>
    </div>
  </div>
</div>
<?php $view->end() ?>
