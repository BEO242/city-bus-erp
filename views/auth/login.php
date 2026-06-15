<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
$flash = \CityBus\Core\Session::pullFlash();
?>

<?php $view->start('content') ?>
<div class="min-h-screen flex">
  <!-- Hero gauche -->
  <div class="hidden lg:flex w-1/2 bg-gradient-to-br from-cb-dark via-cb-primary to-cb-secondary text-white p-12 flex-col justify-between relative overflow-hidden">
    <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
    <div class="absolute -bottom-40 -left-20 w-[28rem] h-[28rem] rounded-full bg-cb-accent/20 blur-3xl"></div>

    <div class="relative z-10">
      <div class="flex items-center gap-3 mb-12">
        <div class="bg-white text-cb-primary rounded-xl p-2.5">
          <i data-lucide="bus" class="w-7 h-7"></i>
        </div>
        <span class="font-bold text-2xl">City Bus ERP</span>
      </div>
      <h1 class="text-5xl font-extrabold leading-tight">
        Pilotez votre flotte<br>
        avec <span class="text-cb-accent">précision</span>.
      </h1>
      <p class="text-white/80 text-lg mt-6 max-w-md leading-relaxed">
        Plateforme intégrée de gestion : voyages, billetterie, contrôle QR, caisse,
        maintenance, RH et reporting temps réel.
      </p>
    </div>

    <div class="relative z-10 grid grid-cols-3 gap-4">
      <?php foreach ([
        ['shield-check', 'Sécurisé'],
        ['wifi-off',     'Mode offline'],
        ['gauge',        'Temps réel'],
      ] as [$icon, $label]): ?>
        <div class="bg-white/10 backdrop-blur rounded-2xl p-4 text-center">
          <i data-lucide="<?= e($icon) ?>" class="w-6 h-6 mx-auto mb-2"></i>
          <p class="text-sm font-medium"><?= e($label) ?></p>
        </div>
      <?php endforeach ?>
    </div>
  </div>

  <!-- Formulaire -->
  <div class="flex-1 flex items-center justify-center p-8">
    <div class="w-full max-w-md">
      <div class="lg:hidden flex items-center gap-3 mb-8 justify-center">
        <div class="bg-cb-primary text-white rounded-xl p-2.5">
          <i data-lucide="bus" class="w-7 h-7"></i>
        </div>
        <span class="font-bold text-2xl text-cb-primary">City Bus ERP</span>
      </div>

      <h2 class="text-3xl font-bold text-slate-900">Bienvenue 👋</h2>
      <p class="text-slate-500 mt-2">Connectez-vous pour accéder à votre tableau de bord.</p>

      <?php foreach ($flash as $type => $msgs): foreach ($msgs as $msg): ?>
        <div class="mt-4 p-3 rounded-xl <?= $type==='danger'?'bg-rose-50 border border-rose-200 text-rose-700':'bg-cb-bg border border-cb-accent/30 text-cb-dark' ?>">
          <?= e($msg) ?>
        </div>
      <?php endforeach; endforeach ?>

      <form method="post" action="<?= e(url('login')) ?>" class="mt-8 space-y-5">
        <?= csrf_field() ?>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Adresse email</label>
          <div class="relative">
            <i data-lucide="mail" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus
                   class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none transition">
          </div>
          <?php foreach (errors('email') as $e): ?>
            <p class="text-xs text-rose-600 mt-1"><?= e($e) ?></p>
          <?php endforeach ?>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Mot de passe</label>
          <div class="relative" x-data="{ show:false }">
            <i data-lucide="lock" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input :type="show?'text':'password'" name="password" required
                   class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none transition">
            <button type="button" @click="show=!show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700">
              <i :data-lucide="show?'eye-off':'eye'" class="w-4 h-4"></i>
            </button>
          </div>
          <?php foreach (errors('password') as $e): ?>
            <p class="text-xs text-rose-600 mt-1"><?= e($e) ?></p>
          <?php endforeach ?>
        </div>

        <button type="submit"
                class="w-full bg-gradient-to-r from-cb-primary to-cb-secondary text-white font-semibold py-3 rounded-xl shadow-lg shadow-cb-primary/30 hover:shadow-xl hover:from-cb-dark transition flex items-center justify-center gap-2">
          <i data-lucide="log-in" class="w-4 h-4"></i> Se connecter
        </button>

        <p class="text-center text-sm">
          <a href="<?= e(url('forgot-password')) ?>" class="text-cb-primary hover:underline">Mot de passe oublié ?</a>
        </p>
      </form>

      <p class="text-center text-xs text-slate-400 mt-8">
        © <?= date('Y') ?> City Bus ERP · Tous droits réservés.
      </p>
    </div>
  </div>
</div>
<?php $view->end() ?>
