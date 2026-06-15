<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$isEdit = !empty($tariff);
$action = $isEdit
    ? url('referentiel/baggage-tariffs/' . $tariff['id'])
    : url('referentiel/baggage-tariffs');

// Valeurs courantes
$curLineId   = $tariff['line_id']              ?? old('line_id', 0);
$curNatureId = $tariff['baggage_nature_id']    ?? old('baggage_nature_id', 0);
$curLabel    = $tariff['label']                ?? old('label', '');
$curBaseFee  = $tariff['base_fee_fcfa']        ?? old('base_fee_fcfa', 0);
$curPerKg    = $tariff['per_kg_fcfa']          ?? old('per_kg_fcfa', '');
$curBracket  = (int)($tariff['bracket_mode']   ?? old('bracket_mode', 0));
$curVolSurch = $tariff['volume_surcharge_fcfa'] ?? old('volume_surcharge_fcfa', '');
$curMaxWt    = $tariff['max_weight_kg']        ?? old('max_weight_kg', '');
$curMaxLen   = $tariff['max_length_cm']        ?? old('max_length_cm', '');
$curMaxW     = $tariff['max_width_cm']         ?? old('max_width_cm', '');
$curMaxH     = $tariff['max_height_cm']        ?? old('max_height_cm', '');
$curMaxGirth = $tariff['max_girth_cm']         ?? old('max_girth_cm', '');
$curFrom     = $tariff['valid_from']           ?? old('valid_from', '');
$curUntil    = $tariff['valid_until']          ?? old('valid_until', '');
$curNotes    = $tariff['notes']                ?? old('notes', '');
$brackets    = $brackets ?? [];
?>
<?php $view->start('content') ?>

<div x-data="baggageTariffForm()" class="space-y-5">

  <!-- En-tête ──────────────────────────────────────────────────────────── -->
  <div class="flex items-center gap-3">
    <a href="<?= e(url('referentiel/baggage-tariffs')) ?>"
       class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>
      <p class="text-sm text-slate-500">
        <?= $isEdit ? 'Modifier le tarif bagage sélectionné' : 'Définir un tarif pour les bagages excédentaires sur une ligne' ?>
      </p>
    </div>
  </div>

  <form method="post" action="<?= e($action) ?>" data-dirty-watch="<?= $isEdit ? '1' : '0' ?>" class="space-y-5">
    <?= csrf_field() ?>

    <!-- ══ LIGNE + NATURE ════════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
      <h3 class="font-semibold text-slate-800 flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="route" class="w-4 h-4 text-cb-primary"></i> Ligne et nature de bagage
      </h3>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Ligne -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Ligne <span class="text-rose-500">*</span></label>
          <select name="line_id" required
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
            <option value="">— Sélectionner une ligne —</option>
            <?php foreach ($lines as $l):
              $sel = $curLineId == $l['id'];
            ?>
              <option value="<?= (int)$l['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                <?= e($l['code']) ?> — <?= e($l['name']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>

        <!-- Nature de bagage -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nature de bagage <span class="text-rose-500">*</span></label>
          <select name="baggage_nature_id" required
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
            <option value="">— Sélectionner —</option>
            <?php foreach ($baggageNatures as $slug => $n):
              $sel = $curNatureId == $n['id'];
            ?>
              <option value="<?= (int)$n['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                <?= e($n['label']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>
      </div>

      <!-- Libellé -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Libellé <span class="text-rose-500">*</span></label>
        <input type="text" name="label" maxlength="150" required
               value="<?= e($curLabel) ?>"
               placeholder="Ex : Excédent bagage standard — Ligne BZV-PNR"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
      </div>
    </div>

    <!-- ══ FORMULE TARIFAIRE ══════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
      <h3 class="font-semibold text-slate-800 flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="calculator" class="w-4 h-4 text-cb-primary"></i> Formule tarifaire
      </h3>

      <!-- Frais fixes par colis -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Frais fixes par colis (FCFA)
          <span class="text-xs font-normal text-slate-400 ml-1">(0 si pas de frais fixes)</span>
        </label>
        <div class="relative max-w-sm">
          <input type="number" name="base_fee_fcfa" min="0" step="100"
                 value="<?= (int)$curBaseFee ?>"
                 class="w-full pl-3 pr-16 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">FCFA</span>
        </div>
      </div>

      <!-- Mode de tarification -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Mode de calcul du poids <span class="text-rose-500">*</span></label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl">
          <label class="cursor-pointer">
            <input type="radio" name="bracket_mode" value="0"
                   <?= !$curBracket ? 'checked' : '' ?>
                   x-model="bracketMode" value="0"
                   class="peer sr-only">
            <div class="flex items-start gap-3 px-4 py-3.5 rounded-xl border-2 border-slate-200 transition
                        peer-checked:border-cb-primary peer-checked:bg-cb-bg/50 hover:border-slate-300">
              <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-200 flex items-center justify-center shrink-0">
                <i data-lucide="trending-up" class="w-4 h-4 text-indigo-600"></i>
              </div>
              <div>
                <div class="font-semibold text-sm text-slate-800">Prix fixe par kg</div>
                <div class="text-xs text-slate-400 mt-0.5">Un taux unique multiplié par le nombre de kg</div>
              </div>
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="bracket_mode" value="1"
                   <?= $curBracket ? 'checked' : '' ?>
                   x-model="bracketMode" value="1"
                   class="peer sr-only">
            <div class="flex items-start gap-3 px-4 py-3.5 rounded-xl border-2 border-slate-200 transition
                        peer-checked:border-cb-primary peer-checked:bg-cb-bg/50 hover:border-slate-300">
              <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center shrink-0">
                <i data-lucide="layers" class="w-4 h-4 text-amber-600"></i>
              </div>
              <div>
                <div class="font-semibold text-sm text-slate-800">Tranches de poids</div>
                <div class="text-xs text-slate-400 mt-0.5">Grilles de prix selon les plages de poids</div>
              </div>
            </div>
          </label>
        </div>
      </div>

      <!-- Prix par kg (mode simple) -->
      <div x-show="bracketMode == '0'">
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Prix par kg (FCFA/kg) <span class="text-rose-500">*</span>
        </label>
        <div class="relative max-w-sm">
          <input type="number" name="per_kg_fcfa" min="0" step="50"
                 :required="bracketMode == '0'"
                 value="<?= $curPerKg !== '' ? (int)$curPerKg : '' ?>"
                 class="w-full pl-3 pr-20 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">FCFA/kg</span>
        </div>
      </div>

      <!-- Tranches de poids (mode brackets) -->
      <div x-show="bracketMode == '1'" class="space-y-3">
        <div class="flex items-center justify-between">
          <label class="text-sm font-medium text-slate-700">Tranches de poids</label>
          <button type="button" @click="addBracket()"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cb-bg text-cb-primary text-xs font-semibold border border-cb-primary/20 hover:bg-cb-primary/10 transition">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une tranche
          </button>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase">
              <tr>
                <th class="px-3 py-2 text-left">De (kg)</th>
                <th class="px-3 py-2 text-left">À (kg) <span class="text-slate-300 font-normal">vide = illimité</span></th>
                <th class="px-3 py-2 text-right">Prix FCFA</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="(b, idx) in brackets" :key="idx">
                <tr class="border-t border-slate-100">
                  <td class="px-3 py-2">
                    <input type="number" :name="`brackets[${idx}][weight_from]`" x-model="b.weight_from"
                           min="0" step="0.5" placeholder="0"
                           class="w-24 px-2 py-1.5 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
                  </td>
                  <td class="px-3 py-2">
                    <input type="number" :name="`brackets[${idx}][weight_to]`" x-model="b.weight_to"
                           min="0" step="0.5" placeholder="∞"
                           class="w-24 px-2 py-1.5 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
                  </td>
                  <td class="px-3 py-2 text-right">
                    <input type="number" :name="`brackets[${idx}][price]`" x-model="b.price"
                           min="0" step="100"
                           class="w-32 px-2 py-1.5 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm text-right">
                  </td>
                  <td class="px-3 py-2">
                    <button type="button" @click="removeBracket(idx)"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                  </td>
                </tr>
              </template>
              <tr x-show="brackets.length === 0" class="border-t border-slate-100">
                <td colspan="4" class="px-3 py-4 text-center text-xs text-slate-400">
                  Aucune tranche. Cliquez «&nbsp;Ajouter une tranche&nbsp;».
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Surcharge hors gabarit -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Surcharge hors gabarit (FCFA)
          <span class="text-xs font-normal text-slate-400 ml-1">(optionnel — si le colis dépasse les dimensions max)</span>
        </label>
        <div class="relative max-w-sm">
          <input type="number" name="volume_surcharge_fcfa" min="0" step="100"
                 value="<?= $curVolSurch !== '' ? (int)$curVolSurch : '' ?>"
                 placeholder="Laisser vide si pas de surcharge"
                 class="w-full pl-3 pr-16 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">FCFA</span>
        </div>
      </div>
    </div>

    <!-- ══ CONTRAINTES PHYSIQUES (collapsible) ════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden" x-data="{ open: <?= ($curMaxWt || $curMaxLen || $curMaxW || $curMaxH || $curMaxGirth) ? 'true' : 'false' ?> }">
      <button type="button" @click="open = !open"
              class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-slate-50 transition">
        <h3 class="font-semibold text-slate-800 flex items-center gap-2">
          <i data-lucide="ruler" class="w-4 h-4 text-cb-primary"></i> Contraintes physiques
          <span class="text-xs font-normal text-slate-400 ml-1">(toutes optionnelles)</span>
        </h3>
        <i :data-lucide="open ? 'chevron-up' : 'chevron-down'" class="w-4 h-4 text-slate-400"></i>
      </button>
      <div x-show="open" class="px-6 pb-6 space-y-4 border-t border-slate-100">
        <p class="text-xs text-slate-400 pt-3">
          Définissez les limites au-delà desquelles la surcharge hors gabarit s'applique.
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Poids max (kg)</label>
            <input type="number" name="max_weight_kg" min="0" step="0.5"
                   value="<?= $curMaxWt !== '' ? $curMaxWt : '' ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Longueur max (cm)</label>
            <input type="number" name="max_length_cm" min="0"
                   value="<?= $curMaxLen !== '' ? (int)$curMaxLen : '' ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Largeur max (cm)</label>
            <input type="number" name="max_width_cm" min="0"
                   value="<?= $curMaxW !== '' ? (int)$curMaxW : '' ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Hauteur max (cm)</label>
            <input type="number" name="max_height_cm" min="0"
                   value="<?= $curMaxH !== '' ? (int)$curMaxH : '' ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Périmètre max (cm) <span class="font-normal text-slate-400">2×(L+l)</span></label>
            <input type="number" name="max_girth_cm" min="0"
                   value="<?= $curMaxGirth !== '' ? (int)$curMaxGirth : '' ?>"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
          </div>
        </div>
      </div>
    </div>

    <!-- ══ VALIDITÉ TEMPORELLE ════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <h3 class="font-semibold text-slate-800 flex items-center gap-2">
          <i data-lucide="calendar-range" class="w-4 h-4 text-cb-primary"></i> Période de validité
        </h3>
        <span class="text-xs text-slate-400">Laisser vide = tarif permanent</span>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Début</label>
          <input type="date" name="valid_from" value="<?= e($curFrom) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Fin</label>
          <input type="date" name="valid_until" value="<?= e($curUntil) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
        </div>
      </div>
    </div>

    <!-- ══ NOTES ══════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-3">
      <h3 class="font-semibold text-slate-800 flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="file-text" class="w-4 h-4 text-cb-primary"></i> Notes
        <span class="text-xs font-normal text-slate-400 ml-1">(optionnel)</span>
      </h3>
      <textarea name="notes" rows="3" maxlength="2000"
                placeholder="Ex : Tarif applicable aux valises uniquement. Ne concerne pas les colis commerciaux."
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm resize-none"
      ><?= e($curNotes) ?></textarea>
    </div>

    <?php if ($isEdit): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4">
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1" <?= !empty($tariff['is_active']) ? 'checked' : '' ?>
               class="w-4 h-4 rounded border-slate-300 text-cb-primary focus:ring-cb-primary/20">
        <span class="text-sm text-slate-700 font-medium">Tarif actif</span>
      </label>
    </div>
    <?php endif ?>

    <!-- ══ ACTIONS ════════════════════════════════════════════════════════ -->
    <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-slate-100 shadow-soft p-4">
      <a href="<?= e(url('referentiel/baggage-tariffs')) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
        Annuler
      </a>
      <button type="submit" data-dirty-submit
              class="px-6 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-dark transition inline-flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        <?= $isEdit ? 'Mettre à jour' : 'Créer le tarif bagage' ?>
      </button>
    </div>

  </form>
</div>

<script>
function baggageTariffForm() {
  return {
    bracketMode: '<?= $curBracket ?>',
    brackets: <?= json_encode(array_map(fn($b) => [
      'weight_from' => $b['weight_from_kg'],
      'weight_to'   => $b['weight_to_kg']  ?? '',
      'price'       => $b['price_fcfa'],
    ], $brackets), JSON_THROW_ON_ERROR) ?>,

    addBracket() {
      const last = this.brackets[this.brackets.length - 1] ?? null;
      const from = last && last.weight_to ? parseFloat(last.weight_to) : 0;
      this.brackets.push({ weight_from: from, weight_to: '', price: '' });
      this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
    },

    removeBracket(idx) {
      this.brackets.splice(idx, 1);
    },

    init() {
      this.$watch('bracketMode', () => {
        this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
      });
    },
  };
}
</script>

<?php $view->end() ?>
