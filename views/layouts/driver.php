<?php /** @var \CityBus\Core\View $view */ ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<title><?= e($title ?? 'Chauffeur · City Bus') ?></title>
<link rel="manifest" href="<?= e(url('driver-manifest.webmanifest')) ?>">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/driver-icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/tailwind-built.css')) ?>?v=<?= @filemtime(__DIR__.'/../../public/assets/css/tailwind-built.css') ?: time() ?>">
<style>
  body { background: #f1f5f9; -webkit-tap-highlight-color: transparent; touch-action: manipulation; padding-bottom: env(safe-area-inset-bottom); }
  .tabbar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; border-top: 1px solid #e2e8f0; padding-bottom: env(safe-area-inset-bottom); z-index: 50; }
  .tabbar a { color: #64748b; }
  .tabbar a.active { color: #0f172a; }
</style>
</head>
<body class="min-h-screen">
  <header class="bg-slate-900 text-white px-4 py-3 sticky top-0 z-40 shadow">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <i data-lucide="bus" class="w-5 h-5 text-amber-400"></i>
        <span class="font-bold"><?= e($headerTitle ?? 'Chauffeur') ?></span>
      </div>
      <div class="text-xs opacity-70" id="connStatus">●</div>
    </div>
  </header>

  <?php $flashes = \CityBus\Core\Session::pullFlash(); foreach ($flashes as $type => $msgs): foreach ($msgs as $msg): ?>
    <div class="m-3 px-3 py-2 rounded-lg text-sm <?= $type==='success'?'bg-emerald-100 text-emerald-800':($type==='danger'?'bg-rose-100 text-rose-800':'bg-amber-100 text-amber-800') ?>">
      <?= e($msg) ?>
    </div>
  <?php endforeach; endforeach ?>

  <main class="pb-24 pt-2 px-3">
    <?= $__content ?? '' ?>
  </main>

  <nav class="tabbar grid grid-cols-4">
    <?php
      $tab = $tab ?? '';
      $items = [
        ['home',    'home',     'Voyages',   url('m/driver')],
        ['stops',   'map-pin',  'Arrêts',    url('m/driver/stops')],
        ['hos',     'clock-9',  'HOS',       url('m/driver/hos')],
        ['profile', 'user',     'Profil',    url('m/driver/profile')],
      ];
      foreach ($items as [$key, $icon, $lbl, $href]):
    ?>
      <a href="<?= e($href) ?>" class="flex flex-col items-center justify-center py-2 <?= $tab===$key?'active':'' ?>">
        <i data-lucide="<?= e($icon) ?>" class="w-5 h-5"></i>
        <span class="text-[11px] mt-0.5"><?= e($lbl) ?></span>
      </a>
    <?php endforeach ?>
  </nav>

  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <script>
    lucide.createIcons();
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('<?= e(url('driver-sw.js')) ?>', { scope: '<?= e(url('m/driver')) ?>' }).catch(() => {});
    }
    function updateOnline() {
      const el = document.getElementById('connStatus');
      if (!el) return;
      el.textContent = navigator.onLine ? '● en ligne' : '○ hors ligne';
      el.className = 'text-xs ' + (navigator.onLine ? 'text-emerald-300' : 'text-amber-300');
    }
    window.addEventListener('online', updateOnline);
    window.addEventListener('offline', updateOnline);
    updateOnline();
  </script>
</body>
</html>
