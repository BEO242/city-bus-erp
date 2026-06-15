<?php /**
 * Pagination simple.
 * Variables attendues : $page, $pages, $baseUrl (optionnel — sinon current path)
 */
$baseUrl = $baseUrl ?? strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$qs = $_GET; unset($qs['page']);
$build = function ($p) use ($baseUrl, $qs) {
    $qs['page'] = $p;
    return $baseUrl . '?' . http_build_query($qs);
};
if (($pages ?? 1) < 2) return;
?>
<nav class="flex items-center gap-1 text-sm">
  <a href="<?= e($build(max(1, $page-1))) ?>"
     class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 <?= $page<=1?'pointer-events-none opacity-40':'' ?>">‹</a>
  <?php
    $start = max(1, $page-2); $end = min($pages, $page+2);
    if ($start > 1) echo '<a href="' . e($build(1)) . '" class="px-3 py-1.5 rounded-lg hover:bg-slate-50">1</a>';
    if ($start > 2) echo '<span class="px-2">…</span>';
    for ($i = $start; $i <= $end; $i++):
  ?>
    <a href="<?= e($build($i)) ?>"
       class="px-3 py-1.5 rounded-lg <?= $i==$page?'bg-cb-primary text-white font-semibold':'hover:bg-slate-50' ?>"><?= $i ?></a>
  <?php endfor;
    if ($end < $pages-1) echo '<span class="px-2">…</span>';
    if ($end < $pages) echo '<a href="' . e($build($pages)) . '" class="px-3 py-1.5 rounded-lg hover:bg-slate-50">' . $pages . '</a>';
  ?>
  <a href="<?= e($build(min($pages, $page+1))) ?>"
     class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 <?= $page>=$pages?'pointer-events-none opacity-40':'' ?>">›</a>
</nav>
