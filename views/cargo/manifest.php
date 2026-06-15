<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <a href="<?= e(url('voyages/' . $trip['id'])) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
        <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour au voyage
      </a>
      <h1 class="text-2xl font-bold text-slate-900 mt-2">Manifeste Fret</h1>
      <p class="text-slate-500 text-sm">Voyage <?= e($trip['trip_code'] ?? '') ?> · <?= e(date('d/m/Y', strtotime((string)$trip['trip_date']))) ?></p>
    </div>
    <a href="?pdf=1" class="px-4 py-2 rounded-xl bg-slate-900 text-white inline-flex items-center gap-2">
      <i data-lucide="file-down" class="w-4 h-4"></i> Télécharger PDF
    </a>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">Total colis</p><p class="text-2xl font-bold"><?= (int)$totals['count'] ?></p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">Poids total</p><p class="text-2xl font-bold"><?= number_format((float)$totals['weight'], 2, ',', ' ') ?> kg</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">Valeur déclarée</p><p class="text-xl font-bold"><?= e(fcfa((int)$totals['declared'])) ?></p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">Recettes</p><p class="text-xl font-bold text-emerald-600"><?= e(fcfa((int)$totals['revenue'])) ?></p></div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-4 py-3 text-left">N°</th>
          <th class="px-4 py-3 text-left">Expéditeur</th>
          <th class="px-4 py-3 text-left">Destinataire</th>
          <th class="px-4 py-3 text-right">Poids</th>
          <th class="px-4 py-3 text-right">Prix</th>
          <th class="px-4 py-3 text-center">Paiement</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($parcels as $p): ?>
        <tr>
          <td class="px-4 py-3"><a href="<?= e(url('cargo/parcels/' . $p['id'])) ?>" class="font-mono text-cb-primary hover:underline"><?= e($p['parcel_number']) ?></a></td>
          <td class="px-4 py-3"><?= e($p['sender_name']) ?><div class="text-xs text-slate-400"><?= e($p['sender_phone']) ?></div></td>
          <td class="px-4 py-3"><?= e($p['recipient_name']) ?><div class="text-xs text-slate-400"><?= e($p['recipient_phone']) ?></div></td>
          <td class="px-4 py-3 text-right"><?= number_format((float)$p['weight_kg'], 2, ',', ' ') ?> kg</td>
          <td class="px-4 py-3 text-right font-semibold"><?= e(fcfa((int)$p['total_price_fcfa'])) ?></td>
          <td class="px-4 py-3 text-center">
            <?php if ($p['paid_at_origin']): ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-700">Payé</span>
            <?php else: ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700">À destination</span>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$parcels): ?>
        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Aucun colis sur ce voyage</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
