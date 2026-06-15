<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$isEdit = !empty($type);
$action = $isEdit ? url('referentiel/vehicle-types/' . $type['id']) : url('referentiel/vehicle-types');
?>
<?php $view->start('content') ?>

<div class="max-w-2xl mx-auto space-y-6">

  <!-- En-tete -->
  <div class="flex items-center gap-4">
    <a href="<?= e(url('referentiel/vehicle-types')) ?>"
       class="text-slate-500 hover:text-cb-primary p-2 rounded-lg hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>
      <?php if ($isEdit): ?>
        <p class="text-sm text-slate-500"><?= e($type['code']) ?></p>
      <?php endif ?>
    </div>
  </div>

  <!-- Formulaire -->
  <form method="post" action="<?= e($action) ?>" novalidate
        class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
    <?= csrf_field() ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="cb-label">Code <span class="text-rose-500">*</span></label>
        <input name="code" required maxlength="30" placeholder="minibus"
               value="<?= e(old('code', $type['code'] ?? '')) ?>"
               class="cb-input font-mono" <?= $isEdit ? '' : '' ?>>
        <p class="text-xs text-slate-400 mt-1">Identifiant technique (lettres minuscules, chiffres, underscores)</p>
      </div>
      <div>
        <label class="cb-label">Libelle <span class="text-rose-500">*</span></label>
        <input name="label" required maxlength="80" placeholder="Minibus"
               value="<?= e(old('label', $type['label'] ?? '')) ?>"
               class="cb-input">
      </div>
    </div>

    <div>
      <label class="cb-label">Description</label>
      <input name="description" maxlength="255" placeholder="Description courte du type de vehicule"
             value="<?= e(old('description', $type['description'] ?? '')) ?>"
             class="cb-input">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div>
        <label class="cb-label">Icone</label>
        <select name="icon" class="cb-input">
          <?php
          $icons = ['bus' => 'Bus', 'truck' => 'Camion', 'car' => 'Voiture', 'container' => 'Container'];
          $curIcon = old('icon', $type['icon'] ?? 'truck');
          foreach ($icons as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $curIcon === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="cb-label">Ordre d'affichage</label>
        <input type="number" name="sort_order" min="0" max="999"
               value="<?= e(old('sort_order', $type['sort_order'] ?? 0)) ?>"
               class="cb-input">
      </div>
      <div class="flex items-end pb-1">
        <label class="flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition has-[:checked]:border-cb-primary has-[:checked]:bg-blue-50 w-full">
          <input type="checkbox" name="is_active" value="1"
                 <?= (int)old('is_active', $type['is_active'] ?? 1) ? 'checked' : '' ?>
                 class="w-4 h-4 accent-cb-primary">
          <span class="font-medium text-sm">Actif</span>
        </label>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-between items-center pt-3 border-t border-slate-100">
      <a href="<?= e(url('referentiel/vehicle-types')) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition">
        Annuler
      </a>
      <button type="submit"
              class="px-6 py-2.5 rounded-xl bg-cb-primary text-white font-semibold text-sm hover:bg-cb-dark transition flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        <?= $isEdit ? 'Enregistrer' : 'Creer le type' ?>
      </button>
    </div>
  </form>

</div>
<?php $view->end() ?>
