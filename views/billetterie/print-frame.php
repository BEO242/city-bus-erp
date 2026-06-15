<?php /** Auto-impression du PDF dans iframe */ ?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>Impression <?= e($ticket['ticket_number']) ?></title>
<style>body{margin:0;font-family:sans-serif} .wrap{padding:1rem;text-align:center} button{padding:.6rem 1.2rem;background:#1565C0;color:#fff;border:0;border-radius:.5rem;cursor:pointer;margin:.5rem}</style>
</head><body>
<div class="wrap">
  <p>Impression du ticket <strong><?= e($ticket['ticket_number']) ?></strong></p>
  <button onclick="document.getElementById('f').contentWindow.print()">🖨 Imprimer</button>
  <a href="<?= e(url('billetterie/'.$ticket['id'])) ?>"><button style="background:#666">Retour</button></a>
</div>
<iframe id="f" src="<?= e(url('billetterie/'.$ticket['id'].'/pdf')) ?>" style="width:100%;height:80vh;border:1px solid #eee" onload="setTimeout(()=>this.contentWindow.print(),500)"></iframe>
</body></html>
