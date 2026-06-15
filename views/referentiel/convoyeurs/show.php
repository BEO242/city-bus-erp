<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Convoyeur;
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="<?= url('referentiel/convoyeurs') ?>" class="p-2 rounded-lg hover:bg-slate-100 transition">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><?= e(Convoyeur::fullName($convoyeur)) ?></h1>
                <p class="text-sm text-slate-500"><?= e($convoyeur['matricule']) ?> · <?= e($convoyeur['agency_name'] ?? '—') ?></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium <?= Convoyeur::STATUS_COLORS[$convoyeur['status']] ?? '' ?>">
                <?= Convoyeur::STATUSES[$convoyeur['status']] ?? $convoyeur['status'] ?>
            </span>
            <?php if (\CityBus\Core\Auth::can('referentiel.edit')): ?>
            <a href="<?= url('referentiel/convoyeurs/' . $convoyeur['id'] . '/edit') ?>"
               class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Modifier
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alertes -->
    <?php if (!empty($alerts)): ?>
    <div class="space-y-2">
        <?php foreach ($alerts as $alert): ?>
        <div class="px-4 py-2 rounded-lg border text-sm
            <?= $alert['level'] === 'danger' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-amber-50 border-amber-200 text-amber-700' ?>">
            <?= e($alert['msg']) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Colonne gauche : infos -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Identité -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Identité</h3>
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Prénom</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['first_name']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Nom</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['last_name']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Genre</dt>
                        <dd class="font-medium text-slate-900"><?= ($convoyeur['gender'] ?? '') === 'M' ? 'Masculin' : (($convoyeur['gender'] ?? '') === 'F' ? 'Féminin' : '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Date de naissance</dt>
                        <dd class="font-medium text-slate-900"><?= $convoyeur['birth_date'] ? date('d/m/Y', strtotime($convoyeur['birth_date'])) : '—' ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">N° CNI</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['national_id'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Expiration CNI</dt>
                        <dd class="font-medium text-slate-900"><?= $convoyeur['national_id_expiry'] ? date('d/m/Y', strtotime($convoyeur['national_id_expiry'])) : '—' ?></dd>
                    </div>
                </dl>
            </div>

            <!-- Contact -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Contact</h3>
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Telephone</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['phone']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Telephone 2</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['phone_alt'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Email</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['email'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Adresse</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['address'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Ville</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['city'] ?? '—') ?></dd>
                    </div>
                </dl>
            </div>

            <!-- Contact d'urgence -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Contact d'urgence</h3>
                <dl class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Nom</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['emergency_name'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Telephone</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['emergency_phone'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Lien</dt>
                        <dd class="font-medium text-slate-900"><?= e($convoyeur['emergency_relation'] ?? '—') ?></dd>
                    </div>
                </dl>
            </div>

            <!-- Derniers voyages -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Derniers voyages</h3>
                <?php if (empty($trips)): ?>
                <p class="text-sm text-slate-400">Aucun voyage enregistré.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-600">Date</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-600">Ligne</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-600">Véhicule</th>
                                <th class="px-3 py-2 text-center font-medium text-slate-600">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($trips as $tr): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2"><?= date('d/m/Y', strtotime($tr['trip_date'])) ?></td>
                                <td class="px-3 py-2"><?= e($tr['line_code'] ?? '') ?> — <?= e($tr['line_name'] ?? '') ?></td>
                                <td class="px-3 py-2 font-mono text-xs"><?= e($tr['bus_code'] ?? '') ?></td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600"><?= e($tr['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Colonne droite : stats + emploi + rémunération -->
        <div class="space-y-6">
            <!-- Statistiques -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Statistiques</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600">Voyages total</span>
                        <span class="text-lg font-bold text-slate-900"><?= (int)($stats['total_trips'] ?? $convoyeur['total_trips'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600">30 derniers jours</span>
                        <span class="text-lg font-bold text-indigo-600"><?= (int)($stats['trips_30d'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600">Note</span>
                        <span class="text-lg font-bold text-amber-500"><?= number_format((float)$convoyeur['rating_score'], 1) ?> / 5</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-600">Avertissements</span>
                        <span class="text-lg font-bold <?= (int)$convoyeur['warnings_count'] > 0 ? 'text-rose-600' : 'text-slate-400' ?>"><?= (int)$convoyeur['warnings_count'] ?></span>
                    </div>
                </div>
            </div>

            <!-- Emploi -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Emploi</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Date d'embauche</dt>
                        <dd class="font-medium"><?= $convoyeur['hire_date'] ? date('d/m/Y', strtotime($convoyeur['hire_date'])) : '—' ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Agence</dt>
                        <dd class="font-medium"><?= e($convoyeur['agency_name'] ?? '—') ?></dd>
                    </div>
                </dl>
            </div>

            <!-- Rémunération -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Rémunération</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Salaire base</dt>
                        <dd class="font-medium"><?= number_format((int)($convoyeur['salary_base'] ?? 0), 0, ',', ' ') ?> F</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Prime/jour</dt>
                        <dd class="font-medium"><?= number_format((int)($convoyeur['daily_bonus'] ?? 0), 0, ',', ' ') ?> F</dd>
                    </div>
                    <?php if (!empty($convoyeur['bank_name'])): ?>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Banque</dt>
                        <dd class="font-medium"><?= e($convoyeur['bank_name']) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($convoyeur['mobile_money_number'])): ?>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Mobile money</dt>
                        <dd class="font-medium"><?= e($convoyeur['mobile_money_number']) ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Suppression -->
            <?php if (\CityBus\Core\Auth::can('referentiel.delete')): ?>
            <div class="bg-white rounded-xl border p-6">
                <form method="POST" action="<?= url('referentiel/convoyeurs/' . $convoyeur['id'] . '/delete') ?>"
                      onsubmit="return confirm('Confirmer la suppression de ce convoyeur ?')">
                    <?= csrf() ?>
                    <button type="submit" class="w-full px-4 py-2 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg text-sm font-medium hover:bg-rose-100 transition">
                        Supprimer ce convoyeur
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notes -->
    <?php if (!empty($notes)): ?>
    <div class="bg-white rounded-xl border p-6">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Notes</h3>
        <div class="space-y-3">
            <?php foreach ($notes as $note): ?>
            <div class="p-3 bg-slate-50 rounded-lg text-sm">
                <p class="text-slate-700"><?= nl2br(e($note['content'])) ?></p>
                <p class="text-xs text-slate-400 mt-1"><?= date('d/m/Y H:i', strtotime($note['created_at'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php $view->stop() ?>
