<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Clients API publique</h1>
      <p class="text-slate-500 text-sm">OAuth2 client_credentials pour intégrateurs externes.</p>
    </div>
    <a href="<?= e(url('admin/api/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white">+ Nouveau client</a>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Nom</th>
          <th class="px-5 py-3 text-left">Client ID</th>
          <th class="px-5 py-3 text-left">Scopes</th>
          <th class="px-5 py-3 text-right">Quota/min</th>
          <th class="px-5 py-3 text-right">Appels 24h</th>
          <th class="px-5 py-3 text-center">Actif</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($clients as $c): ?>
        <tr>
          <td class="px-5 py-3 font-semibold"><?= e($c['name']) ?><?php if ($c['description']): ?><div class="text-xs text-slate-400"><?= e($c['description']) ?></div><?php endif ?></td>
          <td class="px-5 py-3 font-mono text-xs"><?= e($c['client_id']) ?></td>
          <td class="px-5 py-3 text-xs"><?= e($c['scopes']) ?></td>
          <td class="px-5 py-3 text-right"><?= (int)$c['rate_limit_per_min'] ?></td>
          <td class="px-5 py-3 text-right"><?= (int)$c['calls_24h'] ?></td>
          <td class="px-5 py-3 text-center"><?= $c['is_active'] ? '✓' : '×' ?></td>
          <td class="px-5 py-3 text-right">
            <?php if ($c['is_active']): ?>
              <form method="post" action="<?= e(url('admin/api/' . $c['id'] . '/revoke')) ?>" onsubmit="return confirm('Révoquer ?')">
                <?= csrf_field() ?>
                <button class="text-xs text-rose-600 hover:underline">Révoquer</button>
              </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$clients): ?><tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Aucun client API</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->end() ?>
