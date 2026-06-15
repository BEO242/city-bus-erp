<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');

$typeDefaults = [
    'passage_arret'   => ['icon' => 'map-pin-off',  'defColor' => '#C62828', 'desc' => 'Ticket rouge — colis/passager avec arrêt anticipé. 4 sections : 2 talons + billet central + stub agence.'],
    'passage_final'   => ['icon' => 'flag',           'defColor' => '#1A237E', 'desc' => 'Ticket bleu marine — billet passager destination finale. 4 sections : 2 talons + billet central + stub agence.'],
    'bagage_excedent' => ['icon' => 'package-plus',  'defColor' => '#F57C00', 'desc' => 'Ticket orange — bagages/colis excédentaires hors quota inclus. 4 sections avec Nombre de colis.'],
    'bagage_inclus'   => ['icon' => 'package-check', 'defColor' => '#1A237E', 'desc' => 'Ticket bleu marine — bagages inclus dans le prix du billet. 4 sections avec Nombre de colis.'],
    'talon_arret'     => ['icon' => 'ticket',        'defColor' => '#C62828', 'desc' => 'Talon rouge — accompagne le billet passager arrêt anticipé. 3 sections : 2 talons info + billet principal.'],
];
?>
<?php $view->start('content') ?>
<div class="max-w-3xl mx-auto space-y-5">

  <!-- Fil d'Ariane -->
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('billetterie/preprint')) ?>" class="hover:text-cb-primary transition inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Pré-imprimés
    </a>
    <span>/</span>
    <span class="text-slate-800 font-medium">Configuration des types</span>
  </div>

  <!-- En-tête -->
  <div class="bg-gradient-to-br from-cb-primary to-cb-dark text-white rounded-2xl p-6 shadow-soft">
    <div class="flex items-center gap-4">
      <span class="w-14 h-14 bg-white/15 rounded-2xl flex items-center justify-center shrink-0">
        <i data-lucide="palette" class="w-7 h-7"></i>
      </span>
      <div>
        <h1 class="text-2xl font-bold">Configuration des types de tickets</h1>
        <p class="text-white/70 text-sm mt-0.5">
          Définissez les couleurs et libellés de chaque type de billet/talon pré-imprimé.
        </p>
      </div>
    </div>
  </div>

  <!-- Formulaire -->
  <form method="post" action="<?= e(url('billetterie/preprint/config')) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="space-y-4">
    <?php foreach ($typeDefaults as $typeKey => $td):
      $cfg      = $typeConfigs[$typeKey] ?? [];
      $bgColor  = $cfg['color']      ?? $td['defColor'];
      $fgColor  = $cfg['text_color'] ?? '#FFFFFF';
      $lbl      = $cfg['label']      ?? $typeKey;
      $desc     = $cfg['description'] ?? $td['desc'];
      $prefixDefaults = ['passage_arret'=>'CB-PA','passage_final'=>'CB-PF','bagage_excedent'=>'CB-BE','bagage_inclus'=>'CB-BI','talon_arret'=>'CB-TA'];
      $numPrefix = $cfg['number_prefix'] ?? ($prefixDefaults[$typeKey] ?? 'CB-PP');
      $variant  = $cfg['layout_variant'] ?? 'A';
      $rowHeight = (int)($cfg['row_height_mm'] ?? ($typeKey === 'talon_arret' ? 80 : 62));
      $showQr = !array_key_exists('show_qr', $cfg) || !empty($cfg['show_qr']);
      $showCompanyContact = !array_key_exists('show_company_contact', $cfg) || !empty($cfg['show_company_contact']);
      $showCompanyPhone = !array_key_exists('show_company_phone', $cfg) || !empty($cfg['show_company_phone']);
      $showTripInfo = !array_key_exists('show_trip_info', $cfg) || !empty($cfg['show_trip_info']);
      $showSeatInfo = !array_key_exists('show_seat_info', $cfg) || !empty($cfg['show_seat_info']);
      $showPriceField = !array_key_exists('show_price_field', $cfg) || !empty($cfg['show_price_field']);
      $showAgencyStub = !array_key_exists('show_agency_stub', $cfg) || !empty($cfg['show_agency_stub']);
      $showPassengerReference = !array_key_exists('show_passenger_reference', $cfg) || !empty($cfg['show_passenger_reference']);
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden"
        x-data="{ bg: '<?= e($bgColor) ?>', fg: '<?= e($fgColor) ?>', variant: '<?= e($variant) ?>' }">

      <!-- En-tête de la carte avec aperçu en direct -->
      <div class="p-4 flex items-center gap-4" :style="'background:'+bg+';color:'+fg">
        <i data-lucide="<?= e($td['icon']) ?>" class="w-6 h-6 shrink-0"></i>
        <div class="flex-1">
          <p class="font-bold text-base" x-text="document.getElementById('label_<?= $typeKey ?>').value || '<?= e($lbl) ?>'"></p>
          <p class="text-xs opacity-75"><?= e($td['desc']) ?></p>
        </div>
        <span class="text-xs font-mono opacity-60" x-text="bg"></span>
      </div>

      <!-- Champs -->
      <div class="p-5 grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="cb-label">Libellé affiché sur le ticket</label>
          <input type="text" name="label_<?= e($typeKey) ?>" id="label_<?= e($typeKey) ?>"
                 value="<?= e($lbl) ?>" required maxlength="60"
                 class="cb-input w-full"
                 @input="$el.closest('[x-data]').querySelector('.preview-label') && null">
        </div>

        <div>
          <label class="cb-label">Couleur de fond</label>
          <div class="flex items-center gap-2 mt-1">
            <input type="color" name="color_<?= e($typeKey) ?>"
                   value="<?= e($bgColor) ?>"
                   x-model="bg"
                   class="h-10 w-16 rounded-xl border border-slate-200 cursor-pointer p-1">
            <code class="text-sm font-mono bg-slate-100 px-3 py-2 rounded-xl flex-1" x-text="bg"></code>
          </div>
        </div>

        <div>
          <label class="cb-label">Couleur du texte</label>
          <div class="flex items-center gap-2 mt-1">
            <input type="color" name="text_color_<?= e($typeKey) ?>"
                   value="<?= e($fgColor) ?>"
                   x-model="fg"
                   class="h-10 w-16 rounded-xl border border-slate-200 cursor-pointer p-1">
            <code class="text-sm font-mono bg-slate-100 px-3 py-2 rounded-xl flex-1" x-text="fg"></code>
          </div>
          <!-- Boutons rapides -->
          <div class="flex gap-2 mt-2">
            <button type="button" @click="fg='#FFFFFF'"
                    class="px-3 py-1 rounded-lg bg-slate-800 text-white text-xs font-medium hover:bg-slate-600 transition">
              Blanc
            </button>
            <button type="button" @click="fg='#1A237E'"
                    class="px-3 py-1 rounded-lg border border-slate-200 text-xs font-medium hover:bg-slate-50 transition"
                    style="color:#1A237E;">
              Bleu foncé
            </button>
            <button type="button" @click="fg='#111111'"
                    class="px-3 py-1 rounded-lg border border-slate-200 text-xs font-medium hover:bg-slate-50 transition">
              Noir
            </button>
          </div>
        </div>

        <div class="col-span-2">
          <label class="cb-label">Description (optionnel)</label>
          <textarea name="description_<?= e($typeKey) ?>" rows="2"
                    class="cb-input w-full resize-none text-sm"
                    placeholder="Description interne…"><?= e($desc) ?></textarea>
        </div>

        <div class="col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between gap-3 mb-3">
            <div>
              <p class="text-sm font-semibold text-slate-800">Mini aperçu des modèles</p>
              <p class="text-xs text-slate-500">Le cadre accentué correspond au modèle actuellement sélectionné.</p>
            </div>
            <span class="text-[11px] font-mono uppercase tracking-[0.18em] text-slate-400" x-text="'Modèle ' + variant"></span>
          </div>
          <div class="grid md:grid-cols-2 gap-3">
            <div class="rounded-2xl border p-3 transition"
                 :class="variant === 'A' ? 'border-cb-primary ring-2 ring-cb-primary/15 bg-white' : 'border-slate-200 bg-white/80'">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-700">Modèle A</span>
                <span class="text-[10px] uppercase tracking-[0.16em] text-slate-400">Classique</span>
              </div>
              <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <div class="h-8 px-2 flex items-center justify-between text-[10px] font-semibold" :style="'background:'+bg+';color:'+fg">
                  <span>Header</span>
                  <span>N° + contact</span>
                </div>
                <div class="flex h-20">
                  <div class="w-[32%] border-r border-dashed border-slate-300 p-2 text-[10px] text-slate-500">
                    <div class="font-semibold text-slate-700">Numéro</div>
                    <div class="mt-1">Date</div>
                    <div class="mt-2 rounded border border-dashed border-slate-300 h-8 flex items-center justify-center">QR</div>
                  </div>
                  <div class="flex-1 p-2 text-[10px] text-slate-500 space-y-1.5">
                    <div class="h-2 rounded bg-slate-200"></div>
                    <div class="h-2 rounded bg-slate-200"></div>
                    <div class="h-2 rounded bg-slate-200"></div>
                    <div class="h-2 rounded bg-slate-200 w-4/5"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="rounded-2xl border p-3 transition"
                 :class="variant === 'B' ? 'border-cb-primary ring-2 ring-cb-primary/15 bg-white' : 'border-slate-200 bg-white/80'">
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-700">Modèle B</span>
                <span class="text-[10px] uppercase tracking-[0.16em] text-slate-400">Alternatif</span>
              </div>
              <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <div class="h-8 px-2 flex items-center justify-between text-[10px] font-semibold" :style="'background:'+bg+';color:'+fg">
                  <span>Header</span>
                  <span>Logo</span>
                </div>
                <div class="flex h-20">
                  <div class="flex-1 p-2 text-[10px] text-slate-500 space-y-1.5 border-r border-slate-200">
                    <div class="h-2 rounded bg-slate-200"></div>
                    <div class="h-2 rounded bg-slate-200"></div>
                    <div class="h-2 rounded bg-slate-200"></div>
                    <div class="h-2 rounded bg-slate-200 w-3/4"></div>
                  </div>
                  <div class="w-[30%] p-2 text-[10px] text-slate-500">
                    <div class="font-semibold text-slate-700">Bloc latéral</div>
                    <div class="mt-1">N°</div>
                    <div class="mt-2 rounded border border-dashed border-slate-300 h-8 flex items-center justify-center">QR</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="cb-label">Modèle à utiliser</label>
          <select name="layout_variant_<?= e($typeKey) ?>" class="cb-input w-full" x-model="variant">
            <option value="A" <?= $variant === 'A' ? 'selected' : '' ?>>Modèle A — disposition classique</option>
            <option value="B" <?= $variant === 'B' ? 'selected' : '' ?>>Modèle B — disposition alternative</option>
          </select>
          <p class="text-xs text-slate-400 mt-1">Le modèle sélectionné sera utilisé lors de la génération du PDF.</p>
        </div>

        <div>
          <label class="cb-label">Hauteur du support (mm)</label>
          <input type="number" name="row_height_mm_<?= e($typeKey) ?>"
                 value="<?= e((string)$rowHeight) ?>"
                 min="<?= $typeKey === 'talon_arret' ? '70' : '55' ?>"
                 max="<?= $typeKey === 'talon_arret' ? '95' : '75' ?>"
                 class="cb-input w-full text-sm font-mono">
          <p class="text-xs text-slate-400 mt-1">Valeur conseillée : <?= $typeKey === 'talon_arret' ? '80' : '62' ?> mm.</p>
        </div>

        <div>
          <label class="cb-label">Préfixe de numérotation</label>
          <input type="text" name="number_prefix_<?= e($typeKey) ?>"
                 value="<?= e($numPrefix) ?>"
                 maxlength="12" pattern="[A-Za-z0-9\-]{2,12}"
                 class="cb-input w-full uppercase text-sm font-mono"
                 placeholder="Ex: CB-PA">
          <p class="text-xs text-slate-400 mt-1">Format: <?= e($numPrefix) ?>-2026-00001 &mdash; lettres, chiffres et tirets, 2 à 12 caractères.</p>
        </div>

        <div class="col-span-2">
          <label class="cb-label">Informations à afficher</label>
          <div class="grid md:grid-cols-2 gap-3 mt-2">
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
              <input type="checkbox" name="show_qr_<?= e($typeKey) ?>" value="1" <?= $showQr ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
              <span>Afficher le QR code</span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
              <input type="checkbox" name="show_company_contact_<?= e($typeKey) ?>" value="1" <?= $showCompanyContact ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
              <span>Afficher les contacts société</span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
              <input type="checkbox" name="show_company_phone_<?= e($typeKey) ?>" value="1" <?= $showCompanyPhone ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
              <span>Afficher uniquement le téléphone société</span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
              <input type="checkbox" name="show_trip_info_<?= e($typeKey) ?>" value="1" <?= $showTripInfo ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
              <span>Afficher la date et les informations de trajet</span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
              <input type="checkbox" name="show_seat_info_<?= e($typeKey) ?>" value="1" <?= $showSeatInfo ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
              <span>Afficher siège et bus quand disponibles</span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 md:col-span-2">
              <input type="checkbox" name="show_price_field_<?= e($typeKey) ?>" value="1" <?= $showPriceField ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
              <span>Afficher la zone prix / tarif sur le support</span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
              <input type="checkbox" name="show_agency_stub_<?= e($typeKey) ?>" value="1" <?= $showAgencyStub ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
              <span>Afficher le bloc agence sur le talon agence</span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
              <input type="checkbox" name="show_passenger_reference_<?= e($typeKey) ?>" value="1" <?= $showPassengerReference ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-cb-primary">
              <span>Afficher la référence billet passager</span>
            </label>
          </div>
        </div>
      </div>

    </div>
    <?php endforeach ?>
    </div>

    <!-- Barre d'actions -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft px-6 py-4 flex items-center justify-between mt-4">
      <a href="<?= e(url('billetterie/preprint')) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 transition text-sm font-medium inline-flex items-center gap-2">
        <i data-lucide="x" class="w-4 h-4"></i> Annuler
      </a>
      <button type="submit"
              class="px-6 py-2.5 rounded-xl bg-cb-primary text-white font-bold hover:bg-cb-dark transition shadow-soft inline-flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i> Enregistrer la configuration
      </button>
    </div>

  </form>

</div>
<?php $view->end() ?>
