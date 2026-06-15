<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5 max-w-4xl mx-auto">
  <a href="<?= e(url('finance/invoices')) ?>" class="text-sm text-slate-500 inline-flex items-center gap-1"><i data-lucide="chevron-left" class="w-4 h-4"></i> Retour</a>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
    <div class="flex items-start justify-between mb-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e($inv['invoice_number']) ?></h1>
        <p class="text-sm text-slate-500"><?= e(ucfirst($inv['type'])) ?> · Émise le <?= e(date('d/m/Y H:i', strtotime($inv['issued_at']))) ?></p>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-slate-100"><?= e($inv['status']) ?></span>
    </div>

    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-600">
        <tr>
          <th class="px-3 py-2 text-left">Description</th>
          <th class="px-3 py-2 text-right">Qté</th>
          <th class="px-3 py-2 text-right">PU HT</th>
          <th class="px-3 py-2 text-right">HT</th>
          <th class="px-3 py-2 text-right">TVA %</th>
          <th class="px-3 py-2 text-right">TVA</th>
          <th class="px-3 py-2 text-right">TTC</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($inv['lines'] as $l): ?>
          <tr>
            <td class="px-3 py-2"><?= e($l['description']) ?> <span class="text-xs text-slate-400">(<?= e($l['line_type']) ?>)</span></td>
            <td class="px-3 py-2 text-right"><?= (int)$l['quantity'] ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$l['unit_price_ht']) ?></td>
            <td class="px-3 py-2 text-right font-mono"><?= number_format((int)$l['amount_ht']) ?></td>
            <td class="px-3 py-2 text-right text-xs"><?= e($l['tax_pct']) ?>%</td>
            <td class="px-3 py-2 text-right font-mono text-slate-500"><?= number_format((int)$l['tax_amount']) ?></td>
            <td class="px-3 py-2 text-right font-mono font-bold"><?= number_format((int)$l['amount_ttc']) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot class="border-t-2 border-slate-300">
        <tr><td colspan="3"></td>
          <td class="px-3 py-2 text-right font-bold">Total HT</td>
          <td colspan="2"></td>
          <td class="px-3 py-2 text-right font-mono font-bold"><?= number_format((int)$inv['total_ht']) ?></td>
        </tr>
        <tr><td colspan="3"></td>
          <td class="px-3 py-2 text-right font-bold">Total TVA</td>
          <td colspan="2"></td>
          <td class="px-3 py-2 text-right font-mono font-bold text-slate-500"><?= number_format((int)$inv['total_tax']) ?></td>
        </tr>
        <tr class="bg-slate-50"><td colspan="3"></td>
          <td class="px-3 py-3 text-right font-bold uppercase">Total TTC</td>
          <td colspan="2"></td>
          <td class="px-3 py-3 text-right font-mono font-bold text-cb-primary text-lg"><?= number_format((int)$inv['total_ttc']) ?> <?= e($inv['currency']) ?></td>
        </tr>
      </tfoot>
    </table>

    <?php if ($inv['notes']): ?><div class="mt-4 text-sm text-slate-600 bg-amber-50 p-3 rounded"><?= e($inv['notes']) ?></div><?php endif ?>
  </div>
</div>
<?php $view->end() ?>
