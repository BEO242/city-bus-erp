<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5" x-data="parcelQuote()">
  <div>
    <a href="<?= e(url('cargo/parcels')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
  </div>

  <form method="post" action="<?= e(url('cargo/parcels')) ?>" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <?= csrf_field() ?>

    <!-- Colonne 1-2 : formulaire -->
    <div class="lg:col-span-2 space-y-5">

      <!-- Trajet -->
      <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-4">
        <h2 class="font-semibold text-slate-900">Trajet</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Origine *</label>
            <select name="origin_agency_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
              <option value="">— Sélectionner —</option>
              <?php foreach ($agencies as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= (int)old('origin_agency_id', auth()['agency_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Destination *</label>
            <select name="destination_agency_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
              <option value="">— Sélectionner —</option>
              <?php foreach ($agencies as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= (int)old('destination_agency_id', 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Expéditeur -->
      <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-4">
        <h2 class="font-semibold text-slate-900">Expéditeur</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nom *</label>
            <input name="sender_name" required value="<?= e(old('sender_name')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Téléphone *</label>
            <input name="sender_phone" required value="<?= e(old('sender_phone')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Pièce d'identité</label>
            <input name="sender_id_doc" value="<?= e(old('sender_id_doc')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Adresse</label>
            <input name="sender_address" value="<?= e(old('sender_address')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
        </div>
      </div>

      <!-- Destinataire -->
      <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-4">
        <h2 class="font-semibold text-slate-900">Destinataire</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nom *</label>
            <input name="recipient_name" required value="<?= e(old('recipient_name')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Téléphone *</label>
            <input name="recipient_phone" required value="<?= e(old('recipient_phone')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Pièce d'identité</label>
            <input name="recipient_id_doc" value="<?= e(old('recipient_id_doc')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Adresse</label>
            <input name="recipient_address" value="<?= e(old('recipient_address')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
        </div>
      </div>

      <!-- Caractéristiques -->
      <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-4">
        <h2 class="font-semibold text-slate-900">Colis</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie *</label>
            <select name="parcel_type" x-model="form.type" @change="recalc()" required
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
              <?php foreach ($types as $k => $lbl): ?>
                <option value="<?= e($k) ?>" <?= old('parcel_type', 'colis') === $k ? 'selected' : '' ?>>
                  <?= e($lbl) ?>
                </option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de pièces</label>
            <input type="number" min="1" name="pieces_count" value="<?= e(old('pieces_count', 1)) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Poids (kg) *</label>
            <input type="number" step="0.01" min="0" name="weight_kg" x-model="form.weight" @input.debounce.500ms="recalc()" required value="<?= e(old('weight_kg')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Volume (m³)</label>
            <input type="number" step="0.001" min="0" name="volume_m3" value="<?= e(old('volume_m3')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Description *</label>
            <input name="description" required value="<?= e(old('description')) ?>" placeholder="Contenu du colis (vêtements, électronique, papiers…)" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Valeur déclarée (FCFA)</label>
            <input type="number" min="0" name="declared_value_fcfa" x-model="form.declared" @input.debounce.500ms="recalc()" value="<?= e(old('declared_value_fcfa', 0)) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
            <p class="text-xs text-slate-400 mt-1">Sert au calcul de l'assurance.</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tarif</label>
            <select name="parcel_tariff_id" x-model="form.tariffId" @change="recalc()" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
              <option value="">Auto (selon poids)</option>
              <?php foreach ($tariffs as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= e($t['label']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-200"><?= e(old('notes')) ?></textarea>
          </div>
        </div>
      </div>

      <!-- Paiement -->
      <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
        <h2 class="font-semibold text-slate-900 mb-3">Paiement</h2>
        <div class="grid grid-cols-2 gap-4">
          <?php foreach ($methods as $k => $lbl): ?>
            <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 cursor-pointer hover:bg-cb-bg/30">
              <input type="radio" name="payment_method" value="<?= e($k) ?>" <?= old('payment_method', 'especes')===$k?'checked':'' ?>>
              <span class="text-sm"><?= e($lbl) ?></span>
            </label>
          <?php endforeach ?>
        </div>
      </div>
    </div>

    <!-- Colonne 3 : récap prix -->
    <div class="space-y-5">
      <div class="bg-gradient-to-br from-cb-primary to-cb-secondary text-white rounded-2xl p-6 shadow-soft sticky top-4">
        <h3 class="text-lg font-semibold mb-4">Récapitulatif</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="opacity-80">Prix de base</span><span x-text="fmt(quote.base)"></span></div>
          <div class="flex justify-between"><span class="opacity-80">Assurance</span><span x-text="fmt(quote.insurance)"></span></div>
          <div class="flex justify-between"><span class="opacity-80">Taxes</span><span x-text="fmt(quote.tax)"></span></div>
          <div class="border-t border-white/30 pt-3 flex justify-between font-bold text-lg">
            <span>Total</span><span x-text="fmt(quote.total)"></span>
          </div>
        </div>
        <button class="w-full mt-6 bg-white text-cb-primary font-semibold py-3 rounded-xl hover:bg-slate-100 transition">
          Enregistrer le dépôt
        </button>
      </div>
    </div>
  </form>
</div>

<script>
function parcelQuote() {
  return {
    form: {
      type: '<?= e(old('parcel_type', 'colis')) ?>',
      weight: parseFloat('<?= e(old('weight_kg', 0)) ?>') || 0,
      declared: parseInt('<?= e(old('declared_value_fcfa', 0)) ?>') || 0,
      tariffId: '',
    },
    quote: { base: 0, insurance: 0, tax: 0, total: 0 },
    fmt(n) { return new Intl.NumberFormat('fr-FR').format(n || 0) + ' FCFA'; },
    async recalc() {
      if (!this.form.weight || this.form.weight <= 0) {
        this.quote = { base: 0, insurance: 0, tax: 0, total: 0 };
        return;
      }
      const params = new URLSearchParams({
        type: this.form.type,
        weight_kg: this.form.weight,
        declared_value_fcfa: this.form.declared || 0,
      });
      if (this.form.tariffId) params.append('parcel_tariff_id', this.form.tariffId);
      try {
        const r = await fetch('<?= e(url('cargo/parcels/quote')) ?>?' + params.toString());
        const j = await r.json();
        this.quote = j;
      } catch (e) { console.error(e); }
    },
    init() { this.recalc(); }
  };
}
</script>
<?php $view->end() ?>
