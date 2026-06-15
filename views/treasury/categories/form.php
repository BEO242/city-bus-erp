<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$isEdit   = !empty($category);
$action   = $isEdit ? url('finance/treasury/categories/' . $category['id']) : url('finance/treasury/categories');
$backUrl  = url('finance/treasury/categories');

$curCode   = $category['code']       ?? old('code', '');
$curLabel  = $category['label']      ?? old('label', '');
$curType   = $category['type']       ?? old('type', 'encaissement');
$curColor  = $category['color']      ?? old('color', 'slate');
$curOrder  = $category['sort_order'] ?? old('sort_order', 100);
$curActive = isset($category['is_active']) ? (int)$category['is_active'] : 1;

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

  <div class="flex items-center gap-3">
    <a href="<?= e($backUrl) ?>" class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-xl font-black text-slate-900"><?= e($title) ?></h1>
    </div>
  </div>

  <form method="post" action="<?= e($action) ?>" class="space-y-4">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="tag" class="w-4 h-4 text-cb-primary"></i> Identité
      </h2>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Libellé <span class="text-rose-500">*</span></label>
        <input type="text" name="label" required maxlength="120" value="<?= e($curLabel) ?>"
               placeholder="Ex : Carburant, Entretien véhicule…"
               x-ref="labelInput"
               @input="!<?= $isEdit ? 'true' : 'false' ?> && ($refs.codeInput.value = slugify($event.target.value))"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Code technique <span class="text-rose-500">*</span></label>
        <input type="text" name="code" required maxlength="40" value="<?= e($curCode) ?>"
               placeholder="ex : carburant" x-ref="codeInput"
               pattern="[a-z0-9_\-]+"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm font-mono">
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Type <span class="text-rose-500">*</span></label>
        <div class="flex gap-3">
          <label class="flex items-center gap-2 cursor-pointer px-4 py-2.5 rounded-xl border border-slate-200 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 transition">
            <input type="radio" name="type" value="encaissement" <?= $curType === 'encaissement' ? 'checked' : '' ?> class="accent-emerald-600">
            <span class="text-sm font-semibold text-slate-700">Encaissement</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer px-4 py-2.5 rounded-xl border border-slate-200 has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50 transition">
            <input type="radio" name="type" value="decaissement" <?= $curType === 'decaissement' ? 'checked' : '' ?> class="accent-rose-600">
            <span class="text-sm font-semibold text-slate-700">Décaissement</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Couleur -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="palette" class="w-4 h-4 text-cb-primary"></i> Couleur du badge
      </h2>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($colors as $key => $name):
          $cls = $colorPreview[$key];
        ?>
        <label class="cursor-pointer" title="<?= e($name) ?>">
          <input type="radio" name="color" value="<?= $key ?>" x-model="color"
                 <?= $curColor === $key ? 'checked' : '' ?> class="sr-only peer">
          <div class="w-8 h-8 rounded-full <?= $cls['bg'] ?> ring-2 ring-offset-2 ring-transparent
                      peer-checked:ring-2 peer-checked:<?= $cls['ring'] ?> transition-all"></div>
        </label>
        <?php endforeach ?>
      </div>
    </div>

    <!-- Options -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="settings-2" class="w-4 h-4 text-cb-primary"></i> Options
      </h2>
      <div class="grid grid-cols-2 gap-4 items-end">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Ordre d'affichage</label>
          <input type="number" name="sort_order" min="0" value="<?= e($curOrder) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
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
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">Annuler</a>
      <button type="submit"
              class="px-6 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-bold hover:bg-cb-dark transition flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        <?= $isEdit ? 'Mettre à jour' : 'Créer' ?>
      </button>
    </div>
  </form>
</div>

<script>
function slugify(str) {
  return str.toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9\s_-]/g, '')
    .trim().replace(/\s+/g, '_');
}
</script>
<?php $view->end() ?>
