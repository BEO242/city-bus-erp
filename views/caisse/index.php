<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
  $theorique   = (int)($current['theoretical']    ?? 0);
  $fondInitial = (int)($current['opening_amount'] ?? 0);
  $closureNum  = '';
  if ($current) {
      $openedTs  = strtotime($current['opened_at']);
      $closureNum = 'CLO-' . date('ymd', $openedTs) . '-' . str_pad((string)$current['id'], 3, '0', STR_PAD_LEFT);
      $now       = time();
      $diffMin   = (int)(($now - $openedTs) / 60);
      $durHours  = intdiv($diffMin, 60);
      $durMins   = $diffMin % 60;
      $duration  = $durHours > 0 ? "{$durHours}h {$durMins}min" : "{$durMins} min";
  }
?>

<!-- Données PHP → JS (évite les guillemets dans l'attribut x-data) -->
<script>
window.__caisse = {
  theorique:   <?= $theorique ?>,
  fondInitial: <?= $fondInitial ?>,
  registerId:  <?= (int)($current['id'] ?? 0) ?>,
  postes:      <?= json_encode(array_values($postes ?? []), JSON_UNESCAPED_UNICODE) ?>,
};
</script>

<div x-data="caisseClose()" class="space-y-5">

  <!-- ═══════════════════════════════════════════════════════
       En-tête
  ════════════════════════════════════════════════════════ -->
  <div class="flex justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Clôture de Caisse</h1>
      <p class="text-slate-500 text-sm">Gestion des sessions et clôtures de caisse.</p>
    </div>
    <div class="flex items-center gap-2">
      <?php if (!$current): ?>
      <button type="button" @click="openOpen()"
              class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-semibold text-sm inline-flex items-center gap-2 hover:bg-cb-dark transition shadow-sm">
        <i data-lucide="lock-open" class="w-4 h-4"></i> Ouvrir une caisse
      </button>
      <?php endif ?>
      <a href="<?= e(url('finance/caisses')) ?>"
         class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
        <i data-lucide="settings-2" class="w-4 h-4 text-cb-primary"></i> Gérer les postes
      </a>
    </div>
  </div>

  <?php if ($current): ?>
  <!-- ═══════════════════════════════════════════════════════
       Session en cours
  ════════════════════════════════════════════════════════ -->
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-cb-primary to-cb-secondary px-6 py-4 flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
          <i data-lucide="wallet" class="w-5 h-5 text-white"></i>
        </div>
        <div>
          <p class="text-white/70 text-xs font-medium uppercase tracking-wide">Session en cours</p>
          <p class="text-white font-bold text-lg leading-tight">
            <?= e(!empty($current['caisse_name']) ? $current['caisse_name'] : ($current['agency_name'] ?? 'Caisse')) ?>
            <?php if (!empty($current['caisse_code'])): ?>
              <span class="text-white/60 text-sm font-mono ml-1"><?= e(strtoupper($current['caisse_code'])) ?></span>
            <?php endif ?>
          </p>
        </div>
      </div>
      <!-- Bouton clôture → ouvre le modal -->
      <button type="button" @click="openClose()"
              class="px-4 py-2 rounded-xl bg-white text-cb-primary font-semibold text-sm inline-flex items-center gap-2 hover:bg-white/90 transition shadow">
        <i data-lucide="lock" class="w-4 h-4"></i> Clôturer cette caisse
      </button>
    </div>

    <div class="px-6 py-4 grid grid-cols-2 sm:grid-cols-4 gap-4 border-b border-slate-100">
      <div>
        <p class="text-xs text-slate-500 mb-0.5">Caissier</p>
        <p class="text-sm font-semibold text-slate-800">
          <?php $u = auth(); echo e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))); ?>
        </p>
      </div>
      <div>
        <p class="text-xs text-slate-500 mb-0.5">Ouverture</p>
        <p class="text-sm font-semibold text-slate-800"><?= e(date('d/m/Y H:i', $openedTs)) ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-500 mb-0.5">Agence</p>
        <p class="text-sm font-semibold text-slate-800"><?= e($current['agency_name'] ?? '—') ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-500 mb-0.5">Durée</p>
        <p class="text-sm font-semibold text-slate-800"><?= e($duration) ?></p>
      </div>
    </div>

    <?php
      $sessEnc    = (int)($current['total_enc'] ?? 0);
      $sessDec    = (int)($current['total_dec'] ?? 0);
      $sessSolde  = $sessEnc - $sessDec;
      $sessSolPos = $sessSolde >= 0;
    ?>
    <div class="px-6 py-4 grid grid-cols-2 sm:grid-cols-4 gap-3 border-b border-slate-100">
      <!-- Fond initial -->
      <div class="bg-slate-50 rounded-xl p-3 text-center">
        <p class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider mb-1">Fond initial</p>
        <p class="text-sm font-black text-slate-800 tabular-nums"><?= number_format($fondInitial, 0, ',', ' ') ?></p>
        <p class="text-[10px] text-slate-400 font-mono">FCFA</p>
      </div>
      <!-- Encaissements -->
      <div class="bg-emerald-50 rounded-xl p-3 text-center border border-emerald-100">
        <p class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wider mb-1 flex items-center justify-center gap-1">
          <i data-lucide="arrow-down-left" class="w-3 h-3"></i> Encaissements
        </p>
        <p class="text-sm font-black text-emerald-700 tabular-nums">+ <?= number_format($sessEnc, 0, ',', ' ') ?></p>
        <p class="text-[10px] text-emerald-400 font-mono">FCFA</p>
      </div>
      <!-- Décaissements -->
      <div class="bg-rose-50 rounded-xl p-3 text-center border border-rose-100">
        <p class="text-[10px] text-rose-600 font-semibold uppercase tracking-wider mb-1 flex items-center justify-center gap-1">
          <i data-lucide="arrow-up-right" class="w-3 h-3"></i> Décaissements
        </p>
        <p class="text-sm font-black text-rose-700 tabular-nums">- <?= number_format($sessDec, 0, ',', ' ') ?></p>
        <p class="text-[10px] text-rose-400 font-mono">FCFA</p>
      </div>
      <!-- Solde net + Théorique -->
      <div class="<?= $sessSolPos ? 'bg-blue-50 border border-blue-100' : 'bg-orange-50 border border-orange-100' ?> rounded-xl p-3 text-center">
        <p class="text-[10px] <?= $sessSolPos ? 'text-blue-600' : 'text-orange-600' ?> font-semibold uppercase tracking-wider mb-1 flex items-center justify-center gap-1">
          <i data-lucide="scale" class="w-3 h-3"></i> Solde net
        </p>
        <p class="text-sm font-black <?= $sessSolPos ? 'text-blue-700' : 'text-orange-700' ?> tabular-nums">
          <?= ($sessSolPos ? '+' : '') . number_format($sessSolde, 0, ',', ' ') ?>
        </p>
        <p class="text-[10px] <?= $sessSolPos ? 'text-blue-400' : 'text-orange-400' ?> font-mono">FCFA · théo. <?= number_format($theorique, 0, ',', ' ') ?></p>
      </div>
    </div>
  </div>

  <?php endif ?>

  <!-- ═══════════════════════════════════════════════════════
       Historique des sessions
  ════════════════════════════════════════════════════════ -->
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
      <i data-lucide="history" class="w-4 h-4 text-cb-primary"></i>
      <span class="font-bold text-slate-700">Historique des sessions</span>
      <span class="ml-auto text-xs text-slate-400"><?= count($history) ?> session(s)</span>
    </div>

    <?php if (!$history): ?>
      <div class="p-10 text-center text-slate-400">
        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
        <p class="text-sm">Aucune session de caisse enregistrée.</p>
      </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-slate-500 uppercase bg-slate-50">
            <th class="px-4 py-3">N° CLO</th>
            <th class="px-4 py-3">Poste</th>
            <th class="px-4 py-3">Ouverture</th>
            <th class="px-4 py-3">Clôture</th>
            <th class="px-4 py-3 text-right">Fond initial</th>
            <th class="px-4 py-3 text-right text-emerald-600">Encaissements</th>
            <th class="px-4 py-3 text-right text-rose-500">Décaissements</th>
            <th class="px-4 py-3 text-right">Fond déclaré</th>
            <th class="px-4 py-3 text-right">Écart</th>
            <th class="px-4 py-3 text-center">Statut</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($history as $h):
            $hTs       = strtotime($h['opened_at']);
            $hCloNum   = 'CLO-' . date('ymd', $hTs) . '-' . str_pad((string)$h['id'], 3, '0', STR_PAD_LEFT);
            $gap       = (int)($h['gap_amount'] ?? 0);
            $gapCls    = $gap === 0 ? 'text-slate-500' : ($gap < 0 ? 'text-rose-600 font-semibold' : 'text-emerald-600 font-semibold');
            $gapSign   = $gap > 0 ? '+' : '';
            // Encaissements = ventes + treasury encaissements + entrées positives
            $encTotal  = (int)($h['total_ventes'] ?? 0)
                       + (int)($h['total_tx_enc'] ?? 0)
                       + (int)($h['total_entries_enc'] ?? 0);
            // Décaissements = treasury décaissements + entrées négatives
            $decTotal  = (int)($h['total_tx_dec'] ?? 0)
                       + (int)($h['total_entries_dec'] ?? 0);
            $statusConf = match($h['status']) {
              'ouverte'  => ['label' => 'Ouverte',   'cls' => 'bg-blue-50 text-blue-700'],
              'cloture'  => ['label' => 'Clôturée',  'cls' => 'bg-amber-50 text-amber-700'],
              'cloturee' => ['label' => 'Clôturée',  'cls' => 'bg-amber-50 text-amber-700'],
              'valide'   => ['label' => 'Validée',   'cls' => 'bg-emerald-50 text-emerald-700'],
              default    => ['label' => ucfirst($h['status']), 'cls' => 'bg-slate-100 text-slate-500'],
            };
          ?>
          <tr class="hover:bg-slate-50/50 transition">
            <td class="px-4 py-3 font-mono text-xs text-cb-primary font-semibold"><?= e($hCloNum) ?></td>
            <td class="px-4 py-3">
              <?php if (!empty($h['caisse_name'])): ?>
                <div class="font-medium text-slate-800 text-xs"><?= e($h['caisse_name']) ?></div>
                <div class="text-[10px] text-slate-400 font-mono"><?= e(strtoupper($h['caisse_code'] ?? '')) ?></div>
              <?php else: ?>
                <span class="text-slate-400 text-xs"><?= e($h['agency_name']) ?></span>
              <?php endif ?>
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs"><?= e(date('d/m/Y H:i', $hTs)) ?></td>
            <td class="px-4 py-3 text-slate-600 text-xs">
              <?= !empty($h['closed_at']) ? e(date('d/m/Y H:i', strtotime($h['closed_at']))) : '<span class="text-slate-300">—</span>' ?>
            </td>
            <td class="px-4 py-3 text-right text-slate-700 text-xs font-mono"><?= e(fcfa((int)($h['opening_amount'] ?? 0))) ?></td>
            <!-- Encaissements -->
            <td class="px-4 py-3 text-right text-xs font-mono">
              <?php if ($encTotal > 0): ?>
                <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">
                  <span class="text-emerald-400 font-bold">+</span><?= e(fcfa($encTotal)) ?>
                </span>
              <?php else: ?>
                <span class="text-slate-300">—</span>
              <?php endif ?>
            </td>
            <!-- Décaissements -->
            <td class="px-4 py-3 text-right text-xs font-mono">
              <?php if ($decTotal > 0): ?>
                <span class="inline-flex items-center gap-1 text-rose-600 font-semibold">
                  <span class="text-rose-400 font-bold">−</span><?= e(fcfa($decTotal)) ?>
                </span>
              <?php else: ?>
                <span class="text-slate-300">—</span>
              <?php endif ?>
            </td>
            <td class="px-4 py-3 text-right text-xs font-mono">
              <?= !empty($h['declared_amount']) ? e(fcfa((int)$h['declared_amount'])) : '<span class="text-slate-300">—</span>' ?>
            </td>
            <td class="px-4 py-3 text-right text-xs font-mono <?= $gapCls ?>">
              <?= !empty($h['closed_at']) ? $gapSign . e(fcfa(abs($gap))) : '<span class="text-slate-300">—</span>' ?>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusConf['cls'] ?>">
                <?= $statusConf['label'] ?>
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <?php if (!empty($h['closed_at'])): ?>
                <a href="<?= e(url('finance/treasury/closures/' . $h['closure_id'])) ?>"
                   class="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-cb-primary transition" title="Voir le détail">
                  <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                </a>
              <?php else: ?>
                <span class="text-slate-200">—</span>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </div>


  <!-- ═══════════════════════════════════════════════════════
       MODAL — Clôture avec billettage (2 étapes)
  ════════════════════════════════════════════════════════ -->
  <div x-show="showClose" x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center p-4"
       style="display:none;">

    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeClose()"></div>

    <!-- Panneau -->
    <div class="relative w-full max-w-xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">

      <!-- Gradient header -->
      <div class="bg-gradient-to-r from-cb-primary to-cb-secondary px-6 py-5 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
            <i data-lucide="lock" class="w-5 h-5 text-white"></i>
          </div>
          <div>
            <p class="text-white font-bold text-base leading-tight">Clôture de caisse</p>
            <p class="text-white/60 text-xs font-mono"><?= e($closureNum ?: '—') ?></p>
          </div>
        </div>
        <!-- Indicateur d'étape -->
        <div class="flex items-center gap-1.5">
          <div class="w-2 h-2 rounded-full transition-colors" :class="step===1 ? 'bg-white' : 'bg-white/30'"></div>
          <div class="w-2 h-2 rounded-full transition-colors" :class="step===2 ? 'bg-white' : 'bg-white/30'"></div>
          <button @click="closeClose()" class="ml-3 w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 transition text-white">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
      </div>

      <!-- ── Étape 1 : Billettage ── -->
      <div x-show="step===1" class="flex-1 overflow-y-auto">

        <!-- Carte récap -->
        <div class="grid grid-cols-3 gap-3 p-5 pb-0">
          <div class="bg-slate-50 rounded-xl p-3 text-center">
            <p class="text-[10px] text-slate-500 font-semibold uppercase mb-1">Fond initial</p>
            <p class="text-sm font-black text-slate-700 font-mono"><?= number_format($fondInitial, 0, ',', ' ') ?></p>
            <p class="text-[10px] text-slate-400">FCFA</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-3 text-center">
            <p class="text-[10px] text-slate-500 font-semibold uppercase mb-1">Théorique</p>
            <p class="text-sm font-black text-emerald-600 font-mono"><?= number_format($theorique, 0, ',', ' ') ?></p>
            <p class="text-[10px] text-slate-400">FCFA</p>
          </div>
          <div class="rounded-xl p-3 text-center border-2 transition-colors"
               :class="ecart===0 ? 'bg-emerald-50 border-emerald-200' : (ecart>0 ? 'bg-blue-50 border-blue-200' : 'bg-rose-50 border-rose-200')">
            <p class="text-[10px] font-semibold uppercase mb-1 opacity-70"
               :class="ecart===0 ? 'text-emerald-700' : (ecart>0 ? 'text-blue-700' : 'text-rose-700')">Écart</p>
            <p class="text-sm font-black font-mono"
               :class="ecart===0 ? 'text-emerald-700' : (ecart>0 ? 'text-blue-700' : 'text-rose-700')"
               x-text="(ecart>0?'+':'') + fmt(ecart)"></p>
            <p class="text-[10px] font-semibold opacity-60"
               :class="ecart===0 ? 'text-emerald-700' : (ecart>0 ? 'text-blue-700' : 'text-rose-700')"
               x-text="ecart===0 ? 'Exact' : (ecart>0 ? 'Excédent' : 'Déficit')"></p>
          </div>
        </div>

        <!-- Table billettage -->
        <div class="p-5">
          <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Comptage des billets & pièces</p>
          <div class="border border-slate-100 rounded-xl overflow-hidden">
            <!-- En-têtes -->
            <div class="grid grid-cols-4 text-[10px] font-bold text-slate-400 uppercase bg-slate-50 px-4 py-2 border-b border-slate-100">
              <span>Coupure</span>
              <span class="text-center">Quantité</span>
              <span class="text-center col-span-1">Ajuster</span>
              <span class="text-right">Sous-total</span>
            </div>
            <!-- Lignes coupures -->
            <?php foreach ([10000,5000,2000,1000,500,100,50,25,10,5] as $denom): ?>
            <div class="grid grid-cols-4 items-center px-4 py-2.5 border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition">
              <span class="font-mono font-bold text-slate-700 text-sm">
                <?= number_format($denom, 0, ',', ' ') ?> <span class="text-slate-400 text-xs font-normal">F</span>
              </span>
              <div class="text-center">
                <input type="number" min="0" step="1"
                       x-model.number="counts[<?= $denom ?>]"
                       @input="counts[<?= $denom ?>] = Math.max(0, parseInt($event.target.value)||0)"
                       class="w-16 text-center px-2 py-1.5 rounded-lg border border-slate-200 text-sm font-semibold focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
              </div>
              <div class="flex items-center justify-center gap-1">
                <button type="button" @click="counts[<?= $denom ?>] = Math.max(0,(counts[<?= $denom ?>]||0)-1)"
                        class="w-6 h-6 rounded-md bg-slate-100 hover:bg-rose-100 hover:text-rose-600 text-slate-500 flex items-center justify-center transition text-sm font-bold leading-none">−</button>
                <button type="button" @click="counts[<?= $denom ?>] = (counts[<?= $denom ?>]||0)+1"
                        class="w-6 h-6 rounded-md bg-slate-100 hover:bg-emerald-100 hover:text-emerald-600 text-slate-500 flex items-center justify-center transition text-sm font-bold leading-none">+</button>
              </div>
              <span class="text-right font-mono text-slate-600 text-sm"
                    x-text="fmt(<?= $denom ?> * (counts[<?= $denom ?>]||0))"></span>
            </div>
            <?php endforeach ?>
            <!-- Total -->
            <div class="grid grid-cols-4 items-center px-4 py-3 bg-slate-50 border-t-2 border-slate-200">
              <span class="col-span-3 font-bold text-slate-700 text-sm">Total compté</span>
              <span class="text-right font-black text-slate-900 font-mono" x-text="fmt(totalCompte) + ' FCFA'"></span>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div class="px-5 pb-5">
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Observations / Notes</label>
          <textarea x-model="notes" rows="2" placeholder="Anomalies constatées, commentaires…"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm resize-none"></textarea>
        </div>
      </div>

      <!-- ── Étape 2 : Confirmation ── -->
      <div x-show="step===2" class="flex-1 overflow-y-auto p-6 space-y-5">
        <div class="text-center">
          <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-3"
               :class="ecart===0 ? 'bg-emerald-100' : (ecart < 0 ? 'bg-rose-100' : 'bg-blue-100')">
            <i data-lucide="check-circle" class="w-7 h-7"
               :class="ecart===0 ? 'text-emerald-600' : (ecart < 0 ? 'text-rose-600' : 'text-blue-600')"></i>
          </div>
          <h3 class="font-bold text-slate-800 text-lg">Confirmer la clôture</h3>
          <p class="text-slate-500 text-sm mt-1">Vérifiez le récapitulatif avant de valider.</p>
        </div>

        <!-- Récapitulatif -->
        <div class="bg-slate-50 rounded-2xl p-4 space-y-3">
          <div class="flex justify-between items-center py-2 border-b border-slate-200">
            <span class="text-sm text-slate-500">N° de clôture</span>
            <span class="font-mono font-bold text-cb-primary text-sm"><?= e($closureNum ?: '—') ?></span>
          </div>
          <div class="flex justify-between items-center py-2 border-b border-slate-200">
            <span class="text-sm text-slate-500">Caisse</span>
            <span class="font-semibold text-slate-800 text-sm">
              <?= e(!empty($current['caisse_name']) ? $current['caisse_name'] : ($current['agency_name'] ?? '—')) ?>
            </span>
          </div>
          <div class="flex justify-between items-center py-2 border-b border-slate-200">
            <span class="text-sm text-slate-500">Fond initial</span>
            <span class="font-mono font-semibold text-slate-700 text-sm"><?= number_format($fondInitial, 0, ',', ' ') ?> FCFA</span>
          </div>
          <div class="flex justify-between items-center py-2 border-b border-slate-200">
            <span class="text-sm text-slate-500">Solde théorique</span>
            <span class="font-mono font-semibold text-emerald-600 text-sm"><?= number_format($theorique, 0, ',', ' ') ?> FCFA</span>
          </div>
          <div class="flex justify-between items-center py-2 border-b border-slate-200">
            <span class="text-sm text-slate-500">Billettage compté</span>
            <span class="font-mono font-bold text-slate-900 text-sm" x-text="fmt(totalCompte) + ' FCFA'"></span>
          </div>
          <div class="flex justify-between items-center py-2">
            <span class="text-sm font-bold text-slate-700">Écart</span>
            <span class="font-mono font-black text-base px-3 py-1 rounded-lg"
                  :class="ecart===0 ? 'bg-emerald-100 text-emerald-700' : (ecart>0 ? 'bg-blue-100 text-blue-700' : 'bg-rose-100 text-rose-700')"
                  x-text="(ecart>0?'+':'') + fmt(ecart) + ' FCFA'"></span>
          </div>
        </div>

        <!-- Notes (recap) -->
        <div x-show="notes" class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800">
          <p class="font-semibold text-xs text-amber-600 mb-1 uppercase">Note</p>
          <p x-text="notes" class="text-sm text-amber-700"></p>
        </div>

        <!-- Avertissement écart -->
        <div x-show="ecart !== 0"
             class="rounded-xl px-4 py-3 text-sm flex items-start gap-2"
             :class="ecart < 0 ? 'bg-rose-50 border border-rose-200 text-rose-800' : 'bg-blue-50 border border-blue-200 text-blue-800'">
          <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0"
             :class="ecart < 0 ? 'text-rose-500' : 'text-blue-500'"></i>
          <span x-text="ecart < 0 ? 'Attention : un déficit de ' + fmt(Math.abs(ecart)) + ' FCFA sera enregistré.' : 'Un excédent de ' + fmt(ecart) + ' FCFA sera enregistré.'"></span>
        </div>
      </div>

      <!-- ── Formulaire caché (soumis à l'étape 2) ── -->
      <form method="post" :action="closeUrl" id="closeForm" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="declared_amount" :value="totalCompte">
        <input type="hidden" name="notes" :value="notes">
        <?php foreach ([10000,5000,2000,1000,500,100,50,25,10,5] as $denom): ?>
        <input type="hidden" name="denom_<?= $denom ?>" :value="counts[<?= $denom ?>] || 0">
        <?php endforeach ?>
      </form>

      <!-- Footer boutons -->
      <div class="px-6 py-4 border-t border-slate-100 flex justify-between items-center gap-3 shrink-0 bg-white">
        <!-- Étape 1 -->
        <template x-if="step===1">
          <div class="flex gap-3 w-full">
            <button type="button" @click="closeClose()"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
              Annuler
            </button>
            <button type="button" @click="goConfirm()"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-bold hover:bg-cb-dark transition flex items-center justify-center gap-2">
              Continuer <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
          </div>
        </template>
        <!-- Étape 2 -->
        <template x-if="step===2">
          <div class="flex gap-3 w-full">
            <button type="button" @click="step=1"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition flex items-center justify-center gap-1.5">
              <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
            </button>
            <button type="button" @click="submitClose()"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-rose-600 text-white text-sm font-bold hover:bg-rose-700 transition flex items-center justify-center gap-2">
              <i data-lucide="lock" class="w-4 h-4"></i> Clôturer définitivement
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>


  <!-- ═══════════════════════════════════════════════════════
       MODAL — Ouvrir une caisse (2 étapes)
  ════════════════════════════════════════════════════════ -->
  <div x-show="showOpen" x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center p-4"
       style="display:none;">

    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeOpen()"></div>

    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">

      <!-- Header -->
      <div class="bg-gradient-to-r from-emerald-600 to-emerald-500 px-6 py-5 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
            <i data-lucide="lock-open" class="w-5 h-5 text-white"></i>
          </div>
          <div>
            <p class="text-white font-bold text-base">Ouvrir une caisse</p>
            <p class="text-white/60 text-xs" x-text="stepOpen===1 ? 'Démarrer une nouvelle session' : 'Confirmer l\'ouverture'"></p>
          </div>
        </div>
        <div class="flex items-center gap-1.5">
          <div class="w-2 h-2 rounded-full transition-colors" :class="stepOpen===1 ? 'bg-white' : 'bg-white/30'"></div>
          <div class="w-2 h-2 rounded-full transition-colors" :class="stepOpen===2 ? 'bg-white' : 'bg-white/30'"></div>
          <button @click="closeOpen()" class="ml-3 w-8 h-8 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 transition text-white">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>
      </div>

      <!-- ── Étape 1 : Formulaire ── -->
      <div x-show="stepOpen===1" class="flex-1 overflow-y-auto p-6 space-y-5">
        <?php if (!empty($postes)): ?>
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Poste de caisse <span class="text-rose-500">*</span></label>
          <select x-model="openPosteId"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none text-sm bg-white">
            <option value="">-- Sélectionner un poste --</option>
            <?php foreach ($postes as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> — <?= e($p['agency_name']) ?> (<?= e(strtoupper($p['code'])) ?>)</option>
            <?php endforeach ?>
          </select>
        </div>
        <?php endif ?>

        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Fond de caisse initial (FCFA)</label>
          <div class="relative">
            <input type="number" x-model.number="openAmount" min="0" step="1"
                   class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none text-sm pr-14">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-mono">FCFA</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-1">Montant en caisse au démarrage de la session.</p>
        </div>
      </div>

      <!-- ── Étape 2 : Confirmation ── -->
      <div x-show="stepOpen===2" class="flex-1 overflow-y-auto p-6 space-y-5">
        <div class="text-center">
          <div class="w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-3">
            <i data-lucide="check-circle" class="w-7 h-7 text-emerald-600"></i>
          </div>
          <h3 class="font-bold text-slate-800 text-lg">Confirmer l'ouverture</h3>
          <p class="text-slate-500 text-sm mt-1">Vérifiez les informations avant de démarrer.</p>
        </div>

        <div class="bg-slate-50 rounded-2xl p-4 space-y-0 divide-y divide-slate-200">
          <div class="flex justify-between items-center py-3">
            <span class="text-sm text-slate-500">Poste de caisse</span>
            <span class="font-semibold text-slate-800 text-sm text-right max-w-[55%]" x-text="selectedPosteName || 'Non spécifié'"></span>
          </div>
          <div class="flex justify-between items-center py-3">
            <span class="text-sm text-slate-500">Fond initial</span>
            <span class="font-mono font-bold text-emerald-600 text-sm" x-text="fmt(openAmount) + ' FCFA'"></span>
          </div>
          <div class="flex justify-between items-center py-3">
            <span class="text-sm text-slate-500">Date d'ouverture</span>
            <span class="font-semibold text-slate-800 text-sm"><?= date('d/m/Y H:i') ?></span>
          </div>
          <div class="flex justify-between items-center py-3">
            <span class="text-sm text-slate-500">Caissier</span>
            <span class="font-semibold text-slate-800 text-sm">
              <?php $u = auth(); echo e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))); ?>
            </span>
          </div>
        </div>

        <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 flex items-start gap-2">
          <i data-lucide="info" class="w-4 h-4 text-emerald-600 mt-0.5 shrink-0"></i>
          <p class="text-sm text-emerald-800">Une fois ouverte, la session devra être clôturée avec un billettage pour enregistrer l'écart.</p>
        </div>
      </div>

      <!-- Formulaire caché soumis à l'étape 2 -->
      <form method="post" action="<?= e(url('caisse/open')) ?>" id="openForm" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="caisse_id"       :value="openPosteId">
        <input type="hidden" name="opening_amount"  :value="openAmount">
      </form>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-100 flex gap-3 shrink-0 bg-white">
        <!-- Étape 1 -->
        <template x-if="stepOpen===1">
          <div class="flex gap-3 w-full">
            <button type="button" @click="closeOpen()"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
              Annuler
            </button>
            <button type="button" @click="goConfirmOpen()"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition flex items-center justify-center gap-2">
              Continuer <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
          </div>
        </template>
        <!-- Étape 2 -->
        <template x-if="stepOpen===2">
          <div class="flex gap-3 w-full">
            <button type="button" @click="stepOpen=1"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition flex items-center justify-center gap-1.5">
              <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
            </button>
            <button type="button" @click="submitOpen()"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition flex items-center justify-center gap-2">
              <i data-lucide="lock-open" class="w-4 h-4"></i> Ouvrir la caisse
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>

</div><!-- /x-data -->


<script>
function caisseClose() {
  // Données injectées par PHP via window.__caisse (évite les problèmes de guillemets dans x-data)
  const cfg      = window.__caisse || {};
  const theorique   = cfg.theorique   || 0;
  const fondInitial = cfg.fondInitial || 0;
  const postes      = cfg.postes      || [];
  const DENOMS      = [10000, 5000, 2000, 1000, 500, 100, 50, 25, 10, 5];
  const closeUrl    = '<?= e(url('caisse/close')) ?>';

  return {
    /* ── Clôture ── */
    showClose : false,
    step      : 1,
    counts    : {},
    notes     : '',
    closeUrl,

    get totalCompte() {
      return DENOMS.reduce((s, d) => s + d * (parseInt(this.counts[d]) || 0), 0);
    },
    get ecart() {
      return this.totalCompte - theorique;
    },
    openClose() {
      this.counts    = {};
      this.notes     = '';
      this.step      = 1;
      this.showClose = true;
    },
    closeClose() {
      this.showClose = false;
    },
    goConfirm() {
      this.step = 2;
    },
    submitClose() {
      document.getElementById('closeForm').submit();
    },

    /* ── Ouverture ── */
    showOpen    : false,
    stepOpen    : 1,
    openPosteId : '',
    openAmount  : 0,

    get selectedPosteName() {
      if (!this.openPosteId) return '';
      const p = postes.find(p => String(p.id) === String(this.openPosteId));
      return p ? p.name + (p.agency_name ? ' — ' + p.agency_name : '') : '';
    },
    openOpen() {
      this.openPosteId = '';
      this.openAmount  = 0;
      this.stepOpen    = 1;
      this.showOpen    = true;
    },
    closeOpen() {
      this.showOpen = false;
    },
    goConfirmOpen() {
      if (postes.length > 0 && !this.openPosteId) {
        alert('Veuillez sélectionner un poste de caisse.');
        return;
      }
      this.stepOpen = 2;
    },
    submitOpen() {
      document.getElementById('openForm').submit();
    },

    /* ── Utilitaires ── */
    fmt(n) {
      return new Intl.NumberFormat('fr-FR').format(Math.round(n));
    },
  };
}
</script>

<?php $view->end() ?>
