<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
use CityBus\Models\Trip;
use CityBus\Services\TripStateMachine;

$statusColors = [
  'planifie'     => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400'],
  'valide'       => ['bg' => 'bg-cyan-100',  'text' => 'text-cyan-700',  'dot' => 'bg-cyan-500'],
  'embarquement' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500'],
  'en_route'     => ['bg' => 'bg-cb-primary','text' => 'text-white',     'dot' => 'bg-white'],
  'arrive'       => ['bg' => 'bg-emerald-100','text' => 'text-emerald-700','dot' => 'bg-emerald-500'],
  'cloture'      => ['bg' => 'bg-emerald-200','text' => 'text-emerald-800','dot' => 'bg-emerald-600'],
  'incident'     => ['bg' => 'bg-rose-100',  'text' => 'text-rose-700',  'dot' => 'bg-rose-500'],
  'retourne'     => ['bg' => 'bg-orange-100','text' => 'text-orange-700','dot' => 'bg-orange-500'],
  'litige'       => ['bg' => 'bg-purple-100','text' => 'text-purple-700','dot' => 'bg-purple-500'],
  'annule'       => ['bg' => 'bg-rose-100',  'text' => 'text-rose-600',  'dot' => 'bg-rose-400'],
];
$sm = $statusColors[$trip['status']] ?? $statusColors['planifie'];

$totalSeats = (int)($trip['bus_seats'] ?? 0);
$soldCount  = count($bookedSeats);
$occupancy  = $totalSeats > 0 ? round($soldCount / $totalSeats * 100) : 0;
$revenue    = (int)($trip['total_revenue'] ?? 0);
$delay      = (int)($trip['delay_minutes'] ?? 0);
$tolerance  = \CityBus\Core\Setting::getInt('voyage.delay_tolerance_minutes', 15);
$booked     = array_flip(array_map('intval', $bookedSeats));

$passengerTickets = array_values(array_filter($tickets, fn($t) => in_array($t['ticket_type'] ?? '', ['passager','arret_route','finale'])));
$activeTab = $activeTab ?? 'details';
?>
<?php $view->start('content') ?>

<div class="space-y-5" x-data="{ tab: '<?= e($activeTab) ?>' }">

  <!-- ─────────── BREADCRUMB + HEAD ─────────── -->
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('voyages')) ?>" class="hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Voyages
    </a>
    <span>/</span>
    <span class="text-slate-800 font-mono font-semibold"><?= e($trip['trip_code']) ?></span>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
    <div class="flex items-start justify-between flex-wrap gap-4">
      <div class="flex items-start gap-4">
        <div class="w-14 h-14 rounded-2xl bg-cb-bg flex items-center justify-center shrink-0">
          <i data-lucide="bus" class="w-7 h-7 text-cb-primary"></i>
        </div>
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-2xl font-bold text-slate-900"><?= e($trip['line_code']) ?> · <?= e($trip['line_name']) ?></h1>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?= $sm['bg'] ?> <?= $sm['text'] ?>">
              <span class="w-1.5 h-1.5 rounded-full <?= $sm['dot'] ?>"></span>
              <?= e(Trip::STATUSES[$trip['status']] ?? $trip['status']) ?>
            </span>
            <?php if (!empty($manifestLocked)): ?>
              <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold bg-slate-900 text-white">
                <i data-lucide="lock" class="w-3 h-3"></i> Verrouillé
              </span>
            <?php endif ?>
          </div>
          <p class="text-sm text-slate-500 mt-1">
            <?= e(date('d/m/Y', strtotime($trip['trip_date']))) ?>
            · Départ <span class="font-mono"><?= e(substr($trip['departure_scheduled'], 0, 5)) ?></span>
            <?php if (!empty($trip['departure_actual'])): ?>
              · Réel <span class="font-mono <?= $delay > $tolerance ? 'text-rose-600 font-bold' : ($delay > 0 ? 'text-amber-600' : 'text-emerald-600') ?>">
                <?= e(substr($trip['departure_actual'], 0, 5)) ?>
                <?php if ($delay > 0): ?>(+<?= $delay ?>min)<?php endif ?>
              </span>
            <?php endif ?>
          </p>
          <p class="text-xs text-slate-400 font-mono mt-1"><?= e($trip['trip_code']) ?></p>
        </div>
      </div>

      <div class="flex gap-2 flex-wrap">
        <?php if (can('billetterie.create') && !$manifestLocked && !Trip::isTerminal($trip['status'])): ?>
          <a href="<?= e(url('billetterie/sale/' . $trip['id'])) ?>"
             class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-secondary shadow-soft">
            <i data-lucide="ticket" class="w-4 h-4"></i> Vendre
          </a>
        <?php endif ?>
        <a href="<?= e(url('voyages/' . $trip['id'] . '/manifest')) ?>"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
          <i data-lucide="printer" class="w-4 h-4"></i> Manifeste
        </a>
        <?php if (can('voyages.inventory.view')): ?>
          <a href="<?= e(url('voyages/' . $trip['id'] . '/inventory')) ?>"
             class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
            <i data-lucide="layers" class="w-4 h-4"></i> Inventaire
          </a>
        <?php endif ?>
        <a href="<?= e(url('voyages/' . $trip['id'] . '/tracking')) ?>"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
          <i data-lucide="map" class="w-4 h-4"></i> Suivi
        </a>
        <?php if (can('voyages.briefing.view')): ?>
          <a href="<?= e(url('voyages/' . $trip['id'] . '/briefing')) ?>"
             class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
            <i data-lucide="clipboard-list" class="w-4 h-4"></i> Briefing
          </a>
        <?php endif ?>
        <?php if (can('voyages.edit') && !Trip::isTerminal($trip['status'])): ?>
          <a href="<?= e(url('voyages/' . $trip['id'] . '/edit')) ?>"
             class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
            <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
          </a>
        <?php endif ?>
      </div>
    </div>
  </div>

  <!-- ─────────── KPI CARDS ─────────── -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Véhicule</div>
      <div class="text-lg font-bold text-slate-900 mt-1"><?= e($trip['bus_code']) ?></div>
      <div class="text-xs text-slate-500"><?= e($trip['bus_plate']) ?> · <?= (int)$trip['bus_seats'] ?> places</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Chauffeur</div>
      <div class="text-base font-bold text-slate-900 mt-1 truncate">
        <?= e(trim(($trip['driver_first'] ?? '') . ' ' . ($trip['driver_last'] ?? ''))) ?: '—' ?>
      </div>
      <div class="text-xs text-slate-500"><?= e($trip['driver_matricule'] ?? '') ?></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Charge</div>
      <div class="text-3xl font-bold mt-1 <?= $occupancy >= 80 ? 'text-emerald-600' : ($occupancy >= 50 ? 'text-amber-600' : 'text-slate-900') ?>"><?= $occupancy ?>%</div>
      <div class="text-xs text-slate-500"><?= $soldCount ?> / <?= $totalSeats ?> places</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Recettes</div>
      <div class="text-2xl font-bold text-emerald-600 mt-1"><?= e(fcfa($revenue)) ?></div>
    </div>
  </div>

  <!-- ─────────── ONGLETS ─────────── -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">

    <!-- Tabs nav -->
    <div class="border-b border-slate-100 overflow-x-auto">
      <nav class="flex gap-1 px-3 py-2 min-w-max">
        <?php
        $tabs = [
          'details'    => ['label' => 'Détails',    'icon' => 'layout-dashboard', 'count' => null],
          'passengers' => ['label' => 'Passagers',  'icon' => 'users',            'count' => count($passengerTickets)],
          'cargo'      => ['label' => 'Fret',       'icon' => 'package',          'count' => count($parcels ?? [])],
          'crew'       => ['label' => 'Équipage',   'icon' => 'user-cog',         'count' => count($crew ?? []) + (!empty($trip['driver_first']) ? 1 : 0) + (!empty($trip['convoyeur_first']) ? 1 : 0)],
          'finances'   => ['label' => 'Finances',   'icon' => 'receipt',          'count' => count($expenses ?? [])],
          'audit'      => ['label' => 'Historique', 'icon' => 'history',          'count' => count($statusTimeline ?? [])],
        ];
        ?>
        <?php foreach ($tabs as $key => $t): ?>
          <button @click="tab='<?= e($key) ?>'"
            :class="tab === '<?= e($key) ?>' ? 'bg-cb-primary text-white' : 'text-slate-600 hover:bg-slate-50'"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold whitespace-nowrap">
            <i data-lucide="<?= e($t['icon']) ?>" class="w-3.5 h-3.5"></i>
            <?= e($t['label']) ?>
            <?php if ($t['count'] !== null && $t['count'] > 0): ?>
              <span :class="tab === '<?= e($key) ?>' ? 'bg-white/20' : 'bg-slate-200 text-slate-600'"
                    class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"><?= (int)$t['count'] ?></span>
            <?php endif ?>
          </button>
        <?php endforeach ?>
      </nav>
    </div>

    <!-- TAB: Détails -->
    <div x-show="tab === 'details'" class="p-6 space-y-6">
      <div class="grid md:grid-cols-2 gap-6">
        <div>
          <h3 class="text-sm font-bold text-slate-700 mb-3">Trajet</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-slate-500">Origine</dt>
              <dd class="font-medium text-slate-900"><?= e($trip['departure_city_name']) ?></dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Destination</dt>
              <dd class="font-medium text-slate-900"><?= e($trip['arrival_city_name']) ?></dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Distance</dt>
              <dd class="font-mono"><?= number_format((float)($trip['distance_km'] ?? 0), 0, ',', ' ') ?> km</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Durée prévue</dt>
              <dd><?= number_format((float)($trip['duration_hours'] ?? 0), 1, ',', '') ?> h</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Type</dt>
              <dd class="font-medium"><?= e(Trip::TYPES[$trip['trip_type'] ?? 'commercial'] ?? '—') ?></dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Priorité</dt>
              <dd class="font-medium"><?= e(Trip::PRIORITIES[$trip['priority'] ?? 'normale'] ?? '—') ?></dd>
            </div>
          </dl>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-700 mb-3">Détails opérationnels</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-slate-500">Km départ</dt>
              <dd class="font-mono"><?= !empty($trip['mileage_start']) ? number_format((int)$trip['mileage_start'], 0, ',', ' ') . ' km' : '—' ?></dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Km arrivée</dt>
              <dd class="font-mono"><?= !empty($trip['mileage_end']) ? number_format((int)$trip['mileage_end'], 0, ',', ' ') . ' km' : '—' ?></dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Météo</dt>
              <dd><?= e($trip['weather_conditions'] ?? '—') ?></dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Réf. externe</dt>
              <dd class="font-mono text-xs"><?= e($trip['external_reference'] ?? '—') ?></dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Plan de sièges -->
      <?php if ($totalSeats > 0): ?>
        <div>
          <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="armchair" class="w-4 h-4 text-cb-primary"></i>
            Plan des sièges
            <span class="text-xs font-normal text-slate-400">(<?= $soldCount ?>/<?= $totalSeats ?> occupés)</span>
          </h3>
          <div class="grid grid-cols-8 sm:grid-cols-12 lg:grid-cols-15 gap-1.5 max-w-3xl">
            <?php for ($i = 1; $i <= $totalSeats; $i++): $isBooked = isset($booked[$i]); ?>
              <div class="aspect-square rounded-md flex items-center justify-center text-xs font-bold border <?= $isBooked
                ? 'bg-cb-primary text-white border-cb-primary'
                : 'bg-slate-50 text-slate-400 border-slate-200' ?>">
                <?= $i ?>
              </div>
            <?php endfor ?>
          </div>
        </div>
      <?php endif ?>

      <!-- Changement de statut via boutons -->
      <?php if (can('voyages.update') && !Trip::isTerminal($trip['status'])): ?>
        <?php
        $allowedTripTransitions = TripStateMachine::allowedFrom($trip['status']);
        $tripTransitionMeta = [
            'planifie'     => ['icon' => 'calendar',       'cls' => 'bg-slate-600 hover:bg-slate-700 text-white'],
            'valide'       => ['icon' => 'check-circle',   'cls' => 'bg-cyan-600 hover:bg-cyan-700 text-white'],
            'embarquement' => ['icon' => 'log-in',         'cls' => 'bg-amber-600 hover:bg-amber-700 text-white'],
            'en_route'     => ['icon' => 'truck',          'cls' => 'bg-cb-primary hover:bg-cb-dark text-white'],
            'arrive'       => ['icon' => 'map-pin',        'cls' => 'bg-emerald-600 hover:bg-emerald-700 text-white'],
            'cloture'      => ['icon' => 'lock',           'cls' => 'bg-emerald-700 hover:bg-emerald-800 text-white'],
            'incident'     => ['icon' => 'alert-triangle', 'cls' => 'bg-rose-600 hover:bg-rose-700 text-white'],
            'retourne'     => ['icon' => 'undo-2',         'cls' => 'bg-orange-600 hover:bg-orange-700 text-white'],
            'litige'       => ['icon' => 'shield-alert',   'cls' => 'bg-purple-600 hover:bg-purple-700 text-white'],
            'annule'       => ['icon' => 'x-circle',       'cls' => 'border border-rose-300 text-rose-600 hover:bg-rose-50'],
        ];
        // Transitions nécessitant un motif obligatoire
        $needsReason = ['annule', 'incident', 'retourne', 'litige'];
        ?>
        <?php if (!empty($allowedTripTransitions)): ?>
        <div class="border-t border-slate-100 pt-6" x-data="{ reasonTarget: '', reasonText: '' }">
          <h3 class="text-sm font-bold text-slate-700 mb-3">Avancer le statut du voyage</h3>
          <div class="flex flex-wrap gap-2 mb-3">
            <?php foreach ($allowedTripTransitions as $nextSt):
                $tMeta = $tripTransitionMeta[$nextSt] ?? ['icon' => 'arrow-right', 'cls' => 'bg-slate-600 hover:bg-slate-700 text-white'];
                $tLabel = Trip::STATUSES[$nextSt] ?? $nextSt;
                $reqReason = in_array($nextSt, $needsReason, true);
            ?>
            <?php if ($reqReason): ?>
              <button type="button"
                      @click="reasonTarget = '<?= e($nextSt) ?>'; $nextTick(() => $refs.reasonInput?.focus())"
                      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition <?= $tMeta['cls'] ?>">
                <i data-lucide="<?= e($tMeta['icon']) ?>" class="w-4 h-4"></i> <?= e($tLabel) ?>
              </button>
            <?php else: ?>
              <form method="post" action="<?= e(url('voyages/' . $trip['id'] . '/status')) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="<?= e($nextSt) ?>">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition <?= $tMeta['cls'] ?>">
                  <i data-lucide="<?= e($tMeta['icon']) ?>" class="w-4 h-4"></i> <?= e($tLabel) ?>
                </button>
              </form>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>

          <!-- Formulaire de motif (affiché si une transition le requiert) -->
          <form x-show="reasonTarget" x-cloak x-transition
                method="post" action="<?= e(url('voyages/' . $trip['id'] . '/status')) ?>"
                class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="status" :value="reasonTarget">
            <p class="text-sm text-slate-600 font-medium">
              Motif obligatoire pour cette transition
              <span class="font-bold" x-text="reasonTarget"></span>
            </p>
            <input x-ref="reasonInput" name="reason" x-model="reasonText" required minlength="5"
                   placeholder="Raison du changement de statut…"
                   class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:ring-1 focus:ring-cb-primary/30 outline-none">
            <div class="flex gap-2">
              <button type="button" @click="reasonTarget = ''; reasonText = ''"
                      class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-100 transition">
                Annuler
              </button>
              <button type="submit" :disabled="reasonText.trim().length < 5"
                      class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium disabled:opacity-40 transition">
                Confirmer
              </button>
            </div>
          </form>
        </div>
        <?php endif; ?>
      <?php endif ?>
    </div>

    <!-- TAB: Passagers -->
    <div x-show="tab === 'passengers'" class="p-6">
      <?php if ($passengerTickets): ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="text-left px-3 py-2 font-semibold uppercase text-xs">Ticket</th>
                <th class="text-center px-3 py-2 font-semibold uppercase text-xs">Siège</th>
                <th class="text-left px-3 py-2 font-semibold uppercase text-xs">Passager</th>
                <th class="text-left px-3 py-2 font-semibold uppercase text-xs">Téléphone</th>
                <th class="text-right px-3 py-2 font-semibold uppercase text-xs">Prix</th>
                <th class="text-center px-3 py-2 font-semibold uppercase text-xs">Statut</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($passengerTickets as $tk): ?>
                <tr class="hover:bg-slate-50">
                  <td class="px-3 py-2 font-mono text-xs">
                    <a href="<?= e(url('billetterie/' . $tk['id'])) ?>" class="text-cb-primary hover:underline"><?= e($tk['ticket_number']) ?></a>
                  </td>
                  <td class="px-3 py-2 text-center font-bold"><?= e($tk['seat_number'] ?? '—') ?></td>
                  <td class="px-3 py-2"><?= e($tk['passenger_name'] ?? '—') ?></td>
                  <td class="px-3 py-2 font-mono text-xs text-slate-500"><?= e($tk['passenger_phone'] ?? '') ?></td>
                  <td class="px-3 py-2 text-right font-semibold"><?= e(fcfa((int)$tk['price_fcfa'])) ?></td>
                  <td class="px-3 py-2 text-center">
                    <span class="px-2 py-0.5 rounded text-xs <?= match($tk['status'] ?? '') {
                      'emis' => 'bg-amber-100 text-amber-700',
                      'controle','utilise','embarque' => 'bg-emerald-100 text-emerald-700',
                      'annule' => 'bg-rose-100 text-rose-700',
                      default => 'bg-slate-100 text-slate-600'
                    } ?>"><?= e($tk['status'] ?? '') ?></span>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-center text-slate-400 py-8">Aucun passager pour ce voyage.</p>
      <?php endif ?>
    </div>

    <!-- TAB: Cargo -->
    <div x-show="tab === 'cargo'" class="p-6">
      <?php if (!empty($parcels)): ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="text-left px-3 py-2 font-semibold uppercase text-xs">N°</th>
                <th class="text-left px-3 py-2 font-semibold uppercase text-xs">Trajet</th>
                <th class="text-left px-3 py-2 font-semibold uppercase text-xs">Destinataire</th>
                <th class="text-right px-3 py-2 font-semibold uppercase text-xs">Poids</th>
                <th class="text-right px-3 py-2 font-semibold uppercase text-xs">Prix</th>
                <th class="text-center px-3 py-2 font-semibold uppercase text-xs">Statut</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($parcels as $p): ?>
                <tr class="hover:bg-slate-50">
                  <td class="px-3 py-2 font-mono text-xs">
                    <a href="<?= e(url('cargo/parcels/' . $p['id'])) ?>" class="text-cb-primary hover:underline"><?= e($p['parcel_number']) ?></a>
                  </td>
                  <td class="px-3 py-2 text-xs"><?= e($p['origin_agency'] ?? '?') ?> → <?= e($p['destination_agency'] ?? '?') ?></td>
                  <td class="px-3 py-2"><?= e($p['recipient_name']) ?></td>
                  <td class="px-3 py-2 text-right font-mono"><?= number_format((float)$p['weight_kg'], 1, ',', '') ?> kg</td>
                  <td class="px-3 py-2 text-right font-semibold"><?= e(fcfa((int)$p['total_price_fcfa'])) ?></td>
                  <td class="px-3 py-2 text-center">
                    <span class="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-600"><?= e($p['status']) ?></span>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-center text-slate-400 py-8">Aucun colis sur ce voyage.</p>
      <?php endif ?>
    </div>

    <!-- TAB: Équipage -->
    <div x-show="tab === 'crew'" class="p-6">
      <?php
        // Membres principaux : driver + convoyeur depuis trips
        $primaryCrew = [];
        if (!empty($trip['driver_first'])) {
            $primaryCrew[] = [
                'first_name' => $trip['driver_first'],
                'last_name'  => $trip['driver_last'] ?? '',
                'matricule'  => $trip['driver_matricule'] ?? '',
                'phone'      => $trip['driver_phone'] ?? '',
                'role'       => 'chauffeur',
                '_primary'   => true,
            ];
        }
        if (!empty($trip['convoyeur_first'])) {
            $primaryCrew[] = [
                'first_name' => $trip['convoyeur_first'],
                'last_name'  => $trip['convoyeur_last'] ?? '',
                'matricule'  => $trip['convoyeur_matricule'] ?? '',
                'phone'      => $trip['convoyeur_phone'] ?? '',
                'role'       => 'convoyeur',
                '_primary'   => true,
            ];
        }
        $allCrew = array_merge($primaryCrew, $crew ?? []);
      ?>
      <?php if (!empty($allCrew)): ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <?php foreach ($allCrew as $c): ?>
            <div class="flex items-center gap-3 p-3 rounded-xl <?= !empty($c['_primary']) ? 'bg-cb-bg/60 border border-cb-primary/20' : 'bg-slate-50 border border-slate-100' ?>">
              <div class="w-10 h-10 rounded-full bg-cb-primary/10 flex items-center justify-center text-cb-primary font-bold text-sm">
                <?= e(strtoupper(mb_substr($c['first_name'] ?? '?', 0, 1) . mb_substr($c['last_name'] ?? '', 0, 1))) ?>
              </div>
              <div class="min-w-0 flex-1">
                <div class="font-semibold text-sm truncate"><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></div>
                <div class="text-xs text-slate-500 capitalize"><?= e($c['role'] ?? '') ?><?= !empty($c['matricule']) ? ' · ' . e($c['matricule']) : '' ?></div>
                <?php if (!empty($c['phone'])): ?>
                  <div class="text-xs text-slate-400 font-mono"><?= e($c['phone']) ?></div>
                <?php endif ?>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      <?php else: ?>
        <p class="text-center text-slate-400 py-8">Aucun équipage assigné.</p>
      <?php endif ?>
    </div>

    <!-- TAB: Finances (dépenses bidirectionnelles) -->
    <div x-show="tab === 'finances'" class="p-6">
      <?php
      // Préparer les items extra pour le voyage
      $tripExtraItems = [];
      foreach (($tollRecords ?? []) as $tlr) {
          $tripExtraItems[] = ['_source'=>'toll','cat_code'=>'peage','cat_label'=>'Péages routiers','cat_color'=>'amber',
              'amount_fcfa'=>(int)$tlr['cost_fcfa'],'toll_name'=>$tlr['toll_name']??null,'route'=>$tlr['route']??null,
              'created_at'=>$tlr['created_at'],'logged_by_name'=>$tlr['logged_by_name']??'','type'=>'decaissement'];
      }
      foreach (($parkingRecords ?? []) as $pr) {
          $tripExtraItems[] = ['_source'=>'parking','cat_code'=>'parking','cat_label'=>'Frais de parking','cat_color'=>'slate',
              'amount_fcfa'=>(int)$pr['cost_fcfa'],'location'=>$pr['location']??null,'duration_hours'=>$pr['duration_hours']??null,
              'created_at'=>$pr['created_at'],'logged_by_name'=>$pr['logged_by_name']??'','type'=>'decaissement'];
      }
      foreach (($washRecords ?? []) as $wr) {
          $tripExtraItems[] = ['_source'=>'wash','cat_code'=>'lavage_bus','cat_label'=>'Lavage de bus','cat_color'=>'blue',
              'amount_fcfa'=>(int)$wr['cost_fcfa'],'wash_type'=>$wr['wash_type']??'complet','location'=>$wr['location']??null,
              'created_at'=>$wr['created_at'],'logged_by_name'=>$wr['logged_by_name']??'','type'=>'decaissement'];
      }
      foreach (($fineRecords ?? []) as $fr) {
          $tripExtraItems[] = ['_source'=>'fine','cat_code'=>'amende','cat_label'=>'Amendes / contraventions','cat_color'=>'red',
              'amount_fcfa'=>(int)$fr['cost_fcfa'],'infraction_type'=>$fr['infraction_type']??null,'location'=>$fr['location']??null,
              'authority'=>$fr['authority']??null,'is_contested'=>$fr['is_contested']??0,
              'created_at'=>$fr['fine_date']??$fr['created_at'],'logged_by_name'=>$fr['logged_by_name']??'','type'=>'decaissement'];
      }
      foreach (($tripCompensations ?? []) as $dc) {
          $cLabels = ['prime_journaliere'=>'Prime journalière','prime_autre'=>'Autres primes','indemnite'=>'Indemnités','commission_agent'=>'Commissions'];
          $cColors = ['prime_journaliere'=>'blue','prime_autre'=>'violet','indemnite'=>'blue','commission_agent'=>'violet'];
          $tripExtraItems[] = ['_source'=>'compensation','cat_code'=>$dc['comp_type'],
              'cat_label'=>$cLabels[$dc['comp_type']]??$dc['comp_type'],'cat_color'=>$cColors[$dc['comp_type']]??'slate',
              'amount_fcfa'=>(int)$dc['cost_fcfa'],'comp_type'=>$dc['comp_type'],'reason'=>$dc['reason']??null,
              'created_at'=>$dc['created_at'],'logged_by_name'=>$dc['logged_by_name']??'','type'=>'decaissement'];
      }
      $view->include('partials/expense_widget', [
          'expEntityType' => 'trip',
          'expEntityId'   => (int)$trip['id'],
          'expenses'      => $expenses ?? [],
          'expCategories' => $expCategories ?? [],
          'expTotals'     => $expTotals ?? [],
          'expContext'    => [
              'bus_id'    => (int)($trip['bus_id'] ?? 0),
              'driver_id' => (int)($trip['driver_id'] ?? 0),
          ],
          'expExtraItems' => $tripExtraItems,
      ]);
      ?>
    </div>

    <!-- TAB: Historique -->
    <div x-show="tab === 'audit'" class="p-6">
      <?php if (!empty($statusTimeline)): ?>
        <div class="space-y-3">
          <?php foreach ($statusTimeline as $s):
            $sm2 = $statusColors[$s['to_status']] ?? null;
          ?>
            <div class="flex gap-3">
              <div class="w-2 h-2 rounded-full <?= $sm2 ? $sm2['dot'] : 'bg-slate-400' ?> mt-2 shrink-0"></div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm">
                  <?= e(Trip::STATUSES[$s['to_status']] ?? $s['to_status']) ?>
                  <?php if ($s['from_status']): ?>
                    <span class="text-xs text-slate-400 font-normal">depuis <?= e(Trip::STATUSES[$s['from_status']] ?? $s['from_status']) ?></span>
                  <?php endif ?>
                </div>
                <?php if (!empty($s['reason'])): ?>
                  <p class="text-sm text-slate-600 mt-0.5"><?= e($s['reason']) ?></p>
                <?php endif ?>
                <p class="text-xs text-slate-400 mt-0.5">
                  <?= e(date('d/m/Y H:i', strtotime((string)$s['changed_at']))) ?>
                  <?php if (!empty($s['author'])): ?> · par <?= e(trim((string)$s['author'])) ?><?php endif ?>
                </p>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      <?php else: ?>
        <p class="text-center text-slate-400 py-8">Aucun événement enregistré.</p>
      <?php endif ?>
    </div>

  </div>

</div>

<?php $view->end() ?>
