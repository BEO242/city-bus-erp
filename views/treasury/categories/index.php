<?php /** @var \CityBus\Core\View $view */ $view->extends('layouts/app'); ?>
<?php $view->start('content') ?>
<?php
$colorMap = [
  'slate'=>['bg'=>'bg-slate-100','text'=>'text-slate-700'],'red'=>['bg'=>'bg-red-100','text'=>'text-red-700'],
  'orange'=>['bg'=>'bg-orange-100','text'=>'text-orange-700'],'amber'=>['bg'=>'bg-amber-100','text'=>'text-amber-700'],
  'green'=>['bg'=>'bg-emerald-100','text'=>'text-emerald-700'],'blue'=>['bg'=>'bg-blue-100','text'=>'text-blue-700'],
  'violet'=>['bg'=>'bg-violet-100','text'=>'text-violet-700'],'pink'=>['bg'=>'bg-pink-100','text'=>'text-pink-700'],
];
?>
<div class="space-y-5">

  <div class="flex justify-between flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-bold">Catégories de transactions</h1>
      <p class="text-slate-500 text-sm">Gérez les types d'encaissement et de décaissement.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= e(url('finance/treasury')) ?>"
         class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-medium inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Trésorerie
      </a>
      <?php if (can('finance.treasury.manage')): ?>
      <a href="<?= e(url('finance/treasury/categories/create')) ?>"
         class="px-4 py-2.5 rounded-xl bg-cb-primary text-white font-medium inline-flex items-center gap-2 text-sm hover:bg-cb-dark transition">
        <i data-lucide="plus" class="w-4 h-4"></i> Nouvelle catégorie
      </a>
      <?php endif ?>
    </div>
  </div>

  <!-- Encaissements -->
  <div class="bg-white rounded-2xl border border-slate-100 p-5">
    <h2 class="font-bold text-emerald-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100 mb-3">
      <i data-lucide="arrow-down-circle" class="w-4 h-4"></i> Encaissements
    </h2>
    <div class="space-y-2">
      <?php foreach ($categories as $cat): if ($cat['type'] !== 'encaissement') continue;
        $cls = $colorMap[$cat['color']] ?? $colorMap['slate'];
      ?>
      <div class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 transition <?= !$cat['is_active'] ? 'opacity-50' : '' ?>">
        <div class="flex items-center gap-3 min-w-0">
          <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?= $cls['bg'] ?> <?= $cls['text'] ?>"><?= e($cat['label']) ?></span>
          <span class="text-xs text-slate-400 font-mono"><?= e($cat['code']) ?></span>
          <?php if ($cat['is_system']): ?>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-200 text-slate-500 font-semibold">SYSTÈME</span>
          <?php endif ?>
          <?php if (!$cat['is_active']): ?>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-rose-100 text-rose-600 font-semibold">INACTIF</span>
          <?php endif ?>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <span class="text-xs text-slate-400"><?= (int)$cat['tx_count'] ?> tx</span>
          <?php if (!$cat['is_system'] && can('finance.treasury.manage')): ?>
          <a href="<?= e(url('finance/treasury/categories/' . $cat['id'] . '/edit')) ?>"
             class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-cb-primary" title="Modifier">
            <i data-lucide="pencil" class="w-4 h-4"></i>
          </a>
          <form method="post" action="<?= e(url('finance/treasury/categories/' . $cat['id'] . '/delete')) ?>"
                class="inline" onsubmit="return confirm('Supprimer cette catégorie ?')">
            <?= csrf_field() ?>
            <button type="submit" class="p-1.5 rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-500" title="Supprimer">
              <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
          </form>
          <?php endif ?>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>

  <!-- Décaissements -->
  <div class="bg-white rounded-2xl border border-slate-100 p-5">
    <h2 class="font-bold text-rose-700 text-sm flex items-center gap-2 pb-3 border-b border-slate-100 mb-3">
      <i data-lucide="arrow-up-circle" class="w-4 h-4"></i> Décaissements
    </h2>
    <?php
    // Grouper les décaissements par sous-section logique
    $decGroups = [];
    foreach ($categories as $cat) {
        if ($cat['type'] !== 'decaissement') continue;
        $s = (int)$cat['sort_order'];
        if ($s <= 19)      $g = 'Opérations bancaires';
        elseif ($s <= 29)  $g = 'Exploitation véhicules';
        elseif ($s <= 49)  $g = 'Personnel';
        elseif ($s <= 59)  $g = 'Fonctionnement & admin';
        elseif ($s <= 69)  $g = 'Remboursements';
        else               $g = 'Autre';
        $decGroups[$g][] = $cat;
    }
    ?>
    <div class="space-y-5">
      <?php foreach ($decGroups as $groupLabel => $groupCats): ?>
      <div>
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-2">
          <span class="w-4 h-px bg-slate-200"></span> <?= e($groupLabel) ?>
        </p>
        <div class="space-y-1">
          <?php foreach ($groupCats as $cat):
            $cls = $colorMap[$cat['color']] ?? $colorMap['slate'];
          ?>
          <div class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 transition <?= !$cat['is_active'] ? 'opacity-50' : '' ?>">
            <div class="flex items-center gap-3 min-w-0">
              <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?= $cls['bg'] ?> <?= $cls['text'] ?>"><?= e($cat['label']) ?></span>
              <span class="text-xs text-slate-400 font-mono"><?= e($cat['code']) ?></span>
              <?php if ($cat['is_system']): ?>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-200 text-slate-500 font-semibold">SYSTÈME</span>
              <?php endif ?>
              <?php if (!$cat['is_active']): ?>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-rose-100 text-rose-600 font-semibold">INACTIF</span>
              <?php endif ?>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <span class="text-xs text-slate-400"><?= (int)$cat['tx_count'] ?> tx</span>
              <?php if (!$cat['is_system'] && can('finance.treasury.manage')): ?>
              <a href="<?= e(url('finance/treasury/categories/' . $cat['id'] . '/edit')) ?>"
                 class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-cb-primary" title="Modifier">
                <i data-lucide="pencil" class="w-4 h-4"></i>
              </a>
              <form method="post" action="<?= e(url('finance/treasury/categories/' . $cat['id'] . '/delete')) ?>"
                    class="inline" onsubmit="return confirm('Supprimer cette catégorie ?')">
                <?= csrf_field() ?>
                <button type="submit" class="p-1.5 rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-500" title="Supprimer">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </form>
              <?php endif ?>
            </div>
          </div>
          <?php endforeach ?>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</div>
<?php $view->end() ?>
