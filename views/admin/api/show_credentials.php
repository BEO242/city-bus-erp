<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5 max-w-2xl">
  <div class="bg-amber-50 border border-amber-300 rounded-2xl p-6">
    <h1 class="text-xl font-bold text-amber-900 mb-2">⚠ Identifiants client API</h1>
    <p class="text-sm text-amber-800">Notez ces identifiants maintenant. Le secret ne sera <strong>plus jamais affiché</strong>.</p>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-3">
    <div>
      <label class="text-xs uppercase text-slate-400">Client ID</label>
      <code class="block font-mono text-sm bg-slate-50 p-3 rounded-xl mt-1 break-all"><?= e($client_id) ?></code>
    </div>
    <div>
      <label class="text-xs uppercase text-slate-400">Client Secret</label>
      <code class="block font-mono text-sm bg-slate-900 text-emerald-300 p-3 rounded-xl mt-1 break-all"><?= e($client_secret) ?></code>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 text-xs">
      <p class="text-slate-500 mb-1">Exemple obtention de token :</p>
      <pre class="text-xs overflow-x-auto">curl -X POST <?= e(url('api/oauth/token')) ?> \
  -d "grant_type=client_credentials" \
  -d "client_id=<?= e($client_id) ?>" \
  -d "client_secret=<?= e($client_secret) ?>"</pre>
    </div>
  </div>
  <a href="<?= e(url('admin/api')) ?>" class="px-4 py-2 rounded-xl bg-slate-900 text-white">J'ai noté les identifiants</a>
</div>
<?php $view->end() ?>
