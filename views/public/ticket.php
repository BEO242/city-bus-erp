<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
?>
<?php $view->start('content') ?>
<div class="min-h-screen bg-gradient-to-br from-cb-primary to-cb-secondary p-4">
  <div class="max-w-md mx-auto bg-white rounded-3xl shadow-2xl overflow-hidden">
    <div class="bg-cb-dark text-white p-6 text-center">
      <p class="text-xs uppercase opacity-80">Billet officiel</p>
      <h1 class="text-2xl font-bold font-mono"><?= e($ticket['ticket_number']) ?></h1>
    </div>

    <div class="p-6 space-y-4">
      <div class="text-center">
        <p class="text-3xl font-bold"><?= e($ticket['from_city'] ?? '') ?></p>
        <p class="text-cb-primary text-2xl my-1">→</p>
        <p class="text-3xl font-bold"><?= e($ticket['to_city'] ?? '') ?></p>
      </div>

      <div class="grid grid-cols-2 gap-3 text-center bg-slate-50 rounded-xl p-4">
        <div>
          <p class="text-xs text-slate-500">Date</p>
          <p class="font-bold"><?= e(date('d/m/Y', strtotime((string)$ticket['trip_date']))) ?></p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Départ</p>
          <p class="font-bold"><?= e(substr((string)$ticket['departure_scheduled'], 0, 5)) ?></p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Voyage</p>
          <p class="font-mono text-sm"><?= e($ticket['trip_code']) ?></p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Siège</p>
          <p class="font-bold text-2xl text-cb-primary"><?= e($ticket['seat_number'] ?? '—') ?></p>
        </div>
      </div>

      <div class="text-center">
        <p class="text-xs text-slate-500">Passager</p>
        <p class="font-semibold"><?= e($ticket['passenger_name'] ?? '—') ?></p>
      </div>

      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center">
        <p class="text-xs text-amber-900">Présentez ce QR à l'embarquement</p>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($ticket['qr_code']) ?>" alt="QR" class="mx-auto mt-2">
      </div>

      <div class="grid grid-cols-3 gap-2 text-xs">
        <a href="https://wa.me/?text=<?= $shareText ?>" target="_blank" class="bg-emerald-500 text-white text-center py-3 rounded-xl">WhatsApp</a>
        <a href="<?= e($gcalUrl) ?>" target="_blank" class="bg-blue-500 text-white text-center py-3 rounded-xl">Google Cal</a>
        <a href="<?= e($icsUrl) ?>" class="bg-slate-700 text-white text-center py-3 rounded-xl">.ics</a>
      </div>
    </div>

    <div class="bg-slate-50 p-4 text-center text-xs text-slate-500">
      © CITY BUS · Document non transférable
    </div>
  </div>
</div>
<?php $view->end() ?>
