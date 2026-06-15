<?php
/** @var \CityBus\Core\View $view */
/** @var array  $trip */
/** @var array  $bookedSeats */
/** @var array  $register */
/** @var array  $stops */
/** @var array  $availableScopes */
/** @var array  $ticketTypes */
/** @var array  $passengerCategories */
/** @var array  $travelClasses */
/** @var array  $baggageTariffs */

$view->extends('layouts/app');

$totalSeats   = (int)$trip['bus_seats'];
$soldCount    = count($bookedSeats);
$occupancyPct = $totalSeats > 0 ? round(($soldCount / $totalSeats) * 100) : 0;
$isNearFull   = $occupancyPct >= 80;
$isFull       = $soldCount >= $totalSeats;
$bookedSet    = array_flip(array_map('intval', $bookedSeats));
$scopesJson   = json_encode(array_values($availableScopes), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$btJson       = json_encode(array_values($baggageTariffs),  JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$fretCatsJson = json_encode(array_values($fretCategories ?? []), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$firstStop    = !empty($stops) ? $stops[0] : null;
$lastStop     = !empty($stops) ? $stops[count($stops) - 1] : null;
$firstStopLabel = $firstStop ? e($firstStop['name']) : 'Départ de ligne';
$lastStopLabel  = $lastStop  ? e($lastStop['name'])  : 'Terminus de ligne';
$resolveUrl   = e(url('billetterie/resolve-tariff'));
$calcUrl      = e(url('billetterie-bagages/calc'));
$lineId       = (int)$trip['line_id'];
$tripId       = (int)$trip['id'];
$tripDate     = e($trip['trip_date']);
$storeUrl     = e(url('billetterie'));
?>
<?php $view->start('content') ?>

<div
    x-data="ticketSale()"
    x-init="init()"
    class="max-w-7xl mx-auto px-4 py-6 space-y-4"
>

    <!-- ── HEADER voyage ─────────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-200 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-cb-bg text-cb-primary">
                <i data-lucide="bus" class="w-5 h-5"></i>
            </span>
            <div>
                <p class="font-bold text-gray-900 text-lg">
                    <?= e($trip['line_code']) ?> — <?= e($trip['line_name']) ?>
                </p>
                <p class="text-sm text-gray-500">
                    Bus <?= e($trip['bus_code']) ?> &bull;
                    <?= date('d/m/Y', strtotime($trip['trip_date'])) ?> à
                    <?= substr((string)($trip['departure_scheduled'] ?? ''), 0, 5) ?>
                    &bull; <?= $totalSeats ?> sièges
                </p>
            </div>
        </div>
        <?php if (!$register): ?>
        <div class="flex items-center gap-2 bg-amber-50 text-amber-700 border border-amber-200 rounded-xl px-4 py-2 text-sm font-medium">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            Caisse non ouverte — vente possible mais non liée à une caisse
        </div>
        <?php endif ?>
    </div>

    <!-- ── FLASH ──────────────────────────────────────────────────────────── -->
    <?php
    $flashes = \CityBus\Core\Session::pullFlash();
    foreach ($flashes as $type => $msgs):
        $cls = $type === 'success' ? 'bg-green-50 border-green-200 text-green-800'
             : ($type === 'danger'  ? 'bg-red-50 border-red-200 text-red-800'
                                    : 'bg-amber-50 border-amber-200 text-amber-800');
    ?>
    <div class="rounded-xl border px-4 py-3 text-sm <?= $cls ?>">
        <?php foreach ($msgs as $m): ?><p><?= e($m) ?></p><?php endforeach ?>
    </div>
    <?php endforeach ?>

    <!-- ── AVERTISSEMENT CAPACITÉ ────────────────────────────────────────── -->
    <?php if ($isFull): ?>
    <div class="rounded-xl border px-4 py-3 text-sm bg-red-50 border-red-200 text-red-800 flex items-start gap-3">
        <i data-lucide="alert-octagon" class="w-5 h-5 text-red-600 shrink-0 mt-0.5"></i>
        <div>
            <p class="font-semibold">Bus complet — <?= $soldCount ?>/<?= $totalSeats ?> sièges vendus (<?= $occupancyPct ?>%)</p>
            <p class="text-xs text-red-600 mt-0.5">
                <?php if (\CityBus\Core\Setting::getBool('voyage.allow_overbooking', false)): ?>
                    La surréservation est activée (<?= \CityBus\Core\Setting::getInt('voyage.overbooking_pct', 0) ?>%). Vous pouvez encore vendre des billets au-delà de la capacité physique.
                <?php else: ?>
                    La surréservation n'est pas activée. La vente sera refusée.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php elseif ($isNearFull): ?>
    <div class="rounded-xl border px-4 py-3 text-sm bg-amber-50 border-amber-200 text-amber-800 flex items-start gap-3">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
        <div>
            <p class="font-semibold">Capacité bientôt atteinte — <?= $soldCount ?>/<?= $totalSeats ?> sièges vendus (<?= $occupancyPct ?>%)</p>
            <p class="text-xs text-amber-600 mt-0.5"><?= $totalSeats - $soldCount ?> place(s) restante(s).</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── GRILLE PRINCIPALE ──────────────────────────────────────────────── -->
    <div class="grid lg:grid-cols-5 gap-5 items-start">

        <!-- ── COLONNE GAUCHE : onglets ──────────────────────────────────── -->
        <div class="lg:col-span-3 space-y-4">

            <!-- Navigation onglets -->
            <div class="bg-white rounded-2xl border border-gray-200 p-1 flex gap-1">
                <!-- Onglet 1 -->
                <button type="button"
                    @click="goTab('siege')"
                    :class="tab==='siege'
                        ? 'bg-cb-primary text-white shadow-sm'
                        : 'text-gray-500 hover:bg-gray-100'"
                    class="flex-1 flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition">
                    <span
                        :class="siegeOk ? 'bg-green-500 text-white' : (tab==='siege' ? 'bg-white/30 text-white' : 'bg-gray-200 text-gray-500')"
                        class="inline-flex w-5 h-5 rounded-full items-center justify-center text-xs font-bold shrink-0">
                        <svg x-show="siegeOk" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span x-show="!siegeOk">1</span>
                    </span>
                    Siège & Tarif
                </button>
                <!-- Onglet 2 -->
                <button type="button"
                    @click="goTab('bagages')"
                    :class="tab==='bagages'
                        ? 'bg-amber-500 text-white shadow-sm'
                        : 'text-gray-500 hover:bg-gray-100'"
                    class="flex-1 flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition">
                    <span
                        :class="bagsVisited ? 'bg-amber-600/30 text-white' : (tab==='bagages' ? 'bg-white/30 text-white' : 'bg-gray-200 text-gray-500')"
                        class="inline-flex w-5 h-5 rounded-full items-center justify-center text-xs font-bold">
                        <span x-text="bags.length > 0 ? bags.length : '2'"></span>
                    </span>
                    Bagages
                    <span x-show="!bagsVisited" class="text-xs font-normal opacity-80">(obligatoire)</span>
                </button>
                <!-- Onglet 3 -->
                <button type="button"
                    @click="goTab('identite')"
                    :class="tab==='identite'
                        ? 'bg-cb-primary text-white shadow-sm'
                        : 'text-gray-500 hover:bg-gray-100'"
                    class="flex-1 flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition">
                    <span
                        :class="identiteOk ? 'bg-green-500 text-white' : (tab==='identite' ? 'bg-white/30 text-white' : 'bg-gray-200 text-gray-500')"
                        class="inline-flex w-5 h-5 rounded-full items-center justify-center text-xs font-bold shrink-0">
                        <svg x-show="identiteOk" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span x-show="!identiteOk">3</span>
                    </span>
                    Identité
                </button>
            </div>

            <!-- ════════════════════════════════════════════════════════════ -->
            <!-- TAB 1 : SIÈGE & TARIF                                       -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <div x-show="tab==='siege'" x-transition class="space-y-4">

                <!-- Plan de sièges -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="armchair" class="w-4 h-4 text-cb-primary"></i>
                        Sélection du siège
                    </h3>

                    <!-- Légende -->
                    <div class="flex flex-wrap gap-3 mb-4 text-xs text-gray-600">
                        <span class="flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded bg-gray-100 border border-gray-300 inline-block"></span> Libre
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded bg-cb-primary inline-block"></span> Sélectionné
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded bg-red-400 inline-block"></span> Occupé
                        </span>
                    </div>

                    <!-- Grille -->
                    <div class="grid grid-cols-8 gap-1.5 max-w-md">
                        <?php for ($s = 1; $s <= $totalSeats; $s++):
                            $isBooked = isset($bookedSet[$s]);
                        ?>
                        <?php if (!$isBooked): ?>
                        <button type="button"
                            @click="seat = (seat === <?= $s ?> ? null : <?= $s ?>)"
                            :class="seat === <?= $s ?>
                                ? 'bg-cb-primary text-white border-cb-primary'
                                : 'bg-gray-50 text-gray-700 border-gray-300 hover:border-cb-primary hover:text-cb-primary'"
                            class="rounded-lg border text-xs font-semibold h-9 transition">
                            <?= $s ?>
                        </button>
                        <?php else: ?>
                        <div class="rounded-lg bg-red-100 border border-red-300 text-red-400 text-xs font-semibold h-9 flex items-center justify-center cursor-not-allowed" title="Occupé">
                            <?= $s ?>
                        </div>
                        <?php endif ?>
                        <?php endfor ?>
                    </div>

                    <p class="mt-3 text-sm text-gray-500">
                        Siège sélectionné :
                        <span class="font-semibold text-gray-900" x-text="seat ?? '—'"></span>
                        &nbsp;·&nbsp;
                        <?= count($bookedSet) ?> / <?= $totalSeats ?> occupés
                    </p>
                </div>

                <!-- Périmètre tarifaire -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i data-lucide="tag" class="w-4 h-4 text-cb-primary"></i>
                        Périmètre tarifaire
                    </h3>

                    <?php if (empty($availableScopes)): ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-700 text-sm flex items-start gap-2">
                        <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
                        <span>Aucun tarif pré-configuré sur cette ligne — saisissez le prix manuellement ci-dessous.</span>
                    </div>
                    <?php else: ?>

                    <div class="grid sm:grid-cols-3 gap-3">
                        <!-- Type de billet -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                                Type de billet
                            </label>
                            <select x-model="type" @change="onScopeChange()"
                                class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-cb-primary focus:border-cb-primary">
                                <template x-for="t in availableTypes()" :key="t.value">
                                    <option :value="t.value" x-text="t.label"></option>
                                </template>
                            </select>
                        </div>
                        <!-- Catégorie passager -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                                Catégorie
                            </label>
                            <select x-model="cat" @change="onScopeChange()"
                                class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-cb-primary focus:border-cb-primary">
                                <template x-for="c in availableCats()" :key="c.value">
                                    <option :value="c.value" x-text="c.label"></option>
                                </template>
                            </select>
                        </div>
                        <!-- Classe de voyage -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                                Classe
                            </label>
                            <select x-model="cls" @change="onScopeChange()"
                                class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-cb-primary focus:border-cb-primary">
                                <template x-for="cl in availableClasses()" :key="cl.value">
                                    <option :value="cl.value" x-text="cl.label"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <?php if (!empty($stops)): ?>
                    <!-- Embarquement → Destination (filtrés mutuellement) -->
                    <div class="mt-3 grid sm:grid-cols-2 gap-3">
                        <!-- Embarquement -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                                <i data-lucide="circle-dot" class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5 text-cb-primary"></i>
                                Embarquement (arrêt)
                            </label>
                            <select x-model="boardingStopId" @change="onBoardingChange()"
                                class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-cb-primary focus:border-cb-primary">
                                <option value="">— <?= $firstStopLabel ?> —</option>
                                <template x-for="s in availableBoardingStops()" :key="s.id">
                                    <option :value="s.id" x-text="s.name"></option>
                                </template>
                            </select>
                        </div>
                        <!-- Destination -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5 text-rose-500"></i>
                                Destination (arrêt)
                            </label>
                            <select x-model="destStopId" @change="onDestChange()"
                                class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-cb-primary focus:border-cb-primary">
                                <option value="">— <?= $lastStopLabel ?> —</option>
                                <template x-for="s in availableDestStops()" :key="s.id">
                                    <option :value="s.id" x-text="s.name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Trajet partiel → tarif spécifique appliqué</p>
                        </div>
                    </div>
                    <?php endif ?>
                    <?php endif ?>

                    <!-- Tarif résolu -->
                    <div class="mt-4">
                        <!-- Loading -->
                        <div x-show="tariffStatus==='loading'" class="flex items-center gap-2 text-gray-400 text-sm">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            Recherche du tarif…
                        </div>
                        <!-- Erreur technique (réseau, etc.) -->
                        <div x-show="tariffStatus==='error'" class="bg-red-50 border border-red-200 rounded-xl p-3 text-red-700 text-sm" x-text="tariffError"></div>

                        <!-- ── MODE OK : tarif trouvé ────────────────────────────── -->
                        <div x-cloak x-show="tariffStatus==='ok'"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="rounded-xl border p-4 space-y-3 bg-cb-bg border-blue-200">

                            <!-- En-tête : label + prix -->
                            <div class="flex items-center justify-between gap-4 flex-wrap">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tarif appliqué</p>
                                    <p class="font-bold text-gray-900 text-sm mt-0.5" x-text="scopeLabel()"></p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-2xl font-extrabold text-cb-primary leading-none py-2.5 px-3"
                                       x-text="tariff.price_formatted || '—'"></p>
                                    <p class="text-xs text-gray-400 mt-1">FCFA · billet passager</p>
                                </div>
                            </div>

                        <!-- ── MODE MANUEL : aucun tarif ───────────────────────────── -->
                        </div>
                        <div x-cloak x-show="tariffStatus==='manual'"
                            x-transition:enter="transition ease-out duration-250"
                            x-transition:enter-start="opacity-0 scale-[0.98]"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="rounded-xl border-2 border-orange-300 bg-orange-50 p-4 space-y-4">

                            <!-- Bannière alerte -->
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-xl bg-orange-100 flex items-center justify-center shrink-0 mt-0.5">
                                    <i data-lucide="alert-triangle" class="w-5 h-5 text-orange-500"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-orange-800 text-sm" x-text="tariffError || 'Aucun tarif configuré — saisissez le prix manuellement.'"></p>
                                    <p class="text-xs text-orange-600 mt-0.5">Le montant et le motif sont obligatoires et enregistrés pour audit.</p>
                                </div>
                            </div>

                            <!-- En-tête scope label -->
                            <div>
                                <p class="text-xs font-semibold text-orange-600 uppercase tracking-wide">Prix du billet</p>
                                <p class="font-bold text-gray-900 text-sm mt-0.5" x-text="scopeLabel()"></p>
                            </div>

                            <!-- Saisie du prix -->
                            <div>
                                <label class="block text-xs font-bold text-orange-700 uppercase tracking-wide mb-2">
                                    Montant du billet (FCFA) <span class="text-red-500">*</span>
                                </label>
                                <template x-if="tariffStatus === 'manual'">
                                    <div>
                                        <div class="relative">
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                x-model="priceInput"
                                                placeholder="Ex. 12 000"
                                                autocomplete="off"
                                                class="w-full rounded-xl border-2 px-4 py-3.5 text-right text-2xl font-extrabold transition-all focus:outline-none focus:ring-2"
                                                :class="priceInput && parseInt(priceInput) > 0
                                                    ? 'border-orange-400 text-orange-700 bg-white focus:ring-orange-200'
                                                    : 'border-orange-300 text-gray-400 bg-white focus:ring-orange-200'">
                                            <span class="absolute right-4 bottom-2 text-xs text-gray-400 pointer-events-none">FCFA</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <p class="text-xs text-orange-500">Saisissez le montant convenu avec le passager.</p>
                                            <p x-show="parseInt(priceInput) > 0"
                                                class="text-xs font-semibold text-green-600 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                <span x-text="parseInt(priceInput).toLocaleString('fr-FR') + ' FCFA'"></span>
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Motif -->
                            <div>
                                <label class="block text-xs font-bold text-orange-700 uppercase tracking-wide mb-2">
                                    Motif / Justification <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                    x-model="priceNote"
                                    :placeholder="tariffError && tariffError.includes('arrêt')
                                        ? 'Ex. Tarif inexistant, accord client…'
                                        : 'Ex. Tarif négocié, accord commercial…'"
                                    class="w-full rounded-xl border-2 px-4 py-3 text-sm transition-all focus:outline-none focus:ring-2 focus:ring-orange-200"
                                    :class="priceNote.trim().length >= 5
                                        ? 'border-green-300 bg-white text-gray-900'
                                        : (priceNote.length > 0
                                            ? 'border-red-400 bg-red-50 text-red-700'
                                            : 'border-orange-300 bg-white text-gray-600')">
                                <p class="text-xs text-orange-500 mt-1.5 flex items-center gap-1">
                                    <i data-lucide="lock" class="w-3 h-3"></i>
                                    Enregistré pour audit — ne peut pas être effacé après émission.
                                </p>
                            </div>

                            <!-- Résumé validité inline -->
                            <div class="rounded-xl border border-orange-200 bg-white p-3 space-y-1.5">
                                <div class="flex items-center gap-2 text-xs"
                                    :class="parseInt(priceInput) > 0 ? 'text-green-700' : 'text-gray-400'">
                                    <span x-text="parseInt(priceInput) > 0 ? '✓' : '○'"></span>
                                    <span>Montant saisi :
                                        <strong x-text="parseInt(priceInput) > 0 ? parseInt(priceInput).toLocaleString('fr-FR') + ' FCFA' : '—'"></strong>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-xs"
                                    :class="priceNote.trim().length >= 5 ? 'text-green-700' : 'text-gray-400'">
                                    <span x-text="priceNote.trim().length >= 5 ? '✓' : '○'"></span>
                                    <span>Motif :
                                        <strong x-text="priceNote.trim().length >= 5 ? priceNote.trim() : 'requis (5 car. min)'"></strong>
                                    </span>
                                </div>
                            </div>

                        </div>

                        <!-- ── Sections OK complémentaires (franchise, services, réduction) ── -->
                        <div x-cloak x-show="tariffStatus==='ok'" class="space-y-3">

                            <!-- Franchise bagages -->
                            <div class="rounded-xl border border-blue-200 bg-cb-bg p-4">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Franchise bagages incluse</p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex items-center gap-1.5 bg-green-50 text-green-700 border border-green-200 rounded-lg px-3 py-1.5 text-sm font-medium">
                                        <i data-lucide="package" class="w-3.5 h-3.5"></i>
                                        <span x-text="tariff.baggage_included_qty"></span>
                                        <span x-text="tariff.baggage_included_qty <= 1 ? 'bagage' : 'bagages'"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 bg-green-50 text-green-700 border border-green-200 rounded-lg px-3 py-1.5 text-sm font-medium">
                                        <i data-lucide="scale" class="w-3.5 h-3.5"></i>
                                        <span x-text="tariff.baggage_included_kg + ' kg'"></span>
                                        max
                                    </span>
                                    <span x-show="tariff.baggage_included_qty === 0" class="text-xs text-gray-500 italic self-center">
                                        (aucune franchise — tout bagage est excédentaire)
                                    </span>
                                </div>
                            </div>

                            <!-- Services inclus -->
                            <template x-if="tariffStatus==='ok' && tariff.services && tariff.services.length">
                                <div class="rounded-xl border border-blue-200 bg-cb-bg p-4">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Services inclus</p>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="svc in tariff.services" :key="svc.id">
                                            <span class="inline-flex items-center gap-1 bg-white border border-gray-200 rounded-lg px-2.5 py-1 text-xs text-gray-700"
                                                x-text="svc.label"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Réduction -->
                            <div class="rounded-xl border border-blue-200 bg-cb-bg p-4">
                                <button type="button"
                                    @click="showDiscount = !showDiscount; if (!showDiscount) { discountFcfa = ''; discountReason = ''; } $nextTick(() => lucide.createIcons({ attrs: { 'stroke-width': '2' } }))"
                                    class="flex items-center gap-1.5 text-xs font-semibold transition"
                                    :class="showDiscount ? 'text-amber-600 hover:text-amber-700' : 'text-gray-400 hover:text-gray-600'">
                                    <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                                    <span x-text="showDiscount ? 'Annuler la réduction' : 'Appliquer une réduction'"></span>
                                    <i :data-lucide="showDiscount ? 'chevron-up' : 'chevron-down'" class="w-3.5 h-3.5"></i>
                                </button>

                                <div x-show="showDiscount" x-transition class="mt-3 space-y-3">
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1.5">
                                                Réduction (FCFA) <span class="text-red-500">*</span>
                                            </label>
                                            <input type="number" min="1" step="1"
                                                x-model="discountFcfa"
                                                :max="(parseInt(priceInput) || 1) - 1"
                                                placeholder="Ex. 1 000"
                                                class="w-full rounded-xl border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-300 transition-colors"
                                                :class="discountFcfa && parseInt(discountFcfa) >= (parseInt(priceInput)||0)
                                                    ? 'border-red-400 bg-red-50'
                                                    : 'border-amber-300 bg-white'">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1.5">
                                                Motif <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text"
                                                x-model="discountReason"
                                                placeholder="Ex. Client fidèle, bon de réduction…"
                                                class="w-full rounded-xl border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-300 transition-colors"
                                                :class="discountReason.length > 0 && discountReason.trim().length < 5
                                                    ? 'border-red-400 bg-red-50'
                                                    : 'border-amber-300 bg-white'">
                                        </div>
                                    </div>
                                    <div x-show="discountAmount() > 0" class="flex items-center justify-between rounded-lg bg-amber-50 border border-amber-200 px-3 py-2">
                                        <span class="text-xs font-medium text-amber-700">Prix net après réduction</span>
                                        <span class="font-bold text-amber-800" x-text="fcfa(netTicketPrice())"></span>
                                    </div>
                                    <p x-show="discountFcfa && parseInt(discountFcfa) >= (parseInt(priceInput)||0)"
                                        class="text-xs text-red-600 font-medium">
                                        La réduction ne peut pas dépasser ou égaler le tarif officiel.
                                    </p>
                                </div>
                            </div>

                        </div><!-- /sections OK complémentaires -->
                    </div>

                    <!-- CTA vers onglet suivant -->
                    <div class="mt-4 flex justify-end">
                        <button type="button"
                            @click="goTab('bagages')"
                            :disabled="!siegeOk"
                            :class="siegeOk ? 'bg-cb-primary hover:bg-cb-dark text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition">
                            Suivant : Déclarer les bagages
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>

                </div>

            </div><!-- /tab siege -->

            <!-- ════════════════════════════════════════════════════════════ -->
            <!-- TAB 2 : BAGAGES                                             -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <div x-show="tab==='bagages'" x-transition class="space-y-4">

                <!-- Rappel franchise -->
                <div x-show="tariffStatus==='ok'" class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-amber-100 text-amber-600 shrink-0">
                            <i data-lucide="package-check" class="w-5 h-5"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-amber-900 text-sm">
                                Franchise : <span x-text="tariff.baggage_included_qty"></span>
                                <span x-text="tariff.baggage_included_qty <= 1 ? ' bagage' : ' bagages'"></span>
                                jusqu'à <span x-text="tariff.baggage_included_kg"></span> kg inclus dans le billet
                            </p>
                            <p class="text-xs text-amber-700 mt-0.5">
                                Les bagages dépassant cette franchise génèrent un billet bagage excédentaire payant.
                            </p>
                            <!-- Jauge franchise -->
                            <div class="mt-2 flex flex-wrap gap-4 text-xs text-amber-800 font-medium">
                                <span>
                                    Pièces :
                                    <span x-text="franchiseUsed().count"></span> / <span x-text="tariff.baggage_included_qty"></span>
                                    utilisé(e)s
                                </span>
                                <span>
                                    Poids :
                                    <span x-text="(franchiseUsed().kg).toFixed(1)"></span> /
                                    <span x-text="tariff.baggage_included_kg"></span> kg utilisés
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="tariffStatus!=='ok' && tariffStatus!=='manual'" class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4 text-yellow-800 text-sm">
                    <i data-lucide="info" class="w-4 h-4 inline-block mr-1"></i>
                    Résolvez d'abord le tarif dans l'onglet <strong>Siège & Tarif</strong> pour voir la franchise.
                </div>
                <div x-show="tariffStatus==='manual'" class="bg-orange-50 border border-orange-200 rounded-2xl p-4 text-orange-700 text-sm flex items-start gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 mt-0.5"></i>
                    <span>Mode <strong>prix hors tarif</strong> — franchise bagages non définie. Tous les bagages saisis seront traités comme excédentaires.</span>
                </div>

                <!-- Liste des bagages -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="luggage" class="w-4 h-4 text-amber-500"></i>
                            Liste des bagages du client
                        </h3>
                        <button type="button" @click="addBag()"
                            class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-3 py-2 rounded-xl transition">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Ajouter un bagage
                        </button>
                    </div>

                    <!-- Aucun bagage -->
                    <div x-show="bags.length === 0" class="py-8 text-center">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 mb-3">
                            <i data-lucide="package-open" class="w-7 h-7"></i>
                        </div>
                        <p class="text-gray-500 text-sm font-medium">Aucun bagage déclaré</p>
                        <p class="text-gray-400 text-xs mt-1">
                            Cliquez sur <strong>Ajouter un bagage</strong> pour chaque bagage du client,<br>
                            ou passez à l'onglet Identité si le client n'a pas de bagage.
                        </p>
                    </div>

                    <!-- Lignes bagages -->
                    <div class="space-y-3">
                        <template x-for="(bag, idx) in bags" :key="bag.uid">
                            <div class="rounded-xl border p-4 transition"
                                :class="bagClassification()[idx]==='inclus'
                                    ? 'border-green-200 bg-green-50'
                                    : (bagClassification()[idx]==='exces'
                                        ? 'border-amber-200 bg-amber-50'
                                        : 'border-gray-200 bg-white')">

                                <div class="flex items-start gap-3 flex-wrap">
                                    <!-- Numéro -->
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-200 text-gray-600 text-xs font-bold shrink-0 mt-1"
                                        x-text="idx + 1"></span>

                                    <!-- Description -->
                                    <div class="flex-1 min-w-32">
                                        <label class="block text-xs font-semibold text-gray-500 mb-1">
                                            Description <span class="font-normal">(optionnel)</span>
                                        </label>
                                        <input type="text"
                                            x-model="bag.description"
                                            placeholder="Ex. valise noire, sac à dos…"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                    </div>

                                    <!-- Poids -->
                                    <div class="w-32 shrink-0">
                                        <label class="block text-xs font-semibold text-gray-500 mb-1">
                                            Poids (kg) <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number" min="1" step="1"
                                            x-model="bag.weight_kg"
                                            @input.debounce.300ms="onBagWeightChange(bag)"
                                            placeholder="0"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                    </div>

                                    <!-- Catégorie fret (combobox type-ahead) -->
                                    <div class="flex-1 min-w-36 relative" @click.outside="bag.fretOpen = false">
                                        <label class="block text-xs font-semibold text-gray-500 mb-1">
                                            Catégorie fret <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text"
                                            x-model="bag.fretQuery"
                                            @focus="bag.fretOpen = true"
                                            @input="bag.fretOpen = true; bag.fret_category_slug = ''; bag.fretPrice = 0; computeFretPrice(bag)"
                                            @keydown.escape="bag.fretOpen = false"
                                            placeholder="Chercher…"
                                            autocomplete="off"
                                            :class="bag.fret_category_slug
                                                ? 'border-emerald-400 bg-emerald-50'
                                                : (bag.fretQuery && !bag.fret_category_slug ? 'border-red-300' : 'border-gray-300')"
                                            class="w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400">

                                        <!-- Dropdown -->
                                        <div x-show="bag.fretOpen && fretFiltered(bag).length > 0" x-cloak
                                            class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                            <template x-for="fc in fretFiltered(bag)" :key="fc.slug">
                                                <button type="button"
                                                    @click="selectFretCategory(bag, fc)"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-amber-50 flex items-center gap-2 transition">
                                                    <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                                        :style="'background:' + (fc.color || '#888')"></span>
                                                    <span x-text="fc.label" class="flex-1 font-medium text-gray-800"></span>
                                                    <span class="text-xs text-gray-400"
                                                        x-text="parseInt(fc.price_per_kg).toLocaleString('fr-FR') + ' F/kg'"></span>
                                                </button>
                                            </template>
                                            <div x-show="fretFiltered(bag).length === 0"
                                                class="px-3 py-2 text-sm text-gray-400 italic">Aucune catégorie trouvée</div>
                                        </div>

                                        <!-- Prix fret calculé sous le combobox -->
                                        <div x-show="bag.fret_category_slug && bag.fretPrice > 0"
                                            class="mt-1 text-xs font-semibold text-amber-700"
                                            x-text="fcfa(bag.fretPrice) + ' (fret)'"></div>
                                        <div x-show="bag.fret_category_slug && bag.fretPrice === 0 && bagClassification()[idx] === 'inclus'"
                                            class="mt-1 text-xs text-green-600 font-medium">Franchise — fret inclus</div>
                                    </div>

                                    <!-- Badge classification -->
                                    <div class="shrink-0 mt-5">
                                        <span x-show="!bag.weight_kg || parseFloat(bag.weight_kg) <= 0"
                                            class="inline-flex items-center gap-1 bg-gray-100 text-gray-400 rounded-lg px-2.5 py-1.5 text-xs font-medium">
                                            <i data-lucide="minus-circle" class="w-3.5 h-3.5"></i>
                                            En attente
                                        </span>
                                        <span x-show="bag.weight_kg && parseFloat(bag.weight_kg) > 0 && bagClassification()[idx]==='inclus'"
                                            class="inline-flex items-center gap-1 bg-green-100 text-green-700 rounded-lg px-2.5 py-1.5 text-xs font-semibold">
                                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                                            Franchise
                                        </span>
                                        <span x-show="bag.weight_kg && parseFloat(bag.weight_kg) > 0 && bagClassification()[idx]==='exces'"
                                            class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 rounded-lg px-2.5 py-1.5 text-xs font-semibold">
                                            <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                                            Excédent
                                        </span>
                                    </div>

                                    <!-- Supprimer -->
                                    <button type="button" @click="removeBag(bag.uid)"
                                        class="text-gray-400 hover:text-red-500 transition mt-5 shrink-0">
                                        <i data-lucide="x-circle" class="w-5 h-5"></i>
                                    </button>
                                </div>

                                <!-- SECTION EXCÉDENT : nature + prix -->
                                <div x-show="bagClassification()[idx]==='exces'" class="mt-3 border-t border-amber-200 pt-3 space-y-3">
                                    <?php if (!empty($baggageTariffs)): ?>
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-amber-700 mb-1">
                                                Nature du bagage excédentaire <span class="text-red-500">*</span>
                                            </label>
                                            <select x-model="bag.baggage_tariff_id"
                                                @change="calcExcessBagPrice(bag)"
                                                class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                                <option value="">— Sélectionner —</option>
                                                <?php foreach ($baggageTariffs as $bt): ?>
                                                <option value="<?= (int)$bt['id'] ?>">
                                                    <?= e($bt['label']) ?>
                                                </option>
                                                <?php endforeach ?>
                                            </select>
                                        </div>
                                        <div class="flex items-end">
                                            <div class="w-full">
                                                <label class="block text-xs font-semibold text-amber-700 mb-1">Prix calculé</label>
                                                <div class="flex items-center gap-2 bg-amber-100 border border-amber-200 rounded-lg px-3 py-2 min-h-[38px]">
                                                    <svg x-show="bag.price_loading" class="animate-spin w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                    </svg>
                                                    <span x-show="!bag.price_loading && bag.price_result"
                                                        class="font-bold text-amber-800 text-sm"
                                                        x-text="bag.price_result ? fcfa(bag.price_result.total) : '—'"></span>
                                                    <span x-show="!bag.price_loading && !bag.price_result"
                                                        class="text-amber-600 text-sm italic">
                                                        Sélectionnez une nature
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <!-- Pas de tarif bagage dédié → le prix est calculé via la catégorie fret ci-dessus -->
                                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-amber-700 text-xs flex items-start gap-2">
                                        <i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
                                        <span>Le prix de ce bagage excédentaire est calculé via la <strong>catégorie fret</strong> sélectionnée ci-dessus.</span>
                                    </div>
                                    <?php endif ?>
                                </div>

                            </div>
                        </template>
                    </div>
                </div>

                <!-- Résumé classification + CTA -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-3 text-sm">
                        <span class="flex items-center gap-1.5 text-green-700 font-medium">
                            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                            <span x-text="includedBagCount()"></span>
                            <span x-text="includedBagCount() <= 1 ? 'bagage inclus' : 'bagages inclus'"></span>
                        </span>
                        <span class="text-gray-300">|</span>
                        <span class="flex items-center gap-1.5 text-amber-600 font-medium">
                            <i data-lucide="alert-circle" class="w-4 h-4"></i>
                            <span x-text="excessBagCount()"></span>
                            <span x-text="excessBagCount() <= 1 ? 'excédent' : 'excédents'"></span>
                            <template x-if="excessTotal() > 0">
                                <span class="font-bold" x-text="'(' + fcfa(excessTotal()) + ')'"></span>
                            </template>
                        </span>
                    </div>
                    <button type="button"
                        @click="goTab('identite')"
                        class="inline-flex items-center gap-2 bg-cb-primary hover:bg-cb-dark text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">
                        Suivant : Identité du passager
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>

            </div><!-- /tab bagages -->

            <!-- ════════════════════════════════════════════════════════════ -->
            <!-- TAB 3 : IDENTITÉ                                            -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <div x-show="tab==='identite'" x-transition>
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-5">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                        <i data-lucide="user" class="w-4 h-4 text-cb-primary"></i>
                        Identité du passager
                    </h3>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <!-- Nom -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                                Nom complet <span class="text-red-500">*</span>
                            </label>
                            <input type="text" x-model="passengerName"
                                placeholder="Prénom Nom"
                                class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-cb-primary focus:border-cb-primary"
                                :class="passengerName.trim().length > 0 && passengerName.trim().length < 2 ? 'border-red-400' : ''">
                        </div>
                        <!-- Téléphone -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                                Téléphone
                            </label>
                            <input type="tel" x-model="passengerPhone"
                                placeholder="+226 XX XX XX XX"
                                class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-cb-primary focus:border-cb-primary">
                        </div>
                    </div>

                </div>
            </div><!-- /tab identite -->

        </div><!-- /col gauche -->

        <!-- ── COLONNE DROITE : récap + formulaire ────────────────────── -->
        <div class="lg:col-span-2">
            <div class="sticky top-4 space-y-4">

                <form method="POST" action="<?= $storeUrl ?>" @submit.prevent="submitForm($el)">
                    <?= csrf_field() ?>
                    <input type="hidden" name="trip_id"       value="<?= $tripId ?>">
                    <input type="hidden" name="ticket_type"         :value="type">
                    <input type="hidden" name="passenger_category"  :value="cat">
                    <input type="hidden" name="travel_class"        :value="cls">
                    <input type="hidden" name="seat_number"         :value="seat ?? ''">
                    <input type="hidden" name="passenger_name"      :value="passengerName">
                    <input type="hidden" name="passenger_phone"     :value="passengerPhone">
                    <input type="hidden" name="origin_stop_id"      :value="boardingStopId">
                    <input type="hidden" name="boarding_stop_id"    :value="boardingStopId">
                    <input type="hidden" name="alighting_stop_id"   :value="destStopId || alightingStopId">
                    <input type="hidden" name="destination_stop_id" :value="destStopId">
                    <input type="hidden" name="payment_method"      :value="payment">
                    <input type="hidden" name="bags_json"           :value="JSON.stringify(bagsForSubmit())">
                    <input type="hidden" name="payment_status"       :value="payNow ? 'paye' : 'en_attente'">
                    <input type="hidden" name="manual_price_fcfa"   :value="tariffStatus==='manual' ? (parseInt(priceInput)||'') : ''">
                    <input type="hidden" name="manual_price_reason" :value="tariffStatus==='manual' ? priceNote : ''">
                    <input type="hidden" name="discount_fcfa"       :value="tariffStatus==='ok' && discountAmount() > 0 ? discountAmount() : 0">
                    <input type="hidden" name="discount_reason"     :value="tariffStatus==='ok' && discountAmount() > 0 ? discountReason : ''">

                    <!-- Récapitulatif -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i data-lucide="receipt" class="w-4 h-4 text-cb-primary"></i>
                            Récapitulatif
                        </h3>

                        <!-- Siège -->
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 flex items-center gap-1.5">
                                <i data-lucide="armchair" class="w-3.5 h-3.5"></i> Siège
                            </span>
                            <span class="font-semibold" x-text="seat ? 'N°' + seat : '—'"></span>
                        </div>

                        <!-- Destination -->
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 flex items-center gap-1.5">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5"></i> Destination
                            </span>
                            <span class="font-semibold" x-text="destStopLabel()"></span>
                        </div>

                        <!-- Billet passager -->
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 flex items-center gap-1.5 flex-wrap">
                                <i data-lucide="ticket" class="w-3.5 h-3.5"></i> Billet passager
                                <span x-show="tariffStatus==='manual'"
                                    class="inline-flex items-center bg-orange-100 text-orange-700 rounded px-1.5 py-0.5 text-xs font-medium">
                                    Hors tarif
                                </span>
                            </span>
                            <span class="font-semibold"
                                :class="tariffStatus==='ok' && discountAmount() > 0 ? 'text-gray-400 line-through text-base' : 'text-cb-primary'"
                                x-text="priceInput > 0 ? fcfa(parseInt(priceInput)) : '—'"></span>
                        </div>

                        <!-- Réduction -->
                        <template x-if="tariffStatus==='ok' && discountAmount() > 0">
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-amber-600 flex items-center gap-1.5">
                                        <i data-lucide="tag" class="w-3.5 h-3.5"></i> Réduction
                                    </span>
                                    <span class="font-semibold text-amber-600" x-text="'−' + fcfa(discountAmount())"></span>
                                </div>
                                <div x-show="discountReason" class="text-xs text-amber-500 text-right mt-0.5 italic" x-text="discountReason"></div>
                                <div class="flex items-center justify-between text-sm mt-1.5 pt-1.5 border-t border-dashed border-amber-200">
                                    <span class="font-medium text-gray-700">Prix net billet</span>
                                    <span class="font-bold text-gray-900" x-text="fcfa(netTicketPrice())"></span>
                                </div>
                            </div>
                        </template>

                        <!-- Bagages inclus -->
                        <template x-if="includedBagCount() > 0">
                            <div class="rounded-xl bg-green-50 border border-green-100 p-3 space-y-1.5">
                                <p class="text-xs font-semibold text-green-700 flex items-center gap-1.5">
                                    <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                                    Bagages dans la franchise
                                </p>
                                <template x-for="(bag, idx) in bags" :key="bag.uid">
                                    <div x-show="bagClassification()[idx]==='inclus'" class="flex items-center justify-between text-xs text-green-800">
                                        <span x-text="(bag.description || 'Bagage ' + (idx+1)) + ' · ' + (bag.weight_kg || 0) + ' kg'"></span>
                                        <span class="font-semibold">Inclus</span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Bagages excédentaires -->
                        <template x-if="excessBagCount() > 0">
                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 space-y-1.5">
                                <p class="text-xs font-semibold text-amber-700 flex items-center gap-1.5">
                                    <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                                    Bagages excédentaires
                                </p>
                                <template x-for="(bag, idx) in bags" :key="bag.uid">
                                    <div x-show="bagClassification()[idx]==='exces'" class="flex items-center justify-between text-xs text-amber-800">
                                        <span x-text="(bag.description || 'Bagage ' + (idx+1)) + ' · ' + (bag.weight_kg || 0) + ' kg'"></span>
                                        <span class="font-semibold"
                                            x-text="(bag.price_result?.total || bag.fretPrice) ? fcfa(Math.max(bag.price_result?.total || 0, bag.fretPrice || 0)) : '—'"></span>
                                    </div>
                                </template>
                                <div class="border-t border-amber-200 pt-1.5 flex items-center justify-between text-xs font-bold text-amber-900">
                                    <span>Sous-total excédent</span>
                                    <span x-text="fcfa(excessTotal())"></span>
                                </div>
                            </div>
                        </template>

                        <!-- Total -->
                        <div class="border-t border-gray-100 pt-3">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-900">Total à percevoir</span>
                                <span class="text-xl font-extrabold text-cb-primary"
                                    x-text="fcfa(grandTotal())"></span>
                            </div>
                        </div>

                        <!-- Mode de paiement -->
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Mode de paiement</p>
                            <div class="grid grid-cols-3 gap-1.5">
                                <?php foreach (['especes' => 'Espèces', 'mobile_money' => 'Mobile Money', 'cheque' => 'Chèque'] as $val => $lbl): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" x-model="payment" value="<?= $val ?>" class="sr-only peer">
                                    <span class="block text-center text-xs font-medium rounded-xl border border-gray-200 px-2 py-2 transition
                                        peer-checked:bg-cb-primary peer-checked:text-white peer-checked:border-cb-primary
                                        hover:border-cb-primary hover:text-cb-primary">
                                        <?= $lbl ?>
                                    </span>
                                </label>
                                <?php endforeach ?>
                            </div>
                        </div>
                    </div>

                    <!-- Checklist de validation -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Checklist</p>
                        <div class="space-y-1.5 text-sm">
                            <!-- Siège -->
                            <div class="flex items-center gap-2" :class="seat ? 'text-green-700' : 'text-gray-400'">
                                <svg x-show="seat" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="!seat" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                                Siège sélectionné
                            </div>
                            <!-- Tarif -->
                            <div class="flex items-center gap-2" :class="tariffReady ? 'text-green-700' : (tariffStatus==='manual' ? 'text-orange-500' : 'text-gray-400')">
                                <svg x-show="tariffReady" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="!tariffReady && tariffStatus==='manual'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                <svg x-show="!tariffReady && tariffStatus!=='manual'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                                <span x-text="tariffStatus==='manual' ? 'Prix hors tarif' : 'Tarif résolu'"></span>
                            </div>
                            <!-- Réduction -->
                            <div x-show="tariffStatus==='ok' && showDiscount" class="flex items-center gap-2"
                                :class="(discountAmount() > 0 && discountReason.trim().length >= 5) ? 'text-green-700' : 'text-amber-500'">
                                <svg x-show="discountAmount() > 0 && discountReason.trim().length >= 5" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="!(discountAmount() > 0 && discountReason.trim().length >= 5)" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                Réduction + motif renseignés
                            </div>

                            <!-- Bagages -->
                            <div class="flex items-center gap-2" :class="bagsVisited ? 'text-green-700' : 'text-amber-500'">
                                <svg x-show="bagsVisited" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="!bagsVisited" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                Bagages déclarés
                            </div>
                            <!-- Nom -->
                            <div class="flex items-center gap-2" :class="passengerName.trim().length >= 2 ? 'text-green-700' : 'text-gray-400'">
                                <svg x-show="passengerName.trim().length >= 2" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="passengerName.trim().length < 2" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                                Nom du passager
                            </div>
                            <!-- Nature excédents (masqué si aucun tarif configuré sur la ligne) -->
                            <div x-show="excessBagCount() > 0 && <?= !empty($btJson) && $btJson !== '[]' ? 'true' : 'false' ?>" class="flex items-center gap-2"
                                :class="excessBagsHaveNature() ? 'text-green-700' : 'text-red-500'">
                                <svg x-show="excessBagsHaveNature()" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="!excessBagsHaveNature()" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                Nature des excédents renseignée
                            </div>
                            <!-- Catégorie fret -->
                            <div x-show="bags.length > 0 && <?= !empty($fretCategories) ? 'true' : 'false' ?>"
                                class="flex items-center gap-2"
                                :class="allBagsHaveFretCategory() ? 'text-green-700' : 'text-red-500'">
                                <svg x-show="allBagsHaveFretCategory()" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="!allBagsHaveFretCategory()" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                Catégorie fret de chaque bagage
                            </div>
                        </div>
                    </div>

                    <!-- Boutons soumettre : émettre (en_attente) ou émettre + encaisser -->
                    <div class="space-y-2">
                        <!-- Bouton principal : Émettre (paiement en attente) -->
                        <button type="submit"
                            @click="payNow = false"
                            :disabled="!canSubmit() || submitting"
                            :class="(canSubmit() && !submitting)
                                ? 'bg-cb-primary hover:bg-cb-dark text-white cursor-pointer'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                            class="w-full py-3.5 rounded-2xl font-bold text-base transition flex items-center justify-center gap-2">
                            <template x-if="!submitting">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="printer" class="w-5 h-5"></i>
                                    Émettre le billet
                                    <template x-if="excessBagCount() > 0">
                                        <span class="text-sm font-normal opacity-80">
                                            + <span x-text="excessBagCount()"></span> billet(s) bagage
                                        </span>
                                    </template>
                                </span>
                            </template>
                            <template x-if="submitting && !payNow">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>
                                    Émission en cours…
                                </span>
                        </template>
                        </button>

                        <!-- Bouton secondaire : Émettre & Encaisser immédiatement -->
                        <button type="submit"
                            @click="payNow = true"
                            :disabled="!canSubmit() || submitting"
                            :class="(canSubmit() && !submitting)
                                ? 'bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                            class="w-full py-3 rounded-2xl font-semibold text-sm transition flex items-center justify-center gap-2">
                            <template x-if="!submitting || !payNow">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                                    Émettre & Encaisser
                                    <span class="font-bold" x-text="fcfa(grandTotal())"></span>
                                </span>
                            </template>
                            <template x-if="submitting && payNow">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                    Encaissement en cours…
                                </span>
                            </template>
                        </button>
                    </div>

                    <!-- Message erreur submit -->
                    <p x-show="submitError" x-text="submitError"
                        class="text-red-600 text-sm text-center font-medium"></p>

                </form>

            </div>
        </div><!-- /col droite -->

    </div><!-- /grid -->

</div><!-- /x-data -->

<?php $view->end() ?>
<?php $view->start('scripts') ?>
<script>
(function () {
    const SCOPES          = <?= $scopesJson ?>;
    const BAGGAGE_TARIFFS = <?= $btJson ?>;
    const STOPS           = <?= json_encode(array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'], 'pos' => (int)$s['order_position']], $stops), JSON_HEX_TAG|JSON_UNESCAPED_UNICODE) ?>;
    const LINE_ID         = <?= $lineId ?>;
    const TRIP_ID         = <?= $tripId ?>;
    const TRIP_DATE       = '<?= $tripDate ?>';
    const RESOLVE_URL     = '<?= $resolveUrl ?>';
    const CALC_URL        = '<?= $calcUrl ?>';
    const FRET_CATEGORIES = <?= $fretCatsJson ?? '[]' ?>;
    const ALL_TYPES   = <?= json_encode(array_map(fn($k,$v) => ['value'=>$k,'label'=>$v['label']], array_keys($ticketTypes),   array_values($ticketTypes)),   JSON_HEX_TAG|JSON_UNESCAPED_UNICODE) ?>;
    const ALL_CATS    = <?= json_encode(array_map(fn($k,$v) => ['value'=>$k,'label'=>$v['label']], array_keys($passengerCategories), array_values($passengerCategories)), JSON_HEX_TAG|JSON_UNESCAPED_UNICODE) ?>;
    const ALL_CLASSES = <?= json_encode(array_map(fn($k,$v) => ['value'=>$k,'label'=>$v['label']], array_keys($travelClasses),  array_values($travelClasses)),  JSON_HEX_TAG|JSON_UNESCAPED_UNICODE) ?>;

    window.ticketSale = function () {
        return {
            /* ── état ──────────────────────────────────────────────────── */
            tab:         'siege',
            bagsVisited: false,

            seat: null,
            type: '',
            cat:  '',
            cls:  '',
            destStopId: '',

            tariff:       { id: null, price_fcfa: 0, price_formatted: '—',
                            baggage_included_qty: 0, baggage_included_kg: 0.0,
                            label: '', services: [] },
            tariffStatus: 'idle',   /* idle | loading | ok | manual | error */
            tariffError:  '',

            bags: [],   /* [{uid, description, weight_kg, baggage_tariff_id,
                               price_result, price_loading}] */

            passengerName:   '',
            passengerPhone:  '',
            boardingStopId:  '',
            alightingStopId: '',
            payment:         'especes',

            priceInput:    '',   /* number — set from tariff.price_fcfa (ok) or user-typed (manual) */
            priceNote:     '',   /* motif obligatoire en mode manuel */
            tariffFallback: false,

            showDiscount:   false,
            discountFcfa:   '',
            discountReason: '',

            payNow: false,         /* false = émettre en attente, true = émettre + encaisser */
            submitError: '',
            submitting: false,
            _resolveAbort: null,   /* AbortController courant pour resolveTariff() */

            /* ── init ───────────────────────────────────────────────────── */
            init() {
                const first = SCOPES[0] ?? null;
                this.type = first?.ticket_type        || (ALL_TYPES[0]?.value   ?? 'aller_simple');
                this.cat  = first?.passenger_category || (ALL_CATS[0]?.value    ?? 'adulte');
                this.cls  = first?.travel_class       || (ALL_CLASSES[0]?.value ?? 'standard');
                this.$nextTick(() => this.resolveTariff());
            },

            /* ── navigation ─────────────────────────────────────────────── */
            goTab(t) {
                this.tab = t;
                if (t === 'bagages') this.bagsVisited = true;
                this.$nextTick(() => {
                    lucide.createIcons({ attrs: { 'stroke-width': '2' } });
                });
            },

            /* ── périmètre : toutes les valeurs du référentiel ─────────── */
            availableTypes()   { return ALL_TYPES;   },
            availableCats()    { return ALL_CATS;    },
            availableClasses() { return ALL_CLASSES; },
            onScopeChange() {
                this.resolveTariff();
            },
            onBoardingChange() {
                /* Si la destination actuelle est avant ou égale à l'embarquement, la réinitialiser */
                if (this.boardingStopId && this.destStopId) {
                    const bPos = this._stopPos(this.boardingStopId);
                    const dPos = this._stopPos(this.destStopId);
                    if (dPos <= bPos) { this.destStopId = ''; this.alightingStopId = ''; }
                }
                this.resolveTariff();
            },
            onDestChange() {
                /* Si l'embarquement actuel est après ou égal à la destination, le réinitialiser */
                if (this.destStopId && this.boardingStopId) {
                    const bPos = this._stopPos(this.boardingStopId);
                    const dPos = this._stopPos(this.destStopId);
                    if (bPos >= dPos) { this.boardingStopId = ''; }
                }
                this.alightingStopId = this.destStopId;
                this.resolveTariff();
            },
            /* Arrêts disponibles pour l'embarquement :
               - exclut le 1er (déjà représenté par l'option par défaut "— Brazzaville —")
               - exclut le terminus (on ne peut pas embarquer au dernier arrêt)
               - si destination fixée : exclut aussi les arrêts après ou au même niveau */
            availableBoardingStops() {
                const first = STOPS[0]?.pos ?? -1;
                const last  = STOPS[STOPS.length - 1]?.pos ?? 9999;
                if (!this.destStopId) {
                    return STOPS.filter(s => s.pos > first && s.pos < last);
                }
                const dPos = this._stopPos(this.destStopId);
                return STOPS.filter(s => s.pos > first && s.pos < dPos);
            },
            /* Arrêts disponibles pour la destination :
               - exclut le terminus (déjà représenté par l'option par défaut "— Pointe-Noire —")
               - exclut le 1er arrêt (on ne peut pas descendre au départ)
               - si embarquement fixé : exclut aussi les arrêts avant ou au même niveau */
            availableDestStops() {
                const first = STOPS[0]?.pos ?? -1;
                const last  = STOPS[STOPS.length - 1]?.pos ?? 9999;
                if (!this.boardingStopId) {
                    return STOPS.filter(s => s.pos > first && s.pos < last);
                }
                const bPos = this._stopPos(this.boardingStopId);
                return STOPS.filter(s => s.pos > bPos && s.pos < last);
            },
            _stopPos(stopId) {
                const s = STOPS.find(s => s.id === parseInt(stopId));
                return s ? s.pos : 0;
            },

            /* ── résolution tarif ───────────────────────────────────────── */
            async resolveTariff() {
                if (!this.type || !this.cat || !this.cls) return;

                /* Annule la requête précédente si elle est encore en vol */
                if (this._resolveAbort) {
                    this._resolveAbort.abort();
                }
                this._resolveAbort = new AbortController();
                const signal = this._resolveAbort.signal;

                this.tariffStatus = 'loading';
                this.tariffError  = '';

                console.group('%c[TARIF] resolveTariff()', 'color:#C62828;font-weight:bold');
                console.log('Params:', {
                    line_id: LINE_ID, type: this.type, cat: this.cat, cls: this.cls,
                    boardingStopId: this.boardingStopId || '(vide=départ)',
                    destStopId: this.destStopId || '(vide=terminus)',
                });

                try {
                    const p = new URLSearchParams({
                        line_id:             LINE_ID,
                        ticket_type:         this.type,
                        passenger_category:  this.cat,
                        travel_class:        this.cls,
                        date:                TRIP_DATE,
                        origin_stop_id:      this.boardingStopId || '',
                        destination_stop_id: this.destStopId     || '',
                    });
                    const res  = await fetch(RESOLVE_URL + '?' + p, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal,
                    });
                    if (signal.aborted) { console.log('⛔ Requête annulée (AbortController)'); console.groupEnd(); return; }
                    const json = await res.json();

                    console.log('Réponse serveur:', JSON.parse(JSON.stringify(json)));

                    if (json.ok) {
                        console.log('%c→ MODE OK — tarif trouvé : ' + json.tariff.price_fcfa + ' FCFA', 'color:green;font-weight:bold');
                        this.tariff         = json.tariff;
                        this.tariffStatus   = 'ok';
                        this.tariffFallback = false;
                        this.priceInput     = String(json.tariff.price_fcfa);
                        this.priceNote      = '';
                        this.showDiscount   = false;
                        this.discountFcfa   = '';
                        this.discountReason = '';
                    } else if (json.manual_mode) {
                        console.log('%c→ MODE MANUEL (serveur) — ' + (json.error || 'aucun tarif'), 'color:orange;font-weight:bold');
                        this.tariffStatus   = 'manual';
                        this.tariffFallback = false;
                        this.tariffError    = json.error ?? 'Aucun tarif configuré pour cette combinaison.';
                        this.priceInput     = '';  /* l'input est (re)créé vide par x-if */
                        /* Pré-remplir le motif uniquement si le champ est encore vide,
                           pour ne pas écraser une justification déjà saisie par l'agent */
                        if (!this.priceNote.trim()) {
                            this.priceNote = json.no_segment_tariff ? 'Tarif inexistant' : '';
                        }
                    } else {
                        console.log('%c→ MODE ERREUR — ' + (json.error || 'inconnu'), 'color:red;font-weight:bold');
                        this.tariffStatus   = 'error';
                        this.tariffError    = json.error ?? 'Tarif introuvable.';
                        this.tariffFallback = false;
                        this.priceInput     = '';
                    }

                    console.log('État final:', { tariffStatus: this.tariffStatus, priceInput: this.priceInput, priceNote: this.priceNote, tariffFallback: this.tariffFallback });
                    console.groupEnd();
                } catch (e) {
                    console.groupEnd();
                    if (e.name === 'AbortError') return; /* annulation volontaire — pas d'erreur */
                    console.error('[TARIF] Erreur réseau:', e);
                    this.tariffStatus = 'error';
                    this.tariffError  = 'Erreur réseau lors de la récupération du tarif.';
                }
                this.$nextTick(() => lucide.createIcons({ attrs: { 'stroke-width': '2' } }));
            },

            /* ── gestion des bagages ─────────────────────────────────────── */
            addBag() {
                this.bags.push({
                    uid:               Date.now() + Math.random(),
                    description:       '',
                    weight_kg:         '',
                    baggage_tariff_id: BAGGAGE_TARIFFS.length ? BAGGAGE_TARIFFS[0].id : null,
                    price_result:      null,
                    price_loading:     false,
                    fret_category_slug: FRET_CATEGORIES.length === 1 ? FRET_CATEGORIES[0].slug : '',
                    fretQuery:          FRET_CATEGORIES.length === 1 ? FRET_CATEGORIES[0].label : '',
                    fretOpen:           false,
                    fretPrice:          0,
                });
            },
            removeBag(uid) {
                this.bags = this.bags.filter(b => b.uid !== uid);
            },
            onBagWeightChange(bag) {
                /* re-classifie immédiatement (réactif via bagClassification),
                   puis recalcule le prix si le sac est excédent */
                this.$nextTick(() => {
                    const idx = this.bags.findIndex(b => b.uid === bag.uid);
                    if (idx === -1) return;
                    if (this.bagClassification()[idx] === 'exces') {
                        this.calcExcessBagPrice(bag);
                        this.computeFretPrice(bag);
                    } else {
                        bag.price_result = null;
                        this.computeFretPrice(bag);
                    }
                });
            },
            /* Retourne le poids excédentaire réel pour un bagage donné,
               en soustrayant la franchise restante après les bagages inclus.
               IMPORTANT : doit être cohérent avec bagClassification().
               Quand la franchise est 0 (qty=0 ou kgLimit=0), tout le poids est excédentaire. */
            excessWeightFor(uid) {
                const qty      = parseInt(this.tariff?.baggage_included_qty)  || 0;
                const kgLimit  = parseFloat(this.tariff?.baggage_included_kg) || 0.0;

                /* Pas de franchise → tout le poids de chaque bagage est excédentaire */
                if (qty <= 0 || kgLimit <= 0) {
                    const bag = this.bags.find(b => b.uid === uid);
                    return bag ? Math.max(0, parseFloat(bag.weight_kg) || 0) : 0;
                }

                let usedCount = 0, usedKg = 0.0;
                for (const b of this.bags) {
                    const w = parseFloat(b.weight_kg) || 0;
                    if (w <= 0) { if (b.uid === uid) return 0; continue; }

                    /* Même logique que bagClassification : inclus si quota pièces ET poids OK */
                    if (usedCount < qty && (usedKg + w) <= kgLimit) {
                        usedCount++; usedKg += w;
                        if (b.uid === uid) return 0;
                    } else {
                        let excessW;
                        if (usedCount >= qty) {
                            /* quota de pièces dépassé : tout le poids est en excédent */
                            excessW = w;
                        } else {
                            /* quota kg dépassé : seul le dépassement est facturé */
                            const remaining = Math.max(0, kgLimit - usedKg);
                            excessW = Math.max(0, w - remaining);
                            usedKg  = kgLimit;
                        }
                        if (b.uid === uid) return excessW;
                    }
                }
                return 0;
            },
            async calcExcessBagPrice(bag) {
                if (!bag.baggage_tariff_id || !(parseFloat(bag.weight_kg) > 0)) {
                    bag.price_result = null;
                    return;
                }
                bag.price_loading = true;
                try {
                    const excessW = this.excessWeightFor(bag.uid);
                    const p   = new URLSearchParams({
                        tariff_id: bag.baggage_tariff_id,
                        weight_kg: excessW > 0 ? excessW : parseFloat(bag.weight_kg),
                        trip_id:   TRIP_ID,
                    });
                    const res = await fetch(CALC_URL + '?' + p);
                    bag.price_result = await res.json();
                } catch (_) {
                    bag.price_result = null;
                }
                bag.price_loading = false;
                this.computeFretPrice(bag);
            },

            /* ── classification franchise ───────────────────────────────── */
            bagClassification() {
                const qty     = parseInt(this.tariff?.baggage_included_qty)  || 0;
                const kgLimit = parseFloat(this.tariff?.baggage_included_kg) || 0.0;

                /* Pas de franchise configurée → tout est excédentaire */
                if (qty <= 0 || kgLimit <= 0) {
                    return this.bags.map(bag => {
                        const w = parseFloat(bag.weight_kg) || 0;
                        return w <= 0 ? 'pending' : 'exces';
                    });
                }

                let usedCount = 0, usedKg = 0.0;
                return this.bags.map(bag => {
                    const w = parseFloat(bag.weight_kg) || 0;
                    if (w <= 0) return 'pending';
                    if (usedCount < qty && (usedKg + w) <= kgLimit) {
                        usedCount++;
                        usedKg += w;
                        return 'inclus';
                    }
                    return 'exces';
                });
            },
            franchiseUsed() {
                const cls = this.bagClassification();
                return {
                    count: cls.filter(c => c === 'inclus').length,
                    kg:    this.bags.reduce((s, b, i) =>
                               cls[i] === 'inclus' ? s + (parseFloat(b.weight_kg) || 0) : s, 0),
                };
            },
            includedBagCount() {
                return this.bagClassification().filter(c => c === 'inclus').length;
            },
            excessBagCount() {
                return this.bagClassification().filter(c => c === 'exces').length;
            },
            excessTotal() {
                const cls = this.bagClassification();
                return this.bags.reduce((sum, b, i) => {
                    if (cls[i] !== 'exces') return sum;
                    /* Prix via tarif bagage si disponible, sinon via catégorie fret */
                    const baggagePrice = b.price_result?.total || 0;
                    const fretPrice    = b.fretPrice || 0;
                    return sum + Math.max(baggagePrice, fretPrice);
                }, 0);
            },
            discountAmount() {
                if (this.tariffStatus !== 'ok') return 0;
                const d   = parseInt(this.discountFcfa || 0) || 0;
                const max = Math.max(0, parseInt(this.priceInput || 1) - 1);
                return Math.min(d, max);
            },
            netTicketPrice() {
                const base = parseInt(this.priceInput || 0) || 0;
                if (this.tariffStatus === 'ok') {
                    return Math.max(0, base - this.discountAmount());
                }
                return Math.max(0, base);
            },
            grandTotal() {
                return this.netTicketPrice() + this.excessTotal();
            },
            excessBagsHaveNature() {
                /* Si aucun tarif bagage configuré → la facturation passe par les catégories fret,
                   donc on vérifie que chaque bagage excédentaire a bien une catégorie fret. */
                const cls = this.bagClassification();
                if (!BAGGAGE_TARIFFS.length) {
                    return this.bags.every((b, i) =>
                        cls[i] !== 'exces' || (b.fret_category_slug && b.fret_category_slug !== '')
                    );
                }
                return this.bags.every((b, i) =>
                    cls[i] !== 'exces' || (b.baggage_tariff_id && parseInt(b.baggage_tariff_id) > 0)
                );
            },

            /* ── fret categories combobox ───────────────────────────────── */
            fretFiltered(bag) {
                if (!bag.fretQuery || bag.fret_category_slug) return FRET_CATEGORIES;
                const q = bag.fretQuery.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
                return FRET_CATEGORIES.filter(c => {
                    const l = c.label.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
                    return l.includes(q);
                });
            },
            selectFretCategory(bag, cat) {
                bag.fret_category_slug = cat.slug;
                bag.fretQuery          = cat.label;
                bag.fretOpen           = false;
                this.computeFretPrice(bag);
                this.$nextTick(() => lucide.createIcons({ attrs: { 'stroke-width': '2' } }));
            },
            computeFretPrice(bag) {
                if (!bag.fret_category_slug) { bag.fretPrice = 0; return; }
                const cat = FRET_CATEGORIES.find(c => c.slug === bag.fret_category_slug);
                if (!cat) { bag.fretPrice = 0; return; }
                const idx = this.bags.findIndex(b => b.uid === bag.uid);
                const cls = this.bagClassification();
                if (cls[idx] !== 'exces') { bag.fretPrice = 0; return; }
                const excessW = Math.round(this.excessWeightFor(bag.uid));
                bag.fretPrice = parseInt(cat.price_per_kg) * excessW;
            },
            allBagsHaveFretCategory() {
                if (FRET_CATEGORIES.length === 0) return true; // aucune catégorie configurée
                return this.bags.every(b => b.fret_category_slug !== '');
            },

            /* ── données pour la soumission ─────────────────────────────── */
            bagsForSubmit() {
                const cls = this.bagClassification();
                return this.bags.map((b, i) => ({
                    description:       b.description || '',
                    weight_kg:         parseFloat(b.weight_kg) || 0,
                    baggage_tariff_id: b.baggage_tariff_id ? parseInt(b.baggage_tariff_id) : null,
                    is_excess:         cls[i] === 'exces' ? 1 : 0,
                    fret_category_slug: b.fret_category_slug || '',
                    fret_price:         b.fretPrice || 0,
                    excess_kg_int:      cls[i] === 'exces' ? Math.round(this.excessWeightFor(b.uid)) : 0,
                }));
            },

            /* ── validation + soumission ────────────────────────────────── */
            get tariffReady() {
                if (this.tariffStatus === 'ok') return true;
                if (this.tariffStatus === 'manual') {
                    return parseInt(this.priceInput || 0) > 0
                        && this.priceNote.trim().length >= 5;
                }
                return false;
            },
            get siegeOk() {
                return !!this.seat && this.tariffReady;
            },
            get identiteOk() {
                return this.passengerName.trim().length >= 2;
            },
            canSubmit() {
                if (!this.seat) return false;
                if (this.tariffStatus === 'ok') {
                    const disc = parseInt(this.discountFcfa || 0) || 0;
                    if (disc > 0 && this.discountReason.trim().length < 5) return false;
                    if (disc > 0 && disc >= parseInt(this.priceInput || 0)) return false;
                } else if (this.tariffStatus === 'manual') {
                    if (parseInt(this.priceInput || 0) <= 0)      return false;
                    if (this.priceNote.trim().length < 5)          return false;
                } else {
                    return false;
                }
                if (this.passengerName.trim().length < 2)    return false;
                if (!this.bagsVisited)                       return false;
                if (this.excessBagCount() > 0 && !this.excessBagsHaveNature()) return false;
                if (!this.allBagsHaveFretCategory()) return false;
                return true;
            },
            submitForm(formEl) {
                this.submitError = '';
                if (this.submitting) return;
                if (!this.canSubmit()) {
                    if (!this.bagsVisited) {
                        this.submitError = 'Veuillez valider l\'étape Bagages avant de soumettre.';
                    } else if (this.tariffStatus === 'ok') {
                        const disc = parseInt(this.discountFcfa || 0) || 0;
                        if (disc > 0 && this.discountReason.trim().length < 5)
                            this.submitError = 'Motif de réduction obligatoire (5 caractères min).';
                        else if (disc > 0 && disc >= parseInt(this.priceInput || 0))
                            this.submitError = 'La réduction ne peut pas dépasser ou égaler le tarif.';
                        else
                            this.submitError = 'Complétez tous les champs obligatoires.';
                    } else if (this.tariffStatus === 'manual') {
                        if (!(parseInt(this.priceInput || 0) > 0))
                            this.submitError = 'Saisissez un prix valide (prix hors tarif).';
                        else if (this.priceNote.trim().length < 5)
                            this.submitError = 'Le motif du prix hors tarif est obligatoire (min. 5 caractères).';
                        else
                            this.submitError = 'Complétez tous les champs obligatoires.';
                    } else if (this.excessBagCount() > 0 && !this.excessBagsHaveNature()) {
                        this.submitError = 'Renseignez la nature de chaque bagage excédentaire.';
                    } else {
                        this.submitError = 'Complétez tous les champs obligatoires.';
                    }
                    return;
                }
                this.submitting = true;
                formEl.submit();
            },

            /* ── libellé du périmètre tarifaire résolu ────────────────── */
            scopeLabel() {
                const parts = [];
                if (this.type) parts.push(this._typeLabel(this.type));
                if (this.cat && this.cat !== 'adulte') parts.push(this._catLabel(this.cat));
                if (this.cls && this.cls !== 'standard') parts.push(this._clsLabel(this.cls));
                if (this.destStopId) parts.push('→ ' + this.destStopLabel());
                return parts.join(' · ') || '—';
            },

            /* ── utilitaires labels ─────────────────────────────────────── */
            _typeLabel(v) {
                const found = ALL_TYPES.find(t => t.value === v);
                if (found) return found.label;
                const map = { aller_simple:'Aller simple', aller_retour:'Aller-retour', abonnement:'Abonnement', groupe:'Groupe', passager:'Passager', excursion:'Excursion', charter:'Affrètement' };
                return map[v] ?? v;
            },
            _catLabel(v) {
                const found = ALL_CATS.find(c => c.value === v);
                if (found) return found.label;
                const map = { adulte:'Adulte', enfant:'Enfant', senior:'Senior', etudiant:'Étudiant', vip:'VIP', militaire:'Militaire' };
                return map[v] ?? v;
            },
            _clsLabel(v) {
                const found = ALL_CLASSES.find(c => c.value === v);
                if (found) return found.label;
                const map = { standard:'Standard', affaires:'Affaires', premium:'Premium', vip:'VIP' };
                return map[v] ?? v;
            },

            /* ── libellé arrêt destination ─────────────────────────────── */
            destStopLabel() {
                if (!this.destStopId) return STOPS.length ? STOPS[STOPS.length - 1].name : 'Terminus';
                const s = STOPS.find(s => s.id === parseInt(this.destStopId));
                return s ? s.name : (STOPS.length ? STOPS[STOPS.length - 1].name : 'Terminus');
            },

            /* ── formateur FCFA ─────────────────────────────────────────── */
            fcfa(n) {
                return new Intl.NumberFormat('fr-FR').format(Math.round(n || 0)) + ' FCFA';
            },
        };
    };
})();
</script>
<?php $view->end() ?>
