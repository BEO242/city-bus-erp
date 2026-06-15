<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$isEdit      = !empty($category);
$action      = $isEdit ? url('cargo/categories/'.$category['id']) : url('cargo/categories');
$backUrl     = url('referentiel/tariffs?tab=cargo');

$curSlug     = $category['slug']          ?? old('slug',          '');
$curLabel    = $category['label']         ?? old('label',         '');
$curDesc     = $category['description']   ?? old('description',   '');
$curColor    = $category['color']         ?? old('color',         'slate');
$curPriceKg  = $category['price_per_kg']  ?? old('price_per_kg',  '');
$curMinPrice = $category['min_price_fcfa']?? old('min_price_fcfa','0');
$curOrder    = $category['sort_order']    ?? old('sort_order',    100);
$curActive   = isset($category['is_active']) ? (int)$category['is_active'] : 1;

$colorPreview = [
  'slate'  => ['bg' => 'bg-slate-100',  'text' => 'text-slate-700',  'ring' => 'ring-slate-400'],
  'red'    => ['bg' => 'bg-red-100',    'text' => 'text-red-700',    'ring' => 'ring-red-400'],
  'orange' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'ring' => 'ring-orange-400'],
  'amber'  => ['bg' => 'bg-amber-100',  'text' => 'text-amber-700',  'ring' => 'ring-amber-400'],
  'green'  => ['bg' => 'bg-emerald-100','text' => 'text-emerald-700','ring' => 'ring-emerald-400'],
  'blue'   => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'ring' => 'ring-blue-400'],
  'violet' => ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'ring' => 'ring-violet-400'],
  'pink'   => ['bg' => 'bg-pink-100',   'text' => 'text-pink-700',   'ring' => 'ring-pink-400'],
];
?>

<div class="space-y-5 max-w-xl mx-auto" x-data="{ color: '<?= e($curColor) ?>' }">

  <!-- En-tête -->
  <div class="flex items-center gap-3">
    <a href="<?= e($backUrl) ?>"
       class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-xl font-black text-slate-900"><?= e($title) ?></h1>
      <p class="text-xs text-slate-400 mt-0.5">
        <?= $isEdit ? 'Modifier la catégorie fret' : 'Créer une nouvelle catégorie fret' ?>
      </p>
    </div>
  </div>

  <form method="post" action="<?= e($action) ?>" class="space-y-4">
    <?= csrf_field() ?>

    <!-- Identité -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="tag" class="w-4 h-4 text-cb-primary"></i> Identité
      </h2>

      <!-- Libellé -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Libellé <span class="text-rose-500">*</span>
        </label>
        <input type="text" name="label" required maxlength="100"
               value="<?= e($curLabel) ?>"
               placeholder="Ex : Colis standard, Aliments, Fragile…"
               x-ref="labelInput"
               @input="$refs.slugInput.value === '' || !<?= $isEdit ? 'false' : 'true' ?> ? $refs.slugInput.value = slugify($event.target.value) : null"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
      </div>

      <!-- Slug -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Slug (identifiant technique) <span class="text-rose-500">*</span>
        </label>
        <div class="flex items-center gap-2">
          <input type="text" name="slug" required maxlength="60"
                 value="<?= e($curSlug) ?>"
                 placeholder="ex : colis_standard"
                 x-ref="slugInput"
                 pattern="[a-z0-9_\-]+"
                 title="Minuscules, chiffres, tirets et underscores uniquement"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm font-mono">
        </div>
        <p class="text-[11px] text-slate-400 mt-1">
          Minuscules, chiffres, <code class="bg-slate-100 px-1 rounded">-</code> et <code class="bg-slate-100 px-1 rounded">_</code> uniquement.
          <?php if ($isEdit): ?>
          <strong class="text-amber-600">⚠ Modifier le slug mettra à jour tous les tarifs et colis associés.</strong>
          <?php endif ?>
        </p>
      </div>

      <!-- Description -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Description <span class="text-slate-400 font-normal">(optionnel)</span>
        </label>
        <textarea name="description" rows="2" maxlength="500"
                  placeholder="Ex : Colis de moins de 5 kg, emballage standard…"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm resize-none"><?= e($curDesc) ?></textarea>
      </div>
    </div>

    <!-- Tarification -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="coins" class="w-4 h-4 text-cb-primary"></i> Tarification
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            Prix au kg (FCFA) <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <input type="number" name="price_per_kg" required min="0" step="1"
                   id="input-price-kg"
                   value="<?= e($curPriceKg) ?>"
                   class="w-full pl-3 pr-16 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-right text-lg font-bold text-cb-primary"
                   placeholder="0">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">F/kg</span>
          </div>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            Montant minimum (FCFA) <span class="text-slate-400 font-normal">(0 = aucun)</span>
          </label>
          <div class="relative">
            <input type="number" name="min_price_fcfa" min="0" step="1"
                   id="input-min-price"
                   value="<?= e($curMinPrice) ?>"
                   class="w-full pl-3 pr-16 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-right text-lg font-bold text-slate-700"
                   placeholder="0">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">FCFA</span>
          </div>
        </div>
      </div>
      <p class="text-[11px] text-slate-400">
        Prix appliqué = <strong>max(minimum, poids × prix/kg)</strong>
      </p>
      <div class="bg-cb-bg rounded-xl px-4 py-3 text-xs text-cb-primary" id="calc-preview">
        <i data-lucide="calculator" class="w-3.5 h-3.5 inline mr-1"></i>
        <span id="calc-text">Saisissez un prix/kg pour voir un exemple.</span>
      </div>
    </div>

    <!-- Apparence -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="palette" class="w-4 h-4 text-cb-primary"></i> Couleur du badge
      </h2>

      <div class="flex flex-wrap gap-2">
        <?php foreach ($colors as $key => $name):
          $cls = $colorPreview[$key];
        ?>
        <label class="cursor-pointer" title="<?= e($name) ?>">
          <input type="radio" name="color" value="<?= $key ?>"
                 x-model="color"
                 <?= $curColor === $key ? 'checked' : '' ?>
                 class="sr-only peer">
          <div class="w-8 h-8 rounded-full <?= $cls['bg'] ?> ring-2 ring-offset-2 ring-transparent
                      peer-checked:ring-2 peer-checked:<?= $cls['ring'] ?> transition-all">
          </div>
        </label>
        <?php endforeach ?>
      </div>

      <!-- Aperçu -->
      <div class="flex items-center gap-2 mt-1">
        <span class="text-xs text-slate-400">Aperçu :</span>
        <?php foreach ($colorPreview as $key => $cls): ?>
        <span x-show="color === '<?= $key ?>'"
              class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold
                     <?= $cls['bg'] ?> <?= $cls['text'] ?>">
          <i data-lucide="tag" class="w-3 h-3"></i>
          <span x-text="document.querySelector('[name=label]')?.value || 'Catégorie'"></span>
        </span>
        <?php endforeach ?>
      </div>
    </div>

    <!-- Ordre & statut -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="settings-2" class="w-4 h-4 text-cb-primary"></i> Options
      </h2>
      <div class="grid grid-cols-2 gap-4 items-end">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Ordre d'affichage</label>
          <input type="number" name="sort_order" min="0" value="<?= e($curOrder) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        </div>
        <div class="pb-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" <?= $curActive ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-slate-300 accent-cb-primary">
            <span class="text-sm text-slate-700 font-semibold">Catégorie active</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
      <a href="<?= e($backUrl) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
        Annuler
      </a>
      <button type="submit"
              class="px-6 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-bold hover:bg-cb-dark transition flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        <?= $isEdit ? 'Mettre à jour' : 'Créer la catégorie' ?>
      </button>
    </div>

  </form>
</div>

<script>
function slugify(str) {
  return str.toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9\s_-]/g, '')
    .trim()
    .replace(/\s+/g, '_');
}
(function () {
  const pk  = document.getElementById('input-price-kg');
  const min = document.getElementById('input-min-price');
  const txt = document.getElementById('calc-text');
  function update() {
    const p = parseFloat(pk.value) || 0;
    const m = parseFloat(min.value) || 0;
    if (p <= 0 && m <= 0) { txt.textContent = 'Saisissez un prix/kg pour voir un exemple.'; return; }
    const fmt = n => n.toLocaleString('fr-FR');
    txt.innerHTML =
      `5 kg → <strong>${fmt(Math.max(m, 5*p))} F</strong>`
    + ` · 10 kg → <strong>${fmt(Math.max(m, 10*p))} F</strong>`
    + ` · 25 kg → <strong>${fmt(Math.max(m, 25*p))} F</strong>`;
  }
  pk.addEventListener('input', update);
  min.addEventListener('input', update);
  update();
})();
</script>

<?php $view->end() ?>
