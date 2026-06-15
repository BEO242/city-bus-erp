<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/public'); ?>
<?php $view->start('content') ?>

<!-- Hero Section -->
<section class="relative rounded-3xl overflow-hidden mb-8 shadow-2xl"
         style="background: linear-gradient(135deg, #C62828 0%, #8B0000 60%, #1a0000 100%);">
  <!-- Background pattern -->
  <div class="absolute inset-0 opacity-10"
       style="background-image: repeating-linear-gradient(45deg, transparent, transparent 30px, rgba(255,255,255,.08) 30px, rgba(255,255,255,.08) 32px);"></div>
  <!-- Large decorative bus icon -->
  <div class="absolute right-0 bottom-0 text-[240px] leading-none opacity-5 select-none pointer-events-none font-black" style="color:#fff">🚌</div>

  <div class="relative px-8 pt-10 pb-6">
    <div class="max-w-xl mb-6">
      <div class="inline-flex items-center gap-2 bg-white/15 text-white/90 text-xs font-semibold px-3 py-1.5 rounded-full mb-4">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
        Réservations en ligne disponibles
      </div>
      <h1 class="text-4xl font-black text-white leading-tight mb-3">
        Voyagez partout<br>avec <span class="text-amber-400">City Bus</span>
      </h1>
      <p class="text-white/70 text-base">Réservez votre place en quelques clics · Payez par Mobile Money, carte ou cash · E-billet QR instantané</p>
    </div>

    <!-- Search form -->
    <div class="bg-white rounded-2xl shadow-2xl p-5 max-w-3xl">
      <form method="get" action="<?= e(url('public/booking/search')) ?>"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="lg:col-span-1">
          <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5">
            <i data-lucide="map-pin" class="w-3 h-3 inline text-cb-primary"></i> Départ
          </label>
          <select name="from" required
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm bg-slate-50 focus:ring-2 focus:ring-cb-primary/30 focus:border-cb-primary outline-none">
            <option value="">— ville départ —</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <!-- Swap arrow -->
        <div class="hidden lg:flex items-end justify-center pb-1 absolute" style="margin-left:-10px;margin-top:26px">
          <div class="w-7 h-7 rounded-full bg-cb-primary text-white flex items-center justify-center text-xs">⇄</div>
        </div>

        <div class="lg:col-span-1">
          <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5">
            <i data-lucide="map-pin" class="w-3 h-3 inline text-emerald-600"></i> Arrivée
          </label>
          <select name="to" required
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm bg-slate-50 focus:ring-2 focus:ring-cb-primary/30 focus:border-cb-primary outline-none">
            <option value="">— ville arrivée —</option>
            <?php foreach ($cities as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="lg:col-span-1">
          <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5">
            <i data-lucide="calendar" class="w-3 h-3 inline text-violet-600"></i> Date
          </label>
          <input name="date" type="date" required
                 min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm bg-slate-50 focus:ring-2 focus:ring-cb-primary/30 focus:border-cb-primary outline-none">
        </div>

        <div class="lg:col-span-1">
          <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5">
            <i data-lucide="users" class="w-3 h-3 inline text-amber-600"></i> Passagers
          </label>
          <select name="pax"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm bg-slate-50 focus:ring-2 focus:ring-cb-primary/30 focus:border-cb-primary outline-none">
            <?php for ($i = 1; $i <= 6; $i++): ?>
              <option value="<?= $i ?>"><?= $i ?> passager<?= $i > 1 ? 's' : '' ?></option>
            <?php endfor ?>
          </select>
        </div>

        <div class="lg:col-span-1 flex items-end">
          <button type="submit"
                  class="w-full py-2.5 px-4 rounded-xl bg-cb-primary hover:bg-cb-secondary text-white font-black text-sm transition shadow-md hover:shadow-lg flex items-center justify-center gap-2">
            <i data-lucide="search" class="w-4 h-4"></i>
            Rechercher
          </button>
        </div>
      </form>

      <!-- Quick links under form -->
      <div class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-100">
        <a href="<?= e(url('public/booking/lookup')) ?>"
           class="flex items-center gap-1.5 text-xs text-slate-500 hover:text-cb-primary transition">
          <i data-lucide="search" class="w-3.5 h-3.5"></i> Retrouver mon PNR
        </a>
        <a href="<?= e(url('public/cargo/track')) ?>"
           class="flex items-center gap-1.5 text-xs text-slate-500 hover:text-cb-primary transition">
          <i data-lucide="package" class="w-3.5 h-3.5"></i> Suivre mon colis
        </a>
        <a href="<?= e(url('public/departures')) ?>"
           class="flex items-center gap-1.5 text-xs text-slate-500 hover:text-cb-primary transition">
          <i data-lucide="clock" class="w-3.5 h-3.5"></i> Horaires du jour
        </a>
      </div>
    </div>
  </div>
</section>

<!-- Feature cards -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
  <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 hover:border-amber-200 hover:shadow-md transition group">
    <div class="w-12 h-12 rounded-2xl bg-amber-100 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
      <i data-lucide="zap" class="w-6 h-6 text-amber-600"></i>
    </div>
    <h3 class="font-bold text-slate-900 mb-2">Réservation instantanée</h3>
    <p class="text-sm text-slate-500 leading-relaxed">Choisissez votre siège, renseignez vos informations et recevez votre e-billet en moins de 2 minutes. Sans inscription.</p>
  </div>
  <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 hover:border-emerald-200 hover:shadow-md transition group">
    <div class="w-12 h-12 rounded-2xl bg-emerald-100 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
      <i data-lucide="smartphone" class="w-6 h-6 text-emerald-600"></i>
    </div>
    <h3 class="font-bold text-slate-900 mb-2">Paiement Mobile Money</h3>
    <p class="text-sm text-slate-500 leading-relaxed">Payez en toute sécurité via Airtel Money, MTN MoMo ou Orange Money. Votre confirmation arrive immédiatement par SMS.</p>
    <div class="flex items-center gap-2 mt-3">
      <span class="text-[10px] bg-red-100 text-red-700 font-bold px-2 py-0.5 rounded">Airtel</span>
      <span class="text-[10px] bg-yellow-100 text-yellow-800 font-bold px-2 py-0.5 rounded">MTN</span>
      <span class="text-[10px] bg-orange-100 text-orange-700 font-bold px-2 py-0.5 rounded">Orange</span>
    </div>
  </div>
  <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 hover:border-cb-primary/30 hover:shadow-md transition group">
    <div class="w-12 h-12 rounded-2xl bg-cb-bg flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
      <i data-lucide="qr-code" class="w-6 h-6 text-cb-primary"></i>
    </div>
    <h3 class="font-bold text-slate-900 mb-2">E-billet QR Code</h3>
    <p class="text-sm text-slate-500 leading-relaxed">Votre PNR unique est envoyé par SMS et email avec QR code. Présentez-le lors de l'embarquement — aucun papier nécessaire.</p>
  </div>
</section>

<!-- Popular routes (if available) -->
<?php if (!empty($popularRoutes ?? [])): ?>
<section class="mb-8">
  <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
    <i data-lucide="trending-up" class="w-5 h-5 text-cb-primary"></i>
    Lignes populaires
  </h2>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php foreach (array_slice($popularRoutes ?? [], 0, 4) as $route): ?>
    <a href="<?= e(url('public/booking/search?' . http_build_query(['from' => $route['from_id'] ?? '', 'to' => $route['to_id'] ?? '', 'date' => date('Y-m-d')]))) ?>"
       class="bg-white rounded-xl border border-slate-100 p-4 hover:border-cb-primary hover:shadow-md transition">
      <div class="flex items-center gap-2 text-sm font-bold text-slate-900">
        <span><?= e($route['departure_city'] ?? '') ?></span>
        <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
        <span><?= e($route['arrival_city'] ?? '') ?></span>
      </div>
      <?php if (!empty($route['min_price'])): ?>
        <p class="text-xs text-cb-primary font-bold mt-1">À partir de <?= number_format((int)$route['min_price']) ?> FCFA</p>
      <?php endif ?>
    </a>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<!-- Trust badges -->
<section class="bg-slate-50 rounded-2xl p-6 border border-slate-100">
  <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
    <div>
      <p class="text-2xl font-black text-cb-primary">100K+</p>
      <p class="text-xs text-slate-500 mt-1 font-semibold">Voyageurs transportés</p>
    </div>
    <div>
      <p class="text-2xl font-black text-cb-primary">50+</p>
      <p class="text-xs text-slate-500 mt-1 font-semibold">Destinations desservies</p>
    </div>
    <div>
      <p class="text-2xl font-black text-cb-primary">98%</p>
      <p class="text-xs text-slate-500 mt-1 font-semibold">Clients satisfaits</p>
    </div>
    <div>
      <p class="text-2xl font-black text-cb-primary">24/7</p>
      <p class="text-xs text-slate-500 mt-1 font-semibold">Support disponible</p>
    </div>
  </div>
</section>

<?php $view->end() ?>
