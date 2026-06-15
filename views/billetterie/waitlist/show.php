<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$statusColors = [
  'waiting'   => 'bg-slate-100 text-slate-700',
  'notified'  => 'bg-amber-100 text-amber-700',
  'converted' => 'bg-emerald-100 text-emerald-700',
  'expired'   => 'bg-rose-100 text-rose-700',
  'cancelled' => 'bg-slate-100 text-slate-400',
];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('voyages/' . $trip['id'])) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Voyage
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2">Liste d'attente · <?= e($trip['trip_code']) ?></h1>
    <p class="text-slate-500 text-sm"><?= e($trip['line_code']) ?> · <?= e($trip['line_name']) ?> · <?= e(date('d/m/Y', strtotime((string)$trip['trip_date']))) ?></p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <?php if (can('waitlist.manage')): ?>
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="font-semibold mb-3">Inscrire un passager</h2>
      <form method="post" action="<?= e(url('billetterie/waitlist/' . $trip['id'] . '/add')) ?>" class="space-y-2">
        <?= csrf_field() ?>
        <input name="passenger_name" placeholder="Nom passager" required class="w-full px-3 py-2 rounded-xl border border-slate-200">
        <input name="passenger_phone" placeholder="Téléphone" required class="w-full px-3 py-2 rounded-xl border border-slate-200">
        <input type="number" name="seats_requested" value="1" min="1" placeholder="Nb places" class="w-full px-3 py-2 rounded-xl border border-slate-200">
        <button class="w-full px-4 py-2 rounded-xl bg-cb-primary text-white">Inscrire</button>
      </form>
      <form method="post" action="<?= e(url('billetterie/waitlist/' . $trip['id'] . '/notify-next')) ?>" class="mt-3 pt-3 border-t border-slate-100">
        <?= csrf_field() ?>
        <button class="w-full px-4 py-2 rounded-xl bg-amber-500 text-white">Notifier le suivant</button>
        <p class="text-xs text-slate-400 mt-1 text-center">SMS au 1er en attente avec délai de confirmation.</p>
      </form>
    </div>
    <?php endif ?>

    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-soft">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="font-semibold">Passagers en attente (<?= count($entries) ?>)</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50/50 text-slate-600 text-xs uppercase">
          <tr>
            <th class="px-3 py-2 text-left">Pos.</th>
            <th class="px-3 py-2 text-left">Passager</th>
            <th class="px-3 py-2 text-left">Téléphone</th>
            <th class="px-3 py-2 text-right">Sièges</th>
            <th class="px-3 py-2 text-center">État</th>
            <th class="px-3 py-2 text-right">Inscrit</th>
            <th></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($entries as $en): ?>
          <tr>
            <td class="px-3 py-2 font-bold"><?= (int)$en['position'] ?></td>
            <td class="px-3 py-2"><?= e($en['passenger_name']) ?></td>
            <td class="px-3 py-2 text-xs"><?= e($en['passenger_phone']) ?></td>
            <td class="px-3 py-2 text-right"><?= (int)$en['seats_requested'] ?></td>
            <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs <?= e($statusColors[$en['status']] ?? '') ?>"><?= e($en['status']) ?></span></td>
            <td class="px-3 py-2 text-right text-xs"><?= e(date('d/m H:i', strtotime((string)$en['requested_at']))) ?></td>
            <td class="px-3 py-2 text-right">
              <?php if (in_array($en['status'], ['waiting','notified'], true) && can('waitlist.manage')): ?>
                <form method="post" action="<?= e(url('billetterie/waitlist/entry/' . $en['id'] . '/cancel')) ?>" class="inline" onsubmit="return confirm('Annuler ?')">
                  <?= csrf_field() ?>
                  <button class="text-xs text-rose-600 hover:underline">×</button>
                </form>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (!$entries): ?>
          <tr><td colspan="7" class="px-3 py-12 text-center text-slate-400">Aucune inscription</td></tr>
        <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $view->end() ?>
