<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="max-w-md mx-auto">
  <h1 class="text-2xl font-bold text-slate-800 mb-2">Codes de récupération</h1>
  <p class="text-sm text-slate-500 mb-5">Conservez ces codes en lieu sûr. Chaque code n'est utilisable qu'une seule fois et permet de vous reconnecter en cas de perte de votre appareil 2FA.</p>

  <div class="bg-white rounded-2xl shadow-soft p-6">
    <?php if (empty($codes)): ?>
      <p class="text-sm text-amber-600">Aucun code à afficher (déjà consultés).</p>
    <?php else: ?>
      <div class="grid grid-cols-2 gap-2 font-mono text-center">
        <?php foreach ($codes as $c): ?>
          <div class="px-3 py-2 rounded-lg bg-slate-50 text-slate-700 text-sm tracking-widest"><?= e($c) ?></div>
        <?php endforeach ?>
      </div>
      <button onclick="navigator.clipboard.writeText(document.getElementById('codes-text').innerText); this.innerText='Copié !'"
              class="mt-4 w-full py-2 rounded-lg bg-cb-bg text-cb-primary font-semibold hover:bg-cb-accent/20">
        Copier tous les codes
      </button>
      <textarea id="codes-text" class="hidden"><?= e(implode("\n", $codes)) ?></textarea>
    <?php endif ?>
    <a href="<?= e(url('profile')) ?>" class="block text-center mt-4 text-sm text-slate-500 hover:text-cb-primary">Retour au profil</a>
  </div>
</div>

<?php $view->end() ?>
