<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Line;
use CityBus\Models\Tariff;
$view->extends('layouts/app');

$isEdit   = !empty($line);
$lineId   = $line['id'] ?? 0;
$action   = $isEdit ? url('referentiel/lines/'.$lineId) : url('referentiel/lines');
$gallery  = $gallery  ?? [];
$docs     = $docs     ?? [];
$agencies = $agencies ?? [];
$lineType = old('line_type', $line['line_type'] ?? 'interurbain');

$stopsInit = array_values(array_map(fn($s) => [
    'id'             => (int)$s['id'],
    'name'           => $s['name'],
    'km_from_origin' => $s['km_from_origin'] ?? '',
    'agency_id'      => $s['agency_id'] ?? '',
    'order_position' => (int)$s['order_position'],
], $stops));
$stopsJson    = json_encode($stopsInit, JSON_UNESCAPED_UNICODE);
$agenciesJson = json_encode(array_values(array_map(
    fn($a) => ['id' => $a['id'], 'name' => $a['name']], $agencies
)), JSON_UNESCAPED_UNICODE);
?>
<?php $view->start('content') ?>

<script>
(function () {
  var S = <?= $stopsJson ?>, A = <?= $agenciesJson ?>;
  window._stopsManager = function () {
    function byKm(a, b) {
      return (parseFloat(a.km_from_origin) || 0) - (parseFloat(b.km_from_origin) || 0);
    }
    return {
      stops: S.map(function (s, i) { return Object.assign({ _key: i }, s); }).sort(byKm),
      agencies: A, _key: S.length,
      newStop: { name: '', km_from_origin: '', agency_id: '' },
      addStop: function () {
        var n = this.newStop.name.trim(); if (!n) return;
        this.stops.push({ _key: this._key++, id: 0, name: n,
          km_from_origin: this.newStop.km_from_origin,
          agency_id: this.newStop.agency_id,
          order_position: 0 });
        this.newStop = { name: '', km_from_origin: '', agency_id: '' };
        this.reorder();
      },
      removeStop: function (i) { this.stops.splice(i, 1); this.reorder(); },
      reorder: function () {
        this.stops.sort(byKm);
        this.stops.forEach(function (s, i) { s.order_position = i + 1; });
      },
      get stopsJson() {
        return JSON.stringify(this.stops.map(function (s) {
          return { id: s.id, name: s.name, km_from_origin: s.km_from_origin,
                   agency_id: s.agency_id, order_position: s.order_position };
        }));
      }
    };
  };
})();
</script>

<div x-data="{ tab: 'identification', lineType: '<?= e($lineType) ?>', get isUrbain() { return this.lineType === 'urbain'; } }" class="space-y-5 pb-8">

  <!-- ── PAGE HEADER ─────────────────────────────────────────── -->
  <div class="bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">

    <!-- Bandeau couleur -->
    <div class="h-1 bg-gradient-to-r from-cb-primary to-cb-accent"></div>

    <div class="px-6 pt-5 pb-0">

      <!-- Breadcrumb + titre -->
      <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
          <a href="<?= e($isEdit ? url('referentiel/lines/'.$lineId) : url('referentiel/lines')) ?>"
             class="p-1.5 rounded-lg text-slate-400 hover:text-cb-primary hover:bg-cb-bg transition shrink-0">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
          </a>
          <div>
            <div class="text-xs text-slate-400 flex items-center gap-1 mb-0.5">
              <a href="<?= e(url('referentiel/lines')) ?>" class="hover:text-cb-primary">Lignes</a>
              <i data-lucide="chevron-right" class="w-3 h-3"></i>
              <span><?= $isEdit ? e($line['code']) : 'Nouvelle ligne' ?></span>
            </div>
            <h1 class="text-lg font-bold text-slate-900"><?= e($title) ?></h1>
          </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="flex items-center gap-2 text-xs shrink-0">
          <div class="px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-100 text-center">
            <div class="font-bold text-slate-800"><?= count($stops) ?></div>
            <div class="text-slate-400">Arrêts</div>
          </div>
          <div class="px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-100 text-center">
            <div class="font-bold text-slate-800"><?= count($tariffs ?? []) ?></div>
            <div class="text-slate-400">Tarifs</div>
          </div>
          <div class="px-3 py-1.5 rounded-lg border text-center
               <?= !empty($line['is_active']) ? 'bg-emerald-50 border-emerald-100' : 'bg-slate-50 border-slate-100' ?>">
            <div class="font-bold <?= !empty($line['is_active']) ? 'text-emerald-600' : 'text-slate-400' ?>">
              <?= !empty($line['is_active']) ? 'Active' : 'Inactive' ?>
            </div>
            <div class="text-slate-400">Statut</div>
          </div>
        </div>
        <?php endif ?>
      </div>

      <!-- Onglets (underline, cohérent avec buses/drivers) -->
      <nav class="-mb-px flex gap-1 overflow-x-auto">
        <?php
        $tabs = [
          ['id' => 'identification',   'icon' => 'map',       'label' => 'Identification'],
          ['id' => 'caracteristiques', 'icon' => 'sliders',   'label' => 'Caractéristiques'],
          ['id' => 'arrets',           'icon' => 'map-pin',   'label' => 'Arrêts',
           'badge' => count($stops)],
          ['id' => 'tarifs',           'icon' => 'tags',      'label' => 'Tarifs',
           'badge' => count($tariffs ?? []), 'editOnly' => true],
          ['id' => 'galerie',          'icon' => 'images',    'label' => 'Galerie',    'editOnly' => true],
          ['id' => 'documents',        'icon' => 'paperclip', 'label' => 'Documents',  'editOnly' => true],
        ];
        foreach ($tabs as $t):
          $eo = $t['editOnly'] ?? false;
          $disabled = $eo && !$isEdit;
        ?>
        <button type="button"
          @click="<?= $disabled ? '' : "tab = '{$t['id']}'" ?>"
          :class="tab === '<?= $t['id'] ?>' ? 'border-cb-primary text-cb-primary' : 'border-transparent <?= $disabled ? 'text-slate-300 cursor-not-allowed' : 'text-slate-500 hover:text-slate-700' ?>'"
          class="flex items-center gap-1.5 px-3 py-3 border-b-2 text-sm font-medium whitespace-nowrap transition-colors <?= $disabled ? 'cursor-not-allowed' : '' ?>">
          <i data-lucide="<?= $t['icon'] ?>" class="w-4 h-4"></i>
          <?= e($t['label']) ?>
          <?php if (isset($t['badge']) && ($isEdit || !$eo) && $t['badge'] > 0): ?>
            <span class="text-[10px] font-bold rounded-full px-1.5 py-0.5 min-w-[18px] text-center"
                  :class="tab === '<?= $t['id'] ?>' ? 'bg-cb-bg text-cb-primary' : 'bg-slate-100 text-slate-500'">
              <?= $t['badge'] ?>
            </span>
          <?php endif ?>
          <?php if ($disabled): ?>
            <span class="text-[9px] bg-slate-100 text-slate-400 px-1 rounded">après création</span>
          <?php endif ?>
        </button>
        <?php endforeach ?>
      </nav>
    </div>
  </div>

  <!-- ── FORMULAIRE (onglets 1-3) ───────────────────────────── -->
  <form method="post" action="<?= e($action) ?>" data-dirty-watch="<?= $isEdit ? '1' : '0' ?>" novalidate>
    <?= csrf_field() ?>

    <!-- ── ONGLET 1 : Identification ────────────────────────── -->
    <div x-show="tab === 'identification'" class="space-y-4">

      <!-- Identité -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="map" class="w-4 h-4 text-cb-primary"></i>
          Identification
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-[7rem_1fr_10rem] gap-4">

          <!-- Code -->
          <div>
            <label class="cb-label">Code <span class="text-rose-500">*</span></label>
            <input name="code" required maxlength="10"
                   value="<?= e(old('code', $line['code'] ?? '')) ?>"
                   placeholder="L-BZV"
                   class="cb-input font-mono uppercase text-sm">
            <p class="text-[11px] text-slate-400 mt-1">Ex : L-BZV</p>
            <?php foreach (errors('code') as $err): ?>
              <p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p>
            <?php endforeach ?>
          </div>

          <!-- Nom -->
          <div>
            <label class="cb-label">Nom de la ligne <span class="text-rose-500">*</span></label>
            <input name="name" required maxlength="120"
                   value="<?= e(old('name', $line['name'] ?? '')) ?>"
                   placeholder="Brazzaville — Pointe-Noire"
                   class="cb-input">
            <?php foreach (errors('name') as $err): ?>
              <p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p>
            <?php endforeach ?>
          </div>

          <!-- Type de ligne -->
          <div>
            <label class="cb-label">Type <span class="text-rose-500">*</span></label>
            <select name="line_type" x-model="lineType" class="cb-input">
              <?php foreach (Line::LINE_TYPES as $val => $lbl): ?>
                <option value="<?= e($val) ?>"><?= e($lbl) ?></option>
              <?php endforeach ?>
            </select>
            <?php foreach (errors('line_type') as $err): ?>
              <p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p>
            <?php endforeach ?>
          </div>

        </div>
      </div>

      <!-- Itinéraire INTERURBAIN -->
      <div x-show="lineType === 'interurbain'" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-4">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="route" class="w-4 h-4 text-cb-primary"></i>
          Itinéraire interurbain
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

          <!-- Départ -->
          <div>
            <label class="cb-label">
              <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-400 mr-1.5 align-middle"></span>
              Ville de départ <span class="text-rose-500">*</span>
            </label>
            <select name="departure_city_id" class="cb-input">
              <option value="">— Ville de départ —</option>
              <?php foreach (($cities ?? []) as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                  <?= ((int)old('departure_city_id', $line['departure_city_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                  <?= e($c['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
            <?php foreach (errors('departure_city_id') as $err): ?>
              <p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p>
            <?php endforeach ?>
          </div>

          <!-- Arrivée -->
          <div>
            <label class="cb-label">
              <span class="inline-block w-2.5 h-2.5 rounded-full bg-rose-500 mr-1.5 align-middle"></span>
              Ville d'arrivée <span class="text-rose-500">*</span>
            </label>
            <select name="arrival_city_id" class="cb-input">
              <option value="">— Ville d'arrivée —</option>
              <?php foreach (($cities ?? []) as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                  <?= ((int)old('arrival_city_id', $line['arrival_city_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                  <?= e($c['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
            <?php foreach (errors('arrival_city_id') as $err): ?>
              <p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p>
            <?php endforeach ?>
          </div>

          <!-- Agence départ -->
          <div>
            <label class="cb-label">Agence de départ</label>
            <select name="departure_agency_id" class="cb-input">
              <option value="">— Aucune —</option>
              <?php foreach ($agencies as $a): ?>
                <option value="<?= (int)$a['id'] ?>"
                  <?= ((int)old('departure_agency_id', $line['departure_agency_id'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>>
                  <?= e($a['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
            <p class="text-[11px] text-slate-400 mt-1">Gare routière de départ</p>
          </div>

          <!-- Agence arrivée -->
          <div>
            <label class="cb-label">Agence d'arrivée</label>
            <select name="arrival_agency_id" class="cb-input">
              <option value="">— Aucune —</option>
              <?php foreach ($agencies as $a): ?>
                <option value="<?= (int)$a['id'] ?>"
                  <?= ((int)old('arrival_agency_id', $line['arrival_agency_id'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>>
                  <?= e($a['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
            <p class="text-[11px] text-slate-400 mt-1">Gare routière d'arrivée</p>
          </div>

        </div>
      </div>

      <!-- Itinéraire URBAIN -->
      <div x-show="lineType === 'urbain'" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-4">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="bus" class="w-4 h-4 text-cb-primary"></i>
          Ligne urbaine
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <!-- Ville -->
          <div>
            <label class="cb-label">
              <span class="inline-block w-2.5 h-2.5 rounded-full bg-purple-400 mr-1.5 align-middle"></span>
              Ville <span class="text-rose-500">*</span>
            </label>
            <select name="city_id" class="cb-input">
              <option value="">— Sélectionner la ville —</option>
              <?php foreach (($cities ?? []) as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                  <?= ((int)old('city_id', $line['city_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                  <?= e($c['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
            <?php foreach (errors('city_id') as $err): ?>
              <p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p>
            <?php endforeach ?>
            <p class="text-[11px] text-slate-400 mt-1">La ligne dessert des arrêts au sein de cette ville uniquement.</p>
          </div>
        </div>

        <div class="rounded-lg bg-purple-50 border border-purple-100 p-3 mt-3">
          <p class="text-xs text-purple-700 flex items-start gap-2">
            <i data-lucide="info" class="w-4 h-4 text-purple-500 shrink-0 mt-0.5"></i>
            <span>Les lignes urbaines fonctionnent avec une liste d'arrêts ordonnés (onglet Arrêts). Les passagers montent et descendent aux arrêts de la ligne.</span>
          </p>
        </div>
        <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 mt-2">
          <p class="text-xs text-amber-700 flex items-start gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"></i>
            <span><strong>Obligatoire :</strong> une ligne urbaine doit comporter au minimum 2 arrêts (départ et arrivée). Ajoutez-les dans l'onglet <em>Arrêts</em> avant d'enregistrer.</span>
          </p>
        </div>
      </div>

      <!-- Barre d'action -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft px-6 py-4 flex items-center justify-between gap-3">
        <a href="<?= e($isEdit ? url('referentiel/lines/'.$lineId) : url('referentiel/lines')) ?>"
           class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition">
          Annuler
        </a>
        <button type="submit" data-dirty-submit
                class="px-5 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i>
          <?= $isEdit ? 'Enregistrer' : 'Créer la ligne' ?>
        </button>
      </div>
    </div>

    <!-- ── ONGLET 2 : Caractéristiques ──────────────────────── -->
    <div x-show="tab === 'caracteristiques'" class="space-y-4">

      <!-- Physique -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="sliders" class="w-4 h-4 text-cb-primary"></i>
          Caractéristiques physiques
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="cb-label">Distance (km)</label>
            <input type="number" step="0.1" min="0" name="distance_km"
                   value="<?= e(old('distance_km', $line['distance_km'] ?? '')) ?>"
                   placeholder="510" class="cb-input">
            <p class="text-[11px] text-slate-400 mt-1">Distance entre les deux terminaux</p>
          </div>
          <div>
            <label class="cb-label">Durée estimée (h)</label>
            <input type="number" step="0.5" min="0" name="duration_hours"
                   value="<?= e(old('duration_hours', $line['duration_hours'] ?? '')) ?>"
                   placeholder="8" class="cb-input">
            <p class="text-[11px] text-slate-400 mt-1">Hors arrêts imprévus</p>
          </div>
        </div>
      </div>

      <?php if ($isEdit): ?>
      <!-- Statut -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2 mb-4">
          <i data-lucide="toggle-left" class="w-4 h-4 text-cb-primary"></i>
          Statut
        </h2>
        <label class="flex items-center gap-3 cursor-pointer select-none group">
          <input type="checkbox" name="is_active" value="1"
                 <?= !empty($line['is_active']) ? 'checked' : '' ?>
                 class="w-4 h-4 rounded border-slate-300 accent-cb-primary cursor-pointer">
          <div>
            <div class="text-sm font-medium text-slate-800 group-hover:text-cb-primary transition flex items-center gap-2">
              Ligne active
              <?php if (!empty($line['is_active'])): ?>
                <span class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-semibold">En service</span>
              <?php else: ?>
                <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full font-semibold">Suspendue</span>
              <?php endif ?>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">Des voyages peuvent être planifiés et des billets vendus sur cette ligne.</p>
          </div>
        </label>
      </div>
      <?php endif ?>

      <!-- Barre d'action -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft px-6 py-4 flex items-center justify-between gap-3">
        <a href="<?= e($isEdit ? url('referentiel/lines/'.$lineId) : url('referentiel/lines')) ?>"
           class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition">
          Annuler
        </a>
        <button type="submit"
                class="px-5 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i>
          <?= $isEdit ? 'Enregistrer' : 'Créer la ligne' ?>
        </button>
      </div>
    </div>

    <!-- ── ONGLET 3 : Arrêts ─────────────────────────────────── -->
    <div x-show="tab === 'arrets'" x-data="_stopsManager()">
      <input type="hidden" name="stops_json" :value="stopsJson">

      <!-- Avertissement urbain si < 2 arrêts -->
      <template x-if="lineType === 'urbain' && stops.length < 2">
        <div class="mb-3 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 flex items-start gap-2">
          <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-500 shrink-0 mt-0.5"></i>
          <div>
            <p class="text-sm font-semibold text-rose-700">Arrêts insuffisants</p>
            <p class="text-xs text-rose-600 mt-0.5">Une ligne urbaine nécessite au minimum <strong>2 arrêts</strong> (départ et arrivée). Ajoutez des arrêts ci-dessous.</p>
          </div>
        </div>
      </template>

      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">

        <!-- En-tête -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
              <i data-lucide="map-pin" class="w-4 h-4 text-cb-primary"></i>
              <span x-text="lineType === 'urbain' ? 'Arrêts de la ligne' : 'Arrêts intermédiaires'">Arrêts intermédiaires</span>
              <span class="text-xs font-semibold bg-cb-bg text-cb-primary px-2 py-0.5 rounded-full"
                    x-text="stops.length + ' arrêt(s)'"></span>
              <template x-if="lineType === 'urbain' && stops.length < 2">
                <span class="text-[10px] font-bold bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full">min. 2 requis</span>
              </template>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Triés automatiquement par distance croissante depuis le départ.</p>
          </div>
        </div>

        <!-- Terminal départ -->
        <?php if (!empty($line)): ?>
        <div class="flex items-center gap-3 px-6 py-3 bg-emerald-50 border-b border-emerald-100">
          <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0">
            <i data-lucide="circle-dot" class="w-4 h-4"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-emerald-800">
              <?= e($line['departure_city_name'] ?? '') ?>
            </div>
            <div class="text-xs text-emerald-600">Point de départ · 0 km</div>
          </div>
          <span class="text-[10px] font-bold uppercase bg-emerald-100 text-emerald-700 px-2 py-1 rounded">Départ</span>
        </div>
        <?php endif ?>

        <!-- Liste des arrêts -->
        <div class="divide-y divide-slate-50">
          <template x-for="(stop, i) in stops" :key="stop._key">
            <div class="flex items-center gap-3 px-6 py-2.5 hover:bg-slate-50 group transition">

              <!-- Numéro -->
              <div class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-bold shrink-0 border border-slate-200"
                   x-text="i + 1"></div>

              <!-- Champs -->
              <div class="flex-1 grid grid-cols-1 sm:grid-cols-[1fr_6rem_9rem] gap-2">
                <input x-model="stop.name"
                       class="cb-input py-1.5 text-sm"
                       placeholder="Nom de l'arrêt">
                <div class="relative">
                  <input x-model="stop.km_from_origin"
                         type="number" step="0.1" min="0"
                         @change="reorder()"
                         class="cb-input py-1.5 text-sm pr-7"
                         placeholder="0">
                  <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none">km</span>
                </div>
                <select x-model="stop.agency_id" class="cb-input py-1.5 text-sm">
                  <option value="">— Agence —</option>
                  <?php foreach ($agencies as $a): ?>
                    <option value="<?= e($a['id']) ?>"><?= e($a['name']) ?></option>
                  <?php endforeach ?>
                </select>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition shrink-0">
                <button type="button" @click="removeStop(i)"
                        class="p-1 rounded-lg text-slate-300 hover:text-rose-500 hover:bg-rose-50 transition">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </div>
            </div>
          </template>

          <!-- Vide -->
          <div x-show="stops.length === 0"
               class="py-10 text-center text-sm text-slate-400">
            <i data-lucide="map-pin-off" class="w-8 h-8 text-slate-200 mx-auto mb-2"></i>
            Aucun arrêt — ajoutez-en ci-dessous
          </div>
        </div>

        <!-- Terminal arrivée -->
        <?php if (!empty($line)): ?>
        <div class="flex items-center gap-3 px-6 py-3 bg-rose-50 border-t border-rose-100">
          <div class="w-8 h-8 rounded-full bg-rose-500 text-white flex items-center justify-center shrink-0">
            <i data-lucide="map-pin" class="w-4 h-4"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-rose-800">
              <?= e($line['arrival_city_name'] ?? '') ?>
            </div>
            <div class="text-xs text-rose-500">Terminus d'arrivée</div>
          </div>
          <span class="text-[10px] font-bold uppercase bg-rose-100 text-rose-700 px-2 py-1 rounded">Arrivée</span>
        </div>
        <?php endif ?>

        <!-- Formulaire ajout -->
        <div class="border-t border-slate-100 bg-slate-50 px-6 py-4">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
            Ajouter un arrêt
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-[1fr_6rem_9rem_auto] gap-3 items-end">
            <div>
              <label class="cb-label">Nom <span class="text-rose-500">*</span></label>
              <input x-model="newStop.name"
                     @keydown.enter.prevent="addStop()"
                     class="cb-input"
                     placeholder="Ex : Dolisie">
            </div>
            <div>
              <label class="cb-label">Distance</label>
              <div class="relative">
                <input x-model="newStop.km_from_origin"
                       type="number" step="0.1" min="0"
                       class="cb-input pr-7"
                       placeholder="0">
                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none">km</span>
              </div>
            </div>
            <div>
              <label class="cb-label">Agence</label>
              <select x-model="newStop.agency_id" class="cb-input">
                <option value="">— Aucune —</option>
                <?php foreach ($agencies as $a): ?>
                  <option value="<?= e($a['id']) ?>"><?= e($a['name']) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <button type="button" @click="addStop()"
                    class="px-4 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-1.5 shrink-0">
              <i data-lucide="plus" class="w-4 h-4"></i> Ajouter
            </button>
          </div>
        </div>
      </div>

      <!-- Barre d'action -->
      <div class="mt-4 bg-white rounded-2xl border border-slate-100 shadow-soft px-6 py-4 flex items-center justify-between gap-3">
        <a href="<?= e($isEdit ? url('referentiel/lines/'.$lineId) : url('referentiel/lines')) ?>"
           class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition">
          Annuler
        </a>
        <button type="submit"
                class="px-5 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i>
          <?= $isEdit ? 'Enregistrer' : 'Créer la ligne' ?>
        </button>
      </div>
    </div>

  </form>

  <!-- ── ONGLET 4 : Tarifs ──────────────────────────────────── -->
  <div x-show="tab === 'tarifs'">
    <?php if ($isEdit): ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="tags" class="w-4 h-4 text-cb-primary"></i>
            Grille tarifaire
            <span class="text-xs text-slate-400 font-normal"><?= count($tariffs ?? []) ?> tarif(s)</span>
          </h2>
          <a href="<?= e(url('referentiel/tariffs/create')) ?>?line_id=<?= (int)$lineId ?>"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cb-primary text-white text-xs font-semibold hover:bg-cb-dark transition">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Nouveau tarif
          </a>
        </div>

        <?php if (empty($tariffs)): ?>
          <div class="py-14 text-center">
            <i data-lucide="tag" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
            <p class="text-sm text-slate-500 font-medium">Aucun tarif configuré</p>
            <p class="text-xs text-slate-400 mt-1 mb-4">Définissez les prix par type de billet</p>
            <a href="<?= e(url('referentiel/tariffs/create')) ?>?line_id=<?= (int)$lineId ?>"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-cb-bg text-cb-primary text-sm font-semibold hover:bg-cb-primary hover:text-white transition">
              <i data-lucide="plus" class="w-4 h-4"></i> Créer le premier tarif
            </a>
          </div>
        <?php else: ?>
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                <th class="px-5 py-3 text-left">Type</th>
                <th class="px-5 py-3 text-right">Prix</th>
                <th class="px-5 py-3 text-center">Statut</th>
                <th class="px-5 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php foreach ($tariffs as $t):
                $slug = $t['ticket_type'] ?? '';
                $cls  = Tariff::typeColors()[$slug] ?? 'bg-slate-50 text-slate-600 border-slate-200';
                $ico  = Tariff::typeIcons()[$slug]  ?? 'tag';
              ?>
              <tr class="hover:bg-slate-50/60 transition group">
                <td class="px-5 py-3">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-semibold <?= $cls ?>">
                    <i data-lucide="<?= e($ico) ?>" class="w-3 h-3"></i>
                    <?= e(Tariff::types()[$slug] ?? $slug) ?>
                  </span>
                </td>
                <td class="px-5 py-3 text-right font-bold text-cb-primary"><?= e(fcfa((int)$t['price_fcfa'])) ?></td>
                <td class="px-5 py-3 text-center">
                  <?php if ((int)$t['is_active']): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-100">
                      <i data-lucide="check" class="w-3 h-3"></i> Actif
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-50 text-slate-400 text-xs font-semibold border border-slate-100">
                      <i data-lucide="x" class="w-3 h-3"></i> Inactif
                    </span>
                  <?php endif ?>
                </td>
                <td class="px-5 py-3 text-right">
                  <a href="<?= e(url('referentiel/tariffs/'.$t['id'].'/edit')) ?>"
                     class="text-xs text-slate-400 hover:text-cb-primary transition opacity-0 group-hover:opacity-100 inline-flex items-center gap-1">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Modifier
                  </a>
                </td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        <?php endif ?>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-2xl border border-dashed border-slate-200 py-14 text-center">
        <i data-lucide="tags" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
        <p class="text-sm font-medium text-slate-500">Disponible après création de la ligne</p>
      </div>
    <?php endif ?>
  </div>

  <!-- ── ONGLET 5 : Galerie ──────────────────────────────────── -->
  <div x-show="tab === 'galerie'">
    <?php if ($isEdit): ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
        <?php
        $mediableType = 'lines';
        $mediableId   = $lineId;
        $galleryItems = $gallery;
        include BASE_PATH . '/views/components/media-gallery.php';
        ?>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-2xl border border-dashed border-slate-200 py-14 text-center">
        <i data-lucide="images" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
        <p class="text-sm font-medium text-slate-500">Disponible après création de la ligne</p>
      </div>
    <?php endif ?>
  </div>

  <!-- ── ONGLET 6 : Documents ────────────────────────────────── -->
  <div x-show="tab === 'documents'">
    <?php if ($isEdit): ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
        <?php
        $mediableType = 'lines';
        $mediableId   = $lineId;
        $docItems     = $docs;
        include BASE_PATH . '/views/components/media-dropzone.php';
        ?>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-2xl border border-dashed border-slate-200 py-14 text-center">
        <i data-lucide="paperclip" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
        <p class="text-sm font-medium text-slate-500">Disponible après création de la ligne</p>
      </div>
    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
