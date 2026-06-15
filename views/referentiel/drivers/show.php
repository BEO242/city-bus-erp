<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Driver;
use CityBus\Services\MediaService;
$view->extends('layouts/app');
$gallery = MediaService::enrichAll($gallery ?? []);
$docs    = MediaService::enrichAll($docs    ?? []);
$photo   = $gallery[0] ?? null;
$thumbUrl = $photo ? ($photo['thumb_url'] ?? $photo['url'] ?? null) : null;
$initials = strtoupper(substr($driver['first_name'],0,1).substr($driver['last_name'],0,1));
$sc = Driver::statusClass($driver['status']);
$catsArr = !empty($driver['license_categories']) ? array_map('trim', explode(',', $driver['license_categories'])) : [];
$age = Driver::age($driver['birth_date'] ?? null);
?>
<?php $view->start('content') ?>
<div class="space-y-6">

  <!-- En-tete -->
  <div class="flex items-center gap-4 flex-wrap">
    <a href="<?= e(url('referentiel/drivers')) ?>" class="text-slate-500 hover:text-cb-primary p-2 rounded-lg hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(Driver::fullName($driver)) ?></h1>
        <span class="font-mono text-xs px-2.5 py-1 bg-cb-bg text-cb-primary rounded-full"><?= e($driver['matricule']) ?></span>
        <span class="text-xs px-2.5 py-1 rounded-full border font-semibold <?= $sc ?>"><?= e(Driver::STATUSES[$driver['status']] ?? $driver['status']) ?></span>
      </div>
      <p class="text-sm text-slate-500 mt-0.5">
        <?php if (!empty($driver['phone'])): ?><i data-lucide="phone" class="w-3 h-3 inline"></i> <?= e($driver['phone']) ?><?php endif ?>
        <?php if (!empty($driver['agency_name'])): ?> &middot; <?= e($driver['agency_name']) ?><?php endif ?>
        <?php if ($age): ?> &middot; <?= $age ?> ans<?php endif ?>
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= e(url('referentiel/drivers/'.$driver['id'].'/edit')) ?>"
         class="px-4 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-2">
        <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
      </a>
      <form method="post" action="<?= e(url('referentiel/drivers/'.$driver['id'].'/delete')) ?>"
            onsubmit="return confirm('Supprimer ce chauffeur ? Cette action est irreversible.')">
        <?= csrf_field() ?>
        <button type="submit" class="px-4 py-2.5 rounded-xl border border-rose-200 text-rose-600 text-sm font-semibold hover:bg-rose-50 transition flex items-center gap-2">
          <i data-lucide="trash-2" class="w-4 h-4"></i>
        </button>
      </form>
    </div>
  </div>

  <!-- Alertes -->
  <?php if (!empty($alerts)): ?>
  <div class="space-y-2">
    <?php foreach ($alerts as $a):
      $cls = $a['level']==='danger' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800';
    ?>
      <div class="flex items-center gap-3 px-4 py-3 rounded-xl border <?= $cls ?> text-sm">
        <i data-lucide="<?= e($a['icon'] ?? 'alert-triangle') ?>" class="w-4 h-4 shrink-0"></i>
        <div class="flex-1">
          <span class="font-semibold"><?= e($a['label']) ?></span>
          <span class="opacity-75 ml-2"><?= e($a['detail']) ?></span>
        </div>
        <?php if (!empty($a['date'])): ?><span class="text-xs opacity-70"><?= e($a['date']) ?></span><?php endif ?>
      </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <!-- Photo + stats -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
    <div class="grid sm:grid-cols-3 gap-0">
      <div class="sm:col-span-1 bg-gradient-to-br from-cb-bg to-slate-100 flex items-center justify-center p-6 min-h-[220px]">
        <?php if ($thumbUrl): ?>
          <img src="<?= e($thumbUrl) ?>" alt="" class="w-40 h-40 rounded-full object-cover ring-4 ring-white shadow-lg">
        <?php else: ?>
          <div class="w-40 h-40 rounded-full bg-cb-primary/10 flex items-center justify-center ring-4 ring-white shadow-lg">
            <span class="text-5xl font-bold text-cb-primary"><?= e($initials) ?></span>
          </div>
        <?php endif ?>
      </div>
      <div class="sm:col-span-2 grid grid-cols-2 sm:grid-cols-4 divide-x divide-slate-100">
        <div class="p-4 text-center">
          <div class="text-xs text-slate-500 uppercase tracking-wide">Voyages</div>
          <div class="text-2xl font-bold text-slate-800"><?= (int)($stats['trips_total'] ?? 0) ?></div>
          <div class="text-xs text-emerald-600 mt-0.5"><?= (int)($stats['trips_done'] ?? 0) ?> terminés</div>
        </div>
        <div class="p-4 text-center">
          <div class="text-xs text-slate-500 uppercase tracking-wide">30 derniers j.</div>
          <div class="text-2xl font-bold text-cb-primary"><?= (int)($stats['trips_30d'] ?? 0) ?></div>
          <div class="text-xs text-slate-400 mt-0.5">voyages</div>
        </div>
        <div class="p-4 text-center">
          <div class="text-xs text-slate-500 uppercase tracking-wide">Bus conduits</div>
          <div class="text-2xl font-bold text-slate-800"><?= (int)($stats['buses_driven'] ?? 0) ?></div>
        </div>
        <div class="p-4 text-center">
          <div class="text-xs text-slate-500 uppercase tracking-wide">Notation</div>
          <div class="text-2xl font-bold text-amber-500 flex items-center justify-center gap-1">
            <i data-lucide="star" class="w-5 h-5 fill-amber-500"></i>
            <?= number_format((float)$driver['rating_score'],1,',','') ?>
          </div>
          <div class="text-xs text-slate-400 mt-0.5">/ 10</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Cartes infos -->
  <div class="grid sm:grid-cols-2 gap-4">

    <!-- Identité -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h3 class="font-semibold text-slate-700 flex items-center gap-2 mb-2">
        <i data-lucide="user" class="w-4 h-4 text-cb-primary"></i> Identit&eacute;
      </h3>
      <dl class="grid grid-cols-2 gap-y-2 text-sm">
        <dt class="text-slate-500">Genre</dt><dd><?= e(Driver::GENDERS[$driver['gender']] ?? '—') ?></dd>
        <dt class="text-slate-500">N&eacute; le</dt><dd><?= e($driver['birth_date'] ?: '—') ?></dd>
        <dt class="text-slate-500">Lieu</dt><dd><?= e($driver['birth_place'] ?: '—') ?></dd>
        <dt class="text-slate-500">Nationalit&eacute;</dt><dd><?= e($driver['nationality'] ?: '—') ?></dd>
        <dt class="text-slate-500">Sit. matrim.</dt><dd><?= e(Driver::MARITAL_STATUSES[$driver['marital_status']] ?? '—') ?></dd>
        <dt class="text-slate-500">Enfants</dt><dd><?= (int)$driver['children_count'] ?></dd>
        <dt class="text-slate-500">Groupe sang.</dt><dd><?= e($driver['blood_type'] ?: '—') ?></dd>
        <dt class="text-slate-500">CNI</dt><dd class="font-mono text-xs"><?= e($driver['national_id'] ?: '—') ?></dd>
        <?php if (!empty($driver['national_id_expiry'])): ?>
          <dt class="text-slate-500">Exp. CNI</dt><dd><?= e($driver['national_id_expiry']) ?></dd>
        <?php endif ?>
      </dl>
    </div>

    <!-- Contact + urgence -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h3 class="font-semibold text-slate-700 flex items-center gap-2 mb-2">
        <i data-lucide="phone" class="w-4 h-4 text-cb-primary"></i> Contact
      </h3>
      <dl class="grid grid-cols-2 gap-y-2 text-sm">
        <dt class="text-slate-500">T&eacute;l.</dt><dd class="font-semibold"><?= e($driver['phone']) ?></dd>
        <?php if (!empty($driver['phone_alt'])): ?>
        <dt class="text-slate-500">T&eacute;l. 2</dt><dd><?= e($driver['phone_alt']) ?></dd>
        <?php endif ?>
        <?php if (!empty($driver['email'])): ?>
        <dt class="text-slate-500">Email</dt><dd class="truncate"><?= e($driver['email']) ?></dd>
        <?php endif ?>
        <dt class="text-slate-500">Adresse</dt><dd><?= e($driver['address'] ?: '—') ?></dd>
        <dt class="text-slate-500">Ville</dt><dd><?= e($driver['city'] ?: '—') ?></dd>
      </dl>
      <?php if (!empty($driver['emergency_name']) || !empty($driver['emergency_phone'])): ?>
      <div class="mt-3 pt-3 border-t border-slate-100">
        <p class="text-xs uppercase tracking-wide text-rose-600 font-semibold mb-2 flex items-center gap-1">
          <i data-lucide="alert-octagon" class="w-3.5 h-3.5"></i> En cas d'urgence
        </p>
        <p class="text-sm font-semibold"><?= e($driver['emergency_name'] ?: '—') ?>
          <?php if (!empty($driver['emergency_relation'])): ?>
            <span class="text-slate-400 font-normal">(<?= e($driver['emergency_relation']) ?>)</span>
          <?php endif ?>
        </p>
        <p class="text-sm text-slate-600"><?= e($driver['emergency_phone'] ?: '—') ?></p>
      </div>
      <?php endif ?>
    </div>

    <!-- Permis -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h3 class="font-semibold text-slate-700 flex items-center gap-2 mb-2">
        <i data-lucide="id-card" class="w-4 h-4 text-cb-primary"></i> Permis &amp; aptitudes
      </h3>
      <dl class="grid grid-cols-2 gap-y-2 text-sm">
        <dt class="text-slate-500">N&deg; permis</dt><dd class="font-mono"><?= e($driver['license_number']) ?></dd>
        <dt class="text-slate-500">Cat&eacute;gories</dt>
        <dd>
          <?php foreach ($catsArr as $c): ?>
            <span class="inline-block px-1.5 py-0.5 mr-1 bg-cb-bg text-cb-primary text-xs font-bold rounded">Cat. <?= e($c) ?></span>
          <?php endforeach ?>
        </dd>
        <dt class="text-slate-500">D&eacute;livr&eacute; le</dt><dd><?= e($driver['license_issue_date'] ?: '—') ?></dd>
        <dt class="text-slate-500">Expiration</dt>
        <dd>
          <?= e($driver['license_expiry']) ?>
          <?php
            $now = new DateTime('today');
            $exp = new DateTime($driver['license_expiry']);
            $daysLeft = (int)$now->diff($exp)->format('%r%a');
            if ($daysLeft < 0): ?>
              <span class="ml-1 text-rose-600 text-xs font-bold">Expir&eacute;</span>
            <?php elseif ($daysLeft <= 30): ?>
              <span class="ml-1 text-amber-600 text-xs font-bold">J-<?= $daysLeft ?></span>
          <?php endif ?>
        </dd>
        <dt class="text-slate-500">Autorit&eacute;</dt><dd class="text-xs"><?= e($driver['license_authority'] ?: '—') ?></dd>
        <dt class="text-slate-500">Visite m&eacute;d.</dt><dd><?= e($driver['medical_cert_expiry'] ?: '—') ?></dd>
        <dt class="text-slate-500">Psychotech.</dt><dd><?= e($driver['psycho_test_expiry'] ?: '—') ?></dd>
        <dt class="text-slate-500">Ophtalmologie</dt><dd><?= e($driver['ophthalmo_expiry'] ?? '—') ?: '—' ?></dd>
        <dt class="text-slate-500">Test antidopage</dt><dd><?= e($driver['drug_test_last'] ?? '—') ?: '—' ?></dd>
      </dl>
    </div>

    <!-- Affectation -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3">
      <h3 class="font-semibold text-slate-700 flex items-center gap-2 mb-2">
        <i data-lucide="briefcase" class="w-4 h-4 text-cb-primary"></i> Carri&egrave;re &amp; affectation
      </h3>
      <dl class="grid grid-cols-2 gap-y-2 text-sm">
        <dt class="text-slate-500">Embauch&eacute; le</dt><dd><?= e($driver['hire_date']) ?></dd>
        <dt class="text-slate-500">Exp&eacute;rience</dt><dd><?= (int)$driver['experience_years'] ?> ans</dd>
        <?php if (!empty($driver['previous_employer'])): ?>
          <dt class="text-slate-500">Ancien employeur</dt><dd><?= e($driver['previous_employer']) ?></dd>
        <?php endif ?>
        <dt class="text-slate-500">Agence</dt><dd><?= e($driver['agency_name'] ?: '—') ?></dd>
      </dl>
      <?php if (!empty($driver['primary_bus'])): $b = $driver['primary_bus']; ?>
      <div class="mt-3 pt-3 border-t border-slate-100">
        <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold mb-2">V&eacute;hicule assign&eacute;</p>
        <a href="<?= e(url('referentiel/vehicules/'.$b['id'])) ?>" class="flex items-center gap-3 p-3 -mx-1 rounded-xl bg-cb-bg/50 hover:bg-cb-bg transition">
          <div class="w-10 h-10 rounded-lg bg-cb-primary text-white flex items-center justify-center">
            <i data-lucide="bus" class="w-5 h-5"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-sm"><?= e($b['plate']) ?></div>
            <div class="text-xs text-slate-500"><?= e($b['code']) ?> &middot; <?= e($b['brand']) ?> <?= e($b['model']) ?></div>
          </div>
          <i data-lucide="external-link" class="w-4 h-4 text-slate-400"></i>
        </a>
      </div>
      <?php endif ?>
    </div>

    <!-- Rémunération -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3 sm:col-span-2">
      <h3 class="font-semibold text-slate-700 flex items-center gap-2 mb-2">
        <i data-lucide="banknote" class="w-4 h-4 text-cb-primary"></i> R&eacute;mun&eacute;ration
      </h3>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
          <div class="text-xs text-slate-500">Salaire base</div>
          <div class="font-bold text-slate-800 mt-0.5"><?= number_format((int)$driver['salary_base'],0,',',' ') ?> <span class="text-xs text-slate-400">FCFA</span></div>
        </div>
        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
          <div class="text-xs text-slate-500">Prime / jour</div>
          <div class="font-bold text-slate-800 mt-0.5"><?= number_format((int)$driver['daily_bonus'],0,',',' ') ?> <span class="text-xs text-slate-400">FCFA</span></div>
        </div>
        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
          <div class="text-xs text-slate-500">Prime / km</div>
          <div class="font-bold text-slate-800 mt-0.5"><?= number_format((float)$driver['km_bonus_rate'],2,',',' ') ?> <span class="text-xs text-slate-400">FCFA</span></div>
        </div>
        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
          <div class="text-xs text-slate-500">Mobile Money</div>
          <div class="font-mono text-sm text-slate-800 mt-0.5"><?= e($driver['mobile_money_number'] ?: '—') ?></div>
        </div>
      </div>
      <?php if (!empty($driver['bank_name']) || !empty($driver['bank_account'])): ?>
      <div class="text-sm text-slate-600 pt-2 border-t border-slate-100">
        <i data-lucide="landmark" class="w-3.5 h-3.5 inline text-slate-400"></i>
        <?= e($driver['bank_name'] ?: '—') ?>
        <?php if (!empty($driver['bank_account'])): ?>
          &middot; <span class="font-mono text-xs"><?= e($driver['bank_account']) ?></span>
        <?php endif ?>
      </div>
      <?php endif ?>
    </div>
  </div>

  <!-- Notes & historique -->
  <div id="notes" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-5">
    <div class="flex items-center justify-between">
      <h3 class="font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="message-square-text" class="w-4 h-4 text-cb-primary"></i>
        Notes &amp; observations
        <span class="text-xs font-normal text-slate-400 ml-1">(<?= count($notes ?? []) ?>)</span>
      </h3>
    </div>

    <?php if (!empty($driver['notes'])): ?>
      <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
        <div class="text-xs text-slate-400 mb-1">Note de création</div>
        <p class="text-sm text-slate-600 whitespace-pre-line"><?= e($driver['notes']) ?></p>
      </div>
    <?php endif ?>

    <!-- Formulaire d'ajout -->
    <form method="post" action="<?= e(url('referentiel/drivers/'.$driver['id'].'/notes')) ?>" class="space-y-3">
      <?= csrf_field() ?>
      <textarea name="content" rows="3" maxlength="2000"
                placeholder="Ajouter une observation, un suivi, une évaluation..."
                class="cb-input w-full resize-none"></textarea>
      <div class="flex justify-end">
        <button type="submit"
                class="px-4 py-2 bg-cb-primary text-white rounded-xl text-sm font-medium hover:bg-cb-dark transition flex items-center gap-2">
          <i data-lucide="send" class="w-4 h-4"></i> Enregistrer la note
        </button>
      </div>
    </form>

    <!-- Historique -->
    <?php if (empty($notes)): ?>
      <p class="text-sm text-slate-400 text-center py-4 border-t border-slate-100">Aucune note pour le moment.</p>
    <?php else: ?>
      <div class="space-y-3 border-t border-slate-100 pt-4">
        <?php foreach ($notes as $note):
          $currentUser = auth();
          $canDelete   = (int)($currentUser['id'] ?? 0) === (int)$note['author_id']
                      || in_array($currentUser['role'] ?? '', ['admin', 'superadmin'], true);
        ?>
          <div class="flex gap-3 p-4 bg-slate-50 rounded-xl border border-slate-100">
            <div class="w-9 h-9 rounded-full bg-cb-primary text-white flex items-center justify-center font-bold text-sm shrink-0">
              <?= e(strtoupper(substr($note['author_name'] ?? '?', 0, 1))) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <span class="font-semibold text-sm text-slate-800"><?= e($note['author_name'] ?? 'Inconnu') ?></span>
                  <span class="text-xs text-slate-400 ml-2 capitalize"><?= e($note['author_role'] ?? '') ?></span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <time class="text-xs text-slate-400"><?= e(date('d/m/Y H:i', strtotime($note['created_at']))) ?></time>
                  <?php if ($canDelete): ?>
                    <form method="post"
                          action="<?= e(url('referentiel/drivers/'.$driver['id'].'/notes/'.$note['id'].'/delete')) ?>"
                          onsubmit="return confirm('Supprimer cette note ?')">
                      <?= csrf_field() ?>
                      <button type="submit" class="text-slate-300 hover:text-rose-500 transition p-1 rounded">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                      </button>
                    </form>
                  <?php endif ?>
                </div>
              </div>
              <p class="text-sm text-slate-700 mt-1.5 whitespace-pre-line"><?= e($note['content']) ?></p>
            </div>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>

  <!-- Derniers voyages -->
  <?php if (!empty($trips)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
    <h3 class="font-semibold text-slate-700 flex items-center gap-2 mb-3">
      <i data-lucide="route" class="w-4 h-4 text-cb-primary"></i>
      Derniers voyages
      <span class="text-xs bg-cb-bg text-cb-primary px-2 py-0.5 rounded-full ml-1"><?= count($trips) ?></span>
    </h3>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-xs text-slate-500 border-b border-slate-100">
          <tr><th class="text-left py-2">Date</th><th class="text-left">Code</th><th class="text-left">Ligne</th><th class="text-left">Bus</th><th class="text-left">Statut</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($trips as $t):
            $tStatusCls = match($t['status'] ?? '') {
              'done'=>'bg-emerald-100 text-emerald-700','active'=>'bg-blue-100 text-blue-700',
              'cancelled'=>'bg-rose-100 text-rose-700', default=>'bg-slate-100 text-slate-600' };
          ?>
          <tr class="hover:bg-slate-50">
            <td class="py-2 text-slate-600"><?= e($t['trip_date'] ?? '') ?></td>
            <td class="font-mono text-xs"><?= e($t['code'] ?? '') ?></td>
            <td><?= e($t['line_name'] ?? '—') ?></td>
            <td class="font-mono text-xs"><?= e($t['bus_code'] ?? '—') ?></td>
            <td><span class="text-xs px-2 py-0.5 rounded-full <?= $tStatusCls ?>"><?= e($t['status'] ?? '—') ?></span></td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif ?>

  <!-- Incidents -->
  <?php if (!empty($incidents)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
    <h3 class="font-semibold text-slate-700 flex items-center gap-2 mb-3">
      <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i>
      Incidents enregistr&eacute;s
      <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full ml-1"><?= count($incidents) ?></span>
    </h3>
    <div class="space-y-2">
      <?php foreach ($incidents as $inc):
        $sev = $inc['severity'] ?? 'mineur';
        $sevCls = match($sev) {
          'critique'=>'bg-rose-100 text-rose-700 border-rose-200',
          'grave'=>'bg-orange-100 text-orange-700 border-orange-200',
          'modere'=>'bg-amber-100 text-amber-700 border-amber-200',
          default=>'bg-slate-100 text-slate-600 border-slate-200' };
      ?>
        <div class="border <?= $sevCls ?> rounded-xl px-4 py-3 text-sm">
          <div class="flex items-start gap-3 flex-wrap">
            <span class="px-2 py-0.5 rounded-full text-xs font-bold uppercase bg-white/60"><?= e($sev) ?></span>
            <span class="text-xs text-slate-500"><?= e($inc['occurred_at']) ?></span>
            <?php if (!empty($inc['type'])): ?><span class="text-xs px-2 py-0.5 rounded bg-white/60"><?= e($inc['type']) ?></span><?php endif ?>
            <?php if (!empty($inc['bus_code'])): ?>
              <span class="text-xs"><i data-lucide="bus" class="w-3 h-3 inline"></i> <?= e($inc['bus_code']) ?></span>
            <?php endif ?>
            <?php if (!empty($inc['cost_fcfa'])): ?>
              <span class="ml-auto text-xs font-semibold"><?= number_format((int)$inc['cost_fcfa'],0,',',' ') ?> FCFA</span>
            <?php endif ?>
          </div>
          <p class="mt-2 text-slate-700"><?= e($inc['description'] ?? '—') ?></p>
          <?php if (!empty($inc['resolved'])): ?>
            <p class="mt-1 text-xs text-emerald-700"><i data-lucide="check" class="w-3 h-3 inline"></i> R&eacute;solu — <?= e($inc['resolution_notes'] ?? '') ?></p>
          <?php endif ?>
        </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>

  <!-- ── FINANCES (widget dépenses bidirectionnel) ────────────────────── -->
  <?php
  // Préparer les items extra pour le chauffeur
  $driverExtraItems = [];
  // payroll_records → salaire / salaire_avance
  foreach (($payrollRecords ?? []) as $pr) {
      $catCode = $pr['payroll_type'] === 'avance' ? 'salaire_avance' : 'salaire';
      $driverExtraItems[] = ['_source'=>'payroll','cat_code'=>$catCode,
          'cat_label'=>$catCode === 'salaire_avance' ? 'Avance sur salaire' : 'Salaires','cat_color'=>'pink',
          'amount_fcfa'=>(int)$pr['cost_fcfa'],'payroll_type'=>$pr['payroll_type'],
          'period_month'=>$pr['period_month'],'period_year'=>$pr['period_year'],
          'base_amount'=>$pr['base_amount'],'deductions'=>$pr['deductions'],'net_amount'=>$pr['net_amount'],
          'created_at'=>$pr['created_at'],'logged_by_name'=>$pr['logged_by_name']??'','type'=>'decaissement'];
  }
  // driver_compensations → prime_journaliere / prime_autre / indemnite / commission_agent
  foreach (($compensations ?? []) as $dc) {
      $cLabels = ['prime_journaliere'=>'Prime journalière','prime_autre'=>'Autres primes','indemnite'=>'Indemnités de déplacement','commission_agent'=>'Commissions agents'];
      $cColors = ['prime_journaliere'=>'blue','prime_autre'=>'violet','indemnite'=>'blue','commission_agent'=>'violet'];
      $driverExtraItems[] = ['_source'=>'compensation','cat_code'=>$dc['comp_type'],
          'cat_label'=>$cLabels[$dc['comp_type']]??$dc['comp_type'],'cat_color'=>$cColors[$dc['comp_type']]??'slate',
          'amount_fcfa'=>(int)$dc['cost_fcfa'],'comp_type'=>$dc['comp_type'],'reason'=>$dc['reason']??null,
          'rate_type'=>$dc['rate_type']??'fixe','rate_value'=>$dc['rate_value']??null,
          'created_at'=>$dc['created_at'],'logged_by_name'=>$dc['logged_by_name']??'','type'=>'decaissement'];
  }
  // fine_records → amende
  foreach (($fineRecords ?? []) as $fr) {
      $driverExtraItems[] = ['_source'=>'fine','cat_code'=>'amende','cat_label'=>'Amendes / contraventions','cat_color'=>'red',
          'amount_fcfa'=>(int)$fr['cost_fcfa'],'infraction_type'=>$fr['infraction_type']??null,'location'=>$fr['location']??null,
          'authority'=>$fr['authority']??null,'is_contested'=>$fr['is_contested']??0,
          'created_at'=>$fr['fine_date']??$fr['created_at'],'logged_by_name'=>$fr['logged_by_name']??'','type'=>'decaissement'];
  }

  $view->include('partials/expense_widget', [
      'expEntityType'  => 'driver',
      'expEntityId'    => (int)$driver['id'],
      'expenses'       => $expenses ?? [],
      'expCategories'  => $expCategories ?? [],
      'expTotals'      => $expTotals ?? [],
      'expContext'     => [],
      'expExtraItems'  => $driverExtraItems,
      'expDriverSalary'=> (int)($driver['salary_base'] ?? 0),
      'expDriverBonus' => (int)($driver['daily_bonus'] ?? 0),
  ]);
  ?>

  <?php
  // ── Données media passées à Alpine ──────────────────────────────────────
  $canEditMedia = can('referentiel.edit');
  $mediaCfg = [
    'type'      => 'drivers',
    'id'        => (int)$driver['id'],
    'csrf'      => csrf_token(),
    'canEdit'   => $canEditMedia,
    'uploadUrl' => url('media/upload'),
    'baseUrl'   => rtrim(url('media'), '/') . '/',
  ];
  ?>
  <script>
  window.__GALLERY   = <?= json_encode(array_values($gallery), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
  window.__DOCS      = <?= json_encode(array_values($docs),    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
  window.__MEDIA_CFG = <?= json_encode($mediaCfg,              JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
  </script>

  <!-- ── PHOTOS ──────────────────────────────────────────────────────────── -->
  <?php if (!empty($gallery) || $canEditMedia): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5"
       x-data="mediaSection(window.__GALLERY, {...window.__MEDIA_CFG, collection:'gallery'})"
       x-init="$nextTick(() => window.lucide && lucide.createIcons())">

    <!-- En-tête -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="image" class="w-4 h-4 text-cb-primary"></i> Photos
        <span x-text="items.length"
              class="text-xs bg-cb-bg text-cb-primary px-2 py-0.5 rounded-full ml-1 min-w-[1.5rem] text-center tabular-nums"></span>
      </h3>
      <?php if ($canEditMedia): ?>
      <label :class="uploading ? 'opacity-50 pointer-events-none' : ''"
             class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cb-primary text-white text-xs font-semibold hover:bg-cb-dark transition">
        <i data-lucide="upload" class="w-3.5 h-3.5"></i> Ajouter photo
        <input type="file" accept="image/*" multiple class="sr-only" @change="uploadFiles($event)">
      </label>
      <?php endif ?>
    </div>

    <!-- Barre de progression -->
    <div x-show="uploading" x-transition class="mb-3 h-1 bg-slate-100 rounded-full overflow-hidden">
      <div class="h-full bg-cb-primary animate-pulse w-3/4 rounded-full"></div>
    </div>

    <!-- Erreur -->
    <div x-show="uploadError" x-transition
         class="mb-3 flex items-center gap-2 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2">
      <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
      <span x-text="uploadError" class="flex-1"></span>
      <button @click="uploadError = null" class="ml-1 text-rose-400 hover:text-rose-600 transition">
        <i data-lucide="x" class="w-3.5 h-3.5"></i>
      </button>
    </div>

    <!-- État vide -->
    <div x-show="items.length === 0 && !uploading" class="text-center py-10">
      <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
        <i data-lucide="image-off" class="w-6 h-6 text-slate-300"></i>
      </div>
      <p class="text-sm text-slate-400">Aucune photo ajoutée</p>
      <?php if ($canEditMedia): ?>
      <p class="text-xs text-slate-300 mt-1">Cliquez sur « Ajouter photo » pour commencer</p>
      <?php endif ?>
    </div>

    <!-- Grille -->
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-2" x-show="items.length > 0">
      <template x-for="g in items" :key="g.id">
        <div class="relative group aspect-square rounded-xl overflow-hidden border border-slate-100 bg-slate-50 shadow-sm">
          <img :src="g.thumb_url || g.url" :alt="g.caption || g.file_name || ''"
               class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105">
          <!-- Overlay actions -->
          <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent
                      opacity-0 group-hover:opacity-100 transition-opacity duration-150
                      flex flex-col justify-end p-2 gap-1">
            <p class="text-white text-[10px] truncate leading-none mb-1" x-text="g.caption || g.file_name" x-show="g.caption || g.file_name"></p>
            <div class="flex items-center gap-1 justify-end">
              <a :href="g.url" target="_blank"
                 class="w-7 h-7 rounded-lg bg-white/25 hover:bg-white/50 backdrop-blur-sm flex items-center justify-center transition" title="Agrandir">
                <i data-lucide="maximize-2" class="w-3.5 h-3.5 text-white"></i>
              </a>
              <a :href="cfg.baseUrl + g.id + '/file'" :download="g.file_name"
                 class="w-7 h-7 rounded-lg bg-white/25 hover:bg-white/50 backdrop-blur-sm flex items-center justify-center transition" title="Télécharger">
                <i data-lucide="download" class="w-3.5 h-3.5 text-white"></i>
              </a>
              <button x-show="cfg.canEdit" @click.prevent="deleteItem(g.id)"
                      class="w-7 h-7 rounded-lg bg-rose-500/80 hover:bg-rose-600 flex items-center justify-center transition" title="Supprimer">
                <i data-lucide="trash-2" class="w-3.5 h-3.5 text-white"></i>
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
  <?php endif ?>

  <!-- ── DOCUMENTS ────────────────────────────────────────────────────────── -->
  <?php if (!empty($docs) || $canEditMedia): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5"
       x-data="mediaSection(window.__DOCS, {...window.__MEDIA_CFG, collection:'documents'})"
       x-init="$nextTick(() => window.lucide && lucide.createIcons())">

    <!-- En-tête -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="paperclip" class="w-4 h-4 text-cb-primary"></i> Documents
        <span x-text="items.length"
              class="text-xs bg-cb-bg text-cb-primary px-2 py-0.5 rounded-full ml-1 min-w-[1.5rem] text-center tabular-nums"></span>
      </h3>
      <?php if ($canEditMedia): ?>
      <label :class="uploading ? 'opacity-50 pointer-events-none' : ''"
             class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cb-primary text-white text-xs font-semibold hover:bg-cb-dark transition">
        <i data-lucide="upload" class="w-3.5 h-3.5"></i> Ajouter document
        <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.webp" multiple class="sr-only" @change="uploadFiles($event)">
      </label>
      <?php endif ?>
    </div>

    <!-- Barre de progression -->
    <div x-show="uploading" x-transition class="mb-3 h-1 bg-slate-100 rounded-full overflow-hidden">
      <div class="h-full bg-cb-primary animate-pulse w-3/4 rounded-full"></div>
    </div>

    <!-- Erreur -->
    <div x-show="uploadError" x-transition
         class="mb-3 flex items-center gap-2 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2">
      <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
      <span x-text="uploadError" class="flex-1"></span>
      <button @click="uploadError = null" class="ml-1 text-rose-400 hover:text-rose-600 transition">
        <i data-lucide="x" class="w-3.5 h-3.5"></i>
      </button>
    </div>

    <!-- État vide -->
    <div x-show="items.length === 0 && !uploading" class="text-center py-10">
      <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
        <i data-lucide="file-x" class="w-6 h-6 text-slate-300"></i>
      </div>
      <p class="text-sm text-slate-400">Aucun document joint</p>
      <?php if ($canEditMedia): ?>
      <p class="text-xs text-slate-300 mt-1">PDF, Word, Excel, images…</p>
      <?php endif ?>
    </div>

    <!-- Liste -->
    <ul class="divide-y divide-slate-50" x-show="items.length > 0">
      <template x-for="d in items" :key="d.id">
        <li class="py-3 flex items-center gap-3 group rounded-xl hover:bg-slate-50/60 transition -mx-1 px-1">
          <!-- Icône type fichier -->
          <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
               :class="docBg(d)">
            <i :data-lucide="docIcon(d)" class="w-4 h-4" :class="docColor(d)"></i>
          </div>
          <!-- Infos -->
          <div class="flex-1 min-w-0">
            <a :href="d.url" target="_blank"
               class="text-sm font-medium text-slate-800 hover:text-cb-primary transition truncate block"
               x-text="d.caption || d.file_name"></a>
            <span class="text-xs text-slate-400" x-text="d.human_size"></span>
          </div>
          <!-- Actions — visibles au survol -->
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
            <a :href="cfg.baseUrl + d.id + '/file'" :download="d.file_name"
               class="w-8 h-8 rounded-lg border border-slate-200 text-slate-400 hover:text-cb-primary hover:border-cb-primary flex items-center justify-center transition"
               title="Télécharger">
              <i data-lucide="download" class="w-3.5 h-3.5"></i>
            </a>
            <a :href="d.url" target="_blank"
               class="w-8 h-8 rounded-lg border border-slate-200 text-slate-400 hover:text-cb-primary hover:border-cb-primary flex items-center justify-center transition"
               title="Ouvrir">
              <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
            </a>
            <button x-show="cfg.canEdit" @click="deleteItem(d.id)"
                    class="w-8 h-8 rounded-lg border border-rose-200 text-rose-400 hover:bg-rose-50 hover:text-rose-600 flex items-center justify-center transition"
                    title="Supprimer">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
          </div>
        </li>
      </template>
    </ul>
  </div>
  <?php endif ?>

  <script>
  function mediaSection(initialItems, cfg) {
    return {
      items      : (initialItems || []).map(function(i){ return Object.assign({}, i); }),
      cfg        : cfg,
      uploading  : false,
      uploadError: null,

      /* ── Upload ─────────────────────────────────────────────────────── */
      async uploadFiles(event) {
        const files = [...event.target.files];
        if (!files.length) return;
        this.uploading   = true;
        this.uploadError = null;
        const fileKey = this.cfg.collection === 'documents' ? 'document' : 'image';
        for (const file of files) {
          const fd = new FormData();
          fd.append('mediable_type', this.cfg.type);
          fd.append('mediable_id',   String(this.cfg.id));
          fd.append('collection',    this.cfg.collection);
          fd.append(fileKey,         file);
          fd.append('_token',        this.cfg.csrf);
          try {
            const res  = await fetch(this.cfg.uploadUrl, { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success && json.media) {
              this.items.push(json.media);
              this.$nextTick(() => window.lucide && lucide.createIcons());
            } else {
              this.uploadError = json.error || 'Erreur lors de l\'envoi.';
            }
          } catch (e) {
            this.uploadError = 'Erreur réseau, veuillez réessayer.';
          }
        }
        this.uploading     = false;
        event.target.value = '';
      },

      /* ── Suppression ─────────────────────────────────────────────────── */
      async deleteItem(id) {
        if (!confirm('Supprimer ce fichier définitivement ?')) return;
        const fd = new FormData();
        fd.append('_token', this.cfg.csrf);
        try {
          const res  = await fetch(this.cfg.baseUrl + id + '/delete', { method: 'POST', body: fd });
          const json = await res.json();
          if (json.success) {
            this.items = this.items.filter(i => i.id !== id);
          } else {
            alert(json.error || 'Erreur lors de la suppression.');
          }
        } catch (e) {
          alert('Erreur réseau.');
        }
      },

      /* ── Helpers icônes docs ─────────────────────────────────────────── */
      docIcon(d) {
        const mime = (d.mime_type  || '').toLowerCase();
        const name = (d.file_name  || '').toLowerCase();
        if (mime.includes('pdf')   || name.endsWith('.pdf'))   return 'file-text';
        if (mime.includes('image'))                             return 'image';
        if (mime.includes('word')  || /\.docx?$/.test(name))   return 'file-type-2';
        if (mime.includes('sheet') || /\.xlsx?$/.test(name))   return 'table-2';
        if (mime.includes('csv')   || name.endsWith('.csv'))    return 'sheet';
        return 'file';
      },
      docBg(d) {
        const mime = (d.mime_type || '').toLowerCase();
        const name = (d.file_name || '').toLowerCase();
        if (mime.includes('pdf')   || name.endsWith('.pdf'))   return 'bg-rose-100';
        if (mime.includes('image'))                             return 'bg-violet-100';
        if (mime.includes('word')  || /\.docx?$/.test(name))   return 'bg-blue-100';
        if (mime.includes('sheet') || /\.xlsx?$/.test(name))   return 'bg-emerald-100';
        return 'bg-slate-100';
      },
      docColor(d) {
        const mime = (d.mime_type || '').toLowerCase();
        const name = (d.file_name || '').toLowerCase();
        if (mime.includes('pdf')   || name.endsWith('.pdf'))   return 'text-rose-600';
        if (mime.includes('image'))                             return 'text-violet-600';
        if (mime.includes('word')  || /\.docx?$/.test(name))   return 'text-blue-600';
        if (mime.includes('sheet') || /\.xlsx?$/.test(name))   return 'text-emerald-600';
        return 'text-slate-500';
      },
    };
  }
  </script>

</div>
<?php $view->end() ?>
