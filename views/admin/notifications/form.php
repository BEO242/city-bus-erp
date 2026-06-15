<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$declared = json_decode((string)($template['variables'] ?? '[]'), true) ?: [];
?>
<?php $view->start('content') ?>
<div class="space-y-5">
  <div>
    <a href="<?= e(url('admin/notifications')) ?>" class="text-sm text-slate-500 hover:text-cb-primary inline-flex items-center gap-1">
      <i data-lucide="chevron-left" class="w-4 h-4"></i> Retour
    </a>
    <h1 class="text-2xl font-bold text-slate-900 mt-2"><?= e($title) ?></h1>
    <p class="text-slate-500 text-sm">Clé : <code><?= e($template['template_key']) ?></code> · Canal : <span class="font-semibold"><?= e($template['channel']) ?></span></p>
  </div>

  <form method="post" action="<?= e(url('admin/notifications/' . $template['id'])) ?>" class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4 shadow-soft max-w-3xl">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Libellé *</label>
      <input name="label" required value="<?= e($template['label']) ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <?php if ($template['channel'] === 'email'): ?>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Sujet</label>
      <input name="subject" value="<?= e($template['subject'] ?? '') ?>" class="w-full px-3 py-2.5 rounded-xl border border-slate-200">
    </div>
    <?php endif ?>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Corps *</label>
      <textarea name="body" required rows="<?= $template['channel'] === 'email' ? 12 : 5 ?>" class="w-full px-3 py-2 rounded-xl border border-slate-200 font-mono text-sm"><?= e($template['body']) ?></textarea>
    </div>
    <?php if ($declared): ?>
    <div class="bg-slate-50 rounded-xl p-3">
      <p class="text-xs text-slate-500 mb-2">Variables disponibles :</p>
      <div class="flex flex-wrap gap-1">
        <?php foreach ($declared as $v): ?>
          <code class="text-xs bg-white border border-slate-200 px-2 py-0.5 rounded">{{<?= e($v) ?>}}</code>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" <?= $template['is_active'] ? 'checked' : '' ?>> <span class="text-sm">Template actif</span></label>
    <div class="flex justify-end gap-2">
      <a href="<?= e(url('admin/notifications')) ?>" class="px-5 py-2.5 rounded-xl border border-slate-200">Annuler</a>
      <button class="px-5 py-2.5 rounded-xl bg-cb-primary text-white">Enregistrer (v<?= (int)$template['version'] + 1 ?>)</button>
    </div>
  </form>
</div>
<?php $view->end() ?>
