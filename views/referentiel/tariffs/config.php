<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

// Palette de couleurs disponibles (classes Tailwind)
$colorPalette = [
    'bg-blue-50 text-blue-700 border-blue-200'       => ['dot' => 'bg-blue-500',    'name' => 'Bleu'],
    'bg-violet-50 text-violet-700 border-violet-200' => ['dot' => 'bg-violet-500',  'name' => 'Violet'],
    'bg-emerald-50 text-emerald-700 border-emerald-200'=> ['dot' => 'bg-emerald-500','name' => 'Vert'],
    'bg-amber-50 text-amber-700 border-amber-200'    => ['dot' => 'bg-amber-500',   'name' => 'Ambre'],
    'bg-pink-50 text-pink-700 border-pink-200'       => ['dot' => 'bg-pink-500',    'name' => 'Rose'],
    'bg-rose-50 text-rose-600 border-rose-200'       => ['dot' => 'bg-rose-500',    'name' => 'Corail'],
    'bg-sky-50 text-sky-700 border-sky-200'          => ['dot' => 'bg-sky-500',     'name' => 'Ciel'],
    'bg-indigo-50 text-indigo-700 border-indigo-200' => ['dot' => 'bg-indigo-500',  'name' => 'Indigo'],
    'bg-cyan-50 text-cyan-700 border-cyan-200'       => ['dot' => 'bg-cyan-500',    'name' => 'Cyan'],
    'bg-orange-50 text-orange-700 border-orange-200' => ['dot' => 'bg-orange-500',  'name' => 'Orange'],
    'bg-slate-50 text-slate-700 border-slate-200'    => ['dot' => 'bg-slate-400',   'name' => 'Gris'],
    'bg-yellow-50 text-yellow-700 border-yellow-200' => ['dot' => 'bg-yellow-400',  'name' => 'Jaune'],
    'bg-teal-50 text-teal-700 border-teal-200'       => ['dot' => 'bg-teal-500',    'name' => 'Sarcelle'],
    'bg-lime-50 text-lime-700 border-lime-200'       => ['dot' => 'bg-lime-500',    'name' => 'Lime'],
    'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200'=> ['dot' => 'bg-fuchsia-500','name' => 'Fuchsia'],
];
?>
<?php $view->start('content') ?>

<div x-data="tariffConfig()" x-cloak class="space-y-5">

  <!-- En-tête ───────────────────────────────────────────────────────────────── -->
  <div class="flex items-center gap-3">
    <a href="<?= e(url('referentiel/tariffs')) ?>"
       class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Configuration des tarifs</h1>
      <p class="text-sm text-slate-500">Gérez dynamiquement les types de billets, catégories passager et classes de voyage.</p>
    </div>
  </div>

  <!-- Flash ─────────────────────────────────────────────────────────────────── -->
  <?php
  $flash = \CityBus\Core\Session::pullFlash();
  if (!empty($flash)):
    $flashColors = ['success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'danger'  => 'bg-rose-50 text-rose-600 border-rose-200',
                    'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'info'    => 'bg-sky-50 text-sky-700 border-sky-200'];
    $flashIcons  = ['success' => 'check-circle', 'danger' => 'alert-triangle',
                    'warning' => 'alert-triangle', 'info' => 'info'];
    foreach ($flash as $fType => $fMsgs): foreach ($fMsgs as $fMsg):
      $fc = $flashColors[$fType] ?? $flashColors['info'];
      $fi = $flashIcons[$fType]  ?? 'info';
  ?>
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl border <?= $fc ?>">
      <i data-lucide="<?= $fi ?>" class="w-4 h-4 shrink-0"></i>
      <span class="text-sm font-medium"><?= e($fMsg) ?></span>
    </div>
  <?php endforeach; endforeach; endif ?>

  <!-- ══ 3 Sections ══════════════════════════════════════════════════════════ -->
  <?php foreach ($sections as $typeKey => $section):
    $meta = $section['meta'];
    $rows = $section['rows'];
    $usedCount = 0;
  ?>
  <div id="<?= e($typeKey) ?>" class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">

    <!-- En-tête section -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg bg-cb-bg flex items-center justify-center">
          <i data-lucide="<?= e($meta['icon']) ?>" class="w-4 h-4 text-cb-primary"></i>
        </span>
        <div>
          <h2 class="font-semibold text-slate-900"><?= e($meta['label']) ?></h2>
          <p class="text-xs text-slate-400"><?= count($rows) ?> élément(s) · table : <code class="text-xs"><?= e($meta['table']) ?></code></p>
        </div>
      </div>
      <button
        @click="openCreate('<?= e($typeKey) ?>')"
        class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-medium inline-flex items-center gap-2 hover:bg-cb-dark transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Ajouter
      </button>
    </div>

    <!-- Table -->
    <?php if (empty($rows)): ?>
      <div class="px-6 py-10 text-center text-slate-400">
        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
        <p class="text-sm">Aucun élément — cliquez sur « Ajouter » pour créer le premier.</p>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
            <tr>
              <th class="px-5 py-3 text-left w-8">#</th>
              <th class="px-5 py-3 text-left">Aperçu</th>
              <th class="px-5 py-3 text-left">Slug</th>
              <th class="px-5 py-3 text-left">Icône</th>
              <th class="px-5 py-3 text-left">Description</th>
              <th class="px-5 py-3 text-center">Ordre</th>
              <th class="px-5 py-3 text-center">Statut</th>
              <th class="px-5 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $row): ?>
              <tr class="hover:bg-cb-bg/30 transition group">
                <td class="px-5 py-3 text-slate-300 text-xs font-mono"><?= (int)$row['id'] ?></td>
                <td class="px-5 py-3">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-medium <?= e($row['color_class'] ?? '') ?>">
                    <i data-lucide="<?= e($row['icon'] ?? 'tag') ?>" class="w-3.5 h-3.5"></i>
                    <?= e($row['label']) ?>
                  </span>
                </td>
                <td class="px-5 py-3 font-mono text-xs text-slate-600"><?= e($row['slug']) ?></td>
                <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= e($row['icon'] ?? '') ?></td>
                <td class="px-5 py-3 text-xs text-slate-500 max-w-48 truncate"><?= e($row['description'] ?? '—') ?></td>
                <td class="px-5 py-3 text-center text-xs font-mono text-slate-500"><?= (int)($row['sort_order'] ?? 0) ?></td>
                <td class="px-5 py-3 text-center">
                  <?php if ((int)$row['is_active'] === 1): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-700 border border-emerald-200">Actif</span>
                  <?php else: ?>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-500 border border-slate-200">Inactif</span>
                  <?php endif ?>
                </td>
                <td class="px-5 py-3">
                  <div class="flex items-center justify-end gap-1">
                    <!-- Modifier -->
                    <button
                      @click="openEdit('<?= e($typeKey) ?>', <?= htmlspecialchars(json_encode([
                        'id'          => (int)$row['id'],
                        'slug'        => $row['slug'],
                        'label'       => $row['label'],
                        'icon'        => $row['icon'] ?? '',
                        'color_class' => $row['color_class'] ?? '',
                        'description' => $row['description'] ?? '',
                        'sort_order'  => (int)($row['sort_order'] ?? 0),
                        'is_active'   => (int)$row['is_active'],
                      ]), ENT_QUOTES) ?>)"
                      class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition"
                      title="Modifier">
                      <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </button>
                    <!-- Toggle actif -->
                    <form method="post" action="<?= e(url("referentiel/tariffs/config/{$typeKey}/{$row['id']}/toggle")) ?>">
                      <?= csrf_field() ?>
                      <button type="submit"
                        class="p-1.5 rounded-lg border <?= (int)$row['is_active'] ? 'border-amber-200 text-amber-600 hover:bg-amber-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' ?> transition"
                        title="<?= (int)$row['is_active'] ? 'Désactiver' : 'Activer' ?>">
                        <i data-lucide="<?= (int)$row['is_active'] ? 'eye-off' : 'eye' ?>" class="w-3.5 h-3.5"></i>
                      </button>
                    </form>
                    <!-- Supprimer -->
                    <form method="post" action="<?= e(url("referentiel/tariffs/config/{$typeKey}/{$row['id']}/delete")) ?>"
                          onsubmit="return confirm('Supprimer « <?= e(addslashes($row['label'])) ?> » ?')">
                      <?= csrf_field() ?>
                      <button type="submit"
                        class="p-1.5 rounded-lg border border-rose-200 text-rose-500 hover:bg-rose-50 transition"
                        title="Supprimer">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </div>
  <?php endforeach ?>

  <!-- ══ MODAL Créer / Modifier ══════════════════════════════════════════════ -->
  <div x-show="modalOpen"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       class="fixed inset-0 bg-slate-900/50 z-50 flex items-end sm:items-center justify-center p-4"
       @keydown.escape.window="modalOpen = false"
       @click.self="modalOpen = false">

    <div x-show="modalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">

      <!-- En-tête modal -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white z-10">
        <div class="flex items-center gap-2">
          <span class="w-7 h-7 rounded-lg bg-cb-bg flex items-center justify-center">
            <span id="modal-section-icon" class="flex items-center justify-center">
              <i data-lucide="settings" class="w-4 h-4 text-cb-primary"></i>
            </span>
          </span>
          <h3 class="font-semibold text-slate-900" x-text="modalTitle"></h3>
        </div>
        <button @click="modalOpen = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <!-- Formulaire -->
      <form :action="formAction" method="post" :data-dirty-watch="isEdit ? '1' : '0'" class="px-6 py-5 space-y-4">
        <?= csrf_field() ?>

        <!-- Slug -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Slug <span class="text-rose-500">*</span>
            <span class="text-xs font-normal text-slate-400 ml-1">(identifiant unique, immuable de préférence)</span>
          </label>
          <input type="text" name="slug" required maxlength="50"
                 pattern="[a-z0-9_]+"
                 :value="form.slug"
                 @input="form.slug = $event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '')"
                 placeholder="ex : adulte, vip, bagage_excedent"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 font-mono text-sm focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
          <p class="text-xs text-slate-400 mt-1">Minuscules, chiffres et _ uniquement. Ne pas modifier après utilisation.</p>
        </div>

        <!-- Libellé -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Libellé <span class="text-rose-500">*</span></label>
          <input type="text" name="label" required maxlength="100"
                 :value="form.label"
                 x-model="form.label"
                 placeholder="ex : Adulte, VIP / Confort"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
        </div>

        <!-- Icône Lucide -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Icône Lucide <span class="text-rose-500">*</span>
            <a href="https://lucide.dev/icons/" target="_blank" class="text-xs text-cb-primary hover:underline ml-1">↗ voir toutes les icônes</a>
          </label>
          <div class="flex items-center gap-3">
            <div id="icon-preview-box" class="w-10 h-10 rounded-xl border-2 border-slate-200 flex items-center justify-center bg-slate-50">
              <i data-lucide="tag" class="w-5 h-5 text-slate-600"></i>
            </div>
            <input type="text" name="icon" required maxlength="50"
                   x-model="form.icon"
                   @input.debounce.400ms="refreshIconPreview()"
                   placeholder="ex : user, star, briefcase"
                   class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200 font-mono text-sm focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
          </div>
        </div>

        <!-- Couleur -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Couleur du badge <span class="text-rose-500">*</span></label>
          <input type="hidden" name="color_class" :value="form.color_class">
          <div class="grid grid-cols-5 gap-2">
            <?php foreach ($colorPalette as $cls => $info): ?>
              <button type="button"
                      @click="form.color_class = '<?= $cls ?>'"
                      :class="form.color_class === '<?= $cls ?>' ? 'ring-2 ring-cb-primary ring-offset-1 scale-105' : 'hover:scale-105'"
                      class="transition transform"
                      title="<?= e($info['name']) ?>">
                <span class="flex items-center justify-center gap-1 px-2 py-1.5 rounded-lg border text-xs font-medium <?= $cls ?>">
                  <span class="w-2.5 h-2.5 rounded-full <?= e($info['dot']) ?>"></span>
                  <?= e($info['name']) ?>
                </span>
              </button>
            <?php endforeach ?>
          </div>
          <!-- Aperçu badge -->
          <div x-show="form.color_class && form.label" class="mt-3 flex items-center gap-2">
            <span class="text-xs text-slate-400">Aperçu :</span>
            <span id="badge-preview"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-medium"
                  :class="form.color_class">
              <i id="badge-preview-icon" data-lucide="tag" class="w-3.5 h-3.5"></i>
              <span x-text="form.label"></span>
            </span>
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Description <span class="text-xs font-normal text-slate-400">(optionnel)</span>
          </label>
          <input type="text" name="description" maxlength="255"
                 x-model="form.description"
                 placeholder="Brève description affichée dans les formulaires"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        </div>

        <!-- Ordre + Statut (édition seulement) -->
        <div class="flex items-center gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium text-slate-700 mb-1">Ordre d'affichage</label>
            <input type="number" name="sort_order" min="0" max="999"
                   x-model="form.sort_order"
                   class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
          </div>
          <div x-show="isEdit" class="pt-5">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="is_active" value="1"
                     :checked="form.is_active == 1"
                     class="w-4 h-4 rounded border-slate-300 text-cb-primary focus:ring-cb-primary/20">
              <span class="text-sm font-medium text-slate-700">Actif</span>
            </label>
          </div>
        </div>

        <!-- Actions modal -->
        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
          <button type="button" @click="modalOpen = false"
                  class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition text-sm">
            Annuler
          </button>
              <button type="submit" data-dirty-submit
                  class="px-6 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-dark transition inline-flex items-center gap-2 text-sm">
            <i data-lucide="save" class="w-4 h-4"></i>
            <span x-text="isEdit ? 'Mettre à jour' : 'Créer'"></span>
          </button>
        </div>
      </form>
    </div>
  </div>

</div><!-- /x-data -->

<script>
const SECTION_META = <?= json_encode(array_map(fn($s) => [
  'label' => $s['meta']['label'],
  'icon'  => $s['meta']['icon'],
], $sections), JSON_UNESCAPED_UNICODE) ?>;

function tariffConfig() {
  return {
    modalOpen: false,
    isEdit:    false,
    modalTitle: '',
    modalSectionIcon: 'settings',
    formAction: '',
    form: {
      slug: '', label: '', icon: '', color_class: '',
      description: '', sort_order: 0, is_active: 1,
    },

    init() {
      // Mettre à jour l'icône dans l'aperçu badge quand form.icon change
      this.$watch('form.icon', () => this._syncIconPreviews());
    },

    _syncIconPreviews() {
      const iconName = this.form.icon || 'tag';
      // Aperçu grand (dans le champ saisie)
      const box = document.getElementById('icon-preview-box');
      if (box) {
        box.innerHTML = `<i data-lucide="${iconName}" class="w-5 h-5 text-slate-600"></i>`;
      }
      // Aperçu badge
      const badge = document.getElementById('badge-preview-icon');
      if (badge) {
        badge.setAttribute('data-lucide', iconName);
      }
      if (window.lucide) lucide.createIcons();
    },

    _syncModalIcon(iconName) {
      const el = document.getElementById('modal-section-icon');
      if (el) {
        el.innerHTML = `<i data-lucide="${iconName || 'settings'}" class="w-4 h-4 text-cb-primary"></i>`;
        if (window.lucide) lucide.createIcons();
      }
    },

    openCreate(type) {
      this.isEdit    = false;
      this.modalTitle = 'Ajouter — ' + (SECTION_META[type]?.label ?? type);
      this.modalSectionIcon = SECTION_META[type]?.icon ?? 'plus';
      this.formAction = '<?= url('referentiel/tariffs/config') ?>/' + type;
      this.form = { slug: '', label: '', icon: '', color_class: '', description: '', sort_order: 0, is_active: 1 };
      this.modalOpen = true;
      this.$nextTick(() => {
        if (window.lucide) lucide.createIcons();
        this._syncModalIcon(this.modalSectionIcon);
        this._syncIconPreviews();
      });
    },

    openEdit(type, row) {
      this.isEdit    = true;
      this.modalTitle = 'Modifier — ' + (SECTION_META[type]?.label ?? type);
      this.modalSectionIcon = SECTION_META[type]?.icon ?? 'pencil';
      this.formAction = '<?= url('referentiel/tariffs/config') ?>/' + type + '/' + row.id;
      this.form = { ...row };
      this.modalOpen = true;
      this.$nextTick(() => {
        if (window.lucide) lucide.createIcons();
        this._syncModalIcon(this.modalSectionIcon);
        this._syncIconPreviews();
      });
    },

    refreshIconPreview() {
      this._syncIconPreviews();
    },
  };
}
</script>

<?php $view->end() ?>
