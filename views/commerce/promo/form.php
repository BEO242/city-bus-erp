<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('commerce/promo')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
  </div>
  <form method="post" action="<?= e(url('commerce/promo')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft max-w-2xl">
    <?= csrf_field() ?>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Code *</label>
        <input name="code" required value="<?= e(old('code')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 font-mono uppercase">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Libellé *</label>
        <input name="label" required value="<?= e(old('label')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Type *</label>
        <select name="discount_type" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="percent">Pourcentage</option>
          <option value="fixed">Montant fixe FCFA</option>
          <option value="free_seat">Place gratuite</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Valeur *</label>
        <input type="number" name="discount_value" required value="<?= e(old('discount_value', 10)) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Montant min. (FCFA)</label>
        <input type="number" name="min_amount_fcfa" value="<?= e(old('min_amount_fcfa', 0)) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Plafond remise (FCFA)</label>
        <input type="number" name="max_discount_fcfa" value="<?= e(old('max_discount_fcfa')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Utilisations max (total)</label>
        <input type="number" name="max_uses" value="<?= e(old('max_uses')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Max par client</label>
        <input type="number" name="max_uses_per_customer" value="<?= e(old('max_uses_per_customer', 1)) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Valide du</label>
        <input type="datetime-local" name="valid_from" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">au</label>
        <input type="datetime-local" name="valid_until" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
    </div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked> <span class="text-sm">Actif</span></label>
    <div class="flex justify-end gap-2">
      <a href="<?= e(url('commerce/promo')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white">Créer</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
