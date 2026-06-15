<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$f = $filters;
$statusColors = [
  'depose'    => 'bg-slate-100 text-slate-700',
  'en_transit'=> 'bg-cb-bg text-cb-primary',
  'arrive'    => 'bg-emerald-50 text-emerald-700',
  'retire'    => 'bg-slate-100 text-slate-500',
  'perdu'     => 'bg-rose-100 text-rose-700',
  'endommage' => 'bg-orange-100 text-orange-700',
  'retourne'  => 'bg-amber-100 text-amber-700',
];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Colis</h1>
      <p class="text-slate-500 text-sm">Liste de tous les envois fret.</p>
    </div>
    <div class="flex gap-2">
      <a href="<?= e(url('cargo')) ?>" class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50">Dashboard</a>
      <?php if (can('cargo.create')): ?>
        <a href="<?= e(url('cargo/parcels/create')) ?>" class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary inline-flex items-center gap-2">
          <i data-lucide="plus" class="w-4 h-4"></i> Déposer
        </a>
      <?php endif ?>
    </div>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    <div class="bg-white rounded-xl border border-slate-100 p-4">
      <p class="text-xs text-slate-400">Total</p><p class="text-xl font-bold"><?= (int)$stats['total'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
      <p class="text-xs text-slate-400">Déposés</p><p class="text-xl font-bold text-slate-700"><?= (int)$stats['deposes'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
      <p class="text-xs text-slate-400">En transit</p><p class="text-xl font-bold text-cb-primary"><?= (int)$stats['en_transit'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
      <p class="text-xs text-slate-400">Arrivés</p><p class="text-xl font-bold text-emerald-600"><?= (int)$stats['arrives'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
      <p class="text-xs text-slate-400">Recettes</p><p class="text-lg font-bold"><?= e(fcfa((int)$stats['revenue'])) ?></p>
    </div>
  </div>

  <form method="get" class="bg-white rounded-2xl border border-slate-100 p-3 grid grid-cols-2 md:grid-cols-7 gap-2">
    <input name="q" value="<?= e($f['q']) ?>" placeholder="N°, téléphone, nom…" class="md:col-span-2 px-3 py-2 rounded-xl border border-slate-200">
    <select name="status" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="">Tous statuts</option>
      <?php foreach ($statuses as $k => $lbl): ?>
        <option value="<?= e($k) ?>" <?= $f['status']===$k?'selected':'' ?>><?= e($lbl) ?></option>
      <?php endforeach ?>
    </select>
    <select name="origin_agency_id" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="0">Origine : toutes</option>
      <?php foreach ($agencies as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= (int)$f['origin']===(int)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option>
      <?php endforeach ?>
    </select>
    <select name="destination_agency_id" class="px-3 py-2 rounded-xl border border-slate-200">
      <option value="0">Destination : toutes</option>
      <?php foreach ($agencies as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= (int)$f['dest']===(int)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option>
      <?php endforeach ?>
    </select>
    <input type="date" name="from" value="<?= e($f['from']) ?>" class="px-3 py-2 rounded-xl border border-slate-200">
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white">Filtrer</button>
  </form>

  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
        <tr>
          <th class="px-5 py-3 text-left">N°</th>
          <th class="px-5 py-3 text-left">Trajet</th>
          <th class="px-5 py-3 text-left">Expéditeur / Destinataire</th>
          <th class="px-5 py-3 text-right">Poids</th>
          <th class="px-5 py-3 text-right">Prix</th>
          <th class="px-5 py-3 text-center">Statut</th>
          <th class="px-5 py-3 text-right">Déposé le</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($parcels as $p): ?>
        <tr class="hover:bg-cb-bg/40">
          <td class="px-5 py-3">
            <a href="<?= e(url('cargo/parcels/' . $p['id'])) ?>" class="font-mono text-cb-primary hover:underline"><?= e($p['parcel_number']) ?></a>
            <div class="text-xs text-slate-400"><?= e($types[$p['parcel_type']] ?? $p['parcel_type']) ?></div>
          </td>
          <td class="px-5 py-3">
            <div class="text-sm"><?= e($p['origin_agency']) ?> → <?= e($p['destination_agency']) ?></div>
            <?php if ($p['trip_code']): ?>
              <div class="text-xs text-slate-400">Voyage <?= e($p['trip_code']) ?> · <?= e(date('d/m', strtotime((string)$p['trip_date']))) ?></div>
            <?php endif ?>
          </td>
          <td class="px-5 py-3">
            <div class="text-sm"><?= e($p['sender_name']) ?> · <?= e($p['sender_phone']) ?></div>
            <div class="text-xs text-slate-400">→ <?= e($p['recipient_name']) ?> · <?= e($p['recipient_phone']) ?></div>
          </td>
          <td class="px-5 py-3 text-right"><?= number_format((float)$p['weight_kg'], 2, ',', ' ') ?> kg</td>
          <td class="px-5 py-3 text-right font-semibold"><?= e(fcfa((int)$p['total_price_fcfa'])) ?></td>
          <td class="px-5 py-3 text-center">
            <span class="px-2 py-0.5 rounded-full text-xs <?= e($statusColors[$p['status']] ?? 'bg-slate-100') ?>">
              <?= e($statuses[$p['status']] ?? $p['status']) ?>
            </span>
          </td>
          <td class="px-5 py-3 text-right text-xs text-slate-500"><?= e(date('d/m/Y H:i', strtotime((string)$p['deposited_at']))) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$parcels): ?>
        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">Aucun colis</td></tr>
      <?php endif ?>
      </tbody>
    </table>
    <!-- Règle de fin de liste -->
    <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-3 text-xs text-slate-400">
      <div class="flex-1 h-px bg-slate-100"></div>
      <span><?= count($parcels ?? []) ?> enregistrement(s) affiché(s)<?= (($from ?? '') === '' && ($to ?? '') === '') ? ' · 30 derniers' : '' ?></span>
      <div class="flex-1 h-px bg-slate-100"></div>
    </div>
    <?php if ($lastPage > 1): ?>
    <div class="p-4 flex justify-between items-center border-t border-slate-100">
      <span class="text-sm text-slate-500"><?= (int)$total ?> colis · page <?= (int)$page ?> / <?= (int)$lastPage ?></span>
      <div class="flex gap-2">
        <?php if ($page > 1): ?>
          <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">Précédent</a>
        <?php endif ?>
        <?php if ($page < $lastPage): ?>
          <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">Suivant</a>
        <?php endif ?>
      </div>
    </div>
    <?php endif ?>
  </div>
</div>
<?php $view->end() ?>
