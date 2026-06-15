<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Convoyeur;
$view->extends('layouts/app');

$qp = array_filter([
    'q'         => $search       ?? '',
    'status'    => $statusFilter ?? '',
    'agency_id' => $agencyFilter  ? (string)$agencyFilter : '',
    'sort'      => $sortField    ?? 'last_name',
    'dir'       => $sortDir      ?? 'asc',
], fn($v) => $v !== '' && $v !== null);

function cvyPageUrl(array $base, int $p): string {
    return url('referentiel/convoyeurs') . '?' . http_build_query(array_merge($base, ['page' => $p]));
}
function cvySortUrl(array $base, string $field, string $curField, string $curDir): string {
    $dir = ($curField === $field && $curDir === 'asc') ? 'desc' : 'asc';
    return url('referentiel/convoyeurs') . '?' . http_build_query(array_merge($base, ['sort' => $field, 'dir' => $dir, 'page' => 1]));
}

$kpis           = $kpis           ?? [];
$statusCountMap = $statusCountMap ?? [];

$statusChips = [
    'actif'        => ['label' => 'Actifs',    'dot' => 'bg-emerald-500', 'cls' => 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100'],
    'conge'        => ['label' => 'En congé',   'dot' => 'bg-blue-500',    'cls' => 'text-blue-700 bg-blue-50 border-blue-200 hover:bg-blue-100'],
    'en_formation' => ['label' => 'Formation',  'dot' => 'bg-purple-500',  'cls' => 'text-purple-700 bg-purple-50 border-purple-200 hover:bg-purple-100'],
    'suspendu'     => ['label' => 'Suspendus',  'dot' => 'bg-rose-500',    'cls' => 'text-rose-700 bg-rose-50 border-rose-200 hover:bg-rose-100'],
    'quitte'       => ['label' => 'Quittés',    'dot' => 'bg-slate-400',   'cls' => 'text-slate-600 bg-slate-100 border-slate-200 hover:bg-slate-200'],
];
?>
<?php $view->start('content') ?>

<div class="space-y-5">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Convoyeurs</h1>
            <p class="text-sm text-slate-500 mt-0.5"><?= (int)($kpis['total'] ?? 0) ?> convoyeurs enregistrés</p>
        </div>
        <?php if (\CityBus\Core\Auth::can('referentiel.create')): ?>
        <a href="<?= url('referentiel/convoyeurs/create') ?>"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau convoyeur
        </a>
        <?php endif; ?>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border p-4">
            <p class="text-xs text-slate-500 uppercase tracking-wider">Total</p>
            <p class="text-2xl font-bold text-slate-900 mt-1"><?= (int)($kpis['total'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <p class="text-xs text-emerald-600 uppercase tracking-wider">Actifs</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1"><?= (int)($kpis['actif_n'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <p class="text-xs text-amber-600 uppercase tracking-wider">Indisponibles</p>
            <p class="text-2xl font-bold text-amber-700 mt-1"><?= (int)($kpis['indispo_n'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <p class="text-xs text-rose-600 uppercase tracking-wider">Suspendus</p>
            <p class="text-2xl font-bold text-rose-700 mt-1"><?= (int)($kpis['suspendu_n'] ?? 0) ?></p>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" action="<?= url('referentiel/convoyeurs') ?>" class="bg-white rounded-xl border p-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Rechercher..."
                   class="rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <select name="status" class="rounded-lg border-slate-300 text-sm">
                <option value="">Tous les statuts</option>
                <?php foreach (Convoyeur::STATUSES as $val => $label): ?>
                <option value="<?= $val ?>" <?= $statusFilter === $val ? 'selected' : '' ?>><?= $label ?> (<?= $statusCountMap[$val] ?? 0 ?>)</option>
                <?php endforeach; ?>
            </select>
            <select name="agency_id" class="rounded-lg border-slate-300 text-sm">
                <option value="">Toutes les agences</option>
                <?php foreach ($agencies as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $agencyFilter === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-medium text-slate-700 transition">
                Filtrer
            </button>
        </div>
    </form>

    <!-- Status chips -->
    <div class="flex flex-wrap gap-2">
        <a href="<?= url('referentiel/convoyeurs') ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border transition
                  <?= $statusFilter === '' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
            Tous (<?= (int)($kpis['total'] ?? 0) ?>)
        </a>
        <?php foreach ($statusChips as $key => $chip): ?>
        <?php $cnt = $statusCountMap[$key] ?? 0; if ($cnt === 0 && $statusFilter !== $key) continue; ?>
        <a href="<?= url('referentiel/convoyeurs') ?>?status=<?= $key ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border transition <?= $statusFilter === $key ? $chip['cls'] : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
            <span class="w-2 h-2 rounded-full <?= $chip['dot'] ?>"></span>
            <?= $chip['label'] ?> (<?= $cnt ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">
                            <a href="<?= cvySortUrl($qp, 'matricule', $sortField, $sortDir) ?>" class="hover:text-indigo-600">Matricule</a>
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">
                            <a href="<?= cvySortUrl($qp, 'last_name', $sortField, $sortDir) ?>" class="hover:text-indigo-600">Nom</a>
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Telephone</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-600">Agence</th>
                        <th class="px-4 py-3 text-center font-medium text-slate-600">
                            <a href="<?= cvySortUrl($qp, 'status', $sortField, $sortDir) ?>" class="hover:text-indigo-600">Statut</a>
                        </th>
                        <th class="px-4 py-3 text-center font-medium text-slate-600">
                            <a href="<?= cvySortUrl($qp, 'total_trips', $sortField, $sortDir) ?>" class="hover:text-indigo-600">Voyages</a>
                        </th>
                        <th class="px-4 py-3 text-center font-medium text-slate-600">
                            <a href="<?= cvySortUrl($qp, 'rating_score', $sortField, $sortDir) ?>" class="hover:text-indigo-600">Note</a>
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($convoyeurs)): ?>
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">Aucun convoyeur trouvé.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($convoyeurs as $c): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= e($c['matricule']) ?></td>
                        <td class="px-4 py-3">
                            <a href="<?= url('referentiel/convoyeurs/' . $c['id']) ?>" class="font-medium text-slate-900 hover:text-indigo-600">
                                <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
                            </a>
                            <?php if (!empty($c['alerts'])): ?>
                            <span class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full bg-rose-500 text-white text-[10px] font-bold"><?= count($c['alerts']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= e($c['phone'] ?? '') ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= e($c['agency_name'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= Convoyeur::STATUS_COLORS[$c['status']] ?? '' ?>">
                                <?= Convoyeur::STATUSES[$c['status']] ?? $c['status'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-slate-600"><?= (int)$c['total_trips'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-amber-500 font-medium"><?= number_format((float)$c['rating_score'], 1) ?></span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= url('referentiel/convoyeurs/' . $c['id']) ?>" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Voir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($lastPage > 1): ?>
        <div class="px-4 py-3 border-t bg-slate-50 flex items-center justify-between text-xs text-slate-600">
            <span>Page <?= $page ?> / <?= $lastPage ?> (<?= $total ?> résultats)</span>
            <div class="flex gap-1">
                <?php if ($page > 1): ?>
                <a href="<?= cvyPageUrl($qp, $page - 1) ?>" class="px-3 py-1 bg-white border rounded hover:bg-slate-50">Préc.</a>
                <?php endif; ?>
                <?php if ($page < $lastPage): ?>
                <a href="<?= cvyPageUrl($qp, $page + 1) ?>" class="px-3 py-1 bg-white border rounded hover:bg-slate-50">Suiv.</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $view->stop() ?>
