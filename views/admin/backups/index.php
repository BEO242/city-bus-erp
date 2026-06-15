<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$fmtSize = function (int $b): string {
    if ($b < 1024) return $b . ' o';
    if ($b < 1048576) return number_format($b/1024, 1, ',', ' ') . ' Ko';
    if ($b < 1073741824) return number_format($b/1048576, 1, ',', ' ') . ' Mo';
    return number_format($b/1073741824, 2, ',', ' ') . ' Go';
};
$retention = \CityBus\Core\Setting::getInt('backup.retention_days', 30);
$enabled   = \CityBus\Core\Setting::getBool('backup.enabled', false);
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Sauvegardes</h1>
      <p class="text-slate-500 text-sm">Snapshots SQL de la base de données. Conservation : <?= (int)$retention ?> jours.</p>
    </div>
    <form method="post" action="<?= e(url('admin/backups/run')) ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerText='Sauvegarde en cours…';">
      <?= csrf_field() ?>
      <button class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary inline-flex items-center gap-2">
        <i data-lucide="database-backup" class="w-4 h-4"></i> Sauvegarder maintenant
      </button>
    </form>
  </div>

  <div class="bg-cb-bg/40 border border-cb-accent/30 rounded-2xl p-4 text-sm">
    <p class="font-medium text-cb-dark">
      Sauvegardes automatiques :
      <?php if ($enabled): ?>
        <span class="text-emerald-700 font-semibold">activées</span>
        (paramètre <code class="text-xs">backup.enabled</code>)
      <?php else: ?>
        <span class="text-amber-700 font-semibold">désactivées</span>
      <?php endif ?>
    </p>
    <p class="text-slate-600 mt-1">
      Les sauvegardes automatiques nécessitent un cron externe pointant sur
      <code class="text-xs bg-white px-1.5 py-0.5 rounded">php bin/backup.php</code>.
      Vous pouvez toujours déclencher une sauvegarde manuelle depuis cet écran.
    </p>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Fichier</th>
          <th class="px-5 py-3 text-left">Date</th>
          <th class="px-5 py-3 text-right">Taille</th>
          <th class="px-5 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($backups as $b): ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-3 font-mono text-xs text-slate-800"><?= e($b['name']) ?></td>
          <td class="px-5 py-3 text-slate-600"><?= e(date('d/m/Y H:i:s', $b['mtime'])) ?></td>
          <td class="px-5 py-3 text-right"><?= e($fmtSize($b['size'])) ?></td>
          <td class="px-5 py-3 text-right space-x-2">
            <a href="<?= e(url('admin/backups/' . $b['name'] . '/download')) ?>" class="text-cb-primary hover:underline">Télécharger</a>
            <form method="post" action="<?= e(url('admin/backups/' . $b['name'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Supprimer cette sauvegarde ?')">
              <?= csrf_field() ?>
              <button class="text-rose-600 hover:underline">Supprimer</button>
            </form>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$backups): ?>
        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-400">Aucune sauvegarde</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
