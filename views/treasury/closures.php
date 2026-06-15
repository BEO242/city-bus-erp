<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$registersJson = htmlspecialchars(json_encode(array_map(fn($r) => [
    'id'           => (int)$r['id'],
    'theorique'    => (int)$r['theorique'],
    'opening'      => (int)$r['opening_amount'],
    'agency'       => $r['agency_name'],
    'caisse'       => $r['caisse_name'] ?? null,
    'code'         => $r['caisse_code'] ? strtoupper($r['caisse_code']) : null,
    'cashier'      => trim(($r['cashier_first'] ?? '') . ' ' . ($r['cashier_last'] ?? '')),
    'opened_at'    => $r['opened_at'],
], $openRegisters)), ENT_QUOTES | ENT_HTML5);
$postesJson = htmlspecialchars(json_encode(array_values($postes)), ENT_QUOTES | ENT_HTML5);
$denominations = [10000, 5000, 2000, 1000, 500, 100, 50, 25, 10, 5];
?>

<div x-data="caisseGestion(<?= $registersJson ?>, <?= $postesJson ?>)" class="space-y-6">

  <!-- ─── En-tête ─── -->
  <div class="flex justify-between flex-wrap gap-3 items-start">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Gestion des caisses</h1>
      <p class="text-slate-500 text-sm mt-0.5">Ouvrez, clôturez et suivez toutes les sessions de caisse.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= e(url('finance/treasury')) ?>"
         class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
        <i data-lucide="vault" class="w-4 h-4 text-cb-primary"></i> Trésorerie
      </a>
      <button @click="showOpen=true"
              class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-semibold inline-flex items-center gap-2 hover:bg-cb-dark transition text-sm shadow-sm shadow-cb-primary/30">
        <i data-lucide="lock-open" class="w-4 h-4"></i> Ouvrir une caisse
      </button>
    </div>
  </div>

  <!-- ─── Sessions ouvertes ─── -->
  <?php if ($openRegisters): ?>
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center gap-2">
      <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
      <span class="font-bold text-slate-700">Sessions actives</span>
      <span class="ml-1 text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"><?= count($openRegisters) ?></span>
    </div>
    <div class="divide-y divide-slate-50">
      <?php foreach ($openRegisters as $reg):
        $openedTs  = strtotime($reg['opened_at']);
        $diffMin   = (int)((time() - $openedTs) / 60);
        $durStr    = $diffMin >= 60 ? intdiv($diffMin, 60) . 'h ' . ($diffMin % 60) . 'min' : $diffMin . ' min';
        $theorique = (int)$reg['theorique'];
      ?>
      <div class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50/60 transition group flex-wrap">
        <!-- Icône -->
        <div class="w-10 h-10 rounded-xl bg-cb-bg flex items-center justify-center shrink-0">
          <i data-lucide="wallet" class="w-5 h-5 text-cb-primary"></i>
        </div>
        <!-- Infos -->
        <div class="flex-1 min-w-0">
          <p class="font-bold text-slate-900 text-sm leading-tight">
            <?= e(!empty($reg['caisse_name']) ? $reg['caisse_name'] : $reg['agency_name']) ?>
            <?php if (!empty($reg['caisse_code'])): ?>
              <span class="font-mono text-xs text-slate-400 ml-1"><?= e(strtoupper($reg['caisse_code'])) ?></span>
            <?php endif ?>
          </p>
          <p class="text-xs text-slate-400 mt-0.5">
            <?= e($reg['agency_name']) ?> ·
            <?= e(trim(($reg['cashier_first'] ?? '') . ' ' . ($reg['cashier_last'] ?? ''))) ?> ·
            ouverte depuis <?= e($durStr) ?>
          </p>
        </div>
        <!-- Stats enc/dec/solde -->
        <?php
          $rEnc   = (int)($reg['total_tx_enc'] ?? 0);
          $rDec   = (int)($reg['total_tx_dec'] ?? 0);
          $rSolde = $rEnc - $rDec;
        ?>
        <div class="flex items-center gap-4 text-center flex-wrap">
          <div class="px-3 py-1.5 rounded-lg bg-slate-50 text-center min-w-[80px]">
            <p class="text-[10px] text-slate-400 font-medium">Fond init.</p>
            <p class="text-xs font-bold text-slate-700 tabular-nums"><?= number_format((int)$reg['opening_amount'], 0, ',', ' ') ?></p>
          </div>
          <div class="px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-100 text-center min-w-[80px]">
            <p class="text-[10px] text-emerald-500 font-semibold flex items-center justify-center gap-0.5"><i data-lucide="arrow-down-left" class="w-2.5 h-2.5"></i> Enc.</p>
            <p class="text-xs font-black text-emerald-700 tabular-nums">+ <?= number_format($rEnc, 0, ',', ' ') ?></p>
          </div>
          <div class="px-3 py-1.5 rounded-lg bg-rose-50 border border-rose-100 text-center min-w-[80px]">
            <p class="text-[10px] text-rose-500 font-semibold flex items-center justify-center gap-0.5"><i data-lucide="arrow-up-right" class="w-2.5 h-2.5"></i> Déc.</p>
            <p class="text-xs font-black text-rose-700 tabular-nums">- <?= number_format($rDec, 0, ',', ' ') ?></p>
          </div>
          <div class="px-3 py-1.5 rounded-lg <?= $rSolde >= 0 ? 'bg-blue-50 border border-blue-100' : 'bg-orange-50 border border-orange-100' ?> text-center min-w-[80px]">
            <p class="text-[10px] <?= $rSolde >= 0 ? 'text-blue-500' : 'text-orange-500' ?> font-semibold flex items-center justify-center gap-0.5"><i data-lucide="scale" class="w-2.5 h-2.5"></i> Solde</p>
            <p class="text-xs font-black <?= $rSolde >= 0 ? 'text-blue-700' : 'text-orange-700' ?> tabular-nums">
              <?= ($rSolde >= 0 ? '+' : '') . number_format($rSolde, 0, ',', ' ') ?>
            </p>
          </div>
          <div class="px-3 py-1.5 rounded-lg bg-slate-50 text-center min-w-[80px]">
            <p class="text-[10px] text-slate-400 font-medium">Théorique</p>
            <p class="text-xs font-bold text-emerald-700 tabular-nums"><?= number_format($theorique, 0, ',', ' ') ?></p>
          </div>
        </div>
        <!-- Action -->
        <button @click="openClosure(<?= htmlspecialchars(json_encode([
          'id'        => (int)$reg['id'],
          'theorique' => (int)$reg['theorique'],
          'opening'   => (int)$reg['opening_amount'],
          'agency'    => $reg['agency_name'],
          'caisse'    => $reg['caisse_name'] ?? null,
          'code'      => $reg['caisse_code'] ? strtoupper($reg['caisse_code']) : null,
          'cashier'   => trim(($reg['cashier_first'] ?? '') . ' ' . ($reg['cashier_last'] ?? '')),
          'opened_at' => $reg['opened_at'],
        ]), ENT_QUOTES) ?>)"
                class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-bold hover:bg-cb-dark transition flex items-center gap-2 shrink-0">
          <i data-lucide="lock" class="w-4 h-4"></i> Clôturer
        </button>
      </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php else: ?>
  <div class="bg-emerald-50 border border-emerald-200 rounded-2xl px-5 py-4 flex items-center gap-3 text-emerald-700">
    <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0"></i>
    <span class="text-sm font-semibold">Aucune session de caisse ouverte en ce moment.</span>
    <button @click="showOpen=true" class="ml-auto text-xs font-bold underline hover:no-underline">Ouvrir une caisse</button>
  </div>
  <?php endif ?>

  <!-- ─── KPI Strip — comptages ─── -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3.5 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-cb-bg flex items-center justify-center shrink-0">
        <i data-lucide="archive" class="w-4 h-4 text-cb-primary"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-slate-900"><?= (int)($kpi['total'] ?? 0) ?></p>
        <p class="text-xs text-slate-500">Total clôtures</p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3.5 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
        <i data-lucide="clock" class="w-4 h-4 text-amber-600"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-amber-700"><?= (int)($kpi['pending'] ?? 0) ?></p>
        <p class="text-xs text-slate-500">En attente validation</p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3.5 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
        <i data-lucide="calendar-check" class="w-4 h-4 text-blue-600"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-blue-700"><?= (int)($kpi['today'] ?? 0) ?></p>
        <p class="text-xs text-slate-500">Aujourd'hui</p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3.5 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center shrink-0">
        <i data-lucide="trending-down" class="w-4 h-4 text-rose-500"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-rose-600"><?= number_format((int)($kpi['total_ecart'] ?? 0), 0, ',', ' ') ?></p>
        <p class="text-xs text-slate-500">Écarts cumulés (FCFA)</p>
      </div>
    </div>
  </div>

  <!-- ─── KPI financiers — Encaissements / Décaissements / Solde net ─── -->
  <?php
    $fEnc    = (int)($finKpi['total_enc'] ?? 0);
    $fDec    = (int)($finKpi['total_dec'] ?? 0);
    $fSolde  = $fEnc - $fDec;
    $fSolPos = $fSolde >= 0;
  ?>
  <div class="grid grid-cols-3 gap-3">
    <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4">
      <div class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wider mb-2 flex items-center gap-1">
        <i data-lucide="arrow-down-left" class="w-3 h-3"></i> Encaissements
      </div>
      <p class="text-2xl font-black text-emerald-700 tabular-nums leading-tight"><?= number_format($fEnc, 0, ',', ' ') ?></p>
      <p class="text-[10px] text-emerald-400 font-mono mt-0.5">FCFA</p>
    </div>
    <div class="rounded-2xl bg-rose-50 border border-rose-100 p-4">
      <div class="text-[10px] text-rose-600 font-semibold uppercase tracking-wider mb-2 flex items-center gap-1">
        <i data-lucide="arrow-up-right" class="w-3 h-3"></i> Décaissements
      </div>
      <p class="text-2xl font-black text-rose-700 tabular-nums leading-tight"><?= number_format($fDec, 0, ',', ' ') ?></p>
      <p class="text-[10px] text-rose-400 font-mono mt-0.5">FCFA</p>
    </div>
    <div class="rounded-2xl <?= $fSolPos ? 'bg-blue-50 border border-blue-100' : 'bg-orange-50 border border-orange-100' ?> p-4">
      <div class="text-[10px] <?= $fSolPos ? 'text-blue-600' : 'text-orange-600' ?> font-semibold uppercase tracking-wider mb-2 flex items-center gap-1">
        <i data-lucide="scale" class="w-3 h-3"></i> Solde net
      </div>
      <p class="text-2xl font-black <?= $fSolPos ? 'text-blue-700' : 'text-orange-700' ?> tabular-nums leading-tight">
        <?= ($fSolPos ? '+' : '') . number_format($fSolde, 0, ',', ' ') ?>
      </p>
      <p class="text-[10px] <?= $fSolPos ? 'text-blue-400' : 'text-orange-400' ?> font-mono mt-0.5">FCFA</p>
    </div>
  </div>

  <!-- ─── Filtres ─── -->
  <form method="get" action="<?= e(url('finance/treasury/closures')) ?>"
        class="bg-white rounded-2xl border border-slate-100 px-4 py-3 flex flex-wrap items-center gap-3">
    <select name="agency"
            class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none bg-white text-slate-600">
      <option value="">Toutes les agences</option>
      <?php foreach ($agencies as $ag): ?>
        <option value="<?= $ag['id'] ?>" <?= $agencyId === (int)$ag['id'] ? 'selected' : '' ?>><?= e($ag['name']) ?></option>
      <?php endforeach ?>
    </select>
    <select name="status"
            class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none bg-white text-slate-600">
      <option value="">Tous les statuts</option>
      <option value="pending"   <?= $status==='pending'   ? 'selected':'' ?>>En attente</option>
      <option value="validated" <?= $status==='validated' ? 'selected':'' ?>>Validées</option>
    </select>
    <input type="date" name="date_from" value="<?= e($dateFrom) ?>"
           class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none">
    <span class="text-slate-400 text-xs">→</span>
    <input type="date" name="date_to" value="<?= e($dateTo) ?>"
           class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none">
    <button type="submit" class="px-4 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition">
      Filtrer
    </button>
    <?php if ($agencyId || $status || $dateFrom || $dateTo): ?>
      <a href="<?= e(url('finance/treasury/closures')) ?>" class="text-xs text-slate-400 hover:text-cb-primary flex items-center gap-1">
        <i data-lucide="x" class="w-3 h-3"></i> Réinitialiser
      </a>
    <?php endif ?>
    <span class="ml-auto text-xs text-slate-400"><?= count($closures) ?> clôture(s)</span>
  </form>

  <!-- ─── Historique ─── -->
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center gap-2">
      <i data-lucide="history" class="w-4 h-4 text-cb-primary"></i>
      <span class="font-bold text-slate-700">Historique des clôtures</span>
    </div>
    <?php if (!$closures): ?>
      <div class="py-16 text-center">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
          <i data-lucide="archive" class="w-6 h-6 text-slate-300"></i>
        </div>
        <p class="font-semibold text-slate-500">Aucune clôture enregistrée</p>
        <p class="text-slate-400 text-sm mt-1 max-w-xs mx-auto">
          Clôturez une session depuis la section "Sessions actives" ci-dessus.
        </p>
      </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-100">
            <th class="px-4 py-3.5">N° Clôture</th>
            <th class="px-4 py-3.5">Caisse / Agence</th>
            <th class="px-4 py-3.5">Période</th>
            <th class="px-4 py-3.5 text-center">Tickets</th>
            <th class="px-4 py-3.5 text-right">Théorique</th>
            <th class="px-4 py-3.5 text-right">Déclaré</th>
            <th class="px-4 py-3.5 text-right">Écart</th>
            <th class="px-4 py-3.5">Clôturé par</th>
            <th class="px-4 py-3.5">Statut</th>
            <th class="px-4 py-3.5"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($closures as $cl):
            $openedTs   = strtotime($cl['opened_at']);
            $closedTs   = strtotime($cl['closed_at']);
            $closureNum = 'CLO-' . date('ymd', $openedTs) . '-' . str_pad((string)$cl['cash_register_id'], 3, '0', STR_PAD_LEFT);
            $gap        = (int)$cl['gap_amount'];
            $gapCls     = $gap === 0 ? 'text-emerald-700 bg-emerald-50'
                        : ($gap > 0  ? 'text-blue-700 bg-blue-50' : 'text-rose-700 bg-rose-50');
            $durMin     = (int)(($closedTs - $openedTs) / 60);
            $durStr     = $durMin >= 60 ? intdiv($durMin, 60) . 'h ' . ($durMin % 60) . 'min' : $durMin . ' min';
          ?>
          <tr class="hover:bg-slate-50/60 transition group">
            <td class="px-4 py-3.5">
              <span class="font-mono text-xs font-bold text-cb-primary"><?= e($closureNum) ?></span>
            </td>
            <td class="px-4 py-3.5">
              <div class="font-semibold text-slate-800 text-xs leading-tight">
                <?= e(!empty($cl['caisse_name']) ? $cl['caisse_name'] : $cl['agency_name']) ?>
              </div>
              <?php if (!empty($cl['caisse_name'])): ?>
                <div class="text-[11px] text-slate-400"><?= e($cl['agency_name']) ?></div>
              <?php endif ?>
            </td>
            <td class="px-4 py-3.5">
              <div class="text-xs text-slate-600"><?= e(date('d/m/Y H:i', $openedTs)) ?></div>
              <div class="text-[11px] text-slate-400 flex items-center gap-1">
                <i data-lucide="arrow-right" class="w-2.5 h-2.5"></i>
                <?= e(date('d/m H:i', $closedTs)) ?>
                <span class="text-slate-300">·</span><?= e($durStr) ?>
              </div>
            </td>
            <td class="px-4 py-3.5 text-center text-xs font-semibold text-slate-700"><?= (int)$cl['ticket_count'] ?></td>
            <td class="px-4 py-3.5 text-right font-semibold text-slate-800"><?= number_format((int)$cl['theoretical_amount'], 0, ',', ' ') ?></td>
            <td class="px-4 py-3.5 text-right font-semibold text-slate-800"><?= number_format((int)$cl['declared_amount'], 0, ',', ' ') ?></td>
            <td class="px-4 py-3.5 text-right">
              <span class="inline-block px-2 py-0.5 rounded-lg text-xs font-bold <?= $gapCls ?>">
                <?= $gap > 0 ? '+' : '' ?><?= number_format($gap, 0, ',', ' ') ?>
              </span>
            </td>
            <td class="px-4 py-3.5 text-xs text-slate-500"><?= e(trim(($cl['closer_first'] ?? '') . ' ' . ($cl['closer_last'] ?? ''))) ?></td>
            <td class="px-4 py-3.5">
              <?php if ($cl['validated_by']): ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Validée
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> En attente
                </span>
              <?php endif ?>
            </td>
            <td class="px-4 py-3.5">
              <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <a href="<?= e(url('finance/treasury/closures/' . $cl['id'])) ?>"
                   class="p-1.5 rounded-lg hover:bg-cb-bg text-slate-400 hover:text-cb-primary transition" title="Détail">
                  <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                </a>
                <?php if (!$cl['validated_by'] && can('finance.treasury.validate')): ?>
                <form method="post" action="<?= e(url('finance/treasury/closures/' . $cl['id'] . '/validate')) ?>" class="inline">
                  <?= csrf_field() ?>
                  <button type="submit" class="p-1.5 rounded-lg hover:bg-emerald-50 text-slate-400 hover:text-emerald-600 transition" title="Valider">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
                <?php endif ?>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </div>


  <!-- ════════════════════════════════════════════════
       MODAL BILLETTAGE : Clôturer une caisse
  ════════════════════════════════════════════════ -->
  <div x-show="showClosure" x-cloak
       class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-10 overflow-y-auto"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showClosure=false"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         @click.stop>

      <!-- Header -->
      <div class="sticky top-0 bg-gradient-to-r from-cb-primary to-cb-secondary px-6 py-4 rounded-t-2xl flex items-center justify-between">
        <div>
          <p class="text-white/70 text-xs uppercase tracking-wide font-semibold">Clôture de caisse</p>
          <p class="text-white font-bold text-lg leading-tight" x-text="register ? (register.caisse || register.agency) : ''"></p>
          <p class="text-white/60 text-xs font-mono" x-text="register?.code ?? ''"></p>
        </div>
        <button @click="showClosure=false" class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <!-- Form -->
      <form method="post" action="<?= e(url('finance/treasury/closure')) ?>" class="p-6 space-y-5">
        <?= csrf_field() ?>
        <input type="hidden" name="cash_register_id" x-bind:value="register?.id">
        <input type="hidden" name="solde_declare" x-bind:value="totalCompte">

        <!-- Résumé théorique -->
        <div class="grid grid-cols-2 gap-3">
          <div class="bg-slate-50 rounded-xl p-3 text-center">
            <p class="text-xs text-slate-500 mb-0.5">Fond initial</p>
            <p class="text-lg font-black text-slate-700" x-text="fmt(register?.opening ?? 0) + ' F'"></p>
          </div>
          <div class="bg-cb-bg rounded-xl p-3 text-center">
            <p class="text-xs text-cb-primary font-semibold mb-0.5">Solde théorique</p>
            <p class="text-lg font-black text-cb-primary" x-text="fmt(register?.theorique ?? 0) + ' F'"></p>
          </div>
        </div>

        <!-- Billettage -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
              <i data-lucide="banknote" class="w-4 h-4 text-cb-primary"></i> Comptage du caissier
            </h3>
            <button type="button" @click="resetCounts()"
                    class="text-xs text-slate-400 hover:text-cb-primary flex items-center gap-1">
              <i data-lucide="refresh-cw" class="w-3 h-3"></i> Réinitialiser
            </button>
          </div>

          <div class="border border-slate-200 rounded-xl overflow-hidden">
            <div class="grid grid-cols-4 text-xs font-bold text-slate-500 uppercase bg-slate-50 px-4 py-2 border-b border-slate-200">
              <span>Coupure</span>
              <span class="text-center">Quantité</span>
              <span class="text-right col-span-2">Sous-total</span>
            </div>
            <?php foreach ($denominations as $d): ?>
            <div class="grid grid-cols-4 items-center px-4 py-2.5 border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition">
              <span class="font-mono font-bold text-slate-700 text-sm"><?= number_format($d, 0, ',', ' ') ?> F</span>
              <div class="flex items-center justify-center">
                <button type="button"
                        @click="counts[<?= $d ?>] = Math.max(0, (counts[<?= $d ?>]||0) - 1)"
                        class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-lg leading-none transition">−</button>
                <input type="number" name="denom_<?= $d ?>"
                       x-model.number="counts[<?= $d ?>]"
                       min="0" step="1"
                       class="w-14 text-center mx-1 py-1 rounded-lg border border-slate-200 text-sm font-mono focus:border-cb-primary outline-none">
                <button type="button"
                        @click="counts[<?= $d ?>] = (counts[<?= $d ?>]||0) + 1"
                        class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-lg leading-none transition">+</button>
              </div>
              <div class="text-right col-span-2 font-mono font-semibold text-slate-800 text-sm"
                   x-text="fmt(<?= $d ?> * (counts[<?= $d ?>] || 0)) + ' F'"></div>
            </div>
            <?php endforeach ?>
            <!-- Total -->
            <div class="grid grid-cols-4 items-center px-4 py-3 bg-slate-50 border-t-2 border-slate-200">
              <span class="font-bold text-slate-700 text-sm col-span-2">Total compté</span>
              <div class="text-right col-span-2">
                <span class="font-mono font-black text-slate-900 text-base" x-text="fmt(totalCompte) + ' FCFA'"></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Écart -->
        <div class="rounded-xl border-2 p-4 flex items-center gap-4 transition-colors"
             :class="ecart === 0
               ? 'border-emerald-200 bg-emerald-50'
               : (ecart > 0 ? 'border-blue-200 bg-blue-50' : 'border-rose-200 bg-rose-50')">
          <i data-lucide="scale" class="w-5 h-5 shrink-0"
             :class="ecart===0?'text-emerald-600':(ecart>0?'text-blue-600':'text-rose-600')"></i>
          <div class="flex-1">
            <p class="text-xs font-semibold"
               :class="ecart===0?'text-emerald-700':(ecart>0?'text-blue-700':'text-rose-700')"
               x-text="ecart===0?'Solde exact — aucun écart':(ecart>0?'Excédent':'Déficit')"></p>
            <p class="text-sm font-bold mt-0.5"
               :class="ecart===0?'text-emerald-800':(ecart>0?'text-blue-800':'text-rose-800')"
               x-text="(ecart>0?'+':'') + fmt(ecart) + ' FCFA'"></p>
          </div>
          <div class="text-xs text-slate-500 text-right">
            <p>Théorique : <strong x-text="fmt(register?.theorique ?? 0)"></strong></p>
            <p>Compté : <strong x-text="fmt(totalCompte)"></strong></p>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Notes <span class="font-normal text-slate-400">(optionnel)</span></label>
          <textarea name="notes" rows="2" x-model="notes"
                    placeholder="Observations, anomalies constatées…"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm resize-none"></textarea>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 pt-1">
          <button type="button" @click="showClosure=false"
                  class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
            Annuler
          </button>
          <button type="submit"
                  class="flex-1 px-4 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-bold hover:bg-cb-dark transition flex items-center justify-center gap-2">
            <i data-lucide="lock" class="w-4 h-4"></i> Confirmer la clôture
          </button>
        </div>
      </form>
    </div>
  </div>


  <!-- ════════════════════════════════════════════════
       MODAL : Ouvrir une caisse
  ════════════════════════════════════════════════ -->
  <div x-show="showOpen" x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center p-4"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showOpen=false"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         @click.stop>

      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-cb-bg flex items-center justify-center">
            <i data-lucide="lock-open" class="w-4 h-4 text-cb-primary"></i>
          </div>
          <div>
            <h2 class="font-bold text-slate-900">Ouvrir une caisse</h2>
            <p class="text-xs text-slate-400">Démarrer une nouvelle session</p>
          </div>
        </div>
        <button @click="showOpen=false" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 transition">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <form method="post" action="<?= e(url('caisse/open')) ?>" class="p-6 space-y-4">
        <?= csrf_field() ?>

        <?php if ($postes): ?>
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Poste de caisse <span class="text-rose-500">*</span></label>
          <select name="caisse_id" required
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm bg-white">
            <option value="">-- Sélectionner un poste --</option>
            <?php foreach ($postes as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> — <?= e($p['agency_name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <?php endif ?>

        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Fond de caisse initial (FCFA)</label>
          <input type="number" name="opening_amount" value="0" min="0"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
          <p class="text-[11px] text-slate-400 mt-1">Montant en espèces déposé dans la caisse à l'ouverture.</p>
        </div>

        <div class="flex gap-3 pt-1">
          <button type="button" @click="showOpen=false"
                  class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
            Annuler
          </button>
          <button type="submit"
                  class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition flex items-center justify-center gap-2">
            <i data-lucide="lock-open" class="w-4 h-4"></i> Ouvrir
          </button>
        </div>
      </form>
    </div>
  </div>

</div><!-- /x-data -->

<script>
function caisseGestion(openRegisters, postes) {
  const DENOMS = [10000, 5000, 2000, 1000, 500, 100, 50, 25, 10, 5];
  return {
    openRegisters,
    postes,
    showClosure: false,
    showOpen:    false,
    register:    null,
    counts:      {},
    notes:       '',

    get totalCompte() {
      return DENOMS.reduce((s, d) => s + d * (parseInt(this.counts[d]) || 0), 0);
    },
    get ecart() {
      return this.register ? this.totalCompte - (this.register.theorique ?? 0) : 0;
    },

    openClosure(reg) {
      this.register = reg;
      this.counts   = {};
      this.notes    = '';
      this.showClosure = true;
      // Pre-fill denominations from theorique
      this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
    },

    resetCounts() {
      this.counts = {};
    },

    fmt(n) {
      return new Intl.NumberFormat('fr-FR').format(Math.round(n));
    },
  };
}
</script>
<?php $view->end() ?>
