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
.info-table .lbl { font-size: 8.5pt; color: #607D8B; width: 40%; }
.info-table .val { font-size: 10pt; font-weight: bold; }

.pay-table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
.pay-table th { background: #1565C0; color: #fff; padding: 2mm 3mm; font-size: 9pt; text-align: left; }
.pay-table td { padding: 2mm 3mm; font-size: 10pt; border-bottom: 0.5pt solid #E0E7F0; }
.pay-table .amount { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
.pay-table tr.plus td { color: #2E7D32; }
.pay-table tr.minus td { color: #C62828; }
.pay-table tr.total td { font-weight: bold; font-size: 12pt; background: #F0F4FF; border-top: 1.5pt solid #1565C0; }

.net-box { background: #1565C0; color: #fff; border-radius: 5pt; padding: 4mm 6mm; margin-top: 4mm; display: table; width: 100%; }
.net-label { display: table-cell; font-size: 10pt; vertical-align: middle; }
.net-amount { display: table-cell; text-align: right; font-size: 18pt; font-weight: bold; vertical-align: middle; font-family: 'DejaVu Sans Mono', monospace; }

.status-paid { display: inline-block; background: #C8E6C9; color: #1B5E20; padding: 1mm 4mm; border-radius: 3pt; font-size: 8pt; font-weight: bold; }
.status-pending { display: inline-block; background: #FFF9C4; color: #F57F17; padding: 1mm 4mm; border-radius: 3pt; font-size: 8pt; font-weight: bold; }

.footer { margin-top: 10mm; border-top: 1pt dashed #B0BEC5; padding-top: 3mm; font-size: 7.5pt; color: #9E9E9E; text-align: center; }
</style>
</head>
<body>
<div class="page">

  <!-- HEADER -->
  <div class="header">
    <div class="h-left">
      <h1>Fiche de Paie</h1>
      <div class="sub">Période : <?= htmlspecialchars(sprintf('%02d/%04d', $payroll['month'], $payroll['year']), ENT_QUOTES) ?></div>
    </div>
    <div class="h-right">
      <div class="company">CITY BUS</div>
      <div class="company-sub"><?= htmlspecialchars($agency['name'] ?? '', ENT_QUOTES) ?></div>
      <div class="company-sub"><?= htmlspecialchars($agency['city'] ?? 'Brazzaville', ENT_QUOTES) ?></div>
      <div style="margin-top:2mm">
        <?php if (!empty($payroll['is_paid'])): ?>
          <span class="status-paid">PAYÉ le <?= date('d/m/Y', strtotime($payroll['paid_at'] ?? 'now')) ?></span>
        <?php else: ?>
          <span class="status-pending">EN ATTENTE</span>
        <?php endif ?>
      </div>
    </div>
  </div>

  <!-- EMPLOYÉ -->
  <div class="section">
    <div class="section-title">Informations Employé</div>
    <table class="info-table">
      <tr>
        <td class="lbl">Matricule</td>
        <td class="val"><?= htmlspecialchars($employee['matricule'] ?? '', ENT_QUOTES) ?></td>
        <td class="lbl">Nom</td>
        <td class="val"><?= htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''), ENT_QUOTES) ?></td>
      </tr>
      <tr>
        <td class="lbl">Poste</td>
        <td class="val"><?= htmlspecialchars($employee['role'] ?? '', ENT_QUOTES) ?></td>
        <td class="lbl">Agence</td>
        <td class="val"><?= htmlspecialchars($agency['name'] ?? '', ENT_QUOTES) ?></td>
      </tr>
    </table>
  </div>

  <!-- DÉTAIL PAIE -->
  <div class="section">
    <div class="section-title">Détail de la Rémunération</div>
    <table class="pay-table">
      <tr>
        <th>Libellé</th>
        <th style="text-align:right">Montant (FCFA)</th>
      </tr>
      <tr class="plus">
        <td>Salaire de base</td>
        <td class="amount">+ <?= number_format((int)$payroll['salary_base'], 0, ',', ' ') ?></td>
      </tr>
      <?php if ((int)$payroll['bonus_amount'] > 0): ?>
      <tr class="plus">
        <td>Prime de voyages (<?= (int)($payroll['trips_count'] ?? 0) ?> voyages)</td>
        <td class="amount">+ <?= number_format((int)$payroll['bonus_amount'], 0, ',', ' ') ?></td>
      </tr>
      <?php endif ?>
      <?php if ((int)$payroll['deductions'] > 0): ?>
      <tr class="minus">
        <td>Retenues</td>
        <td class="amount">- <?= number_format((int)$payroll['deductions'], 0, ',', ' ') ?></td>
      </tr>
      <?php endif ?>
      <tr class="total">
        <td>NET À PAYER</td>
        <td class="amount"><?= number_format((int)$payroll['net_amount'], 0, ',', ' ') ?> F CFA</td>
      </tr>
    </table>
  </div>

  <!-- NET -->
  <div class="net-box">
    <div class="net-label">Montant Net à Payer</div>
    <div class="net-amount"><?= number_format((int)$payroll['net_amount'], 0, ',', ' ') ?> F CFA</div>
  </div>

  <!-- FOOTER -->
  <div class="footer">
    Document généré le <?= date('d/m/Y à H:i') ?> · CITY BUS ERP · Pour toute question contacter le service RH
  </div>

</div>
</body>
</html>
