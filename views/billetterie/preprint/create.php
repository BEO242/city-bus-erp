<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$_tripsJson = json_encode(array_map(fn($t) => [
    'id'                  => (int)$t['id'],
    'trip_code'           => $t['trip_code'],
    'trip_date'           => $t['trip_date'],
    'departure_scheduled' => $t['departure_scheduled'],
    'line_name'           => $t['line_name'],
    'departure_city'      => $t['departure_city'],
    'arrival_city'        => $t['arrival_city'],
    'seats'               => (int)$t['seats'],
    'bus_code'            => $t['bus_code'],
    'plate'               => $t['plate'],
], $trips), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

$_fretCatJson = json_encode(array_map(fn($c) => [
    'id' => (int)$c['id'], 'slug' => $c['slug'], 'name' => $c['name'],
], $fretCategories ?? []), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
?>
<?php $view->start('content') ?>
<div class="space-y-5">

  <!-- Fil d'Ariane -->
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('billetterie/preprint')) ?>" class="hover:text-cb-primary transition inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Pré-imprimés
    </a>
    <span>/</span>
    <span class="text-slate-800 font-medium">Générer un lot</span>
  </div>

  <!-- En-tête -->
  <div class="bg-gradient-to-br from-cb-primary to-cb-dark text-white rounded-2xl p-6 shadow-soft">
    <div class="flex items-center gap-4">
      <span class="w-14 h-14 bg-white/15 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="printer" class="w-7 h-7"></i>
      </span>
      <div>
        <h1 class="text-2xl font-bold">Générer un lot de supports</h1>
        <p class="text-white/70 text-sm mt-0.5">
          Chaque support reçoit un numéro unique, un code court (6 car.) et un QR code.
        </p>
      </div>
    </div>
  </div>

  <!-- Formulaire -->
  <form method="post" action="<?= e(url('billetterie/preprint')) ?>"
        novalidate
        class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden"
        x-data="preprintForm()">
    <?= csrf_field() ?>

    <div class="p-6 space-y-5">

      <!-- ════════════════════════════════════════════════════════
           Sélecteur de type de pré-impression (3 onglets)
           ════════════════════════════════════════════════════════ -->
      <div>
        <label class="cb-label mb-2">Type de support <span class="text-red-500">*</span></label>
        <div class="grid grid-cols-3 gap-3">
          <label class="cursor-pointer">
            <input type="radio" name="preprint_type" value="billet" x-model="preprintType" class="sr-only peer" checked>
            <div class="peer-checked:ring-2 peer-checked:ring-cb-primary peer-checked:border-cb-primary
                        border-2 border-slate-200 rounded-2xl p-4 hover:border-slate-300 transition text-center">
              <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-blue-50 flex items-center justify-center">
                <i data-lucide="ticket" class="w-5 h-5 text-blue-600"></i>
              </div>
              <div class="font-bold text-sm text-slate-800">Billet passager</div>
              <p class="text-[10px] text-slate-500 mt-1">Support classique lié à un voyage, avec siège</p>
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="preprint_type" value="talon_bagage" x-model="preprintType" class="sr-only peer">
            <div class="peer-checked:ring-2 peer-checked:ring-cb-primary peer-checked:border-cb-primary
                        border-2 border-slate-200 rounded-2xl p-4 hover:border-slate-300 transition text-center">
              <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-amber-50 flex items-center justify-center">
                <i data-lucide="luggage" class="w-5 h-5 text-amber-600"></i>
              </div>
              <div class="font-bold text-sm text-slate-800">Talon bagage</div>
              <p class="text-[10px] text-slate-500 mt-1">Talon pré-numéroté pour bagages, rempli à la main</p>
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="preprint_type" value="talon_colis" x-model="preprintType" class="sr-only peer">
            <div class="peer-checked:ring-2 peer-checked:ring-cb-primary peer-checked:border-cb-primary
                        border-2 border-slate-200 rounded-2xl p-4 hover:border-slate-300 transition text-center">
              <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-purple-50 flex items-center justify-center">
                <i data-lucide="package" class="w-5 h-5 text-purple-600"></i>
              </div>
              <div class="font-bold text-sm text-slate-800">Talon colis</div>
              <p class="text-[10px] text-slate-500 mt-1">Talon pré-numéroté pour colis/fret, rempli à la main</p>
            </div>
          </label>
        </div>
      </div>

      <!-- ════════════════════════════════════════════════════════
           SECTION BILLET : Voyage + type ticket + siège
           ════════════════════════════════════════════════════════ -->
      <template x-if="preprintType === 'billet'">
        <div class="space-y-5">

          <!-- Sélecteur de voyage -->
          <div>
            <label for="trip_id" class="cb-label">Voyage à pré-imprimer <span class="text-red-500">*</span></label>
            <select name="trip_id" id="trip_id" required
                    class="cb-input w-full"
                    x-model="selectedTripId"
                    @change="onTripChange()">
              <option value="">— Choisir un voyage —</option>
              <?php foreach ($trips as $t): ?>
                <option value="<?= (int)$t['id'] ?>">
                  <?= e($t['trip_code']) ?>
                  · <?= e($t['line_name']) ?>
                  (<?= e(date('d/m/Y', strtotime($t['trip_date']))) ?>
                  <?= e(substr($t['departure_scheduled'], 0, 5)) ?>)
                </option>
              <?php endforeach ?>
            </select>
            <?php if (!$trips): ?>
              <p class="cb-hint text-amber-600">Aucun voyage planifié disponible.</p>
            <?php endif ?>
          </div>

          <!-- Résumé du voyage -->
          <div x-show="selectedTrip" x-cloak
               class="bg-cb-bg border border-red-100 rounded-2xl p-4">
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div>
                <p class="text-xs text-slate-500 mb-0.5">Ligne</p>
                <p class="font-semibold text-slate-800" x-text="selectedTrip?.line_name"></p>
              </div>
              <div>
                <p class="text-xs text-slate-500 mb-0.5">Date &amp; heure</p>
                <p class="font-semibold text-slate-800" x-text="selectedTrip ? formatDate(selectedTrip.trip_date) + ' à ' + selectedTrip.departure_scheduled.slice(0,5) : ''"></p>
              </div>
              <div>
                <p class="text-xs text-slate-500 mb-0.5">Trajet</p>
                <p class="font-semibold text-slate-800" x-text="selectedTrip ? selectedTrip.departure_city + ' → ' + selectedTrip.arrival_city : ''"></p>
              </div>
              <div>
                <p class="text-xs text-slate-500 mb-0.5">Véhicule / capacité</p>
                <p class="font-semibold text-slate-800">
                  <span x-text="selectedTrip?.bus_code"></span> &mdash;
                  <span class="text-cb-primary font-bold" x-text="selectedTrip?.seats + ' sièges'"></span>
                </p>
              </div>
            </div>
          </div>

          <!-- Nombre de supports billet -->
          <div x-show="selectedTrip" x-cloak>
            <label for="size" class="cb-label">
              Nombre de supports <span class="text-slate-400 font-normal text-xs ml-1">(≤ capacité bus)</span>
              <span class="text-red-500">*</span>
            </label>
            <div class="flex items-center gap-3 mt-1">
              <input type="range" min="1" :max="selectedTrip?.seats ?? 500" step="1"
                     x-model.number="size"
                     class="flex-1 accent-cb-primary h-2 cursor-pointer">
              <input type="number" name="size" min="1" :max="selectedTrip?.seats ?? 500"
                     x-model.number="size"
                     class="cb-input w-24 text-center font-bold text-lg">
            </div>
          </div>

          <!-- Type de ticket -->
          <div>
            <label class="cb-label">Type de ticket <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-2">
              <?php
              $typeDefaults = [
                'passage_arret'   => ['label' => 'Arrêt anticipé',       'desc' => 'Passager — arrêt en cours de route',   'defColor' => '#C62828'],
                'passage_final'   => ['label' => 'Destination finale',   'desc' => 'Billet passager — destination finale',  'defColor' => '#1A237E'],
                'bagage_excedent' => ['label' => 'Bagages excédentaires','desc' => 'Colis/bagages hors quota inclus',       'defColor' => '#F57C00'],
                'bagage_inclus'   => ['label' => 'Bagages inclus',       'desc' => 'Bagages inclus dans le prix du billet', 'defColor' => '#1A237E'],
                'talon_arret'     => ['label' => 'Talon arrêt anticipé', 'desc' => 'Talon rouge — 3 sections.',             'defColor' => '#C62828'],
              ];
              foreach ($typeDefaults as $typeKey => $td):
                $cfg = $typeConfigs[$typeKey] ?? [];
                $bgColor  = $cfg['color']      ?? $td['defColor'];
                $fgColor  = $cfg['text_color'] ?? '#FFFFFF';
                $lblText  = $cfg['label']      ?? $td['label'];
              ?>
              <label class="cursor-pointer">
                <input type="radio" name="ticket_type" value="<?= e($typeKey) ?>"
                       x-model="ticketType" @change="ticketColor = ''"
                       class="sr-only peer"
                       <?= $typeKey === 'passage_arret' ? 'checked' : '' ?>>
                <div class="peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-slate-700 rounded-2xl overflow-hidden border border-slate-200 hover:border-slate-400 transition">
                  <div class="py-3 text-center font-bold text-sm"
                       style="background:<?= e($bgColor) ?>;color:<?= e($fgColor) ?>;">
                    <?= e($lblText) ?>
                  </div>
                  <div class="px-3 py-2 text-xs text-slate-500"><?= e($td['desc']) ?></div>
                </div>
              </label>
              <?php endforeach ?>
            </div>
          </div>

        </div>
      </template>

      <!-- ════════════════════════════════════════════════════════
           SECTION TALON BAGAGE / COLIS : pas de voyage requis
           ════════════════════════════════════════════════════════ -->
      <template x-if="preprintType !== 'billet'">
        <div class="space-y-5">

          <!-- Info -->
          <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex gap-3">
            <i data-lucide="info" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5"></i>
            <div class="text-xs text-amber-800">
              <p class="font-semibold mb-1" x-text="preprintType === 'talon_bagage' ? 'Talons bagage pré-imprimés' : 'Talons colis pré-imprimés'"></p>
              <p>Ces talons sont <strong>pré-numérotés</strong> avec un code court (6 caractères) + QR code.
                 Le convoyeur les remplit à la main au moment de l'enregistrement.
                 Un voyage peut être associé (optionnel).</p>
            </div>
          </div>

          <!-- Nombre de talons -->
          <div>
            <label class="cb-label">Nombre de talons <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-3 mt-1">
              <input type="range" min="1" max="500" step="1"
                     x-model.number="size"
                     class="flex-1 accent-cb-primary h-2 cursor-pointer">
              <input type="number" name="size" min="1" max="500"
                     x-model.number="size"
                     class="cb-input w-24 text-center font-bold text-lg">
            </div>
            <p class="cb-hint mt-1">Maximum 500 talons par lot.</p>
          </div>

          <!-- Voyage optionnel -->
          <div>
            <label class="cb-label">
              Voyage associé <span class="text-slate-400 font-normal text-xs">(optionnel)</span>
            </label>
            <select name="trip_id" class="cb-input w-full"
                    x-model="selectedTripId" @change="onTripChange()">
              <option value="">— Aucun voyage —</option>
              <?php foreach ($trips as $t): ?>
                <option value="<?= (int)$t['id'] ?>">
                  <?= e($t['trip_code']) ?> · <?= e($t['line_name']) ?>
                  (<?= e(date('d/m/Y', strtotime($t['trip_date']))) ?>)
                </option>
              <?php endforeach ?>
            </select>
          </div>

          <!-- Résumé voyage si choisi -->
          <div x-show="selectedTrip" x-cloak
               class="bg-slate-50 border border-slate-200 rounded-2xl p-4">
            <div class="grid grid-cols-3 gap-3 text-sm">
              <div>
                <p class="text-xs text-slate-500 mb-0.5">Ligne</p>
                <p class="font-semibold text-slate-800" x-text="selectedTrip?.line_name"></p>
              </div>
              <div>
                <p class="text-xs text-slate-500 mb-0.5">Date</p>
                <p class="font-semibold text-slate-800" x-text="selectedTrip ? formatDate(selectedTrip.trip_date) : ''"></p>
              </div>
              <div>
                <p class="text-xs text-slate-500 mb-0.5">Trajet</p>
                <p class="font-semibold text-slate-800" x-text="selectedTrip ? selectedTrip.departure_city + ' → ' + selectedTrip.arrival_city : ''"></p>
              </div>
            </div>
          </div>

        </div>
      </template>

      <!-- ════════════════════════════════════════════════════════
           Champs communs
           ════════════════════════════════════════════════════════ -->

      <!-- Couleur personnalisée -->
      <div>
        <label for="ticket_color" class="cb-label">
          Couleur personnalisée
          <span class="text-slate-400 font-normal text-xs ml-1">(optionnel)</span>
        </label>
        <div class="flex items-center gap-3 mt-1">
          <input type="color" name="ticket_color" id="ticket_color"
                 x-model="ticketColor"
                 class="h-10 w-16 rounded-xl border border-slate-200 cursor-pointer p-1">
          <code class="text-sm font-mono bg-slate-100 px-3 py-2 rounded-xl" x-text="ticketColor || '(couleur par défaut)'"></code>
          <button type="button" @click="ticketColor=''" class="text-xs text-slate-400 hover:text-slate-600 underline">Réinitialiser</button>
          <div x-show="ticketColor" class="px-4 py-2 rounded-xl text-sm font-bold" :style="'background:'+ticketColor+';color:#fff'">Aperçu</div>
        </div>
      </div>

      <!-- Agence (admin sans agence uniquement) -->
      <?php if ($userAgencyId === 0 && $agencies): ?>
      <div>
        <label for="agency_id" class="cb-label">Agence dépositaire <span class="text-red-500">*</span></label>
        <select name="agency_id" id="agency_id" class="cb-input w-full" required>
          <option value="">— Choisir une agence —</option>
          <?php foreach ($agencies as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <?php endif ?>

      <!-- Notes -->
      <div>
        <label for="notes" class="cb-label">Notes <span class="text-slate-400 font-normal">(optionnel)</span></label>
        <textarea name="notes" id="notes" rows="2"
                  placeholder="Ex. Lot Pointe-Noire — fête nationale 2026…"
                  class="cb-input w-full resize-none"></textarea>
      </div>

      <!-- Info workflow -->
      <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex gap-3">
        <i data-lucide="info" class="w-5 h-5 text-slate-400 shrink-0 mt-0.5"></i>
        <ul class="text-xs text-slate-600 space-y-1 list-disc list-inside">
          <template x-if="preprintType === 'billet'">
            <span>
              <li>Le PDF contient un <strong>billet</strong> (remis au passager) + un <strong>talon</strong> (conservé par le vendeur).</li>
              <li>Les informations du voyage sont <strong>pré-imprimées</strong>. Le vendeur <strong>active</strong> le support en scannant le QR.</li>
            </span>
          </template>
          <template x-if="preprintType !== 'billet'">
            <span>
              <li>Chaque talon porte un <strong>code court</strong> (6 car.) lisible + un <strong>QR code</strong> pour scanner.</li>
              <li>Le convoyeur remplit le talon à la main : nom, téléphone, poids, nombre de colis…</li>
              <li>Le talon peut être <strong>associé</strong> ultérieurement à un enregistrement fret dans le système.</li>
            </span>
          </template>
          <li>Un support non activé peut être <strong>annulé</strong> en cas d'erreur.</li>
        </ul>
      </div>

    </div>

    <!-- Barre d'actions -->
    <div class="border-t border-slate-100 bg-slate-50 px-6 py-4 flex items-center justify-between">
      <a href="<?= e(url('billetterie/preprint')) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 transition text-sm font-medium inline-flex items-center gap-2">
        <i data-lucide="x" class="w-4 h-4"></i> Annuler
      </a>
      <button type="submit" :disabled="preprintType === 'billet' && !selectedTrip"
              class="px-6 py-2.5 rounded-xl bg-cb-primary text-white font-bold hover:bg-cb-dark transition shadow-soft inline-flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
        <i data-lucide="printer" class="w-4 h-4"></i>
        <span x-text="preprintType === 'billet' ? 'Générer billets' : 'Générer talons'">Générer</span>
      </button>
    </div>
  </form>

<script>
const TRIPS_DATA = <?= $_tripsJson ?>;
function preprintForm() {
  return {
    trips: TRIPS_DATA,
    preprintType: 'billet',
    selectedTripId: '',
    selectedTrip: null,
    size: 50,
    ticketType: 'passage_arret',
    ticketColor: '',
    onTripChange() {
      this.selectedTrip = this.trips.find(t => t.id == this.selectedTripId) || null;
      if (this.selectedTrip && this.preprintType === 'billet') {
        this.size = this.selectedTrip.seats;
      }
    },
    formatDate(d) {
      if (!d) return '';
      const [y, m, day] = d.split('-');
      return `${day}/${m}/${y}`;
    },
  };
}
</script>

</div>
<?php $view->end() ?>
