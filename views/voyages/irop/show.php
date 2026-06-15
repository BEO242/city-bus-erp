<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app');
$sevColor = match($irop['severity']) { 'critical'=>'rose', 'high'=>'orange', 'medium'=>'amber', 'low'=>'sky', default=>'slate' };
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('voyages/irop')) ?>" class="hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> IROP
    </a>
    <span>/</span>
    <span class="text-slate-800 font-semibold">#<?= (int)$irop['id'] ?></span>
  </div>

  <div class="bg-gradient-to-r from-<?= $sevColor ?>-600 to-<?= $sevColor ?>-700 text-white rounded-2xl p-6 shadow-soft">
    <div class="flex items-start justify-between">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span class="px-2 py-0.5 rounded bg-white/20 text-xs font-bold uppercase"><?= e($irop['severity']) ?></span>
          <span class="px-2 py-0.5 rounded bg-white/20 text-xs font-semibold"><?= e($irop['irop_type']) ?></span>
          <span class="px-2 py-0.5 rounded bg-white/20 text-xs"><?= e($irop['status']) ?></span>
        </div>
        <div class="text-2xl font-bold"><?= e($irop['trip_code']) ?></div>
        <div class="text-sm opacity-90 mt-1"><?= e(date('d/m/Y', strtotime($irop['trip_date']))) ?></div>
      </div>
      <div class="text-right text-sm">
        <div class="opacity-80">Pax impactés</div>
        <div class="text-3xl font-bold"><?= (int)$irop['impact_pax'] ?></div>
        <?php if ((int)$irop['delay_minutes']>0): ?>
          <div class="mt-2 opacity-80">Retard</div>
          <div class="text-xl font-bold">+<?= (int)$irop['delay_minutes'] ?> min</div>
        <?php endif ?>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-soft">
    <h2 class="font-bold text-slate-900 mb-2">Motif</h2>
    <p class="text-sm text-slate-700 whitespace-pre-wrap"><?= e($irop['reason']) ?></p>
    <?php if (!empty($irop['notes'])): ?>
      <h3 class="font-bold text-slate-900 mt-4 mb-2">Notes</h3>
      <p class="text-sm text-slate-700 whitespace-pre-wrap"><?= e($irop['notes']) ?></p>
    <?php endif ?>
    <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
      Ouvert par <strong><?= e($irop['opened_by_name'] ?? 'Système') ?></strong> · <?= e(date('d/m/Y H:i', strtotime($irop['opened_at']))) ?>
    </div>
  </div>

  <!-- Actions IROP -->
  <?php if (in_array($irop['status'], ['open','rebooking'])): ?>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-soft">
      <h2 class="font-bold text-slate-900 mb-3">Actions</h2>
      <div class="flex flex-wrap gap-2">
        <?php if (can('voyages.irop.rebook') && empty($rebookings)): ?>
          <form method="post" action="<?= e(url('voyages/irop/' . $irop['id'] . '/init-rebook')) ?>">
            <?= csrf_field() ?>
            <button class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600">
              <i data-lucide="users" class="inline w-4 h-4"></i> Initier rebooking (<?= (int)$irop['impact_pax'] ?> pax)
            </button>
          </form>
        <?php endif ?>
        <?php if (can('voyages.irop.manage')): ?>
          <form method="post" action="<?= e(url('voyages/irop/' . $irop['id'] . '/resolve')) ?>" onsubmit="return confirm('Marquer cet IROP comme résolu ?')">
            <?= csrf_field() ?>
            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
              <i data-lucide="check" class="inline w-4 h-4"></i> Résoudre
            </button>
          </form>
          <form method="post" action="<?= e(url('voyages/irop/' . $irop['id'] . '/close')) ?>" onsubmit="return confirm('Fermer définitivement cet IROP ?')">
            <?= csrf_field() ?>
            <button class="px-4 py-2 rounded-lg bg-slate-600 text-white text-sm font-semibold hover:bg-slate-700">
              <i data-lucide="lock" class="inline w-4 h-4"></i> Fermer
            </button>
          </form>
        <?php endif ?>
      </div>
    </div>
  <?php endif ?>

  <!-- Rebookings -->
  <?php if (!empty($rebookings)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100">
        <h2 class="font-bold text-slate-900">Demandes de rebooking (<?= count($rebookings) ?>)</h2>
      </div>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-600">
          <tr>
            <th class="px-3 py-2 text-left">PNR</th>
            <th class="px-3 py-2 text-left">Passager</th>
            <th class="px-3 py-2 text-left">Téléphone</th>
            <th class="px-3 py-2 text-right">Remb.</th>
            <th class="px-3 py-2 text-right">Comp.</th>
            <th class="px-3 py-2 text-left">Statut</th>
            <th class="px-3 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($rebookings as $r): ?>
            <tr>
              <td class="px-3 py-2 font-mono text-xs"><?= e($r['original_pnr'] ?? '—') ?></td>
              <td class="px-3 py-2"><?= e($r['pax_name'] ?? '—') ?></td>
              <td class="px-3 py-2 text-xs"><?= e($r['customer_phone'] ?? '—') ?></td>
              <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$r['refund_amount']) ?></td>
              <td class="px-3 py-2 text-right font-mono text-emerald-700"><?= number_format((int)$r['compensation']) ?></td>
              <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded bg-slate-100"><?= e($r['status']) ?></span>
                <?php if ($r['new_trip_code']): ?>
                  <div class="text-xs text-emerald-600 mt-1">→ <?= e($r['new_trip_code']) ?></div>
                <?php endif ?>
              </td>
              <td class="px-3 py-2 text-right">
                <?php if (can('voyages.irop.rebook') && in_array($r['status'], ['pending','offered'])): ?>
                  <form method="post" action="<?= e(url('voyages/irop/' . $irop['id'] . '/rebook/' . $r['id'])) ?>" class="inline">
                    <?= csrf_field() ?>
                    <select name="new_trip_id" class="text-xs border border-slate-200 rounded px-1 py-0.5">
                      <option value="">→ voyage</option>
                      <?php foreach ($altTrips as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= e($t['trip_code']) ?> · <?= e(date('d/m H:i', strtotime($t['trip_date'].' '.$t['departure_time']))) ?></option>
                      <?php endforeach ?>
                    </select>
                    <button class="text-xs text-emerald-700 hover:underline ml-1">OK</button>
                  </form>
                  <form method="post" action="<?= e(url('voyages/irop/' . $irop['id'] . '/refund/' . $r['id'])) ?>" class="inline ml-2">
                    <?= csrf_field() ?>
                    <button class="text-xs text-rose-600 hover:underline">Rembourser</button>
                  </form>
                <?php endif ?>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>
<?php $view->end() ?>
