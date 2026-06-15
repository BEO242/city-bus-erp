<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
    <i data-lucide="git-merge" class="w-6 h-6 text-amber-600"></i> Doublons clients (<?= count($duplicates) ?>)
  </h1>

  <?php if (empty($duplicates)): ?>
    <div class="bg-white rounded-2xl p-12 text-center border border-slate-100 shadow-soft">
      <i data-lucide="check-circle-2" class="w-12 h-12 mx-auto text-emerald-500 mb-3"></i>
      <p class="text-slate-600">Aucun doublon détecté.</p>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-600">
          <tr>
            <th class="px-3 py-2 text-left">Téléphone normalisé</th>
            <th class="px-3 py-2 text-center">Doublons</th>
            <th class="px-3 py-2 text-left">Détails</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($duplicates as $d):
            $ids = explode(',', $d['ids']);
            $custs = \CityBus\Core\Database::select("SELECT id, first_name, last_name, total_trips, total_spent, last_trip_at FROM customers WHERE id IN (" . implode(',', array_map('intval',$ids)) . ") ORDER BY total_spent DESC");
            $keep = $custs[0] ?? null;
          ?>
            <tr>
              <td class="px-3 py-2 font-mono text-xs"><?= e($d['phone_norm']) ?></td>
              <td class="px-3 py-2 text-center font-bold"><?= (int)$d['n'] ?></td>
              <td class="px-3 py-2 text-xs">
                <?php foreach ($custs as $u): ?>
                  <div class="flex justify-between gap-3"><span><strong>#<?= (int)$u['id'] ?></strong> <?= e($u['first_name'].' '.$u['last_name']) ?></span><span class="text-slate-400"><?= (int)$u['total_trips'] ?> voy · <?= number_format((int)$u['total_spent']) ?></span></div>
                <?php endforeach ?>
              </td>
              <td class="px-3 py-2 text-right">
                <?php if ($keep && can('crm.customers.merge')): ?>
                  <?php foreach ($custs as $u): if ($u['id'] === $keep['id']) continue; ?>
                    <form method="post" action="<?= e(url('crm/customers/merge')) ?>" class="inline" onsubmit="return confirm('Fusionner #<?= $u['id'] ?> dans #<?= $keep['id'] ?> ?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="keep_id" value="<?= (int)$keep['id'] ?>">
                      <input type="hidden" name="from_id" value="<?= (int)$u['id'] ?>">
                      <button class="text-xs text-rose-600 hover:underline">→ #<?= (int)$keep['id'] ?></button>
                    </form>
                  <?php endforeach ?>
                <?php endif ?>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>
<?php $view->end() ?>
