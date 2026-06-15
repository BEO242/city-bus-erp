<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$colors = ['sent' => 'bg-emerald-100 text-emerald-700', 'failed' => 'bg-rose-100 text-rose-700', 'queued' => 'bg-slate-100', 'bounced' => 'bg-amber-100 text-amber-700'];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('admin/notifications')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2">Historique des notifications</h1>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">Template</th>
          <th class="px-3 py-2 text-left">Canal</th>
          <th class="px-3 py-2 text-left">Destinataire</th>
          <th class="px-3 py-2 text-left">Aperçu</th>
          <th class="px-3 py-2 text-center">Statut</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="px-3 py-2 text-xs"><?= e(date('d/m H:i', strtotime((string)$l['created_at']))) ?></td>
          <td class="px-3 py-2 font-mono text-xs"><?= e($l['template_key']) ?></td>
          <td class="px-3 py-2 text-xs"><?= e($l['channel']) ?></td>
          <td class="px-3 py-2 text-xs"><?= e($l['recipient']) ?></td>
          <td class="px-3 py-2 text-xs text-slate-500" title="<?= e($l['body']) ?>"><?= e(mb_substr($l['body'], 0, 60)) ?>…</td>
          <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs <?= e($colors[$l['status']] ?? '') ?>"><?= e($l['status']) ?></span></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$logs): ?><tr><td colspan="6" class="py-6 text-center text-slate-400">Aucun log</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
