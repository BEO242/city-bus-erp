<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div x-data="baggageSale()" class="grid lg:grid-cols-3 gap-5">

  <!-- En-tête ──────────────────────────────────────────────────────────── -->
  <div class="lg:col-span-3 flex justify-between flex-wrap gap-3">
    <div>
      <a href="<?= e(url('billetterie-bagages/select-trip')) ?>"
         class="text-sm text-slate-500 inline-flex items-center gap-1 hover:text-cb-primary">
        <i data-lucide="chevron-left" class="w-4 h-4"></i> Changer de voyage
      </a>
      <h1 class="text-2xl font-bold mt-1 flex items-center gap-2">
        <i data-lucide="package" class="w-6 h-6 text-amber-500"></i>
        <?= e($trip['line_name']) ?>
      </h1>
      <p class="text-slate-500">
        <?= e($trip['line_code']) ?>
        · <?= e(date('d/m/Y', strtotime($trip['trip_date']))) ?>
        à <?= e(date('H:i', strtotime($trip['departure_scheduled']))) ?>
        · Bus <?= e($trip['bus_code']) ?>
      </p>
    </div>
    <?php if (!$register && auth()['role'] === 'caissier'): ?>
      <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2.5 rounded-xl text-sm">
        ⚠️ Caisse non ouverte. <a href="<?= e(url('caisse/open')) ?>" class="underline font-semibold">Ouvrir</a>
      </div>
    <?php endif ?>
  </div>

  <!-- Tarifs disponibles (panneau gauche) ─────────────────────────────── -->
  <div class="lg:col-span-2 space-y-4">

    <?php if (empty($tariffs)): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-amber-800 text-sm">
        <i data-lucide="alert-triangle" class="w-5 h-5 inline-block mr-2"></i>
        Aucun tarif bagage actif sur cette ligne.
        <a href="<?= e(url('referentiel/baggage-tariffs/create')) ?>" class="underline ml-1">Créer un tarif</a>
      </div>
    <?php else: ?>

      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
        <h3 class="font-semibold mb-4 flex items-center gap-2">
          <i data-lucide="tag" class="w-4 h-4 text-amber-500"></i> Tarifs bagages disponibles
        </h3>
        <div class="grid sm:grid-cols-2 gap-3">
          <?php foreach ($tariffs as $t): ?>
            <button type="button"
                    @click="selectTariff(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)"
                    :class="selectedTariff?.id == <?= (int)$t['id'] ?>
                        ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-200'
                        : 'border-slate-200 hover:border-amber-300'"
                    class="text-left p-4 rounded-xl border-2 transition">
              <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-medium <?= e($t['nature_color']) ?> border">
                  <i data-lucide="<?= e($t['nature_icon']) ?>" class="w-3 h-3"></i>
                  <?= e($t['nature_label']) ?>
                </span>
              </div>
              <div class="font-semibold text-sm text-slate-800 truncate"><?= e($t['label']) ?></div>
              <div class="text-xs text-slate-500 mt-0.5 font-mono"><?= e($t['formula']) ?></div>
              <?php if ((int)$t['bracket_mode'] && !empty($t['brackets'])): ?>
                <div class="mt-2 space-y-0.5">
                  <?php foreach ($t['brackets'] as $b): ?>
                    <div class="text-xs text-slate-400">
                      <?= number_format((float)$b['weight_from_kg'], 1) ?>
                      – <?= $b['weight_to_kg'] !== null ? number_format((float)$b['weight_to_kg'], 1) . ' kg' : '∞' ?>
                      → <span class="font-semibold text-amber-700"><?= fcfa((int)$b['price_fcfa']) ?></span>
                    </div>
                  <?php endforeach ?>
                </div>
              <?php endif ?>
            </button>
          <?php endforeach ?>
        </div>
      </div>

      <!-- Prix calculé en temps réel -->
      <div x-show="selectedTariff" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
        <h3 class="font-semibold flex items-center gap-2">
          <i data-lucide="calculator" class="w-4 h-4 text-amber-500"></i> Calcul du prix
        </h3>
        <div class="flex items-end gap-3">
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 mb-1">Poids réel (kg) <span class="text-rose-500">*</span></label>
            <input type="number" x-model="weightKg" @input.debounce.300="calcPrice()"
                   min="0.1" step="0.1" placeholder="Ex : 12.5"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-amber-400 outline-none">
          </div>
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 mb-1">Long. (cm)</label>
            <input type="number" x-model="dims.length" @input.debounce.300="calcPrice()"
                   min="0" placeholder="—"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-amber-400 outline-none">
          </div>
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 mb-1">Larg. (cm)</label>
            <input type="number" x-model="dims.width" @input.debounce.300="calcPrice()"
                   min="0" placeholder="—"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-amber-400 outline-none">
          </div>
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 mb-1">Haut. (cm)</label>
            <input type="number" x-model="dims.height" @input.debounce.300="calcPrice()"
                   min="0" placeholder="—"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-amber-400 outline-none">
          </div>
        </div>

        <!-- Résumé prix -->
        <div x-show="priceResult" class="bg-amber-50 rounded-xl p-4 border border-amber-200">
          <template x-if="priceResult?.overweight">
            <div class="text-rose-600 font-semibold text-sm flex items-center gap-2 mb-2">
              <i data-lucide="alert-circle" class="w-4 h-4"></i>
              Poids dépasse le maximum autorisé pour ce tarif.
            </div>
          </template>
          <div class="grid grid-cols-3 gap-2 text-sm">
            <div>
              <div class="text-xs text-slate-500">Frais fixes</div>
              <div class="font-semibold" x-text="formatFcfa(priceResult?.base_fee ?? 0)"></div>
            </div>
            <div>
              <div class="text-xs text-slate-500">Frais poids</div>
              <div class="font-semibold" x-text="formatFcfa(priceResult?.weight_fee ?? 0)"></div>
            </div>
            <div x-show="priceResult?.volume_surcharge > 0">
              <div class="text-xs text-amber-700">Surcharge gabarit</div>
              <div class="font-semibold text-amber-700" x-text="formatFcfa(priceResult?.volume_surcharge ?? 0)"></div>
            </div>
          </div>
          <div class="mt-3 pt-3 border-t border-amber-200 flex justify-between items-center">
            <span class="font-bold text-amber-800">Total à percevoir</span>
            <span class="text-2xl font-bold text-amber-700" x-text="formatFcfa(priceResult?.total ?? 0)"></span>
          </div>
        </div>
      </div>

    <?php endif ?>
  </div>

  <!-- Formulaire de vente (panneau droit) ──────────────────────────────── -->
  <form method="post" action="<?= e(url('billetterie-bagages')) ?>" @submit="injectFormValues"
        class="bg-white rounded-2xl border border-slate-100 p-5 space-y-4 shadow-soft h-fit sticky top-20">
    <?= csrf_field() ?>
    <input type="hidden" name="trip_id"   value="<?= (int)$trip['id'] ?>">
    <input type="hidden" name="line_id"   value="<?= (int)$trip['line_id'] ?>">
    <input type="hidden" name="baggage_tariff_id"  x-model="selectedTariff.id">
    <input type="hidden" name="baggage_nature_id"  x-model="selectedTariff.baggage_nature_id">
    <input type="hidden" name="weight_kg"  x-model="weightKg">
    <input type="hidden" name="length_cm"  x-model="dims.length">
    <input type="hidden" name="width_cm"   x-model="dims.width">
    <input type="hidden" name="height_cm"  x-model="dims.height">

    <h3 class="font-semibold flex items-center gap-2">
      <i data-lucide="user" class="w-4 h-4 text-amber-500"></i> Propriétaire du bagage
    </h3>

    <!-- Sélectionner un passager existant du voyage -->
    <?php if (!empty($passengers)): ?>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Passager dans ce voyage</label>
        <select name="passenger_ticket_id" x-model="selectedPassengerId"
                @change="fillPassenger()"
                class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-amber-400 outline-none text-sm">
          <option value="0">— Saisir manuellement —</option>
          <?php foreach ($passengers as $p): ?>
            <option value="<?= (int)$p['id'] ?>"
                    data-name="<?= e($p['passenger_name']) ?>"
                    data-phone="<?= e($p['passenger_phone'] ?? '') ?>">
              <?= e($p['passenger_name']) ?>
              <?= $p['seat_number'] ? '· Siège ' . $p['seat_number'] : '' ?>
              (<?= e($p['ticket_number']) ?>)
            </option>
          <?php endforeach ?>
        </select>
      </div>
    <?php else: ?>
      <input type="hidden" name="passenger_ticket_id" value="0">
    <?php endif ?>

    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Nom complet <span class="text-rose-500">*</span></label>
      <input name="passenger_name" x-model="passengerName" required maxlength="120"
             class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-amber-400 outline-none"
             placeholder="Ex : Mabiala Jean">
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Téléphone</label>
      <input name="passenger_phone" x-model="passengerPhone" maxlength="20"
             class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-amber-400 outline-none"
             placeholder="06 XX XX XX">
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Description du bagage</label>
      <input name="description" maxlength="255"
             class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-amber-400 outline-none text-sm"
             placeholder="Ex : Valise noire, poignée rouge">
    </div>

    <!-- Récap tarif sélectionné -->
    <div x-show="selectedTariff" class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm">
      <div class="text-xs text-slate-500 mb-0.5">Tarif sélectionné</div>
      <div class="font-semibold text-amber-800" x-text="selectedTariff?.label ?? ''"></div>
      <div class="text-xs font-mono text-amber-600 mt-0.5" x-text="selectedTariff?.formula ?? ''"></div>
    </div>
    <div x-show="!selectedTariff" class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-sm text-slate-400 text-center">
      Sélectionnez un tarif à gauche
    </div>

    <!-- Total -->
    <div x-show="priceResult && !priceResult.overweight"
         class="flex justify-between items-center rounded-xl bg-amber-100 px-4 py-3">
      <span class="font-bold text-amber-900">À percevoir</span>
      <span class="text-xl font-bold text-amber-700" x-text="formatFcfa(priceResult?.total ?? 0)"></span>
    </div>

    <button type="submit"
            :disabled="!selectedTariff || !weightKg || weightKg <= 0 || (priceResult && priceResult.overweight)"
            class="w-full py-3 rounded-xl bg-amber-500 text-white font-semibold hover:bg-amber-600 transition
                   disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2">
      <i data-lucide="ticket" class="w-4 h-4"></i> Émettre le billet bagage
    </button>
  </form>

</div>

<script>
function baggageSale() {
  const TARIFFS = <?= json_encode(array_map(fn($t) => [
    'id'                 => (int)$t['id'],
    'baggage_nature_id'  => (int)$t['baggage_nature_id'],
    'label'              => $t['label'],
    'formula'            => $t['formula'],
    'bracket_mode'       => (int)$t['bracket_mode'],
    'max_weight_kg'      => $t['max_weight_kg'],
  ], $tariffs), JSON_THROW_ON_ERROR) ?>;

  const PASSENGERS = <?= json_encode(array_map(fn($p) => [
    'id'    => (int)$p['id'],
    'name'  => $p['passenger_name'],
    'phone' => $p['passenger_phone'] ?? '',
  ], $passengers), JSON_THROW_ON_ERROR) ?>;

  return {
    selectedTariff:    null,
    weightKg:          '',
    dims:              { length: '', width: '', height: '' },
    priceResult:       null,
    passengerName:     '',
    passengerPhone:    '',
    selectedPassengerId: 0,

    selectTariff(t) {
      this.selectedTariff = t;
      this.priceResult = null;
      if (this.weightKg) this.calcPrice();
    },

    fillPassenger() {
      const p = PASSENGERS.find(x => x.id == this.selectedPassengerId);
      if (p) {
        this.passengerName  = p.name;
        this.passengerPhone = p.phone;
      }
    },

    async calcPrice() {
      if (!this.selectedTariff || !this.weightKg || parseFloat(this.weightKg) <= 0) {
        this.priceResult = null; return;
      }
      const params = new URLSearchParams({
        tariff_id: this.selectedTariff.id,
        weight_kg: this.weightKg,
        length:    this.dims.length  || '',
        width:     this.dims.width   || '',
        height:    this.dims.height  || '',
        trip_id:   '<?= (int)$trip['id'] ?>',
      });
      try {
        const r = await fetch('<?= e(url('billetterie-bagages/calc')) ?>?' + params.toString());
        this.priceResult = await r.json();
        this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
      } catch {}
    },

    formatFcfa(n) {
      return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA';
    },

    injectFormValues() {
      // Les hidden inputs sont déjà liés par x-model
    },
  };
}
</script>

<?php $view->end() ?>
