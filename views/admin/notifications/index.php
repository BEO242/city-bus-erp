<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Templates de notifications</h1>
      <p class="text-slate-500 text-sm">SMS / Email envoyés automatiquement par l'application.</p>
    </div>
    <a href="<?= e(url('admin/notifications/logs')) ?>" class="px-4 py-2 rounded-xl border border-slate-200">Historique d'envoi</a>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Clé</th>
          <th class="px-5 py-3 text-left">Canal</th>
          <th class="px-5 py-3 text-left">Libellé</th>
          <th class="px-5 py-3 text-center">Actif</th>
          <th class="px-5 py-3 text-center">Système</th>
          <th class="px-5 py-3 text-center">Version</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($templates as $t): ?>
        <tr>
          <td class="px-5 py-3 font-mono text-xs"><?= e($t['template_key']) ?></td>
          <td class="px-5 py-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-100"><?= e($t['channel']) ?></span></td>
          <td class="px-5 py-3"><?= e($t['label']) ?></td>
          <td class="px-5 py-3 text-center"><?= $t['is_active'] ? '✓' : '×' ?></td>
          <td class="px-5 py-3 text-center"><?= $t['is_system'] ? '🔒' : '' ?></td>
          <td class="px-5 py-3 text-center text-xs">v<?= (int)$t['version'] ?></td>
          <td class="px-5 py-3 text-right"><a href="<?= e(url('admin/notifications/' . $t['id'] . '/edit')) ?>" class="text-cb-primary hover:underline">Modifier</a></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
