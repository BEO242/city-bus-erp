<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$isEdit  = !empty($tariff);
$action  = $isEdit ? url('cargo/tariffs/'.$tariff['id']) : url('cargo/tariffs');
$backUrl = url('cargo/tariffs');

$curCategory  = $tariff['category']       ?? old('category',       '');
$curLabel     = $tariff['label']          ?? old('label',          '');
$curPriceKg   = $tariff['price_per_kg']   ?? old('price_per_kg',   '');
$curMinPrice  = $tariff['min_price_fcfa'] ?? old('min_price_fcfa', '0');
$curFrom      = $tariff['valid_from']     ?? old('valid_from',     '');
$curUntil     = $tariff['valid_until']    ?? old('valid_until',    '');
$curSortOrder = $tariff['sort_order']     ?? old('sort_order',     100);
$curActive    = isset($tariff['is_active']) ? (int)$tariff['is_active'] : 1;
?>
<?php $view->start('content') ?>

<div class="space-y-5 max-w-xl mx-auto">

  <!-- En-tête -->
  <div class="flex items-center gap-3">
    <a href="<?= e($backUrl) ?>" class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-xl font-black text-slate-900"><?= e($title) ?></h1>
      <p class="text-xs text-slate-400 mt-0.5">
        <?= $isEdit ? 'Modifier le tarif fret' : 'Créer un nouveau tarif fret' ?>
      </p>
    </div>
  </div>

  <form method="post" action="<?= e($action) ?>" class="space-y-4">
    <?= csrf_field() ?>

    <!-- Catégorie + libellé -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="tag" class="w-4 h-4 text-cb-primary"></i> Identification
      </h2>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Catégorie <span class="text-rose-500">*</span>
        </label>
        <input type="text" name="category" required
               list="category-suggestions"
               value="<?= e($curCategory) ?>"
               placeholder="Ex : aliments, textile, électronique…"
               autocomplete="off"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        <datalist id="category-suggestions">
          <?php foreach ($categories as $k => $lbl): ?>
            <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
          <?php endforeach ?>
        </datalist>
        <p class="text-[11px] text-slate-400 mt-1">
          Identifiant de catégorie (minuscules, sans accents) — doit correspondre exactement à ce qui est saisi lors du dépôt d'un colis.
          Exemples&nbsp;: <code class="bg-slate-100 px-1 rounded">colis</code>, <code class="bg-slate-100 px-1 rounded">aliments</code>, <code class="bg-slate-100 px-1 rounded">fragile</code>
        </p>
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Libellé affiché <span class="text-slate-400 font-normal ml-1">(optionnel)</span>
        </label>
        <input type="text" name="label" maxlength="100"
               value="<?= e($curLabel) ?>"
               placeholder="Ex : Tarif standard documents administratifs"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
      </div>
    </div>

    <!-- Tarification -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="coins" class="w-4 h-4 text-cb-primary"></i> Tarification
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Prix au kg -->
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            Prix au kg (FCFA) <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <input type="number" name="price_per_kg" required min="1" step="1"
                   value="<?= e($curPriceKg) ?>"
                   class="w-full pl-3 pr-16 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-right text-lg font-bold text-cb-primary"
                   placeholder="0">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">F/kg</span>
          </div>
        </div>

        <!-- Montant minimum -->
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            Montant minimum (FCFA)
            <span class="text-slate-400 font-normal ml-1">(0 = aucun min.)</span>
          </label>
          <div class="relative">
            <input type="number" name="min_price_fcfa" min="0" step="1"
                   value="<?= e($curMinPrice) ?>"
                   class="w-full pl-3 pr-16 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-right text-lg font-bold text-slate-700"
                   placeholder="0">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">FCFA</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-1">
            Prix appliqué = <strong>max(minimum, poids × prix/kg)</strong>
          </p>
        </div>
      </div>

      <!-- Exemple de calcul dynamique -->
      <div class="bg-cb-bg rounded-xl px-4 py-3 text-xs text-cb-primary" id="calc-preview">
        <i data-lucide="calculator" class="w-3.5 h-3.5 inline mr-1"></i>
        <span id="calc-text">Saisissez un prix/kg pour voir un exemple.</span>
      </div>
    </div>

    <!-- Validité -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="calendar-range" class="w-4 h-4 text-cb-primary"></i> Validité &amp; ordre
      </h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Début de validité</label>
          <input type="date" name="valid_from" value="<?= e($curFrom) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            Fin de validité <span class="text-slate-400 font-normal">(vide = permanent)</span>
          </label>
          <input type="date" name="valid_until" value="<?= e($curUntil) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Ordre d'affichage</label>
          <input type="number" name="sort_order" min="0" value="<?= e($curSortOrder) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        </div>
        <?php if ($isEdit): ?>
        <div class="flex items-end pb-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" <?= $curActive ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-slate-300 accent-cb-primary">
            <span class="text-sm text-slate-700 font-semibold">Tarif actif</span>
          </label>
        </div>
        <?php endif ?>
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
        <?= $isEdit ? 'Mettre à jour' : 'Créer le tarif' ?>
      </button>
    </div>

  </form>
</div>

<script>
(function () {
  const priceKgInput  = document.querySelector('[name="price_per_kg"]');
  const minPriceInput = document.querySelector('[name="min_price_fcfa"]');
  const calcText      = document.getElementById('calc-text');

  function update() {
    const pk  = parseFloat(priceKgInput.value)  || 0;
    const min = parseFloat(minPriceInput.value) || 0;
    if (pk <= 0) { calcText.textContent = 'Saisissez un prix/kg pour voir un exemple.'; return; }
    const ex5  = Math.max(min, 5  * pk);
    const ex10 = Math.max(min, 10 * pk);
    const ex25 = Math.max(min, 25 * pk);
    calcText.innerHTML =
      `Exemple : 5 kg → <strong>${ex5.toLocaleString('fr-FR')} F</strong>`
    + ` · 10 kg → <strong>${ex10.toLocaleString('fr-FR')} F</strong>`
    + ` · 25 kg → <strong>${ex25.toLocaleString('fr-FR')} F</strong>`;
  }
  priceKgInput.addEventListener('input', update);
  minPriceInput.addEventListener('input', update);
  update();
})();
</script>

<?php $view->end() ?>
