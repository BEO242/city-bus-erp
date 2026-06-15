<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
      <i data-lucide="grid-3x3" class="w-6 h-6 text-cb-primary"></i> Segmentation RFM
    </h1>
    <form method="get" class="inline">
      <input type="hidden" name="recompute" value="1">
      <button class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600">
        <i data-lucide="refresh-cw" class="inline w-4 h-4"></i> Recalculer
      </button>
    </form>
  </div>

  <p class="text-sm text-slate-600">
    R = récence (1=très ancien, 5=très récent) · F = fréquence · M = montant.
    <strong>5-5-5</strong> = champions, <strong>1-1-1</strong> = perdus.
  </p>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-3 py-2">Segment R-F-M</th>
          <th class="px-3 py-2 text-center">Clients</th>
          <th class="px-3 py-2">Profil</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($distribution as $d):
          $score = (int)$d['rfm_recency'] + (int)$d['rfm_frequency'] + (int)$d['rfm_monetary'];
          $profile = match(true) {
            $score >= 13 => 'Champions',
            $score >= 10 => 'Loyaux',
            $score >= 7  => 'Potentiels',
            $score >= 4  => 'À risque',
            default      => 'Perdus',
          };
          $col = match($profile){'Champions'=>'emerald','Loyaux'=>'sky','Potentiels'=>'amber','À risque'=>'orange','Perdus'=>'rose'};
        ?>
          <tr>
            <td class="px-3 py-2 font-mono"><?= (int)$d['rfm_recency'] ?>-<?= (int)$d['rfm_frequency'] ?>-<?= (int)$d['rfm_monetary'] ?></td>
            <td class="px-3 py-2 text-center font-bold"><?= (int)$d['n'] ?></td>
            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-<?= $col ?>-100 text-<?= $col ?>-700"><?= e($profile) ?></span></td>
          </tr>
        <?php endforeach ?>
        <?php if (empty($distribution)): ?><tr><td colspan="3" class="px-3 py-12 text-center text-slate-400">Cliquez "Recalculer" pour générer la segmentation.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
