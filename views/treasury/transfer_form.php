<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">

  <div class="flex items-center gap-3">
    <a href="<?= e(url('finance/treasury/transactions')) ?>" class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
        <i data-lucide="arrow-left-right" class="w-5 h-5 text-amber-500"></i> <?= e($title) ?>
      </h1>
      <p class="text-xs text-slate-400 mt-0.5">Transférer des fonds entre deux caisses ouvertes.</p>
    </div>
  </div>

  <form method="post" action="<?= e(url('finance/treasury/transfer')) ?>" class="space-y-4"
        x-data="{ amount: 0, fromId: '', toId: '' }">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="wallet" class="w-4 h-4 text-cb-primary"></i> Caisses
      </h2>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Caisse source <span class="text-rose-500">*</span></label>
          <select name="from_register_id" required x-model="fromId"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="">-- Source --</option>
            <?php foreach ($registers as $r): ?>
            <option value="<?= $r['id'] ?>"><?= e($r['agency_name']) ?> (<?= e($r['first_name'] . ' ' . $r['last_name']) ?>) — <?= number_format((int)$r['solde'], 0, ',', ' ') ?> FCFA</option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Caisse destination <span class="text-rose-500">*</span></label>
          <select name="to_register_id" required x-model="toId"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white">
            <option value="">-- Destination --</option>
            <?php foreach ($registers as $r): ?>
            <option value="<?= $r['id'] ?>"><?= e($r['agency_name']) ?> (<?= e($r['first_name'] . ' ' . $r['last_name']) ?>)</option>
            <?php endforeach ?>
          </select>
        </div>
      </div>

      <template x-if="fromId && toId && fromId === toId">
        <div class="p-3 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold">
          <i data-lucide="alert-circle" class="w-4 h-4 inline"></i> Les caisses source et destination doivent être différentes.
        </div>
      </template>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Montant (FCFA) <span class="text-rose-500">*</span></label>
        <input type="number" name="amount_fcfa" required min="1" step="1"
               x-model.number="amount" placeholder="0"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right">
      </div>

      <div x-show="amount > 0" class="p-3 rounded-xl bg-amber-50 text-center">
        <span class="text-lg font-black text-amber-700" x-text="new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA'"></span>
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">Motif <span class="text-slate-400 font-normal">(optionnel)</span></label>
        <textarea name="motif" rows="2" maxlength="300" placeholder="Raison du transfert…"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm resize-none"></textarea>
      </div>
    </div>

    <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
      <a href="<?= e(url('finance/treasury/transactions')) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">Annuler</a>
      <button type="submit" :disabled="fromId === toId"
              class="px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold transition flex items-center gap-2 disabled:opacity-50">
        <i data-lucide="arrow-left-right" class="w-4 h-4"></i> Initier le virement
      </button>
    </div>
  </form>
</div>
<?php $view->end() ?>
