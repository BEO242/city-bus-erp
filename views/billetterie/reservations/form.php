<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="space-y-5" x-data="{ items: [{ trip_id: '<?= $trip['id'] ?? '' ?>', passenger_name: '', passenger_phone: '', seat_number: '', passenger_category: 'adulte', price_fcfa: 0 }] }">
  <div>
    <a href="<?= e(url('billetterie/reservations')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2">Nouvelle réservation</h1>
  </div>

  <form method="post" action="<?= e(url('billetterie/reservations')) ?>" class="space-y-5">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-4">
      <h2 class="font-semibold">Contact (titulaire de la réservation)</h2>
      <div class="grid grid-cols-3 gap-4">
        <input name="contact_name"  required placeholder="Nom complet *"   class="px-3 py-2.5 rounded-xl border border-slate-200">
        <input name="contact_phone" required placeholder="Téléphone *"     class="px-3 py-2.5 rounded-xl border border-slate-200">
        <input name="contact_email" placeholder="Email"                    class="px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <select name="channel" class="px-3 py-2.5 rounded-xl border border-slate-200">
        <option value="counter">Guichet</option>
        <option value="phone">Téléphone</option>
        <option value="agent">Agent commercial</option>
        <option value="partner">Partenaire</option>
      </select>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft space-y-4">
      <div class="flex justify-between items-center">
        <h2 class="font-semibold">Passagers</h2>
        <button type="button" @click="items.push({ trip_id: '<?= $trip['id'] ?? '' ?>', passenger_name: '', passenger_phone: '', seat_number: '', passenger_category: 'adulte', price_fcfa: 0 })" class="text-sm text-cb-primary hover:underline">+ Ajouter un passager</button>
      </div>
      <template x-for="(item, idx) in items" :key="idx">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-2 p-3 bg-slate-50/50 rounded-xl">
          <input type="number" :name="'items[' + idx + '][trip_id]'"     placeholder="ID Voyage" required x-model="item.trip_id"     class="px-2 py-1.5 rounded border border-slate-200">
          <input :name="'items[' + idx + '][passenger_name]'"  placeholder="Nom passager" required x-model="item.passenger_name"  class="px-2 py-1.5 rounded border border-slate-200 md:col-span-2">
          <input :name="'items[' + idx + '][passenger_phone]'" placeholder="Téléphone"            x-model="item.passenger_phone" class="px-2 py-1.5 rounded border border-slate-200">
          <input :name="'items[' + idx + '][seat_number]'"     placeholder="Siège"                x-model="item.seat_number"     class="px-2 py-1.5 rounded border border-slate-200">
          <select :name="'items[' + idx + '][passenger_category]'" x-model="item.passenger_category" class="px-2 py-1.5 rounded border border-slate-200">
            <option value="adulte">Adulte</option>
            <option value="enfant">Enfant</option>
            <option value="etudiant">Étudiant</option>
            <option value="vip">VIP</option>
          </select>
          <input type="number" :name="'items[' + idx + '][price_fcfa]'" placeholder="Prix FCFA" required x-model.number="item.price_fcfa" class="px-2 py-1.5 rounded border border-slate-200">
        </div>
      </template>
    </div>

    <div class="flex justify-end gap-2">
      <a href="<?= e(url('billetterie/reservations')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium">Réserver (hold)</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
