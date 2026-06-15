<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$totalPostes   = count($caisses);
$activeCount   = count(array_filter($caisses, fn($c) => $c['is_active']));
$inactiveCount = $totalPostes - $activeCount;
$sessionsOpen  = array_sum(array_column($caisses, 'sessions_ouvertes'));

$typeIcons = [
    'principale'  => 'star',
    'point_vente' => 'shopping-bag',
    'agence'      => 'building-2',
    'mobile'      => 'smartphone',
];
$caissesJson = htmlspecialchars(json_encode(array_values($caisses)), ENT_QUOTES | ENT_HTML5);
$baseUrl     = e(url('finance/caisses'));
?>

<div x-data="caisseManager(<?= $caissesJson ?>, '<?= $baseUrl ?>')" class="space-y-5">

  <!-- ─── En-tête ─── -->
  <div class="flex justify-between flex-wrap gap-3 items-start">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Postes de caisse</h1>
      <p class="text-slate-500 text-sm mt-0.5">Définissez et gérez les postes de caisse par agence.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= e(url('caisse')) ?>"
         class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
        <i data-lucide="wallet" class="w-4 h-4 text-cb-primary"></i> Opérations caisse
      </a>
      <button @click="openCreate()"
              class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-semibold inline-flex items-center gap-2 hover:bg-cb-dark transition text-sm shadow-sm shadow-cb-primary/30">
        <i data-lucide="plus" class="w-4 h-4"></i> Nouveau poste
      </button>
    </div>
  </div>

  <!-- ─── KPI Strip ─── -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3.5 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-cb-bg flex items-center justify-center shrink-0">
        <i data-lucide="layout-grid" class="w-4 h-4 text-cb-primary"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-slate-900"><?= $totalPostes ?></p>
        <p class="text-xs text-slate-500">Postes total</p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3.5 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
        <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-emerald-700"><?= $activeCount ?></p>
        <p class="text-xs text-slate-500">Actifs</p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3.5 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-slate-50 flex items-center justify-center shrink-0">
        <i data-lucide="x-circle" class="w-4 h-4 text-slate-400"></i>
      </div>
      <div>
        <p class="text-2xl font-black text-slate-500"><?= $inactiveCount ?></p>
        <p class="text-xs text-slate-500">Inactifs</p>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3.5 flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl <?= $sessionsOpen > 0 ? 'bg-blue-50' : 'bg-slate-50' ?> flex items-center justify-center shrink-0">
        <i data-lucide="activity" class="w-4 h-4 <?= $sessionsOpen > 0 ? 'text-blue-600' : 'text-slate-300' ?>"></i>
      </div>
      <div>
        <p class="text-2xl font-black <?= $sessionsOpen > 0 ? 'text-blue-700' : 'text-slate-400' ?>"><?= $sessionsOpen ?></p>
        <p class="text-xs text-slate-500">Sessions ouvertes</p>
      </div>
    </div>
  </div>

  <!-- ─── Toolbar ─── -->
  <div class="bg-white rounded-2xl border border-slate-100 px-4 py-3 flex flex-wrap items-center gap-3">
    <div class="relative flex-1 min-w-[160px]">
      <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"></i>
      <input type="text" x-model="search" placeholder="Rechercher un poste, code…"
             class="w-full pl-8 pr-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none">
    </div>
    <select x-model="filterType"
            class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none bg-white text-slate-600">
      <option value="">Tous les types</option>
      <?php foreach ($types as $k => $l): ?>
        <option value="<?= e($k) ?>"><?= e($l) ?></option>
      <?php endforeach ?>
    </select>
    <select x-model="filterStatus"
            class="px-3 py-2 rounded-xl border border-slate-200 text-sm focus:border-cb-primary outline-none bg-white text-slate-600">
      <option value="">Tous les statuts</option>
      <option value="active">Actifs seulement</option>
      <option value="inactive">Inactifs seulement</option>
    </select>
    <span class="ml-auto text-xs text-slate-400 hidden sm:block" x-text="visibleCount + ' poste(s)'"></span>
    <button @click="search=''; filterType=''; filterStatus=''"
            x-show="search || filterType || filterStatus"
            x-cloak
            class="text-xs text-cb-primary hover:underline flex items-center gap-1">
      <i data-lucide="x" class="w-3 h-3"></i> Réinitialiser
    </button>
  </div>

  <!-- ─── Table ─── -->
  <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-100">
            <th class="px-5 py-3.5">Poste</th>
            <th class="px-5 py-3.5">Code</th>
            <th class="px-5 py-3.5">Agence</th>
            <th class="px-5 py-3.5">Type</th>
            <th class="px-5 py-3.5 text-center">Sessions</th>
            <th class="px-5 py-3.5 text-center">Transactions</th>
            <th class="px-5 py-3.5">Statut</th>
            <th class="px-5 py-3.5 text-right">Ordre</th>
            <th class="px-5 py-3.5"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50" id="caissesTableBody">
          <?php if (!$caisses): ?>
          <tr>
            <td colspan="9" class="px-5 py-16 text-center">
              <div class="flex flex-col items-center gap-3 text-slate-400">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center">
                  <i data-lucide="wallet" class="w-6 h-6 opacity-40"></i>
                </div>
                <p class="font-semibold text-slate-500">Aucun poste créé</p>
                <p class="text-xs max-w-xs">Cliquez sur <strong>Nouveau poste</strong> pour définir votre première caisse.</p>
                <button @click="openCreate()"
                        class="mt-1 px-4 py-2 rounded-xl bg-cb-primary text-white text-xs font-semibold inline-flex items-center gap-2 hover:bg-cb-dark transition">
                  <i data-lucide="plus" class="w-3.5 h-3.5"></i> Créer le premier poste
                </button>
              </div>
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($caisses as $c):
            $tc        = $colors[$c['type']] ?? $colors['agence'];
            $icon      = $typeIcons[$c['type']] ?? 'wallet';
            $sessBadge = (int)$c['sessions_ouvertes'];
            $txCount   = (int)$c['tx_count'];
            $dataSearch= htmlspecialchars(strtolower($c['name'] . ' ' . $c['code'] . ' ' . $c['agency_name']), ENT_QUOTES);
          ?>
          <tr class="hover:bg-slate-50/70 transition group"
              x-show="matchesFilter('<?= $dataSearch ?>', '<?= e($c['type']) ?>', <?= $c['is_active'] ? 'true' : 'false' ?>)">
            <!-- Nom -->
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl <?= $tc['bg'] ?> flex items-center justify-center shrink-0">
                  <i data-lucide="<?= $icon ?>" class="w-3.5 h-3.5 <?= $tc['text'] ?>"></i>
                </div>
                <div>
                  <p class="font-semibold text-slate-800 leading-tight"><?= e($c['name']) ?></p>
                  <?php if (!empty($c['description'])): ?>
                    <p class="text-[11px] text-slate-400 truncate max-w-[180px]"><?= e($c['description']) ?></p>
                  <?php endif ?>
                </div>
              </div>
            </td>
            <!-- Code -->
            <td class="px-5 py-3.5">
              <span class="font-mono text-xs font-semibold <?= $tc['text'] ?> <?= $tc['bg'] ?> px-2 py-1 rounded-lg">
                <?= e(strtoupper($c['code'])) ?>
              </span>
            </td>
            <!-- Agence -->
            <td class="px-5 py-3.5 text-slate-500 text-xs"><?= e($c['agency_name']) ?></td>
            <!-- Type -->
            <td class="px-5 py-3.5">
              <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $tc['text'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $tc['dot'] ?>"></span>
                <?= e($types[$c['type']] ?? $c['type']) ?>
              </span>
            </td>
            <!-- Sessions -->
            <td class="px-5 py-3.5 text-center">
              <?php if ($sessBadge > 0): ?>
                <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span><?= $sessBadge ?>
                </span>
              <?php else: ?>
                <span class="text-slate-300 text-xs">—</span>
              <?php endif ?>
            </td>
            <!-- Transactions -->
            <td class="px-5 py-3.5 text-center">
              <span class="text-xs <?= $txCount > 0 ? 'font-semibold text-slate-700' : 'text-slate-300' ?>">
                <?= $txCount > 0 ? number_format($txCount) : '—' ?>
              </span>
            </td>
            <!-- Statut -->
            <td class="px-5 py-3.5">
              <?php if ($c['is_active']): ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Actif
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">
                  <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactif
                </span>
              <?php endif ?>
            </td>
            <!-- Ordre -->
            <td class="px-5 py-3.5 text-right">
              <span class="text-xs text-slate-400 font-mono"><?= (int)$c['sort_order'] ?></span>
            </td>
            <!-- Actions -->
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-1 justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click="openEdit(<?= htmlspecialchars(json_encode([
                  'id'          => (int)$c['id'],
                  'name'        => $c['name'],
                  'code'        => $c['code'],
                  'agency_id'   => (string)(int)$c['agency_id'],
                  'type'        => $c['type'],
                  'description' => $c['description'] ?? '',
                  'sort_order'  => (int)$c['sort_order'],
                  'is_active'   => (bool)$c['is_active'],
                ]), ENT_QUOTES) ?>)"
                        class="p-1.5 rounded-lg hover:bg-cb-bg text-slate-400 hover:text-cb-primary transition" title="Modifier">
                  <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </button>
                <form method="post" action="<?= e(url('finance/caisses/' . $c['id'] . '/toggle')) ?>" class="inline">
                  <?= csrf_field() ?>
                  <button type="submit" title="<?= $c['is_active'] ? 'Désactiver' : 'Activer' ?>"
                          class="p-1.5 rounded-lg transition text-slate-400 <?= $c['is_active'] ? 'hover:bg-amber-50 hover:text-amber-600' : 'hover:bg-emerald-50 hover:text-emerald-600' ?>">
                    <i data-lucide="<?= $c['is_active'] ? 'toggle-right' : 'toggle-left' ?>" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
                <button @click="confirmDelete(<?= (int)$c['id'] ?>, '<?= e(addslashes($c['name'])) ?>', <?= $sessBadge ?>)"
                        class="p-1.5 rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-500 transition" title="Supprimer">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
          <?php endif ?>
        </tbody>
      </table>
    </div>

    <!-- Empty filtered state -->
    <?php if ($caisses): ?>
    <div x-show="visibleCount === 0 && (search || filterType || filterStatus)" x-cloak
         class="py-12 text-center text-slate-400 border-t border-slate-50">
      <i data-lucide="search-x" class="w-7 h-7 mx-auto mb-2 opacity-30"></i>
      <p class="text-sm">Aucun poste ne correspond à votre recherche.</p>
      <button @click="search=''; filterType=''; filterStatus=''"
              class="mt-2 text-xs text-cb-primary hover:underline">Réinitialiser les filtres</button>
    </div>
    <?php endif ?>
  </div>


  <!-- ════════════════════════════════════════
       MODAL : Créer / Modifier un poste
  ════════════════════════════════════════ -->
  <div x-show="showModal" x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center p-4"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal()"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.stop>

      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white z-10">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-cb-bg flex items-center justify-center">
            <i data-lucide="wallet" class="w-4 h-4 text-cb-primary"></i>
          </div>
          <div>
            <h2 class="font-bold text-slate-900 text-base" x-text="isEdit ? 'Modifier le poste' : 'Nouveau poste de caisse'"></h2>
            <p class="text-xs text-slate-400" x-text="isEdit ? form.name : 'Définir un nouveau poste'"></p>
          </div>
        </div>
        <button @click="closeModal()" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 transition">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <!-- Form -->
      <form method="post" :action="formAction" class="p-6 space-y-6">
        <?= csrf_field() ?>
        <template x-if="isEdit">
          <input type="hidden" name="_method" value="PUT">
        </template>

        <!-- Identité -->
        <div class="space-y-4">
          <h3 class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest flex items-center gap-2">
            <span class="flex-1 h-px bg-slate-100"></span> Identité <span class="flex-1 h-px bg-slate-100"></span>
          </h3>

          <div>
            <label class="text-xs font-semibold text-slate-600 block mb-1.5">Nom <span class="text-rose-500">*</span></label>
            <input type="text" name="name" required maxlength="120"
                   x-model="form.name"
                   @input="!isEdit && (form.code = slugify(form.name))"
                   placeholder="Ex : Caisse principale, Guichet 2…"
                   class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm">
          </div>

          <div>
            <label class="text-xs font-semibold text-slate-600 block mb-1.5">
              Code technique <span class="text-rose-500">*</span>
              <span class="font-normal text-slate-400 ml-1">minuscules, chiffres, - _</span>
            </label>
            <div class="relative">
              <input type="text" name="code" required maxlength="40"
                     x-model="form.code"
                     :readonly="isEdit"
                     pattern="[a-z0-9_\-]+"
                     placeholder="ex : caisse_principale"
                     :class="isEdit ? 'bg-slate-50 text-slate-500 cursor-not-allowed pr-9' : ''"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm font-mono">
              <template x-if="isEdit">
                <span class="absolute right-3 top-1/2 -translate-y-1/2">
                  <i data-lucide="lock" class="w-3.5 h-3.5 text-slate-400"></i>
                </span>
              </template>
            </div>
            <p x-show="isEdit" class="text-[11px] text-amber-600 mt-1">Le code est verrouillé après création.</p>
          </div>

          <div>
            <label class="text-xs font-semibold text-slate-600 block mb-1.5">Agence <span class="text-rose-500">*</span></label>
            <select name="agency_id" required x-model="form.agency_id"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary focus:ring-2 focus:ring-cb-primary/20 outline-none text-sm bg-white">
              <option value="">-- Sélectionner --</option>
              <?php foreach ($agencies as $ag): ?>
                <option value="<?= $ag['id'] ?>"><?= e($ag['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>

          <div>
            <label class="text-xs font-semibold text-slate-600 block mb-1.5">Description <span class="text-slate-400 font-normal">(optionnel)</span></label>
            <textarea name="description" rows="2" maxlength="300"
                      x-model="form.description"
                      placeholder="Emplacement, usage particulier…"
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm resize-none"></textarea>
          </div>
        </div>

        <!-- Type -->
        <div class="space-y-3">
          <h3 class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest flex items-center gap-2">
            <span class="flex-1 h-px bg-slate-100"></span> Type <span class="flex-1 h-px bg-slate-100"></span>
          </h3>
          <div class="grid grid-cols-2 gap-2">
            <?php foreach ($types as $key => $label):
              $icon = $typeIcons[$key] ?? 'wallet';
              $tc   = $colors[$key] ?? $colors['agence'];
            ?>
            <label class="cursor-pointer">
              <input type="radio" name="type" value="<?= $key ?>"
                     x-model="form.type" class="sr-only peer">
              <div class="flex items-center gap-2.5 p-3 rounded-xl border-2 border-slate-100
                          peer-checked:border-cb-primary peer-checked:bg-cb-bg
                          hover:border-slate-200 transition">
                <div class="w-7 h-7 rounded-lg <?= $tc['bg'] ?> flex items-center justify-center shrink-0">
                  <i data-lucide="<?= $icon ?>" class="w-3.5 h-3.5 <?= $tc['text'] ?>"></i>
                </div>
                <span class="text-sm font-semibold text-slate-700"><?= e($label) ?></span>
              </div>
            </label>
            <?php endforeach ?>
          </div>
        </div>

        <!-- Options -->
        <div class="space-y-3">
          <h3 class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest flex items-center gap-2">
            <span class="flex-1 h-px bg-slate-100"></span> Options <span class="flex-1 h-px bg-slate-100"></span>
          </h3>
          <div class="flex items-center gap-4">
            <div class="flex-1">
              <label class="text-xs font-semibold text-slate-600 block mb-1.5">Ordre d'affichage</label>
              <input type="number" name="sort_order" min="0" max="9999"
                     x-model="form.sort_order"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm">
              <p class="text-[11px] text-slate-400 mt-1">Les petites valeurs apparaissent en premier.</p>
            </div>
            <div class="mt-4">
              <label class="flex items-center gap-3 cursor-pointer select-none">
                <div class="relative">
                  <input type="checkbox" name="is_active" value="1"
                         x-model="form.is_active"
                         class="sr-only peer">
                  <div class="w-11 h-6 rounded-full bg-slate-200 peer-checked:bg-cb-primary transition-colors
                               relative after:absolute after:top-0.5 after:left-0.5
                               after:w-5 after:h-5 after:rounded-full after:bg-white after:shadow
                               after:transition-transform peer-checked:after:translate-x-5"></div>
                </div>
                <div>
                  <p class="text-sm font-semibold text-slate-700">Caisse active</p>
                  <p class="text-[11px] text-slate-400">Visible pour les caissiers</p>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 pt-2 border-t border-slate-100">
          <button type="button" @click="closeModal()"
                  class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
            Annuler
          </button>
          <button type="submit"
                  class="flex-1 px-4 py-2.5 rounded-xl bg-cb-primary text-white text-sm font-bold hover:bg-cb-dark transition flex items-center justify-center gap-2">
            <i data-lucide="save" class="w-4 h-4"></i>
            <span x-text="isEdit ? 'Enregistrer' : 'Créer le poste'"></span>
          </button>
        </div>
      </form>
    </div>
  </div>


  <!-- ════════════════════════════════════════
       MODAL : Confirmer suppression
  ════════════════════════════════════════ -->
  <div x-show="showDeleteModal" x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center p-4"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteModal=false"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         @click.stop>
      <div class="flex flex-col items-center text-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 flex items-center justify-center">
          <i data-lucide="trash-2" class="w-6 h-6 text-rose-500"></i>
        </div>
        <div>
          <h3 class="font-bold text-slate-900 text-lg">Supprimer ce poste ?</h3>
          <p class="text-sm text-slate-500 mt-1.5">
            Le poste <strong class="text-slate-800" x-text="'« ' + (deleteTarget?.name ?? '') + ' »'"></strong> sera définitivement supprimé. Cette action est irréversible.
          </p>
          <div x-show="deleteTarget?.sessions > 0"
               class="text-xs text-amber-700 mt-3 flex items-center justify-center gap-1.5 bg-amber-50 rounded-xl px-4 py-2.5 border border-amber-200">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0"></i>
            <span x-text="deleteTarget?.sessions + ' session(s) ouverte(s) — la suppression sera bloquée par le serveur.'"></span>
          </div>
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button @click="showDeleteModal=false"
                class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50 transition">
          Annuler
        </button>
        <form method="post" :action="'<?= e(url('finance/caisses')) ?>/' + (deleteTarget?.id ?? '') + '/delete'" class="flex-1">
          <?= csrf_field() ?>
          <button type="submit"
                  class="w-full px-4 py-2.5 rounded-xl bg-rose-600 text-white text-sm font-bold hover:bg-rose-700 transition flex items-center justify-center gap-2">
            <i data-lucide="trash-2" class="w-4 h-4"></i> Supprimer
          </button>
        </form>
      </div>
    </div>
  </div>

</div><!-- /x-data -->

<script>
function caisseManager(caisses, baseUrl) {
  return {
    caisses,
    baseUrl,
    showModal:       false,
    showDeleteModal: false,
    isEdit:          false,
    deleteTarget:    null,
    search:          '',
    filterType:      '',
    filterStatus:    '',

    form: {
      id: null, name: '', code: '', agency_id: '',
      type: 'agence', description: '', sort_order: 100, is_active: true,
    },

    get formAction() {
      return this.isEdit
        ? `${this.baseUrl}/${this.form.id}`
        : this.baseUrl;
    },

    get visibleCount() {
      return [...document.querySelectorAll('#caissesTableBody tr[x-show]')]
        .filter(r => r.style.display !== 'none').length;
    },

    openCreate() {
      this.isEdit = false;
      this.form = { id: null, name: '', code: '', agency_id: '', type: 'agence', description: '', sort_order: 100, is_active: true };
      this.showModal = true;
    },

    openEdit(caisse) {
      this.isEdit = true;
      this.form   = { ...caisse };
      this.showModal = true;
    },

    confirmDelete(id, name, sessions) {
      this.deleteTarget = { id, name, sessions };
      this.showDeleteModal = true;
    },

    closeModal() {
      this.showModal = false;
    },

    matchesFilter(searchStr, type, isActive) {
      const q = this.search.toLowerCase().trim();
      if (q && !searchStr.toLowerCase().includes(q)) return false;
      if (this.filterType   && type !== this.filterType) return false;
      if (this.filterStatus === 'active'   && !isActive) return false;
      if (this.filterStatus === 'inactive' &&  isActive) return false;
      return true;
    },

    slugify(str) {
      return str.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9\s_-]/g, '')
        .trim().replace(/\s+/g, '_');
    },
  };
}
</script>
<?php $view->end() ?>
