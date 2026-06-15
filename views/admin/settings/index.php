<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="mb-6 flex items-center justify-between">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Paramètres applicatifs</h1>
    <p class="text-sm text-slate-500 mt-1">Configuration globale du système</p>
  </div>
  <div class="flex items-center gap-2">
    <!-- Export -->
    <a href="<?= e(url('admin/settings/export')) ?>"
       class="inline-flex items-center gap-1.5 text-sm px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600">
      <i data-lucide="download" class="w-4 h-4"></i> Exporter
    </a>
    <!-- Import trigger -->
    <button type="button" onclick="document.getElementById('import-modal').classList.remove('hidden')"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600">
      <i data-lucide="upload" class="w-4 h-4"></i> Importer
    </button>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-5">

  <!-- ── Sidebar ──────────────────────────────────────────────────── -->
  <aside class="bg-white rounded-2xl shadow-soft p-3 h-fit lg:sticky lg:top-20">
    <nav class="space-y-1">
      <?php foreach ($categories as $key => $info):
        $active = $cat === $key;
        $count  = $catCounts[$key] ?? 0;
      ?>
      <a href="<?= e(url('admin/settings?cat='.urlencode($key))) ?>"
         class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm
                <?= $active ? 'bg-cb-primary text-white font-semibold' : 'text-slate-700 hover:bg-slate-50' ?>">
        <i data-lucide="<?= e($info['icon']) ?>" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1"><?= e($info['label']) ?></span>
        <?php if ($count): ?>
          <span class="text-xs <?= $active ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-500' ?> px-1.5 py-0.5 rounded-full"><?= $count ?></span>
        <?php endif ?>
      </a>
      <?php endforeach ?>
    </nav>
  </aside>

  <!-- ── Main form ────────────────────────────────────────────────── -->
  <div class="space-y-4">

    <!-- Header carte -->
    <div class="bg-white rounded-2xl shadow-soft p-5 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center <?= e($categories[$cat]['color']) ?>">
          <i data-lucide="<?= e($categories[$cat]['icon']) ?>" class="w-5 h-5"></i>
        </span>
        <div>
          <h2 class="font-bold text-slate-800"><?= e($categories[$cat]['label']) ?></h2>
          <p class="text-xs text-slate-500"><?= count($settings) ?> paramètre(s)</p>
        </div>
      </div>
      <!-- Reset catégorie -->
      <form method="post" action="<?= e(url('admin/settings/reset')) ?>"
            onsubmit="return confirm('Réinitialiser tous les paramètres de cette catégorie ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="cat" value="<?= e($cat) ?>">
        <button class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500">
          <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Réinitialiser
        </button>
      </form>
    </div>

    <!-- Logo upload (company only) -->
    <?php if ($cat === 'company'): ?>
    <div class="bg-white rounded-2xl shadow-soft p-5">
      <h3 class="font-semibold text-sm text-slate-700 mb-3 flex items-center gap-2">
        <i data-lucide="image" class="w-4 h-4 text-violet-500"></i> Logo société
      </h3>
      <div class="flex items-center gap-5">
        <?php if ($logoPath): ?>
          <img src="<?= e(url($logoPath)) ?>" alt="Logo" class="h-16 object-contain rounded-lg border border-slate-200 p-1">
        <?php else: ?>
          <div class="w-16 h-16 rounded-lg border-2 border-dashed border-slate-200 flex items-center justify-center text-slate-300">
            <i data-lucide="image-off" class="w-6 h-6"></i>
          </div>
        <?php endif ?>
        <form method="post" action="<?= e(url('admin/settings/logo')) ?>" enctype="multipart/form-data" class="flex items-center gap-2">
          <?= csrf_field() ?>
          <input type="file" name="logo" accept="image/*"
                 class="text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
          <button class="text-sm px-3 py-1.5 bg-violet-600 text-white rounded-lg hover:bg-violet-700">
            Mettre à jour
          </button>
        </form>
      </div>
    </div>
    <?php endif ?>

    <!-- SMTP test (mail only) -->
    <?php if ($cat === 'mail'): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4"
         x-data="{ to:'', loading:false, result:null }">
      <h3 class="font-semibold text-sm text-blue-800 mb-2 flex items-center gap-2">
        <i data-lucide="send" class="w-4 h-4"></i> Test de connexion SMTP
      </h3>
      <div class="flex items-center gap-2">
        <input type="email" x-model="to" placeholder="Adresse de test"
               class="flex-1 text-sm px-3 py-2 rounded-lg border border-blue-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-400">
        <button @click="
            loading=true; result=null;
            fetch('<?= e(url('admin/settings/test-smtp')) ?>', {
              method:'POST',
              headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':'<?= csrf_token() ?>'},
              body:'to='+encodeURIComponent(to)+'&_csrf=<?= csrf_token() ?>'
            }).then(r=>r.json()).then(d=>{result=d;loading=false}).catch(()=>{result={ok:false,message:'Erreur réseau'};loading=false})
          "
          :disabled="loading"
          class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-60">
          <span x-show="!loading">Tester</span>
          <span x-show="loading">…</span>
        </button>
      </div>
      <div x-show="result" class="mt-2 text-sm rounded-lg px-3 py-2"
           :class="result?.ok ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
           x-text="result?.message"></div>
    </div>
    <?php endif ?>

    <!-- Settings form -->
    <form method="post" action="<?= e(url('admin/settings')) ?>"
          class="bg-white rounded-2xl shadow-soft p-6">
      <?= csrf_field() ?>
      <input type="hidden" name="cat" value="<?= e($cat) ?>">

      <div class="space-y-5">
        <?php
        $prevGroup = null;
        foreach ($settings as $s):
          $type   = $s['setting_type'];
          $key    = $s['setting_key'];
          $value  = $s['setting_value'];
          $label  = $s['label'];
          $desc   = $s['description'];
          $group  = (int)($s['sort_order'] / 100);
          if ($group !== $prevGroup && $prevGroup !== null):
        ?>
          <hr class="border-slate-100">
        <?php endif; $prevGroup = $group; ?>

        <div class="grid grid-cols-1 md:grid-cols-[1fr_2fr] gap-4 items-start">
          <div>
            <label class="block text-sm font-medium text-slate-700"><?= e($label ?: $key) ?></label>
            <?php if ($desc): ?>
              <p class="text-xs text-slate-400 mt-0.5"><?= e($desc) ?></p>
            <?php endif ?>
            <?php if ($s['updated_at'] ?? null): ?>
              <p class="text-xs text-slate-300 mt-1">Modifié <?= e(date('d/m/y H:i', strtotime($s['updated_at']))) ?></p>
            <?php endif ?>
          </div>
          <div>
            <?php if ($type === 'bool'): ?>
              <!-- Toggle switch — name utilise la notation tableau pour préserver le point dans la clé -->
              <label class="inline-flex items-center cursor-pointer gap-3">
                <input type="hidden" name="settings[<?= e($key) ?>]" value="0">
                <input type="checkbox" name="settings[<?= e($key) ?>]" value="1" <?= $value ? 'checked' : '' ?>
                       class="sr-only peer">
                <div class="relative w-11 h-6 bg-slate-200 peer-checked:bg-cb-primary rounded-full transition-colors
                            after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white
                            after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                <span class="text-sm text-slate-600"><?= $value ? 'Activé' : 'Désactivé' ?></span>
              </label>

            <?php elseif ($type === 'secret'): ?>
              <input type="password" name="settings[<?= e($key) ?>]" placeholder="••••••••••  (laisser vide = inchangé)"
                     autocomplete="new-password"
                     class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-cb-primary/40">

            <?php elseif ($type === 'text'): ?>
              <textarea name="settings[<?= e($key) ?>]" rows="3"
                        class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-cb-primary/40"><?= e($value) ?></textarea>

            <?php elseif ($type === 'int'): ?>
              <input type="number" name="settings[<?= e($key) ?>]" value="<?= e($value) ?>"
                     class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-cb-primary/40">

            <?php else: ?>
              <input type="text" name="settings[<?= e($key) ?>]" value="<?= e($value) ?>"
                     class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-cb-primary/40">
            <?php endif ?>
          </div>
        </div>
        <?php endforeach ?>
      </div>

      <?php if (can('admin.settings.edit')): ?>
      <div class="mt-8 pt-5 border-t border-slate-100 flex justify-end">
        <button class="inline-flex items-center gap-2 px-5 py-2.5 bg-cb-primary text-white text-sm font-semibold rounded-xl hover:bg-cb-primary/90 transition">
          <i data-lucide="save" class="w-4 h-4"></i> Enregistrer
        </button>
      </div>
      <?php endif ?>
    </form>

  </div><!-- end main -->
</div><!-- end grid -->

<!-- ── Import modal ─────────────────────────────────────────────── -->
<div id="import-modal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-slate-800 flex items-center gap-2">
        <i data-lucide="upload" class="w-5 h-5 text-slate-500"></i> Importer des paramètres
      </h3>
      <button onclick="document.getElementById('import-modal').classList.add('hidden')"
              class="text-slate-400 hover:text-slate-600">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <p class="text-sm text-slate-500 mb-4">Sélectionnez un fichier JSON exporté depuis City Bus ERP. Les secrets ne sont jamais remplacés.</p>
    <form method="post" action="<?= e(url('admin/settings/import')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="file" name="settings_file" accept=".json"
             class="block w-full text-sm text-slate-600 mb-4 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')"
                class="px-4 py-2 text-sm border border-slate-200 rounded-lg hover:bg-slate-50">Annuler</button>
        <button class="px-4 py-2 text-sm bg-cb-primary text-white rounded-lg hover:bg-cb-primary/90">
          Importer
        </button>
      </div>
    </form>
  </div>
</div>

<?php $view->end() ?>