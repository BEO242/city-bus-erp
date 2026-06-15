<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5 max-w-3xl mx-auto" x-data="billettage()">

  <div class="flex items-center gap-3">
    <a href="<?= e(url('finance/treasury')) ?>" class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <i data-lucide="lock" class="w-5 h-5 text-cb-primary"></i> <?= e($title) ?>
      </h1>
      <p class="text-xs text-slate-400 mt-0.5">
        <?= e($register['agency_name']) ?> — <?= e($register['first_name'] . ' ' . $register['last_name']) ?>
        · Ouverte le <?= e(date('d/m/Y à H:i', strtotime($register['opened_at']))) ?>
      </p>
    </div>
  </div>

  <!-- Résumé théorique -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
    <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100 mb-4">
      <i data-lucide="calculator" class="w-4 h-4 text-cb-primary"></i> Résumé théorique
    </h2>
    <div class="grid grid-cols-3 gap-4 text-center mb-4">
      <div>
        <p class="text-xs text-slate-500 font-semibold">Fond de caisse</p>
        <p class="text-lg font-bold text-slate-700"><?= number_format((int)$register['opening_amount'], 0, ',', ' ') ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-500 font-semibold">Encaissements</p>
        <p class="text-lg font-bold text-emerald-700">+<?= number_format($totalIn, 0, ',', ' ') ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-500 font-semibold">Décaissements</p>
        <p class="text-lg font-bold text-rose-700">-<?= number_format($totalOut, 0, ',', ' ') ?></p>
      </div>
    </div>
    <div class="bg-slate-50 rounded-xl p-4 text-center">
      <p class="text-xs text-slate-500 font-semibold mb-1">Solde théorique</p>
      <p class="text-2xl font-black text-slate-900"><?= number_format($theorique, 0, ',', ' ') ?> <span class="text-sm">FCFA</span></p>
    </div>

    <?php if ($byCat): ?>
    <div class="mt-4 space-y-1">
      <p class="text-xs text-slate-500 font-semibold mb-2">Détail par catégorie :</p>
      <?php
      $colorMap = [
        'slate'=>'bg-slate-100 text-slate-700','red'=>'bg-red-100 text-red-700','orange'=>'bg-orange-100 text-orange-700',
        'amber'=>'bg-amber-100 text-amber-700','green'=>'bg-emerald-100 text-emerald-700','blue'=>'bg-blue-100 text-blue-700',
        'violet'=>'bg-violet-100 text-violet-700','pink'=>'bg-pink-100 text-pink-700',
      ];
      foreach ($byCat as $c):
        $badge = $colorMap[$c['color']] ?? $colorMap['slate'];
        $isIn = $c['type'] === 'encaissement';
      ?>
      <div class="flex items-center justify-between text-sm">
        <span class="flex items-center gap-2">
          <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?= $badge ?>"><?= e($c['label']) ?></span>
          <span class="text-slate-400 text-xs">(<?= $c['cnt'] ?>)</span>
        </span>
        <span class="font-bold <?= $isIn ? 'text-emerald-700' : 'text-rose-700' ?>">
          <?= $isIn ? '+' : '-' ?><?= number_format((int)$c['total'], 0, ',', ' ') ?>
        </span>
      </div>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>

  <form method="post" action="<?= e(url('finance/treasury/closure')) ?>" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="cash_register_id" value="<?= $register['id'] ?>">

    <!-- Billettage FCFA -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100 mb-4">
        <i data-lucide="banknote" class="w-4 h-4 text-cb-primary"></i> Billettage (comptage physique)
      </h2>

      <div class="space-y-2">
        <?php foreach ($denominations as $d): ?>
        <div class="flex items-center gap-3">
          <span class="w-24 text-right font-mono font-bold text-sm text-slate-700"><?= number_format($d, 0, ',', ' ') ?> FCFA</span>
          <span class="text-slate-400">×</span>
          <input type="number" name="denom_<?= $d ?>" min="0" value="0"
                 x-model.number="denoms[<?= $d ?>]"
                 class="w-24 px-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm text-center font-mono">
          <span class="text-slate-400">=</span>
          <span class="w-32 text-right font-mono font-semibold text-sm text-slate-600"
                x-text="fmt(denoms[<?= $d ?>] * <?= $d ?>)"></span>
        </div>
        <?php endforeach ?>
      </div>

      <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
        <span class="font-bold text-slate-700">Total compté :</span>
        <span class="text-xl font-black text-slate-900" x-text="fmt(totalCompte) + ' FCFA'"></span>
      </div>

      <!-- Remplir automatiquement solde déclaré -->
      <input type="hidden" name="solde_declare" :value="totalCompte">
    </div>

    <!-- Écart -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100 mb-4">
        <i data-lucide="scale" class="w-4 h-4 text-cb-primary"></i> Analyse de l'écart
      </h2>

      <div class="grid grid-cols-3 gap-4 text-center">
        <div>
          <p class="text-xs text-slate-500 font-semibold">Théorique</p>
          <p class="text-lg font-bold text-slate-700"><?= number_format($theorique, 0, ',', ' ') ?></p>
        </div>
        <div>
          <p class="text-xs text-slate-500 font-semibold">Compté</p>
          <p class="text-lg font-bold text-slate-700" x-text="fmt(totalCompte)"></p>
        </div>
        <div>
          <p class="text-xs text-slate-500 font-semibold">Écart</p>
          <p class="text-lg font-bold"
             :class="ecart === 0 ? 'text-emerald-700' : (ecart > 0 ? 'text-blue-700' : 'text-rose-700')"
             x-text="(ecart > 0 ? '+' : '') + fmt(ecart)"></p>
        </div>
      </div>

      <div class="mt-3 p-3 rounded-xl text-sm font-semibold text-center"
           :class="ecart === 0 ? 'bg-emerald-50 text-emerald-700' : (ecart > 0 ? 'bg-blue-50 text-blue-700' : 'bg-rose-50 text-rose-700')">
        <span x-show="ecart === 0">Solde exact</span>
        <span x-show="ecart > 0">Excédent de caisse</span>
        <span x-show="ecart < 0">Déficit de caisse</span>
      </div>
    </div>

    <!-- Notes -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
      <label class="text-xs font-semibold text-slate-600 block mb-1.5">Notes / Observations</label>
      <textarea name="notes" rows="2" maxlength="1000" placeholder="Observations sur la clôture…"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm resize-none"></textarea>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
      <a href="<?= e(url('finance/treasury')) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">Annuler</a>
      <button type="submit"
              onclick="return confirm('Confirmer la clôture de cette caisse ? Cette action est irréversible.')"
              class="px-6 py-2.5 rounded-xl bg-cb-primary hover:bg-cb-dark text-white text-sm font-bold transition flex items-center gap-2">
        <i data-lucide="lock" class="w-4 h-4"></i> Clôturer la caisse
      </button>
    </div>
  </form>
</div>

<script>
function billettage() {
  const theorique = <?= $theorique ?>;
  return {
    denoms: { <?php echo implode(', ', array_map(fn($d) => "$d: 0", $denominations)); ?> },
    get totalCompte() {
      let s = 0;
      <?php foreach ($denominations as $d): ?>
      s += (this.denoms[<?= $d ?>] || 0) * <?= $d ?>;
      <?php endforeach ?>
      return s;
    },
    get ecart() { return this.totalCompte - theorique; },
    fmt(n) { return new Intl.NumberFormat('fr-FR').format(n); }
  };
}
</script>
<?php $view->end() ?>
