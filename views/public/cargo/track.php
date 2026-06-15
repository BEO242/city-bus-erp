<?php /** @var \CityBus\Core\View $view */ ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Suivi colis · City Bus</title>
<link rel="stylesheet" href="<?= e(url('assets/css/tailwind-built.css')) ?>">
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-2xl mx-auto py-8 px-4">

  <h1 class="text-3xl font-bold text-slate-900 mb-6 flex items-center gap-3">
    <i data-lucide="package" class="w-8 h-8 text-cb-primary"></i> Suivi colis
  </h1>

  <form method="get" action="" class="flex gap-2 mb-6">
    <input name="t" value="<?= e($tracking) ?>" placeholder="Numéro de suivi" class="flex-1 px-4 py-3 rounded-lg border border-slate-300">
    <button class="px-5 py-3 rounded-lg bg-cb-primary text-white font-bold">Suivre</button>
  </form>

  <?php if (!$parcel): ?>
    <div class="bg-white rounded-2xl p-8 text-center shadow border border-slate-200">
      <i data-lucide="package-x" class="w-12 h-12 mx-auto text-slate-400 mb-3"></i>
      <p class="text-slate-600">Aucun colis trouvé pour <strong><?= e($tracking) ?></strong></p>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-2xl shadow border border-slate-200 overflow-hidden">
      <div class="p-5 bg-cb-primary text-white">
        <div class="text-xs uppercase opacity-80">Numéro</div>
        <div class="text-2xl font-bold font-mono"><?= e($parcel['parcel_number']) ?></div>
        <div class="mt-2 text-sm opacity-90">
          De <?= e($parcel['sender_name']) ?> → <?= e($parcel['recipient_name']) ?>
        </div>
        <div class="mt-2 text-sm opacity-90">
          Poids : <?= e($parcel['weight_kg']) ?> kg · Statut : <strong><?= e($parcel['status']) ?></strong>
        </div>
      </div>

      <div class="p-5">
        <h2 class="font-bold text-slate-900 mb-3">Historique</h2>
        <ol class="relative border-l-2 border-slate-200 ml-3 space-y-4">
          <?php foreach (array_reverse($parcel['events']) as $e):
            $iconCol = match($e['event_type']) {
              'delivered' => 'emerald', 'in_transit','loaded' => 'sky',
              'arrived','out_for_delivery' => 'amber',
              'lost','damaged','returned' => 'rose',
              default => 'slate',
            };
          ?>
            <li class="ml-6">
              <span class="absolute -left-3 w-6 h-6 rounded-full bg-<?= $iconCol ?>-500 ring-4 ring-white"></span>
              <div class="text-sm font-bold text-slate-900"><?= e(ucfirst(str_replace('_',' ',$e['event_type']))) ?></div>
              <div class="text-xs text-slate-500"><?= e(date('d/m/Y H:i', strtotime($e['occurred_at']))) ?></div>
              <?php if ($e['location']): ?><div class="text-xs text-slate-700 mt-1"><?= e($e['location']) ?></div><?php endif ?>
              <?php if ($e['notes']): ?><div class="text-xs text-slate-600 mt-1"><?= e($e['notes']) ?></div><?php endif ?>
            </li>
          <?php endforeach ?>
          <?php if (empty($parcel['events'])): ?>
            <li class="ml-6 text-sm text-slate-500">Pas encore d'événements enregistrés.</li>
          <?php endif ?>
        </ol>
      </div>
    </div>
  <?php endif ?>

</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
