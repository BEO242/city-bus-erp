<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('billetterie/reservations')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <div class="flex items-start justify-between mt-2 flex-wrap gap-3">
      <div>
        <h1 class="text-3xl font-mono font-bold text-cb-primary"><?= e($reservation['pnr_code']) ?></h1>
        <p class="text-slate-500 text-sm"><?= e($reservation['contact_name']) ?> · <?= e($reservation['contact_phone']) ?></p>
      </div>
      <span class="px-3 py-1 rounded-full text-sm font-medium bg-cb-bg text-cb-primary"><?= e($reservation['status']) ?></span>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    <div class="md:col-span-2 bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100"><h2 class="font-semibold">Passagers (<?= count($items) ?>)</h2></div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
          <tr>
            <th class="px-3 py-2 text-left">Voyage</th>
            <th class="px-3 py-2 text-left">Passager</th>
            <th class="px-3 py-2 text-center">Siège</th>
            <th class="px-3 py-2 text-right">Prix</th>
            <th class="px-3 py-2 text-left">Ticket</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($items as $i): ?>
          <tr>
            <td class="px-3 py-2"><?= e($i['line_code']) ?> · <?= e($i['trip_code']) ?><div class="text-xs text-slate-400"><?= e(date('d/m/Y', strtotime((string)$i['trip_date']))) ?></div></td>
            <td class="px-3 py-2"><?= e($i['passenger_name']) ?><div class="text-xs text-slate-400"><?= e($i['passenger_phone'] ?? '') ?></div></td>
            <td class="px-3 py-2 text-center font-bold"><?= e($i['seat_number'] ?? '—') ?></td>
            <td class="px-3 py-2 text-right"><?= e(fcfa((int)$i['price_fcfa'])) ?></td>
            <td class="px-3 py-2 text-xs"><?= $i['converted_ticket_id'] ? '<span class="text-emerald-600">✓ #' . (int)$i['converted_ticket_id'] . '</span>' : '<span class="text-slate-400">—</span>' ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <div class="space-y-4">
      <div class="bg-cb-primary text-white rounded-2xl p-6 shadow-soft">
        <p class="text-xs uppercase opacity-80">Total</p>
        <p class="text-3xl font-bold"><?= e(fcfa((int)$reservation['total_amount_fcfa'])) ?></p>
        <?php if ($reservation['paid_amount_fcfa'] > 0): ?>
          <p class="text-sm opacity-90 mt-2">Payé : <?= e(fcfa((int)$reservation['paid_amount_fcfa'])) ?></p>
        <?php endif ?>
        <?php if ($reservation['hold_expires_at'] && $reservation['status'] === 'hold'): ?>
          <p class="text-sm opacity-90 mt-2">⏱ Expire : <?= e(date('d/m H:i', strtotime((string)$reservation['hold_expires_at']))) ?></p>
        <?php endif ?>
      </div>

      <?php if (in_array($reservation['status'], ['hold','confirmed','partially_paid'], true)): ?>
        <?php if (can('reservations.confirm')): ?>
        <form method="post" action="<?= e(url('billetterie/reservations/' . $reservation['pnr_code'] . '/convert')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-3">
          <?= csrf_field() ?>
          <h3 class="font-semibold text-slate-900">Convertir en billets</h3>
          <select name="payment_method" class="w-full px-3 py-2 rounded-xl border border-slate-200">
            <option value="especes">Espèces</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="carte">Carte bancaire</option>
            <option value="virement">Virement</option>
          </select>
          <button class="w-full px-4 py-2.5 rounded-xl bg-emerald-600 text-white">Encaisser & émettre les billets</button>
        </form>
        <?php endif ?>

        <?php if (can('reservations.confirm') && $reservation['status'] === 'hold'): ?>
        <form method="post" action="<?= e(url('billetterie/reservations/' . $reservation['pnr_code'] . '/confirm')) ?>" class="bg-white rounded-2xl border border-slate-100 p-4 shadow-soft">
          <?= csrf_field() ?>
          <button class="w-full px-4 py-2 rounded-xl border border-cb-primary text-cb-primary hover:bg-cb-bg/40">Confirmer (sans paiement)</button>
        </form>
        <?php endif ?>

        <?php if (can('reservations.cancel')): ?>
        <details class="bg-white rounded-2xl border border-slate-100 p-4 shadow-soft">
          <summary class="cursor-pointer font-semibold text-rose-700">Annuler</summary>
          <form method="post" action="<?= e(url('billetterie/reservations/' . $reservation['pnr_code'] . '/cancel')) ?>" class="space-y-2 mt-3">
            <?= csrf_field() ?>
            <textarea name="reason" placeholder="Motif (5 car. min)" required rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
            <button class="w-full px-4 py-2 rounded-xl bg-rose-600 text-white">Annuler la réservation</button>
          </form>
        </details>
        <?php endif ?>
      <?php endif ?>
    </div>
  </div>
</div>
<?php $view->end() ?>
