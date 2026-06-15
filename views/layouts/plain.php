<?php /** @var \CityBus\Core\View $view */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($title ?? 'City Bus') ?> · City Bus</title>

  <link rel="manifest" href="<?= e(asset('manifest.json')) ?>">
  <meta name="theme-color" content="#1565C0">

  <link rel="stylesheet" href="<?= e(asset('css/tailwind-built.css')) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/theme-cockpit.css')) ?>">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
  <script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>
</head>
<body class="font-sans bg-slate-50 text-slate-800">
  <?= $__content ?? '' ?>
  <?= $view->section('scripts') ?>
  <script>document.addEventListener('DOMContentLoaded', () => window.lucide && lucide.createIcons());</script>
</body>
</html>
