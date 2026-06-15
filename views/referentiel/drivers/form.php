<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Driver;
$view->extends('layouts/app');
$isEdit  = !empty($driver);
$drvId   = $driver['id'] ?? 0;
$action  = $isEdit ? url('referentiel/drivers/'.$drvId) : url('referentiel/drivers');
$gallery = $gallery ?? [];
$docs    = $docs ?? [];
$cats    = !empty($driver['license_categories']) ? array_map('trim', explode(',', $driver['license_categories'])) : [];
?>
<?php $view->start('content') ?>
<div class="space-y-6" x-data="{ tab: 'identite' }">

  <!-- En-tete -->
  <div class="flex items-center gap-4">
    <a href="<?= e(url('referentiel/drivers' . ($isEdit ? '/'.$drvId : ''))) ?>"
       class="text-slate-500 hover:text-cb-primary p-2 rounded-lg hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>
      <?php if ($isEdit): ?>
        <p class="text-sm text-slate-500"><?= e($driver['matricule']) ?> &middot; <?= e($driver['phone']) ?></p>
      <?php endif ?>
    </div>
  </div>

  <!-- Onglets -->
  <div class="border-b border-slate-200">
    <nav class="-mb-px flex gap-1 overflow-x-auto">
      <?php
      $tabs = [
        ['id'=>'identite','icon'=>'user','label'=>'Identite'],
        ['id'=>'contact','icon'=>'phone','label'=>'Contact &amp; urgence'],
        ['id'=>'permis','icon'=>'id-card','label'=>'Permis &amp; aptitudes'],
        ['id'=>'carriere','icon'=>'briefcase','label'=>'Carriere &amp; affectation'],
        ['id'=>'remuneration','icon'=>'banknote','label'=>'R&eacute;mun&eacute;ration'],
        ['id'=>'galerie','icon'=>'image','label'=>'Photo','editOnly'=>true],
        ['id'=>'documents','icon'=>'paperclip','label'=>'Documents','editOnly'=>true],
      ];
      foreach ($tabs as $t): $eo = $t['editOnly'] ?? false; ?>
      <button type="button" @click="tab = '<?= $t['id'] ?>'"
        :class="tab === '<?= $t['id'] ?>' ? 'border-cb-primary text-cb-primary' : 'border-transparent text-slate-500 hover:text-slate-700'"
        class="flex items-center gap-1.5 px-4 py-3 border-b-2 font-medium text-sm whitespace-nowrap transition-colors">
        <i data-lucide="<?= $t['icon'] ?>" class="w-4 h-4"></i> <?= $t['label'] ?>
        <?php if ($eo && !$isEdit): ?><span class="text-[10px] bg-slate-100 text-slate-400 px-1 rounded ml-1">apres creation</span><?php endif ?>
      </button>
      <?php endforeach ?>
    </nav>
  </div>

  <?php $dateVal = fn($v) => ($v && $v !== '0000-00-00') ? $v : ''; ?>
  <form method="post" action="<?= e($action) ?>" data-dirty-watch="<?= $isEdit ? '1' : '0' ?>" novalidate class="space-y-6">
    <?= csrf_field() ?>

    <!-- ONGLET 1 : Identite -->
    <div x-show="tab === 'identite'" class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
      <h2 class="font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="user" class="w-4 h-4 text-cb-primary"></i> Etat civil
      </h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="cb-label">Matricule <span class="text-rose-500">*</span></label>
          <input name="matricule" required maxlength="20" placeholder="CHF-001"
                 value="<?= e(old('matricule',$driver['matricule']??'')) ?>" class="cb-input">
          <?php foreach (errors('matricule') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
        </div>
        <div>
          <label class="cb-label">Nom <span class="text-rose-500">*</span></label>
          <input name="last_name" required maxlength="60"
                 value="<?= e(old('last_name',$driver['last_name']??'')) ?>" class="cb-input">
          <?php foreach (errors('last_name') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
        </div>
        <div>
          <label class="cb-label">Pr&eacute;nom <span class="text-rose-500">*</span></label>
          <input name="first_name" required maxlength="60"
                 value="<?= e(old('first_name',$driver['first_name']??'')) ?>" class="cb-input">
          <?php foreach (errors('first_name') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
        </div>
        <div>
          <label class="cb-label">Date de naissance</label>
          <input type="date" name="birth_date"
                 value="<?= e($dateVal(old('birth_date',$driver['birth_date']??''))) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">Lieu de naissance</label>
          <input name="birth_place" maxlength="80"
                 value="<?= e(old('birth_place',$driver['birth_place']??'')) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">Genre</label>
          <select name="gender" class="cb-input">
            <option value="">--</option>
            <?php foreach (Driver::GENDERS as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= old('gender',$driver['gender']??'')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="cb-label">Situation matrimoniale</label>
          <select name="marital_status" class="cb-input">
            <option value="">--</option>
            <?php foreach (Driver::MARITAL_STATUSES as $k=>$v): ?>
            <option value="<?= e($k) ?>" <?= old('marital_status',$driver['marital_status']??'')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="cb-label">Nb d'enfants</label>
          <input type="number" name="children_count" min="0" max="20"
                 value="<?= e(old('children_count',$driver['children_count']??0)) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">Nationalit&eacute;</label>
          <input name="nationality" maxlength="50"
                 value="<?= e(old('nationality',$driver['nationality']??'Congolaise')) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">Groupe sanguin</label>
          <input name="blood_type" maxlength="5" placeholder="O+"
                 value="<?= e(old('blood_type',$driver['blood_type']??'')) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">N&deg; carte d'identit&eacute;</label>
          <input name="national_id" maxlength="40"
                 value="<?= e(old('national_id',$driver['national_id']??'')) ?>" class="cb-input">
        </div>
        <div>
          <label class="cb-label">Expiration CNI</label>
          <input type="date" name="national_id_expiry"
                 value="<?= e($dateVal(old('national_id_expiry',$driver['national_id_expiry']??''))) ?>" class="cb-input">
        </div>
      </div>
    </div>

    <!-- ONGLET 2 : Contact -->
    <div x-show="tab === 'contact'" class="space-y-6">
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="phone" class="w-4 h-4 text-cb-primary"></i> Coordonn&eacute;es
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">T&eacute;l&eacute;phone principal <span class="text-rose-500">*</span></label>
            <input name="phone" required maxlength="20" placeholder="+242 06 ..."
                   value="<?= e(old('phone',$driver['phone']??'')) ?>" class="cb-input">
            <?php foreach (errors('phone') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
          </div>
          <div>
            <label class="cb-label">T&eacute;l&eacute;phone secondaire</label>
            <input name="phone_alt" maxlength="20"
                   value="<?= e(old('phone_alt',$driver['phone_alt']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Email</label>
            <input type="email" name="email" maxlength="120"
                   value="<?= e(old('email',$driver['email']??'')) ?>" class="cb-input">
          </div>
          <div class="sm:col-span-2 lg:col-span-2">
            <label class="cb-label">Adresse</label>
            <input name="address" maxlength="200"
                   value="<?= e(old('address',$driver['address']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Ville</label>
            <input name="city" maxlength="60" placeholder="Brazzaville"
                   value="<?= e(old('city',$driver['city']??'')) ?>" class="cb-input">
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="phone-call" class="w-4 h-4 text-rose-500"></i> Personne &agrave; pr&eacute;venir
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">Nom complet</label>
            <input name="emergency_name" maxlength="100"
                   value="<?= e(old('emergency_name',$driver['emergency_name']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">T&eacute;l&eacute;phone</label>
            <input name="emergency_phone" maxlength="20"
                   value="<?= e(old('emergency_phone',$driver['emergency_phone']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Lien (epoux, parent...)</label>
            <input name="emergency_relation" maxlength="50"
                   value="<?= e(old('emergency_relation',$driver['emergency_relation']??'')) ?>" class="cb-input">
          </div>
        </div>
      </div>
    </div>

    <!-- ONGLET 3 : Permis & aptitudes -->
    <div x-show="tab === 'permis'" class="space-y-6">
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="id-card" class="w-4 h-4 text-cb-primary"></i> Permis de conduire
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">N&deg; de permis <span class="text-rose-500">*</span></label>
            <input name="license_number" required maxlength="50"
                   value="<?= e(old('license_number',$driver['license_number']??'')) ?>" class="cb-input font-mono">
            <?php foreach (errors('license_number') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
          </div>
          <div>
            <label class="cb-label">Date d'obtention</label>
            <input type="date" name="license_issue_date"
                   value="<?= e($dateVal(old('license_issue_date',$driver['license_issue_date']??''))) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Date d'expiration <span class="text-rose-500">*</span></label>
            <input type="date" name="license_expiry" required
                   value="<?= e($dateVal(old('license_expiry',$driver['license_expiry']??''))) ?>" class="cb-input">
            <?php foreach (errors('license_expiry') as $err): ?><p class="text-xs text-rose-600 mt-1"><?= e($err) ?></p><?php endforeach ?>
          </div>
          <div class="sm:col-span-2 lg:col-span-3">
            <label class="cb-label">Cat&eacute;gories</label>
            <div class="flex flex-wrap gap-2 mt-1">
              <?php foreach (Driver::LICENSE_CATEGORIES as $c):
                $checked = in_array($c, $cats, true);
              ?>
              <label class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 has-[:checked]:border-cb-primary has-[:checked]:bg-blue-50">
                <input type="checkbox" name="license_categories[]" value="<?= e($c) ?>" <?= $checked?'checked':'' ?> class="w-4 h-4 accent-cb-primary">
                <span class="text-sm font-semibold">Cat. <?= e($c) ?></span>
              </label>
              <?php endforeach ?>
            </div>
          </div>
          <div class="sm:col-span-2 lg:col-span-3">
            <label class="cb-label">Autorit&eacute; de d&eacute;livrance</label>
            <input name="license_authority" maxlength="100" placeholder="Minist&egrave;re des Transports"
                   value="<?= e(old('license_authority',$driver['license_authority']??'')) ?>" class="cb-input">
          </div>
        </div>
      </div>

      <?php
      $medVal0    = $dateVal(old('medical_cert_expiry', $driver['medical_cert_expiry'] ?? ''));
      $psychoVal0 = $dateVal(old('psycho_test_expiry',  $driver['psycho_test_expiry']  ?? ''));
      $ophtVal0   = $dateVal(old('ophthalmo_expiry',    $driver['ophthalmo_expiry']    ?? ''));
      $drugVal0   = $dateVal(old('drug_test_last',       $driver['drug_test_last']      ?? ''));
      ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5"
           x-data="aptitudes(<?= e(json_encode([
             'med'    => $medVal0,
             'psycho' => $psychoVal0,
             'opht'   => $ophtVal0,
             'drug'   => $drugVal0,
           ])) ?>)">

        <!-- En-tête + badge global réactif -->
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <h2 class="font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="stethoscope" class="w-4 h-4 text-cb-primary"></i> Aptitudes m&eacute;dicales
          </h2>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full border transition-colors"
                :class="globalBadgeCls">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path x-show="globalLevel==='ok'"      stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              <path x-show="globalLevel==='warn'"    stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              <path x-show="globalLevel==='danger'"  stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
              <path x-show="globalLevel==='none'"    stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span x-text="globalLabel"></span>
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

          <!-- Visite médicale -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="cb-label !mb-0">Visite m&eacute;dicale (expiration)</label>
              <span x-show="med !== ''"
                    class="text-[11px] font-semibold px-2 py-0.5 rounded-full transition-colors"
                    :class="aptStatus(med).cls"
                    x-text="aptStatus(med).label"></span>
            </div>
            <input type="date" name="medical_cert_expiry"
                   x-model="med"
                   class="cb-input transition-colors"
                   :class="aptStatus(med).ring">
            <!-- Barre de progression -->
            <div x-show="med !== ''" class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500"
                   :class="aptStatus(med).bar"
                   :style="'width:' + aptStatus(med).pct + '%'"></div>
            </div>
          </div>

          <!-- Test psychotechnique -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="cb-label !mb-0">Test psychotechnique (expir.)</label>
              <span x-show="psycho !== ''"
                    class="text-[11px] font-semibold px-2 py-0.5 rounded-full transition-colors"
                    :class="aptStatus(psycho).cls"
                    x-text="aptStatus(psycho).label"></span>
            </div>
            <input type="date" name="psycho_test_expiry"
                   x-model="psycho"
                   class="cb-input transition-colors"
                   :class="aptStatus(psycho).ring">
            <div x-show="psycho !== ''" class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500"
                   :class="aptStatus(psycho).bar"
                   :style="'width:' + aptStatus(psycho).pct + '%'"></div>
            </div>
          </div>

          <!-- Bilan ophtalmologique -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="cb-label !mb-0">Bilan ophtalmologique (expir.)</label>
              <span x-show="opht !== ''"
                    class="text-[11px] font-semibold px-2 py-0.5 rounded-full transition-colors"
                    :class="aptStatus(opht).cls"
                    x-text="aptStatus(opht).label"></span>
            </div>
            <input type="date" name="ophthalmo_expiry"
                   x-model="opht"
                   class="cb-input transition-colors"
                   :class="aptStatus(opht).ring">
            <div x-show="opht !== ''" class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500"
                   :class="aptStatus(opht).bar"
                   :style="'width:' + aptStatus(opht).pct + '%'"></div>
            </div>
          </div>

          <!-- Dernier test antidopage -->
          <div>
            <label class="cb-label">Dernier test antidopage</label>
            <input type="date" name="drug_test_last"
                   x-model="drug"
                   class="cb-input">
            <p x-show="drug !== ''" class="text-xs text-slate-400 mt-1"
               x-text="drugAgo"></p>
          </div>
        </div>

        <!-- Alertes récapitulatives réactives -->
        <template x-if="hasAlerts">
          <div class="pt-4 border-t border-slate-100 space-y-2">
            <template x-if="aptStatus(med).level==='danger' || aptStatus(med).level==='warn'">
              <div class="flex items-center gap-2 px-3 py-2 rounded-xl border text-xs transition-colors"
                   :class="aptStatus(med).level==='danger' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <strong>Visite m&eacute;dicale</strong>
                <span x-text="' — ' + aptStatus(med).label"></span>
              </div>
            </template>
            <template x-if="aptStatus(psycho).level==='danger' || aptStatus(psycho).level==='warn'">
              <div class="flex items-center gap-2 px-3 py-2 rounded-xl border text-xs transition-colors"
                   :class="aptStatus(psycho).level==='danger' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                <strong>Test psychotechnique</strong>
                <span x-text="' — ' + aptStatus(psycho).label"></span>
              </div>
            </template>
            <template x-if="aptStatus(opht).level==='danger' || aptStatus(opht).level==='warn'">
              <div class="flex items-center gap-2 px-3 py-2 rounded-xl border text-xs transition-colors"
                   :class="aptStatus(opht).level==='danger' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <strong>Bilan ophtalmologique</strong>
                <span x-text="' — ' + aptStatus(opht).label"></span>
              </div>
            </template>
          </div>
        </template>
      </div>
    </div>

    <!-- ONGLET 4 : Carrière & affectation -->
    <div x-show="tab === 'carriere'" class="space-y-6">
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="briefcase" class="w-4 h-4 text-cb-primary"></i> Carri&egrave;re
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">Date d'embauche <span class="text-rose-500">*</span></label>
            <input type="date" name="hire_date" required
                   value="<?= e($dateVal(old('hire_date',$driver['hire_date']??date('Y-m-d')))) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Ann&eacute;es d'exp&eacute;rience</label>
            <input type="number" name="experience_years" min="0" max="60"
                   value="<?= e(old('experience_years',$driver['experience_years']??0)) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Ancien employeur</label>
            <input name="previous_employer" maxlength="120"
                   value="<?= e(old('previous_employer',$driver['previous_employer']??'')) ?>" class="cb-input">
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="map-pin" class="w-4 h-4 text-cb-primary"></i> Affectation actuelle
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">Statut <span class="text-rose-500">*</span></label>
            <select name="status" required class="cb-input">
              <?php foreach (Driver::STATUSES as $k=>$v): ?>
              <option value="<?= e($k) ?>" <?= old('status',$driver['status']??'actif')===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label class="cb-label">Agence</label>
            <select name="agency_id" class="cb-input">
              <option value="">-- Aucune --</option>
              <?php foreach ($agencies as $a): ?>
              <option value="<?= e($a['id']) ?>" <?= (int)old('agency_id',$driver['agency_id']??0)===(int)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label class="cb-label">Bus principal</label>
            <select name="primary_bus_id" class="cb-input">
              <option value="">-- Non affect&eacute; --</option>
              <?php foreach ($buses as $b): ?>
              <option value="<?= e($b['id']) ?>" <?= (int)old('primary_bus_id',$driver['primary_bus_id']??0)===(int)$b['id']?'selected':'' ?>><?= e($b['code']) ?> &mdash; <?= e($b['plate']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-3">
        <label class="cb-label">Notes / observations</label>
        <textarea name="notes" rows="4" class="cb-input resize-none" placeholder="Comportement, particularit&eacute;s, historique..."><?= e(old('notes',$driver['notes']??'')) ?></textarea>
      </div>
    </div>

    <!-- ONGLET 5 : Rémunération -->
    <div x-show="tab === 'remuneration'" class="space-y-6">
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="banknote" class="w-4 h-4 text-cb-primary"></i> R&eacute;mun&eacute;ration
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">Salaire de base (FCFA / mois)</label>
            <input type="number" name="salary_base" min="0"
                   value="<?= e(old('salary_base',$driver['salary_base']??0)) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Prime journali&egrave;re (FCFA)</label>
            <input type="number" name="daily_bonus" min="0"
                   value="<?= e(old('daily_bonus',$driver['daily_bonus']??0)) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">Prime au km (FCFA)</label>
            <input type="number" step="0.01" name="km_bonus_rate" min="0"
                   value="<?= e(old('km_bonus_rate',$driver['km_bonus_rate']??0)) ?>" class="cb-input">
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 space-y-5">
        <h2 class="font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="landmark" class="w-4 h-4 text-cb-primary"></i> Coordonn&eacute;es bancaires
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="cb-label">Banque</label>
            <input name="bank_name" maxlength="80"
                   value="<?= e(old('bank_name',$driver['bank_name']??'')) ?>" class="cb-input">
          </div>
          <div>
            <label class="cb-label">N&deg; de compte</label>
            <input name="bank_account" maxlength="60"
                   value="<?= e(old('bank_account',$driver['bank_account']??'')) ?>" class="cb-input font-mono">
          </div>
          <div>
            <label class="cb-label">Mobile Money</label>
            <input name="mobile_money_number" maxlength="20"
                   value="<?= e(old('mobile_money_number',$driver['mobile_money_number']??'')) ?>" class="cb-input">
          </div>
        </div>
      </div>
    </div>

    <!-- Barre d'action -->
    <div x-show="!['galerie','documents'].includes(tab)"
         class="flex justify-between items-center bg-white rounded-2xl border border-slate-100 shadow-soft px-6 py-4">
      <a href="<?= e(url('referentiel/drivers' . ($isEdit ? '/'.$drvId : ''))) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition">Annuler</a>
      <div class="flex items-center gap-3">
        <?php if ($isEdit): ?>
        <a href="<?= e(url('referentiel/drivers/'.$drvId)) ?>"
           class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition flex items-center gap-1.5">
          <i data-lucide="eye" class="w-4 h-4"></i> Voir la fiche
        </a>
        <?php endif ?>
        <button type="submit" data-dirty-submit class="px-6 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-1.5">
          <i data-lucide="check" class="w-4 h-4"></i> <?= $isEdit ? 'Mettre &agrave; jour' : 'Enregistrer' ?>
        </button>
      </div>
    </div>
  </form>

  <!-- Galerie photos (édition uniquement) -->
  <div x-show="tab === 'galerie'">
    <?php if ($isEdit): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
      <?php
      $mediableType = 'drivers'; $mediableId = $drvId;
      $galleryItems = \CityBus\Services\MediaService::enrichAll($gallery);
      $label = 'Photo du chauffeur';
      $maxFiles = 6;
      include BASE_PATH . '/views/components/media-gallery.php';
      ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 flex flex-col items-center gap-3 py-12 text-center">
      <i data-lucide="image" class="w-10 h-10 text-slate-300"></i>
      <p class="text-slate-500">Enregistrez d'abord le chauffeur pour ajouter une photo.</p>
    </div>
    <?php endif ?>
  </div>

  <!-- Documents -->
  <div x-show="tab === 'documents'">
    <?php if ($isEdit): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6">
      <?php
      $mediableType = 'drivers'; $mediableId = $drvId;
      $docItems = \CityBus\Services\MediaService::enrichAll($docs);
      include BASE_PATH . '/views/components/media-dropzone.php';
      ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-6 flex flex-col items-center gap-3 py-12 text-center">
      <i data-lucide="paperclip" class="w-10 h-10 text-slate-300"></i>
      <p class="text-slate-500">Enregistrez d'abord le chauffeur pour joindre des documents (permis, contrat, CNI...).</p>
    </div>
    <?php endif ?>
  </div>
</div>
<style>
.cb-label{display:block;font-size:.8125rem;font-weight:500;color:#374151;margin-bottom:4px}
.cb-input{display:block;width:100%;padding:.6rem .75rem;border:1px solid #e2e8f0;border-radius:.75rem;font-size:.875rem;font-family:inherit;background:#fff;outline:none;transition:border-color .15s}
.cb-input:focus{border-color:#1565C0;box-shadow:0 0 0 3px rgba(21,101,192,.08)}
</style>
<script>
function aptitudes(init) {
  return {
    med:    init.med    ?? '',
    psycho: init.psycho ?? '',
    opht:   init.opht   ?? '',
    drug:   init.drug   ?? '',

    aptStatus(dateStr) {
      if (!dateStr) return { level:'none', label:'Non renseigné', cls:'bg-slate-100 text-slate-400', ring:'', bar:'bg-slate-300', pct:0 };
      const d     = new Date(dateStr);
      const today = new Date(); today.setHours(0,0,0,0);
      const days  = Math.round((d - today) / 86400000);
      if (days < 0)    return { level:'danger', label:'Expiré (il y a '+Math.abs(days)+' j)',  cls:'bg-rose-100 text-rose-700',    ring:'border-rose-400',   bar:'bg-rose-500',    pct:100 };
      if (days <= 15)  return { level:'danger', label:'Expire dans '+days+' j',                cls:'bg-rose-100 text-rose-700',    ring:'border-rose-400',   bar:'bg-rose-500',    pct:Math.max(5,Math.round(days/15*100)) };
      if (days <= 45)  return { level:'warn',   label:'À renouveler (J−'+days+')',             cls:'bg-amber-100 text-amber-700',  ring:'border-amber-400',  bar:'bg-amber-400',   pct:Math.round(days/45*100) };
      if (days <= 180) return { level:'ok',     label:'Valide (J−'+days+')',                   cls:'bg-emerald-100 text-emerald-700', ring:'border-emerald-300', bar:'bg-emerald-500', pct:100 };
      return                 { level:'ok',     label:'Valide (J−'+days+')',                   cls:'bg-emerald-100 text-emerald-700', ring:'border-emerald-300', bar:'bg-emerald-500', pct:100 };
    },

    get globalLevel() {
      const levels = [this.aptStatus(this.med).level, this.aptStatus(this.psycho).level, this.aptStatus(this.opht).level];
      if (levels.includes('danger')) return 'danger';
      if (levels.includes('warn'))   return 'warn';
      if (levels.includes('ok'))     return 'ok';
      return 'none';
    },

    get globalLabel() {
      if (this.globalLevel === 'danger') return 'Un ou plusieurs documents sont expirés ou critiques';
      if (this.globalLevel === 'warn')   return 'Des documents arrivent à échéance — planifier le renouvellement';
      if (this.globalLevel === 'ok')     return 'Aptitudes médicales à jour';
      return 'Aptitudes non encore renseignées';
    },

    get globalBadgeCls() {
      if (this.globalLevel === 'danger') return 'bg-rose-50 border-rose-200 text-rose-700';
      if (this.globalLevel === 'warn')   return 'bg-amber-50 border-amber-200 text-amber-700';
      if (this.globalLevel === 'ok')     return 'bg-emerald-50 border-emerald-200 text-emerald-700';
      return 'bg-slate-50 border-slate-200 text-slate-500';
    },

    get hasAlerts() {
      const l1 = this.aptStatus(this.med).level;
      const l2 = this.aptStatus(this.psycho).level;
      const l3 = this.aptStatus(this.opht).level;
      return l1 === 'danger' || l1 === 'warn' || l2 === 'danger' || l2 === 'warn' || l3 === 'danger' || l3 === 'warn';
    },

    get drugAgo() {
      if (!this.drug) return '';
      const d    = new Date(this.drug);
      const today = new Date(); today.setHours(0,0,0,0);
      const days = Math.round((today - d) / 86400000);
      if (days < 0)  return 'Prévu dans ' + Math.abs(days) + ' jour(s)';
      if (days === 0) return "Aujourd'hui";
      return 'Il y a ' + days + ' jour(s)';
    },
  };
}
</script>
<?php $view->end() ?>
