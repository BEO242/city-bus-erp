<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$isEdit = !empty($incident);
$action = $isEdit ? url('flotte/incidents/'.$incident['id']) : url('flotte/incidents');
$subjectType = old('subject_type', $incident['subject_type'] ?? 'bus');
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('flotte/incidents')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
  </div>

  <form method="post" action="<?= e($action) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft max-w-3xl"
        x-data="{ subjectType: '<?= e($subjectType) ?>' }">
    <?= csrf_field() ?>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Sujet *</label>
        <select name="subject_type" x-model="subjectType" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="bus">Bus</option>
          <option value="driver">Chauffeur</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Identifiant *</label>
        <select name="subject_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <option value="">— Sélectionner —</option>
          <template x-if="subjectType === 'bus'">
            <optgroup label="Bus">
              <?php foreach ($buses as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= ((int)old('subject_id', $incident['subject_id'] ?? 0) === (int)$b['id'] && $subjectType==='bus') ? 'selected' : '' ?>>
                  <?= e($b['code']) ?> · <?= e($b['plate']) ?>
                </option>
              <?php endforeach ?>
            </optgroup>
          </template>
          <template x-if="subjectType === 'driver'">
            <optgroup label="Chauffeurs">
              <?php foreach ($drivers as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= ((int)old('subject_id', $incident['subject_id'] ?? 0) === (int)$d['id'] && $subjectType==='driver') ? 'selected' : '' ?>>
                  <?= e($d['matricule']) ?> · <?= e($d['first_name'] . ' ' . $d['last_name']) ?>
                </option>
              <?php endforeach ?>
            </optgroup>
          </template>
        </select>
        <p class="text-xs text-slate-400 mt-1">Liste filtrée selon le type sélectionné.</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Type *</label>
        <select name="type" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <?php foreach ($types as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= old('type', $incident['type'] ?? '')===$k?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Gravité *</label>
        <select name="severity" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
          <?php foreach ($severities as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= old('severity', $incident['severity'] ?? 'mineur')===$k?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date / Heure *</label>
        <input type="datetime-local" name="occurred_at" required
               value="<?= e(old('occurred_at', !empty($incident['occurred_at']) ? date('Y-m-d\TH:i', strtotime((string)$incident['occurred_at'])) : date('Y-m-d\TH:i'))) ?>"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Lieu</label>
        <input name="location" value="<?= e(old('location', $incident['location'] ?? '')) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Description *</label>
      <textarea name="description" required rows="4" class="w-full px-3 py-2.5 rounded-xl border border-slate-200"><?= e(old('description', $incident['description'] ?? '')) ?></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Coût estimé (FCFA)</label>
      <input type="number" name="cost_fcfa" min="0" value="<?= e(old('cost_fcfa', $incident['cost_fcfa'] ?? 0)) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>

    <div class="flex justify-end gap-2 pt-2">
      <a href="<?= e(url('flotte/incidents')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white font-medium hover:bg-cb-secondary"><?= $isEdit?'Mettre à jour':'Enregistrer' ?></button>
    </div>
  </form>
</div>
<?php $view->end() ?>
