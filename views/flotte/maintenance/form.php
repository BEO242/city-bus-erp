<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <a href="<?= e(url('flotte/maintenance')) ?>" class="text-sm text-slate-500 inline-flex items-center gap-1"><i data-lucide="chevron-left" class="w-4 h-4"></i> Retour</a>
  <h1 class="text-2xl font-bold">Nouvelle maintenance</h1>

  <form method="post" action="<?= e(url('flotte/maintenance')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft" x-data="{ mode: 'realized' }">
    <?= csrf_field() ?>

    <div>
      <label class="block text-sm font-medium mb-1">Nature de saisie</label>
      <select name="entry_mode" x-model="mode" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
        <option value="realized">Maintenance réalisée (donnée réelle)</option>
        <option value="planned">Maintenance planifiée (prévision)</option>
      </select>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Bus</label>
        <select name="bus_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <?php foreach ($buses as $b): ?>
            <option value="<?= e($b['id']) ?>"><?= e($b['code']) ?> — <?= e($b['plate']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Type</label>
        <select name="type" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="preventive">Préventive</option>
          <option value="corrective">Corrective (non prévue)</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1" x-text="mode === 'realized' ? 'Date de réalisation' : 'Date prévue'"></label>
        <input type="date" name="done_on" x-show="mode === 'realized'" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
        <input type="date" name="scheduled_at" x-show="mode === 'planned'" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1" x-text="mode === 'realized' ? 'Coût réel (FCFA)' : 'Coût estimé (FCFA)'"></label>
        <input type="number" name="actual_cost" min="0" value="0" x-show="mode === 'realized'" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
        <input type="number" name="estimated_cost" min="0" value="0" x-show="mode === 'planned'" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Mécanicien (optionnel)</label>
        <select name="mechanic_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="">— Non renseigné —</option>
          <?php foreach ($mechanics as $m): ?>
            <option value="<?= e($m['id']) ?>"><?= e(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Description</label>
      <textarea name="description" rows="3" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200"></textarea>
    </div>
    <div class="flex justify-end gap-2">
      <a href="<?= e(url('flotte/maintenance')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium" x-text="mode === 'realized' ? 'Enregistrer la maintenance réalisée' : 'Créer l\'ordre planifié'"></button>
    </div>
  </form>
</div>
<?php $view->end() ?>
