<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #1a1a2e; }
.page { padding: 10mm; }
.header { border-bottom: 2pt solid #1565C0; padding-bottom: 5mm; margin-bottom: 6mm; display: table; width: 100%; }
.h-left { display: table-cell; vertical-align: top; }
.h-right { display: table-cell; vertical-align: top; text-align: right; }
h1 { font-size: 18pt; color: #1565C0; }
.sub { font-size: 9pt; color: #607D8B; margin-top: 1mm; }
.company { font-size: 11pt; font-weight: bold; }
.company-sub { font-size: 8pt; color: #607D8B; }

.section { margin-bottom: 6mm; }
.section-title { font-size: 10pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt;
    color: #1565C0; border-bottom: 1pt solid #C8D8F0; padding-bottom: 1.5mm; margin-bottom: 3mm; }

.info-table { width: 100%; border-collapse: collapse; }
.info-table td { padding: 1.5mm 2mm; vertical-align: top; }
.info-table .lbl { font-size: 8.5pt; color: #607D8B; width: 45%; }
.info-table .val { font-size: 10pt; font-weight: bold; }

.sales-table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
.sales-table th { background: #1565C0; color: #fff; padding: 2mm 3mm; font-size: 8.5pt; text-align: left; }
.sales-table td { padding: 2mm 3mm; font-size: 9.5pt; border-bottom: 0.5pt solid #E0E7F0; }
.sales-table .amount { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
.sales-table tr:nth-child(even) td { background: #F8FAFF; }
.sales-table tr.total td { font-weight: bold; font-size: 11pt; background: #F0F4FF; border-top: 1.5pt solid #1565C0; }

.summary-box { display: table; width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
.sum-cell { display: table-cell; width: 33%; padding: 3mm; text-align: center; border: 1pt solid #C8D8F0; border-radius: 4pt; }
.sum-label { font-size: 7.5pt; text-transform: uppercase; color: #607D8B; }
.sum-value { font-size: 14pt; font-weight: bold; color: #1565C0; margin-top: 1mm; }
.sum-value.green { color: #2E7D32; }
.sum-value.orange { color: #E65100; }

.diff-box { border-radius: 5pt; padding: 3mm 5mm; margin-top: 3mm; }
.diff-ok { background: #C8E6C9; color: #1B5E20; }
.diff-err { background: #FFCDD2; color: #B71C1C; }

.footer { margin-top: 10mm; border-top: 1pt dashed #B0BEC5; padding-top: 3mm; font-size: 7.5pt; color: #9E9E9E; text-align: center; }
</style>
</head>
<body>
<div class="page">

  <!-- HEADER -->
  <div class="header">
    <div class="h-left">
      <h1>Clôture de Caisse</h1>
      <div class="sub">
        Caisse : <?= htmlspecialchars($register['name'] ?? 'Caisse #' . ($register['id'] ?? ''), ENT_QUOTES) ?>
        &nbsp;·&nbsp; <?= htmlspecialchars($register['agency_name'] ?? '', ENT_QUOTES) ?>
      </div>
    </div>
    <div class="h-right">
      <div class="company">CITY BUS</div>
      <div class="company-sub">Document de clôture</div>
      <div class="company-sub">Généré le <?= date('d/m/Y à H:i') ?></div>
    </div>
  </div>

  <!-- RÉSUMÉ -->
  <div class="section">
    <div class="section-title">Résumé</div>
    <table class="summary-box">
      <tr>
        <td class="sum-cell">
          <div class="sum-label">Tickets vendus</div>
          <div class="sum-value"><?= (int)($closure['tickets_sold'] ?? 0) ?></div>
        </td>
        <td class="sum-cell">
          <div class="sum-label">Total théorique</div>
          <div class="sum-value"><?= number_format((int)($closure['theoretical_amount'] ?? 0), 0, ',', ' ') ?> F</div>
        </td>
        <td class="sum-cell">
          <div class="sum-label">Montant déclaré</div>
          <div class="sum-value green"><?= number_format((int)($closure['declared_amount'] ?? 0), 0, ',', ' ') ?> F</div>
        </td>
      </tr>
    </table>

    <?php
      $diff = (int)($closure['declared_amount'] ?? 0) - (int)($closure['theoretical_amount'] ?? 0);
      $diffCls = abs($diff) <= 500 ? 'diff-ok' : 'diff-err';
      $diffSign = $diff >= 0 ? '+' : '';
    ?>
    <div class="diff-box <?= $diffCls ?>">
      <strong>Écart :</strong> <?= $diffSign . number_format($diff, 0, ',', ' ') ?> F CFA
      <?= abs($diff) <= 500 ? '· Caisse équilibrée' : '· Vérification requise' ?>
    </div>
  </div>

  <!-- INFOS CAISSE -->
  <div class="section">
    <div class="section-title">Informations de Session</div>
    <table class="info-table">
      <tr>
        <td class="lbl">Caissier</td>
        <td class="val"><?= htmlspecialchars(($register['cashier_first'] ?? '') . ' ' . ($register['cashier_last'] ?? ''), ENT_QUOTES) ?></td>
        <td class="lbl">Ouverture</td>
        <td class="val"><?= !empty($register['opened_at']) ? date('d/m/Y H:i', strtotime($register['opened_at'])) : '—' ?></td>
      </tr>
      <tr>
        <td class="lbl">Agence</td>
        <td class="val"><?= htmlspecialchars($register['agency_name'] ?? '', ENT_QUOTES) ?></td>
        <td class="lbl">Clôture</td>
        <td class="val"><?= !empty($closure['closed_at']) ? date('d/m/Y H:i', strtotime($closure['closed_at'])) : date('d/m/Y H:i') ?></td>
      </tr>
    </table>
  </div>

  <!-- VENTES -->
  <?php if (!empty($sales)): ?>
  <div class="section">
    <div class="section-title">Détail des Ventes (<?= count($sales) ?> tickets)</div>
    <table class="sales-table">
      <tr>
        <th>N° Ticket</th>
        <th>Passager</th>
        <th>Ligne</th>
        <th style="text-align:right">Prix (F CFA)</th>
      </tr>
      <?php $total = 0; foreach ($sales as $s): $total += (int)($s['price_fcfa'] ?? 0); ?>
      <tr>
        <td style="font-family:'DejaVu Sans Mono',monospace;font-size:8.5pt"><?= htmlspecialchars($s['ticket_number'] ?? '', ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($s['passenger_name'] ?? '—', ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($s['line_code'] ?? '', ENT_QUOTES) ?></td>
        <td class="amount"><?= number_format((int)($s['price_fcfa'] ?? 0), 0, ',', ' ') ?></td>
      </tr>
      <?php endforeach ?>
      <tr class="total">
        <td colspan="3">TOTAL</td>
        <td class="amount"><?= number_format($total, 0, ',', ' ') ?> F CFA</td>
      </tr>
    </table>
  </div>
  <?php endif ?>

  <!-- FOOTER -->
  <div class="footer">
    Document officiel de clôture de caisse · CITY BUS ERP · Ce document fait foi en cas de litige
  </div>

</div>
</body>
</html>
