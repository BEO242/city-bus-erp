<?php
/**
 * Widget de dépenses bidirectionnel — réutilisable dans trip/bus/driver show pages.
 *
 * Variables attendues :
 *   $expEntityType  string   'trip' | 'bus' | 'driver'
 *   $expEntityId    int      ID de l'entité
 *   $expenses       array    Transactions liées (depuis TreasuryExpenseService)
 *   $expCategories  array    Catégories disponibles pour ce type d'entité
 *   $expTotals      array    Totaux par catégorie (optionnel)
 *   $expContext     array    Contexte additionnel : bus_id, driver_id, trip_id pré-remplis (optionnel)
 *
 * Variables optionnelles :
 *   $expMechanics   array    Liste des mécaniciens [{id,name}] pour le formulaire entretien
 *   $expBusKm       int      Km actuel du bus pour pré-remplir le champ km_at_fill
 *   $expExtraItems  array    Items extra (fuel_logs, maintenance_orders, etc.) pré-formatés
 *   $expDriverSalary int     Salaire de base du chauffeur (pré-remplir payroll_base)
 *   $expDriverBonus  int     Prime journalière du chauffeur
 */

$expEntityType  = $expEntityType  ?? 'bus';
$expEntityId    = $expEntityId    ?? 0;
$expenses       = $expenses       ?? [];
$expCategories  = $expCategories  ?? [];
$expTotals      = $expTotals      ?? [];
$expContext     = $expContext      ?? [];
$expMechanics   = $expMechanics   ?? [];
$expBusKm       = $expBusKm       ?? 0;
$expExtraItems  = $expExtraItems  ?? [];
$expDriverSalary= $expDriverSalary?? 0;
$expDriverBonus = $expDriverBonus ?? 0;

$canManage = can('finance.treasury.manage');

// Total encaissements et décaissements (treasury_transactions uniquement)
$totalDec = 0; $totalEnc = 0;
foreach ($expenses as $ex) {
    if ($ex['type'] === 'decaissement') $totalDec += (int)$ex['amount_fcfa'];
    else $totalEnc += (int)$ex['amount_fcfa'];
}
$soldeNet = $totalEnc - $totalDec;
$soldePos = $soldeNet >= 0;

// Catégories qui ont une table dédiée : on affiche UNIQUEMENT l'enregistrement dédié
// (plus riche en données), pas le treasury_transaction en doublon.
$dedicatedCats = [
    'carburant', 'entretien', 'pneumatique', 'assurance', 'visite_technique',
    'amende', 'lavage_bus', 'peage', 'parking', 'salaire', 'salaire_avance',
    'prime_journaliere', 'prime_autre', 'indemnite', 'commission_agent',
];

// Fusionner les items pour l'accordéon
$allItems = [];
foreach ($expenses as $ex) {
    // Ignorer les treasury_transactions pour les catégories qui ont une table dédiée
    if (!in_array($ex['cat_code'] ?? '', $dedicatedCats, true)) {
        $ex['_source'] = 'treasury';
        $allItems[] = $ex;
    }
}
foreach ($expExtraItems as $ex) {
    $allItems[] = $ex;
}

// Grouper par cat_code pour les accordéons
$grouped = [];
foreach ($allItems as $item) {
    $code = $item['cat_code'] ?? 'unknown';
    if (!isset($grouped[$code])) {
        $grouped[$code] = [
            'code'  => $code,
            'label' => $item['cat_label'] ?? $code,
            'color' => $item['cat_color'] ?? 'slate',
            'items' => [],
            'total' => 0,
            'count' => 0,
        ];
    }
    $grouped[$code]['items'][] = $item;
    $grouped[$code]['total'] += (int)($item['amount_fcfa'] ?? $item['total_cost'] ?? $item['cost_fcfa'] ?? 0);
    $grouped[$code]['count']++;
}

// Tri par sort_order des catégories connues
$catOrder = array_column($expCategories, 'code');
$sortedGroups = [];
foreach ($catOrder as $code) {
    if (isset($grouped[$code])) $sortedGroups[$code] = $grouped[$code];
}
foreach ($grouped as $code => $g) {
    if (!isset($sortedGroups[$code])) $sortedGroups[$code] = $g;
}

$colorMap = [
    'slate'=>'bg-slate-100 text-slate-700','red'=>'bg-rose-100 text-rose-700',
    'orange'=>'bg-orange-100 text-orange-700','amber'=>'bg-amber-100 text-amber-700',
    'green'=>'bg-emerald-100 text-emerald-700','blue'=>'bg-blue-100 text-blue-700',
    'violet'=>'bg-violet-100 text-violet-700','pink'=>'bg-pink-100 text-pink-700',
];
$catIcons = [
    'carburant'=>'fuel','entretien'=>'wrench','lavage_bus'=>'droplets','parking'=>'parking-circle',
    'peage'=>'milestone','pneumatique'=>'circle-dot','assurance'=>'shield-check',
    'visite_technique'=>'clipboard-check','amende'=>'alert-triangle',
    'autre_recette'=>'plus-circle','autre_depense'=>'minus-circle',
    'prime_journaliere'=>'coins','prime_autre'=>'gift','salaire'=>'banknote',
    'salaire_avance'=>'banknote','commission_agent'=>'percent','indemnite'=>'wallet',
];
?>

<div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-4"
     x-data="expenseWidget()"
     x-init="$nextTick(() => window.lucide && lucide.createIcons())">

  <!-- En-tête -->
  <div class="flex items-center justify-between">
    <h3 class="font-semibold text-slate-700 flex items-center gap-2">
      <i data-lucide="receipt" class="w-4 h-4 text-cb-primary"></i>
      Dépenses & recettes
      <span class="text-xs bg-cb-bg text-cb-primary px-2 py-0.5 rounded-full ml-1 tabular-nums"><?= count($allItems) ?></span>
    </h3>
    <?php if ($canManage): ?>
    <button @click="showForm = !showForm; $nextTick(() => window.lucide && lucide.createIcons())"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition"
            :class="showForm ? 'bg-slate-200 text-slate-700' : 'bg-cb-primary text-white hover:bg-cb-dark'">
      <i data-lucide="plus" class="w-3.5 h-3.5" x-show="!showForm"></i>
      <i data-lucide="x" class="w-3.5 h-3.5" x-show="showForm" x-cloak></i>
      <span x-text="showForm ? 'Annuler' : 'Enregistrer'"></span>
    </button>
    <?php endif ?>
  </div>

  <!-- KPI mini -->
  <div class="grid grid-cols-3 gap-3">
    <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100">
      <div class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wider mb-1 flex items-center gap-1">
        <i data-lucide="arrow-down-left" class="w-3 h-3"></i> Encaissements
      </div>
      <div class="text-base font-black text-emerald-700 tabular-nums leading-tight">
        <?= number_format($totalEnc, 0, ',', ' ') ?>
        <span class="text-[10px] font-normal text-emerald-500">FCFA</span>
      </div>
    </div>
    <div class="p-3 rounded-xl bg-rose-50 border border-rose-100">
      <div class="text-[10px] text-rose-600 font-semibold uppercase tracking-wider mb-1 flex items-center gap-1">
        <i data-lucide="arrow-up-right" class="w-3 h-3"></i> Décaissements
      </div>
      <div class="text-base font-black text-rose-700 tabular-nums leading-tight">
        <?= number_format($totalDec, 0, ',', ' ') ?>
        <span class="text-[10px] font-normal text-rose-400">FCFA</span>
      </div>
    </div>
    <div class="p-3 rounded-xl <?= $soldePos ? 'bg-blue-50 border border-blue-100' : 'bg-orange-50 border border-orange-100' ?>">
      <div class="text-[10px] <?= $soldePos ? 'text-blue-600' : 'text-orange-600' ?> font-semibold uppercase tracking-wider mb-1 flex items-center gap-1">
        <i data-lucide="scale" class="w-3 h-3"></i> Solde net
      </div>
      <div class="text-base font-black <?= $soldePos ? 'text-blue-700' : 'text-orange-700' ?> tabular-nums leading-tight">
        <?= ($soldePos ? '+' : '') . number_format($soldeNet, 0, ',', ' ') ?>
        <span class="text-[10px] font-normal <?= $soldePos ? 'text-blue-400' : 'text-orange-400' ?>">FCFA</span>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       FORMULAIRE DYNAMIQUE
  ═══════════════════════════════════════════════════════════ -->
  <?php if ($canManage): ?>
  <div x-show="showForm" x-transition x-cloak
       class="border border-cb-primary/20 bg-cb-bg/30 rounded-xl p-4 space-y-3">

    <!-- Erreur -->
    <div x-show="formError" x-transition
         class="flex items-center gap-2 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">
      <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
      <span x-text="formError" class="flex-1"></span>
      <button @click="formError=null" class="text-rose-400 hover:text-rose-600"><i data-lucide="x" class="w-3 h-3"></i></button>
    </div>

    <!-- Ligne 1 : Catégorie (pleine largeur) -->
    <div>
      <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Catégorie *</label>
      <select x-model="form.category_code" @change="onCategoryChange()"
              class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
        <option value="">-- Choisir --</option>
        <?php foreach ($expCategories as $cat): ?>
        <option value="<?= e($cat['code']) ?>"><?= e($cat['label']) ?></option>
        <?php endforeach ?>
      </select>
    </div>

    <!-- ── CARBURANT ───────────────────────────────────────── -->
    <div x-show="form.category_code === 'carburant'" x-transition x-cloak>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Litres *</label>
          <input type="number" x-model.number="form.liters" min="0.01" step="0.01" placeholder="0.00"
                 @input="calcFuelAmount()"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Prix/L (FCFA)</label>
          <input type="number" x-model.number="form.price_per_liter" min="1" step="0.01"
                 @input="calcFuelAmount()"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Km compteur</label>
          <input type="number" x-model.number="form.km_at_fill" min="0" step="1"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Station</label>
          <input type="text" x-model="form.station_name" maxlength="100" placeholder="Nom de la station"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
    </div>

    <!-- ── ENTRETIEN ───────────────────────────────────────── -->
    <div x-show="form.category_code === 'entretien'" x-transition x-cloak>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Type de maintenance *</label>
          <select x-model="form.maintenance_type"
                  class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="corrective">Corrective</option>
            <option value="preventive">Préventive</option>
          </select>
        </div>
        <?php if (!empty($expMechanics)): ?>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Mécanicien</label>
          <select x-model="form.mechanic_id"
                  class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="">-- Optionnel --</option>
            <?php foreach ($expMechanics as $mec): ?>
            <option value="<?= (int)$mec['id'] ?>"><?= e($mec['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <?php endif ?>
      </div>
    </div>

    <!-- ── PNEUMATIQUE ─────────────────────────────────────── -->
    <div x-show="form.category_code === 'pneumatique'" x-transition x-cloak>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Position</label>
          <select x-model="form.tire_position"
                  class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="">-- Choisir --</option>
            <option value="avant_gauche">Avant gauche</option>
            <option value="avant_droit">Avant droit</option>
            <option value="arriere_gauche">Arrière gauche</option>
            <option value="arriere_droit">Arrière droit</option>
            <option value="secours">Secours</option>
          </select>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Type</label>
          <select x-model="form.tire_type"
                  class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="neuf">Neuf</option>
            <option value="occasion">Occasion</option>
            <option value="rechape">Rechapé</option>
          </select>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Marque</label>
          <input type="text" x-model="form.tire_brand" maxlength="80" placeholder="Michelin, Bridgestone..."
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Dimension</label>
          <input type="text" x-model="form.tire_size" maxlength="30" placeholder="295/80R22.5"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
      <div class="grid grid-cols-3 gap-3 mt-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Quantité</label>
          <input type="number" x-model.number="form.tire_quantity" min="1" max="20" step="1"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Km compteur</label>
          <input type="number" x-model.number="form.tire_km" min="0" step="1"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Fournisseur</label>
          <input type="text" x-model="form.tire_supplier" maxlength="100" placeholder="Nom du fournisseur"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
    </div>

    <!-- ── ASSURANCE ───────────────────────────────────────── -->
    <div x-show="form.category_code === 'assurance'" x-transition x-cloak>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Compagnie</label>
          <input type="text" x-model="form.insurance_company" maxlength="100" placeholder="Nom de l'assureur"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">N° Police</label>
          <input type="text" x-model="form.insurance_policy" maxlength="80" placeholder="POL-XXXXX"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Couverture</label>
          <select x-model="form.coverage_type"
                  class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="rc">RC (responsabilité civile)</option>
            <option value="tous_risques">Tous risques</option>
            <option value="mixte">Mixte</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Début couverture</label>
          <input type="date" x-model="form.insurance_start"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Fin couverture</label>
          <input type="date" x-model="form.insurance_end"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
    </div>

    <!-- ── VISITE TECHNIQUE ────────────────────────────────── -->
    <div x-show="form.category_code === 'visite_technique'" x-transition x-cloak>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Date de la visite</label>
          <input type="date" x-model="form.inspection_date"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Centre</label>
          <input type="text" x-model="form.inspection_center" maxlength="100" placeholder="Centre de contrôle"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Résultat</label>
          <select x-model="form.inspection_result"
                  class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="conforme">Conforme</option>
            <option value="non_conforme">Non conforme</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">N° Certificat</label>
          <input type="text" x-model="form.inspection_certificate" maxlength="80" placeholder="N° du certificat"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Prochaine échéance</label>
          <input type="date" x-model="form.inspection_next_due"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Observations</label>
          <input type="text" x-model="form.inspection_observations" maxlength="300" placeholder="Remarques..."
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
    </div>

    <!-- ── AMENDE ──────────────────────────────────────────── -->
    <div x-show="form.category_code === 'amende'" x-transition x-cloak>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Type d'infraction</label>
          <input type="text" x-model="form.infraction_type" maxlength="100" placeholder="Excès de vitesse, stationnement..."
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Lieu</label>
          <input type="text" x-model="form.fine_location" maxlength="150" placeholder="Lieu de l'infraction"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Autorité</label>
          <input type="text" x-model="form.fine_authority" maxlength="100" placeholder="Police, gendarmerie..."
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Date de l'amende</label>
          <input type="date" x-model="form.fine_date"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div class="flex items-end pb-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" x-model="form.is_contested" class="accent-cb-primary w-4 h-4">
            <span class="text-xs font-semibold text-slate-600">Amende contestée</span>
          </label>
        </div>
      </div>
    </div>

    <!-- ── LAVAGE BUS ──────────────────────────────────────── -->
    <div x-show="form.category_code === 'lavage_bus'" x-transition x-cloak>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Type de lavage</label>
          <select x-model="form.wash_type"
                  class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="complet">Complet</option>
            <option value="exterieur">Extérieur</option>
            <option value="interieur">Intérieur</option>
          </select>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Lieu / Station</label>
          <input type="text" x-model="form.wash_location" maxlength="100" placeholder="Station de lavage"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
    </div>

    <!-- ── PÉAGE ───────────────────────────────────────────── -->
    <div x-show="form.category_code === 'peage'" x-transition x-cloak>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Poste de péage</label>
          <input type="text" x-model="form.toll_name" maxlength="100" placeholder="Nom du poste"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Itinéraire / Axe</label>
          <input type="text" x-model="form.toll_route" maxlength="150" placeholder="Ex: Douala - Yaoundé"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
      </div>
    </div>

    <!-- ── PARKING ─────────────────────────────────────────── -->
    <div x-show="form.category_code === 'parking'" x-transition x-cloak>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Lieu</label>
          <input type="text" x-model="form.parking_location" maxlength="150" placeholder="Lieu du parking"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Durée (heures)</label>
          <input type="number" x-model.number="form.parking_duration" min="0.5" step="0.5" placeholder="0.0"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
      </div>
    </div>

    <!-- ── SALAIRE / AVANCE ────────────────────────────────── -->
    <div x-show="form.category_code === 'salaire' || form.category_code === 'salaire_avance'" x-transition x-cloak>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Mois</label>
          <select x-model="form.payroll_month"
                  class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="1">Janvier</option><option value="2">Février</option><option value="3">Mars</option>
            <option value="4">Avril</option><option value="5">Mai</option><option value="6">Juin</option>
            <option value="7">Juillet</option><option value="8">Août</option><option value="9">Septembre</option>
            <option value="10">Octobre</option><option value="11">Novembre</option><option value="12">Décembre</option>
          </select>
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Année</label>
          <input type="number" x-model.number="form.payroll_year" min="2020" max="2030" step="1"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Salaire de base</label>
          <input type="number" x-model.number="form.payroll_base" min="0" step="1"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Retenues</label>
          <input type="number" x-model.number="form.payroll_deductions" min="0" step="1" placeholder="0"
                 @input="calcPayrollNet()"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
      </div>
    </div>

    <!-- ── PRIMES / INDEMNITÉS / COMMISSIONS ───────────────── -->
    <div x-show="['prime_journaliere','prime_autre','indemnite','commission_agent'].includes(form.category_code)" x-transition x-cloak>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Motif</label>
          <input type="text" x-model="form.comp_reason" maxlength="200" placeholder="Motif de la prime / indemnité"
                 class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
        </div>
        <div x-show="form.category_code === 'commission_agent'">
          <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Mode de calcul</label>
          <div class="flex gap-2">
            <select x-model="form.comp_rate_type"
                    class="flex-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
              <option value="fixe">Montant fixe</option>
              <option value="pourcentage">Pourcentage</option>
            </select>
            <input type="number" x-model.number="form.comp_rate_value" min="0" step="0.01" placeholder="Valeur"
                   class="w-24 px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
          </div>
        </div>
      </div>
    </div>

    <!-- Ligne commune : Montant + Description -->
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">
          Montant FCFA *
          <span x-show="form.category_code === 'carburant'" class="font-normal normal-case text-slate-400">(auto-calculé)</span>
        </label>
        <input type="number" x-model.number="form.amount_fcfa" min="1" step="1" placeholder="0"
               :class="form.category_code === 'carburant' ? 'bg-slate-50' : ''"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Description</label>
        <input type="text" x-model="form.description" maxlength="200" placeholder="Motif, détails..."
               class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
      </div>
    </div>

    <!-- Ligne commune : Paiement + Référence -->
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Mode de paiement</label>
        <select x-model="form.payment_method"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
          <option value="especes">Espèces</option>
          <option value="mobile_money">Mobile Money</option>
          <option value="carte">Carte bancaire</option>
          <option value="virement">Virement</option>
        </select>
      </div>
      <div>
        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Référence <span class="font-normal normal-case">(optionnel)</span></label>
        <input type="text" x-model="form.reference" maxlength="100" placeholder="N° facture, reçu..."
               class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-cb-primary outline-none text-sm">
      </div>
    </div>

    <div class="flex items-center justify-between pt-2">
      <p class="text-[10px] text-slate-400">Sera enregistré en trésorerie automatiquement</p>
      <button @click="submit()" :disabled="submitting"
              class="px-4 py-2 rounded-lg bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition disabled:opacity-50 flex items-center gap-1.5">
        <svg x-show="submitting" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        <i data-lucide="check" class="w-3.5 h-3.5" x-show="!submitting"></i>
        Enregistrer
      </button>
    </div>
  </div>
  <?php endif ?>

  <!-- ══════════════════════════════════════════════════════════
       HISTORIQUE EN ACCORDÉONS
  ═══════════════════════════════════════════════════════════ -->
  <div class="space-y-1">
    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
      <i data-lucide="history" class="w-3.5 h-3.5"></i> Historique par catégorie
    </h4>

    <?php if (empty($sortedGroups)): ?>
    <div class="text-center py-6">
      <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center mx-auto mb-2">
        <i data-lucide="receipt" class="w-5 h-5 text-slate-300"></i>
      </div>
      <p class="text-sm text-slate-400">Aucune donnée enregistrée</p>
    </div>
    <?php else: ?>
    <?php $firstGroup = array_key_first($sortedGroups); ?>
    <div x-data="{ openAcc: '<?= e($firstGroup) ?>' }">
      <?php foreach ($sortedGroups as $code => $group): ?>
      <div class="border border-slate-100 rounded-xl mb-2 overflow-hidden">
        <!-- Header accordéon -->
        <button @click="openAcc = openAcc === '<?= e($code) ?>' ? '' : '<?= e($code) ?>'"
                class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-slate-50/60 transition"
                :class="openAcc === '<?= e($code) ?>' ? 'bg-slate-50' : ''">
          <i data-lucide="<?= e($catIcons[$code] ?? 'tag') ?>" class="w-4 h-4 text-slate-400 shrink-0"></i>
          <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?= $colorMap[$group['color']] ?? 'bg-slate-100 text-slate-700' ?>">
            <?= e($group['label']) ?>
          </span>
          <span class="text-xs text-slate-400 tabular-nums"><?= $group['count'] ?> entrée(s)</span>
          <span class="ml-auto text-sm font-bold text-slate-700 tabular-nums font-mono">
            <?= number_format($group['total'], 0, ',', ' ') ?> <span class="text-[10px] font-normal text-slate-400">FCFA</span>
          </span>
          <i data-lucide="chevron-down" class="w-4 h-4 text-slate-300 transition-transform shrink-0"
             :class="openAcc === '<?= e($code) ?>' ? 'rotate-180' : ''"></i>
        </button>

        <!-- Contenu accordéon -->
        <div x-show="openAcc === '<?= e($code) ?>'" x-transition x-cloak class="border-t border-slate-100">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-[10px] text-slate-500 uppercase tracking-wider bg-slate-50/60">
                <tr>
                  <th class="text-left px-4 py-2">Date</th>
                  <?php if ($code === 'carburant'): ?>
                  <th class="text-right px-3 py-2">Litres</th>
                  <th class="text-right px-3 py-2">Prix/L</th>
                  <th class="text-left px-3 py-2">Station</th>
                  <th class="text-right px-3 py-2">Km</th>
                  <?php elseif ($code === 'entretien'): ?>
                  <th class="text-left px-3 py-2">Type</th>
                  <th class="text-left px-3 py-2">Description</th>
                  <th class="text-left px-3 py-2">Mécanicien</th>
                  <th class="text-center px-3 py-2">Statut</th>
                  <?php elseif ($code === 'pneumatique'): ?>
                  <th class="text-left px-3 py-2">Position</th>
                  <th class="text-left px-3 py-2">Marque / Taille</th>
                  <th class="text-left px-3 py-2">Type</th>
                  <th class="text-center px-3 py-2">Qté</th>
                  <?php elseif ($code === 'assurance'): ?>
                  <th class="text-left px-3 py-2">Compagnie</th>
                  <th class="text-left px-3 py-2">N° Police</th>
                  <th class="text-left px-3 py-2">Couverture</th>
                  <th class="text-left px-3 py-2">Période</th>
                  <?php elseif ($code === 'visite_technique'): ?>
                  <th class="text-left px-3 py-2">Centre</th>
                  <th class="text-center px-3 py-2">Résultat</th>
                  <th class="text-left px-3 py-2">Certificat</th>
                  <th class="text-left px-3 py-2">Prochaine</th>
                  <?php elseif ($code === 'amende'): ?>
                  <th class="text-left px-3 py-2">Infraction</th>
                  <th class="text-left px-3 py-2">Lieu</th>
                  <th class="text-left px-3 py-2">Autorité</th>
                  <th class="text-center px-3 py-2">Contestée</th>
                  <?php elseif ($code === 'lavage_bus'): ?>
                  <th class="text-left px-3 py-2">Type</th>
                  <th class="text-left px-3 py-2">Lieu</th>
                  <?php elseif ($code === 'peage'): ?>
                  <th class="text-left px-3 py-2">Poste</th>
                  <th class="text-left px-3 py-2">Itinéraire</th>
                  <?php elseif ($code === 'parking'): ?>
                  <th class="text-left px-3 py-2">Lieu</th>
                  <th class="text-right px-3 py-2">Durée</th>
                  <?php elseif (in_array($code, ['salaire','salaire_avance'])): ?>
                  <th class="text-left px-3 py-2">Type</th>
                  <th class="text-left px-3 py-2">Période</th>
                  <th class="text-right px-3 py-2">Base</th>
                  <th class="text-right px-3 py-2">Retenues</th>
                  <?php elseif (in_array($code, ['prime_journaliere','prime_autre','indemnite','commission_agent'])): ?>
                  <th class="text-left px-3 py-2">Type</th>
                  <th class="text-left px-3 py-2">Motif</th>
                  <?php else: ?>
                  <th class="text-left px-3 py-2">Description</th>
                  <th class="text-left px-3 py-2">Référence</th>
                  <?php endif ?>
                  <th class="text-right px-4 py-2">Montant</th>
                  <th class="text-left px-3 py-2">Par</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <?php foreach ($group['items'] as $item):
                  $src = $item['_source'] ?? 'treasury';
                  $amt = (int)($item['amount_fcfa'] ?? $item['total_cost'] ?? $item['cost_fcfa'] ?? 0);
                  $date = $item['created_at'] ?? $item['logged_at'] ?? $item['done_at'] ?? $item['scheduled_at'] ?? '';
                  $userName = '';
                  if (!empty($item['user_first'])) {
                      $userName = $item['user_first'] . ' ' . mb_substr($item['user_last'] ?? '', 0, 1) . '.';
                  } elseif (!empty($item['logged_by_name'])) {
                      $userName = $item['logged_by_name'];
                  } elseif (!empty($item['mechanic_name'])) {
                      $userName = $item['mechanic_name'];
                  }
                  $isEnc = ($item['type'] ?? 'decaissement') === 'encaissement';
                ?>
                <tr class="hover:bg-slate-50/60 transition">
                  <td class="px-4 py-2.5 text-xs text-slate-500 whitespace-nowrap">
                    <?= $date ? date('d/m/y H:i', strtotime($date)) : '—' ?>
                  </td>

                  <?php if ($code === 'carburant'): ?>
                  <td class="px-3 py-2.5 text-right text-xs font-mono text-slate-700"><?= isset($item['liters']) ? number_format((float)$item['liters'], 1, ',', ' ') . ' L' : '—' ?></td>
                  <td class="px-3 py-2.5 text-right text-xs font-mono text-slate-500"><?= isset($item['price_per_liter']) ? number_format((float)$item['price_per_liter'], 0, ',', ' ') : '—' ?></td>
                  <td class="px-3 py-2.5 text-xs text-slate-600"><?= e($item['station_name'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-right text-xs font-mono text-slate-500"><?= !empty($item['km_at_fill']) ? number_format((int)$item['km_at_fill'], 0, ',', ' ') : '—' ?></td>

                  <?php elseif ($code === 'entretien'): ?>
                  <td class="px-3 py-2.5">
                    <?php if (!empty($item['maintenance_type'])): $mtCls = $item['maintenance_type'] === 'preventive' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'; ?>
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?= $mtCls ?>"><?= $item['maintenance_type'] === 'preventive' ? 'Préventive' : 'Corrective' ?></span>
                    <?php else: ?>—<?php endif ?>
                  </td>
                  <td class="px-3 py-2.5 text-xs text-slate-700 max-w-[200px] truncate"><?= e($item['description'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-xs text-slate-600"><?= e($item['mechanic_name'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-center">
                    <?php if (!empty($item['status'])):
                      $stCls = match($item['status'] ?? '') { 'termine'=>'bg-emerald-100 text-emerald-700', 'en_cours'=>'bg-blue-100 text-blue-700', 'planifie'=>'bg-amber-100 text-amber-700', default=>'bg-slate-100 text-slate-500' };
                    ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= $stCls ?>"><?= e(ucfirst(str_replace('_', ' ', $item['status']))) ?></span>
                    <?php else: ?>—<?php endif ?>
                  </td>

                  <?php elseif ($code === 'pneumatique'): ?>
                  <td class="px-3 py-2.5 text-xs text-slate-700"><?= e(str_replace('_', ' ', ucfirst($item['position'] ?? '—'))) ?></td>
                  <td class="px-3 py-2.5 text-xs text-slate-600"><?= e(($item['brand'] ?? '') . ($item['size'] ? ' '.$item['size'] : '')) ?: '—' ?></td>
                  <td class="px-3 py-2.5">
                    <?php $ttCls = match($item['tire_type'] ?? '') { 'neuf'=>'bg-emerald-100 text-emerald-700', 'occasion'=>'bg-amber-100 text-amber-700', 'rechape'=>'bg-blue-100 text-blue-700', default=>'bg-slate-100 text-slate-500' }; ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= $ttCls ?>"><?= e(ucfirst($item['tire_type'] ?? '—')) ?></span>
                  </td>
                  <td class="px-3 py-2.5 text-center text-xs font-mono"><?= (int)($item['quantity'] ?? 1) ?></td>

                  <?php elseif ($code === 'assurance'): ?>
                  <td class="px-3 py-2.5 text-xs text-slate-700"><?= e($item['company'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-xs font-mono text-slate-500"><?= e($item['policy_number'] ?? '—') ?></td>
                  <td class="px-3 py-2.5">
                    <?php $cvLbl = match($item['coverage_type'] ?? '') { 'rc'=>'RC', 'tous_risques'=>'Tous risques', 'mixte'=>'Mixte', default=>'—' }; ?>
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold bg-green-100 text-green-700"><?= $cvLbl ?></span>
                  </td>
                  <td class="px-3 py-2.5 text-xs text-slate-500"><?= !empty($item['period_start']) ? date('d/m/y', strtotime($item['period_start'])).' → '.(!empty($item['period_end']) ? date('d/m/y', strtotime($item['period_end'])) : '?') : '—' ?></td>

                  <?php elseif ($code === 'visite_technique'): ?>
                  <td class="px-3 py-2.5 text-xs text-slate-700"><?= e($item['center'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-center">
                    <?php $resCls = ($item['result'] ?? '') === 'conforme' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= $resCls ?>"><?= e(ucfirst($item['result'] ?? '—')) ?></span>
                  </td>
                  <td class="px-3 py-2.5 text-xs font-mono text-slate-500"><?= e($item['certificate_number'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-xs text-slate-500"><?= !empty($item['next_due']) ? date('d/m/Y', strtotime($item['next_due'])) : '—' ?></td>

                  <?php elseif ($code === 'amende'): ?>
                  <td class="px-3 py-2.5 text-xs text-slate-700"><?= e($item['infraction_type'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-xs text-slate-600"><?= e($item['location'] ?? $item['fine_location'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-xs text-slate-500"><?= e($item['authority'] ?? $item['fine_authority'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-center">
                    <?php if (!empty($item['is_contested'])): ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-amber-100 text-amber-700">Oui</span>
                    <?php else: ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-slate-100 text-slate-500">Non</span>
                    <?php endif ?>
                  </td>

                  <?php elseif ($code === 'lavage_bus'): ?>
                  <td class="px-3 py-2.5">
                    <?php $wLbl = match($item['wash_type'] ?? '') { 'interieur'=>'Intérieur', 'exterieur'=>'Extérieur', 'complet'=>'Complet', default=>'—' }; ?>
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold bg-blue-100 text-blue-700"><?= $wLbl ?></span>
                  </td>
                  <td class="px-3 py-2.5 text-xs text-slate-600"><?= e($item['location'] ?? $item['wash_location'] ?? '—') ?></td>

                  <?php elseif ($code === 'peage'): ?>
                  <td class="px-3 py-2.5 text-xs text-slate-700"><?= e($item['toll_name'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-xs text-slate-600"><?= e($item['route'] ?? $item['toll_route'] ?? '—') ?></td>

                  <?php elseif ($code === 'parking'): ?>
                  <td class="px-3 py-2.5 text-xs text-slate-700"><?= e($item['location'] ?? $item['parking_location'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-right text-xs font-mono text-slate-500"><?= !empty($item['duration_hours']) ? number_format((float)$item['duration_hours'], 1) . 'h' : '—' ?></td>

                  <?php elseif (in_array($code, ['salaire','salaire_avance'])): ?>
                  <td class="px-3 py-2.5">
                    <?php $pLbl = ($item['payroll_type'] ?? '') === 'avance' ? 'Avance' : 'Salaire'; $pCls = ($item['payroll_type'] ?? '') === 'avance' ? 'bg-amber-100 text-amber-700' : 'bg-pink-100 text-pink-700'; ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= $pCls ?>"><?= $pLbl ?></span>
                  </td>
                  <td class="px-3 py-2.5 text-xs text-slate-600"><?= !empty($item['period_month']) ? sprintf('%02d/%d', $item['period_month'], $item['period_year'] ?? date('Y')) : '—' ?></td>
                  <td class="px-3 py-2.5 text-right text-xs font-mono text-slate-500"><?= !empty($item['base_amount']) ? number_format((int)$item['base_amount'], 0, ',', ' ') : '—' ?></td>
                  <td class="px-3 py-2.5 text-right text-xs font-mono text-slate-400"><?= (int)($item['deductions'] ?? 0) > 0 ? '-'.number_format((int)$item['deductions'], 0, ',', ' ') : '—' ?></td>

                  <?php elseif (in_array($code, ['prime_journaliere','prime_autre','indemnite','commission_agent'])): ?>
                  <td class="px-3 py-2.5">
                    <?php $cLabels = ['prime_journaliere'=>'Journalière','prime_autre'=>'Autre prime','indemnite'=>'Indemnité','commission_agent'=>'Commission'];
                    $cColors = ['prime_journaliere'=>'bg-blue-100 text-blue-700','prime_autre'=>'bg-violet-100 text-violet-700','indemnite'=>'bg-blue-100 text-blue-700','commission_agent'=>'bg-violet-100 text-violet-700']; ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= $cColors[$item['comp_type'] ?? $code] ?? 'bg-slate-100 text-slate-500' ?>"><?= e($cLabels[$item['comp_type'] ?? $code] ?? ucfirst($code)) ?></span>
                  </td>
                  <td class="px-3 py-2.5 text-xs text-slate-600 max-w-[200px] truncate"><?= e($item['reason'] ?? $item['description'] ?? '—') ?></td>

                  <?php else: ?>
                  <td class="px-3 py-2.5 text-xs text-slate-700 max-w-[200px] truncate"><?= e($item['description'] ?? '—') ?></td>
                  <td class="px-3 py-2.5 text-xs text-slate-400 font-mono"><?= e($item['reference'] ?? '—') ?></td>
                  <?php endif ?>

                  <td class="px-4 py-2.5 text-right whitespace-nowrap font-mono font-semibold text-sm <?= $isEnc ? 'text-emerald-700' : 'text-rose-700' ?>">
                    <?= $isEnc ? '+' : '' ?><?= number_format($amt, 0, ',', ' ') ?>
                    <span class="text-[10px] font-normal text-slate-400 ml-0.5">F</span>
                  </td>
                  <td class="px-3 py-2.5 text-xs text-slate-500"><?= e($userName ?: '—') ?></td>
                </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>

</div>

<script>
function expenseWidget() {
  var cfg = <?= json_encode([
    'entityType'    => $expEntityType,
    'entityId'      => $expEntityId,
    'url'           => url('finance/treasury/quick-expense'),
    'csrf'          => csrf_token(),
    'context'       => $expContext,
    'busKm'         => $expBusKm,
    'driverSalary'  => $expDriverSalary,
    'driverBonus'   => $expDriverBonus,
  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;

  var now = new Date();

  return {
    showForm: false,
    submitting: false,
    formError: null,
    form: {
      category_code:   '',
      amount_fcfa:     '',
      description:     '',
      payment_method:  'especes',
      reference:       '',
      // Carburant
      liters: '', price_per_liter: 625, km_at_fill: cfg.busKm || '', station_name: '',
      // Entretien
      maintenance_type: 'corrective', mechanic_id: '',
      // Pneumatique
      tire_position: '', tire_type: 'neuf', tire_brand: '', tire_size: '', tire_quantity: 1, tire_km: cfg.busKm || '', tire_supplier: '',
      // Assurance
      insurance_company: '', insurance_policy: '', coverage_type: 'rc', insurance_start: '', insurance_end: '',
      // Visite technique
      inspection_date: '', inspection_center: '', inspection_result: 'conforme', inspection_certificate: '', inspection_next_due: '', inspection_observations: '',
      // Amende
      infraction_type: '', fine_location: '', fine_authority: '', fine_date: '', is_contested: false,
      // Lavage
      wash_type: 'complet', wash_location: '',
      // Péage
      toll_name: '', toll_route: '',
      // Parking
      parking_location: '', parking_duration: '',
      // Salaire
      payroll_month: String(now.getMonth() + 1), payroll_year: now.getFullYear(), payroll_base: cfg.driverSalary || '', payroll_deductions: 0, payroll_notes: '',
      // Compensations
      comp_reason: '', comp_rate_type: 'fixe', comp_rate_value: '',
    },

    onCategoryChange() {
      if (this.form.category_code === 'carburant') {
        this.form.km_at_fill = cfg.busKm || '';
        this.form.price_per_liter = 625;
        this.form.liters = '';
        this.form.amount_fcfa = '';
      } else if (this.form.category_code === 'pneumatique') {
        this.form.tire_km = cfg.busKm || '';
      } else if (this.form.category_code === 'prime_journaliere') {
        this.form.amount_fcfa = cfg.driverBonus || '';
      } else if (this.form.category_code === 'salaire' || this.form.category_code === 'salaire_avance') {
        this.form.payroll_base = cfg.driverSalary || '';
        this.form.payroll_month = String(now.getMonth() + 1);
        this.form.payroll_year = now.getFullYear();
        this.form.payroll_deductions = 0;
        if (this.form.category_code === 'salaire') {
          this.form.amount_fcfa = cfg.driverSalary || '';
        }
      }
      this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    calcFuelAmount() {
      if (this.form.category_code === 'carburant' && this.form.liters > 0 && this.form.price_per_liter > 0) {
        this.form.amount_fcfa = Math.round(this.form.liters * this.form.price_per_liter);
      }
    },

    calcPayrollNet() {
      if (['salaire','salaire_avance'].includes(this.form.category_code)) {
        var base = Number(this.form.payroll_base) || 0;
        var ded  = Number(this.form.payroll_deductions) || 0;
        if (this.form.category_code === 'salaire') {
          this.form.amount_fcfa = Math.max(0, base - ded);
        }
      }
    },

    async submit() {
      if (!this.form.category_code) { this.formError = 'Choisissez une catégorie.'; return; }
      if (this.form.category_code === 'carburant') {
        if (!this.form.liters || this.form.liters <= 0) { this.formError = 'Saisissez le nombre de litres.'; return; }
        this.calcFuelAmount();
      }
      if (!this.form.amount_fcfa || this.form.amount_fcfa <= 0) { this.formError = 'Saisissez un montant.'; return; }

      this.submitting = true;
      this.formError  = null;

      var body = new FormData();
      body.append('_csrf',          cfg.csrf);
      body.append('category_code',  this.form.category_code);
      body.append('amount_fcfa',    String(this.form.amount_fcfa));
      body.append('description',    this.form.description);
      body.append('payment_method', this.form.payment_method);
      body.append('reference',      this.form.reference);

      // Rattachement entité
      var ef = cfg.entityType === 'trip' ? 'trip_id' : cfg.entityType === 'bus' ? 'bus_id' : 'driver_id';
      body.append(ef, String(cfg.entityId));
      if (cfg.context.bus_id    && ef !== 'bus_id')    body.append('bus_id',    String(cfg.context.bus_id));
      if (cfg.context.driver_id && ef !== 'driver_id') body.append('driver_id', String(cfg.context.driver_id));
      if (cfg.context.trip_id   && ef !== 'trip_id')   body.append('trip_id',   String(cfg.context.trip_id));

      // Champs spécifiques par catégorie
      var cc = this.form.category_code;
      var f = this.form;
      if (cc === 'carburant') {
        body.append('liters', String(f.liters)); body.append('price_per_liter', String(f.price_per_liter));
        if (f.km_at_fill) body.append('km_at_fill', String(f.km_at_fill));
        if (f.station_name) body.append('station_name', f.station_name);
      } else if (cc === 'entretien') {
        body.append('maintenance_type', f.maintenance_type);
        if (f.mechanic_id) body.append('mechanic_id', String(f.mechanic_id));
      } else if (cc === 'pneumatique') {
        if (f.tire_position) body.append('tire_position', f.tire_position);
        body.append('tire_type', f.tire_type);
        if (f.tire_brand) body.append('tire_brand', f.tire_brand);
        if (f.tire_size) body.append('tire_size', f.tire_size);
        body.append('tire_quantity', String(f.tire_quantity));
        if (f.tire_km) body.append('tire_km', String(f.tire_km));
        if (f.tire_supplier) body.append('tire_supplier', f.tire_supplier);
      } else if (cc === 'assurance') {
        if (f.insurance_company) body.append('insurance_company', f.insurance_company);
        if (f.insurance_policy) body.append('insurance_policy', f.insurance_policy);
        body.append('coverage_type', f.coverage_type);
        if (f.insurance_start) body.append('insurance_start', f.insurance_start);
        if (f.insurance_end) body.append('insurance_end', f.insurance_end);
      } else if (cc === 'visite_technique') {
        if (f.inspection_date) body.append('inspection_date', f.inspection_date);
        if (f.inspection_center) body.append('inspection_center', f.inspection_center);
        body.append('inspection_result', f.inspection_result);
        if (f.inspection_certificate) body.append('inspection_certificate', f.inspection_certificate);
        if (f.inspection_next_due) body.append('inspection_next_due', f.inspection_next_due);
        if (f.inspection_observations) body.append('inspection_observations', f.inspection_observations);
      } else if (cc === 'amende') {
        if (f.infraction_type) body.append('infraction_type', f.infraction_type);
        if (f.fine_location) body.append('fine_location', f.fine_location);
        if (f.fine_authority) body.append('fine_authority', f.fine_authority);
        if (f.fine_date) body.append('fine_date', f.fine_date);
        if (f.is_contested) body.append('is_contested', '1');
      } else if (cc === 'lavage_bus') {
        body.append('wash_type', f.wash_type);
        if (f.wash_location) body.append('wash_location', f.wash_location);
      } else if (cc === 'peage') {
        if (f.toll_name) body.append('toll_name', f.toll_name);
        if (f.toll_route) body.append('toll_route', f.toll_route);
      } else if (cc === 'parking') {
        if (f.parking_location) body.append('parking_location', f.parking_location);
        if (f.parking_duration) body.append('parking_duration', String(f.parking_duration));
      } else if (cc === 'salaire' || cc === 'salaire_avance') {
        body.append('payroll_month', String(f.payroll_month));
        body.append('payroll_year', String(f.payroll_year));
        body.append('payroll_base', String(f.payroll_base));
        body.append('payroll_deductions', String(f.payroll_deductions));
        if (f.payroll_notes) body.append('payroll_notes', f.payroll_notes);
      } else if (['prime_journaliere','prime_autre','indemnite','commission_agent'].includes(cc)) {
        if (f.comp_reason) body.append('comp_reason', f.comp_reason);
        if (cc === 'commission_agent') {
          body.append('comp_rate_type', f.comp_rate_type);
          if (f.comp_rate_value) body.append('comp_rate_value', String(f.comp_rate_value));
        }
      }

      try {
        var res  = await fetch(cfg.url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        var json = await res.json();
        if (json.success && json.transaction) {
          this.showForm = false;
          window.location.reload();
        } else {
          this.formError = json.error || 'Erreur lors de l\'enregistrement.';
        }
      } catch(e) {
        this.formError = 'Erreur réseau, veuillez réessayer.';
      }
      this.submitting = false;
    },
  };
}
</script>
