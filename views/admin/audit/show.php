<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
use CityBus\Core\AuditPresenter;

$action    = $row['action']    ?? '';
$entity    = $row['entity']    ?? '';
$entityId  = $row['entity_id'] ?? null;
$ip        = $row['ip_address']  ?? '';
$mac       = $row['mac_address'] ?? '';
$ua        = $row['user_agent']  ?? '';
$device    = AuditPresenter::deviceLabel($ua);
$userName  = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
$userEmail = $row['email'] ?? '';
?>
<?php $view->start('content') ?>

<!-- Breadcrumb -->
<nav class="text-sm text-slate-500 mb-5 flex items-center gap-2">
  <a href="<?= url('admin/audit') ?>" class="hover:text-slate-700">Journaux d'audit</a>
  <span class="text-slate-300">/</span>
  <span class="text-slate-700 font-medium">#<?= (int)$row['id'] ?></span>
</nav>

<div class="mb-6 flex items-center justify-between">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Détail de l'action</h1>
    <p class="text-sm text-slate-500 mt-1"><?= e(date('d/m/Y \\à H\\hi\\ms\\s', strtotime($row['created_at']))) ?></p>
  </div>
  <a href="<?= url('admin/audit') ?>"
     class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
    <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
  </a>
</div>

<!-- Info grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

  <!-- Action -->
  <div class="bg-white rounded-2xl shadow-soft p-5">
    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Action effectuée</p>
    <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold <?= AuditPresenter::badgeClass($action) ?>">
      <?= e(AuditPresenter::actionLabel($action)) ?>
    </span>
    <p class="text-xs text-slate-400 mt-2">Code interne : <code><?= e($action) ?></code></p>
  </div>

  <!-- Objet concerné -->
  <div class="bg-white rounded-2xl shadow-soft p-5">
    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Objet concerné</p>
    <span class="text-slate-800 font-medium">
      <?php if ($entity): ?>
        <?= e(AuditPresenter::entityLabel($entity)) ?>
        <?php if ($entityId): ?>
          <span class="text-slate-400 ml-1 text-sm">n°<?= (int)$entityId ?></span>
        <?php endif; ?>
      <?php else: ?>
        <span class="text-slate-400">—</span>
      <?php endif; ?>
    </span>
  </div>

  <!-- Utilisateur -->
  <div class="bg-white rounded-2xl shadow-soft p-5">
    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Utilisateur</p>
    <?php if ($userName !== '' || $userEmail !== ''): ?>
      <p class="font-medium text-slate-800"><?= e($userName ?: '—') ?></p>
      <p class="text-sm text-slate-500"><?= e($userEmail) ?></p>
    <?php else: ?>
      <span class="text-slate-400 text-sm italic">Non connecté / anonyme</span>
    <?php endif; ?>
  </div>

  <!-- Poste / Réseau -->
  <div class="bg-white rounded-2xl shadow-soft p-5 md:col-span-2">
    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Poste &amp; Réseau</p>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Appareil</p>
        <p class="font-medium text-slate-800"><?= e($device) ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Adresse IP</p>
        <code class="text-slate-700"><?= $ip !== '' ? e($ip) : '—' ?></code>
      </div>
      <div>
        <p class="text-xs text-slate-400 mb-0.5">Adresse MAC</p>
        <code class="text-slate-700"><?= $mac !== '' ? e($mac) : '<span class="italic text-slate-400 font-sans">Non disponible (connexion distante)</span>' ?></code>
      </div>
      <?php if ($ua !== ''): ?>
      <div class="col-span-full">
        <p class="text-xs text-slate-400 mb-0.5">Navigateur (détail technique)</p>
        <p class="text-xs text-slate-500 font-mono break-all"><?= e($ua) ?></p>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Détails JSON -->
<div class="bg-white rounded-2xl shadow-soft p-5">
  <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Informations supplémentaires</p>

  <?php if (empty($details)): ?>
    <p class="text-sm text-slate-400 italic">Aucune donnée supplémentaire enregistrée.</p>
  <?php else: ?>
    <table class="w-full text-sm mb-4">
      <tbody>
        <?php foreach ($details as $k => $v): ?>
        <tr class="border-b border-slate-100 last:border-0">
          <td class="py-2.5 pr-4 font-medium text-slate-500 w-1/3"><?= e(AuditPresenter::detailKey($k)) ?></td>
          <td class="py-2.5 text-slate-800 font-medium">
            <?= e(AuditPresenter::detailValue($k, $v)) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- JSON brut rétractable -->
    <details class="mt-2">
      <summary class="text-xs text-slate-400 cursor-pointer hover:text-slate-600">Voir JSON brut</summary>
      <pre class="mt-2 p-3 bg-slate-50 rounded-lg text-xs overflow-x-auto text-slate-700"><?= e(json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </details>
  <?php endif; ?>
</div>

<?php $view->end() ?>
