<?php /** @var \CityBus\Core\View $view */ ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($title) ?> · City Bus</title>
<meta http-equiv="refresh" content="60">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { height: 100%; }
  body { font-family: "Segoe UI", -apple-system, sans-serif; background: #0b1020; color: #e2e8f0; min-height: 100vh; }
  .head { background: linear-gradient(90deg, #0f172a, #1e293b); padding: 18px 28px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f59e0b; }
  .head h1 { font-size: 28px; font-weight: 800; letter-spacing: 2px; color: #fbbf24; }
  .head .clock { font-size: 32px; font-family: "Courier New", monospace; font-weight: bold; }
  .head .city { font-size: 18px; color: #94a3b8; margin-top: 2px; }
  .filters { padding: 10px 28px; background: #111827; display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 1px solid #1f2937; }
  .filters a { padding: 6px 14px; border-radius: 18px; background: #1f2937; color: #cbd5e1; text-decoration: none; font-size: 13px; }
  .filters a.active { background: #f59e0b; color: #0b1020; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; }
  th { padding: 14px 18px; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; background: #0f172a; border-bottom: 1px solid #1e293b; }
  td { padding: 18px; border-bottom: 1px solid #1e293b; font-size: 18px; }
  tr:hover td { background: #111827; }
  .time { font-family: "Courier New", monospace; font-weight: bold; font-size: 24px; color: #fbbf24; }
  .time.delayed { color: #ef4444; text-decoration: line-through; }
  .time.new { color: #10b981; font-size: 20px; display: block; }
  .dest { font-size: 22px; font-weight: bold; color: #fff; }
  .line-code { display: inline-block; padding: 3px 10px; border-radius: 6px; background: #1e40af; color: #fff; font-size: 13px; font-weight: bold; font-family: monospace; margin-right: 8px; }
  .status { display: inline-block; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
  .status.planifie { background: #1e3a8a; color: #93c5fd; }
  .status.boarding { background: #166534; color: #86efac; animation: pulse 2s infinite; }
  .status.en_route { background: #064e3b; color: #6ee7b7; }
  .status.retarde, .status.retardé { background: #7f1d1d; color: #fca5a5; animation: pulse 2s infinite; }
  .status.prep, .status.prepare { background: #78350f; color: #fcd34d; }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }
  .bus-code { color: #94a3b8; font-size: 14px; font-family: monospace; }
  .empty { text-align: center; padding: 80px 20px; color: #64748b; font-size: 18px; }
  .footer { padding: 12px 28px; text-align: center; color: #475569; font-size: 11px; border-top: 1px solid #1e293b; }
</style>
</head>
<body>
  <div class="head">
    <div>
      <h1>DÉPARTS</h1>
      <div class="city"><?= e($cityName) ?></div>
    </div>
    <div class="clock" id="clock"><?= date('H:i:s') ?></div>
  </div>

  <div class="filters">
    <a href="<?= e(url('public/departures')) ?>" class="<?= $cityId === 0 ? 'active' : '' ?>">Toutes gares</a>
    <?php foreach ($cities as $c): ?>
      <a href="<?= e(url('public/departures/' . $c['id'])) ?>" class="<?= $cityId === (int)$c['id'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
    <?php endforeach ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="empty">Aucun départ programmé.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="width: 14%">Heure</th>
          <th style="width: 8%">Voyage</th>
          <th>Destination</th>
          <th style="width: 14%">Bus</th>
          <th style="width: 16%">Statut</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $delay = (int)($r['delay_minutes'] ?? 0);
          $st = $r['status'];
          $time = substr($r['departure_time'] ?? '', 0, 5);
          $newTime = '';
          if ($delay > 0 && $time) {
            $ts = strtotime($r['trip_date'] . ' ' . $time . ' +' . $delay . ' minutes');
            $newTime = date('H:i', $ts);
          }
        ?>
          <tr>
            <td>
              <span class="time <?= $delay>0?'delayed':'' ?>"><?= e($time) ?></span>
              <?php if ($newTime): ?><span class="time new"><?= e($newTime) ?></span><?php endif ?>
            </td>
            <td><span class="line-code"><?= e($r['line_code']) ?></span></td>
            <td>
              <div class="dest"><?= e($r['arrival_city']) ?></div>
              <div style="font-size: 13px; color: #94a3b8; margin-top: 2px;"><?= e($r['line_name']) ?> · <?= e($r['trip_code']) ?></div>
            </td>
            <td><span class="bus-code"><?= e($r['bus_code'] ?? '—') ?><?= $r['bus_plate'] ? ' · ' . e($r['bus_plate']) : '' ?></span></td>
            <td><span class="status <?= e(strtolower($st)) ?>"><?= e($st) ?></span></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>

  <div class="footer">
    Mise à jour automatique toutes les 60 secondes · <?= count($rows) ?> départ(s) · <?= e(date('d/m/Y')) ?>
  </div>

  <script>
    setInterval(function() {
      var d = new Date();
      var pad = function(n) { return n < 10 ? '0' + n : n; };
      document.getElementById('clock').textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }, 1000);
  </script>
</body>
</html>
