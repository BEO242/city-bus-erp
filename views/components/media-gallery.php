<?php
/**
 * Composant réutilisable — Galerie d'images avec MediaManager.
 *
 * Variables requises :
 *   $mediableType  string  Ex : 'buses'
 *   $mediableId    int     ID du modèle associé
 *   $collection    string  Toujours 'gallery' pour ce composant
 *   $galleryItems  array   Médias pré-chargés (depuis MediaService::enrichAll())
 *
 * Variables optionnelles :
 *   $cropEnabled   bool    Activer le recadrage avant upload (défaut : true)
 *   $maxFiles      int     Maximum de photos (défaut : 20)
 *   $label         string  Titre de la section (défaut : 'Galerie photos')
 */
$mediableType = $mediableType ?? 'unknown';
$mediableId   = (int)($mediableId   ?? 0);
$collection   = 'gallery';
$galleryItems = $galleryItems ?? [];
$cropEnabled  = $cropEnabled  ?? true;
$maxFiles     = $maxFiles     ?? 20;
$label        = $label        ?? 'Galerie photos';
$componentId  = 'gallery-' . $mediableType . '-' . $mediableId;
?>
<div class="space-y-3">
  <div class="flex items-center justify-between">
    <h3 class="font-semibold text-slate-800 flex items-center gap-2">
      <i data-lucide="images" class="w-4 h-4 text-cb-primary"></i>
      <?= e($label) ?>
      <?php if (!empty($galleryItems)): ?>
        <span class="text-xs font-normal text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full"><?= count($galleryItems) ?> photo<?= count($galleryItems) > 1 ? 's' : '' ?></span>
      <?php endif ?>
    </h3>
    <?php if (!empty($galleryItems)): ?>
      <span class="text-xs text-slate-400">Glissez les images pour réordonner · 1ère = couverture</span>
    <?php endif ?>
  </div>

  <div id="<?= e($componentId) ?>"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  new MediaManager({
    container:    '#<?= e($componentId) ?>',
    mediableType: '<?= e($mediableType) ?>',
    mediableId:   <?= (int)$mediableId ?>,
    collection:   'gallery',
    csrf:         document.querySelector('meta[name="csrf-token"]')?.content || '',
    cropEnabled:  <?= $cropEnabled ? 'true' : 'false' ?>,
    maxFiles:     <?= (int)$maxFiles ?>,
    initialItems: <?= json_encode(array_values($galleryItems), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  });
});
</script>
