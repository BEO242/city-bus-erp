<?php /** @var \CityBus\Core\View $view */ ?>
<style>
  body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #0D1B2A; }
  h1 { font-size: 14pt; margin: 0; color: #0D1B2A; }
  .meta { margin-top: 2mm; font-size: 9pt; color: #555; }
  table { width: 100%; border-collapse: collapse; margin-top: 5mm; }
  th { background: #0D1B2A; color: white; padding: 2mm; text-align: left; font-size: 8pt; }
  td { padding: 1.5mm 2mm; border-bottom: 1px solid #eee; font-size: 8pt; vertical-align: top; }
  .totals { margin-top: 5mm; background: #FFF3E0; padding: 3mm; }
  .signatures { margin-top: 12mm; display: flex; gap: 10mm; }
  .sig-box { flex: 1; border-top: 1px solid #999; padding-top: 2mm; font-size: 8pt; text-align: center; }
</style>

<div style="display: flex; justify-content: space-between; align-items: center;">
  <div>
    <?php if (!empty($company['logo_base64'])): ?>
      <img src="<?= e($company['logo_base64']) ?>" style="height: 15mm;">
    <?php endif ?>
    <h1><?= e($company['name'] ?? 'CITY BUS') ?></h1>
    <p style="margin: 1mm 0 0; font-size: 8pt; color: #666;"><?= e($company['address'] ?? '') ?> · <?= e($company['phone'] ?? '') ?></p>
  </div>
  <div style="text-align: right;">
    <h1>MANIFESTE FRET</h1>
    <p class="meta">Voyage <?= e($trip['trip_code'] ?? '') ?> · <?= e(date('d/m/Y', strtotime((string)$trip['trip_date']))) ?></p>
    <p class="meta">Véhicule <?= e($trip['bus_code'] ?? '—') ?> · <?= e($trip['plate'] ?? '') ?></p>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>N°</th>
      <th>Expéditeur</th>
      <th>Destinataire</th>
      <th>Trajet</th>
      <th style="text-align:right;">Poids</th>
      <th style="text-align:right;">Prix</th>
      <th>Statut paiement</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($parcels as $p): ?>
    <tr>
      <td><strong><?= e($p['parcel_number']) ?></strong><br><span style="font-size:7pt;color:#666;"><?= e($p['parcel_type']) ?></span></td>
      <td><?= e($p['sender_name']) ?><br><span style="font-size:7pt;color:#666;"><?= e($p['sender_phone']) ?></span></td>
      <td><?= e($p['recipient_name']) ?><br><span style="font-size:7pt;color:#666;"><?= e($p['recipient_phone']) ?></span></td>
      <td><?= e($p['origin_agency']) ?><br>→ <?= e($p['destination_agency']) ?></td>
      <td style="text-align:right;"><?= number_format((float)$p['weight_kg'], 2, ',', ' ') ?> kg</td>
      <td style="text-align:right;"><strong><?= e(fcfa((int)$p['total_price_fcfa'])) ?></strong></td>
      <td><?= $p['paid_at_origin'] ? '<span style="color:#2E7D32;">PAYÉ</span>' : '<span style="color:#C62828;">À DESTINATION</span>' ?></td>
    </tr>
  <?php endforeach ?>
  <?php if (!$parcels): ?>
    <tr><td colspan="7" style="text-align:center; padding: 8mm; color: #999;">Aucun colis sur ce voyage.</td></tr>
  <?php endif ?>
  </tbody>
</table>

<div class="totals">
  <table style="margin: 0;">
    <tr>
      <td><strong>Total colis :</strong> <?= (int)$totals['count'] ?></td>
      <td style="text-align:right;"><strong>Poids total :</strong> <?= number_format((float)$totals['weight'], 2, ',', ' ') ?> kg</td>
      <td style="text-align:right;"><strong>Valeur déclarée :</strong> <?= e(fcfa((int)$totals['declared'])) ?></td>
      <td style="text-align:right;"><strong>Recettes :</strong> <?= e(fcfa((int)$totals['revenue'])) ?></td>
    </tr>
  </table>
</div>

<div class="signatures">
  <div class="sig-box">Chauffeur</div>
  <div class="sig-box">Convoyeur</div>
  <div class="sig-box">Responsable agence</div>
</div>
