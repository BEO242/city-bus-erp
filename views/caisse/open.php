<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <a href="<?= e(url('caisse')) ?>" class="text-sm text-slate-500 inline-flex items-center gap-1"><i data-lucide="chevron-left" class="w-4 h-4"></i> Retour</a>
  <h1 class="text-2xl font-bold mt-2">Ouvrir une caisse</h1>
  <p class="text-slate-500 text-sm mb-5">Saisissez le fond de caisse initial.</p>

  <form method="post" action="<?= e(url('caisse/open')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium mb-1">Agence</label>
      <select name="agency_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
        <?php foreach ($agencies as $a): ?>
          <option value="<?= e($a['id']) ?>"><?= e($a['name']) ?> — <?= e($a['city']) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Fond initial (FCFA)</label>
      <input type="number" name="opening_amount" required min="0" value="0" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-2xl font-bold text-cb-primary">
    </div>
    <div class="flex justify-end gap-2">
      <a href="<?= e(url('caisse')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium">Ouvrir</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
