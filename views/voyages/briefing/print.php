<?php /** @var \CityBus\Core\View $view */ ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($title ?? 'Briefing') ?></title>
<style>
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: -apple-system, "Segoe UI", Arial, sans-serif; font-size: 11pt; color: #0f172a; margin: 0; }
  h1 { font-size: 22pt; margin: 0 0 2mm; }
  h2 { font-size: 13pt; margin: 6mm 0 2mm; border-bottom: 1px solid #cbd5e1; padding-bottom: 1mm; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f172a; padding-bottom: 4mm; margin-bottom: 4mm; }
  .meta { font-size: 9pt; color: #64748b; }
  .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4mm; margin: 3mm 0; }
  .box { border: 1px solid #cbd5e1; border-radius: 3mm; padding: 3mm; }
  .box .lbl { font-size: 8pt; text-transform: uppercase; color: #64748b; }
  .box .val { font-weight: bold; font-size: 12pt; margin-top: 1mm; }
  table { width: 100%; border-collapse: collapse; font-size: 9pt; }
  th { text-align: left; background: #f1f5f9; padding: 2mm; font-size: 8pt; text-transform: uppercase; }
  td { padding: 2mm; border-bottom: 1px solid #e2e8f0; }
  .inv { display: grid; grid-template-columns: repeat(5, 1fr); gap: 2mm; }
  .inv .cell { border: 1px solid #cbd5e1; padding: 2mm; text-align: center; border-radius: 2mm; }
  .inv .code { font-size: 16pt; font-weight: bold; }
  .inv .qty { font-size: 12pt; margin-top: 1mm; }
  .signoff { margin-top: 8mm; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6mm; }
  .signoff .sig { border-top: 1px solid #0f172a; padding-top: 2mm; font-size: 9pt; text-align: center; }
  .notes { background: #fef3c7; border: 1px solid #fbbf24; padding: 3mm; border-radius: 2mm; font-size: 10pt; }
  @media print { .no-print { display: none; } }
  .no-print { text-align: right; margin-bottom: 4mm; }
  .no-print button { padding: 2mm 4mm; background: #0f172a; color: #fff; border: 0; border-radius: 2mm; cursor: pointer; }
</style>
</head>
<body>
  <div class="no-print"><button onclick="window.print()">Imprimer</button></div>

  <div class="header">
    <div>
      <div class="meta">BRIEFING VOYAGE</div>
      <h1><?= e($trip['trip_code']) ?></h1>
      <div><?= e($trip['line_code']) ?> · <?= e($trip['departure_city']) ?> → <?= e($trip['arrival_city']) ?></div>
    </div>
    <div style="text-align:right;">
      <div class="meta">DATE</div>
      <div style="font-size:14pt;font-weight:bold;"><?= e(date('d/m/Y', strtotime($trip['trip_date']))) ?></div>
      <div class="meta" style="margin-top:2mm;">DÉPART</div>
      <div style="font-size:14pt;font-weight:bold;"><?= e(substr($trip['departure_time'] ?? '', 0, 5)) ?></div>
    </div>
  </div>

  <div class="grid3">
    <div class="box">
      <div class="lbl">Véhicule</div>
      <div class="val"><?= e($trip['bus_code'] ?? '—') ?></div>
      <div><?= e($trip['bus_plate'] ?? '') ?></div>
      <div class="meta"><?= e(($trip['bus_brand'] ?? '') . ' ' . ($trip['bus_model'] ?? '')) ?> · <?= (int)($trip['bus_seats'] ?? 0) ?> sièges</div>
    </div>
    <div class="box">
      <div class="lbl">Chauffeur</div>
      <div class="val"><?= e($trip['driver_name'] ?? '—') ?></div>
      <div><?= e($trip['driver_phone'] ?? '') ?></div>
      <div class="meta">Permis <?= e($trip['driver_license'] ?? '—') ?></div>
    </div>
    <div class="box">
      <div class="lbl">Receveur</div>
      <div class="val"><?= e($trip['conductor_name'] ?? '—') ?></div>
      <div><?= e($trip['conductor_phone'] ?? '') ?></div>
    </div>
  </div>

  <h2>Inventaire (<?= (int)$totalSold ?>/<?= (int)$totalCap ?>)</h2>
  <div class="inv">
    <?php foreach ($inventory as $inv): ?>
      <div class="cell">
        <div class="code" style="color: <?= e($inv['color_hex'] ?? '#0f172a') ?>"><?= e($inv['class_code']) ?></div>
        <div class="qty"><?= (int)$inv['sold_count'] ?>/<?= (int)$inv['capacity'] ?></div>
      </div>
    <?php endforeach ?>
  </div>

  <h2>Itinéraire</h2>
  <table>
    <thead>
      <tr><th>#</th><th>Arrêt</th><th>Arrivée</th><th>Départ</th><th style="text-align:right">Km</th></tr>
    </thead>
    <tbody>
    <?php foreach ($stops as $i => $s): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><strong><?= e($s['stop_name'] ?? '—') ?></strong></td>
        <td><?= $s['scheduled_arrival'] ? e(date('H:i', strtotime($s['scheduled_arrival']))) : '—' ?></td>
        <td><?= $s['scheduled_departure'] ? e(date('H:i', strtotime($s['scheduled_departure']))) : '—' ?></td>
        <td style="text-align:right"><?= e($s['km_from_start'] ?? '—') ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>

  <?php if (!empty($trip['notes'])): ?>
    <h2>Consignes</h2>
    <div class="notes"><?= nl2br(e($trip['notes'])) ?></div>
  <?php endif ?>

  <div class="signoff">
    <div class="sig">Chauffeur (signature)</div>
    <div class="sig">Receveur (signature)</div>
    <div class="sig">Régulateur (signature)</div>
  </div>

</body>
</html>
