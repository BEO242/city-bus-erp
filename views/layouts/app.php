<?php /** @var \CityBus\Core\View $view */
$user = auth();
$role = $user['role'] ?? '';
$flash = \CityBus\Core\Session::pullFlash();
$current = $_SERVER['REQUEST_URI'] ?? '';

$navGroups = [
  'ops' => [
    'label' => 'Opérations', 'icon' => 'zap',
    'items' => [
      ['label' => 'Voyages',      'icon' => 'route',      'url' => url('voyages'),                'perm' => 'voyages.view'],
      ['label' => 'PNR / Résa',   'icon' => 'bookmark',   'url' => url('billetterie/reservations'), 'perm' => 'reservations.view'],
      ['label' => 'Billetterie',  'icon' => 'ticket',     'url' => url('billetterie/select-trip'), 'perm' => 'billetterie.view'],
      ['label' => 'Fret & Bagages','icon' => 'package',     'url' => url('operations/fret'),         'perm' => 'fret.view'],
      ['label' => 'Pré-imprimés', 'icon' => 'tickets',    'url' => url('billetterie/preprint'),    'perm' => 'billetterie.preprint'],
      ['label' => 'Tickets urbains','icon' => 'bus',       'url' => url('billetterie/urban-tickets'),'perm' => 'billetterie.preprint'],
      ['label' => 'Contrôle',     'icon' => 'scan-line',  'url' => url('controle'),               'perm' => 'controle.view'],
    ],
  ],
  'commerce' => [
    'label' => 'Commerce', 'icon' => 'shopping-bag',
    'guard' => 'crm.view|vouchers.view|corporate.view|partners.view',
    'items' => [
      ['label' => 'CRM clients 360°','icon' => 'user-search',          'url' => url('crm/customers'),        'perm' => 'crm.view'],
      ['label' => 'Fidélité',        'icon' => 'crown',                 'url' => url('crm/loyalty'),          'perm' => 'crm.view'],
      ['label' => 'Réclamations',    'icon' => 'message-circle-warning','url' => url('crm/complaints'),       'perm' => 'crm.view'],
      ['label' => 'Avis clients',    'icon' => 'star',                  'url' => url('crm/feedback'),         'perm' => 'feedback.view'],
      ['label' => 'Avoirs',          'icon' => 'gift',                  'url' => url('commerce/vouchers'),    'perm' => 'vouchers.view'],
      ['label' => 'Corporate B2B',   'icon' => 'building-2',            'url' => url('commerce/corporate'),   'perm' => 'corporate.view'],
      ['label' => 'Partenaires',     'icon' => 'handshake',             'url' => url('commerce/partners'),    'perm' => 'partners.view'],
    ],
  ],
  'ref' => [
    'label' => 'Référentiel', 'icon' => 'database',
    'guard' => 'referentiel.view',
    'items' => [
      ['label' => 'Lignes',     'icon' => 'map',        'url' => url('referentiel/lines'),    'perm' => 'referentiel.view'],
      ['label' => 'Tarifs',     'icon' => 'tags',       'url' => url('referentiel/tariffs'),  'perm' => 'referentiel.view'],
      ['label' => 'Villes',     'icon' => 'map-pin',    'url' => url('referentiel/cities'),   'perm' => 'referentiel.view'],
      ['label' => 'Agences',    'icon' => 'building-2', 'url' => url('referentiel/agencies'), 'perm' => 'referentiel.view'],
      ['label' => 'Véhicules',  'icon' => 'truck',      'url' => url('referentiel/vehicules'), 'perm' => 'referentiel.view'],
      ['label' => 'Chauffeurs', 'icon' => 'id-card',    'url' => url('referentiel/drivers'),  'perm' => 'referentiel.view'],
      ['label' => 'Convoyeurs','icon' => 'users',      'url' => url('referentiel/convoyeurs'),'perm' => 'referentiel.view'],
    ],
  ],
  'finance' => [
    'label' => 'Finance', 'icon' => 'landmark',
    'guard' => 'finance.treasury.view|caisse.view',
    'items' => [
      ['label' => 'Transactions',       'icon' => 'arrow-left-right', 'url' => url('finance/treasury/transactions'), 'perm' => 'finance.treasury.view'],
      ['label' => 'Virements',          'icon' => 'repeat-2',         'url' => url('finance/treasury/transfer'),     'perm' => 'finance.treasury.manage'],
      ['label' => 'Caisses & Clôtures', 'icon' => 'lock',             'url' => url('finance/treasury/closures'),     'perm' => 'finance.treasury.view'],
      ['label' => 'Catégories',         'icon' => 'tags',             'url' => url('finance/treasury/categories'),   'perm' => 'finance.treasury.view'],
      ['label' => 'Caisses',            'icon' => 'wallet',           'url' => url('finance/caisses'),               'perm' => 'caisse.view'],
      ['label' => 'Remboursements',    'icon' => 'undo-2',           'url' => url('finance/refunds'),               'perm' => 'finance.treasury.view'],
    ],
  ],
  'rh' => [
    'label' => 'Ressources humaines', 'icon' => 'users',
    'guard' => 'rh.view',
    'items' => [
      ['label' => 'Tableau de bord', 'icon' => 'layout-dashboard', 'url' => url('rh/dashboard'),   'perm' => 'rh.view'],
      ['label' => 'Employés',        'icon' => 'user-round',        'url' => url('rh/employees'),   'perm' => 'rh.view'],
      ['label' => 'Postes',          'icon' => 'briefcase',         'url' => url('rh/positions'),   'perm' => 'rh.view'],
      ['label' => 'Planning',        'icon' => 'calendar-days',     'url' => url('rh/schedule'),    'perm' => 'rh.view'],
      ['label' => 'Paie',            'icon' => 'banknote',          'url' => url('rh/payroll'),     'perm' => 'rh.payroll'],
      ['label' => 'Heures service',  'icon' => 'clock',             'url' => url('rh/hos'),         'perm' => 'hos.view'],
    ],
  ],
  'sys' => [
    'label' => 'Administration', 'icon' => 'shield-check',
    'guard' => 'admin.users.view|admin.roles.view|admin.settings.view|admin.audit.view|notifications.view|api.tokens.manage',
    'items' => [
      ['label' => 'Utilisateurs',        'icon' => 'user-cog',    'url' => url('admin/users'),         'perm' => 'admin.users.view'],
      ['label' => 'Rôles & permissions', 'icon' => 'shield',      'url' => url('admin/roles'),         'perm' => 'admin.roles.view'],
      ['label' => 'Paramètres',          'icon' => 'settings',    'url' => url('admin/settings'),      'perm' => 'admin.settings.view'],
      ['label' => 'Notifications',       'icon' => 'bell',        'url' => url('admin/notifications'), 'perm' => 'notifications.view'],
      ['label' => 'API publique',        'icon' => 'plug',        'url' => url('admin/api'),           'perm' => 'api.tokens.manage'],
      ['label' => 'Audit',               'icon' => 'file-search', 'url' => url('admin/audit'),         'perm' => 'admin.audit.view'],
    ],
  ],
];
// Auto-open the group that contains the current page (longest prefix match wins)
$currentPath     = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$cleanCurrentNav = rtrim($currentPath, '/');
$defaultGrp      = 'ops';
$bestNavLen      = -1;
foreach ($navGroups as $gKey => $g) {
  foreach ($g['items'] as $gi) {
    $giPath = rtrim(parse_url($gi['url'], PHP_URL_PATH) ?? '', '/');
    if ($giPath === '') continue;
    if ($cleanCurrentNav === $giPath || str_starts_with($cleanCurrentNav, $giPath . '/')) {
      if (strlen($giPath) > $bestNavLen) {
        $bestNavLen = strlen($giPath);
        $defaultGrp = $gKey;
      }
    }
  }
}

$navAlerts = $navAlerts ?? [];
?><!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($title ?? 'City Bus ERP') ?> · City Bus</title>

  <link rel="manifest" href="<?= e(asset('manifest.json')) ?>">
  <meta name="theme-color" content="#C62828">
  <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">

  <!-- Init theme avant rendu pour éviter le flash -->
  <script>
    (function () {
      var saved = localStorage.getItem('cb-theme') || 'light';
      document.documentElement.setAttribute('data-theme', saved);
    })();
  </script>

  <!-- Tailwind précompilé (CLI standalone, AOT, instant load) — cache-buster sur mtime -->
  <?php
    $cssBust = function (string $path): string {
      $abs = BASE_PATH . '/public/' . ltrim($path, '/');
      $mtime = is_file($abs) ? filemtime($abs) : time();
      return e(asset($path)) . '?v=' . $mtime;
    };
  ?>
  <link rel="stylesheet" href="<?= $cssBust('css/tailwind-built.css') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $cssBust('css/app.css') ?>">
  <link rel="stylesheet" href="<?= $cssBust('css/theme-cockpit.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
  <script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" defer></script>
  <script>window._appBase = '<?= e(rtrim(url(''), '/')) ?>';</script>
  <script src="<?= e(asset('js/media-manager.js')) ?>" defer></script>
  <style>
    [x-cloak] { display: none }
    .sidebar-scroll { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.15) transparent; }
    .sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 2px; }
  </style>
  <?= $view->section('styles') ?>
</head>
<?php
  $initials     = strtoupper(substr($user['first_name'] ?? '?', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
  $firstInitial = strtoupper(substr($user['first_name'] ?? '?', 0, 1));
?>
<body class="font-sans antialiased min-h-screen">

<div x-data="{ sidebar: true, mobileOpen: false, grp: '<?= $defaultGrp ?>' }" class="flex min-h-screen">

  <!-- Overlay mobile -->
  <div x-show="mobileOpen" @click="mobileOpen=false" class="fixed inset-0 bg-black/50 z-30 lg:hidden" x-transition></div>

  <!-- ═══════════════════════════════════════════════════════════════
       SIDEBAR (identique à v1, fond rouge cardinal — signature CityBus)
  ═══════════════════════════════════════════════════════════════ -->
  <aside :class="[sidebar ? 'lg:w-64' : 'lg:w-[60px]', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']"
         class="sidebar-cb text-white flex-shrink-0 transition-all duration-200 flex flex-col fixed lg:sticky top-0 h-screen shadow-2xl shadow-black/30 z-40 w-64 lg:flex">

    <!-- Logo -->
    <div class="h-16 flex items-center justify-between px-4 border-b border-white/10 halftone sidebar-cb-header shrink-0">
      <div class="flex items-center gap-2.5 overflow-hidden min-w-0">
        <div class="w-8 h-8 rounded-xl bg-cb-yellow flex items-center justify-center shadow-md shrink-0">
          <i data-lucide="bus" class="w-4 h-4 text-cb-secondary"></i>
        </div>
        <div x-show="sidebar" class="leading-none min-w-0">
          <div class="font-black text-white text-sm tracking-tight">CITY<span class="text-cb-yellow">BUS</span></div>
          <div class="text-[8px] text-white/50 tracking-widest uppercase">Backoffice ERP</div>
        </div>
      </div>
      <button @click="sidebar=!sidebar" class="text-white/60 hover:text-white transition shrink-0 ml-1">
        <i data-lucide="chevrons-left" class="w-4 h-4" x-show="sidebar"></i>
        <i data-lucide="chevrons-right" class="w-4 h-4" x-show="!sidebar"></i>
      </button>
    </div>

    <!-- Statut système -->
    <div x-show="sidebar" class="px-4 py-1.5 bg-black/10 border-b border-white/10 shrink-0">
      <div class="flex items-center gap-2">
        <div class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse shrink-0"></div>
        <span class="text-[9px] text-white/50 uppercase tracking-wider font-semibold">Système opérationnel</span>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-2.5 py-3 overflow-y-auto sidebar-scroll text-sm">
      <?php
        $_dbPath = rtrim(parse_url(url('dashboard'), PHP_URL_PATH) ?? '', '/');
        $dbActive = (rtrim($currentPath, '/') === $_dbPath);
      ?>
      <a href="<?= e(url('dashboard')) ?>" title="Tableau de bord"
         class="flex items-center gap-3 px-3 py-2.5 mb-2 rounded-xl transition group <?= $dbActive
           ? 'bg-cb-yellow text-cb-secondary font-bold shadow-md shadow-black/20'
           : 'text-white hover:bg-white/10' ?>">
        <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
        <span x-show="sidebar" class="whitespace-nowrap">Tableau de bord</span>
      </a>

      <!-- Tour de contrôle (cockpit-style) -->
      <?php if (can('ops.control_tower.view')): $ctActive = rtrim($currentPath, '/') === rtrim(parse_url(url('ops/control-tower'), PHP_URL_PATH) ?? '', '/'); ?>
      <a href="<?= e(url('ops/control-tower')) ?>" title="Tour de contrôle"
         class="flex items-center gap-3 px-3 py-2.5 mb-2 rounded-xl transition group <?= $ctActive
           ? 'bg-cb-yellow text-cb-secondary font-bold shadow-md'
           : 'text-white hover:bg-white/10' ?>">
        <i data-lucide="radar" class="w-4 h-4 shrink-0"></i>
        <span x-show="sidebar" class="whitespace-nowrap">Tour de contrôle</span>
        <span x-show="sidebar" class="ml-auto w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
      </a>
      <?php endif ?>

      <div class="border-t border-white/10 mb-2"></div>

      <?php foreach ($navGroups as $gKey => $group):
        if (!empty($group['guard'])) {
          $guards = explode('|', $group['guard']);
          $allowed = false;
          foreach ($guards as $g) { if (can(trim($g))) { $allowed = true; break; } }
          if (!$allowed) continue;
        }
        $visibleItems = array_filter($group['items'], fn($i) => !$i['perm'] || can($i['perm']));
        if (empty($visibleItems)) continue;
      ?>
      <div class="mb-1 rounded-xl overflow-hidden transition-all"
           :class="grp === '<?= $gKey ?>' ? 'bg-black/20 ring-1 ring-white/10' : ''">
        <button @click="grp = (grp === '<?= $gKey ?>' ? '' : '<?= $gKey ?>')" x-show="sidebar"
                class="w-full flex items-center justify-between px-3 py-2 text-left select-none group transition rounded-xl"
                :class="grp === '<?= $gKey ?>'
                  ? 'bg-white/10 text-white'
                  : 'hover:bg-white/5 text-white/50 hover:text-white'">
          <div class="flex items-center gap-2">
            <i data-lucide="<?= e($group['icon']) ?>" class="w-3.5 h-3.5 transition"
               :class="grp === '<?= $gKey ?>' ? 'text-cb-yellow' : 'text-white/40 group-hover:text-white/70'"></i>
            <span class="text-[10px] uppercase tracking-widest font-extrabold transition"
                  :class="grp === '<?= $gKey ?>' ? 'text-white' : 'text-white/50 group-hover:text-white'"><?= e($group['label']) ?></span>
          </div>
          <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform duration-200"
             :class="grp === '<?= $gKey ?>' ? 'text-cb-yellow rotate-0' : 'text-white/30 -rotate-90'"></i>
        </button>

        <div x-show="!sidebar || grp === '<?= $gKey ?>'"
             x-transition:enter="transition-all duration-150 ease-out"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="px-1.5 pb-1.5 space-y-0.5">
          <?php
          // Longest prefix match: only one item per group is active
          $cleanCurItem  = rtrim($currentPath, '/');
          $bestItemLen   = -1;
          $activeItemUrl = '';
          foreach ($visibleItems as $_it) {
            $p = rtrim(parse_url($_it['url'], PHP_URL_PATH) ?? '', '/');
            if ($p === '') continue;
            if ($cleanCurItem === $p || str_starts_with($cleanCurItem, $p . '/')) {
              if (strlen($p) > $bestItemLen) { $bestItemLen = strlen($p); $activeItemUrl = $_it['url']; }
            }
          }
          foreach ($visibleItems as $item):
            $active = ($item['url'] === $activeItemUrl);
          ?>
            <a href="<?= e($item['url']) ?>" title="<?= e($item['label']) ?>"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition group text-sm <?= $active
                 ? 'bg-cb-yellow text-cb-secondary font-bold shadow-sm'
                 : 'text-white/80 hover:bg-white/10 hover:text-white' ?>">
              <i data-lucide="<?= e($item['icon']) ?>" class="w-4 h-4 shrink-0 <?= $active ? 'text-cb-secondary' : 'text-white/50 group-hover:text-white group-hover:scale-110 transition-transform' ?>"></i>
              <span x-show="sidebar" class="whitespace-nowrap font-medium"><?= e($item['label']) ?></span>
            </a>
          <?php endforeach ?>
        </div>
      </div>
      <?php endforeach ?>
    </nav>

    <!-- Lien site public -->
    <div class="px-3 py-2 border-t border-white/10 shrink-0">
      <a href="<?= e(url('')) ?>" target="_blank" rel="noopener" title="Voir le site public"
         class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-xl text-white hover:bg-white/10 transition text-xs">
        <i data-lucide="globe" class="w-4 h-4 shrink-0"></i>
        <span x-show="sidebar" class="whitespace-nowrap flex-1">Site public</span>
        <i data-lucide="external-link" class="w-3 h-3 shrink-0" x-show="sidebar"></i>
      </a>
    </div>

    <!-- Profil bas sidebar -->
    <div class="p-3 border-t border-white/10 bg-black/10 shrink-0">
      <div class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-white/10 cursor-pointer transition">
        <div class="w-8 h-8 rounded-xl bg-cb-yellow flex items-center justify-center font-black text-cb-secondary text-sm shadow-md shrink-0">
          <?= e($initials) ?>
        </div>
        <div x-show="sidebar" class="min-w-0 flex-1">
          <p class="text-white text-xs font-bold truncate"><?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></p>
          <p class="text-white/60 text-[10px] truncate"><?= e(ucfirst($role)) ?></p>
        </div>
      </div>
    </div>
  </aside>

  <!-- ═══════════════════════════════════════════════════════════════
       ZONE PRINCIPALE — Cockpit topbar + canvas + statusbar
  ═══════════════════════════════════════════════════════════════ -->
  <div class="flex-1 flex flex-col min-w-0">

    <!-- ─── COCKPIT TOPBAR ─── -->
    <header
      x-data="{
        notif: false, quickAdd: false, profile: false,
        clock: '',
        initClock() {
          const fmt = () => new Date().toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
          this.clock = fmt();
          setInterval(()=>{ this.clock = fmt(); }, 1000);
        }
      }"
      x-init="initClock()"
      class="cockpit-topbar"
    >
      <button @click="mobileOpen=!mobileOpen" class="lg:hidden cockpit-icon-btn">
        <i data-lucide="menu" class="w-4 h-4"></i>
      </button>

      <div class="live">
        <span class="cockpit-pulse"></span>
        <span class="hidden sm:inline">LIVE</span>
      </div>
      <span class="sep hidden md:inline">|</span>
      <span class="muted hidden md:inline" style="font-family:ui-monospace,monospace" x-text="clock"></span>
      <span class="sep hidden lg:inline">|</span>
      <span class="accent hidden lg:inline" style="font-family:ui-monospace,monospace"><?= e(strtoupper(date('d M Y'))) ?></span>

      <!-- Breadcrumb / Title -->
      <div class="flex items-center gap-2 ml-2 min-w-0">
        <i data-lucide="home" class="w-3.5 h-3.5" style="color:var(--cb-primary)"></i>
        <i data-lucide="chevron-right" class="w-3 h-3" style="color:var(--text-faint)"></i>
        <span class="font-bold text-sm truncate" style="color:var(--text-primary)"><?= e($title ?? 'Tableau de bord') ?></span>
      </div>

      <!-- Spacer -->
      <div class="flex-1"></div>

      <!-- Badge agence -->
      <?php if (!empty($user['agency_name'])): ?>
        <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 h-7 rounded text-xs font-semibold"
              style="background:var(--cb-bg);color:var(--cb-primary)">
          <i data-lucide="map-pin" class="w-3 h-3"></i><?= e($user['agency_name']) ?>
        </span>
      <?php endif ?>

      <!-- Theme toggle -->
      <button class="theme-toggle" onclick="toggleTheme()" title="Basculer mode sombre / clair">
        <svg class="moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
      </button>

      <!-- Notifications -->
      <div class="relative">
        <button @click="notif=!notif; quickAdd=false; profile=false" class="cockpit-icon-btn relative">
          <i data-lucide="bell" class="w-4 h-4"></i>
          <?php if (!empty($navAlerts)): ?>
            <span class="absolute -top-1 -right-1 w-4 h-4 flex items-center justify-center rounded-full text-[9px] font-black leading-none"
                  style="background:var(--cb-accent);color:var(--cb-secondary)"><?= count($navAlerts) ?></span>
          <?php endif ?>
        </button>
        <div x-show="notif" @click.outside="notif=false" x-cloak
          class="absolute right-0 top-9 w-80 rounded-lg border z-50 overflow-hidden"
          style="background:var(--bg-panel);border-color:var(--border-default);box-shadow:var(--shadow-pop)">
          <div class="px-4 py-2.5 flex items-center justify-between" style="background:linear-gradient(135deg,var(--cb-primary),var(--cb-secondary))">
            <span class="text-white font-semibold text-sm">Notifications</span>
            <?php if (!empty($navAlerts)): ?>
              <span class="text-[10px] font-black px-2 py-0.5 rounded" style="background:var(--cb-accent);color:var(--cb-secondary)"><?= count($navAlerts) ?> nouv.</span>
            <?php endif ?>
          </div>
          <ul class="max-h-64 overflow-y-auto" style="border-color:var(--border-soft)">
            <?php if (!empty($navAlerts)): foreach ($navAlerts as $a): ?>
              <li class="flex gap-3 px-4 py-3 cursor-pointer border-b" style="border-color:var(--border-soft)" onmouseover="this.style.background='var(--bg-row-hover)'" onmouseout="this.style.background=''">
                <div class="w-7 h-7 rounded flex items-center justify-center shrink-0 mt-0.5" style="background:var(--cb-bg)">
                  <i data-lucide="<?= e($a['icon'] ?? 'bell') ?>" class="w-3.5 h-3.5" style="color:var(--cb-primary)"></i>
                </div>
                <p class="text-xs font-medium mt-1" style="color:var(--text-secondary)"><?= e($a['message'] ?? $a['label'] ?? '') ?></p>
              </li>
            <?php endforeach; else: ?>
              <li class="px-4 py-6 text-center text-sm" style="color:var(--text-faint)">Aucune notification</li>
            <?php endif ?>
          </ul>
        </div>
      </div>

      <!-- Créer rapide -->
      <div class="relative">
        <button @click="quickAdd=!quickAdd; notif=false; profile=false"
          class="flex items-center gap-1.5 text-white text-xs font-bold px-2.5 h-7 rounded transition"
          style="background:var(--cb-primary)" onmouseover="this.style.background='var(--cb-secondary)'" onmouseout="this.style.background='var(--cb-primary)'">
          <i data-lucide="plus" class="w-3.5 h-3.5"></i>
          <span class="hidden sm:inline">Créer</span>
        </button>
        <div x-show="quickAdd" @click.outside="quickAdd=false" x-cloak
          class="absolute right-0 top-9 w-44 rounded-lg border overflow-hidden z-50 py-1 text-sm"
          style="background:var(--bg-panel);border-color:var(--border-default);box-shadow:var(--shadow-pop)">
          <a href="<?= e(url('billetterie/select-trip')) ?>" class="flex items-center gap-2.5 px-4 py-2.5 transition" style="color:var(--text-secondary)" onmouseover="this.style.background='var(--cb-bg)';this.style.color='var(--cb-primary)'" onmouseout="this.style.background='';this.style.color='var(--text-secondary)'">
            <i data-lucide="ticket" class="w-4 h-4"></i> Ticket
          </a>
          <a href="<?= e(url('voyages/create')) ?>" class="flex items-center gap-2.5 px-4 py-2.5 transition" style="color:var(--text-secondary)" onmouseover="this.style.background='var(--cb-bg)';this.style.color='var(--cb-primary)'" onmouseout="this.style.background='';this.style.color='var(--text-secondary)'">
            <i data-lucide="bus" class="w-4 h-4"></i> Voyage
          </a>
          <?php if (can('cargo.create')): ?>
          <a href="<?= e(url('cargo/parcels/create')) ?>" class="flex items-center gap-2.5 px-4 py-2.5 transition" style="color:var(--text-secondary)" onmouseover="this.style.background='var(--cb-bg)';this.style.color='var(--cb-primary)'" onmouseout="this.style.background='';this.style.color='var(--text-secondary)'">
            <i data-lucide="package" class="w-4 h-4"></i> Colis
          </a>
          <?php endif ?>
          <?php if (can('rh.view')): ?>
          <a href="<?= e(url('rh/employees')) ?>" class="flex items-center gap-2.5 px-4 py-2.5 transition" style="color:var(--text-secondary)" onmouseover="this.style.background='var(--cb-bg)';this.style.color='var(--cb-primary)'" onmouseout="this.style.background='';this.style.color='var(--text-secondary)'">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Agent
          </a>
          <?php endif ?>
        </div>
      </div>

      <!-- Profil -->
      <div class="relative">
        <button @click="profile=!profile; notif=false; quickAdd=false"
          class="flex items-center gap-2 rounded pl-1 pr-2 h-7 transition"
          style="background:var(--bg-panel-2);color:var(--text-primary)">
          <div class="w-5 h-5 rounded flex items-center justify-center text-white font-black text-[10px]"
               style="background:linear-gradient(135deg,var(--cb-primary),var(--cb-secondary))"><?= e($firstInitial) ?></div>
          <span class="hidden sm:block text-xs font-semibold truncate max-w-[80px]"><?= e($user['first_name'] ?? '') ?></span>
          <i data-lucide="chevron-down" class="w-3 h-3 shrink-0" style="color:var(--text-faint)" :class="profile ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="profile" @click.outside="profile=false" x-cloak
          class="absolute right-0 top-9 w-52 rounded-lg border overflow-hidden z-50 py-1 text-sm"
          style="background:var(--bg-panel);border-color:var(--border-default);box-shadow:var(--shadow-pop)">
          <div class="px-4 py-3 border-b" style="border-color:var(--border-soft)">
            <p class="font-bold text-sm" style="color:var(--text-primary)"><?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></p>
            <p class="text-[11px] mt-0.5" style="color:var(--text-faint)"><?= e($user['email'] ?? '') ?></p>
          </div>
          <a href="<?= e(url('profile')) ?>" class="flex items-center gap-2.5 px-4 py-2.5 transition" style="color:var(--text-secondary)" onmouseover="this.style.background='var(--bg-row-hover)'" onmouseout="this.style.background=''">
            <i data-lucide="user" class="w-4 h-4" style="color:var(--cb-primary)"></i> Mon profil
          </a>
          <div class="border-t my-1" style="border-color:var(--border-soft)"></div>
          <form method="post" action="<?= e(url('logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="w-full text-left flex items-center gap-2.5 px-4 py-2.5 transition" style="color:var(--cb-primary)" onmouseover="this.style.background='var(--cb-bg)'" onmouseout="this.style.background=''">
              <i data-lucide="log-out" class="w-4 h-4"></i> Déconnexion
            </button>
          </form>
        </div>
      </div>
    </header>

    <!-- Flash messages -->
    <?php if (!empty($flash)): ?>
      <div class="px-5 pt-4 space-y-2">
        <?php foreach ($flash as $type => $msgs): foreach ($msgs as $msg):
          $cls = match($type) {
            'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
            'danger'  => 'bg-rose-50 border-rose-200 text-rose-800',
            'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
            default   => 'bg-cb-bg border-cb-accent/30 text-cb-dark',
          };
          $icon = match($type) {
            'success' => 'check-circle-2', 'danger' => 'x-circle',
            'warning' => 'alert-triangle', default => 'info',
          };
        ?>
          <div class="flex items-center gap-3 p-3.5 rounded-xl border <?= $cls ?>"
               x-data="{ show:true }" x-show="show" x-transition.duration.300ms x-init="setTimeout(()=>show=false, 5000)">
            <i data-lucide="<?= e($icon) ?>" class="w-5 h-5 shrink-0"></i>
            <span class="flex-1"><?= e($msg) ?></span>
            <button @click="show=false"><i data-lucide="x" class="w-4 h-4"></i></button>
          </div>
        <?php endforeach; endforeach ?>
      </div>
    <?php endif ?>

    <!-- ─── CANVAS principal ─── -->
    <main class="flex-1 p-4" style="background:var(--bg-canvas)">
      <?= $__content ?? '' ?>
    </main>

    <!-- ─── COCKPIT STATUSBAR ─── -->
    <footer class="cockpit-statusbar">
      <span><span class="ok">●</span> DB OK</span>
      <span><span class="ok">●</span> API</span>
      <span class="hidden md:inline"><span class="ok">●</span> Backup OK</span>
      <span class="hidden lg:inline" style="margin-left:auto">
        © <?= date('Y') ?> City Bus ERP
        · <?= e($user['email'] ?? '') ?>
        <?php if (!empty($user['agency_name'])): ?> · <?= e($user['agency_name']) ?><?php endif ?>
      </span>
    </footer>
  </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= $view->section('scripts') ?>
<script>
  // Theme toggle
  function toggleTheme() {
    const html = document.documentElement;
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.classList.add('theme-transitioning');
    html.setAttribute('data-theme', next);
    localStorage.setItem('cb-theme', next);
    setTimeout(() => html.classList.remove('theme-transitioning'), 50);
  }

  document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= e(asset('service-worker.js')) ?>').catch(() => {});
  }

  // ── Dirty-form guard (inchangé v1) ──
  (function () {
    const initialized = new WeakSet();
    function formSnapshot(form) { return new URLSearchParams(new FormData(form)).toString(); }
    function shouldWatch(form) {
      if (form.hasAttribute('data-no-dirty')) return false;
      const explicit = form.getAttribute('data-dirty-watch');
      if (explicit !== null) return explicit === '1';
      const methodInput = form.querySelector('input[name="_method"]');
      const method = ((methodInput && methodInput.value) || '').toUpperCase();
      return method === 'PUT' || method === 'PATCH';
    }
    function targetButtons(form) {
      const explicitBtns = Array.from(form.querySelectorAll('[data-dirty-submit]'));
      if (explicitBtns.length) return explicitBtns;
      return Array.from(form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]'))
        .filter(btn => !btn.closest('[data-no-dirty]') && !btn.classList.contains('btn-danger'));
    }
    function initForm(form) {
      if (initialized.has(form) || !shouldWatch(form)) return;
      const submitBtns = targetButtons(form);
      if (!submitBtns.length) return;
      initialized.add(form);
      let initial = formSnapshot(form);
      let dirty = false;
      function lockButton(btn) {
        btn.classList.add('opacity-50', 'cursor-not-allowed');
        btn.dataset.dirtyLocked = '1';
        const hasDynamicDisabled = btn.hasAttribute(':disabled') || btn.hasAttribute('x-bind:disabled') || btn.hasAttribute('v-bind:disabled');
        if (!hasDynamicDisabled) btn.setAttribute('disabled', 'disabled');
      }
      function unlockButton(btn) {
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
        delete btn.dataset.dirtyLocked;
        btn.removeAttribute('disabled');
      }
      function update() {
        const changed = formSnapshot(form) !== initial;
        if (changed === dirty) return;
        dirty = changed;
        submitBtns.forEach(btn => dirty ? unlockButton(btn) : lockButton(btn));
      }
      function resetBaseline() {
        initial = formSnapshot(form);
        dirty = false;
        submitBtns.forEach(btn => lockButton(btn));
      }
      update();
      form.addEventListener('input', update);
      form.addEventListener('change', update);
      form.addEventListener('reset', () => setTimeout(update, 0));
      form.addEventListener('submit', e => { if (!dirty) { e.preventDefault(); update(); } });
      window.addEventListener('pageshow', update);
      setTimeout(resetBaseline, 0);
      if (window.requestAnimationFrame) requestAnimationFrame(() => requestAnimationFrame(resetBaseline));
    }
    function scanForms() { document.querySelectorAll('form').forEach(initForm); }
    scanForms();
    new MutationObserver(scanForms).observe(document.body, {
      subtree: true, childList: true, attributes: true, attributeFilter: ['data-dirty-watch']
    });
  })();
</script>
</body>
</html>
