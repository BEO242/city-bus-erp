<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$gap       = (int)$closure['gap_amount'];
$gapCls    = $gap === 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
           : ($gap > 0   ? 'bg-blue-50 border-blue-200 text-blue-700'
                         : 'bg-rose-50 border-rose-200 text-rose-700');
$gapLabel  = $gap === 0 ? 'Solde exact — aucun écart' : ($gap > 0 ? 'Excédent' : 'Déficit');
$gapSign   = $gap > 0 ? '+' : '';
$openedTs  = strtotime($closure['opened_at']);
$closedTs  = strtotime($closure['closed_at']);
$durMin    = (int)(($closedTs - $openedTs) / 60);
$durStr    = $durMin >= 60 ? intdiv($durMin, 60) . 'h ' . ($durMin % 60) . 'min' : $durMin . ' min';

$paymentLabels = [
    'especes'     => 'Espèces',
    'mobile_money'=> 'Mobile Money',
    'mobile'      => 'Mobile Money',
    'carte'       => 'Carte bancaire',
    'virement'    => 'Virement',
    'cheque'      => 'Chèque',
    'voucher'     => 'Bon / Voucher',
];
?>
<div class="space-y-5 max-w-3xl mx-auto">

  <!-- En-tête -->
  <div class="flex items-center gap-3">
    <a href="<?= e(url('finance/treasury/closures')) ?>"
       class="p-2 rounded-xl text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition shrink-0">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div class="flex-1 min-w-0">
      <h1 class="text-xl font-black text-slate-900 truncate"><?= e($closureNum) ?></h1>
      <p class="text-xs text-slate-400 mt-0.5">
        <?= e($closure['agency_name']) ?>
        <?php if (!empty($closure['caisse_name'])): ?>
          · <span class="font-mono"><?= e(strtoupper($closure['caisse_code'])) ?></span> <?= e($closure['caisse_name']) ?>
        <?php endif ?>
      </p>
    </div>
    <!-- Statut badge -->
    <?php if ($closure['validated_by']): ?>
      <span class="inline-flex items-center gap-1.5 text-sm font-bold px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-700 shrink-0">
        <i data-lucide="check-circle-2" class="w-4 h-4"></i> Validée
      </span>
    <?php elseif (can('finance.treasury.validate')): ?>
      <form method="post" action="<?= e(url('finance/treasury/closures/' . $closure['id'] . '/validate')) ?>">
        <?= csrf_field() ?>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition">
          <i data-lucide="check-circle" class="w-4 h-4"></i> Valider la clôture
        </button>
      </form>
    <?php endif ?>
  </div>

  <!-- Résumé session -->
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-cb-primary to-cb-secondary px-5 py-3.5 flex flex-wrap items-center gap-4">
      <div class="flex items-center gap-2 text-white">
        <i data-lucide="calendar" class="w-4 h-4 opacity-70"></i>
        <span class="text-sm font-semibold"><?= e(date('d/m/Y H:i', $openedTs)) ?></span>
        <i data-lucide="arrow-right" class="w-3.5 h-3.5 opacity-50"></i>
        <span class="text-sm font-semibold"><?= e(date('d/m/Y H:i', $closedTs)) ?></span>
        <span class="text-white/50 text-xs">(<?= e($durStr) ?>)</span>
      </div>
      <div class="flex items-center gap-2 text-white ml-auto">
        <i data-lucide="ticket" class="w-4 h-4 opacity-70"></i>
        <span class="text-sm font-semibold"><?= (int)$closure['ticket_count'] ?> ticket(s)</span>
      </div>
    </div>

    <?php
      $txEnc   = array_sum(array_map(fn($t) => $t['type']==='encaissement' ? (int)$t['amount_fcfa'] : 0, $txList));
      $txDec   = array_sum(array_map(fn($t) => $t['type']==='decaissement' ? (int)$t['amount_fcfa'] : 0, $txList));
      $txSolde = $txEnc - $txDec;
      $txSolPos = $txSolde >= 0;
    ?>
    <!-- Ligne 1 : Encaissements / Décaissements / Solde net -->
    <div class="grid grid-cols-3 border-b border-slate-100">
      <div class="p-4 text-center bg-emerald-50/60">
        <p class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wider mb-1 flex items-center justify-center gap-1">
          <i data-lucide="arrow-down-left" class="w-3 h-3"></i> Encaissements
        </p>
        <p class="text-xl font-black text-emerald-700 tabular-nums"><?= number_format($txEnc, 0, ',', ' ') ?></p>
        <p class="text-[10px] text-emerald-400 font-mono">FCFA</p>
      </div>
      <div class="p-4 text-center bg-rose-50/60 border-x border-slate-100">
        <p class="text-[10px] text-rose-600 font-semibold uppercase tracking-wider mb-1 flex items-center justify-center gap-1">
          <i data-lucide="arrow-up-right" class="w-3 h-3"></i> Décaissements
        </p>
        <p class="text-xl font-black text-rose-700 tabular-nums"><?= number_format($txDec, 0, ',', ' ') ?></p>
        <p class="text-[10px] text-rose-400 font-mono">FCFA</p>
      </div>
      <div class="p-4 text-center <?= $txSolPos ? 'bg-blue-50/60' : 'bg-orange-50/60' ?>">
        <p class="text-[10px] <?= $txSolPos ? 'text-blue-600' : 'text-orange-600' ?> font-semibold uppercase tracking-wider mb-1 flex items-center justify-center gap-1">
          <i data-lucide="scale" class="w-3 h-3"></i> Solde net trésorerie
        </p>
        <p class="text-xl font-black <?= $txSolPos ? 'text-blue-700' : 'text-orange-700' ?> tabular-nums">
          <?= ($txSolPos ? '+' : '') . number_format($txSolde, 0, ',', ' ') ?>
        </p>
        <p class="text-[10px] <?= $txSolPos ? 'text-blue-400' : 'text-orange-400' ?> font-mono">FCFA</p>
      </div>
    </div>
    <!-- Ligne 2 : Fond initial / Solde théorique / Solde déclaré / Écart -->
    <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-slate-100">
      <div class="p-4 text-center">
        <p class="text-xs text-slate-500 font-semibold mb-1">Fond initial</p>
        <p class="text-lg font-black text-slate-700"><?= number_format((int)$closure['opening_amount'], 0, ',', ' ') ?></p>
        <p class="text-[10px] text-slate-400 font-mono">FCFA</p>
      </div>
      <div class="p-4 text-center">
        <p class="text-xs text-slate-500 font-semibold mb-1">Solde théorique</p>
        <p class="text-lg font-black text-slate-800"><?= number_format((int)$closure['theoretical_amount'], 0, ',', ' ') ?></p>
        <p class="text-[10px] text-slate-400 font-mono">FCFA</p>
      </div>
      <div class="p-4 text-center">
        <p class="text-xs text-slate-500 font-semibold mb-1">Solde déclaré</p>
        <p class="text-lg font-black text-slate-800"><?= number_format((int)$closure['declared_amount'], 0, ',', ' ') ?></p>
        <p class="text-[10px] text-slate-400 font-mono">FCFA</p>
      </div>
      <div class="p-4 text-center <?= $gapCls ?> border-0">
        <p class="text-xs font-semibold mb-1 opacity-80">Écart</p>
        <p class="text-lg font-black"><?= $gapSign ?><?= number_format($gap, 0, ',', ' ') ?></p>
        <p class="text-[10px] font-semibold opacity-70"><?= $gapLabel ?></p>
      </div>
    </div>
  </div>

  <!-- Billettage -->
  <?php if (!empty($denoms)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center gap-2">
      <i data-lucide="banknote" class="w-4 h-4 text-cb-primary"></i>
      <span class="font-bold text-slate-700 text-sm">Billettage déclaré</span>
    </div>
    <div class="p-5">
      <div class="border border-slate-100 rounded-xl overflow-hidden">
        <div class="grid grid-cols-3 text-xs font-bold text-slate-500 uppercase bg-slate-50 px-4 py-2.5 border-b border-slate-100">
          <span>Coupure</span><span class="text-center">Quantité</span><span class="text-right">Sous-total</span>
        </div>
        <?php $totalBillettage = 0; foreach ($denoms as $d): $totalBillettage += (int)$d['subtotal']; ?>
        <div class="grid grid-cols-3 items-center px-4 py-2.5 border-b border-slate-50 last:border-0">
          <span class="font-mono font-bold text-slate-700"><?= number_format((int)$d['denomination'], 0, ',', ' ') ?> FCFA</span>
          <span class="text-center text-slate-500">× <?= (int)$d['quantity'] ?></span>
          <span class="text-right font-mono font-semibold text-slate-800"><?= number_format((int)$d['subtotal'], 0, ',', ' ') ?> F</span>
        </div>
        <?php endforeach ?>
        <div class="grid grid-cols-3 items-center px-4 py-3 bg-slate-50 border-t-2 border-slate-200">
          <span class="col-span-2 font-bold text-slate-700">Total billettage</span>
          <span class="text-right font-black text-slate-900 text-base font-mono"><?= number_format($totalBillettage, 0, ',', ' ') ?> FCFA</span>
        </div>
      </div>
    </div>
  </div>
  <?php endif ?>

  <!-- Ventes par mode de paiement -->
  <?php if ($salesByMode): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-5">
    <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100 mb-4">
      <i data-lucide="credit-card" class="w-4 h-4 text-cb-primary"></i>
      Ventes par mode de paiement
    </h2>
    <div class="space-y-2">
      <?php $totalSales = 0; foreach ($salesByMode as $s): $totalSales += (int)$s['total']; ?>
      <div class="flex items-center gap-3">
        <span class="text-sm text-slate-600 w-36 shrink-0"><?= e($paymentLabels[$s['payment_method']] ?? $s['payment_method']) ?></span>
        <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
          <?php $pct = $totalSales > 0 ? round((int)$s['total'] / $totalSales * 100) : 0; ?>
          <div class="h-full bg-cb-primary rounded-full" style="width:<?= $pct ?>%"></div>
        </div>
        <span class="text-xs text-slate-400 w-6 text-right"><?= $pct ?>%</span>
        <span class="text-sm font-bold text-slate-800 w-32 text-right font-mono">
          <?= number_format((int)$s['total'], 0, ',', ' ') ?> FCFA
        </span>
        <span class="text-xs text-slate-400">(<?= (int)$s['cnt'] ?> tx)</span>
      </div>
      <?php endforeach ?>
    </div>
    <div class="pt-3 mt-3 border-t border-slate-100 flex justify-between items-center">
      <span class="text-sm font-bold text-slate-700">Total ventes</span>
      <span class="text-base font-black text-slate-900"><?= number_format($totalSales, 0, ',', ' ') ?> FCFA</span>
    </div>
  </div>
  <?php endif ?>

  <!-- Transactions trésorerie -->
  <?php if ($txList): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-5">
    <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100 mb-4">
      <i data-lucide="arrow-left-right" class="w-4 h-4 text-cb-primary"></i>
      Transactions trésorerie (<?= count($txList) ?>)
    </h2>
    <div class="space-y-1.5">
      <?php foreach ($txList as $tx):
        $isEnc = $tx['type'] === 'encaissement';
      ?>
      <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 transition">
        <div class="w-7 h-7 rounded-lg <?= $isEnc ? 'bg-emerald-50' : 'bg-rose-50' ?> flex items-center justify-center shrink-0">
          <i data-lucide="<?= $isEnc ? 'arrow-down-left' : 'arrow-up-right' ?>"
             class="w-3.5 h-3.5 <?= $isEnc ? 'text-emerald-600' : 'text-rose-600' ?>"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-slate-700 truncate"><?= e($tx['description'] ?: $tx['cat_label']) ?></p>
          <p class="text-[11px] text-slate-400"><?= e(date('d/m H:i', strtotime($tx['created_at']))) ?></p>
        </div>
        <span class="text-sm font-bold <?= $isEnc ? 'text-emerald-700' : 'text-rose-700' ?>">
          <?= $isEnc ? '+' : '-' ?><?= number_format((int)$tx['amount_fcfa'], 0, ',', ' ') ?>
        </span>
      </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>

  <!-- Infos clôture -->
  <div class="bg-white rounded-2xl border border-slate-100 p-5 space-y-3 text-sm">
    <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
      <i data-lucide="info" class="w-4 h-4 text-cb-primary"></i> Informations
    </h2>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <p class="text-xs text-slate-500 mb-0.5">Clôturé par</p>
        <p class="font-semibold text-slate-800"><?= e(trim(($closure['closer_first'] ?? '') . ' ' . ($closure['closer_last'] ?? ''))) ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-500 mb-0.5">Date de clôture</p>
        <p class="font-semibold text-slate-800"><?= e(date('d/m/Y à H:i', $closedTs)) ?></p>
      </div>
      <?php if ($closure['validated_by']): ?>
      <div>
        <p class="text-xs text-slate-500 mb-0.5">Validé par</p>
        <p class="font-semibold text-emerald-700"><?= e(trim(($closure['validator_first'] ?? '') . ' ' . ($closure['validator_last'] ?? ''))) ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-500 mb-0.5">Date de validation</p>
        <p class="font-semibold text-slate-800"><?= e(date('d/m/Y à H:i', strtotime($closure['validated_at']))) ?></p>
      </div>
      <?php else: ?>
      <div class="col-span-2">
        <p class="text-xs text-slate-500 mb-0.5">Validation</p>
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> En attente de validation
        </span>
      </div>
      <?php endif ?>
    </div>
    <?php if (!empty($closure['notes'])): ?>
    <div class="pt-3 border-t border-slate-100">
      <p class="text-xs text-slate-500 mb-1">Notes</p>
      <p class="text-slate-700 text-sm bg-slate-50 rounded-xl px-4 py-3"><?= nl2br(e($closure['notes'])) ?></p>
    </div>
    <?php endif ?>
  </div>

</div>
<?php $view->end() ?>
