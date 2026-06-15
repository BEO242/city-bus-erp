<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/public'); ?>
<?php $view->start('content') ?>
<div class="max-w-2xl mx-auto">
  <h1 class="text-2xl font-bold text-slate-900 mb-4">Finaliser votre réservation</h1>

  <form method="post" action="<?= e(url('public/booking/submit')) ?>" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="trip_id" value="<?= (int)$trip_id ?>">
    <input type="hidden" name="class" value="<?= e($class) ?>">

    <div class="bg-white rounded-2xl p-5 shadow-soft border border-slate-100">
      <h2 class="font-bold text-slate-900 mb-3">Passagers (<?= $pax ?>)</h2>
      <?php for ($i = 0; $i < $pax; $i++): ?>
        <div class="mb-3">
          <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Passager <?= $i + 1 ?></label>
          <input name="pax_names[]" required placeholder="Nom complet" class="w-full px-3 py-3 rounded-lg border border-slate-300">
        </div>
      <?php endfor ?>
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-soft border border-slate-100">
      <h2 class="font-bold text-slate-900 mb-3">Vos coordonnées</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Nom complet</label>
          <input name="contact_name" required class="w-full px-3 py-3 rounded-lg border border-slate-300">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Téléphone *</label>
          <input name="contact_phone" required placeholder="+242 XX XXX XX XX" class="w-full px-3 py-3 rounded-lg border border-slate-300">
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Email</label>
          <input name="contact_email" type="email" class="w-full px-3 py-3 rounded-lg border border-slate-300">
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-soft border border-slate-100">
      <h2 class="font-bold text-slate-900 mb-3">Mode de paiement</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
        <?php foreach ([
          'AIRTEL_MONEY' => 'Airtel Money',
          'MTN_MOMO'     => 'MTN MoMo',
          'ORANGE_MONEY' => 'Orange Money',
          'CINETPAY'     => 'Carte (CinetPay)',
          'CASH'         => 'Espèces (à l\'agence)',
        ] as $code => $lbl): ?>
          <label class="flex items-center gap-2 p-3 border border-slate-200 rounded-lg cursor-pointer has-[:checked]:bg-cb-primary has-[:checked]:text-white has-[:checked]:border-cb-primary">
            <input type="radio" name="payment_method" value="<?= $code ?>" <?= $code==='CASH'?'checked':'' ?>>
            <span class="text-sm font-semibold"><?= e($lbl) ?></span>
          </label>
        <?php endforeach ?>
      </div>
    </div>

    <button class="w-full px-5 py-4 rounded-2xl bg-cb-primary text-white font-bold text-lg hover:bg-cb-secondary shadow-soft">
      <i data-lucide="lock" class="inline w-5 h-5"></i> Confirmer la réservation
    </button>
  </form>
</div>
<?php $view->end() ?>
