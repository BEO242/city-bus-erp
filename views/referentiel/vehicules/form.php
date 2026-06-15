<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Bus;
$view->extends('layouts/app');
$isEdit  = !empty($bus);
$busId   = $bus['id'] ?? 0;
$action  = $isEdit ? url('referentiel/vehicules/'.$busId) : url('referentiel/vehicules');
$gallery = $gallery ?? [];
$docs    = $docs ?? [];
?>
<?php $view->start('content') ?>
<div class="space-y-6" x-data="{ tab: 'identite' }">

  <!-- En-tête -->
  <div class="flex items-center gap-4">
    <a href="<?= e(url('referentiel/vehicules' . ($isEdit ? '/'.$busId : ''))) ?>"
       class="text-slate-500 hover:text-cb-primary p-2 rounded-lg hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>
      <?php if ($isEdit): ?>
        <p class="text-sm text-slate-500"><?= e($bus['code']) ?> &middot; <?= e($bus['plate']) ?></p>
      <?php endif ?>
    </div>
  </div>

  <!-- Onglets -->
  <div class="border-b border-slate-200">
    <nav class="-mb-px flex gap-1 overflow-x-auto">
      <?php
      $tabs = [
        ['id' => 'identite',   'icon' => 'info',         'label' => 'Identite'],
        ['id' => 'specs',      'icon' => 'ruler',        'label' => 'Specifications'],
        ['id' => 'affectation','icon' => 'map-pin',       'label' => 'Affectation'],
        ['id' => 'conformite', 'icon' => 'shield-check',  'label' => 'Conformite'],
        ['id' => 'equipements','icon' => 'settings-2',    'label' => 'Equipements'],
        ['id' => 'galerie',    'icon' => 'images',        'label' => 'Galerie',    'editOnly' => true],
        ['id' => 'documents',  'icon' => 'paperclip',     'label' => 'Documents',  'editOnly' => true],
      ];
      foreach ($tabs as $t):
        $eo = $t['editOnly'] ?? false;
      ?>
      <button type="button"
        @click="tab = '<?= $t['id'] ?>'"
        :class="tab === '<?= $t['id'] ?>' ? 'border-cb-primary text-cb-primary' : 'border-transparent text-slate-500 hover:text-slate-700'"
        class="flex items-center gap-1.5 px-4 py-3 border-b-2 font-medium text-sm whitespace-nowrap transition-colors">
        <i data-lucide="<?= $t['icon'] ?>" class="w-4 h-4"></i>
        <?= $t['label'] ?>
        <?php if ($eo && !$isEdit): ?><span class="text-[10px] bg-slate-100 text-slate-400 px-1 rounded ml-1">apres creation</span><?php endif ?>
      </button>
      <?php endforeach ?>
    </nav>
  </div>

  <?php $dateVal = fn($v) => ($v && $v !== '0000-00-00') ? $v : ''; ?>
  <!-- Formulaire principal (onglets 1-4) -->
  <form method="post" action="<?= e($action) ?>" data-dirty-watch="<?= $isEdit ? '1' : '0' ?>" novalidate class="space-y-6">
    <?= csrf_field() ?>

    <!-- ONGLET 1 : Identite -->
    <div x-show="tab === 'identite'" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="info" class="w-4 h-4 text-cb-primary"></i> Identification du vehicule
      </h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="cb-label">Code interne <span class="text-rose-500">*</span></label>
          <input name="code" required maxlength="20" placeholder="CB-001"
                 value="<?= e(old('code',$bus['code']??'')) ?>" class="cb-input">
          <?php foreach (errors('code') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
        </div>
        <div>
          <label class="cb-label">Immatriculation <span class="text-rose-500">*</span></label>
          <input name="plate" required maxlength="20" placeholder="CG-1234-A"
                 value="<?= e(old('plate',$bus['plate']??'')) ?>" class="cb-input">
          <?php foreach (errors('plate') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
        </div>
        <div>
          <label class="cb-label">Couleur</label>
          <input name="color" list="cb-colors" maxlength="30" placeholder="Blanc"
                 value="<?= e(old('color',$bus['color']??'')) ?>" class="cb-input">
          <datalist id="cb-colors">
            <?php foreach (\CityBus\Models\Bus::COLORS as $c): ?><option value="<?= e($c) ?>"><?php endforeach ?>
          </datalist>
        </div>
        <div>
          <label class="cb-label">Marque</label>
          <input name="brand" maxlength="50" placeholder="Toyota"
                 value="<?= e(old('brand',$bus['brand']??'')) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">Modele</label>
          <input name="model" maxlength="50" placeholder="Coaster"
                 value="<?= e(old('model',$bus['model']??'')) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">Annee</label>
          <input type="number" name="year" min="1990" max="<?= date('Y')+1 ?>"
                 value="<?= e(old('year',$bus['year']??'')) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">Carburant</label>
          <select name="fuel_type" class="cb-input">
            <option value="">-- Selectionner --</option>
            <?php foreach (\CityBus\Models\Bus::FUEL_TYPES as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= old('fuel_type',$bus['fuel_type']??'')===$k?'selected':'' ?>><?= e($v) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="cb-label">Transmission</label>
          <select name="transmission" class="cb-input">
            <option value="">-- Selectionner --</option>
            <?php foreach (\CityBus\Models\Bus::TRANSMISSIONS as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= old('transmission',$bus['transmission']??'')===$k?'selected':'' ?>><?= e($v) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="cb-label">Nb places <span class="text-rose-500">*</span></label>
          <input type="number" name="seats" required min="1" max="100"
                 value="<?= e(old('seats',$bus['seats']??47)) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">N&deg; Chassis (VIN)</label>
          <input name="vin" maxlength="50" placeholder="1HGBH41JXMN109186"
                 value="<?= e(old('vin',$bus['vin']??'')) ?>" class="cb-input font-mono text-sm">
        </div>
        <div>
          <label class="cb-label">N&deg; Moteur</label>
          <input name="engine_number" maxlength="50"
                 value="<?= e(old('engine_number',$bus['engine_number']??'')) ?>" class="cb-input font-mono text-sm">
        </div>
        <div>
          <label class="cb-label">Type de véhicule</label>
          <select name="vehicle_type_id" class="cb-input">
            <option value="">-- Sélectionner --</option>
            <?php foreach ($vehicleTypes ?? [] as $vt): ?>
            <option value="<?= e($vt['id']) ?>" <?= (int)old('vehicle_type_id',$bus['vehicle_type_id']??0)===(int)$vt['id']?'selected':'' ?>><?= e($vt['label']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
    </div>

    <!-- ONGLET SPECS : Specifications techniques -->
    <div x-show="tab === 'specs'" class="space-y-6">
      <!-- Dimensions & poids -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-4">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="ruler" class="w-4 h-4 text-cb-primary"></i> Dimensions &amp; capacit&eacute;s
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
          <div>
            <label class="cb-label">Longueur (m)</label>
            <input type="number" step="0.01" name="length_m" min="0"
                   value="<?= e(old('length_m',$bus['length_m']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Largeur (m)</label>
            <input type="number" step="0.01" name="width_m" min="0"
                   value="<?= e(old('width_m',$bus['width_m']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Hauteur (m)</label>
            <input type="number" step="0.01" name="height_m" min="0"
                   value="<?= e(old('height_m',$bus['height_m']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Nb essieux</label>
            <input type="number" name="axles_count" min="2" max="6"
                   value="<?= e(old('axles_count',$bus['axles_count']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Poids &agrave; vide (kg)</label>
            <input type="number" name="weight_empty_kg" min="0"
                   value="<?= e(old('weight_empty_kg',$bus['weight_empty_kg']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">PTAC (kg)</label>
            <input type="number" name="weight_max_kg" min="0"
                   value="<?= e(old('weight_max_kg',$bus['weight_max_kg']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Capacit&eacute; bagages (kg)</label>
            <input type="number" name="cargo_capacity_kg" min="0"
                   value="<?= e(old('cargo_capacity_kg',$bus['cargo_capacity_kg']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">R&eacute;servoir (L)</label>
            <input type="number" name="fuel_tank_l" min="0"
                   value="<?= e(old('fuel_tank_l',$bus['fuel_tank_l']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Conso. moy. (L/100km)</label>
            <input type="number" step="0.1" name="consumption_avg_l" min="0"
                   value="<?= e(old('consumption_avg_l',$bus['consumption_avg_l']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Nb airbags</label>
            <input type="number" name="airbags_count" min="0" max="20"
                   value="<?= e(old('airbags_count',$bus['airbags_count']??'')) ?>" class="cb-input">
          </div>
        </div>
      </div>

      <!-- Sécurité -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-3">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="shield" class="w-4 h-4 text-cb-primary"></i> Syst&egrave;mes de s&eacute;curit&eacute;
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <?php
          $safetys = [
            ['key'=>'abs_brakes',    'label'=>'Freins ABS',         'desc'=>'Anti-blocage des roues'],
            ['key'=>'esp_system',    'label'=>'Stabilit&eacute; ESP', 'desc'=>'Contr&ocirc;le &eacute;lectronique'],
            ['key'=>'retarder',      'label'=>'Ralentisseur',        'desc'=>'Frein moteur descente'],
            ['key'=>'seatbelts_all', 'label'=>'Ceintures partout',   'desc'=>'Sur tous les si&egrave;ges'],
            ['key'=>'tachograph',    'label'=>'Tachygraphe',         'desc'=>'Enregistreur de vitesse'],
          ];
          foreach ($safetys as $s):
            $on = (bool)(int)($bus[$s['key']]??0);
          ?>
          <label class="flex items-start gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition has-[:checked]:border-cb-primary has-[:checked]:bg-blue-50">
            <input type="checkbox" name="<?= $s['key'] ?>" value="1" <?= $on?'checked':'' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
            <div>
              <div class="font-medium text-sm"><?= $s['label'] ?></div>
              <p class="text-xs text-slate-400 mt-0.5"><?= $s['desc'] ?></p>
            </div>
          </label>
          <?php endforeach ?>
        </div>
      </div>

      <!-- GPS / IoT -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-4">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="navigation" class="w-4 h-4 text-cb-primary"></i> G&eacute;olocalisation / IoT
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">Op&eacute;rateur GPS</label>
            <input name="gps_provider" maxlength="60" placeholder="Tracking Congo"
                   value="<?= e(old('gps_provider',$bus['gps_provider']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Identifiant boitier</label>
            <input name="gps_device_id" maxlength="60"
                   value="<?= e(old('gps_device_id',$bus['gps_device_id']??'')) ?>" class="cb-input font-mono text-sm">
          </div>
          <div>
            <label class="cb-label">Num&eacute;ro SIM</label>
            <input name="gps_sim_number" maxlength="30"
                   value="<?= e(old('gps_sim_number',$bus['gps_sim_number']??'')) ?>" class="cb-input">
          </div>
        </div>
      </div>
    </div>

    <!-- ONGLET 2 : Affectation -->
    <div x-show="tab === 'affectation'" class="space-y-6">
      <!-- Affectation operationnelle -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="map-pin" class="w-4 h-4 text-cb-primary"></i> Affectation op&eacute;rationnelle
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">Agence</label>
            <select name="agency_id" class="cb-input">
              <option value="">-- Aucune --</option>
              <?php foreach ($agencies as $a): ?>
              <option value="<?= e($a['id']) ?>" <?= (int)old('agency_id',$bus['agency_id']??0)===(int)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label class="cb-label">Chauffeur principal</label>
            <select name="primary_driver_id" class="cb-input">
              <option value="">-- Non affect&eacute; --</option>
              <?php foreach (($drivers ?? []) as $d): ?>
              <option value="<?= e($d['id']) ?>" <?= (int)old('primary_driver_id',$bus['primary_driver_id']??0)===(int)$d['id']?'selected':'' ?>>
                <?= e($d['matricule']) ?> &mdash; <?= e($d['last_name']) ?> <?= e($d['first_name']) ?>
              </option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label class="cb-label">Statut <span class="text-rose-500">*</span></label>
            <select name="status" required class="cb-input">
              <?php foreach (\CityBus\Models\Bus::STATUSES as $k=>$v): ?>
              <option value="<?= e($k) ?>" <?= old('status',$bus['status']??'disponible')===$k?'selected':'' ?>><?= e($v) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label class="cb-label">Kilometrage actuel (km)</label>
            <input type="number" name="km_current" min="0"
                   value="<?= e(old('km_current',$bus['km_current']??0)) ?>" class="cb-input">
          </div>
        </div>
      </div>

      <!-- Acquisition / Finance -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="banknote" class="w-4 h-4 text-cb-primary"></i> Acquisition &amp; financement
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">Date d'achat</label>
            <input type="date" name="purchase_date"
                   value="<?= e($dateVal(old('purchase_date',$bus['purchase_date']??''))) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Prix d'achat (FCFA)</label>
            <input type="number" name="purchase_price_fcfa" min="0"
                   value="<?= e(old('purchase_price_fcfa',$bus['purchase_price_fcfa']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Mode d'acquisition</label>
            <select name="financing_type" class="cb-input">
              <option value="">-- Selectionner --</option>
              <?php foreach (\CityBus\Models\Bus::FINANCING_TYPES as $k=>$v): ?>
              <option value="<?= e($k) ?>" <?= old('financing_type',$bus['financing_type']??'')===$k?'selected':'' ?>><?= e($v) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label class="cb-label">Fournisseur</label>
            <input name="supplier" maxlength="100"
                   value="<?= e(old('supplier',$bus['supplier']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Kilometrage a l'achat</label>
            <input type="number" name="mileage_at_purchase" min="0"
                   value="<?= e(old('mileage_at_purchase',$bus['mileage_at_purchase']??0)) ?>" class="cb-input">
          </div>
        </div>
      </div>

      <!-- Immatriculation / Carte grise -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="file-text" class="w-4 h-4 text-cb-primary"></i> Carte grise
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="cb-label">N&deg; carte grise</label>
            <input name="registration_card_number" maxlength="50"
                   value="<?= e(old('registration_card_number',$bus['registration_card_number']??'')) ?>" class="cb-input font-mono">
          </div>
          <div>
            <label class="cb-label">Date d'&eacute;mission</label>
            <input type="date" name="registration_card_date"
                   value="<?= e($dateVal(old('registration_card_date',$bus['registration_card_date']??''))) ?>" class="cb-input">
          </div>
        </div>
      </div>
    </div>

    <!-- ONGLET 3 : Conformite -->
    <div x-show="tab === 'conformite'" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="shield-check" class="w-4 h-4 text-cb-primary"></i> Conformite administrative
      </h2>
      <!-- Assurance -->
      <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
        <legend class="text-sm font-semibold text-slate-600 px-2">Assurance</legend>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">Expiration</label>
            <input type="date" name="insurance_expiry"
                   value="<?= e($dateVal(old('insurance_expiry',$bus['insurance_expiry']??''))) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Compagnie</label>
            <input name="insurance_company" maxlength="100" placeholder="Assur-Congo"
                   value="<?= e(old('insurance_company',$bus['insurance_company']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">N&deg; police</label>
            <input name="insurance_policy" maxlength="100"
                   value="<?= e(old('insurance_policy',$bus['insurance_policy']??'')) ?>" class="cb-input font-mono text-sm">
          </div>
        </div>
      </fieldset>
      <!-- CT -->
      <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
        <legend class="text-sm font-semibold text-slate-600 px-2">Controle technique</legend>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="cb-label">Expiration</label>
            <input type="date" name="tech_control_expiry"
                   value="<?= e($dateVal(old('tech_control_expiry',$bus['tech_control_expiry']??''))) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Centre de controle</label>
            <input name="tech_control_center" maxlength="100" placeholder="CFVT Brazzaville"
                   value="<?= e(old('tech_control_center',$bus['tech_control_center']??'')) ?>" class="cb-input">
          </div>
        </div>
      </fieldset>
      <!-- Prochaine maintenance -->
      <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
        <legend class="text-sm font-semibold text-slate-600 px-2">Prochaine maintenance</legend>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="cb-label">Date prevue</label>
            <input type="date" name="next_maintenance_at"
                   value="<?= e($dateVal(old('next_maintenance_at',$bus['next_maintenance_at']??''))) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Km declencheur</label>
            <input type="number" name="next_maintenance_km" min="0" placeholder="100000"
                   value="<?= e(old('next_maintenance_km',$bus['next_maintenance_km']??'')) ?>" class="cb-input">
          </div>
        </div>
      </fieldset>
    </div>

    <!-- ONGLET 4 : Equipements -->
    <?php
    $extraRaw  = old('equipment_extra', $bus['equipment_extra'] ?? '[]');
    $extraList = json_decode(is_string($extraRaw) ? $extraRaw : '[]', true) ?: [];
    $extraJson = json_encode(array_values($extraList), JSON_UNESCAPED_UNICODE);
    $suggestions = ['Caméras de surveillance','Extincteur','Trousse de secours','Porte-bagages','Sièges premium',
      'Système audio','Prises USB','Vitres teintées','Ceintures 3 points','Affichage LED','Rampe handicapés','Porte automatique'];
    $suggestionsJson = json_encode($suggestions, JSON_UNESCAPED_UNICODE);
    ?>
    <script>
    (function() {
      var _extra = <?= $extraJson ?>;
      var _sugg  = <?= $suggestionsJson ?>;
      window._equipementsData = function() {
        return {
          extraItems: _extra.slice(),
          newItem: '',
          suggestions: _sugg,
          addItem() {
            var v = this.newItem.trim();
            if (v && !this.extraItems.includes(v) && this.extraItems.length < 30) {
              this.extraItems.push(v);
            }
            this.newItem = '';
          },
          removeItem(i) { this.extraItems.splice(i, 1); },
          addSuggestion(s) { if (!this.extraItems.includes(s)) { this.extraItems.push(s); } }
        };
      };
    })();
    </script>
    <div x-show="tab === 'equipements'" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5"
         x-data="_equipementsData()">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="settings-2" class="w-4 h-4 text-cb-primary"></i> Equipements &amp; observations
      </h2>

      <!-- Équipements fixes -->
      <div>
        <p class="cb-label mb-2">Équipements standards</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <?php
          $equips = [
            ['key'=>'ac',         'icon'=>'wind',       'label'=>'Climatisation',  'desc'=>'Systeme de climatisation'],
            ['key'=>'gps_tracker','icon'=>'navigation', 'label'=>'Traqueur GPS',   'desc'=>'Localisation temps reel'],
            ['key'=>'wifi',       'icon'=>'wifi',        'label'=>'Wi-Fi a bord',  'desc'=>'Connexion passagers'],
          ];
          foreach ($equips as $eq):
            $checked = (bool)(int)($bus[$eq['key']]??0);
          ?>
          <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition has-[:checked]:border-cb-primary has-[:checked]:bg-blue-50">
            <input type="checkbox" name="<?= $eq['key'] ?>" value="1" <?= $checked?'checked':'' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
            <div>
              <div class="flex items-center gap-1.5">
                <i data-lucide="<?= $eq['icon'] ?>" class="w-4 h-4 text-cb-primary"></i>
                <span class="font-medium text-sm"><?= $eq['label'] ?></span>
              </div>
              <p class="text-xs text-slate-400 mt-0.5"><?= $eq['desc'] ?></p>
            </div>
          </label>
          <?php endforeach ?>
        </div>
      </div>

      <!-- Équipements supplémentaires (tags) -->
      <div class="space-y-3">
        <p class="cb-label">Équipements supplémentaires</p>

        <!-- Tags ajoutés -->
        <div class="flex flex-wrap gap-2 min-h-[36px]">
          <template x-for="(item, i) in extraItems" :key="i">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-cb-bg text-cb-primary rounded-full text-sm font-medium">
              <span x-text="item"></span>
              <button type="button" @click="removeItem(i)"
                      class="w-4 h-4 rounded-full hover:bg-cb-primary hover:text-white transition flex items-center justify-center text-xs leading-none">&times;</button>
            </span>
          </template>
          <span x-show="extraItems.length === 0" class="text-sm text-slate-400 italic">Aucun équipement supplémentaire</span>
        </div>

        <!-- Saisie libre -->
        <div class="flex gap-2">
          <input type="text" x-model="newItem"
                 @keydown.enter.prevent="addItem()"
                 placeholder="Ex : Caméras, Prises USB…"
                 class="cb-input flex-1">
          <button type="button" @click="addItem()"
                  class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-1.5">
            <i data-lucide="plus" class="w-4 h-4"></i> Ajouter
          </button>
        </div>

        <!-- Suggestions rapides -->
        <div class="space-y-1.5">
          <p class="text-xs text-slate-400">Suggestions :</p>
          <div class="flex flex-wrap gap-2">
            <template x-for="s in suggestions" :key="s">
              <button type="button" @click="addSuggestion(s)"
                      :class="extraItems.includes(s) ? 'bg-cb-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-cb-bg hover:text-cb-primary'"
                      class="px-2.5 py-1 rounded-full text-xs font-medium transition">
                <span x-text="s"></span>
              </button>
            </template>
          </div>
        </div>

        <!-- Champ caché JSON -->
        <input type="hidden" name="equipment_extra" :value="JSON.stringify(extraItems)">
      </div>

      <!-- Notes -->
      <div>
        <label class="cb-label">Observations / Notes</label>
        <textarea name="notes" rows="4" placeholder="Informations complementaires, historique…"
                  class="cb-input resize-none"><?= e(old('notes',$bus['notes']??'')) ?></textarea>
      </div>
    </div>

    <!-- Barre d'action (onglets 1-4) -->
    <div x-show="!['galerie','documents'].includes(tab)"
         class="flex justify-between items-center bg-white rounded-2xl border border-slate-100 shadow-soft px-6 py-4">
      <a href="<?= e(url($isEdit?'referentiel/vehicules/'.$busId:'referentiel/vehicules')) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition">Annuler</a>
      <div class="flex items-center gap-3">
        <?php if ($isEdit): ?>
        <a href="<?= e(url('referentiel/vehicules/'.$busId)) ?>"
           class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition flex items-center gap-1.5">
          <i data-lucide="eye" class="w-4 h-4"></i> Voir la fiche
        </a>
        <?php endif ?>
        <button type="submit" data-dirty-submit
                class="px-6 py-2.5 rounded-xl bg-cb-primary text-white font-semibold text-sm hover:bg-cb-dark transition flex items-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i>
          <?= $isEdit?'Enregistrer les modifications':'Créer le véhicule' ?>
        </button>
      </div>
    </div>
  </form>

  <!-- ONGLET 5 : Galerie (AJAX) -->
  <div x-show="tab === 'galerie'">
    <?php if ($isEdit): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
      <?php
      $mediableType = 'buses'; $mediableId = $busId;
      $galleryItems = \CityBus\Services\MediaService::enrichAll($gallery);
      include BASE_PATH . '/views/components/media-gallery.php';
      ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 flex flex-col items-center gap-3 py-12 text-center">
      <i data-lucide="images" class="w-10 h-10 text-slate-300"></i>
      <p class="text-slate-500">Enregistrez d'abord le véhicule pour ajouter des photos.</p>
    </div>
    <?php endif ?>
  </div>

  <!-- ONGLET 6 : Documents (AJAX) -->
  <div x-show="tab === 'documents'">
    <?php if ($isEdit): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
      <?php
      $mediableType = 'buses'; $mediableId = $busId;
      $docItems = \CityBus\Services\MediaService::enrichAll($docs);
      include BASE_PATH . '/views/components/media-dropzone.php';
      ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 flex flex-col items-center gap-3 py-12 text-center">
      <i data-lucide="paperclip" class="w-10 h-10 text-slate-300"></i>
      <p class="text-slate-500">Enregistrez d'abord le véhicule pour joindre des documents.</p>
    </div>
    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
