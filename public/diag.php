<?php
/**
 * Page diagnostic CSS — accès direct sans auth
 * URL : http://localhost:8000/diag.php
 */
$cssFile = __DIR__ . '/assets/css/tailwind-built.css';
$cssExists = is_file($cssFile);
$cssSize = $cssExists ? filesize($cssFile) : 0;
$cssMtime = $cssExists ? date('Y-m-d H:i:s', filemtime($cssFile)) : '—';
$ts = $cssExists ? filemtime($cssFile) : time();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Diagnostic CSS</title>
<link rel="stylesheet" href="/assets/css/tailwind-built.css?v=<?= $ts ?>">
<style>
  body { font-family: monospace; padding: 20px; background: #f5f5f5; max-width: 900px; margin: 0 auto; }
  .ok { color: #16a34a; }
  .ko { color: #dc2626; }
  .test { padding: 10px; margin: 5px 0; border: 1px solid #ddd; background: white; }
  pre { background: #1e1e1e; color: #4ec9b0; padding: 10px; overflow-x: auto; font-size: 12px; }
</style>
</head>
<body>

<h1>🔧 Diagnostic CSS Tailwind</h1>

<h2>1. Fichier serveur</h2>
<div class="test">
  CSS existe : <?= $cssExists ? '<span class="ok">✓ OUI</span>' : '<span class="ko">✗ NON</span>' ?><br>
  Chemin : <code><?= htmlspecialchars($cssFile) ?></code><br>
  Taille : <strong><?= number_format($cssSize) ?> octets</strong> (<?= round($cssSize/1024, 1) ?> KB)<br>
  Modifié : <?= $cssMtime ?>
</div>

<h2>2. Test des classes Tailwind (visuels)</h2>
<p>Si Tailwind charge, les boîtes ci-dessous sont colorées et arrondies :</p>

<div class="bg-red-500 text-white p-4 rounded-xl">
  <strong>bg-red-500 text-white p-4 rounded-xl</strong> — Doit être ROUGE avec coins arrondis
</div>
<br>
<div class="bg-cb-primary text-white p-4 rounded-2xl shadow-soft">
  <strong>bg-cb-primary (couleur custom #C62828)</strong> — Doit être ROUGE City Bus
</div>
<br>
<div class="flex items-center justify-between bg-slate-100 p-3 rounded-lg">
  <span>flex justify-between</span>
  <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-sm font-bold">OK</span>
</div>

<h2>3. URL générée</h2>
<div class="test">
  URL CSS référencée : <code>/assets/css/tailwind-built.css?v=<?= $ts ?></code><br>
  Test : <a href="/assets/css/tailwind-built.css?v=<?= $ts ?>" target="_blank">Ouvrir le CSS dans un onglet</a>
</div>

<h2>4. Console JS (regarde Console DevTools)</h2>
<script>
  console.log('[DIAG] Test Tailwind CSS');
  fetch('/assets/css/tailwind-built.css?v=<?= $ts ?>', { cache: 'no-store' })
    .then(r => {
      console.log('[DIAG] Status:', r.status);
      console.log('[DIAG] Content-Type:', r.headers.get('content-type'));
      console.log('[DIAG] Content-Length:', r.headers.get('content-length'));
      return r.text();
    })
    .then(t => {
      console.log('[DIAG] Premier caractères:', t.substring(0, 100));
      console.log('[DIAG] Longueur totale:', t.length);
      document.getElementById('jsResult').innerHTML =
        '<span class="ok">✓ Fetch OK — ' + t.length + ' caractères reçus</span>';
    })
    .catch(e => {
      console.error('[DIAG] ERREUR:', e);
      document.getElementById('jsResult').innerHTML =
        '<span class="ko">✗ Fetch failed: ' + e.message + '</span>';
    });
</script>
<div class="test" id="jsResult">Test en cours...</div>

<h2>5. Que faire selon le résultat</h2>
<ul>
  <li>✓ <strong>Boîtes colorées au #2</strong> → Tailwind charge bien, ton problème vient d'ailleurs (peut-être une vue spécifique)</li>
  <li>✗ <strong>Pas de couleurs</strong> → CSS pas chargé. Ouvre DevTools (F12) onglet Network, recharge cette page, vérifie le statut de <code>tailwind-built.css</code>:
    <ul>
      <li>404 → fichier introuvable</li>
      <li>200 mais sans style → MIME type erroné ou cache corrompu</li>
      <li>(canceled) → bloqué par extension/CSP</li>
    </ul>
  </li>
</ul>

</body>
</html>
