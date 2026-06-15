<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Agences</h1>
      <p class="text-slate-500 text-sm">Gestion des agences principales, points de vente et postes de contrôle.</p>
    </div>
    <a href="<?= e(url('referentiel/agencies/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary inline-flex items-center gap-2">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle agence
    </a>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 flex gap-2">
    <div class="relative flex-1">
      <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
      <input name="q" value="<?= e($q) ?>" placeholder="Rechercher…" class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 focus:border-cb-primary outline-none">
    </div>
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Filtrer</button>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">Nom</th>
          <th class="px-5 py-3 text-left">Ville</th>
          <th class="px-5 py-3 text-left">Type</th>
          <th class="px-5 py-3 text-left">Téléphone</th>
          <th class="px-5 py-3 text-center">Statut</th>
          <th class="px-5 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($data['items'] as $a): ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-3 font-semibold text-slate-900"><?= e($a['name']) ?>
            <div class="text-xs text-slate-400 font-normal"><?= e($a['address']) ?></div>
          </td>
          <td class="px-5 py-3"><?= e($a['city_name'] ?? str_replace('_','-',(string)($a['city'] ?? ''))) ?></td>
          <td class="px-5 py-3">
            <span class="px-2 py-0.5 rounded-full text-xs bg-cb-bg text-cb-primary"><?= e($a['type']) ?></span>
          </td>
          <td class="px-5 py-3 text-slate-600"><?= e($a['phone'] ?? '—') ?></td>
          <td class="px-5 py-3 text-center">
            <?php if ($a['is_active']): ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-700">Active</span>
            <?php else: ?>
              <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-500">Inactive</span>
            <?php endif ?>
          </td>
          <td class="px-5 py-3 text-right">
            <a href="<?= e(url('referentiel/agencies/'.$a['id'].'/edit')) ?>" class="text-cb-primary hover:underline">Modifier</a>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$data['items']): ?>
        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">Aucune agence</td></tr>
      <?php endif ?>
      </tbody>
    </table>
    <div class="p-4 flex justify-between items-center border-t border-slate-100">
      <span class="text-sm text-slate-500"><?= e($data['total']) ?> agence(s)</span>
      <?php $page = $data['page']; $pages = $data['pages']; include BASE_PATH.'/views/components/_pagination.php'; ?>
    </div>
  </div>
</div>
<?php $view->end() ?>
