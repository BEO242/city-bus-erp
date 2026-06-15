<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('commerce/corporate')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
  </div>
  <form method="post" action="<?= e(url('commerce/corporate')) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft max-w-2xl">
    <?= csrf_field() ?>
    <input name="company_name" required placeholder="Nom de la société *" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    <input name="legal_id" placeholder="N° RCCM / NIU" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    <div class="grid grid-cols-2 gap-4">
      <input name="contact_name"  required placeholder="Contact *" class="px-3 py-2.5 rounded-xl border border-slate-200">
      <input name="contact_phone" required placeholder="Téléphone *" class="px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <input name="contact_email" placeholder="Email" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    <input name="address" placeholder="Adresse" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    <div class="grid grid-cols-3 gap-4">
      <div><label class="text-xs text-slate-500">Remise %</label><input type="number" step="0.01" name="discount_percent" value="0" class="w-full px-3 py-2 rounded-xl border border-slate-200"></div>
      <div><label class="text-xs text-slate-500">Crédit FCFA</label><input type="number" name="credit_limit_fcfa" value="0" class="w-full px-3 py-2 rounded-xl border border-slate-200"></div>
      <div><label class="text-xs text-slate-500">Délai paiement (jours)</label><input type="number" name="payment_terms_days" value="30" class="w-full px-3 py-2 rounded-xl border border-slate-200"></div>
    </div>
    <textarea name="notes" rows="2" placeholder="Notes" class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked> Actif</label>
    <div class="flex justify-end gap-2">
      <a href="<?= e(url('commerce/corporate')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white">Créer</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
