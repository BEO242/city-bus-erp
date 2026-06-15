<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$f = $filters;
use CityBus\Core\AuditPresenter;
?>
<?php $view->start('content') ?>

<div class="mb-6 flex items-center justify-between">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Journal d'activité</h1>
    <p class="text-sm text-slate-500 mt-1">
      <?= number_format($total, 0, ',', ' ') ?> enregistrement(s) — toutes les actions des utilisateurs
    </p>
  </div>
  <!-- Export CSV -->
  <a href="<?= url('admin/audit/export?' . http_build_query(array_filter([
    'user_id' => $f['userF'] ?: null,
    'action'  => $f['actionF'] ?: null,
    'entity'  => $f['entityF'] ?: null,
    'from'    => $f['dateFrom'] ?: null,
    'to'      => $f['dateTo'] ?: null,
  ]))) ?>"
     class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm">
    <i data-lucide="download" class="w-4 h-4"></i> Exporter (Excel/CSV)
  </a>
</div>

<form method="get" class="bg-white rounded-2xl shadow-soft p-4 mb-5 grid grid-cols-2 md:grid-cols-6 gap-3">
  <!-- Utilisateur (select) -->
  <div class="md:col-span-2">
    <label class="text-xs font-semibold text-slate-500">Utilisateur</label>
    <select name="user_id" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm">
      <option value="">Tous</option>
      <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= $f['userF'] === (int)$u['id'] ? 'selected' : '' ?>>
          <?= e($u['first_name'] . ' ' . $u['last_name']) ?> — <?= e($u['email']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="text-xs font-semibold text-slate-500">Type d'action</label>
    <select name="action" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm">
      <option value="">Toutes les actions</option>
      <?php foreach ($actionGroups as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= $f['actionF'] === $code ? 'selected' : '' ?>>
          <?= e($label) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="text-xs font-semibold text-slate-500">Objet concerné</label>
    <select name="entity" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm">
      <option value="">Tous les objets</option>
      <?php foreach ($entityGroups as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= $f['entityF'] === $code ? 'selected' : '' ?>>
          <?= e($label) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="text-xs font-semibold text-slate-500">Du</label>
    <input type="date" name="from" value="<?= e($f['dateFrom']) ?>" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm">
  </div>
  <div>
    <label class="text-xs font-semibold text-slate-500">Au</label>
    <input type="date" name="to" value="<?= e($f['dateTo']) ?>" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm">
  </div>
  <div class="col-span-full flex gap-2 justify-end">
    <a href="<?= url('admin/audit') ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
      Réinitialiser
    </a>
    <button class="px-4 py-2 rounded-lg bg-cb-primary text-white font-semibold text-sm">Filtrer</button>
  </div>
</form>

<div class="bg-white rounded-2xl shadow-soft overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
      <tr>
        <th class="text-left px-4 py-3">Date &amp; Heure</th>
        <th class="text-left px-4 py-3">Utilisateur</th>
        <th class="text-left px-4 py-3">Action effectuée</th>
        <th class="text-left px-4 py-3">Objet concerné</th>
        <th class="text-left px-4 py-3">Poste / Connexion</th>
        <th class="w-10"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <?php foreach ($rows as $r): ?>
        <?php
          $label  = AuditPresenter::actionLabel($r['action']);
          $entity = $r['entity'] ?? '';
          $device = AuditPresenter::deviceLabel((string)($r['user_agent'] ?? ''));
          $ip     = $r['ip_address'] ?? '';
          $mac    = $r['mac_address'] ?? '';
        ?>
        <tr class="hover:bg-slate-50 cursor-pointer"
            onclick="window.location='<?= e(url('admin/audit/' . $r['id'])) ?>'">
          <!-- Date -->
          <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
            <span class="font-medium text-slate-700"><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></span>
            <span class="block text-slate-400"><?= e(date('H\hi\ms\s', strtotime($r['created_at']))) ?></span>
          </td>
          <!-- Utilisateur -->
          <td class="px-4 py-3">
            <?php if ($r['first_name']): ?>
              <span class="font-medium text-slate-800"><?= e($r['first_name'].' '.$r['last_name']) ?></span>
              <span class="block text-xs text-slate-400"><?= e($r['email'] ?? '') ?></span>
            <?php else: ?>
              <span class="text-slate-400 italic text-xs">Non connecté</span>
            <?php endif; ?>
          </td>
          <!-- Action lisible -->
          <td class="px-4 py-3">
            <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= AuditPresenter::badgeClass($r['action']) ?>">
              <?= e($label) ?>
            </span>
          </td>
          <!-- Objet concerné -->
          <td class="px-4 py-3 text-sm text-slate-700">
            <?php if ($entity): ?>
              <?= e(AuditPresenter::entityLabel($entity)) ?>
              <?php if ($r['entity_id']): ?>
                <span class="text-slate-400 text-xs ml-1">n°<?= (int)$r['entity_id'] ?></span>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-slate-400">—</span>
            <?php endif; ?>
          </td>
          <!-- Poste / Connexion -->
          <td class="px-4 py-3">
            <span class="text-xs text-slate-700"><?= e($device) ?></span>
            <?php if ($ip): ?>
              <span class="block text-xs text-slate-400 font-mono">
                IP : <?= e($ip) ?>
                <?php if ($mac): ?>&nbsp;·&nbsp; MAC : <?= e($mac) ?><?php endif; ?>
              </span>
            <?php endif; ?>
          </td>
          <!-- Lien détail -->
          <td class="px-4 py-3 text-right">
            <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 inline-block"></i>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="6" class="px-4 py-12 text-center text-slate-400">
            <i data-lucide="search-x" class="w-8 h-8 mx-auto mb-2 opacity-30"></i><br>
            Aucune activité pour ces critères.
          </td>
        </tr>
      <?php endif ?>
    </tbody>
  </table>
</div>

<?php if ($lastPage > 1):
  $qs = $_GET; ?>
  <div class="flex items-center justify-center gap-2 mt-5 flex-wrap">
    <?php if ($page > 1): $qs['page'] = $page - 1; ?>
      <a href="<?= e(url('admin/audit?' . http_build_query($qs))) ?>"
         class="px-3 py-1.5 rounded-lg text-sm bg-white border border-slate-200 hover:bg-slate-50">‹</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page - 3); $p <= min($lastPage, $page + 3); $p++):
      $qs['page'] = $p; ?>
      <a href="<?= e(url('admin/audit?' . http_build_query($qs))) ?>"
         class="px-3 py-1.5 rounded-lg text-sm <?= $p === $page ? 'bg-cb-primary text-white font-semibold' : 'bg-white border border-slate-200 hover:bg-slate-50' ?>">
        <?= $p ?>
      </a>
    <?php endfor; ?>
    <?php if ($page < $lastPage): $qs['page'] = $page + 1; ?>
      <a href="<?= e(url('admin/audit?' . http_build_query($qs))) ?>"
         class="px-3 py-1.5 rounded-lg text-sm bg-white border border-slate-200 hover:bg-slate-50">›</a>
    <?php endif; ?>
  </div>
<?php endif ?>

<?php $view->end() ?>

