<?php /** Auto-impression du billet bagage dans iframe */ ?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>Impression <?= e($ticket['ticket_number']) ?></title>
<style>
  body{margin:0;font-family:sans-serif;background:#f8fafc}
  .wrap{padding:1.5rem;text-align:center;background:#fff;border-bottom:1px solid #e2e8f0}
  button{padding:.6rem 1.4rem;border:0;border-radius:.5rem;cursor:pointer;margin:.4rem;font-size:.9rem;font-weight:600}
  .btn-print{background:#f59e0b;color:#fff}
  .btn-back{background:#64748b;color:#fff}
</style>
</head>
<body>
<div class="wrap">
  <p style="margin:0 0 .5rem">Billet bagage &nbsp;<strong><?= e($ticket['ticket_number']) ?></strong></p>
  <button class="btn-print" onclick="document.getElementById('f').contentWindow.print()">🖨 Imprimer</button>
  <a href="<?= e(url('billetterie-bagages/' . $ticket['id'])) ?>">
    <button class="btn-back">← Retour</button>
  </a>
</div>
<iframe id="f"
        src="<?= e(url('billetterie-bagages/' . $ticket['id'] . '/pdf')) ?>"
        style="width:100%;height:85vh;border:none"
        onload="setTimeout(()=>this.contentWindow.print(), 600)">
</iframe>
</body></html>
