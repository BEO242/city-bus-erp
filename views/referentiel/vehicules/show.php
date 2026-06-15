<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Bus;
$view->extends('layouts/app');
$gallery     = \CityBus\Services\MediaService::enrichAll($gallery ?? []);
$docs        = \CityBus\Services\MediaService::enrichAll($docs    ?? []);
$cover       = $gallery[0] ?? null;
$maintenance = $maintenance ?? [];
$fuelLogs    = $fuelLogs ?? [];
$mechanics   = $mechanics ?? [];
$incidents   = $incidents ?? [];
$notes       = $notes ?? [];
$trips       = $trips ?? [];

$statusColors = [
  'disponible'   => 'bg-emerald-100 text-emerald-700',
  'en_voyage'    => 'bg-blue-100 text-blue-700',
  'maintenance'  => 'bg-amber-100 text-amber-700',
  'hors_service' => 'bg-rose-100 text-rose-700',
];
$sc = $statusColors[$bus['status']] ?? 'bg-slate-100 text-slate-700';
$statusLabels = Bus::STATUSES;

// Fuel stats
$totalLiters   = array_sum(array_column($fuelLogs, 'liters'));
$totalFuelCost = array_sum(array_column($fuelLogs, 'total_cost'));

// Maintenance stats
$maintenanceCost = array_sum(array_column($maintenance, 'actual_cost'));
$maintenanceOpen = count(array_filter($maintenance, fn($m) => in_array($m['status'], ['planifie','en_cours'])));

// Incident stats
$incidentsOpen = count(array_filter($incidents, fn($i) => !$i['resolved']));
$incidentsCost = array_sum(array_column($incidents, 'cost_fcfa'));

$busUrl = url('referentiel/vehicules/' . $bus['id']);
?>
<?php $view->start('content') ?>
<div class="space-y-5" x-data="{ tab: 'voyages' }">

  <!-- En-tête -->
  <div class="flex items-center gap-4 flex-wrap">
    <a href="<?= e(url('referentiel/vehicules')) ?>"
       class="text-slate-500 hover:text-cb-primary p-2 rounded-lg hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-xl font-black text-slate-900"><?= e($bus['plate']) ?></h1>
        <span class="font-mono text-xs px-2.5 py-1 bg-cb-bg text-cb-primary rounded-full font-bold"><?= e($bus['code']) ?></span>
        <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?= $sc ?>">
          <?= e($statusLabels[$bus['status']] ?? $bus['status']) ?>
        </span>
      </div>
      <p class="text-sm text-slate-500 mt-0.5"><?= e($bus['brand']) ?> <?= e($bus['model']) ?> <?= e($bus['year']) ?></p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= e(url('referentiel/vehicules/'.$bus['id'].'/edit')) ?>"
         class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-2">
        <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
      </a>
      <form method="post" action="<?= e(url('referentiel/vehicules/'.$bus['id'].'/delete')) ?>"
            onsubmit="return confirm('Supprimer ce véhicule ? Cette action est irréversible.')">
        <?= csrf_field() ?>
        <button type="submit"
                class="px-3 py-2 rounded-xl border border-rose-200 text-rose-600 text-sm font-semibold hover:bg-rose-50 transition">
          <i data-lucide="trash-2" class="w-4 h-4"></i>
        </button>
      </form>
    </div>
  </div>

  <!-- Alertes -->
  <?php if (!empty($alerts)): ?>
  <div class="space-y-2">
    <?php foreach ($alerts as $a):
      $cls = $a['level']==='danger' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800';
    ?>
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl border <?= $cls ?> text-sm">
      <i data-lucide="<?= e($a['icon']) ?>" class="w-4 h-4 shrink-0"></i>
      <div class="flex-1"><span class="font-semibold"><?= e($a['label']) ?></span><span class="opacity-75 ml-2"><?= e($a['detail']) ?></span></div>
      <?php if (!empty($a['date'])): ?><span class="text-xs opacity-70"><?= e($a['date']) ?></span><?php endif ?>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <!-- Cover + stats strip -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <?php if ($cover): ?>
      <img src="<?= e($cover['thumb_url']) ?>" alt="<?= e($bus['code']) ?>" class="w-full h-48 object-cover">
    <?php else: ?>
      <div class="w-full h-24 bg-gradient-to-br from-cb-bg to-slate-100 flex items-center justify-center">
        <i data-lucide="bus" class="w-12 h-12 text-slate-200"></i>
      </div>
    <?php endif ?>
    <div class="grid grid-cols-4 sm:grid-cols-4 lg:grid-cols-8 divide-x divide-slate-100 text-center">
      <div class="py-3 px-2">
        <div class="text-xl font-black text-cb-primary"><?= e($bus['seats']) ?></div>
        <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">Places</div>
      </div>
      <div class="py-3 px-2">
        <div class="text-xl font-black text-slate-800"><?= number_format((int)$bus['km_current'],0,' ') ?></div>
        <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">Km</div>
      </div>
      <div class="py-3 px-2">
        <div class="text-xl font-black text-slate-800"><?= (int)($stats['trips_total'] ?? 0) ?></div>
        <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">Voyages</div>
      </div>
      <div class="py-3 px-2">
        <div class="text-xl font-black text-emerald-600"><?= number_format((int)($stats['revenue'] ?? 0)/1000) ?>K</div>
        <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">FCFA</div>
      </div>
      <div class="py-3 px-2 <?= count($maintenance) > 0 ? '' : 'opacity-40' ?>">
        <div class="text-xl font-black <?= $maintenanceOpen > 0 ? 'text-amber-600' : 'text-slate-800' ?>"><?= count($maintenance) ?></div>
        <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">Mainten.</div>
      </div>
      <div class="py-3 px-2 <?= count($fuelLogs) > 0 ? '' : 'opacity-40' ?>">
        <div class="text-xl font-black text-slate-800"><?= number_format($totalLiters,0,' ') ?></div>
        <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">Litres</div>
      </div>
      <div class="py-3 px-2 <?= $incidentsOpen > 0 ? '' : 'opacity-40' ?>">
        <div class="text-xl font-black <?= $incidentsOpen > 0 ? 'text-rose-600' : 'text-slate-800' ?>"><?= count($incidents) ?></div>
        <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">Incidents</div>
      </div>
      <div class="py-3 px-2">
        <div class="text-xl font-black text-slate-800"><?= count($gallery) + count($docs) ?></div>
        <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">Médias</div>
      </div>
    </div>
  </div>

  <!-- Fiches info (identité / affectation / specs / conformité / équipements) -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Identification -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-2.5">
      <h2 class="font-bold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3 text-sm">
        <i data-lucide="info" class="w-4 h-4 text-cb-primary"></i> Identification
      </h2>
      <?php foreach ([
        ['Couleur', $bus['color']],
        ['Carburant', Bus::FUEL_TYPES[$bus['fuel_type'] ?? ''] ?? $bus['fuel_type'] ?? null],
        ['Transmission', Bus::TRANSMISSIONS[$bus['transmission'] ?? ''] ?? $bus['transmission'] ?? null],
        ['N° Chassis', $bus['vin']],
        ['N° Moteur', $bus['engine_number']],
      ] as [$label, $val]): if (!$val) continue; ?>
      <div class="flex justify-between text-sm">
        <span class="text-slate-400"><?= $label ?></span>
        <span class="font-semibold text-slate-800"><?= e($val) ?></span>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Affectation -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-2.5">
      <h2 class="font-bold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3 text-sm">
        <i data-lucide="map-pin" class="w-4 h-4 text-cb-primary"></i> Affectation &amp; acquisition
      </h2>
      <?php foreach ([
        ['Agence', $bus['agency_name'] ?? null],
        ['Date achat', $bus['purchase_date']],
        ['Prix achat', !empty($bus['purchase_price_fcfa']) ? number_format((int)$bus['purchase_price_fcfa'],0,' ').' FCFA' : null],
        ['Mode', !empty($bus['financing_type']) ? (Bus::FINANCING_TYPES[$bus['financing_type']] ?? $bus['financing_type']) : null],
        ['Fournisseur', $bus['supplier'] ?? null],
        ['Km à l\'achat', $bus['mileage_at_purchase'] ? number_format((int)$bus['mileage_at_purchase'],0,' ').' km' : null],
        ['N° carte grise', $bus['registration_card_number'] ?? null],
        ['Date C.G.', $bus['registration_card_date'] ?? null],
      ] as [$label, $val]): if (!$val) continue; ?>
      <div class="flex justify-between text-sm gap-3">
        <span class="text-slate-400 shrink-0"><?= $label ?></span>
        <span class="font-semibold text-slate-800 text-right truncate"><?= e($val) ?></span>
      </div>
      <?php endforeach ?>
      <?php if (!empty($primaryDriver)): ?>
      <div class="mt-2 pt-3 border-t border-slate-100">
        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-2">Chauffeur principal</div>
        <a href="<?= e(url('referentiel/drivers/'.$primaryDriver['id'])) ?>"
           class="flex items-center gap-3 p-3 bg-cb-bg rounded-xl hover:bg-blue-50 transition">
          <div class="w-9 h-9 rounded-full bg-cb-primary text-white flex items-center justify-center font-bold text-sm shrink-0">
            <?= e(strtoupper(substr($primaryDriver['first_name'],0,1).substr($primaryDriver['last_name'],0,1))) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-sm text-slate-800 truncate"><?= e($primaryDriver['last_name']) ?> <?= e($primaryDriver['first_name']) ?></div>
            <div class="text-xs text-slate-500"><?= e($primaryDriver['matricule']) ?></div>
          </div>
          <div class="text-right shrink-0">
            <div class="font-black text-cb-primary"><?= number_format((float)($primaryDriver['rating_score'] ?? 0),1) ?>/10</div>
          </div>
        </a>
      </div>
      <?php endif ?>
    </div>

    <!-- Conformité -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
      <h2 class="font-bold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3 mb-3 text-sm">
        <i data-lucide="shield-check" class="w-4 h-4 text-cb-primary"></i> Conformité
      </h2>
      <?php foreach ([
        'Assurance' => [['Expiration', $bus['insurance_expiry']], ['Compagnie', $bus['insurance_company']], ['N° Police', $bus['insurance_policy']]],
        'Contrôle technique' => [['Expiration', $bus['tech_control_expiry']], ['Centre', $bus['tech_control_center']]],
        'Prochaine maintenance' => [['Date', $bus['next_maintenance_at']], ['Km déclencheur', $bus['next_maintenance_km'] ? number_format((int)$bus['next_maintenance_km'],0,' ').' km' : null]],
      ] as $section => $items):
        $hasData = array_filter($items, fn($i) => !empty($i[1]));
        if (!$hasData) continue;
      ?>
      <div class="mb-3">
        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5"><?= $section ?></div>
        <?php foreach ($items as [$label, $val]): if (!$val) continue; ?>
        <div class="flex justify-between text-sm py-0.5">
          <span class="text-slate-400"><?= $label ?></span>
          <span class="font-semibold text-slate-800"><?= e($val) ?></span>
        </div>
        <?php endforeach ?>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Équipements + GPS -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
      <h2 class="font-bold text-slate-700 flex items-center gap-2 border-b border-slate-100 pb-3 mb-3 text-sm">
        <i data-lucide="settings-2" class="w-4 h-4 text-cb-primary"></i> Équipements
      </h2>
      <div class="flex flex-wrap gap-2 mb-3">
        <?php if ($bus['ac']): ?><span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-semibold"><i data-lucide="wind" class="w-3 h-3"></i> Clim</span><?php endif ?>
        <?php if ($bus['gps_tracker']): ?><span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg text-xs font-semibold"><i data-lucide="navigation" class="w-3 h-3"></i> GPS</span><?php endif ?>
        <?php if ($bus['wifi']): ?><span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-purple-50 text-purple-700 rounded-lg text-xs font-semibold"><i data-lucide="wifi" class="w-3 h-3"></i> Wi-Fi</span><?php endif ?>
        <?php if ($bus['abs_brakes']): ?><span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold">ABS</span><?php endif ?>
        <?php if ($bus['esp_system']): ?><span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold">ESP</span><?php endif ?>
        <?php if ($bus['tachograph']): ?><span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold">Tachy.</span><?php endif ?>
        <?php foreach (json_decode($bus['equipment_extra'] ?? '[]', true) ?: [] as $eq): ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-cb-bg text-cb-primary rounded-lg text-xs font-semibold"><?= e($eq) ?></span>
        <?php endforeach ?>
      </div>
      <?php if (!empty($bus['gps_device_id']) || !empty($bus['gps_provider'])): ?>
      <div class="border-t border-slate-100 pt-3">
        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1.5">Géolocalisation</div>
        <?php foreach ([['Opérateur', $bus['gps_provider'] ?? null], ['Boîtier', $bus['gps_device_id'] ?? null], ['SIM', $bus['gps_sim_number'] ?? null]] as [$l, $v]): if (!$v) continue; ?>
        <div class="flex justify-between text-sm"><span class="text-slate-400"><?= $l ?></span><span class="font-mono text-xs text-slate-700"><?= e($v) ?></span></div>
        <?php endforeach ?>
      </div>
      <?php endif ?>
    </div>

  </div>

  <!-- ═══════════ ONGLETS HISTORIQUE ═══════════ -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

    <!-- Barre d'onglets -->
    <div class="flex items-center gap-0.5 px-4 pt-4 pb-0 border-b border-slate-100 overflow-x-auto">
      <?php
        // Comptage finances global (toutes sources)
        $financeCount = count($expenses ?? []) + count($fuelLogs ?? []) + count($maintenance ?? [])
            + count($tireRecords ?? []) + count($insuranceRecords ?? []) + count($inspectionRecords ?? [])
            + count($washRecords ?? []) + count($tollRecords ?? []) + count($parkingRecords ?? [])
            + count($fineRecords ?? []);
        $tabs = [
          ['key' => 'voyages',     'icon' => 'route',               'label' => 'Voyages',   'count' => count($trips)],
          ['key' => 'incidents',   'icon' => 'alert-triangle',      'label' => 'Incidents', 'count' => count($incidents)],
          ['key' => 'depenses',    'icon' => 'receipt',              'label' => 'Finances',  'count' => $financeCount],
          ['key' => 'notes',       'icon' => 'message-square-text', 'label' => 'Notes',     'count' => count($notes)],
          ['key' => 'medias',      'icon' => 'images',              'label' => 'Médias',    'count' => count($gallery) + count($docs)],
        ];
        foreach ($tabs as $t):
          $alertTab = ($t['key'] === 'depenses' && $maintenanceOpen > 0)
                   || ($t['key'] === 'incidents'   && $incidentsOpen > 0);
      ?>
      <button @click="tab='<?= $t['key'] ?>'"
              :class="tab==='<?= $t['key'] ?>' ? 'border-cb-primary text-cb-primary font-bold' : 'border-transparent text-slate-500 hover:text-slate-700'"
              class="flex items-center gap-1.5 px-3 pb-3 text-xs border-b-2 transition whitespace-nowrap">
        <i data-lucide="<?= $t['icon'] ?>" class="w-3.5 h-3.5 shrink-0"></i>
        <?= $t['label'] ?>
        <?php if ($t['count'] > 0): ?>
          <span class="<?= $alertTab ? 'bg-rose-500 text-white' : 'bg-slate-100 text-slate-600' ?> text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
            <?= $t['count'] ?>
          </span>
        <?php endif ?>
      </button>
      <?php endforeach ?>
    </div>

    <div class="p-5">

      <!-- ── VOYAGES ── -->
      <div x-show="tab==='voyages'" x-cloak>
        <?php if (empty($trips)): ?>
          <p class="text-center text-slate-400 text-sm py-8"><i data-lucide="route" class="w-8 h-8 mx-auto mb-2 text-slate-200"></i><br>Aucun voyage enregistré.</p>
        <?php else: ?>
        <div class="overflow-x-auto -mx-1">
          <table class="w-full text-sm">
            <thead class="text-[10px] uppercase text-slate-400 bg-slate-50">
              <tr>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-3 py-2 text-left">Code</th>
                <th class="px-3 py-2 text-left">Ligne</th>
                <th class="px-3 py-2 text-left">Chauffeur</th>
                <th class="px-3 py-2 text-left">Statut</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php foreach ($trips as $t):
                $tCls = ['planifie'=>'bg-slate-100 text-slate-600','embarquement'=>'bg-amber-50 text-amber-700','en_route'=>'bg-blue-50 text-blue-700','arrive'=>'bg-emerald-50 text-emerald-700','cloture'=>'bg-emerald-50 text-emerald-700','annule'=>'bg-rose-50 text-rose-700'][$t['status']] ?? 'bg-slate-100';
              ?>
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-2 whitespace-nowrap"><?= e($t['trip_date']) ?> <span class="text-slate-400 text-xs"><?= e(substr($t['departure_scheduled'] ?? '',0,5)) ?></span></td>
                <td class="px-3 py-2 font-mono text-xs"><?= e($t['trip_code']) ?></td>
                <td class="px-3 py-2"><?= e($t['line_name'] ?? '—') ?></td>
                <td class="px-3 py-2"><?= e($t['driver_name'] ?? '—') ?></td>
                <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs <?= $tCls ?>"><?= e(str_replace('_',' ',$t['status'])) ?></span></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <?php endif ?>
      </div>

      <!-- ── MAINTENANCE (fusionné dans Finances) ── -->
      <div x-show="false" x-cloak>

        <!-- Stats maintenance -->
        <?php if (!empty($maintenance)): ?>
        <div class="grid grid-cols-3 gap-3 mb-4">
          <div class="bg-slate-50 rounded-xl p-3 text-center">
            <div class="text-xl font-black text-slate-800"><?= count($maintenance) ?></div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Total</div>
          </div>
          <div class="bg-amber-50 rounded-xl p-3 text-center">
            <div class="text-xl font-black text-amber-700"><?= $maintenanceOpen ?></div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wide">En cours</div>
          </div>
          <div class="bg-rose-50 rounded-xl p-3 text-center">
            <div class="text-xl font-black text-rose-700"><?= number_format((int)$maintenanceCost/1000) ?>K</div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Coût FCFA</div>
          </div>
        </div>
        <?php endif ?>

        <!-- Bouton ajouter -->
        <?php if (can('flotte.maintenance.create')): ?>
        <div class="mb-4">
          <button @click="addOpen=!addOpen"
                  class="flex items-center gap-2 px-4 py-2 bg-cb-primary text-white text-xs font-bold rounded-xl hover:bg-cb-dark transition">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span x-text="addOpen ? 'Annuler' : 'Enregistrer une maintenance'"></span>
          </button>

          <div x-show="addOpen" x-transition class="mt-3 bg-slate-50 border border-slate-200 rounded-xl p-4">
            <form method="post" action="<?= e(url('flotte/maintenance')) ?>" class="space-y-3">
              <?= csrf_field() ?>
              <input type="hidden" name="bus_id" value="<?= $bus['id'] ?>">
              <input type="hidden" name="_next" value="<?= e($busUrl) ?>">

              <!-- Mode saisie -->
              <div class="flex gap-3 text-xs mb-2">
                <label class="flex items-center gap-1.5 cursor-pointer">
                  <input type="radio" name="entry_mode" value="realized" x-model="mode" class="accent-cb-primary"> Réalisée
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer">
                  <input type="radio" name="entry_mode" value="planned" x-model="mode" class="accent-cb-primary"> Planifiée
                </label>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Type *</label>
                  <select name="type" required class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
                    <option value="preventive">Préventive</option>
                    <option value="corrective">Corrective</option>
                  </select>
                </div>
                <div x-show="mode==='realized'">
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Date réalisée</label>
                  <input type="date" name="done_on" value="<?= date('Y-m-d') ?>"
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
                </div>
                <div x-show="mode==='planned'">
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Date prévue *</label>
                  <input type="date" name="scheduled_at"
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
                </div>
              </div>

              <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Description *</label>
                <textarea name="description" required rows="2"
                          class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white resize-none"
                          placeholder="Ex: Vidange moteur + filtre à huile…"></textarea>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div x-show="mode==='realized'">
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Coût réel (FCFA)</label>
                  <input type="number" name="actual_cost" min="0"
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
                </div>
                <div x-show="mode==='planned'">
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Coût estimé (FCFA)</label>
                  <input type="number" name="estimated_cost" min="0"
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
                </div>
                <?php if (!empty($mechanics)): ?>
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Mécanicien</label>
                  <select name="mechanic_id" class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
                    <option value="0">— Non assigné —</option>
                    <?php foreach ($mechanics as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= e($m['last_name'].' '.$m['first_name']) ?></option>
                    <?php endforeach ?>
                  </select>
                </div>
                <?php endif ?>
              </div>

              <button type="submit"
                      class="w-full py-2.5 bg-cb-primary text-white text-sm font-bold rounded-xl hover:bg-cb-dark transition">
                <i data-lucide="save" class="w-4 h-4 inline mr-1"></i>
                <span x-text="mode==='planned' ? 'Planifier la maintenance' : 'Enregistrer la maintenance'"></span>
              </button>
            </form>
          </div>
        </div>
        <?php endif ?>

        <!-- Liste des ordres -->
        <?php if (empty($maintenance)): ?>
          <p class="text-center text-slate-400 text-sm py-8"><i data-lucide="wrench" class="w-8 h-8 mx-auto mb-2 text-slate-200"></i><br>Aucune maintenance enregistrée.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($maintenance as $m):
            $mCls = ['planifie'=>'bg-sky-100 text-sky-700','en_cours'=>'bg-amber-100 text-amber-700','termine'=>'bg-emerald-100 text-emerald-700','annule'=>'bg-slate-100 text-slate-500'][$m['status']] ?? 'bg-slate-100 text-slate-500';
            $mLbl = ['planifie'=>'Planifié','en_cours'=>'En cours','termine'=>'Terminé','annule'=>'Annulé'][$m['status']] ?? $m['status'];
            $tCls = $m['type'] === 'corrective' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700';
            $date = $m['done_at'] ?? $m['scheduled_at'] ?? $m['created_at'];
          ?>
          <div class="border border-slate-200 rounded-xl p-3 flex items-start gap-3">
            <div class="shrink-0 mt-0.5">
              <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full <?= $tCls ?>">
                <?= $m['type'] === 'corrective' ? 'Corrective' : 'Préventive' ?>
              </span>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex justify-between items-start gap-2">
                <p class="text-sm font-semibold text-slate-800"><?= e(mb_substr($m['description'],0,80)) ?></p>
                <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full <?= $mCls ?>"><?= $mLbl ?></span>
              </div>
              <div class="flex items-center gap-3 mt-1 text-xs text-slate-500 flex-wrap">
                <span><i data-lucide="calendar" class="w-3 h-3 inline mr-0.5"></i><?= e(date('d/m/Y', strtotime($date))) ?></span>
                <?php if (!empty($m['mechanic_name'])): ?>
                  <span><i data-lucide="user" class="w-3 h-3 inline mr-0.5"></i><?= e($m['mechanic_name']) ?></span>
                <?php endif ?>
                <?php if (!empty($m['actual_cost'])): ?>
                  <span class="text-rose-600 font-semibold"><?= number_format((int)$m['actual_cost'],0,' ') ?> FCFA</span>
                <?php elseif (!empty($m['estimated_cost'])): ?>
                  <span class="text-slate-400">~<?= number_format((int)$m['estimated_cost'],0,' ') ?> FCFA estimé</span>
                <?php endif ?>
              </div>
            </div>
          </div>
          <?php endforeach ?>
        </div>
        <?php endif ?>
      </div>

      <!-- ── CARBURANT (fusionné dans Finances) ── -->
      <div x-show="false" x-cloak>

        <!-- Stats carburant -->
        <?php if (!empty($fuelLogs)): ?>
        <div class="grid grid-cols-3 gap-3 mb-4">
          <div class="bg-slate-50 rounded-xl p-3 text-center">
            <div class="text-xl font-black text-slate-800"><?= number_format($totalLiters,0,' ') ?></div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Litres total</div>
          </div>
          <div class="bg-amber-50 rounded-xl p-3 text-center">
            <div class="text-xl font-black text-amber-700"><?= number_format((int)$totalFuelCost/1000) ?>K</div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wide">FCFA total</div>
          </div>
          <div class="bg-sky-50 rounded-xl p-3 text-center">
            <?php
              $kmFirst = !empty($fuelLogs) ? min(array_filter(array_column($fuelLogs,'km_at_fill'),fn($v)=>$v>0)) : 0;
              $kmLast  = !empty($fuelLogs) ? max(array_column($fuelLogs,'km_at_fill')) : 0;
              $kmDiff  = ($kmLast - ($kmFirst ?: $kmLast));
              $avgConso = ($kmDiff > 0 && $totalLiters > 0) ? round($totalLiters / $kmDiff * 100, 1) : 0;
            ?>
            <div class="text-xl font-black text-sky-700"><?= $avgConso > 0 ? $avgConso : '—' ?></div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wide">L/100km</div>
          </div>
        </div>
        <?php endif ?>

        <!-- Bouton ajouter -->
        <?php if (can('flotte.view')): ?>
        <div class="mb-4">
          <button @click="addOpen=!addOpen"
                  class="flex items-center gap-2 px-4 py-2 bg-amber-500 text-white text-xs font-bold rounded-xl hover:bg-amber-600 transition">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span x-text="addOpen ? 'Annuler' : 'Enregistrer un plein'"></span>
          </button>

          <div x-show="addOpen" x-transition class="mt-3 bg-slate-50 border border-slate-200 rounded-xl p-4">
            <form method="post" action="<?= e(url('flotte/fuel')) ?>" class="space-y-3">
              <?= csrf_field() ?>
              <input type="hidden" name="bus_id" value="<?= $bus['id'] ?>">
              <input type="hidden" name="_next" value="<?= e($busUrl) ?>">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Litres *</label>
                  <input type="number" name="liters" step="0.1" min="1" required
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white" placeholder="Ex: 120">
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Prix/litre (FCFA) *</label>
                  <input type="number" name="price_per_liter" step="1" min="1" required
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white" placeholder="Ex: 750">
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Km compteur</label>
                  <input type="number" name="km_at_fill" min="0"
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white"
                         value="<?= (int)$bus['km_current'] ?>">
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Station</label>
                  <input type="text" name="station_name" maxlength="100"
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white" placeholder="Ex: Total Yaoundé">
                </div>
              </div>
              <button type="submit"
                      class="w-full py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition">
                <i data-lucide="fuel" class="w-4 h-4 inline mr-1"></i> Enregistrer le plein
              </button>
            </form>
          </div>
        </div>
        <?php endif ?>

        <!-- Liste des pleins -->
        <?php if (empty($fuelLogs)): ?>
          <p class="text-center text-slate-400 text-sm py-8"><i data-lucide="fuel" class="w-8 h-8 mx-auto mb-2 text-slate-200"></i><br>Aucun plein enregistré.</p>
        <?php else: ?>
        <div class="overflow-x-auto -mx-1">
          <table class="w-full text-sm">
            <thead class="text-[10px] uppercase text-slate-400 bg-slate-50">
              <tr>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-3 py-2 text-right">Litres</th>
                <th class="px-3 py-2 text-right">Coût</th>
                <th class="px-3 py-2 text-right">Km</th>
                <th class="px-3 py-2 text-left">Station</th>
                <th class="px-3 py-2 text-left hidden sm:table-cell">Par</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php foreach ($fuelLogs as $fl): ?>
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-2 text-xs whitespace-nowrap"><?= e(date('d/m/Y', strtotime($fl['logged_at']))) ?></td>
                <td class="px-3 py-2 text-right font-semibold text-amber-700"><?= number_format((float)$fl['liters'],1) ?> L</td>
                <td class="px-3 py-2 text-right text-xs font-semibold"><?= number_format((int)$fl['total_cost'],0,' ') ?></td>
                <td class="px-3 py-2 text-right text-xs text-slate-500"><?= $fl['km_at_fill'] ? number_format((int)$fl['km_at_fill'],0,' ') : '—' ?></td>
                <td class="px-3 py-2 text-xs text-slate-600"><?= e($fl['station_name'] ?? '—') ?></td>
                <td class="px-3 py-2 text-xs text-slate-400 hidden sm:table-cell"><?= e($fl['logged_by_name'] ?? '—') ?></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <?php endif ?>
      </div>

      <!-- ── INCIDENTS ── -->
      <div x-show="tab==='incidents'" x-cloak x-data="{ addOpen: false }">

        <!-- Stats incidents -->
        <?php if (!empty($incidents)): ?>
        <div class="grid grid-cols-3 gap-3 mb-4">
          <div class="bg-slate-50 rounded-xl p-3 text-center">
            <div class="text-xl font-black text-slate-800"><?= count($incidents) ?></div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Total</div>
          </div>
          <div class="bg-rose-50 rounded-xl p-3 text-center">
            <div class="text-xl font-black text-rose-700"><?= $incidentsOpen ?></div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Non résolus</div>
          </div>
          <div class="bg-orange-50 rounded-xl p-3 text-center">
            <div class="text-xl font-black text-orange-700"><?= number_format((int)$incidentsCost/1000) ?>K</div>
            <div class="text-[10px] text-slate-400 uppercase tracking-wide">Coût FCFA</div>
          </div>
        </div>
        <?php endif ?>

        <!-- Bouton signaler -->
        <?php if (can('flotte.incidents.create') || can('flotte.maintenance.create')): ?>
        <div class="mb-4">
          <button @click="addOpen=!addOpen"
                  class="flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            <span x-text="addOpen ? 'Annuler' : 'Signaler un incident'"></span>
          </button>

          <div x-show="addOpen" x-transition class="mt-3 bg-slate-50 border border-slate-200 rounded-xl p-4">
            <form method="post" action="<?= e(url('flotte/incidents')) ?>" class="space-y-3">
              <?= csrf_field() ?>
              <input type="hidden" name="subject_type" value="bus">
              <input type="hidden" name="subject_id" value="<?= $bus['id'] ?>">
              <input type="hidden" name="bus_id" value="<?= $bus['id'] ?>">
              <input type="hidden" name="_next" value="<?= e($busUrl) ?>">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Type *</label>
                  <select name="type" required class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
                    <?php foreach (['accident'=>'Accident','panne'=>'Panne','retard'=>'Retard','infraction'=>'Infraction','altercation'=>'Altercation','vol'=>'Vol','autre'=>'Autre'] as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach ?>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Sévérité *</label>
                  <select name="severity" required class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
                    <?php foreach (['mineur'=>'Mineur','modere'=>'Modéré','grave'=>'Grave','critique'=>'Critique'] as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach ?>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Date / heure *</label>
                  <input type="datetime-local" name="occurred_at" required
                         value="<?= date('Y-m-d\TH:i') ?>"
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
                </div>
                <div>
                  <label class="text-xs font-semibold text-slate-600 block mb-1">Lieu</label>
                  <input type="text" name="location" maxlength="150"
                         class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white" placeholder="Ex: Carrefour Nlongkak">
                </div>
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Description *</label>
                <textarea name="description" required rows="2"
                          class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white resize-none"
                          placeholder="Décrivez l'incident…"></textarea>
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-600 block mb-1">Coût estimé (FCFA)</label>
                <input type="number" name="cost_fcfa" min="0"
                       class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white">
              </div>
              <button type="submit"
                      class="w-full py-2.5 bg-rose-600 text-white text-sm font-bold rounded-xl hover:bg-rose-700 transition">
                <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i> Enregistrer l'incident
              </button>
            </form>
          </div>
        </div>
        <?php endif ?>

        <?php if (empty($incidents)): ?>
          <p class="text-center text-slate-400 text-sm py-8"><i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2 text-emerald-200"></i><br>Aucun incident enregistré.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($incidents as $inc):
            $sevCls = ['mineur'=>'bg-slate-100 text-slate-600','modere'=>'bg-amber-100 text-amber-700','grave'=>'bg-orange-100 text-orange-700','critique'=>'bg-rose-100 text-rose-800'][$inc['severity']] ?? 'bg-slate-100';
          ?>
          <div class="border border-slate-200 rounded-xl p-3 flex items-start gap-3 <?= $inc['resolved'] ? 'opacity-60' : '' ?>">
            <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full mt-0.5 <?= $sevCls ?>"><?= e(ucfirst($inc['severity'])) ?></span>
            <div class="flex-1 min-w-0">
              <div class="flex justify-between items-start gap-2">
                <div class="font-semibold text-sm text-slate-800">
                  <?= e(ucfirst($inc['type'])) ?>
                  <?php if (!empty($inc['driver_name'])): ?><span class="text-slate-400 font-normal"> · <?= e($inc['driver_name']) ?></span><?php endif ?>
                </div>
                <span class="text-xs text-slate-400 shrink-0 whitespace-nowrap"><?= e(date('d/m/Y H:i', strtotime($inc['occurred_at']))) ?></span>
              </div>
              <p class="text-sm text-slate-600 mt-0.5"><?= e($inc['description']) ?></p>
              <div class="flex items-center gap-3 mt-1 text-xs flex-wrap">
                <?php if (!empty($inc['location'])): ?><span class="text-slate-400"><i data-lucide="map-pin" class="w-3 h-3 inline mr-0.5"></i><?= e($inc['location']) ?></span><?php endif ?>
                <?php if ((int)$inc['cost_fcfa'] > 0): ?><span class="text-rose-600 font-semibold"><?= number_format((int)$inc['cost_fcfa'],0,' ') ?> FCFA</span><?php endif ?>
                <?php if ($inc['resolved']): ?><span class="text-emerald-600 font-semibold flex items-center gap-1"><i data-lucide="check-circle" class="w-3 h-3"></i> Résolu</span><?php endif ?>
              </div>
            </div>
          </div>
          <?php endforeach ?>
        </div>
        <?php endif ?>
      </div>

      <!-- ── FINANCES (vue unifiée : carburant + maintenance + trésorerie) ── -->
      <div x-show="tab==='depenses'" x-cloak>
        <?php
        // Préparer les items extra pour l'accordéon — toutes les tables spécifiques
        $extraItems = [];

        // fuel_logs → carburant
        foreach ($fuelLogs as $fl) {
            $extraItems[] = ['_source'=>'fuel_log','cat_code'=>'carburant','cat_label'=>'Carburant','cat_color'=>'orange',
                'amount_fcfa'=>(int)$fl['total_cost'],'liters'=>$fl['liters'],'price_per_liter'=>$fl['price_per_liter'],
                'station_name'=>$fl['station_name']??null,'km_at_fill'=>$fl['km_at_fill']??null,
                'created_at'=>$fl['logged_at'],'logged_by_name'=>$fl['logged_by_name']??'','type'=>'decaissement'];
        }
        // maintenance_orders → entretien
        foreach ($maintenance as $mo) {
            $extraItems[] = ['_source'=>'maintenance','cat_code'=>'entretien','cat_label'=>'Entretien véhicule','cat_color'=>'green',
                'amount_fcfa'=>(int)($mo['actual_cost']??$mo['estimated_cost']??0),'maintenance_type'=>$mo['type']??'',
                'description'=>$mo['description']??'','mechanic_name'=>$mo['mechanic_name']??'','status'=>$mo['status']??'',
                'created_at'=>$mo['done_at']??$mo['scheduled_at']??$mo['created_at'],'reference'=>$mo['invoice_ref']??null,'type'=>'decaissement'];
        }
        // tire_records → pneumatique
        foreach (($tireRecords ?? []) as $tr) {
            $extraItems[] = ['_source'=>'tire','cat_code'=>'pneumatique','cat_label'=>'Pneumatiques / pneus','cat_color'=>'slate',
                'amount_fcfa'=>(int)$tr['cost_fcfa'],'position'=>$tr['position']??null,'brand'=>$tr['brand']??null,
                'size'=>$tr['size']??null,'tire_type'=>$tr['tire_type']??'neuf','quantity'=>$tr['quantity']??1,
                'created_at'=>$tr['created_at'],'logged_by_name'=>$tr['logged_by_name']??'','type'=>'decaissement'];
        }
        // insurance_records → assurance
        foreach (($insuranceRecords ?? []) as $ir) {
            $extraItems[] = ['_source'=>'insurance','cat_code'=>'assurance','cat_label'=>'Assurances véhicules','cat_color'=>'green',
                'amount_fcfa'=>(int)$ir['cost_fcfa'],'company'=>$ir['company']??null,'policy_number'=>$ir['policy_number']??null,
                'coverage_type'=>$ir['coverage_type']??'rc','period_start'=>$ir['period_start']??null,'period_end'=>$ir['period_end']??null,
                'created_at'=>$ir['created_at'],'logged_by_name'=>$ir['logged_by_name']??'','type'=>'decaissement'];
        }
        // inspection_records → visite_technique
        foreach (($inspectionRecords ?? []) as $inr) {
            $extraItems[] = ['_source'=>'inspection','cat_code'=>'visite_technique','cat_label'=>'Visite technique','cat_color'=>'green',
                'amount_fcfa'=>(int)$inr['cost_fcfa'],'center'=>$inr['center']??null,'result'=>$inr['result']??'conforme',
                'certificate_number'=>$inr['certificate_number']??null,'next_due'=>$inr['next_due']??null,
                'created_at'=>$inr['inspection_date']??$inr['created_at'],'logged_by_name'=>$inr['logged_by_name']??'','type'=>'decaissement'];
        }
        // wash_records → lavage_bus
        foreach (($washRecords ?? []) as $wr) {
            $extraItems[] = ['_source'=>'wash','cat_code'=>'lavage_bus','cat_label'=>'Lavage de bus','cat_color'=>'blue',
                'amount_fcfa'=>(int)$wr['cost_fcfa'],'wash_type'=>$wr['wash_type']??'complet','location'=>$wr['location']??null,
                'created_at'=>$wr['created_at'],'logged_by_name'=>$wr['logged_by_name']??'','type'=>'decaissement'];
        }
        // toll_records → peage
        foreach (($tollRecords ?? []) as $tlr) {
            $extraItems[] = ['_source'=>'toll','cat_code'=>'peage','cat_label'=>'Péages routiers','cat_color'=>'amber',
                'amount_fcfa'=>(int)$tlr['cost_fcfa'],'toll_name'=>$tlr['toll_name']??null,'route'=>$tlr['route']??null,
                'created_at'=>$tlr['created_at'],'logged_by_name'=>$tlr['logged_by_name']??'','type'=>'decaissement'];
        }
        // parking_records → parking
        foreach (($parkingRecords ?? []) as $pr) {
            $extraItems[] = ['_source'=>'parking','cat_code'=>'parking','cat_label'=>'Frais de parking','cat_color'=>'slate',
                'amount_fcfa'=>(int)$pr['cost_fcfa'],'location'=>$pr['location']??null,'duration_hours'=>$pr['duration_hours']??null,
                'created_at'=>$pr['created_at'],'logged_by_name'=>$pr['logged_by_name']??'','type'=>'decaissement'];
        }
        // fine_records → amende
        foreach (($fineRecords ?? []) as $fr) {
            $extraItems[] = ['_source'=>'fine','cat_code'=>'amende','cat_label'=>'Amendes / contraventions','cat_color'=>'red',
                'amount_fcfa'=>(int)$fr['cost_fcfa'],'infraction_type'=>$fr['infraction_type']??null,'location'=>$fr['location']??null,
                'authority'=>$fr['authority']??null,'is_contested'=>$fr['is_contested']??0,
                'created_at'=>$fr['fine_date']??$fr['created_at'],'logged_by_name'=>$fr['logged_by_name']??'','type'=>'decaissement'];
        }

        $mecList = array_map(fn($m) => ['id'=>(int)$m['id'],'name'=>trim($m['first_name'].' '.$m['last_name'])], $mechanics ?? []);

        $view->include('partials/expense_widget', [
            'expEntityType' => 'bus',
            'expEntityId'   => (int)$bus['id'],
            'expenses'      => $expenses ?? [],
            'expCategories' => $expCategories ?? [],
            'expTotals'     => $expTotals ?? [],
            'expContext'    => [],
            'expExtraItems' => $extraItems,
            'expMechanics'  => $mecList,
            'expBusKm'      => (int)($bus['km_current'] ?? 0),
        ]);
        ?>
      </div>

      <!-- ── NOTES ── -->
      <div x-show="tab==='notes'" x-cloak>
        <form method="post" action="<?= e(url('referentiel/vehicules/'.$bus['id'].'/notes')) ?>" class="space-y-3 mb-5">
          <?= csrf_field() ?>
          <textarea name="content" rows="3" maxlength="2000"
                    placeholder="Ajouter une observation, un suivi, un contrôle…"
                    class="cb-input w-full resize-none"></textarea>
          <div class="flex justify-end">
            <button type="submit"
                    class="px-4 py-2 bg-cb-primary text-white rounded-xl text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-2">
              <i data-lucide="send" class="w-4 h-4"></i> Enregistrer
            </button>
          </div>
        </form>
        <?php if (empty($notes)): ?>
          <p class="text-center text-slate-400 text-sm py-4 border-t border-slate-100">Aucune note pour le moment.</p>
        <?php else: ?>
        <div class="space-y-3 border-t border-slate-100 pt-4">
          <?php foreach ($notes as $note):
            $currentUser = auth();
            $canDelete = (int)($currentUser['id'] ?? 0) === (int)$note['author_id']
                      || in_array($currentUser['role'] ?? '', ['admin','superadmin'], true);
          ?>
          <div class="flex gap-3 p-4 bg-slate-50 rounded-xl border border-slate-100">
            <div class="w-9 h-9 rounded-full bg-cb-primary text-white flex items-center justify-center font-bold text-sm shrink-0">
              <?= e(strtoupper(substr($note['author_name'] ?? '?', 0, 1))) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <span class="font-semibold text-sm text-slate-800"><?= e($note['author_name'] ?? 'Inconnu') ?></span>
                  <span class="text-xs text-slate-400 ml-2 capitalize"><?= e($note['author_role'] ?? '') ?></span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <time class="text-xs text-slate-400"><?= e(date('d/m/Y H:i', strtotime($note['created_at']))) ?></time>
                  <?php if ($canDelete): ?>
                  <form method="post" action="<?= e(url('referentiel/vehicules/'.$bus['id'].'/notes/'.$note['id'].'/delete')) ?>"
                        onsubmit="return confirm('Supprimer cette note ?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="text-slate-300 hover:text-rose-500 transition p-1 rounded">
                      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                  </form>
                  <?php endif ?>
                </div>
              </div>
              <p class="text-sm text-slate-700 mt-1.5 whitespace-pre-line"><?= e($note['content']) ?></p>
            </div>
          </div>
          <?php endforeach ?>
        </div>
        <?php endif ?>
      </div>

      <!-- ── MÉDIAS ── -->
      <div x-show="tab==='medias'" x-cloak>
        <?php if (!empty($gallery)): ?>
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
          <i data-lucide="images" class="w-4 h-4 text-cb-primary"></i> Photos (<?= count($gallery) ?>)
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-5">
          <?php foreach ($gallery as $img): ?>
          <a href="<?= e($img['url']) ?>" target="_blank" class="block rounded-xl overflow-hidden aspect-[4/3] hover:opacity-90 transition">
            <img src="<?= e($img['thumb_url']) ?>" alt="" class="w-full h-full object-cover">
          </a>
          <?php endforeach ?>
        </div>
        <?php endif ?>

        <?php if (!empty($docs)): ?>
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
          <i data-lucide="paperclip" class="w-4 h-4 text-cb-primary"></i> Documents (<?= count($docs) ?>)
        </h3>
        <div class="divide-y divide-slate-100">
          <?php foreach ($docs as $doc): ?>
          <div class="flex items-center gap-3 py-3">
            <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
              <i data-lucide="<?= e($doc['icon']) ?>" class="w-4 h-4 text-slate-500"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-slate-800 truncate"><?= e($doc['file_name']) ?></div>
              <div class="text-xs text-slate-400"><?= e($doc['human_size']) ?></div>
            </div>
            <a href="<?= e($doc['url']) ?>" target="_blank"
               class="shrink-0 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs hover:bg-slate-50 transition flex items-center gap-1">
              <i data-lucide="download" class="w-3.5 h-3.5"></i> Télécharger
            </a>
          </div>
          <?php endforeach ?>
        </div>
        <?php endif ?>

        <?php if (empty($gallery) && empty($docs)): ?>
        <p class="text-center text-slate-400 text-sm py-8"><i data-lucide="images" class="w-8 h-8 mx-auto mb-2 text-slate-200"></i><br>Aucun média associé.</p>
        <?php endif ?>
      </div>

    </div>
  </div>

</div>
<?php $view->end() ?>
