<?php
$view->extends('layouts/app');
$categoriesJson = json_encode($categories, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$stopsJson      = json_encode($stops ?? [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$tripStopsJson  = json_encode($tripStops ?? [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$tripsJson      = json_encode($availableTrips ?? [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
$calcUrl = e(url('operations/fret/calc-price'));
$storeUrl = e(url('operations/fret'));
?>

<?php $view->start('content'); ?>

<!-- Breadcrumb + Title -->
<div class="mb-6">
    <nav class="flex items-center gap-2 text-sm text-slate-500 mb-2">
        <a href="<?= e(url('operations/fret')) ?>" class="hover:text-cb-primary transition-colors">Fret &amp; Bagages</a>
        <i data-lucide="chevron-right" class="w-4 h-4"></i>
        <span class="text-slate-700 font-medium"><?= e($title) ?></span>
    </nav>
    <h1 class="text-2xl font-bold text-slate-800"><?= e($title) ?></h1>
</div>

<?php if ($mode === 'baggage'): ?>
<!-- ═══════════════════════════════════════════════════════════════════════
     MODE BAGAGE (inchangé)
     ═══════════════════════════════════════════════════════════════════════ -->
<?php if ($trip): ?>
<?php
    $cargoCapacity   = (float)($trip['cargo_capacity_kg'] ?? 0);
    $currentLoadKg   = (float)($fretLoad['total_weight_kg'] ?? 0);
    $loadedItems     = (int)($fretLoad['items_count'] ?? 0);
    $loadPct         = $cargoCapacity > 0 ? round(($currentLoadKg / $cargoCapacity) * 100) : 0;
    $isNearCapacity  = $cargoCapacity > 0 && $loadPct >= 80;
    $isOverCapacity  = $cargoCapacity > 0 && $currentLoadKg >= $cargoCapacity;
?>
<div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-4">
    <div class="flex items-center gap-3">
        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
            <i data-lucide="bus" class="w-5 h-5 text-blue-600"></i>
        </div>
        <div class="flex-1 grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
            <div><span class="text-xs font-semibold text-slate-500 block">Ligne</span><span class="font-medium text-slate-800"><?= e($trip['line_name']) ?> (<?= e($trip['line_code']) ?>)</span></div>
            <div><span class="text-xs font-semibold text-slate-500 block">Date</span><span class="font-medium text-slate-800"><?= e($trip['trip_date']) ?></span></div>
            <div><span class="text-xs font-semibold text-slate-500 block">Départ prévu</span><span class="font-medium text-slate-800"><?= e($trip['departure_scheduled']) ?></span></div>
            <div><span class="text-xs font-semibold text-slate-500 block">Bus</span><span class="font-medium text-slate-800"><?= e($trip['bus_code']) ?></span></div>
            <div><span class="text-xs font-semibold text-slate-500 block">Fret chargé</span><span class="font-medium <?= $isOverCapacity ? 'text-red-700' : ($isNearCapacity ? 'text-amber-700' : 'text-slate-800') ?>"><?= number_format($currentLoadKg, 1, ',', ' ') ?> kg (<?= $loadedItems ?> item(s))</span></div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
    <div class="flex items-center gap-3">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600"></i>
        <p class="text-sm text-amber-800">Aucun voyage sélectionné. <a href="<?= e(url('billetterie/select-trip')) ?>" class="font-semibold underline hover:text-amber-900">Choisir un voyage</a></p>
    </div>
</div>
<?php endif; ?>

<!-- Formulaire bagage (simple, identique à l'ancien) -->
<form action="<?= $storeUrl ?>" method="POST" x-data="fretForm()" class="space-y-6">
    <?= csrf_field() ?>
    <input type="hidden" name="item_type" value="baggage">
    <?php if ($trip): ?><input type="hidden" name="trip_id" value="<?= e($trip['id']) ?>"><?php endif; ?>
    <input type="hidden" name="category_slug" :value="categorySlug">
    <input type="hidden" name="passenger_ticket_id" :value="passengerTicketId">

    <!-- Catégorie -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-4">Catégorie fret</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <?php foreach ($categories as $cat): ?>
            <button type="button"
                @click="selectCategory(<?= htmlspecialchars(json_encode($cat, JSON_HEX_TAG | JSON_HEX_APOS), ENT_QUOTES) ?>)"
                :class="categorySlug === '<?= e($cat['slug']) ?>' ? 'ring-2 ring-cb-primary border-cb-primary shadow-sm' : 'border-gray-200 hover:border-gray-300'"
                class="relative flex flex-col items-start p-4 rounded-xl border transition-all text-left">
                <span class="font-bold text-slate-800 text-sm mb-1"><?= e($cat['label']) ?></span>
                <span class="text-xs text-slate-600"><?= number_format($cat['price_per_kg'], 0, ',', ' ') ?> F/kg</span>
                <div x-show="categorySlug === '<?= e($cat['slug']) ?>'" class="absolute top-2 right-2"><i data-lucide="check-circle-2" class="w-4 h-4 text-cb-primary"></i></div>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Expéditeur -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-4">Informations expéditeur</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php if (!empty($passengers)): ?>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Passager associé</label>
                <select x-model="passengerTicketId" @change="onPassengerSelect()" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary">
                    <option value="">— Saisie manuelle —</option>
                    <?php foreach ($passengers as $p): ?>
                    <option value="<?= e($p['id']) ?>">Siège <?= e($p['seat_number']) ?> — <?= e($p['passenger_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="sender_name" x-ref="senderName" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="Nom complet">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Téléphone</label>
                <input type="text" name="sender_phone" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="6XX XXX XXX">
            </div>
        </div>
    </div>

    <!-- Détails -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-4">Détails du bagage</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Poids (kg) <span class="text-red-500">*</span></label>
                <input type="number" name="weight_kg" step="0.1" min="0" required x-model.number="weightKg" @input="recalcPrice()" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="0.0">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Pièces</label>
                <input type="number" name="pieces_count" min="1" value="1" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Description</label>
                <input type="text" name="description" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="Optionnel">
            </div>
        </div>
    </div>

    <!-- Franchise -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_franchise" x-model="isFranchise" @change="recalcPrice()" class="w-5 h-5 rounded-md border-gray-300 text-cb-primary focus:ring-cb-primary/20">
            <span class="text-sm text-slate-700">Bagage en franchise (inclus dans le billet)</span>
        </label>
    </div>

    <!-- Tarification -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div class="text-sm text-slate-600">
                <template x-if="isFranchise"><span class="text-green-700 font-medium">Franchise — 0 FCFA</span></template>
                <template x-if="!isFranchise && categorySlug && weightKg > 0">
                    <span><span x-text="weightKg"></span> kg × <span x-text="new Intl.NumberFormat('fr-FR').format(pricePerKg)"></span> F/kg</span>
                </template>
            </div>
            <span class="text-2xl font-bold text-cb-primary" x-text="priceDisplay"></span>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="<?= e(url('operations/fret')) ?>" class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-slate-600 hover:bg-gray-50 transition-colors">Annuler</a>
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-medium hover:bg-cb-primary/90 transition-colors shadow-sm">
            <i data-lucide="package-plus" class="w-4 h-4"></i> Enregistrer
        </button>
    </div>
</form>

<script>
function fretForm() {
    return {
        mode: 'baggage',
        categories: <?= $categoriesJson ?>,
        categorySlug: '', selectedCategory: null, passengerTicketId: '',
        weightKg: 0, isFranchise: false, pricePerKg: 0, minPrice: 0, totalPrice: 0,
        selectCategory(cat) { this.categorySlug = cat.slug; this.pricePerKg = cat.price_per_kg; this.minPrice = cat.min_price_fcfa; this.recalcPrice(); },
        onPassengerSelect() {
            if (this.passengerTicketId) {
                const passengers = <?= json_encode($passengers ?? [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
                const p = passengers.find(x => x.id == this.passengerTicketId);
                if (p) this.$refs.senderName.value = p.passenger_name;
            }
        },
        recalcPrice() {
            if (this.isFranchise) { this.totalPrice = 0; return; }
            if (!this.categorySlug || this.weightKg <= 0) { this.totalPrice = 0; return; }
            this.totalPrice = Math.max(this.minPrice, Math.ceil(this.weightKg * this.pricePerKg));
        },
        get priceDisplay() {
            if (this.isFranchise) return 'Franchise';
            if (this.totalPrice === 0) return '0 FCFA';
            return new Intl.NumberFormat('fr-FR').format(this.totalPrice) + ' FCFA';
        }
    };
}
</script>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════════════
     MODE COLIS — Formulaire multi-colis avec voyage obligatoire
     ═══════════════════════════════════════════════════════════════════════ -->

<?php if (!$trip): ?>
<!-- ── Étape 1 : Sélection du voyage (obligatoire) ────────────────────── -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-soft overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 bg-amber-50">
        <div class="flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600"></i>
            <h2 class="text-sm font-bold text-amber-800">Sélectionnez un voyage avant de continuer</h2>
        </div>
        <p class="text-xs text-amber-700 mt-1">Le voyage détermine les arrêts disponibles, la date de départ/arrivée et les informations logistiques.</p>
    </div>

    <div class="p-6 space-y-4">
        <?php if (empty($availableTrips)): ?>
        <div class="text-center py-8">
            <i data-lucide="calendar-x" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
            <p class="text-sm text-slate-500 font-medium">Aucun voyage planifié disponible</p>
            <p class="text-xs text-slate-400 mt-1">Planifiez d'abord un voyage depuis le module Opérations.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-100 max-h-[28rem] overflow-y-auto rounded-xl border border-slate-100">
            <?php foreach ($availableTrips as $t): ?>
            <a href="<?= e(url('operations/fret/create')) ?>?mode=colis&trip_id=<?= (int)$t['id'] ?>"
               class="flex items-center gap-4 px-4 py-3 hover:bg-cb-bg transition group">
                <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 group-hover:bg-cb-primary group-hover:text-white transition">
                    <i data-lucide="bus" class="w-4 h-4"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-slate-800"><?= e($t['line_code']) ?></span>
                        <span class="text-xs text-slate-500"><?= e($t['departure_city_name']) ?> → <?= e($t['arrival_city_name']) ?></span>
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5">
                        <?= e($t['trip_date']) ?> · Départ <?= e($t['departure_scheduled'] ?? '—') ?>
                        <?php if (!empty($t['arrival_scheduled'])): ?> · Arrivée prévue <?= e($t['arrival_scheduled']) ?><?php endif; ?>
                        · Bus <?= e($t['bus_code']) ?>
                    </div>
                </div>
                <div class="shrink-0">
                    <span class="text-[10px] font-bold uppercase px-2 py-1 rounded-lg
                        <?= $t['status'] === 'embarquement' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700' ?>">
                        <?= e($t['status']) ?>
                    </span>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 group-hover:text-cb-primary transition shrink-0"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ── Étape 2 : Formulaire multi-colis (voyage sélectionné) ──────────── -->

<!-- Bandeau voyage -->
<div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-4">
    <div class="flex items-center gap-3">
        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
            <i data-lucide="bus" class="w-5 h-5 text-blue-600"></i>
        </div>
        <div class="flex-1 grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
            <div><span class="text-xs font-semibold text-slate-500 block">Ligne</span><span class="font-medium text-slate-800"><?= e($trip['line_code']) ?> — <?= e($trip['line_name']) ?></span></div>
            <div><span class="text-xs font-semibold text-slate-500 block">Date</span><span class="font-medium text-slate-800"><?= e($trip['trip_date']) ?></span></div>
            <div><span class="text-xs font-semibold text-slate-500 block">Départ</span><span class="font-medium text-slate-800"><?= e($trip['departure_scheduled'] ?? '—') ?></span></div>
            <div><span class="text-xs font-semibold text-slate-500 block">Trajet</span><span class="font-medium text-slate-800"><?= e($trip['departure_city_name'] ?? '') ?> → <?= e($trip['arrival_city_name'] ?? '') ?></span></div>
            <div><span class="text-xs font-semibold text-slate-500 block">Bus</span><span class="font-medium text-slate-800"><?= e($trip['bus_code']) ?></span></div>
        </div>
        <a href="<?= e(url('operations/fret/create')) ?>?mode=colis"
           class="shrink-0 px-3 py-1.5 rounded-lg border border-blue-300 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition">
            Changer
        </a>
    </div>
</div>

<form action="<?= $storeUrl ?>" method="POST" x-data="colisForm()" class="space-y-6">
    <?= csrf_field() ?>
    <input type="hidden" name="item_type" value="colis">
    <input type="hidden" name="trip_id" value="<?= e($trip['id']) ?>">
    <input type="hidden" name="items_json" :value="itemsJson">

    <!-- Expéditeur & Destinataire -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-4">Expéditeur & Destinataire</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Expéditeur -->
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-slate-700 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-slate-500"></span> Expéditeur</h3>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="sender_name" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="Nom complet">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Téléphone</label>
                    <input type="text" name="sender_phone" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="6XX XXX XXX">
                </div>
            </div>
            <!-- Destinataire -->
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-rose-700 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Destinataire</h3>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="recipient_name" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="Nom complet">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Téléphone <span class="text-red-500">*</span></label>
                    <input type="text" name="recipient_phone" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="6XX XXX XXX">
                </div>
            </div>
        </div>
    </div>

    <!-- Origine & Destination (basés sur les arrêts du voyage) -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-4">Origine & Destination</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-1"></span>
                    Arrêt de prise en charge <span class="text-red-500">*</span>
                </label>
                <select name="origin_stop_id" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary">
                    <?php foreach ($tripStops as $i => $s): ?>
                    <option value="<?= e($s['id']) ?>" <?= $i === 0 ? 'selected' : '' ?>><?= e($s['name']) ?><?= $s['km_from_origin'] ? ' (' . $s['km_from_origin'] . ' km)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                    <span class="inline-block w-2 h-2 rounded-full bg-rose-500 mr-1"></span>
                    Arrêt de livraison <span class="text-red-500">*</span>
                </label>
                <select name="destination_stop_id" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary">
                    <?php foreach ($tripStops as $i => $s): ?>
                    <option value="<?= e($s['id']) ?>" <?= $i === count($tripStops) - 1 ? 'selected' : '' ?>><?= e($s['name']) ?><?= $s['km_from_origin'] ? ' (' . $s['km_from_origin'] . ' km)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Liste des colis (dynamique, multi) -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="packages" class="w-4 h-4 text-cb-primary"></i>
                Colis à enregistrer
                <span class="text-xs font-bold bg-cb-bg text-cb-primary px-2 py-0.5 rounded-full" x-text="items.length + ' colis'"></span>
            </h2>
            <button type="button" @click="addItem()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cb-primary text-white text-xs font-semibold hover:bg-cb-dark transition">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter un colis
            </button>
        </div>

        <div class="divide-y divide-slate-50">
            <template x-for="(item, idx) in items" :key="item._key">
                <div class="px-6 py-4 hover:bg-slate-50/50 transition">
                    <div class="flex items-start gap-3">
                        <!-- Numéro -->
                        <div class="w-7 h-7 rounded-full bg-cb-bg text-cb-primary flex items-center justify-center text-xs font-bold shrink-0 mt-1" x-text="idx + 1"></div>

                        <!-- Champs -->
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-[1fr_7rem_5rem_1fr] gap-3">
                            <!-- Catégorie -->
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Catégorie <span class="text-red-500">*</span></label>
                                <select x-model="item.category_slug" @change="recalcItem(idx)" class="w-full px-2.5 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat['slug']) ?>"><?= e($cat['label']) ?> (<?= number_format($cat['price_per_kg'], 0, ',', ' ') ?> F/kg)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Poids -->
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Poids (kg) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.1" min="0.1" x-model.number="item.weight_kg" @input="recalcItem(idx)"
                                    class="w-full px-2.5 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="0">
                            </div>
                            <!-- Pièces -->
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Pièces</label>
                                <input type="number" min="1" x-model.number="item.pieces_count"
                                    class="w-full px-2.5 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="1">
                            </div>
                            <!-- Description -->
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Description</label>
                                <input type="text" x-model="item.description"
                                    class="w-full px-2.5 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-cb-primary/20 focus:border-cb-primary" placeholder="Contenu (optionnel)">
                            </div>
                        </div>

                        <!-- Prix + suppression -->
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <span class="text-sm font-bold text-cb-primary whitespace-nowrap" x-text="item.price_display"></span>
                            <button type="button" @click="removeItem(idx)" x-show="items.length > 1"
                                class="p-1 rounded-lg text-slate-300 hover:text-rose-500 hover:bg-rose-50 transition">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Total -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
            <div class="text-sm text-slate-600">
                <span x-text="items.length"></span> colis · <span x-text="totalWeight.toFixed(1)"></span> kg total
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-500 block">Total à payer</span>
                <span class="text-xl font-black text-cb-primary" x-text="totalPriceDisplay"></span>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3">
        <a href="<?= e(url('operations/fret')) ?>" class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-slate-600 hover:bg-gray-50 transition-colors">Annuler</a>
        <button type="submit" :disabled="!canSubmit"
            :class="canSubmit ? 'bg-cb-primary hover:bg-cb-dark' : 'bg-slate-300 cursor-not-allowed'"
            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-white text-sm font-semibold transition-colors shadow-sm">
            <i data-lucide="package-plus" class="w-4 h-4"></i>
            Enregistrer <span x-text="items.length > 1 ? items.length + ' colis' : 'le colis'"></span>
        </button>
    </div>
</form>

<script>
function colisForm() {
    const categories = <?= $categoriesJson ?>;
    const catMap = {};
    categories.forEach(c => { catMap[c.slug] = c; });

    let _key = 1;

    function makeItem() {
        return { _key: _key++, category_slug: '', weight_kg: 0, pieces_count: 1, description: '', price: 0, price_display: '0 FCFA' };
    }

    return {
        items: [makeItem()],
        catMap: catMap,

        addItem() { this.items.push(makeItem()); },
        removeItem(idx) { this.items.splice(idx, 1); },

        recalcItem(idx) {
            const item = this.items[idx];
            const cat = this.catMap[item.category_slug];
            if (!cat || item.weight_kg <= 0) { item.price = 0; item.price_display = '0 FCFA'; return; }
            const raw = Math.ceil(item.weight_kg * cat.price_per_kg);
            item.price = Math.max(cat.min_price_fcfa || 0, raw);
            item.price_display = new Intl.NumberFormat('fr-FR').format(item.price) + ' FCFA';
        },

        get totalWeight() { return this.items.reduce((s, i) => s + (i.weight_kg || 0), 0); },
        get totalPrice() { return this.items.reduce((s, i) => s + (i.price || 0), 0); },
        get totalPriceDisplay() { return new Intl.NumberFormat('fr-FR').format(this.totalPrice) + ' FCFA'; },

        get canSubmit() {
            return this.items.length > 0 && this.items.every(i => i.category_slug !== '' && i.weight_kg > 0);
        },

        get itemsJson() {
            return JSON.stringify(this.items.map(i => ({
                category_slug: i.category_slug,
                weight_kg: i.weight_kg,
                pieces_count: i.pieces_count || 1,
                description: i.description || ''
            })));
        }
    };
}
</script>
<?php endif; // end $trip check ?>
<?php endif; // end mode check ?>

<?php $view->end(); ?>
