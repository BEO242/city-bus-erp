<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$colors = [
  'hold' => 'bg-amber-100 text-amber-700',
  'confirmed' => 'bg-cb-bg text-cb-primary',
  'paid' => 'bg-emerald-100 text-emerald-700',
  'partially_paid' => 'bg-amber-100 text-amber-700',
  'converted' => 'bg-emerald-100 text-emerald-700',
  'cancelled' => 'bg-rose-100 text-rose-700',
  'expired' => 'bg-slate-100 text-slate-500',
];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Réservations / PNR</h1>
      <p class="text-slate-500 text-sm">Dossiers de réservation avec hold avant paiement.</p>
    </div>
    <?php if (can('reservations.create')): ?>
      <a href="<?= e(url('billetterie/reservations/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white">+ Nouvelle réservation</a>
    <?php endif ?>
  </div>

  <form method="post" action="<?= e(url('billetterie/reservations/lookup')) ?>" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <?= csrf_field() ?>
    <input name="pnr" placeholder="Saisir un code PNR…" class="flex-1 px-3 py-2 rounded-xl border border-slate-200 font-mono uppercase" required>
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Ouvrir</button>
  </form>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <input name="q" value="<?= e($q) ?>" placeholder="PNR, nom, téléphone…" class="flex-1 px-3 py-2 rounded-xl border border-slate-200">
    <select name="status" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="">Tous</option>
      <?php foreach (['hold','confirmed','paid','converted','cancelled','expired'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach ?>
    </select>
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Filtrer</button>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">PNR</th>
          <th class="px-5 py-3 text-left">Contact</th>
          <th class="px-5 py-3 text-center">Pax</th>
          <th class="px-5 py-3 text-right">Total</th>
          <th class="px-5 py-3 text-center">Statut</th>
          <th class="px-5 py-3 text-left">Hold expire</th>
          <th class="px-5 py-3 text-right">Créé</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($reservations as $r): ?>
          <tr>
            <td class="px-5 py-3"><a href="<?= e(url('billetterie/reservations/' . $r['pnr_code'])) ?>" class="font-mono text-cb-primary hover:underline"><?= e($r['pnr_code']) ?></a></td>
            <td class="px-5 py-3"><?= e($r['contact_name']) ?><div class="text-xs text-slate-400"><?= e($r['contact_phone']) ?></div></td>
            <td class="px-5 py-3 text-center"><?= (int)$r['items_count'] ?></td>
            <td class="px-5 py-3 text-right font-semibold"><?= e(fcfa((int)$r['total_amount_fcfa'])) ?></td>
            <td class="px-5 py-3 text-center"><span class="px-2 py-0.5 rounded-full text-xs <?= e($colors[$r['status']] ?? '') ?>"><?= e($r['status']) ?></span></td>
            <td class="px-5 py-3 text-xs"><?= $r['hold_expires_at'] ? e(date('d/m H:i', strtotime((string)$r['hold_expires_at']))) : '—' ?></td>
            <td class="px-5 py-3 text-xs text-right text-slate-500"><?= e(date('d/m H:i', strtotime((string)$r['created_at']))) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (!$reservations): ?>
          <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Aucune réservation</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
