<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<?php
// ── Couleurs dynamiques depuis ticket_type_configs ──────────────────────────
$headerBg   = $typeConfig['color']      ?? '#1565C0';
$headerFg   = $typeConfig['text_color'] ?? '#FFFFFF';
$typeLabel  = $typeConfig['label']      ?? ucfirst(str_replace('_', ' ', $ticket['ticket_type'] ?? 'Passager'));

// Flags d'affichage depuis ticket_type_configs (défaut = tout afficher)
$cfgShowQr         = !array_key_exists('show_qr', $typeConfig)               || !empty($typeConfig['show_qr']);
$cfgShowContact    = !array_key_exists('show_company_contact', $typeConfig)   || !empty($typeConfig['show_company_contact']);
$cfgShowPhone      = !array_key_exists('show_company_phone', $typeConfig)     || !empty($typeConfig['show_company_phone']);
$cfgShowTrip       = !array_key_exists('show_trip_info', $typeConfig)         || !empty($typeConfig['show_trip_info']);
$cfgShowSeat       = !array_key_exists('show_seat_info', $typeConfig)         || !empty($typeConfig['show_seat_info']);
$cfgShowPrice      = !array_key_exists('show_price_field', $typeConfig)       || !empty($typeConfig['show_price_field']);
$cfgShowPassengerRef = !array_key_exists('show_passenger_reference', $typeConfig) || !empty($typeConfig['show_passenger_reference']);

// Couleur de la route-bar : version légèrement plus sombre
function _darkenHex(string $hex, int $pct): string {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $f = 1 - $pct / 100;
    return sprintf('#%02x%02x%02x', max(0,(int)($r*$f)), max(0,(int)($g*$f)), max(0,(int)($b*$f)));
}
$routeBg  = _darkenHex($headerBg, 15);
$seatBg   = $headerBg;
$borderC  = $headerBg;
// Couleur du badge de type (teinte claire du header)
function _tintHex(string $hex, float $mix): string {   // mix vers blanc
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return sprintf('#%02x%02x%02x',
        (int)($r + (255 - $r) * $mix),
        (int)($g + (255 - $g) * $mix),
        (int)($b + (255 - $b) * $mix)
    );
}
$typeBadgeBg  = _tintHex($headerBg, 0.85);
$typeBadgeFg  = $headerBg;
// Couleur de l'en-tête footer : très légère teinte
$footerBg = _tintHex($headerBg, 0.92);
$footerBorder = _tintHex($headerBg, 0.65);
$routeCodeFg  = _tintHex($headerBg, 0.55);
$routeDateFg  = _tintHex($headerBg, 0.65);
?>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #1a1a2e; background: #fff; }

.ticket {
    width: 148mm;
    min-height: 105mm;
    padding: 0;
    border: 1.5pt solid <?= $borderC ?>;
    border-radius: 6pt;
    overflow: hidden;
}

/* Header */
.header {
    background: <?= $headerBg ?>;
    color: <?= $headerFg ?>;
    padding: 6mm 5mm 5mm 5mm;
}
.header-top { display: flex; justify-content: space-between; align-items: flex-start; }
.company-name { font-size: 15pt; font-weight: bold; letter-spacing: 1pt; }
.company-sub  { font-size: 7pt; opacity: 0.85; margin-top: 1mm; }
.ticket-number-box {
    background: rgba(255,255,255,0.18);
    border-radius: 4pt;
    padding: 2mm 4mm;
    text-align: right;
}
.ticket-number-label { font-size: 6pt; text-transform: uppercase; opacity: 0.8; }
.ticket-number { font-size: 11pt; font-weight: bold; font-family: 'DejaVu Sans Mono', monospace; letter-spacing: 1pt; }

/* Route */
.route-bar {
    background: <?= $routeBg ?>;
    padding: 3mm 5mm;
    display: flex;
    align-items: center;
    gap: 3mm;
}
.route-code { font-size: 9pt; font-weight: bold; color: <?= $routeCodeFg ?>; }
.route-name { font-size: 10pt; font-weight: bold; color: <?= $headerFg ?>; }
.route-date { font-size: 8pt; color: <?= $routeDateFg ?>; margin-left: auto; }

/* Body */
.body { padding: 4mm 5mm; display: table; width: 100%; }
.col-left { display: table-cell; width: 65%; vertical-align: top; }
.col-right { display: table-cell; width: 35%; vertical-align: top; text-align: center; }

.field-group { margin-bottom: 3mm; }
.field-label { font-size: 6.5pt; text-transform: uppercase; color: #607D8B; letter-spacing: 0.5pt; margin-bottom: 0.8mm; }
.field-value { font-size: 10pt; font-weight: bold; color: #1a1a2e; }
.field-value.small { font-size: 8.5pt; font-weight: normal; }

.info-grid { display: table; width: 100%; margin-top: 2mm; }
.info-cell { display: table-cell; width: 50%; vertical-align: top; }

.qr-box { border: 1pt solid #E0E7F0; border-radius: 4pt; padding: 2mm; background: #FAFBFF; }
.qr-code-label { font-size: 6pt; text-transform: uppercase; color: #607D8B; text-align: center; margin-top: 1mm; }

/* Seat badge */
.seat-badge {
    display: inline-block;
    background: <?= $seatBg ?>;
    color: <?= $headerFg ?>;
    border-radius: 4pt;
    padding: 2mm 5mm;
    font-size: 18pt;
    font-weight: bold;
    margin: 2mm 0;
    min-width: 18mm;
    text-align: center;
}
.seat-badge.no-seat { background: #607D8B; font-size: 10pt; padding: 3mm 4mm; }

/* Price */
.price-tag { font-size: 13pt; font-weight: bold; color: #2E7D32; }

/* Divider */
.divider { border-top: 1pt dashed #B0BEC5; margin: 2mm 0; }

/* Type badge */
.type-badge {
    display: inline-block;
    padding: 0.5mm 3mm;
    border-radius: 3pt;
    font-size: 7pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    background: <?= $typeBadgeBg ?>;
    color: <?= $typeBadgeFg ?>;
}

/* Footer */
.footer {
    background: <?= $footerBg ?>;
    border-top: 1pt solid <?= $footerBorder ?>;
    padding: 2mm 5mm;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.footer-text { font-size: 6.5pt; color: #607D8B; }
.status-badge {
    display: inline-block;
    padding: 0.5mm 3mm;
    border-radius: 3pt;
    font-size: 7pt;
    font-weight: bold;
    background: #C8E6C9;
    color: #1B5E20;
}
.status-badge.annule { background: #FFCDD2; color: #B71C1C; }
.status-badge.utilise { background: #E0E0E0; color: #424242; }
</style>
</head>
<body>
<div class="ticket">

  <!-- HEADER -->
  <div class="header">
    <div class="header-top">
      <div>
        <div class="company-name"><?= htmlspecialchars(strtoupper($company['name'] ?? 'CITY BUS'), ENT_QUOTES) ?></div>
        <?php
          $subParts = array_filter([
            $agency['name'] ?? null,
            $agency['city'] ?? ($company['address'] ?? null),
          ]);
        ?>
        <div class="company-sub"><?= htmlspecialchars(implode(' · ', $subParts), ENT_QUOTES) ?></div>
        <?php if (!empty($company['phone']) || !empty($company['email'])): ?>
          <div class="company-sub"><?= htmlspecialchars(trim(($company['phone'] ?? '') . (empty($company['phone']) || empty($company['email']) ? '' : ' · ') . ($company['email'] ?? '')), ENT_QUOTES) ?></div>
        <?php endif ?>
      </div>
      <div class="ticket-number-box">
        <div class="ticket-number-label">N° Ticket</div>
        <div class="ticket-number"><?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES) ?></div>
      </div>
    </div>
  </div>

  <!-- ROUTE BAR -->
  <?php if ($cfgShowTrip): ?>
  <div class="route-bar">
    <span class="route-code"><?= htmlspecialchars($trip['line_code'] ?? '', ENT_QUOTES) ?></span>
    <span class="route-name"><?= htmlspecialchars($trip['line_name'] ?? '', ENT_QUOTES) ?></span>
    <span class="route-date"><?= date('d/m/Y H:i', strtotime($trip['departure_scheduled'] ?? 'now')) ?></span>
  </div>
  <?php endif ?>

  <!-- BODY -->
  <div class="body">
    <div class="col-left">
      <div class="field-group">
        <div class="field-label">Passager</div>
        <div class="field-value"><?= htmlspecialchars($ticket['passenger_name'], ENT_QUOTES) ?></div>
        <?php if (!empty($ticket['passenger_phone'])): ?>
          <div class="field-value small"><?= htmlspecialchars($ticket['passenger_phone'], ENT_QUOTES) ?></div>
        <?php endif ?>
      </div>

      <?php if ($cfgShowTrip): ?>
      <div class="info-grid">
        <div class="info-cell">
          <div class="field-label">Véhicule</div>
          <div class="field-value small"><?= htmlspecialchars($trip['bus_code'] ?? '—', ENT_QUOTES) ?></div>
          <?php if (!empty($trip['bus_plate'])): ?>
            <div class="field-value small"><?= htmlspecialchars($trip['bus_plate'], ENT_QUOTES) ?></div>
          <?php endif ?>
        </div>
        <div class="info-cell">
          <div class="field-label">Date voyage</div>
          <div class="field-value small"><?= date('d/m/Y', strtotime($trip['trip_date'] ?? 'now')) ?></div>
        </div>
      </div>
      <?php endif ?>

      <div class="divider"></div>

      <div class="info-grid">
        <?php if ($cfgShowSeat): ?>
        <div class="info-cell">
          <div class="field-label">Siège</div>
          <?php if (!empty($ticket['seat_number'])): ?>
            <div class="seat-badge"><?= (int)$ticket['seat_number'] ?></div>
          <?php else: ?>
            <div class="seat-badge no-seat">Libre</div>
          <?php endif ?>
        </div>
        <?php endif ?>
        <?php if ($cfgShowPrice): ?>
        <div class="info-cell">
          <div class="field-label">Prix</div>
          <div class="price-tag"><?= number_format((int)$ticket['price_fcfa'], 0, ',', ' ') ?> F</div>
          <div style="margin-top:1mm">
            <?php
              // $typeLabel est déjà calculé en haut depuis typeConfig['label']
            ?>
            <span class="type-badge"><?= htmlspecialchars($typeLabel, ENT_QUOTES) ?></span>
          </div>
        </div>
        <?php endif ?>
      </div>
    </div>

    <div class="col-right">
      <?php if ($cfgShowQr && !empty($qrBase64)): ?>
      <div class="qr-box">
        <img src="data:image/png;base64,<?= $qrBase64 ?>" width="75" height="75" alt="QR">
        <div class="qr-code-label">Scanner pour valider</div>
      </div>
      <?php endif ?>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="footer">
    <div class="footer-text">
      Émis le <?= date('d/m/Y à H:i') ?> · <?= htmlspecialchars($footerText ?? 'Conservez ce ticket pour tout contrôle', ENT_QUOTES) ?>
      <?php
        $legal = array_filter([
          !empty($company['niu'])  ? 'NIU '  . $company['niu']  : null,
          !empty($company['rccm']) ? 'RCCM ' . $company['rccm'] : null,
        ]);
      ?>
      <?php if ($legal): ?>
        <br><span style="font-size:6.5pt; opacity:0.75;"><?= htmlspecialchars(implode(' · ', $legal), ENT_QUOTES) ?></span>
      <?php endif ?>
    </div>
    <?php
      $statusCls = $ticket['status'] === 'annule' ? 'annule' : ($ticket['status'] === 'utilise' ? 'utilise' : '');
      $statusLabel = $ticket['status'] === 'valide' ? 'Valide' : ($ticket['status'] === 'annule' ? 'Annulé' : ($ticket['status'] === 'utilise' ? 'Utilisé' : ucfirst($ticket['status'])));
    ?>
    <span class="status-badge <?= $statusCls ?>"><?= $statusLabel ?></span>
  </div>

</div>
</body>
</html>
