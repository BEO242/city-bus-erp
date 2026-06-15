<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$isEdit  = !empty($tariff);
$action  = $action ?? url('referentiel/tariffs');
$backUrl = url('referentiel/tariffs');

$curLineId    = (int)($tariff['line_id']              ?? old('line_id',  (int)($_GET['line_id'] ?? 0)));
$curOriginStop = (int)($tariff['origin_stop_id']      ?? old('origin_stop_id', 0));
$curDestStop  = (int)($tariff['destination_stop_id']  ?? old('destination_stop_id', 0));
$stopsJson    = json_encode($stops ?? []);
$curType    = $tariff['ticket_type']   ?? old('ticket_type',    'aller_simple');
$curClass   = $tariff['travel_class']  ?? old('travel_class',   'standard');
$curPrice   = $tariff['price_fcfa']    ?? old('price_fcfa',     '');
$curLabel   = $tariff['label']         ?? old('label',          '');
$curFrom    = $tariff['valid_from']    ?? old('valid_from',     '');
$curUntil   = $tariff['valid_until']   ?? old('valid_until',    '');
$curNotes   = $tariff['notes']         ?? old('notes',          '');
$curActive  = isset($tariff['is_active']) ? (int)$tariff['is_active'] : 1;
$curBagQty  = (int)($tariff['baggage_included_qty']  ?? old('baggage_included_qty', 0));
$curBagKg   = (float)($tariff['baggage_included_kg'] ?? old('baggage_included_kg', 0));

// Catégories cochées
$decoded = json_decode($tariff['passenger_categories'] ?? '[]', true);
$curCats = is_array($decoded) && !empty($decoded) ? $decoded : ['adulte'];

// Détecter le type de la ligne courante (pour l'édition)
$curLineType = 'interurbain'; // défaut
foreach ($lines as $l) {
    if ((int)$l['id'] === $curLineId) {
        $curLineType = $l['line_type'] ?? 'interurbain';
        break;
    }
}

// Construire la map lineId → line_type pour Alpine.js
$lineTypeMap = [];
foreach ($lines as $l) {
    $lineTypeMap[(int)$l['id']] = $l['line_type'] ?? 'interurbain';
}

$ticketTypes = $ticketTypes ?? [
  'aller_simple'  => 'Aller simple',
  'aller_retour'  => 'Aller-retour',
  'abonnement'    => 'Abonnement mensuel',
  'groupe'        => 'Groupe / collectif',
];
$travelClasses = $travelClasses ?? [
  'standard'   => 'Standard',
  'vip'        => 'VIP / Confort',
  'economique' => 'Économique',
];
$passengerCategories = [
  'adulte'   => ['label' => 'Adulte',   'icon' => 'user'],
  'enfant'   => ['label' => 'Enfant',   'icon' => 'baby'],
  'etudiant' => ['label' => 'Étudiant', 'icon' => 'graduation-cap'],
  'senior'   => ['label' => 'Senior',   'icon' => 'user-check'],
  'vip'      => ['label' => 'VIP',      'icon' => 'star'],
];

// Types par contexte (pour le JS)
$urbanTypeKeys      = ['course_simple','carnet_10','abonnement_journalier','abonnement_hebdo','abonnement'];
$interurbainTypeKeys = ['aller_simple','aller_retour','abonnement','groupe'];

// Construire la liste complète des types avec labels pour le JS
$allTypesForJs = [];
foreach ($ticketTypes as $slug => $label) {
    $allTypesForJs[$slug] = $label;
}
?>

<div class="space-y-5">

  <!-- En-tête -->
  <div class="flex items-center gap-3">
    <a href="<?= e($backUrl) ?>" class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-xl font-black text-slate-900"><?= e($title) ?></h1>
      <p class="text-xs text-slate-400 mt-0.5"><?= $isEdit ? 'Modifier le tarif passager' : 'Créer un nouveau tarif passager' ?></p>
    </div>
  </div>

  <script>
    window.__tariffStops    = <?= $stopsJson ?>;
    window.__lineTypeMap    = <?= json_encode($lineTypeMap, JSON_FORCE_OBJECT) ?>;
    window.__urbanTypes     = <?= json_encode($urbanTypeKeys) ?>;
    window.__interurbainTypes = <?= json_encode($interurbainTypeKeys) ?>;
    window.__allTicketTypes = <?= json_encode($allTypesForJs, JSON_UNESCAPED_UNICODE) ?>;

    function tariffLineForm() {
        return {
            lineId:     '<?= $curLineId ?>',
            lineType:   '<?= e($curLineType) ?>',
            originStop: '<?= $curOriginStop ?>',
            destStop:   '<?= $curDestStop ?>',
            stops:      window.__tariffStops   || {},
            lineTypeMap:window.__lineTypeMap   || {},
            allTypes:   window.__allTicketTypes|| {},
            selectedType: '<?= e($curType) ?>',

            // ── Computed ─────────────────────────────────────────────────
            get isUrban() {
                return this.lineType === 'urbain';
            },

            get lineStops() {
                return (this.lineId && this.lineId !== '0')
                    ? (this.stops[this.lineId] || [])
                    : [];
            },
            get firstStop() { return this.lineStops[0] || null; },
            get lastStop()  { return this.lineStops[this.lineStops.length - 1] || null; },

            get boardingStops() {
                if (!this.lineStops.length) return [];
                const firstPos = this.firstStop ? this.firstStop.order_position : -1;
                const lastPos  = this.lastStop  ? this.lastStop.order_position  : 9999;
                const destStop = this.lineStops.find(s => String(s.id) === String(this.destStop));
                const dPos     = destStop ? destStop.order_position : 9999;
                return this.lineStops.filter(s =>
                    s.order_position > firstPos && s.order_position < Math.min(lastPos, dPos)
                );
            },

            get destinationStops() {
                if (!this.lineStops.length) return [];
                const firstPos = this.firstStop ? this.firstStop.order_position : -1;
                const lastPos  = this.lastStop  ? this.lastStop.order_position  : 9999;
                const origStop = this.lineStops.find(s => String(s.id) === String(this.originStop));
                const oPos     = origStop ? origStop.order_position : -1;
                return this.lineStops.filter(s =>
                    s.order_position > Math.max(firstPos, oPos) && s.order_position < lastPos
                );
            },

            get originStopName() {
                const s = this.lineStops.find(s => String(s.id) === String(this.originStop));
                return s ? s.name : '';
            },
            get destStopName() {
                const s = this.lineStops.find(s => String(s.id) === String(this.destStop));
                return s ? s.name : '';
            },

            // Types de billets filtrés selon le contexte
            get filteredTypes() {
                const keys = this.isUrban
                    ? window.__urbanTypes
                    : window.__interurbainTypes;
                return keys
                    .filter(k => this.allTypes[k] !== undefined)
                    .map(k => ({ slug: k, label: this.allTypes[k] }));
            },

            // ── Handlers ─────────────────────────────────────────────────
            onLineChange(val) {
                this.lineId   = val;
                this.lineType = this.lineTypeMap[parseInt(val)] || 'interurbain';
                this.originStop = '';
                this.destStop   = '';
                // Recaler le type de billet si incompatible
                const keys = this.isUrban ? window.__urbanTypes : window.__interurbainTypes;
                if (!keys.includes(this.selectedType)) {
                    this.selectedType = keys[0] || '';
                }
            },
            // Vrai si les deux arrêts explicitement sélectionnés ont le même nom
            get hasSameStopWarning() {
                if (!this.lineId || this.lineId === '0') return false;
                // originStopName / destStopName retournent '' quand rien n'est sélectionné
                const o = (this.originStopName || '').trim().toLowerCase();
                const d = (this.destStopName   || '').trim().toLowerCase();
                return o !== '' && d !== '' && o === d;
            },

            onOriginChange() {
                if (this.originStop && this.destStop) {
                    const orig = this.lineStops.find(s => String(s.id) === String(this.originStop));
                    const dest = this.lineStops.find(s => String(s.id) === String(this.destStop));
                    if (dest && orig && dest.order_position <= orig.order_position) {
                        this.destStop = '';
                    }
                }
            },
            onDestChange() {
                if (this.destStop && this.originStop) {
                    const orig = this.lineStops.find(s => String(s.id) === String(this.originStop));
                    const dest = this.lineStops.find(s => String(s.id) === String(this.destStop));
                    if (orig && dest && orig.order_position >= dest.order_position) {
                        this.originStop = '';
                    }
                }
            }
        };
    }
  </script>

  <form method="post" action="<?= e($action) ?>" class="space-y-4"
        x-data="tariffLineForm()">
    <?= csrf_field() ?>

    <!-- ═══════════════════════════════════════════════════════════════════
         BLOC 1 : Ligne & arrêts
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="route" class="w-4 h-4 text-cb-primary"></i> Ligne &amp; arrêts
      </h2>

      <!-- Badge urbain (visible dès qu'une ligne urbaine est sélectionnée) -->
      <div x-show="isUrban" x-transition
           class="flex items-start gap-2 px-3 py-2.5 rounded-xl bg-indigo-50 border border-indigo-200 text-xs text-indigo-800">
        <i data-lucide="bus" class="w-4 h-4 mt-0.5 text-indigo-600 flex-shrink-0"></i>
        <div>
          <span class="font-bold">Ligne urbaine</span> — le formulaire s'adapte automatiquement :
          tarif plat, pas de bagage, pas de classe de voyage.
          Les arrêts sont optionnels (utiles pour une tarification par zone).
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Sélecteur de ligne (groupé urbain / interurbain) -->
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            Ligne <span class="text-rose-500">*</span>
          </label>
          <select name="line_id" required
                  @change="onLineChange($event.target.value)"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm bg-white">
            <option value="">— Sélectionner une ligne —</option>
            <?php
            // Grouper les lignes par type pour le <optgroup>
            $linesByType = ['urbain' => [], 'interurbain' => []];
            foreach ($lines as $l) {
                $linesByType[$l['line_type'] ?? 'interurbain'][] = $l;
            }
            ?>
            <?php if (!empty($linesByType['urbain'])): ?>
            <optgroup label="🏙 Lignes urbaines">
              <?php foreach ($linesByType['urbain'] as $l): ?>
                <option value="<?= (int)$l['id'] ?>" <?= $curLineId === (int)$l['id'] ? 'selected' : '' ?>>
                  <?= e($l['code']) ?> — <?= e($l['name']) ?>
                </option>
              <?php endforeach ?>
            </optgroup>
            <?php endif ?>
            <?php if (!empty($linesByType['interurbain'])): ?>
            <optgroup label="🛣 Lignes interurbaines">
              <?php foreach ($linesByType['interurbain'] as $l): ?>
                <option value="<?= (int)$l['id'] ?>" <?= $curLineId === (int)$l['id'] ? 'selected' : '' ?>>
                  <?= e($l['code']) ?> — <?= e($l['name']) ?>
                </option>
              <?php endforeach ?>
            </optgroup>
            <?php endif ?>
          </select>
        </div>

        <!-- Arrêt d'embarquement -->
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            <i data-lucide="circle-dot" class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5 text-cb-primary"></i>
            <span x-text="isUrban ? 'Zone de départ' : 'Arrêt d\'embarquement'"></span>
            <span class="text-slate-400 font-normal">(optionnel)</span>
          </label>
          <div x-show="!lineId || lineId === '0'"
               class="w-full px-3 py-2.5 rounded-xl border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-400 italic">
            Sélectionnez d'abord une ligne
          </div>
          <div x-show="lineId && lineId !== '0' && lineStops.length === 0"
               class="w-full px-3 py-2.5 rounded-xl border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-400 italic">
            Aucun arrêt configuré
          </div>
          <select name="origin_stop_id"
                  x-show="lineId && lineId !== '0' && lineStops.length > 0"
                  @change="originStop = $event.target.value; onOriginChange()"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm bg-white">
            <option value="" x-text="firstStop ? '— ' + firstStop.name + ' —' : '— Départ de ligne —'"></option>
            <template x-for="s in boardingStops" :key="s.id">
              <option :value="s.id"
                      :selected="String(s.id) === String(originStop)"
                      x-text="s.name"></option>
            </template>
          </select>
        </div>

        <!-- Arrêt(s) de destination -->
        <div class="sm:col-span-2">
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            <i data-lucide="map-pin" class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5 text-rose-500"></i>
            <span x-text="isUrban ? 'Zone d\'arrivée' : 'Arrêt(s) de destination'"></span>
            <span class="text-slate-400 font-normal">
              (optionnel<?= !$isEdit ? ' · multi-sélection possible' : '' ?>)
            </span>
          </label>
          <div x-show="!lineId || lineId === '0'"
               class="w-full px-3 py-2.5 rounded-xl border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-400 italic">
            Sélectionnez d'abord une ligne
          </div>
          <div x-show="lineId && lineId !== '0' && lineStops.length === 0"
               class="w-full px-3 py-2.5 rounded-xl border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-400 italic">
            Aucun arrêt configuré
          </div>

          <?php if ($isEdit): ?>
          <select name="destination_stop_id"
                  x-show="lineId && lineId !== '0' && lineStops.length > 0"
                  @change="destStop = $event.target.value; onDestChange()"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm bg-white">
            <option value="" x-text="lastStop ? '— ' + lastStop.name + ' (Terminus) —' : '— Terminus —'"></option>
            <template x-for="s in destinationStops" :key="s.id">
              <option :value="s.id"
                      :selected="String(s.id) === String(destStop)"
                      x-text="s.name"></option>
            </template>
          </select>
          <?php else: ?>
          <div x-show="lineId && lineId !== '0' && lineStops.length > 0"
               class="rounded-xl border border-slate-200 bg-white divide-y divide-slate-50 max-h-48 overflow-y-auto">
            <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-slate-50 cursor-pointer transition">
              <input type="checkbox" name="destination_stop_ids[]" value="0"
                     class="w-4 h-4 rounded border-slate-300 accent-cb-primary" checked>
              <span class="text-sm text-slate-700" x-text="lastStop ? lastStop.name + ' (Terminus)' : 'Terminus'"></span>
              <span class="text-[10px] bg-rose-50 text-rose-600 px-1.5 py-0.5 rounded font-semibold ml-auto">terminus</span>
            </label>
            <template x-for="s in destinationStops" :key="s.id">
              <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-slate-50 cursor-pointer transition">
                <input type="checkbox" name="destination_stop_ids[]" :value="s.id"
                       class="w-4 h-4 rounded border-slate-300 accent-cb-primary">
                <span class="text-sm text-slate-700" x-text="s.name"></span>
                <span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-semibold ml-auto"
                      x-text="'pos. ' + s.order_position"></span>
              </label>
            </template>
          </div>
          <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1" x-show="!isUrban && lineId && lineId !== '0' && lineStops.length > 0">
            <i data-lucide="info" class="w-3 h-3"></i>
            Cochez plusieurs destinations pour créer un tarif identique pour chacune.
          </p>
          <p class="text-[11px] text-indigo-400 mt-1.5 flex items-center gap-1" x-show="isUrban && lineId && lineId !== '0' && lineStops.length > 0">
            <i data-lucide="info" class="w-3 h-3"></i>
            En tarif plat urbain, laissez terminus coché (tarif unique sur toute la ligne).
            Cochez plusieurs zones seulement si vous appliquez une tarification par tronçon.
          </p>
          <?php endif ?>
        </div>
      </div>

      <!-- Badge récapitulatif trajet (interurbain) -->
      <div x-show="!isUrban && ((originStop && originStop !== '0') || (destStop && destStop !== '0'))"
           class="flex items-center gap-2 text-xs text-slate-500 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2">
        <i data-lucide="route" class="w-3.5 h-3.5 text-amber-500 flex-shrink-0"></i>
        <span>
          Ce tarif s'applique au segment
          <strong class="text-amber-700"
            x-text="(originStopName || (firstStop?.name ?? 'Départ')) + ' → ' + (destStopName || (lastStop?.name ?? 'Terminus'))"></strong>.
        </span>
      </div>

      <!-- Avertissement : arrêt origine = arrêt destination (même nom) -->
      <div x-show="hasSameStopWarning" x-transition
           class="flex items-start gap-2 px-3 py-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
        <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 text-rose-600 flex-shrink-0"></i>
        <div>
          <span class="font-bold">Arrêts identiques</span> —
          l'arrêt d'embarquement et l'arrêt de destination ont le même nom
          (<span class="font-mono font-semibold" x-text="originStopName"></span>).
          Un tarif doit couvrir un segment entre deux arrêts distincts.
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         BLOC 2 : Conditions tarifaires
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="ticket" class="w-4 h-4 text-cb-primary"></i> Conditions tarifaires
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <!-- Type de billet (dynamique selon ligne) -->
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            Type de billet <span class="text-rose-500">*</span>
          </label>
          <!-- Rendu dynamique par Alpine.js pour filtrer selon le contexte urbain/interurbain -->
          <select name="ticket_type" required x-model="selectedType"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm bg-white">
            <template x-for="t in filteredTypes" :key="t.slug">
              <option :value="t.slug" x-text="t.label"></option>
            </template>
          </select>
          <!-- Explication contextuelle -->
          <p x-show="isUrban" class="text-[11px] text-indigo-500 mt-1 flex items-center gap-1">
            <i data-lucide="info" class="w-3 h-3"></i>
            Types filtrés pour le réseau urbain.
          </p>
        </div>

        <!-- Classe de voyage : visible uniquement pour les lignes interurbaines -->
        <div x-show="!isUrban" x-transition>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            Classe de voyage <span class="text-rose-500">*</span>
          </label>
          <!-- :disabled="isUrban" → non soumis quand ligne urbaine (évite le doublon) -->
          <select name="travel_class" :disabled="isUrban"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm bg-white">
            <?php foreach ($travelClasses as $k => $lbl): ?>
              <option value="<?= $k ?>" <?= $curClass === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <!-- Soumis UNIQUEMENT pour les lignes urbaines (:disabled="!isUrban") -->
        <input type="hidden" name="travel_class" value="standard" :disabled="!isUrban">

      </div>

      <!-- Catégories passager -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-2">
          Catégorie(s) passager <span class="text-rose-500">*</span>
        </label>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($passengerCategories as $k => $cat): ?>
          <?php if ($k === 'vip'): // VIP pas pertinent en urbain — toujours disponible mais signalé ?>
          <label class="cursor-pointer" x-show="!isUrban || '<?= $k ?>' !== 'vip'" x-transition>
          <?php else: ?>
          <label class="cursor-pointer">
          <?php endif ?>
            <input type="checkbox" name="passenger_category[]" value="<?= $k ?>"
                   <?= in_array($k, $curCats) ? 'checked' : '' ?> class="peer sr-only">
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border-2 border-slate-200 text-xs font-semibold text-slate-600 transition
                        peer-checked:border-cb-primary peer-checked:bg-cb-bg peer-checked:text-cb-primary hover:border-slate-300">
              <i data-lucide="<?= $cat['icon'] ?>" class="w-3 h-3"></i>
              <?= e($cat['label']) ?>
            </div>
          </label>
          <?php endforeach ?>
        </div>
        <p class="text-[11px] text-slate-400 mt-1.5">
          Cochez toutes les catégories couvertes par ce tarif.
        </p>
      </div>

      <!-- Prix -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          <span x-text="isUrban ? 'Prix du ticket (FCFA)' : 'Prix (FCFA)'"></span>
          <span class="text-rose-500">*</span>
        </label>
        <div class="relative max-w-xs">
          <input type="number" name="price_fcfa" required min="0" step="1"
                 value="<?= e($curPrice) ?>"
                 class="w-full pl-3 pr-16 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-right text-lg font-bold text-cb-primary"
                 placeholder="0">
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">FCFA</span>
        </div>
        <p x-show="isUrban" class="text-[11px] text-indigo-500 mt-1 flex items-center gap-1">
          <i data-lucide="info" class="w-3 h-3"></i>
          En transport urbain, saisissez le tarif unique par course (ex : 200 FCFA).
        </p>
      </div>

      <!-- Libellé personnalisé -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Libellé personnalisé
          <span class="text-slate-400 font-normal ml-1">(optionnel)</span>
        </label>
        <input type="text" name="label" maxlength="100" value="<?= e($curLabel) ?>"
               :placeholder="isUrban ? 'Ex : Tarif réduit scolaire, Nuit…' : 'Ex : Tarif scolaire, Promo fêtes…'"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         BLOC 3 : Franchise bagage — interurbain uniquement
         ═══════════════════════════════════════════════════════════════════ -->
    <div x-show="!isUrban" x-transition
         class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="luggage" class="w-4 h-4 text-cb-primary"></i> Franchise bagage incluse
        <span class="text-slate-400 font-normal text-xs ml-1">(comprise dans le prix)</span>
      </h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Nombre de bagages</label>
          <div class="relative">
            <!-- :disabled="isUrban" → non soumis quand ligne urbaine -->
            <input type="number" name="baggage_included_qty" min="0" max="99" step="1"
                   :disabled="isUrban"
                   value="<?= $curBagQty ?>"
                   class="w-full px-3 pr-14 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">pièces</span>
          </div>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Poids inclus (kg)</label>
          <div class="relative">
            <!-- :disabled="isUrban" → non soumis quand ligne urbaine -->
            <input type="number" name="baggage_included_kg" min="0" max="999" step="0.5"
                   :disabled="isUrban"
                   value="<?= number_format($curBagKg, 1, '.', '') ?>"
                   class="w-full px-3 pr-8 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">kg</span>
          </div>
        </div>
      </div>
    </div>
    <!-- Soumis UNIQUEMENT pour les lignes urbaines (force qty=0 et kg=0) -->
    <input type="hidden" name="baggage_included_qty" value="0" :disabled="!isUrban">
    <input type="hidden" name="baggage_included_kg"  value="0" :disabled="!isUrban">

    <!-- ═══════════════════════════════════════════════════════════════════
         BLOC 4 : Validité & notes
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="calendar-range" class="w-4 h-4 text-cb-primary"></i> Validité &amp; notes
      </h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Début de validité</label>
          <input type="date" name="valid_from" value="<?= e($curFrom) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">
            Fin de validité
            <span class="text-slate-400 font-normal">(vide = permanent)</span>
          </label>
          <input type="date" name="valid_until" value="<?= e($curUntil) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        </div>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Notes <span class="text-slate-400 font-normal">(optionnel)</span>
        </label>
        <textarea name="notes" rows="2" maxlength="2000"
                  :placeholder="isUrban
                    ? 'Ex : Valable uniquement en heures de pointe…'
                    : 'Ex : Valable sur présentation d\'une pièce d\'identité…'"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm resize-none"><?= e($curNotes) ?></textarea>
      </div>
      <?php if ($isEdit): ?>
      <label class="flex items-center gap-2 cursor-pointer pt-1">
        <input type="checkbox" name="is_active" value="1" <?= $curActive ? 'checked' : '' ?>
               class="w-4 h-4 rounded border-slate-300 accent-cb-primary">
        <span class="text-sm text-slate-700 font-semibold">Tarif actif</span>
      </label>
      <?php endif ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         Actions
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
      <a href="<?= e($backUrl) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
        Annuler
      </a>
      <button type="submit"
              :disabled="hasSameStopWarning"
              :class="hasSameStopWarning
                ? 'opacity-50 cursor-not-allowed bg-cb-primary'
                : 'bg-cb-primary hover:bg-cb-dark'"
              class="px-6 py-2.5 rounded-xl text-white text-sm font-bold transition flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        <?= $isEdit ? 'Mettre à jour' : 'Créer le tarif' ?>
      </button>
    </div>

  </form>
</div>
<?php $view->end() ?>
