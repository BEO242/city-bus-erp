<?php
/**
 * Composant réutilisable — Zone de dépôt pour documents (PDF, DOC, XLS).
 *
 * Variables requises :
 *   $mediableType   string  Ex : 'buses'
 *   $mediableId     int     ID du modèle associé
 *   $docItems       array   Médias pré-chargés (depuis MediaService::enrichAll())
 *
 * Variables optionnelles :
 *   $maxFiles       int     Maximum de documents (défaut : 30)
 *   $label          string  Titre (défaut : 'Documents & pièces jointes')
 */
$mediableType = $mediableType ?? 'unknown';
$mediableId   = (int)($mediableId   ?? 0);
$docItems     = $docItems     ?? [];
$maxFiles     = $maxFiles     ?? 30;
$label        = $label        ?? 'Documents & pièces jointes';
$componentId  = 'docs-' . $mediableType . '-' . $mediableId;
?>
<div class="space-y-3">
  <div class="flex items-center justify-between">
    <h3 class="font-semibold text-slate-800 flex items-center gap-2">
      <i data-lucide="paperclip" class="w-4 h-4 text-cb-primary"></i>
      <?= e($label) ?>
      <?php if (!empty($docItems)): ?>
        <span class="text-xs font-normal text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full"><?= count($docItems) ?> fichier<?= count($docItems) > 1 ? 's' : '' ?></span>
      <?php endif ?>
    </h3>
  </div>

  <div id="<?= e($componentId) ?>"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  new MediaManager({
    container:    '#<?= e($componentId) ?>',
    mediableType: '<?= e($mediableType) ?>',
    mediableId:   <?= (int)$mediableId ?>,
    collection:   'documents',
    csrf:         document.querySelector('meta[name="csrf-token"]')?.content || '',
    cropEnabled:  false,
    maxFiles:     <?= (int)$maxFiles ?>,
    initialItems: <?= json_encode(array_values($docItems), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  });
});
</script>
