<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans', sans-serif; font-size:11pt; color:#1a1a2e; background:#fff; }

.ticket {
    width:148mm;
    min-height:105mm;
    border:1.5pt solid #f59e0b;
    border-radius:6pt;
    overflow:hidden;
}

/* Header ambre */
.header {
    background:#f59e0b;
    color:#fff;
    padding:6mm 5mm 5mm;
}
.header-top { display:flex; justify-content:space-between; align-items:flex-start; }
.company-name { font-size:15pt; font-weight:bold; letter-spacing:1pt; }
.company-sub  { font-size:7pt; opacity:0.9; margin-top:1mm; }
.badge-bagage {
    background:rgba(0,0,0,0.15);
    border-radius:4pt;
    padding:2mm 4mm;
    text-align:center;
}
.badge-label  { font-size:6pt; text-transform:uppercase; opacity:0.85; }
.badge-text   { font-size:10pt; font-weight:bold; letter-spacing:0.5pt; }

/* Bande route */
.route-bar {
    background:#d97706;
    padding:3mm 5mm;
    display:flex;
    align-items:center;
    gap:3mm;
}
.route-code  { font-size:9pt; font-weight:bold; color:#fef3c7; }
.route-name  { font-size:10pt; font-weight:bold; color:#fff; }
.route-date  { font-size:8pt; color:#fde68a; margin-left:auto; }

/* Ticket number */
.ticket-number-bar {
    background:#fffbeb;
    border-bottom:1pt solid #fde68a;
    padding:2mm 5mm;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.tn-label  { font-size:6.5pt; text-transform:uppercase; color:#92400e; letter-spacing:0.5pt; }
.tn-value  { font-size:11pt; font-weight:bold; font-family:'DejaVu Sans Mono',monospace; color:#92400e; }

/* Body */
.body { padding:4mm 5mm; display:table; width:100%; }
.col-left  { display:table-cell; width:63%; vertical-align:top; }
.col-right { display:table-cell; width:37%; vertical-align:top; text-align:center; padding-left:3mm; }

.field-group  { margin-bottom:3mm; }
.field-label  { font-size:6.5pt; text-transform:uppercase; color:#78716c; letter-spacing:0.5pt; margin-bottom:0.8mm; }
.field-value  { font-size:10pt; font-weight:bold; color:#1a1a2e; }
.field-value.small { font-size:8.5pt; font-weight:normal; }

.info-grid { display:table; width:100%; margin-top:2mm; }
.info-cell { display:table-cell; width:50%; vertical-align:top; }

/* QR */
.qr-box   { border:1pt solid #fde68a; border-radius:4pt; padding:2mm; background:#fffbeb; }
.qr-label { font-size:6pt; text-transform:uppercase; color:#92400e; text-align:center; margin-top:1mm; }

/* Nature badge */
.nature-badge {
    display:inline-block;
    background:#fef3c7;
    color:#92400e;
    border:1pt solid #fde68a;
    border-radius:4pt;
    padding:1mm 3mm;
    font-size:8pt;
    font-weight:bold;
    text-transform:uppercase;
    margin-bottom:2mm;
}

/* Prix */
.price-tag { font-size:13pt; font-weight:bold; color:#b45309; }

/* Poids */
.weight-tag {
    display:inline-block;
    background:#f59e0b;
    color:#fff;
    border-radius:4pt;
    padding:2mm 5mm;
    font-size:16pt;
    font-weight:bold;
    text-align:center;
    min-width:20mm;
    margin:2mm 0;
}

.divider { border-top:1pt dashed #d6d3d1; margin:2mm 0; }

/* Footer */
.footer {
    background:#fffbeb;
    border-top:1pt solid #fde68a;
    padding:2mm 5mm;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.footer-text { font-size:6pt; color:#a16207; }
.footer-bold { font-weight:bold; }
</style>
</head>
<body>
<div class="ticket">

  <!-- Header -->
  <div class="header">
    <div class="header-top">
      <div>
        <div class="company-name"><?= e($agency['name'] ?? 'CITY BUS') ?></div>
        <div class="company-sub">BILLET BAGAGE EXCÉDENTAIRE</div>
      </div>
      <div class="badge-bagage">
        <div class="badge-label">Nature</div>
        <div class="badge-text"><?= e($ticket['nature_label'] ?? '—') ?></div>
      </div>
    </div>
  </div>

  <!-- Bande route -->
  <div class="route-bar">
    <span class="route-code"><?= e($ticket['line_code']) ?></span>
    <span class="route-name"><?= e($ticket['line_name']) ?></span>
    <span class="route-date">
      <?= e(date('d/m/Y', strtotime($ticket['trip_date']))) ?>
      &nbsp;<?= e(date('H:i', strtotime($ticket['departure_scheduled']))) ?>
    </span>
  </div>

  <!-- Numéro billet -->
  <div class="ticket-number-bar">
    <span class="tn-label">N° Billet bagage</span>
    <span class="tn-value"><?= e($ticket['ticket_number']) ?></span>
  </div>

  <!-- Corps -->
  <div class="body">
    <div class="col-left">

      <div class="field-group">
        <div class="field-label">Propriétaire</div>
        <div class="field-value"><?= e($ticket['passenger_name']) ?></div>
        <?php if ($ticket['passenger_phone']): ?>
          <div class="field-value small"><?= e($ticket['passenger_phone']) ?></div>
        <?php endif ?>
      </div>

      <?php if ($ticket['description']): ?>
        <div class="field-group">
          <div class="field-label">Description</div>
          <div class="field-value small"><?= e($ticket['description']) ?></div>
        </div>
      <?php endif ?>

      <div class="info-grid">
        <div class="info-cell">
          <div class="field-label">Poids</div>
          <div class="weight-tag"><?= number_format((float)$ticket['weight_kg'], 1) ?>&nbsp;kg</div>
        </div>
        <div class="info-cell">
          <div class="field-label">Total perçu</div>
          <div class="price-tag" style="margin-top:2mm"><?= fcfa((int)$ticket['total_price_fcfa']) ?></div>
          <?php if ((int)$ticket['base_fee_fcfa'] > 0 || (int)$ticket['volume_surcharge_fcfa'] > 0): ?>
            <div style="font-size:6.5pt;color:#a16207;margin-top:1mm">
              <?php if ((int)$ticket['base_fee_fcfa'] > 0): ?>
                Fixe: <?= fcfa((int)$ticket['base_fee_fcfa']) ?><br>
              <?php endif ?>
              <?php if ((int)$ticket['weight_fee_fcfa'] > 0): ?>
                Poids: <?= fcfa((int)$ticket['weight_fee_fcfa']) ?><br>
              <?php endif ?>
              <?php if ((int)$ticket['volume_surcharge_fcfa'] > 0): ?>
                Gabarit: <?= fcfa((int)$ticket['volume_surcharge_fcfa']) ?>
              <?php endif ?>
            </div>
          <?php endif ?>
        </div>
      </div>

      <?php if ($ticket['length_cm'] || $ticket['width_cm'] || $ticket['height_cm']): ?>
        <div class="divider"></div>
        <div class="field-group">
          <div class="field-label">Dimensions</div>
          <div class="field-value small">
            <?= implode(' × ', array_filter([
              $ticket['length_cm'] ? $ticket['length_cm'] . 'cm' : null,
              $ticket['width_cm']  ? $ticket['width_cm']  . 'cm' : null,
              $ticket['height_cm'] ? $ticket['height_cm'] . 'cm' : null,
            ])) ?>
          </div>
        </div>
      <?php endif ?>

    </div>
    <div class="col-right">
      <div class="qr-box">
        <img src="data:image/png;base64,<?= $qrBase64 ?>" alt="QR" width="80">
      </div>
      <div class="qr-label">Scanner pour vérifier</div>
      <?php if ($ticket['passenger_ticket_number'] ?? null): ?>
        <div style="margin-top:3mm;font-size:6.5pt;color:#78716c;">
          <div style="text-transform:uppercase;letter-spacing:0.5pt">Billet passager lié</div>
          <div style="font-weight:bold;font-family:'DejaVu Sans Mono',monospace;font-size:7pt">
            <?= e($ticket['passenger_ticket_number']) ?>
          </div>
        </div>
      <?php endif ?>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    <span class="footer-text">Émis le <?= e(date('d/m/Y H:i', strtotime($ticket['sold_at']))) ?></span>
    <span class="footer-text footer-bold">Ce billet doit accompagner le bagage en tout temps</span>
    <span class="footer-text"><?= e($agency['phone'] ?? '') ?></span>
  </div>

</div>
</body>
</html>
