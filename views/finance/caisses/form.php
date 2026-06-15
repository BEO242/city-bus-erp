<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$isEdit  = !empty($caisse);
$action  = $isEdit ? url('finance/caisses/' . $caisse['id']) : url('finance/caisses');
$backUrl = url('finance/caisses');

$curCode     = $caisse['code']        ?? old('code', '');
$curName     = $caisse['name']        ?? old('name', '');
$curType     = $caisse['type']        ?? old('type', 'agence');
$curAgency   = (int)($caisse['agency_id'] ?? old('agency_id', 0));
$curDesc     = $caisse['description'] ?? old('description', '');
$curOrder    = $caisse['sort_order']  ?? old('sort_order', 100);
$curActive   = isset($caisse['is_active']) ? (int)$caisse['is_active'] : 1;

$typeIcons = [
    'principale'  => 'star',
    'point_vente' => 'shopping-bag',
    'agence'      => 'building-2',
    'mobile'      => 'smartphone',
];
?>

<div class="space-y-5 max-w-xl mx-auto">

  <div class="flex items-center gap-3">
    <a href="<?= e($backUrl) ?>" class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-xl font-black text-slate-900"><?= e($title) ?></h1>
      <p class="text-xs text-slate-400 mt-0.5"><?= $isEdit ? 'Modifier les informations de la caisse' : 'Ajouter une nouvelle caisse' ?></p>
    </div>
  </div>

  <form method="post" action="<?= e($action) ?>" class="space-y-4">
    <?= csrf_field() ?>

    <!-- Identité -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="wallet" class="w-4 h-4 text-cb-primary"></i> Identité
      </h2>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Nom <span class="text-rose-500">*</span></label>
        <input type="text" name="name" required maxlength="120" value="<?= e($curName) ?>"
               placeholder="Ex : Caisse principale, Guichet 1…"
               x-ref="nameInput"
               @input="!<?= $isEdit ? 'true' : 'false' ?> && ($refs.codeInput.value = slugify($event.target.value))"
               x-data=""
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Code technique <span class="text-rose-500">*</span></label>
        <input type="text" name="code" required maxlength="40" value="<?= e($curCode) ?>"
               placeholder="ex : caisse_principale" x-ref="codeInput"
               pattern="[a-z0-9_\-]+"
               title="Minuscules, chiffres, tirets et underscores uniquement"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm font-mono">
        <p class="text-[11px] text-slate-400 mt-1">Identifiant unique, non modifiable après création.</p>
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Agence <span class="text-rose-500">*</span></label>
        <select name="agency_id" required
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm bg-white">
          <option value="">-- Sélectionner une agence --</option>
          <?php foreach ($agencies as $ag): ?>
          <option value="<?= $ag['id'] ?>" <?= $curAgency === (int)$ag['id'] ? 'selected' : '' ?>>
            <?= e($ag['name']) ?>
          </option>
          <?php endforeach ?>
        </select>
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Description <span class="text-slate-400 font-normal">(optionnel)</span></label>
        <textarea name="description" rows="2" maxlength="300" placeholder="Utilisation, emplacement…"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm resize-none"><?= e($curDesc) ?></textarea>
      </div>
    </div>

    <!-- Type -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="tag" class="w-4 h-4 text-cb-primary"></i> Type de caisse
      </h2>
      <div class="grid grid-cols-2 gap-3">
        <?php foreach ($types as $key => $label):
          $icon = $typeIcons[$key] ?? 'wallet';
          $checked = $curType === $key;
        ?>
        <label class="cursor-pointer">
          <input type="radio" name="type" value="<?= $key ?>" <?= $checked ? 'checked' : '' ?> class="sr-only peer">
          <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200
                      peer-checked:border-cb-primary peer-checked:bg-cb-bg transition">
            <i data-lucide="<?= $icon ?>" class="w-4 h-4 text-slate-400 peer-checked:text-cb-primary shrink-0"></i>
            <span class="text-sm font-semibold text-slate-700"><?= e($label) ?></span>
          </div>
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
            <span class="text-sm text-slate-700 font-semibold">Caisse active</span>
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
        <?= $isEdit ? 'Mettre à jour' : 'Créer la caisse' ?>
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
