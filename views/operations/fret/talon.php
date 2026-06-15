<?php
/**
 * Fret talon — print-only receipt (80mm thermal printer)
 */

$isBagage = ($item['item_type'] ?? '') === 'bagage';
$isColis = ($item['item_type'] ?? '') === 'colis';
$typeLabel = $isBagage ? 'BAGAGE PASSAGER' : 'COLIS';
$categoryLabel = $item['category_label'] ?? '—';
$tripDate = !empty($item['trip_date']) ? date('d/m/Y', strtotime($item['trip_date'])) : null;
$departureTime = !empty($item['departure_scheduled']) ? date('H:i', strtotime($item['departure_scheduled'])) : null;
$createdDate = !empty($item['created_at']) ? date('d/m/Y', strtotime($item['created_at'])) : date('d/m/Y');
$createdTime = !empty($item['created_at']) ? date('H:i', strtotime($item['created_at'])) : date('H:i');
$totalPrice = number_format((float)($item['total_price'] ?? 0), 0, ',', ' ');
$isFranchise = !empty($item['is_franchise']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Talon <?= e($item['tracking_code']) ?></title>
    <style>
        @page { size: 80mm auto; margin: 2mm; }
        body { font-family: 'Courier New', monospace; font-size: 11px; margin: 0; padding: 4mm; width: 76mm; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 3mm 0; }
        .row { display: flex; justify-content: space-between; }
        .big { font-size: 16px; font-weight: bold; letter-spacing: 2px; }
        .section { margin-bottom: 1mm; }
        .scissors { text-align: center; margin: 6mm 0; font-size: 13px; letter-spacing: 1px; }
        @media print { .no-print { display: none !important; } }
        @media screen {
            body { max-width: 320px; margin: 20px auto; border: 1px solid #ccc; padding: 15px; }
        }
    </style>
</head>
<body>

<!-- Action buttons (no-print) -->
<div class="no-print" style="text-align: center; margin-bottom: 10px;">
    <button onclick="window.print()" style="padding: 8px 16px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; margin-right: 8px;">
        Imprimer
    </button>
    <button onclick="window.close()" style="padding: 8px 16px; background: #6b7280; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">
        Fermer
    </button>
</div>

<!-- ========== COPY 1: CLIENT ========== -->
<?php for ($copy = 0; $copy < 2; $copy++): ?>
<?php if ($copy === 1): ?>
<div class="scissors">&#9986; — — — COPIE AGENT — — —</div>
<?php endif; ?>

<div class="section">
    <div class="center bold">=================================</div>
    <div class="center bold" style="font-size: 13px;">CITY BUS — FRET</div>
    <div class="center bold">=================================</div>
    <div class="center bold" style="margin-top: 2mm;">TALON DE <?= $typeLabel ?></div>
</div>

<div style="margin-top: 3mm;">
    <div class="center">Code:</div>
    <div class="center big"><?= e($item['tracking_code']) ?></div>
</div>

<div class="line"></div>

<div class="section">
    <div>Type: <?= $isBagage ? 'Bagage passager' : 'Colis' ?></div>
    <div>Cat&eacute;gorie: <?= e($categoryLabel) ?></div>
    <div>Poids: <?= number_format((float)($item['weight_kg'] ?? 0), 2, ',', ' ') ?> kg | Pi&egrave;ces: <?= (int)($item['pieces'] ?? 1) ?></div>
</div>

<div class="line"></div>

<div class="section">
    <div>Exp&eacute;diteur: <?= e($item['sender_name'] ?? '—') ?></div>
    <div>T&eacute;l: <?= e($item['sender_phone'] ?? '—') ?></div>
</div>

<?php if ($isColis): ?>
<div style="margin-top: 1mm;">
    <div>Destinataire: <?= e($item['recipient_name'] ?? '—') ?></div>
    <div>T&eacute;l: <?= e($item['recipient_phone'] ?? '—') ?></div>
</div>
<?php endif; ?>

<?php if ($tripDate): ?>
<div class="line"></div>
<div class="section">
    <div>Voyage: <?= e($item['line_code'] ?? '') ?> — <?= e($tripDate) ?></div>
    <?php if ($departureTime): ?>
        <div>D&eacute;part: <?= e($departureTime) ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="line"></div>

<div class="section">
    <?php if ($isFranchise): ?>
        <div class="center bold" style="font-size: 13px;">FRANCHISE (inclus)</div>
    <?php else: ?>
        <div class="center bold" style="font-size: 14px;">Prix: <?= $totalPrice ?> FCFA</div>
    <?php endif; ?>
</div>

<div class="center bold">=================================</div>
<div class="center">Date: <?= e($createdDate) ?> &agrave; <?= e($createdTime) ?></div>
<div class="center">Agent: <?= e($item['registered_by_name'] ?? '—') ?></div>
<div class="center bold">=================================</div>

<div class="center" style="margin-top: 2mm; font-size: 10px;">
    <?php if ($copy === 0): ?>
        Conservez ce talon comme<br>preuve d'enregistrement.
    <?php else: ?>
        Copie &agrave; conserver par l'agent.
    <?php endif; ?>
</div>

<?php endfor; ?>

</body>
</html>
