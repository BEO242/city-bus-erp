<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$statusColors = [
  'depose'    => 'bg-slate-100 text-slate-700',
  'en_transit'=> 'bg-cb-bg text-cb-primary',
  'arrive'    => 'bg-emerald-50 text-emerald-700',
  'retire'    => 'bg-slate-100 text-slate-500',
  'perdu'     => 'bg-rose-100 text-rose-700',
  'endommage' => 'bg-orange-100 text-orange-700',
  'retourne'  => 'bg-amber-100 text-amber-700',
];
$eventLabels = [
  'depose' => 'Dépôt', 'charge' => 'Chargement', 'depart' => 'Départ',
  'arrivee_etape' => 'Arrivée étape', 'arrivee_destination' => 'Arrivée destination',
  'retrait' => 'Retrait', 'litige' => 'Litige', 'retour' => 'Retour', 'annule' => 'Annulation', 'message' => 'Note',
];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('cargo/parcels')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <div class="flex items-start justify-between mt-2 flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 font-mono"><?= e($parcel['parcel_number']) ?></h1>
        <p class="text-slate-500 text-sm"><?= e($types[$parcel['parcel_type']] ?? '') ?> · <?= e($parcel['origin_agency']) ?> → <?= e($parcel['destination_agency']) ?></p>
      </div>
      <div class="flex items-center gap-2">
        <span class="px-3 py-1 rounded-full text-sm font-medium <?= e($statusColors[$parcel['status']] ?? 'bg-slate-100') ?>">
          <?= e($statuses[$parcel['status']] ?? $parcel['status']) ?>
        </span>
        <a href="<?= e(url('cargo/parcels/' . $parcel['id'] . '/label')) ?>" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-sm hover:bg-slate-700">
          <i data-lucide="qr-code" class="w-3 h-3 inline"></i> Étiquette PDF
        </a>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Détails -->
    <div class="lg:col-span-2 space-y-5">
      <div class="grid grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
          <p class="text-xs uppercase text-slate-400 mb-2">Expéditeur</p>
          <p class="font-semibold"><?= e($parcel['sender_name']) ?></p>
          <p class="text-sm text-slate-500"><?= e($parcel['sender_phone']) ?></p>
          <?php if ($parcel['sender_id_doc']): ?><p class="text-xs text-slate-400">Pièce : <?= e($parcel['sender_id_doc']) ?></p><?php endif ?>
          <?php if ($parcel['sender_address']): ?><p class="text-xs text-slate-400 mt-1"><?= e($parcel['sender_address']) ?></p><?php endif ?>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
          <p class="text-xs uppercase text-slate-400 mb-2">Destinataire</p>
          <p class="font-semibold"><?= e($parcel['recipient_name']) ?></p>
          <p class="text-sm text-slate-500"><?= e($parcel['recipient_phone']) ?></p>
          <?php if ($parcel['recipient_id_doc']): ?><p class="text-xs text-slate-400">Pièce : <?= e($parcel['recipient_id_doc']) ?></p><?php endif ?>
          <?php if ($parcel['recipient_address']): ?><p class="text-xs text-slate-400 mt-1"><?= e($parcel['recipient_address']) ?></p><?php endif ?>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
        <h2 class="font-semibold text-slate-900 mb-3">Caractéristiques</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div><p class="text-xs text-slate-400">Poids</p><p class="font-semibold"><?= number_format((float)$parcel['weight_kg'], 2, ',', ' ') ?> kg</p></div>
          <div><p class="text-xs text-slate-400">Pièces</p><p class="font-semibold"><?= (int)$parcel['pieces_count'] ?></p></div>
          <div><p class="text-xs text-slate-400">Volume</p><p class="font-semibold"><?= $parcel['volume_m3'] ? number_format((float)$parcel['volume_m3'], 3, ',', ' ') . ' m³' : '—' ?></p></div>
          <div><p class="text-xs text-slate-400">Valeur déclarée</p><p class="font-semibold"><?= e(fcfa((int)$parcel['declared_value_fcfa'])) ?></p></div>
        </div>
        <p class="text-xs text-slate-400 mt-4">Description</p>
        <p class="text-slate-700"><?= e($parcel['description']) ?></p>
        <?php if ($parcel['notes']): ?>
          <p class="text-xs text-slate-400 mt-3">Notes</p>
          <p class="text-sm text-slate-700 whitespace-pre-line"><?= e($parcel['notes']) ?></p>
        <?php endif ?>
      </div>

      <!-- Timeline -->
      <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
        <h2 class="font-semibold text-slate-900 mb-4">Suivi</h2>
        <div class="space-y-3">
          <?php foreach ($timeline as $ev): ?>
            <div class="flex gap-3 pb-3 border-b border-slate-100 last:border-0">
              <div class="w-2 h-2 rounded-full bg-cb-primary mt-2"></div>
              <div class="flex-1">
                <p class="text-sm font-medium"><?= e($eventLabels[$ev['event_type']] ?? $ev['event_type']) ?></p>
                <?php if ($ev['description']): ?><p class="text-sm text-slate-600"><?= e($ev['description']) ?></p><?php endif ?>
                <p class="text-xs text-slate-400">
                  <?= e(date('d/m/Y H:i', strtotime((string)$ev['occurred_at']))) ?>
                  <?php if ($ev['actor_name']): ?> · par <?= e(trim((string)$ev['actor_name'])) ?><?php endif ?>
                  <?php if ($ev['location']): ?> · <?= e($ev['location']) ?><?php endif ?>
                </p>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="space-y-5">
      <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
        <h2 class="font-semibold text-slate-900 mb-3">Tarification</h2>
        <div class="space-y-1 text-sm">
          <div class="flex justify-between"><span class="text-slate-500">Prix de base</span><span><?= e(fcfa((int)$parcel['base_price_fcfa'])) ?></span></div>
          <div class="flex justify-between"><span class="text-slate-500">Assurance</span><span><?= e(fcfa((int)$parcel['insurance_fee_fcfa'])) ?></span></div>
          <div class="flex justify-between"><span class="text-slate-500">Taxes</span><span><?= e(fcfa((int)$parcel['tax_amount_fcfa'])) ?></span></div>
          <div class="flex justify-between font-bold pt-2 border-t border-slate-200">
            <span>Total</span><span><?= e(fcfa((int)$parcel['total_price_fcfa'])) ?></span>
          </div>
          <div class="text-xs text-slate-400 mt-2">
            Paiement : <?= e($methods[$parcel['payment_method']] ?? $parcel['payment_method']) ?>
            <?php if (!$parcel['paid_at_origin']): ?><span class="text-amber-600">· à destination</span><?php endif ?>
          </div>
        </div>
      </div>

      <?php if ($parcel['status'] === 'depose' && can('cargo.edit')): ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
          <h3 class="font-semibold text-slate-900 mb-3">Charger sur un voyage</h3>
          <form method="post" action="<?= e(url('cargo/parcels/' . $parcel['id'] . '/load')) ?>" class="space-y-2">
            <?= csrf_field() ?>
            <input type="number" name="trip_id" placeholder="ID du voyage" required class="w-full px-3 py-2 rounded-xl border border-slate-200">
            <button class="w-full px-4 py-2 rounded-xl bg-cb-primary text-white hover:bg-cb-secondary">Charger</button>
          </form>
        </div>
      <?php endif ?>

      <?php if ($parcel['status'] === 'en_transit' && can('cargo.edit')): ?>
        <form method="post" action="<?= e(url('cargo/parcels/' . $parcel['id'] . '/arrive')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
          <?= csrf_field() ?>
          <button class="w-full px-4 py-2.5 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">Marquer comme arrivé</button>
          <p class="text-xs text-slate-400 mt-2 text-center">Un SMS sera envoyé au destinataire</p>
        </form>
      <?php endif ?>

      <?php if ($parcel['status'] === 'arrive' && can('cargo.pickup')): ?>
        <form method="post" action="<?= e(url('cargo/parcels/' . $parcel['id'] . '/pickup')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-3">
          <?= csrf_field() ?>
          <h3 class="font-semibold text-slate-900">Remise au destinataire</h3>
          <input name="pickup_recipient_name" placeholder="Nom de la personne (si différent)" class="w-full px-3 py-2 rounded-xl border border-slate-200">
          <input name="pickup_id_doc" placeholder="N° pièce d'identité" class="w-full px-3 py-2 rounded-xl border border-slate-200">
          <textarea name="pickup_notes" rows="2" placeholder="Notes" class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
          <button class="w-full px-4 py-2.5 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">Confirmer le retrait</button>
        </form>
      <?php endif ?>

      <?php if (in_array($parcel['status'], ['depose','en_transit','arrive'], true)): ?>
        <details class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
          <summary class="cursor-pointer font-semibold text-slate-900">Signaler un litige</summary>
          <form method="post" action="<?= e(url('cargo/parcels/' . $parcel['id'] . '/issue')) ?>" class="space-y-2 mt-3">
            <?= csrf_field() ?>
            <select name="issue_type" class="w-full px-3 py-2 rounded-xl border border-slate-200">
              <option value="perdu">Perdu</option>
              <option value="endommage">Endommagé</option>
            </select>
            <textarea name="description" rows="2" placeholder="Description du problème" required class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
            <button class="w-full px-4 py-2 rounded-xl bg-rose-600 text-white hover:bg-rose-700">Signaler</button>
          </form>
        </details>

        <?php if (can('cargo.delete')): ?>
        <details class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
          <summary class="cursor-pointer font-semibold text-slate-900 text-rose-700">Annuler le colis</summary>
          <form method="post" action="<?= e(url('cargo/parcels/' . $parcel['id'] . '/cancel')) ?>" class="space-y-2 mt-3" onsubmit="return confirm('Annuler définitivement ce colis ?')">
            <?= csrf_field() ?>
            <textarea name="reason" rows="2" placeholder="Motif d'annulation (min 5 car.)" required class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
            <button class="w-full px-4 py-2 rounded-xl bg-rose-600 text-white hover:bg-rose-700">Annuler le colis</button>
          </form>
        </details>
        <?php endif ?>
      <?php endif ?>

      <?php if ($parcel['status'] === 'retire'): ?>
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6">
          <p class="text-sm font-semibold text-emerald-900">✓ Retiré</p>
          <p class="text-xs text-slate-600 mt-1">Le <?= e(date('d/m/Y H:i', strtotime((string)$parcel['picked_up_at']))) ?></p>
          <?php if ($parcel['pickup_recipient_name']): ?>
            <p class="text-xs text-slate-600">Par : <?= e($parcel['pickup_recipient_name']) ?></p>
          <?php endif ?>
        </div>
      <?php endif ?>
    </div>
  </div>
</div>
<?php $view->end() ?>
