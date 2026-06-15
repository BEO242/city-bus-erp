<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<?php
/**
 * Template mPDF — Lot de tickets pré-imprimés City Bus
 * Variables : $tickets, $batch_id, $trip (optional), $generated, $typeConfigs (optional)
 *
 * Disposition (fidèle aux modèles physiques) :
 *   [Talon 1] [Talon 2] [Billet principal] [Stub agence]  — 1 rangée par ticket
 */

$defaultColors = [
    'passage_arret'   => ['bg' => '#C62828', 'text' => '#FFFFFF', 'label' => 'Arrêt anticipé'],
    'passage_final'   => ['bg' => '#1A237E', 'text' => '#FFFFFF', 'label' => 'Destination finale'],
    'bagage_excedent' => ['bg' => '#F57C00', 'text' => '#FFFFFF', 'label' => 'Bagages excédentaires'],
    'bagage_inclus'   => ['bg' => '#1A237E', 'text' => '#FFFFFF', 'label' => 'Bagages inclus'],
    'talon_arret'     => ['bg' => '#C62828', 'text' => '#FFFFFF', 'label' => 'Talon arrêt anticipé'],
];
if (!empty($typeConfigs)) {
    foreach ($typeConfigs as $k => $tc) {
        $defaultColors[$k] = ['bg' => $tc['color'], 'text' => $tc['text_color'], 'label' => $tc['label']];
    }
}
function ppCol(array $defaultColors, string $type): array {
    return $defaultColors[$type] ?? $defaultColors['passage_final'];
}
function ppE(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$tripDate   = !empty($trip['trip_date'])            ? date('d/m/Y', strtotime($trip['trip_date'])) : '';
$tripHeure  = !empty($trip['departure_scheduled'])  ? substr($trip['departure_scheduled'], 0, 5)   : '';
$tripDepart = $trip['departure_city'] ?? '';
$tripDest   = $trip['arrival_city']   ?? '';
$lineName   = $trip['line_name']      ?? '';
$tripCode   = $trip['trip_code']      ?? '';
$busCode    = $trip['bus_code']       ?? '';
$batchShort    = substr($batch_id, 0, 8);
// Identité société transmise par PdfService (depuis app_settings)
$appLogo       = $logo_base64   ?? null;
$companyName   = $company_name  ?? 'City Bus';
$companyPhone  = $company_phone ?? '+242 06 778 32 13 / +242 05 689 91 21';
$companyEmail  = $company_email ?? 'Citybusreservation@gmail.com';
$companySlogan = 'Confort et sécurité le long de votre trajet';
$companyServices = 'Transport · Assistance voyage · Expédition colis';

// Preprint type — billet (default), talon_bagage, talon_colis
$preprintType = $preprint_type ?? ($tickets[0]['preprint_type'] ?? 'billet');
$isTalon      = in_array($preprintType, ['talon_bagage', 'talon_colis'], true);
$talonLabel   = $preprintType === 'talon_bagage' ? 'TALON BAGAGE' : 'TALON COLIS';
$talonColor   = $preprintType === 'talon_bagage' ? '#0D47A1' : '#4A148C';
?>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
@page { margin: 10mm 8mm; }
body { font-family:'DejaVu Sans',sans-serif; font-size:7.5pt; color:#111; background:#fff; }

/* ── En-tête de lot (1 seule fois, page 1) ──────────────────────── */
.page-header { width:100%; border-bottom:1.5pt solid #C62828; padding-bottom:2mm; margin-bottom:3mm; }
.ph-title { font-size:11pt; font-weight:bold; color:#C62828; letter-spacing:0.3pt; }
.ph-sub   { font-size:6pt; color:#555; }

/* ── Mise en page A4 portrait ──────────────────────────────────────
   Utile : 194mm × 277mm (marges 8mm G/D, 10mm H/B)
   En-tête lot : ~13mm  →  Disponible tickets : ~264mm
   Colis 4 sections : 4 × 62mm + 3 × 2mm =  254mm  (4/page) ✓
   Talon arrêt 3 sections : 3 × 80mm + 2 × 2mm =  244mm  (3/page) ✓
   ─────────────────────────────────────────────────────────────── */
.ticket-table  { width:100%; border-collapse:collapse; margin-bottom:0; page-break-inside:avoid; }
.ticket-table td { vertical-align:top; padding:0; overflow:hidden; }
.col-t1 { width:11%; }  /* 21.3mm */
.col-t2 { width:11%; }  /* 21.3mm */
.col-bi { width:63%; }  /* 122mm  */
.col-st { width:15%; }  /* 29mm   */
.perf   { border-right:0.8pt dashed #aaa; }

/* Talon */
.talon-box { padding:2mm 1.5mm; height:62mm; overflow:hidden; }
.t-logo    { font-size:9pt; font-weight:bold; line-height:1.1; }
.t-phone   { font-size:5pt; line-height:1.5; margin-top:1mm; opacity:0.88; }
.t-num     { font-size:5pt; margin-top:2mm; line-height:1.6; }
.t-merci   { font-size:4.5pt; margin-top:2mm; font-style:italic; opacity:0.75; }

/* Billet */
.billet-box { height:62mm; overflow:hidden; }
.bi-head    { padding:1.5mm 2.5mm; display:table; width:100%; }
.bi-hl      { display:table-cell; vertical-align:middle; }
.bi-hr      { display:table-cell; vertical-align:middle; text-align:right; width:42%; }
.bi-company { font-size:10pt; font-weight:bold; }
.bi-slogan  { font-size:5pt; opacity:0.85; }
.bi-contact { font-size:4.5pt; line-height:1.5; opacity:0.9; }
.bi-email   { font-size:4pt; opacity:0.75; }
.bi-band    { text-align:center; font-weight:bold; font-size:6.5pt;
              letter-spacing:1.5pt; text-transform:uppercase; padding:0.8mm 2mm; }
.bi-body    { display:table; width:100%; padding:1.5mm 2mm; }
.bi-c1      { display:table-cell; width:54%; vertical-align:top; padding-right:1.5mm; }
.bi-c2      { display:table-cell; width:46%; vertical-align:top; }
.field      { margin-bottom:1mm; font-size:6pt; }
.fk         { font-size:5pt; color:#555; text-transform:uppercase; }
.fv         { font-weight:bold; }
.fline      { border-bottom:0.5pt solid #999; display:block; min-width:18mm; height:3.5mm; }
.info-box   { border:0.5pt solid #bbb; border-radius:2pt; padding:1.5mm; font-size:5pt; }
.ib-row     { display:table; width:100%; margin-bottom:0.8mm; }
.ib-cell    { display:table-cell; }
.ib-label   { font-size:4.5pt; color:#777; text-transform:uppercase; }
.ib-val     { font-weight:bold; font-size:6pt; border-bottom:0.5pt solid #ddd;
              display:block; min-width:10mm; height:3.5mm; overflow:hidden; }
.bi-foot    { padding:0.8mm 2mm; font-size:4.5pt; color:#888; }

/* Stub */
.stub-box { padding:1.5mm 1mm; height:62mm; overflow:hidden; position:relative; }
.stub-wm  { font-size:5.5pt; line-height:1.4; letter-spacing:1.5pt;
            word-break:break-all; opacity:0.15; margin-bottom:1mm; }
.stub-logo  { font-size:9pt; font-weight:bold; text-align:right; }
.stub-type  { font-size:4.5pt; text-align:right; text-transform:uppercase;
              letter-spacing:0.5pt; margin-top:0.5mm; opacity:0.85; }
.stub-agence { font-size:5pt; margin-top:2mm; }

.row-sep { border-top:0.5pt dashed #ccc; height:2mm; margin:0; }
.page-break { page-break-after:always; }

.bi-qr  { display:table-cell; vertical-align:middle; padding-left:1.5mm; width:14mm; }
.bi-qr-ph { width:12mm; height:12mm; border:0.7pt dashed #bbb; display:table;
            text-align:center; font-size:5pt; color:#bbb; }

/* ── Colis : corps billet (TALON N° + QR | champs) ──────────────── */
.bi-body-colis { display:table; width:100%; padding:1.5mm 2mm; }
.bi-talon-col  { display:table-cell; width:36%; vertical-align:top;
                 border-right:0.5pt solid #ccc; padding-right:2mm; }
.bi-fields-col { display:table-cell; width:64%; vertical-align:top; padding-left:2mm; }
.bi-tn-qr      { margin-top:2mm; text-align:center; }
.bi-qr-img     { display:block; margin:0 auto; }

/* ── Talon arrêt : corps billet avec zone QR dédiée ─────────────────── */
.pb-body-wrap  { display:table; width:100%; }
.pb-fields     { display:table-cell; width:75%; vertical-align:top; padding:1.5mm 2.5mm; }
.pb-qr-zone    { display:table-cell; width:25%; vertical-align:middle; text-align:center;
                 border-left:0.5pt solid #ddd; padding-left:1.5mm; }
.bi-tn { font-size:7.5pt; font-weight:bold; line-height:1.4; word-break:break-all; }
.bi-td { font-size:5.5pt; color:#555; margin-top:1.5mm; }

/* ── Passage : colonnes 3 sections (talon_arret) ───────────────────
   Col 2 talons : 17% × 2 = 34mm × 2 = 68mm
   Billet principal : 66% = 128mm
   Hauteur fixée à 80mm (3 billets/page)
   ─────────────────────────────────────────────────────────────── */
.col-tp { width:17%; }  /* 33mm */
.col-bp { width:66%; }  /* 128mm */

/* Talon passage (portrait, info complète) */
.pass-talon-box { padding:1.5mm; height:80mm; overflow:hidden; }
.pt-title  { font-size:8pt; font-weight:bold; line-height:1.2; }
.pt-slogan { font-size:4pt; margin-top:0.5mm; opacity:0.85; }
.pt-sep    { border-top:0.5pt solid rgba(255,255,255,0.5); margin:1.2mm 0; }
.pt-frow   { font-size:5pt; margin-bottom:0.8mm; }
.pt-fval   { border-bottom:0.5pt solid rgba(255,255,255,0.55); display:block;
             min-height:3mm; font-size:5.5pt; font-weight:bold; }

/* Billet de passage principal */
.pass-billet-box { height:80mm; overflow:hidden; }
.pb-head   { padding:2mm 2.5mm; display:table; width:100%; }
.pb-hl     { display:table-cell; vertical-align:middle; width:60%; }
.pb-hr     { display:table-cell; vertical-align:middle; width:40%; text-align:right; }
.pb-slogan { font-size:6pt; font-weight:bold; }
.pb-contact{ font-size:4.5pt; line-height:1.5; margin-top:0.5mm; opacity:0.9; }
.pb-email  { font-size:4pt; opacity:0.75; }
.pb-logo   { font-size:11pt; font-weight:bold; }
.pb-band   { padding:0.8mm 2.5mm; font-weight:bold; font-size:7pt;
             text-align:center; letter-spacing:2pt; text-transform:uppercase; }
.pb-body   { padding:1.5mm 2.5mm; }
.pb-row    { display:table; width:100%; margin-bottom:1mm; }
.pb-cell   { display:table-cell; vertical-align:top; }
.pb-label  { font-size:4.5pt; text-transform:uppercase; color:#777; }
.pb-val    { border-bottom:0.5pt solid #aaa; display:block; min-height:3.5mm;
             font-size:6pt; font-weight:bold; overflow:hidden; }
.pb-foot   { font-size:4pt; color:#888; border-top:0.5pt solid #ddd;
             padding-top:0.8mm; margin-top:1mm; }

/* ── Talons bagage / colis ──────────────────────────────────────
   2 sections par ticket : [Souche convoyeur] | [Talon client]
   Hauteur 88mm → 3 talons/page  (3 × 88mm + 2 × 2mm = 268mm)
   ─────────────────────────────────────────────────────────── */
.tl-table      { width:100%; border-collapse:collapse; page-break-inside:avoid; }
.tl-table td   { vertical-align:top; padding:0; overflow:hidden; }
.tl-col-souche { width:35%; }
.tl-col-talon  { width:65%; }

.tl-souche  { height:88mm; padding:2mm 2mm; overflow:hidden; position:relative; }
.tl-talon   { height:88mm; padding:2mm 2.5mm; overflow:hidden; }

.tl-head       { display:table; width:100%; margin-bottom:2mm; }
.tl-head-left  { display:table-cell; vertical-align:middle; width:55%; }
.tl-head-right { display:table-cell; vertical-align:middle; width:45%; text-align:right; }
.tl-company    { font-size:9pt; font-weight:bold; }
.tl-slogan     { font-size:4.5pt; opacity:0.85; margin-top:0.5mm; }
.tl-band       { text-align:center; font-weight:bold; font-size:7pt;
                 letter-spacing:2pt; text-transform:uppercase; padding:1mm 2mm; margin-bottom:2mm; }
.tl-shortcode  { font-size:14pt; font-weight:bold; letter-spacing:3pt; text-align:center;
                 margin:2mm 0; line-height:1.2; font-family:'DejaVu Sans Mono',monospace; }
.tl-field      { margin-bottom:1.5mm; font-size:6pt; }
.tl-fk         { font-size:5pt; color:#555; text-transform:uppercase; }
.tl-fv         { font-weight:bold; }
.tl-fline      { border-bottom:0.5pt solid #999; display:block; min-width:18mm; height:4mm; }
.tl-fline-sm   { border-bottom:0.5pt solid #999; display:inline-block; min-width:12mm; height:4mm; }
.tl-qr-zone    { text-align:center; margin-top:2mm; }
.tl-foot       { font-size:4pt; color:#888; border-top:0.5pt solid #ddd; padding-top:1mm; margin-top:2mm; }

/* Souche (partie convoyeur) */
.tl-s-title    { font-size:8pt; font-weight:bold; line-height:1.2; }
.tl-s-sub      { font-size:5pt; opacity:0.85; margin-top:1mm; }
.tl-s-code     { font-size:11pt; font-weight:bold; letter-spacing:2pt;
                 font-family:'DejaVu Sans Mono',monospace; margin-top:2mm; }
.tl-s-field    { font-size:5pt; margin-top:1.5mm; }
.tl-s-fline    { border-bottom:0.5pt solid rgba(255,255,255,0.5); display:block;
                 min-height:3.5mm; font-size:5.5pt; font-weight:bold; }
</style>
</head>
<body>

<table width="100%"><tr>
  <td><div class="ph-title"><?= ppE(strtoupper($companyName)) ?> — Supports Pré-imprimés</div>
      <div class="ph-sub">Lot : <strong><?= ppE($batchShort) ?>…</strong> · <?= ppE($generated) ?> · <?= count($tickets) ?> supports<?php if($tripCode): ?> · Voyage : <strong><?= ppE($tripCode) ?></strong> · <?= ppE($lineName) ?> · <?= ppE($tripDate) ?> <?= ppE($tripHeure) ?><?php endif ?></div>
      <?php
        $legal = array_filter([
          !empty($company['niu'])  ? 'NIU '  . $company['niu']  : null,
          !empty($company['rccm']) ? 'RCCM ' . $company['rccm'] : null,
          $company['address'] ?? null,
        ]);
      ?>
      <?php if ($legal): ?>
        <div class="ph-sub" style="margin-top:0.5mm;"><?= ppE(implode(' · ', $legal)) ?></div>
      <?php endif ?>
  </td>
</tr></table>
<div style="border-bottom:1.5pt solid #C62828;margin:2mm 0 4mm;"></div>

<?php
$ticketIdx = 0;
$ticketTotal = count($tickets);
?>
<?php foreach ($tickets as $t):
  $ticketIdx++;
  $thisPreprintType = $t['preprint_type'] ?? $preprintType;
  $thisIsTalon      = in_array($thisPreprintType, ['talon_bagage', 'talon_colis'], true);

  $type   = $t['ticket_type'] ?? 'passage_final';
  $typeCfg = $typeConfigs[$type] ?? [];
  $colors = ppCol($defaultColors, $type);
  $bg     = !empty($t['ticket_color']) ? $t['ticket_color'] : ($thisIsTalon ? $talonColor : $colors['bg']);
  $fg     = $thisIsTalon ? '#FFFFFF' : $colors['text'];
  $lbl    = $thisIsTalon ? $talonLabel : $colors['label'];
  $variant = in_array(($typeCfg['layout_variant'] ?? 'A'), ['A', 'B'], true) ? $typeCfg['layout_variant'] : 'A';
  $rowHeight = $thisIsTalon ? 88 : (int)($typeCfg['row_height_mm'] ?? ($type === 'talon_arret' ? 80 : 62));
  if (!$thisIsTalon) {
      $rowHeight = $type === 'talon_arret'
          ? max(70, min(95, $rowHeight))
          : max(55, min(75, $rowHeight));
  }
  $showQr = !isset($typeCfg['show_qr']) || !empty($typeCfg['show_qr']);
  $showCompanyContact = !isset($typeCfg['show_company_contact']) || !empty($typeCfg['show_company_contact']);
  $showCompanyPhone = !isset($typeCfg['show_company_phone']) || !empty($typeCfg['show_company_phone']);
  $showTripInfo = !isset($typeCfg['show_trip_info']) || !empty($typeCfg['show_trip_info']);
  $showSeatInfo = !isset($typeCfg['show_seat_info']) || !empty($typeCfg['show_seat_info']);
  $showPriceField = !isset($typeCfg['show_price_field']) || !empty($typeCfg['show_price_field']);
  $showAgencyStub = !isset($typeCfg['show_agency_stub']) || !empty($typeCfg['show_agency_stub']);
  $showPassengerReference = !isset($typeCfg['show_passenger_reference']) || !empty($typeCfg['show_passenger_reference']);
  $num    = ppE($t['pre_print_number'] ?? '');
  $shortCode = ppE($t['short_code'] ?? '');
  $seat   = !empty($t['seat_number']) ? (int)$t['seat_number'] : null;
  $agn    = ppE(substr($t['agency_name'] ?? '', 0, 18));
  $qri    = !empty($t['qr_base64']) ? '<img src="data:image/png;base64,'.ppE($t['qr_base64']).'" width="38" height="38" alt="QR">' : '';
  $qrh    = ppE(substr($t['qr_code'] ?? '', 0, 14));
  $fd     = $tripDate  ? ppE($tripDate).' '.ppE($tripHeure) : '';
  $fdep   = $tripDepart ? ppE($tripDepart) : '';
  $fdes   = $tripDest   ? ppE($tripDest)  : '';
  // 4 sections : passage_arret, passage_final, bagage_excedent, bagage_inclus
  // 3 sections : talon_arret
  // 2 sections : talon_bagage, talon_colis
  $isColisType    = !$thisIsTalon && in_array($type, ['passage_arret', 'passage_final', 'bagage_excedent', 'bagage_inclus']);
  $isBilletPassager = in_array($type, ['passage_arret', 'passage_final']);
  $numLabel         = $isBilletPassager ? 'BILLET N°' : 'TALON N°';
?>

<?php if ($thisIsTalon): ?>
<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- TALON BAGAGE / COLIS — 2 sections : Souche convoyeur | Talon client -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<?php
  $isBagage = ($thisPreprintType === 'talon_bagage');
  $tlLabel  = $isBagage ? 'TALON BAGAGE' : 'TALON COLIS';
  $tlIcon   = $isBagage ? 'Bagage' : 'Colis';
?>
<table class="tl-table">
<tr style="height:88mm;">

<!-- SOUCHE CONVOYEUR (partie gauche, colorée) -->
<td class="tl-col-souche perf">
<div class="tl-souche" style="background:<?= $bg ?>;color:<?= $fg ?>;">
  <?php if($appLogo): ?>
    <img src="<?= $appLogo ?>" style="max-height:7mm;max-width:18mm;object-fit:contain;" alt="<?= ppE($companyName) ?>">
  <?php else: ?>
    <div class="tl-s-title"><?= ppE($companyName) ?></div>
  <?php endif ?>
  <div class="tl-s-sub"><?= ppE($companySlogan) ?></div>
  <div style="border-top:0.5pt solid rgba(255,255,255,0.4);margin:1.5mm 0;"></div>
  <div style="font-size:5.5pt;font-weight:bold;text-transform:uppercase;letter-spacing:1pt;text-align:center;">
    <?= ppE($tlLabel) ?>
  </div>
  <div class="tl-s-code" style="text-align:center;"><?= $shortCode ?></div>
  <div style="border-top:0.5pt solid rgba(255,255,255,0.4);margin:1.5mm 0;"></div>
  <div class="tl-s-field">N° :<div class="tl-s-fline"><?= $num ?></div></div>
  <?php if($fd): ?><div class="tl-s-field">Date : <?= $fd ?></div><?php else: ?><div class="tl-s-field">Date : ……/……/20……</div><?php endif ?>
  <?php if($fdep): ?><div class="tl-s-field">Dép. : <?= $fdep ?></div><?php endif ?>
  <?php if($fdes): ?><div class="tl-s-field">Dest. : <?= $fdes ?></div><?php endif ?>
  <div class="tl-s-field">Expéditeur :<div class="tl-s-fline"></div></div>
  <div class="tl-s-field"><?= $isBagage ? 'Nb bagages' : 'Nb colis' ?> :<div class="tl-s-fline"></div></div>
  <div class="tl-s-field">Masse (kg) :<div class="tl-s-fline"></div></div>
  <div style="font-size:4pt;margin-top:2mm;opacity:0.7;font-style:italic;">Souche convoyeur — à conserver</div>
</div>
</td>

<!-- TALON CLIENT (partie droite, fond blanc) -->
<td class="tl-col-talon">
<div class="tl-talon" style="height:88mm;">

  <!-- En-tête coloré -->
  <div class="tl-head" style="background:<?= $bg ?>;color:<?= $fg ?>;padding:1.5mm 2mm;border-radius:2pt;">
    <div class="tl-head-left">
      <?php if($appLogo): ?>
        <img src="<?= $appLogo ?>" style="max-height:7mm;max-width:20mm;object-fit:contain;" alt="<?= ppE($companyName) ?>">
      <?php else: ?>
        <div class="tl-company"><?= ppE($companyName) ?></div>
      <?php endif ?>
      <div class="tl-slogan"><?= ppE($companySlogan) ?></div>
    </div>
    <div class="tl-head-right">
      <?php if($showCompanyContact): ?>
      <div style="font-size:4.5pt;line-height:1.4;opacity:0.9;"><?= ppE($companyServices) ?></div>
      <?php if($showCompanyPhone): ?><div style="font-size:4.5pt;"><?= ppE($companyPhone) ?></div><?php endif ?>
      <div style="font-size:4pt;opacity:0.75;"><?= ppE($companyEmail) ?></div>
      <?php endif ?>
    </div>
  </div>

  <!-- Bande type -->
  <div class="tl-band" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= ppE($tlLabel) ?></div>

  <!-- Code court + QR -->
  <div style="display:table;width:100%;margin-bottom:2mm;">
    <div style="display:table-cell;width:60%;vertical-align:middle;">
      <div style="font-size:5pt;color:#555;text-transform:uppercase;">Code :</div>
      <div class="tl-shortcode" style="color:<?= $bg ?>;text-align:left;"><?= $shortCode ?></div>
      <div style="font-size:5pt;color:#777;">N° : <?= $num ?></div>
    </div>
    <div style="display:table-cell;width:40%;vertical-align:middle;text-align:center;">
      <?php if(!empty($t['qr_base64'])): ?>
        <img src="data:image/png;base64,<?= ppE($t['qr_base64']) ?>" width="52" height="52" alt="QR">
      <?php else: ?>
        <div style="width:18mm;height:18mm;border:0.7pt dashed #bbb;margin:0 auto;font-size:5pt;color:#bbb;padding-top:6mm;text-align:center;">QR</div>
      <?php endif ?>
      <div style="font-size:3.5pt;color:#aaa;margin-top:0.5mm;word-break:break-all;"><?= $qrh ?>…</div>
    </div>
  </div>

  <!-- Champs à remplir -->
  <div style="border-top:0.5pt solid #ddd;padding-top:1.5mm;">
    <div class="tl-field"><span class="tl-fk">Nom expéditeur :</span><span class="tl-fline"></span></div>
    <div class="tl-field"><span class="tl-fk">Tél. expéditeur :</span><span class="tl-fline"></span></div>
    <div class="tl-field"><span class="tl-fk">Nom destinataire :</span><span class="tl-fline"></span></div>
    <div class="tl-field"><span class="tl-fk">Tél. destinataire :</span><span class="tl-fline"></span></div>
    <div class="tl-field">
      <span class="tl-fk">Départ :</span><?php if($fdep): ?><span class="tl-fv"><?= $fdep ?></span><?php else: ?><span class="tl-fline-sm"></span><?php endif ?>
      &nbsp;&nbsp;
      <span class="tl-fk">Dest. :</span><?php if($fdes): ?><span class="tl-fv"><?= $fdes ?></span><?php else: ?><span class="tl-fline-sm"></span><?php endif ?>
    </div>
    <div class="tl-field">
      <span class="tl-fk"><?= $isBagage ? 'Nb bagages :' : 'Nb colis :' ?></span><span class="tl-fline-sm"></span>
      &nbsp;&nbsp;
      <span class="tl-fk">Masse (kg) :</span><span class="tl-fline-sm"></span>
    </div>
    <?php if(!$isBagage): ?>
    <div class="tl-field"><span class="tl-fk">Nature / description :</span><span class="tl-fline"></span></div>
    <?php endif ?>
    <div class="tl-field"><span class="tl-fk">Prix (FCFA) :</span><span class="tl-fline"></span></div>
    <div class="tl-field"><span class="tl-fk">Réf. billet passager :</span><span class="tl-fline"></span></div>
  </div>

  <div class="tl-foot">Talon à remettre au client · Code <?= $shortCode ?> · Conservez pour le retrait<?php if($fd): ?> · <?= $fd ?><?php endif ?></div>

</div>
</td>

</tr>
</table>

<?php elseif ($isColisType): ?>
<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- TICKET COLIS  (Arrêt anticipé / Bagages excédentaires / Bagages inclus) -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<table class="ticket-table">
<tr style="height:<?= $rowHeight ?>mm;">

<!-- TALON 1 (minimal) -->
<td class="col-t1 perf">
<div class="talon-box" style="background:<?= $bg ?>;color:<?= $fg ?>;height:<?= $rowHeight ?>mm;">
  <div class="t-logo">CITY<br>BUS</div>
  <div class="t-num" style="margin-top:2mm;">
    <strong>TALON N° : <?= $num ?></strong><br>
    <?= $showTripInfo ? 'Date :&nbsp;……&nbsp;/&nbsp;……&nbsp;/&nbsp;20……' : '&nbsp;' ?>
  </div>
</div>
</td>

<!-- TALON 2 (avec téléphones) -->
<td class="col-t2 perf">
<div class="talon-box" style="background:<?= $bg ?>;color:<?= $fg ?>;height:<?= $rowHeight ?>mm;">
  <div class="t-logo">CITY<br>BUS</div>
  <?php if($showCompanyContact && $showCompanyPhone): ?><div class="t-phone"><?= ppE($companyPhone) ?></div><?php endif ?>
  <div class="t-num">
    <strong>TALON N° : <?= $num ?></strong><br>
    <?= $showTripInfo ? 'Date :&nbsp;……&nbsp;/&nbsp;……&nbsp;/&nbsp;20……' : '&nbsp;' ?>
  </div>
  <div class="t-merci">Merci pour votre confiance.</div>
</div>
</td>

<!-- BILLET CENTRAL COLIS -->
<td class="col-bi perf">
<div class="billet-box" style="height:<?= $rowHeight ?>mm;">

  <!-- En-tête coloré : logo + slogan | contact -->
  <div class="bi-head" style="background:<?= $bg ?>;color:<?= $fg ?>;">
    <div class="bi-hl">
      <?php if($appLogo): ?>
        <img src="<?= $appLogo ?>" style="max-height:8mm;max-width:22mm;object-fit:contain;" alt="<?= ppE($companyName) ?>">
      <?php else: ?>
        <div class="bi-company"><?= ppE($companyName) ?></div>
      <?php endif ?>
      <div class="bi-slogan"><?= ppE($companySlogan) ?></div>
    </div>
    <div class="bi-hr">
      <?php if($showCompanyContact): ?>
      <div class="bi-contact"><?= ppE($companyServices) ?></div>
      <?php if($showCompanyPhone): ?><div class="bi-contact"><?= ppE($companyPhone) ?></div><?php endif ?>
      <div class="bi-email"><?= ppE($companyEmail) ?></div>
      <?php endif ?>
    </div>
  </div>

  <!-- Corps : BILLET/TALON N° + QR | champs spécifiques -->
  <div class="bi-body-colis">
    <?php if($variant === 'A'): ?>
    <div class="bi-talon-col">
      <div class="bi-tn"><?= $numLabel ?>&nbsp;:<br><?= $num ?></div>
      <?php if($showTripInfo): ?><div class="bi-td"><?= $fd ?: 'Date :&nbsp;……&nbsp;/&nbsp;……&nbsp;/&nbsp;20……' ?></div><?php endif ?>
      <?php if($showQr): ?><div class="bi-tn-qr">
        <?php if($qri): ?>
          <img src="data:image/png;base64,<?= ppE($t['qr_base64']) ?>" width="40" height="40" class="bi-qr-img" alt="QR">
        <?php else: ?>
          <div class="bi-qr-ph">QR</div>
        <?php endif ?>
        <div style="font-size:4pt;color:#888;margin-top:0.5mm;word-break:break-all;"><?= $qrh ?>…</div>
      </div><?php endif ?>
    </div>
    <div class="bi-fields-col">
      <div class="field"><span class="fk">Nom :</span><span class="fline"></span></div>
      <div class="field"><span class="fk">Téléphone :</span><span class="fline"></span></div>
      <?php if($showTripInfo): ?>
      <div class="field"><span class="fk">Départ :</span><?php if($fdep): ?><span class="fv"><?= $fdep ?></span><?php else: ?><span class="fline"></span><?php endif ?></div>
      <div class="field"><span class="fk">Destination :</span><?php if($fdes): ?><span class="fv"><?= $fdes ?></span><?php else: ?><span class="fline"></span><?php endif ?></div>
      <?php endif ?>
      <?php if($type === 'passage_arret'): /* Champ spécifique : lieu d'arrêt anticipé */ ?>
      <div class="field"><span class="fk">Arrêt (lieu de descente) :</span><span class="fline"></span></div>
      <?php endif ?>
      <?php if(in_array($type, ['passage_arret','passage_final'])): /* Billets passager */ ?>
      <?php if($showSeatInfo): ?><div class="field">
        <span class="fk">N° siège :</span><?= $seat ? '<span class="fv" style="margin-right:3mm;">'.$seat.'</span>' : '<span class="fline" style="min-width:8mm;margin-right:3mm;"></span>' ?>
        <span class="fk">Bus N° :</span><?= $busCode ? '<span class="fv">'.ppE($busCode).'</span>' : '<span class="fline" style="min-width:12mm;"></span>' ?>
      </div><?php endif ?>
      <?php if($showPriceField): ?><div class="field"><span class="fk">Prix :</span><span class="fline"></span></div><?php endif ?>
      <?php elseif(in_array($type, ['bagage_excedent','bagage_inclus'])): /* Talons bagages */ ?>
      <div class="field">
        <span class="fk">Nombre de colis :</span><span class="fline" style="min-width:8mm;margin-right:3mm;"></span>
        <span class="fk">Masse :</span><span class="fline" style="min-width:10mm;"></span>
      </div>
      <?php if($type === 'bagage_excedent'): ?>
      <?php if($showPriceField): ?><div class="field"><span class="fk">Prix tarif bagage :</span><span class="fline"></span></div><?php endif ?>
      <?php else: ?>
      <?php if($showPassengerReference): ?><div class="field"><span class="fk">Réf. billet passager :</span><span class="fline"></span></div><?php endif ?>
      <?php endif ?>
      <?php endif ?>
    </div>
    <?php else: ?>
    <div class="bi-fields-col" style="width:68%;padding-left:0;padding-right:2mm;">
      <div class="field"><span class="fk">Nom :</span><span class="fline"></span></div>
      <div class="field"><span class="fk">Téléphone :</span><span class="fline"></span></div>
      <?php if($showTripInfo): ?>
      <div class="field"><span class="fk">Départ :</span><?php if($fdep): ?><span class="fv"><?= $fdep ?></span><?php else: ?><span class="fline"></span><?php endif ?></div>
      <div class="field"><span class="fk">Destination :</span><?php if($fdes): ?><span class="fv"><?= $fdes ?></span><?php else: ?><span class="fline"></span><?php endif ?></div>
      <?php endif ?>
      <?php if($type === 'passage_arret'): ?>
      <div class="field"><span class="fk">Arrêt (lieu de descente) :</span><span class="fline"></span></div>
      <?php endif ?>
      <?php if(in_array($type, ['passage_arret','passage_final'])): ?>
        <?php if($showSeatInfo): ?><div class="field">
          <span class="fk">N° siège :</span><?= $seat ? '<span class="fv" style="margin-right:3mm;">'.$seat.'</span>' : '<span class="fline" style="min-width:8mm;margin-right:3mm;"></span>' ?>
          <span class="fk">Bus N° :</span><?= $busCode ? '<span class="fv">'.ppE($busCode).'</span>' : '<span class="fline" style="min-width:12mm;"></span>' ?>
        </div><?php endif ?>
        <?php if($showPriceField): ?><div class="field"><span class="fk">Prix :</span><span class="fline"></span></div><?php endif ?>
      <?php else: ?>
      <div class="field">
        <span class="fk">Nombre de colis :</span><span class="fline" style="min-width:8mm;margin-right:3mm;"></span>
        <span class="fk">Masse :</span><span class="fline" style="min-width:10mm;"></span>
      </div>
        <?php if($type === 'bagage_excedent' && $showPriceField): ?><div class="field"><span class="fk">Prix tarif bagage :</span><span class="fline"></span></div><?php endif ?>
        <?php if($type === 'bagage_inclus' && $showPassengerReference): ?><div class="field"><span class="fk">Réf. billet passager :</span><span class="fline"></span></div><?php endif ?>
      <?php endif ?>
    </div>
    <div class="bi-talon-col" style="width:32%;border-right:0;padding-right:0;padding-left:2mm;border-left:0.5pt solid #ccc;">
      <div class="bi-td" style="margin-top:0;"><?= ppE($lbl) ?></div>
      <div class="bi-tn" style="margin-top:1.5mm;"><?= $numLabel ?>&nbsp;:<br><?= $num ?></div>
      <?php if($showTripInfo): ?><div class="bi-td"><?= $fd ?: 'Date :&nbsp;……&nbsp;/&nbsp;……&nbsp;/&nbsp;20……' ?></div><?php endif ?>
      <?php if($showQr): ?><div class="bi-tn-qr">
        <?php if($qri): ?>
          <img src="data:image/png;base64,<?= ppE($t['qr_base64']) ?>" width="40" height="40" class="bi-qr-img" alt="QR">
        <?php else: ?>
          <div class="bi-qr-ph">QR</div>
        <?php endif ?>
        <div style="font-size:4pt;color:#888;margin-top:0.5mm;word-break:break-all;"><?= $qrh ?>…</div>
      </div><?php endif ?>
    </div>
    <?php endif ?>
  </div>

</div>
</td>

<!-- STUB AGENCE -->
<td class="col-st">
<div class="stub-box" style="background:<?= $bg ?>;color:<?= $fg ?>;height:<?= $rowHeight ?>mm;">
  <div class="stub-wm">CITY BUS CITY BUS CITY BUS CITY BUS CITY BUS CITY BUS CITY BUS CITY BUS</div>
  <?php if($appLogo): ?>
    <div style="text-align:right;"><img src="<?= $appLogo ?>" style="max-height:6mm;max-width:16mm;object-fit:contain;" alt="<?= ppE($companyName) ?>"></div>
  <?php else: ?>
    <div class="stub-logo" style="color:<?= $fg ?>;"><?= ppE($companyName) ?></div>
  <?php endif ?>
  <div class="stub-type" style="color:<?= $fg ?>;"><?= ppE($lbl) ?></div>
  <?php if($showAgencyStub): ?>
  <div class="stub-agence" style="color:<?= $fg ?>;">Agence de&nbsp;:<br>………………………………</div>
  <?php endif ?>
</div>
</td>

</tr>
</table>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- BILLET DE PASSAGE  (3 sections : talon1 | talon2 | billet principal) -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<table class="ticket-table">
<tr style="height:<?= $rowHeight ?>mm;">

<!-- TALON 1 PASSAGE (info complète) -->
<td class="col-tp perf">
<div class="pass-talon-box" style="background:<?= $bg ?>;color:<?= $fg ?>;height:<?= $rowHeight ?>mm;">
  <?php if($appLogo): ?>
    <img src="<?= $appLogo ?>" style="max-height:5mm;max-width:14mm;object-fit:contain;" alt="<?= ppE($companyName) ?>">
  <?php else: ?>
    <div class="pt-title"><?= ppE($companyName) ?></div>
  <?php endif ?>
  <div class="pt-slogan"><?= ppE($companySlogan) ?></div>
  <div class="pt-sep"></div>
  <div class="pt-frow">Talon N°&nbsp;:<div class="pt-fval"><?= $num ?></div></div>
  <?php if($showTripInfo): ?><div class="pt-frow">Date&nbsp;: ……/……/20…… Heure&nbsp;: ……:……</div><?php endif ?>
  <div class="pt-frow">Nom&nbsp;:<div class="pt-fval"></div></div>
  <div class="pt-frow">Téléphone&nbsp;:<div class="pt-fval"></div></div>
  <?php if($showTripInfo): ?><div class="pt-frow">Départ&nbsp;: <?= $fdep ?: '…………' ?></div><?php endif ?>
  <div class="pt-frow">Arrêt&nbsp;:<div class="pt-fval"></div></div>
  <?php if($showPriceField): ?><div class="pt-frow">Prix&nbsp;:<div class="pt-fval"></div> FCFA</div><?php endif ?>
</div>
</td>

<!-- TALON 2 PASSAGE (identique au talon 1) -->
<td class="col-tp perf">
<div class="pass-talon-box" style="background:<?= $bg ?>;color:<?= $fg ?>;height:<?= $rowHeight ?>mm;">
  <?php if($appLogo): ?>
    <img src="<?= $appLogo ?>" style="max-height:5mm;max-width:14mm;object-fit:contain;" alt="<?= ppE($companyName) ?>">
  <?php else: ?>
    <div class="pt-title"><?= ppE($companyName) ?></div>
  <?php endif ?>
  <div class="pt-slogan"><?= ppE($companySlogan) ?></div>
  <div class="pt-sep"></div>
  <div class="pt-frow">Talon N°&nbsp;:<div class="pt-fval"><?= $num ?></div></div>
  <?php if($showTripInfo): ?><div class="pt-frow">Date&nbsp;: ……/……/20…… Heure&nbsp;: ……:……</div><?php endif ?>
  <div class="pt-frow">Nom&nbsp;:<div class="pt-fval"></div></div>
  <div class="pt-frow">Téléphone&nbsp;:<div class="pt-fval"></div></div>
  <?php if($showTripInfo): ?><div class="pt-frow">Départ&nbsp;: <?= $fdep ?: '…………' ?></div><?php endif ?>
  <div class="pt-frow">Arrêt&nbsp;:<div class="pt-fval"></div></div>
  <?php if($showPriceField): ?><div class="pt-frow">Prix&nbsp;:<div class="pt-fval"></div> FCFA</div><?php endif ?>
</div>
</td>

<!-- BILLET PRINCIPAL TALON ARRÊT ANTICIPÉ -->
<td class="col-bp">
<div class="pass-billet-box" style="height:<?= $rowHeight ?>mm;">

  <!-- En-tête coloré : logo/nom | contact -->
  <div class="pb-head" style="background:<?= $bg ?>;color:<?= $fg ?>;">
    <div class="pb-hl">
      <div class="pb-slogan"><?= ppE($companySlogan) ?></div>
      <?php if($showCompanyContact): ?>
      <div class="pb-contact"><?= ppE($companyServices) ?></div>
      <?php if($showCompanyPhone): ?><div class="pb-contact"><?= ppE($companyPhone) ?></div><?php endif ?>
      <div class="pb-email"><?= ppE($companyEmail) ?></div>
      <?php endif ?>
    </div>
    <div class="pb-hr">
      <?php if($appLogo): ?>
        <img src="<?= $appLogo ?>" style="max-height:7mm;max-width:18mm;object-fit:contain;" alt="<?= ppE($companyName) ?>">
      <?php else: ?>
        <div class="pb-logo"><?= ppE($companyName) ?></div>
      <?php endif ?>
    </div>
  </div>

  <!-- Bande type ticket -->
  <div class="pb-band" style="background:<?= $bg ?>;color:<?= $fg ?>;">TALON ARRÊT ANTICIPÉ</div>

  <!-- Corps structuré — champs + QR zone dédiée -->
  <div class="pb-body-wrap">
  <?php if($variant === 'A'): ?>
  <div class="pb-fields">
    <!-- Ligne 1 : N° talon | Réf. billet passager rouge -->
    <div class="pb-row">
      <div class="pb-cell" style="width:42%;">
        <div class="pb-label">N° Talon</div>
        <div class="pb-val" style="background:<?= $bg ?>;color:<?= $fg ?>;padding:0 1mm;"><?= $num ?></div>
      </div>
      <div class="pb-cell" style="width:58%;padding-left:2mm;">
        <?php if($showPassengerReference): ?>
        <div class="pb-label">Réf. billet passager rouge :</div>
        <div class="pb-val"></div>
        <?php endif ?>
      </div>
    </div>
    <!-- Ligne 2 : Date/Heure | N° siège + Bus N° -->
    <div class="pb-row">
      <div class="pb-cell" style="width:52%;">
        <div class="pb-label">Date &amp; Heure départ</div>
        <div class="pb-val"><?= $showTripInfo ? ($fd ?: '……/……/20……&nbsp;&nbsp;…:……') : '&nbsp;' ?></div>
      </div>
      <div class="pb-cell" style="width:48%;padding-left:2mm;">
        <div class="pb-label">N° siège&nbsp;&nbsp;&nbsp; Bus N°</div>
        <div class="pb-val"><?= $showSeatInfo ? (($seat ?? '……') . '&nbsp;&nbsp;&nbsp;&nbsp;' . ppE($busCode)) : '&nbsp;' ?></div>
      </div>
    </div>
    <!-- Ligne 3 : Nom -->
    <div class="pb-row">
      <div class="pb-cell" style="width:100%;">
        <div class="pb-label">Nom :</div>
        <div class="pb-val"></div>
      </div>
    </div>
    <!-- Ligne 4 : Téléphone -->
    <div class="pb-row">
      <div class="pb-cell" style="width:100%;">
        <div class="pb-label">Téléphone :</div>
        <div class="pb-val"></div>
      </div>
    </div>
    <!-- Ligne 5 : Départ | Arrêt (lieu de descente) -->
    <div class="pb-row">
      <div class="pb-cell" style="width:50%;">
        <div class="pb-label">Départ :</div>
        <div class="pb-val"><?= $showTripInfo ? ($fdep ?: '……………') : '&nbsp;' ?></div>
      </div>
      <div class="pb-cell" style="width:50%;padding-left:2mm;">
        <div class="pb-label">Arrêt (lieu de descente) :</div>
        <div class="pb-val"></div>
      </div>
    </div>
    <!-- Ligne 6 : Prix -->
    <div class="pb-row">
      <div class="pb-cell" style="width:100%;">
        <div class="pb-label">Prix :</div>
        <div class="pb-val" style="background:<?= $showPriceField ? $bg : '#fff' ?>;color:<?= $showPriceField ? $fg : '#111' ?>;padding:0 1mm;"><?= $showPriceField ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; FCFA' : '&nbsp;' ?></div>
      </div>
    </div>
    <div class="pb-foot">Ticket valide 48h · Remboursable avec pénalité de 3 000 FCFA &nbsp;·&nbsp; <span style="font-family:'DejaVu Sans Mono',monospace;color:#ccc;"><?= ppE(substr($t['qr_code'] ?? '', 0, 14)) ?>…</span></div>
  </div><!-- .pb-fields -->
  <div class="pb-qr-zone">
    <?php if($showQr && $qri): ?>
      <img src="data:image/png;base64,<?= ppE($t['qr_base64']) ?>" width="55" height="55" alt="QR">
    <?php elseif($showQr): ?>
      <div style="width:18mm;height:18mm;border:0.7pt dashed rgba(0,0,0,.25);margin:0 auto;display:table;text-align:center;font-size:5pt;color:#aaa;padding-top:6mm;">QR</div>
    <?php else: ?>
      <div style="font-size:5pt;color:#bbb;padding-top:8mm;">QR masqué</div>
    <?php endif ?>
    <?php if($showQr): ?><div style="font-size:4pt;color:#888;margin-top:1mm;word-break:break-all;"><?= $qrh ?>…</div><?php endif ?>
  </div><!-- .pb-qr-zone -->
  <?php else: ?>
  <div class="pb-qr-zone" style="width:20%;border-left:0;padding-left:0;padding-right:1.5mm;border-right:0.5pt solid #ddd;">
    <?php if($showQr && $qri): ?>
      <img src="data:image/png;base64,<?= ppE($t['qr_base64']) ?>" width="52" height="52" alt="QR">
    <?php elseif($showQr): ?>
      <div style="width:17mm;height:17mm;border:0.7pt dashed rgba(0,0,0,.25);margin:0 auto;display:table;text-align:center;font-size:5pt;color:#aaa;padding-top:5.5mm;">QR</div>
    <?php else: ?>
      <div style="font-size:5pt;color:#bbb;padding-top:8mm;">QR masqué</div>
    <?php endif ?>
    <?php if($showQr): ?><div style="font-size:4pt;color:#888;margin-top:1mm;word-break:break-all;"><?= $qrh ?>…</div><?php endif ?>
  </div>
  <div class="pb-fields" style="width:80%;padding-left:2mm;">
    <div class="pb-row">
      <div class="pb-cell" style="width:60%;">
        <div class="pb-label">N° Talon</div>
        <div class="pb-val" style="background:<?= $bg ?>;color:<?= $fg ?>;padding:0 1mm;"><?= $num ?></div>
      </div>
      <div class="pb-cell" style="width:40%;padding-left:2mm;">
        <?php if($showPassengerReference): ?>
        <div class="pb-label">Réf. billet</div>
        <div class="pb-val"></div>
        <?php endif ?>
      </div>
    </div>
    <?php if($showTripInfo): ?><div class="pb-row">
      <div class="pb-cell" style="width:100%;">
        <div class="pb-label">Date, heure et départ</div>
        <div class="pb-val"><?= ($fd ?: '……/……/20……&nbsp;&nbsp;…:……') ?><?= $fdep ? ' · ' . $fdep : '' ?></div>
      </div>
    </div><?php endif ?>
    <div class="pb-row">
      <div class="pb-cell" style="width:100%;">
        <div class="pb-label">Nom et téléphone</div>
        <div class="pb-val"></div>
      </div>
    </div>
    <div class="pb-row">
      <div class="pb-cell" style="width:60%;">
        <div class="pb-label">Arrêt (lieu de descente)</div>
        <div class="pb-val"></div>
      </div>
      <div class="pb-cell" style="width:40%;padding-left:2mm;">
        <div class="pb-label">Siège / Bus</div>
        <div class="pb-val"><?= $showSeatInfo ? (($seat ?? '……') . ' / ' . ppE($busCode)) : '&nbsp;' ?></div>
      </div>
    </div>
    <?php if($showPriceField): ?><div class="pb-row">
      <div class="pb-cell" style="width:100%;">
        <div class="pb-label">Prix</div>
        <div class="pb-val" style="background:<?= $bg ?>;color:<?= $fg ?>;padding:0 1mm;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; FCFA</div>
      </div>
    </div><?php endif ?>
    <div class="pb-foot">Modèle B · Ticket valide 48h<?php if($showCompanyContact): ?> · Contact société conservé en en-tête<?php endif ?></div>
  </div>
  <?php endif ?>
  </div><!-- .pb-body-wrap -->

</div>
</td>

</tr>
</table>
<?php endif ?>
<?php
  // Saut de page : 4 billets colis/page, 3 talons arrêt/page, 3 talons bagage-colis/page
  $perPage = $thisIsTalon ? 3 : (($type === 'talon_arret') ? 3 : 4);
  $isLast  = ($ticketIdx === $ticketTotal);
  if (!$isLast):
    if ($ticketIdx % $perPage === 0):
?>
<div class="page-break"></div>
<?php   else: ?>
<div class="row-sep"></div>
<?php   endif;
  endif;
?>
<?php endforeach ?>
</body>
</html>
