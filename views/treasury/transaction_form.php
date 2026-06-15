<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$isEnc = $txType === 'encaissement';
$accentCls = $isEnc ? 'text-emerald-700' : 'text-rose-700';
$bgCls     = $isEnc ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700';
$iconTx    = $isEnc ? 'arrow-down-circle' : 'arrow-up-circle';
?>
<div class="space-y-5 max-w-2xl mx-auto">

  <div class="flex items-center gap-3">
    <a href="<?= e(url('finance/treasury')) ?>" class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <i data-lucide="<?= $iconTx ?>" class="w-5 h-5 <?= $accentCls ?>"></i> <?= e($title) ?>
      </h1>
    </div>
  </div>

  <form method="post" action="<?= e(url('finance/treasury/transaction')) ?>" class="space-y-4"
        x-data="{ amount: 0 }">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= e($txType) ?>">

    <!-- Caisse & Catégorie -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="wallet" class="w-4 h-4 text-cb-primary"></i> Informations
      </h2>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Caisse <span class="text-rose-500">*</span></label>
        <select name="cash_register_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
          <option value="">-- Sélectionner --</option>
          <?php foreach ($registers as $r): ?>
          <option value="<?= $r['id'] ?>" <?= $userRegisterId === (int)$r['id'] ? 'selected' : '' ?>>
            <?= e($r['agency_name']) ?> — <?= e($r['first_name'] . ' ' . $r['last_name']) ?>
          </option>
          <?php endforeach ?>
        </select>
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Catégorie <span class="text-rose-500">*</span></label>
        <?php
        // Groupement logique par plage de sort_order
        $catGroups = [];
        foreach ($categories as $cat) {
            $s = (int)($cat['sort_order'] ?? 100);
            if ($s <= 9)       $g = $isEnc ? 'Ventes & recettes' : 'Recettes';
            elseif ($s <= 19)  $g = 'Opérations bancaires';
            elseif ($s <= 29)  $g = 'Exploitation véhicules';
            elseif ($s <= 49)  $g = 'Personnel';
            elseif ($s <= 59)  $g = 'Fonctionnement & admin';
            elseif ($s <= 69)  $g = 'Remboursements';
            else               $g = 'Autre';
            $catGroups[$g][] = $cat;
        }
        ?>
        <select name="category_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
          <option value="">-- Sélectionner --</option>
          <?php foreach ($catGroups as $groupLabel => $groupCats): ?>
          <optgroup label="<?= e($groupLabel) ?>">
            <?php foreach ($groupCats as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= e($cat['label']) ?></option>
            <?php endforeach ?>
          </optgroup>
          <?php endforeach ?>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Montant (FCFA) <span class="text-rose-500">*</span></label>
          <input type="number" name="amount_fcfa" required min="1" step="1"
                 x-model.number="amount"
                 placeholder="0"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Mode de paiement</label>
          <select name="payment_method" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="especes">Espèces</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="carte">Carte bancaire</option>
            <option value="virement">Virement</option>
            <option value="cheque">Chèque</option>
          </select>
        </div>
      </div>

      <!-- Aperçu montant -->
      <div x-show="amount > 0" class="p-3 rounded-xl <?= $isEnc ? 'bg-emerald-50' : 'bg-rose-50' ?> text-center">
        <span class="text-lg font-black <?= $accentCls ?>" x-text="new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA'"></span>
      </div>
    </div>

    <!-- Rattachement optionnel -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="link" class="w-4 h-4 text-cb-primary"></i> Rattachement <span class="text-slate-400 font-normal text-xs ml-1">(optionnel)</span>
      </h2>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Bus</label>
          <select name="bus_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="">-- Aucun --</option>
            <?php foreach ($buses as $b): ?>
            <option value="<?= $b['id'] ?>"><?= e($b['plate']) ?><?= $b['code'] ? ' — ' . e($b['code']) : '' ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Voyage</label>
          <select name="trip_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="">-- Aucun --</option>
            <?php foreach ($trips as $t): ?>
            <option value="<?= $t['id'] ?>">#<?= e($t['trip_code']) ?> · <?= e($t['line_name']) ?> · <?= e(date('H:i', strtotime($t['departure_scheduled']))) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Description & Référence -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="file-text" class="w-4 h-4 text-cb-primary"></i> Détails
      </h2>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Description</label>
        <textarea name="description" rows="2" maxlength="500" placeholder="Motif, détails…"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm resize-none"></textarea>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Référence externe</label>
        <input type="text" name="reference" maxlength="100" placeholder="N° facture, reçu…"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
      <a href="<?= e(url('finance/treasury')) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
        Annuler
      </a>
      <button type="submit"
              class="px-6 py-2.5 rounded-xl <?= $bgCls ?> text-white text-sm font-bold transition flex items-center gap-2">
        <i data-lucide="<?= $iconTx ?>" class="w-4 h-4"></i>
        Enregistrer
      </button>
    </div>
  </form>
</div>
<?php $view->end() ?>
