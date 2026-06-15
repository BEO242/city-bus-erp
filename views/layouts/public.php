<?php /** @var \CityBus\Core\View $view */ ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'City Bus') ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/tailwind-built.css')) ?>">
</head>
<body class="bg-slate-50 min-h-screen flex flex-col">
  <header class="bg-cb-primary text-white shadow">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
      <a href="<?= e(url('public/booking')) ?>" class="text-2xl font-bold flex items-center gap-2">
        <i data-lucide="bus" class="w-7 h-7"></i> City Bus
      </a>
      <nav class="flex items-center gap-4 text-sm">
        <a href="<?= e(url('public/booking')) ?>" class="hover:underline">Réserver</a>
        <a href="<?= e(url('public/booking/lookup')) ?>" class="hover:underline">Mon PNR</a>
        <a href="<?= e(url('public/cargo/track')) ?>" class="hover:underline">Suivre colis</a>
        <a href="<?= e(url('public/departures')) ?>" class="hover:underline">Départs</a>
        <a href="<?= e(url('login')) ?>" class="bg-white/20 px-3 py-1 rounded-lg">Connexion</a>
      </nav>
    </div>
  </header>

  <?php $flashes = \CityBus\Core\Session::pullFlash(); foreach ($flashes as $type => $msgs): foreach ($msgs as $msg): ?>
    <div class="max-w-6xl mx-auto mt-3 px-3 py-2 rounded-lg text-sm <?= $type==='success'?'bg-emerald-100 text-emerald-800':($type==='danger'?'bg-rose-100 text-rose-800':'bg-amber-100 text-amber-800') ?>">
      <?= e($msg) ?>
    </div>
  <?php endforeach; endforeach ?>

  <main class="flex-1 max-w-6xl mx-auto w-full px-4 py-6">
    <?= $__content ?? '' ?>
  </main>

  <footer class="bg-slate-900 text-slate-300 text-sm">
    <div class="max-w-6xl mx-auto px-4 py-6 grid grid-cols-1 md:grid-cols-3 gap-6">
      <div><strong class="text-white">City Bus</strong><br>Transport interurbain au Congo.</div>
      <div><strong class="text-white">Aide</strong><br>FAQ · CGV · Contact</div>
      <div><strong class="text-white">Suivez-nous</strong><br>Facebook · WhatsApp</div>
    </div>
    <div class="border-t border-slate-700 py-3 text-center text-xs">
      © <?= date('Y') ?> City Bus
    </div>
  </footer>

  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
