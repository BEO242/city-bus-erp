<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Avoirs (vouchers)</h1>
      <p class="text-slate-500 text-sm">Bons de compensation émis suite à incidents ou annulations.</p>
    </div>
    <?php if (can('vouchers.issue')): ?>
      <details class="bg-white rounded-2xl border border-slate-100 shadow-soft">
        <summary class="cursor-pointer px-4 py-2.5 rounded-xl bg-cb-primary text-white">+ Émettre un avoir</summary>
        <form method="post" action="<?= e(url('commerce/vouchers/issue')) ?>" class="p-4 space-y-2 w-80">
          <?= csrf_field() ?>
          <input type="number" name="amount" placeholder="Montant FCFA" required class="w-full px-3 py-2 rounded-xl border border-slate-200">
          <input type="number" name="customer_id" placeholder="ID client (optionnel)" class="w-full px-3 py-2 rounded-xl border border-slate-200">
          <input type="number" name="trip_id" placeholder="ID voyage (optionnel)" class="w-full px-3 py-2 rounded-xl border border-slate-200">
          <input type="number" name="validity_days" placeholder="Validité (jours)" value="90" class="w-full px-3 py-2 rounded-xl border border-slate-200">
          <textarea name="reason" placeholder="Motif" required rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
          <button class="w-full px-4 py-2 rounded-xl bg-cb-primary text-white">Émettre</button>
        </form>
      </details>
    <?php endif ?>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <input name="q" value="<?= e($q) ?>" placeholder="Code, client, téléphone…" class="flex-1 px-3 py-2 rounded-xl border border-slate-200">
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Rechercher</button>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Code</th>
          <th class="px-5 py-3 text-left">Client</th>
          <th class="px-5 py-3 text-left">Voyage</th>
          <th class="px-5 py-3 text-right">Émis</th>
          <th class="px-5 py-3 text-right">Restant</th>
          <th class="px-5 py-3 text-left">Validité</th>
          <th class="px-5 py-3 text-center">État</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($vouchers as $v): ?>
        <tr>
          <td class="px-5 py-3 font-mono text-cb-primary"><?= e($v['code']) ?></td>
          <td class="px-5 py-3"><?= e(trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '')) ?: $v['phone_display'] ?? '—') ?></td>
          <td class="px-5 py-3 text-xs"><?= e($v['trip_code'] ?? '—') ?></td>
          <td class="px-5 py-3 text-right"><?= e(fcfa((int)$v['issued_amount'])) ?></td>
          <td class="px-5 py-3 text-right font-bold"><?= e(fcfa((int)$v['remaining_amount'])) ?></td>
          <td class="px-5 py-3 text-xs"><?= $v['valid_until'] ? e(date('d/m/Y', strtotime((string)$v['valid_until']))) : '∞' ?></td>
          <td class="px-5 py-3 text-center text-xs">
            <?php if ($v['is_void']): ?><span class="px-2 py-0.5 rounded bg-rose-100 text-rose-700">Annulé</span>
            <?php elseif ((int)$v['remaining_amount'] === 0): ?><span class="px-2 py-0.5 rounded bg-slate-100">Utilisé</span>
            <?php elseif ($v['valid_until'] && strtotime($v['valid_until']) < time()): ?><span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700">Expiré</span>
            <?php else: ?><span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">Valide</span><?php endif ?>
          </td>
          <td class="px-5 py-3 text-right">
            <?php if (can('vouchers.issue') && !$v['is_void']): ?>
              <form method="post" action="<?= e(url('commerce/vouchers/' . $v['id'] . '/void')) ?>" class="inline" onsubmit="return confirm('Annuler ?')">
                <?= csrf_field() ?>
                <button class="text-xs text-rose-600 hover:underline">Annuler</button>
              </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$vouchers): ?>
        <tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">Aucun avoir</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
