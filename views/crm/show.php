<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$fullName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: '—';
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('crm/customers')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($fullName) ?></h1>
    <p class="text-slate-500 text-sm"><?= e($customer['phone_display'] ?? $customer['phone_norm']) ?> · <?= e($customer['email'] ?? '—') ?></p>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Voyages</p>
      <p class="text-2xl font-bold"><?= (int)$customer['total_trips'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Bagages</p>
      <p class="text-2xl font-bold"><?= (int)$customer['total_baggage'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
      <p class="text-xs uppercase text-slate-400">Colis</p>
      <p class="text-2xl font-bold"><?= (int)$customer['total_parcels'] ?></p>
    </div>
    <div class="bg-cb-primary text-white rounded-2xl p-5 shadow-soft">
      <p class="text-xs uppercase opacity-80">Total dépensé</p>
      <p class="text-2xl font-bold"><?= e(fcfa((int)$customer['total_spent'])) ?></p>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <?php if (can('crm.edit')): ?>
    <div class="lg:col-span-1 bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold text-slate-900 mb-3">Profil</h2>
      <form method="post" action="<?= e(url('crm/customers/' . $customer['id'])) ?>" class="space-y-3">
        <?= csrf_field() ?>
        <div class="grid grid-cols-2 gap-2">
          <input name="first_name" placeholder="Prénom" value="<?= e($customer['first_name'] ?? '') ?>" class="px-3 py-2 rounded-xl border border-slate-200">
          <input name="last_name"  placeholder="Nom"    value="<?= e($customer['last_name']  ?? '') ?>" class="px-3 py-2 rounded-xl border border-slate-200">
        </div>
        <input name="email" placeholder="Email" value="<?= e($customer['email'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200">
        <input name="id_doc_number" placeholder="N° pièce" value="<?= e($customer['id_doc_number'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200">
        <input type="date" name="date_of_birth" value="<?= e($customer['date_of_birth'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200">
        <input name="preferred_seat" placeholder="Siège préféré" value="<?= e($customer['preferred_seat'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200">
        <textarea name="notes" rows="3" placeholder="Notes" class="w-full px-3 py-2 rounded-xl border border-slate-200"><?= e($customer['notes'] ?? '') ?></textarea>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="sms_opt_in" value="1" <?= $customer['sms_opt_in'] ? 'checked' : '' ?>>
          SMS marketing
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" name="email_opt_in" value="1" <?= $customer['email_opt_in'] ? 'checked' : '' ?>>
          Email marketing
        </label>
        <button class="w-full px-4 py-2 rounded-xl bg-cb-primary text-white">Enregistrer</button>
      </form>
    </div>
    <?php endif ?>

    <div class="<?= can('crm.edit') ? 'lg:col-span-2' : 'lg:col-span-3' ?> bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="font-semibold">Historique des voyages (<?= count($tickets) ?>)</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
          <tr>
            <th class="px-3 py-2 text-left">Ticket</th>
            <th class="px-3 py-2 text-left">Voyage</th>
            <th class="px-3 py-2 text-left">Ligne</th>
            <th class="px-3 py-2 text-right">Prix</th>
            <th class="px-3 py-2 text-center">Statut</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($tickets as $t): ?>
          <tr>
            <td class="px-3 py-2 font-mono text-xs"><?= e($t['ticket_number']) ?></td>
            <td class="px-3 py-2"><?= e($t['trip_code']) ?> · <?= e(date('d/m/Y', strtotime((string)$t['trip_date']))) ?></td>
            <td class="px-3 py-2"><?= e($t['line_code']) ?> · <?= e($t['line_name']) ?></td>
            <td class="px-3 py-2 text-right font-semibold"><?= e(fcfa((int)$t['price_fcfa'])) ?></td>
            <td class="px-3 py-2 text-center text-xs"><?= e($t['status']) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (!$tickets): ?>
          <tr><td colspan="5" class="py-6 text-center text-slate-400">Aucun voyage</td></tr>
        <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $view->end() ?>
