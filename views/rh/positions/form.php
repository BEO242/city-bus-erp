<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$isEdit    = !empty($position);
$action    = $isEdit ? url('rh/positions/'.$position['id']) : url('rh/positions');
$backUrl   = url('rh/positions');

$curCode   = $position['code']        ?? old('code',        '');
$curLabel  = $position['label']       ?? old('label',       '');
$curDept   = $position['department']  ?? old('department',  '');
$curDesc   = $position['description'] ?? old('description', '');
$curColor  = $position['color']       ?? old('color',       'slate');
$curOrder  = $position['sort_order']  ?? old('sort_order',  100);
$curActive = isset($position['is_active']) ? (int)$position['is_active'] : 1;

$colorPreview = [
  'slate'  => ['bg' => 'bg-slate-100',  'text' => 'text-slate-700',  'ring' => 'ring-slate-400'],
  'red'    => ['bg' => 'bg-red-100',    'text' => 'text-red-700',    'ring' => 'ring-red-400'],
  'orange' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'ring' => 'ring-orange-400'],
  'amber'  => ['bg' => 'bg-amber-100',  'text' => 'text-amber-700',  'ring' => 'ring-amber-400'],
  'green'  => ['bg' => 'bg-emerald-100','text' => 'text-emerald-700','ring' => 'ring-emerald-400'],
  'blue'   => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'ring' => 'ring-blue-400'],
  'violet' => ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'ring' => 'ring-violet-400'],
  'pink'   => ['bg' => 'bg-pink-100',   'text' => 'text-pink-700',   'ring' => 'ring-pink-400'],
];

$departments = ['Opérations','Finance','Commerce','Management','Administration','Technique','Autre'];
?>

<div class="space-y-5 max-w-xl mx-auto" x-data="{ color: '<?= e($curColor) ?>' }">

  <!-- En-tête -->
  <div class="flex items-center gap-3">
    <a href="<?= e($backUrl) ?>"
       class="p-2 rounded-lg text-slate-500 hover:text-cb-primary hover:bg-cb-bg transition">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-xl font-black text-slate-900"><?= e($title) ?></h1>
      <p class="text-xs text-slate-400 mt-0.5">
        <?= $isEdit ? 'Modifier le poste' : 'Créer un nouveau poste' ?>
      </p>
    </div>
  </div>

  <form method="post" action="<?= e($action) ?>" class="space-y-4">
    <?= csrf_field() ?>

    <!-- Identité -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="briefcase" class="w-4 h-4 text-cb-primary"></i> Identité
      </h2>

      <!-- Libellé -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Libellé <span class="text-rose-500">*</span>
        </label>
        <input type="text" name="label" required maxlength="100"
               value="<?= e($curLabel) ?>"
               placeholder="Ex : Chauffeur, Superviseur, Agent commercial…"
               x-ref="labelInput"
               @input="!<?= $isEdit ? 'true' : 'false' ?> && ($refs.codeInput.value = slugify($event.target.value))"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
      </div>

      <!-- Code -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Code technique <span class="text-rose-500">*</span>
        </label>
        <input type="text" name="code" required maxlength="30"
               value="<?= e($curCode) ?>"
               placeholder="ex : chauffeur"
               x-ref="codeInput"
               pattern="[a-z0-9_\-]+"
               title="Minuscules, chiffres, tirets et underscores uniquement"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm font-mono">
        <p class="text-[11px] text-slate-400 mt-1">
          Minuscules, chiffres, <code class="bg-slate-100 px-1 rounded">-</code> et <code class="bg-slate-100 px-1 rounded">_</code> uniquement.
          <?php if ($isEdit): ?>
          <strong class="text-amber-600">⚠ Modifier le code mettra à jour tous les employés associés.</strong>
          <?php endif ?>
        </p>
      </div>

      <!-- Département -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Département <span class="text-slate-400 font-normal">(optionnel)</span>
        </label>
        <input type="text" name="department" maxlength="60"
               list="dept-list"
               value="<?= e($curDept) ?>"
               placeholder="Ex : Opérations, Finance…"
               class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        <datalist id="dept-list">
          <?php foreach ($departments as $d): ?>
            <option value="<?= e($d) ?>">
          <?php endforeach ?>
        </datalist>
      </div>

      <!-- Description -->
      <div>
        <label class="text-xs font-semibold text-slate-600 block mb-1.5">
          Description <span class="text-slate-400 font-normal">(optionnel)</span>
        </label>
        <textarea name="description" rows="2" maxlength="500"
                  placeholder="Responsabilités, missions principales…"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm resize-none"><?= e($curDesc) ?></textarea>
      </div>
    </div>

    <!-- Couleur -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="palette" class="w-4 h-4 text-cb-primary"></i> Couleur du badge
      </h2>

      <div class="flex flex-wrap gap-2">
        <?php foreach ($colors as $key => $name):
          $cls = $colorPreview[$key];
        ?>
        <label class="cursor-pointer" title="<?= e($name) ?>">
          <input type="radio" name="color" value="<?= $key ?>"
                 x-model="color"
                 <?= $curColor === $key ? 'checked' : '' ?>
                 class="sr-only peer">
          <div class="w-8 h-8 rounded-full <?= $cls['bg'] ?> ring-2 ring-offset-2 ring-transparent
                      peer-checked:ring-2 peer-checked:<?= $cls['ring'] ?> transition-all"></div>
        </label>
        <?php endforeach ?>
      </div>

      <!-- Aperçu -->
      <div class="flex items-center gap-2 mt-1">
        <span class="text-xs text-slate-400">Aperçu :</span>
        <?php foreach ($colorPreview as $key => $cls): ?>
        <span x-show="color === '<?= $key ?>'"
              class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold
                     <?= $cls['bg'] ?> <?= $cls['text'] ?>">
          <i data-lucide="briefcase" class="w-3 h-3"></i>
          <span x-text="document.querySelector('[name=label]')?.value || 'Poste'"></span>
        </span>
        <?php endforeach ?>
      </div>
    </div>

    <!-- Options -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
      <h2 class="font-bold text-slate-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100">
        <i data-lucide="settings-2" class="w-4 h-4 text-cb-primary"></i> Options
      </h2>
      <div class="grid grid-cols-2 gap-4 items-end">
        <div>
          <label class="text-xs font-semibold text-slate-600 block mb-1.5">Ordre d'affichage</label>
          <input type="number" name="sort_order" min="0" value="<?= e($curOrder) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
        </div>
        <div class="pb-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" <?= $curActive ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-slate-300 accent-cb-primary">
            <span class="text-sm text-slate-700 font-semibold">Poste actif</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
      <a href="<?= e($backUrl) ?>"
         class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
        Annuler
      </a>
      <button type="submit"
              class="px-6 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-bold hover:bg-cb-dark transition flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i>
        <?= $isEdit ? 'Mettre à jour' : 'Créer le poste' ?>
      </button>
    </div>

  </form>
</div>

<script>
function slugify(str) {
  return str.toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9\s_-]/g, '')
    .trim().replace(/\s+/g, '_');
}
</script>

<?php $view->end() ?>
