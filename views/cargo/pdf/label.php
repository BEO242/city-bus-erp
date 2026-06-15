<?php /** @var \CityBus\Core\View $view */ ?>
<style>
  body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #0D1B2A; margin: 0; }
  .header { text-align: center; border-bottom: 2px solid #0D1B2A; padding-bottom: 4mm; margin-bottom: 4mm; }
  .header img { max-height: 12mm; }
  .number { font-size: 14pt; font-weight: bold; letter-spacing: 1px; }
  .qr { text-align: center; margin: 4mm 0; }
  .qr img { width: 30mm; height: 30mm; }
  .row { display: flex; justify-content: space-between; padding: 1.5mm 0; border-bottom: 1px dotted #ccc; }
  .label { color: #666; font-size: 7pt; text-transform: uppercase; }
  .value { font-weight: bold; font-size: 9pt; }
  .route { background: #0D1B2A; color: white; padding: 2mm 3mm; text-align: center; font-weight: bold; margin: 3mm 0; border-radius: 2mm; }
  .total { background: #FFF3E0; padding: 2mm; text-align: right; font-weight: bold; margin-top: 3mm; }
</style>
<div class="header">
  <?php if (!empty($company['logo_base64'])): ?>
    <img src="<?= e($company['logo_base64']) ?>" alt="logo">
  <?php endif ?>
  <p style="margin: 1mm 0; font-weight: bold;"><?= e($company['name'] ?? 'CITY BUS') ?></p>
  <p style="margin: 0; font-size: 7pt; color: #666;">ÉTIQUETTE COLIS</p>
  <p class="number"><?= e($parcel['parcel_number']) ?></p>
</div>

<div class="qr">
  <img src="<?= e($qr) ?>" alt="QR">
</div>

<div class="route">
  <?= e($parcel['origin_agency']) ?> → <?= e($parcel['destination_agency']) ?>
</div>

<div class="row"><span class="label">Type</span><span class="value"><?= e($parcel['parcel_type']) ?></span></div>
<div class="row"><span class="label">Poids</span><span class="value"><?= number_format((float)$parcel['weight_kg'], 2, ',', ' ') ?> kg</span></div>
<div class="row"><span class="label">Pièces</span><span class="value"><?= (int)$parcel['pieces_count'] ?></span></div>

<div style="margin-top: 3mm; padding: 2mm; background: #F5F5F5; border-radius: 2mm;">
  <p class="label">Expéditeur</p>
  <p class="value"><?= e($parcel['sender_name']) ?></p>
  <p style="font-size: 8pt;"><?= e($parcel['sender_phone']) ?></p>
</div>

<div style="margin-top: 2mm; padding: 2mm; background: #FFF8E1; border-radius: 2mm;">
  <p class="label">Destinataire</p>
  <p class="value"><?= e($parcel['recipient_name']) ?></p>
  <p style="font-size: 8pt;"><?= e($parcel['recipient_phone']) ?></p>
</div>

<div class="total">
  <?= e(fcfa((int)$parcel['total_price_fcfa'])) ?>
  <?php if (!$parcel['paid_at_origin']): ?>
    <span style="font-size: 7pt; color: #C62828;">À PAYER</span>
  <?php endif ?>
</div>

<p style="font-size: 6pt; color: #999; text-align: center; margin-top: 3mm;">
  Présenter une pièce d'identité au retrait · <?= e(date('d/m/Y H:i', strtotime((string)$parcel['deposited_at']))) ?>
</p>
