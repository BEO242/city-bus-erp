<?php /** @var \CityBus\Core\View $view */ ?>
<style>
  body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #0D1B2A; }
  h1 { font-size: 14pt; margin: 0; }
  .meta { font-size: 9pt; color: #555; }
  table { width: 100%; border-collapse: collapse; margin-top: 4mm; }
  th { background: #0D1B2A; color: white; padding: 2mm; font-size: 8pt; text-align: left; }
  td { padding: 1.2mm 2mm; border-bottom: 1px solid #eee; font-size: 8pt; }
  .signatures { margin-top: 12mm; display: flex; gap: 10mm; }
  .sig { flex: 1; border-top: 1px solid #999; padding-top: 2mm; font-size: 8pt; text-align: center; }
</style>

<div style="display: flex; justify-content: space-between;">
  <div>
    <?php if (!empty($company['logo_base64'])): ?><img src="<?= e($company['logo_base64']) ?>" style="height: 14mm;"><?php endif ?>
    <h1><?= e($company['name'] ?? 'CITY BUS') ?></h1>
    <p class="meta"><?= e($company['address'] ?? '') ?> · <?= e($company['phone'] ?? '') ?></p>
  </div>
  <div style="text-align: right;">
    <h1>MANIFESTE PASSAGERS</h1>
    <p class="meta">Voyage <?= e($trip['trip_code'] ?? '') ?> · <?= e(date('d/m/Y', strtotime((string)$trip['trip_date']))) ?></p>
    <p class="meta">Ligne <?= e($trip['line_code']) ?> · <?= e($trip['line_name'] ?? '') ?></p>
    <p class="meta">Bus <?= e($trip['bus_code'] ?? '—') ?> · <?= e($trip['plate'] ?? '') ?> · <?= e(($trip['brand'] ?? '') . ' ' . ($trip['model'] ?? '')) ?></p>
    <p class="meta">Départ prévu : <?= e(date('H:i', strtotime((string)$trip['departure_scheduled']))) ?></p>
  </div>
</div>

<h3 style="margin-top: 6mm; font-size: 10pt;">Équipage</h3>
<table>
  <thead><tr><th>Rôle</th><th>Nom</th><th>Matricule</th><th>Téléphone</th></tr></thead>
  <tbody>
    <?php foreach ($crew as $c): ?>
      <tr>
        <td><?= e(strtoupper($c['role'])) ?></td>
        <td><?= e($c['first_name'] . ' ' . $c['last_name']) ?></td>
        <td><?= e($c['matricule']) ?></td>
        <td><?= e($c['phone'] ?? '') ?></td>
      </tr>
    <?php endforeach ?>
    <?php if (!$crew): ?><tr><td colspan="4" style="text-align: center; color: #999;">Aucun équipage saisi</td></tr><?php endif ?>
  </tbody>
</table>

<h3 style="margin-top: 4mm; font-size: 10pt;">Passagers (<?= count($tickets) ?>)</h3>
<table>
  <thead>
    <tr>
      <th style="width: 8mm;">N°</th>
      <th>Siège</th>
      <th>Passager</th>
      <th>Téléphone</th>
      <th>Embarquement</th>
      <th>Descente</th>
      <th>Catégorie</th>
      <th style="width: 14mm;">N° ticket</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($tickets as $i => $t): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td style="font-weight: bold;"><?= e($t['seat_number'] ?? '—') ?></td>
        <td><?= e($t['passenger_name'] ?? '—') ?></td>
        <td><?= e($t['passenger_phone'] ?? '') ?></td>
        <td><?= e($t['boarding_name'] ?? '—') ?></td>
        <td><?= e($t['alighting_name'] ?? '—') ?></td>
        <td><?= e($t['passenger_category'] ?? '') ?></td>
        <td style="font-family: monospace; font-size: 7pt;"><?= e($t['ticket_number']) ?></td>
      </tr>
    <?php endforeach ?>
    <?php if (!$tickets): ?><tr><td colspan="8" style="text-align: center; color: #999; padding: 8mm;">Aucun passager</td></tr><?php endif ?>
  </tbody>
</table>

<div class="signatures">
  <div class="sig">Chauffeur</div>
  <div class="sig">Convoyeur / Agent</div>
  <div class="sig">Responsable agence</div>
</div>

<p style="font-size: 6pt; color: #999; text-align: center; margin-top: 6mm;">
  Document à présenter en cas de contrôle · Édité le <?= e(date('d/m/Y H:i')) ?>
</p>
