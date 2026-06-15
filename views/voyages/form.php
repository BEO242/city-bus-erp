<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
use CityBus\Models\Trip;

$isEdit = !empty($trip);
$action = $isEdit ? url('voyages/' . $trip['id'] . '/update') : url('voyages');

$decoded = function ($v) {
  if (!$v) return [];
  $j = json_decode((string)$v, true);
  return is_array($j) ? $j : [(string)$v];
};
$notesArr    = $isEdit ? $decoded($trip['notes'] ?? null) : [];
$incNotesArr = $isEdit ? $decoded($trip['incident_notes'] ?? null) : [];
?>
<?php $view->start('content') ?>

<div class="space-y-5"
     x-data='{
       notes: <?= htmlspecialchars(json_encode($notesArr ?: [""], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>,
       incNotes: <?= htmlspecialchars(json_encode($incNotesArr, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>,
       crew: <?= htmlspecialchars(json_encode(array_map(fn($c) => ["emp" => $c["emp_id"], "role" => $c["role"], "note" => $c["crew_note"]], $crew ?: []), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>
     }'>

  <!-- Breadcrumb -->
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= e(url('voyages')) ?>" class="hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Voyages
    </a>
    <span>/</span>
    <span class="text-slate-800 font-semibold"><?= e($title) ?></span>
  </div>

  <form method="post" action="<?= e($action) ?>" data-dirty-watch="<?= $isEdit ? '1' : '0' ?>" class="space-y-5">
    <?= csrf_field() ?>

    <!-- ─────── BLOC 1: Affectation ─────── -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
        <i data-lucide="truck" class="w-5 h-5 text-cb-primary"></i>
        Affectation
      </h2>

      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Ligne *</label>
          <select name="line_id" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
            <option value="">— Sélectionner —</option>
            <?php foreach ($lines as $l): ?>
              <option value="<?= (int)$l['id'] ?>" <?= ($isEdit && (int)$trip['line_id'] === (int)$l['id']) ? 'selected' : '' ?>>
                <?= e($l['code']) ?> · <?= e($l['name']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Véhicule *</label>
          <select name="bus_id" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
            <option value="">— Sélectionner —</option>
            <?php foreach ($buses as $b): ?>
              <option value="<?= (int)$b['id'] ?>" <?= ($isEdit && (int)$trip['bus_id'] === (int)$b['id']) ? 'selected' : '' ?>>
                <?= e($b['code']) ?> · <?= e($b['plate']) ?> (<?= (int)$b['seats'] ?> pl.)
              </option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Chauffeur *</label>
          <select name="driver_id" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
            <option value="">— Sélectionner —</option>
            <?php foreach ($drivers as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= ($isEdit && (int)$trip['driver_id'] === (int)$d['id']) ? 'selected' : '' ?>>
                <?= e($d['matricule']) ?> · <?= e($d['first_name']) ?> <?= e($d['last_name']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
    </div>

    <!-- ─────── BLOC 2: Planning ─────── -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
        <i data-lucide="calendar" class="w-5 h-5 text-cb-primary"></i>
        Planning
      </h2>

      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Date *</label>
          <input type="date" name="trip_date" required
                 value="<?= e($isEdit ? $trip['trip_date'] : date('Y-m-d')) ?>"
                 class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Heure départ *</label>
          <input type="time" name="departure_scheduled" required
                 value="<?= e($isEdit ? substr($trip['departure_scheduled'], 0, 5) : '') ?>"
                 class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Heure arrivée prévue</label>
          <input type="time" name="arrival_scheduled"
                 value="<?= e($isEdit && $trip['arrival_scheduled'] ? substr($trip['arrival_scheduled'], 0, 5) : '') ?>"
                 class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
        </div>

        <?php if ($isEdit): ?>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Départ réel</label>
            <input type="time" name="departure_actual"
                   value="<?= e($trip['departure_actual'] ? substr($trip['departure_actual'], 0, 5) : '') ?>"
                   class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Arrivée réelle</label>
            <input type="time" name="arrival_actual"
                   value="<?= e($trip['arrival_actual'] ? substr($trip['arrival_actual'], 0, 5) : '') ?>"
                   class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
          </div>
        <?php endif ?>
      </div>
    </div>

    <!-- ─────── BLOC 3: Caractéristiques ─────── -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
        <i data-lucide="tags" class="w-5 h-5 text-cb-primary"></i>
        Caractéristiques
      </h2>

      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
          <select name="trip_type" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <?php foreach (Trip::TYPES as $k => $lbl): ?>
              <option value="<?= e($k) ?>" <?= ($isEdit && ($trip['trip_type'] ?? '') === $k) ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Priorité</label>
          <select name="priority" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <?php foreach (Trip::PRIORITIES as $k => $lbl): ?>
              <option value="<?= e($k) ?>" <?= ($isEdit && ($trip['priority'] ?? '') === $k) ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Référence externe</label>
          <input name="external_reference"
                 value="<?= e($isEdit ? ($trip['external_reference'] ?? '') : '') ?>"
                 placeholder="Bon de commande…"
                 class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Km au départ</label>
          <input type="number" name="mileage_start"
                 value="<?= e($isEdit ? ($trip['mileage_start'] ?? '') : '') ?>"
                 class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
        </div>
        <?php if ($isEdit): ?>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Km à l'arrivée</label>
            <input type="number" name="mileage_end"
                   value="<?= e($trip['mileage_end'] ?? '') ?>"
                   class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
          </div>
        <?php endif ?>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Météo</label>
          <input name="weather_conditions"
                 value="<?= e($isEdit ? ($trip['weather_conditions'] ?? '') : '') ?>"
                 placeholder="Ensoleillé, pluie…"
                 class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:border-cb-primary focus:outline-none">
        </div>
        <div class="md:col-span-3">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="public_visible" value="1"
                   <?= (!$isEdit || (int)($trip['public_visible'] ?? 1)) ? 'checked' : '' ?>
                   class="rounded">
            <span class="text-sm text-slate-700">Visible sur le site public</span>
          </label>
        </div>
      </div>
    </div>

    <!-- ─────── BLOC 4: Équipage ─────── -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
          <i data-lucide="users" class="w-5 h-5 text-cb-primary"></i>
          Équipage supplémentaire
        </h2>
        <button type="button"
                @click="crew.push({emp:'', role:'convoyeur', note:''})"
                class="text-xs text-cb-primary hover:underline font-semibold">+ Ajouter membre</button>
      </div>

      <template x-for="(c, idx) in crew" :key="idx">
        <div class="grid md:grid-cols-12 gap-2 mb-2 items-center">
          <select :name="'crew_employee_id[]'" x-model="c.emp" class="md:col-span-5 px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <option value="">— Membre —</option>
            <?php foreach ($staff as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['matricule']) ?> · <?= e($s['first_name']) ?> <?= e($s['last_name']) ?> (<?= e($s['position']) ?>)</option>
            <?php endforeach ?>
          </select>
          <select :name="'crew_role[]'" x-model="c.role" class="md:col-span-2 px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <option value="chauffeur">Chauffeur (relève)</option>
            <option value="convoyeur">Convoyeur</option>
            <option value="caissier">Caissier</option>
            <option value="controleur">Contrôleur</option>
            <option value="guide">Guide</option>
            <option value="autre">Autre</option>
          </select>
          <input :name="'crew_notes[]'" x-model="c.note" placeholder="Notes" class="md:col-span-4 px-3 py-2 text-sm border border-slate-200 rounded-lg">
          <button type="button" @click="crew.splice(idx, 1)" class="md:col-span-1 text-rose-600 text-sm hover:underline">×</button>
        </div>
      </template>

      <p x-show="crew.length === 0" class="text-sm text-slate-400 italic">Aucun membre supplémentaire</p>
    </div>

    <!-- ─────── BLOC 5: Notes ─────── -->
    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-soft">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
          <i data-lucide="sticky-note" class="w-5 h-5 text-cb-primary"></i>
          Notes
        </h2>
        <button type="button" @click="notes.push('')" class="text-xs text-cb-primary hover:underline font-semibold">+ Ligne</button>
      </div>

      <template x-for="(n, idx) in notes" :key="idx">
        <div class="flex gap-2 mb-2">
          <input :name="'notes[]'" x-model="notes[idx]" class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-lg">
          <button type="button" @click="notes.splice(idx, 1)" class="px-3 text-rose-600">×</button>
        </div>
      </template>

      <?php if ($isEdit): ?>
        <div class="mt-6 pt-6 border-t border-slate-100">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-rose-700 flex items-center gap-2">
              <i data-lucide="alert-triangle" class="w-4 h-4"></i>
              Notes d'incident
            </h3>
            <button type="button" @click="incNotes.push('')" class="text-xs text-rose-600 hover:underline font-semibold">+ Ligne</button>
          </div>
          <template x-for="(n, idx) in incNotes" :key="idx">
            <div class="flex gap-2 mb-2">
              <input :name="'incident_notes[]'" x-model="incNotes[idx]" class="flex-1 px-3 py-2 text-sm border border-rose-200 rounded-lg">
              <button type="button" @click="incNotes.splice(idx, 1)" class="px-3 text-rose-600">×</button>
            </div>
          </template>
        </div>
      <?php endif ?>
    </div>

    <!-- ─────── ACTIONS ─────── -->
    <div class="flex justify-end gap-2">
      <a href="<?= e(url($isEdit ? 'voyages/' . $trip['id'] : 'voyages')) ?>"
         class="px-5 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
        Annuler
      </a>
      <button data-dirty-submit class="inline-flex items-center gap-2 px-6 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-secondary shadow-soft">
        <i data-lucide="<?= $isEdit ? 'save' : 'plus' ?>" class="w-4 h-4"></i>
        <?= $isEdit ? 'Enregistrer' : 'Créer le voyage' ?>
      </button>
    </div>

  </form>
</div>

<?php $view->end() ?>
