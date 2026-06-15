<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/public'); ?>
<?php $view->start('content') ?>
<div class="max-w-2xl mx-auto">
  <?php if ($pnr): ?>
    <div class="bg-emerald-500 text-white rounded-3xl p-8 text-center shadow-lg mb-6">
      <i data-lucide="check-circle-2" class="w-16 h-16 mx-auto mb-3"></i>
      <h1 class="text-3xl font-bold">Réservation confirmée</h1>
      <p class="mt-2 opacity-90">Votre PNR : <strong class="font-mono text-2xl"><?= e($pnr['pnr_code']) ?></strong></p>
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-soft border border-slate-100 mb-4">
      <h2 class="font-bold text-slate-900 mb-3">Détails</h2>
      <?php foreach ($pnr['segments'] as $s): ?>
        <div class="border-l-4 border-cb-primary pl-3 mb-3">
          <div class="font-bold"><?= e($s['from_name']) ?> → <?= e($s['to_name']) ?></div>
          <div class="text-sm text-slate-600">
            <?= e(date('d/m/Y', strtotime($s['trip_date']))) ?> à <?= e(substr($s['departure_time'] ?? '', 0, 5)) ?>
          </div>
          <div class="text-xs text-slate-500">Voyage <?= e($s['trip_code']) ?> · Classe <?= e($s['booking_class']) ?> · <?= number_format((int)$s['price_fcfa']) ?> FCFA</div>
        </div>
      <?php endforeach ?>
      <?php foreach ($pnr['passengers'] as $p): ?>
        <div class="text-sm"><i data-lucide="user" class="inline w-4 h-4"></i> <?= e($p['first_name'] . ' ' . $p['last_name']) ?></div>
      <?php endforeach ?>
      <div class="mt-3 pt-3 border-t border-slate-200 flex justify-between font-bold">
        <span>Total</span>
        <span class="text-cb-primary text-xl"><?= number_format((int)$pnr['total_amount_fcfa']) ?> FCFA</span>
      </div>
    </div>

    <p class="text-center text-sm text-slate-600">Un SMS de confirmation a été envoyé au <?= e($pnr['contact_phone']) ?></p>
  <?php else: ?>
    <div class="bg-white rounded-2xl p-8 text-center shadow-soft border border-slate-100">
      <p>PNR introuvable.</p>
    </div>
  <?php endif ?>
</div>
<?php $view->end() ?>
