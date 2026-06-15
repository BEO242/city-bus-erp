<?php /** @var \CityBus\Core\View $view */
use CityBus\Models\Convoyeur;
$view->extends('layouts/app');
$isEdit = !empty($convoyeur);
?>
<?php $view->start('content') ?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= url('referentiel/convoyeurs') ?>" class="p-2 rounded-lg hover:bg-slate-100 transition">
            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-2xl font-bold text-slate-900"><?= $isEdit ? 'Modifier le convoyeur' : 'Nouveau convoyeur' ?></h1>
    </div>

    <form method="POST" action="<?= $isEdit ? url('referentiel/convoyeurs/' . $convoyeur['id']) : url('referentiel/convoyeurs') ?>"
          class="space-y-6">
        <?= csrf() ?>

        <!-- Identité -->
        <div class="bg-white rounded-xl border p-6 space-y-4">
            <h2 class="text-lg font-semibold text-slate-800 border-b pb-2">Identité</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Matricule</label>
                    <input type="text" name="matricule" value="<?= e($convoyeur['matricule'] ?? '') ?>"
                           placeholder="Auto-généré si vide"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Prénom <span class="text-rose-500">*</span></label>
                    <input type="text" name="first_name" value="<?= e($convoyeur['first_name'] ?? '') ?>" required
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nom <span class="text-rose-500">*</span></label>
                    <input type="text" name="last_name" value="<?= e($convoyeur['last_name'] ?? '') ?>" required
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date de naissance</label>
                    <input type="date" name="birth_date" value="<?= e($convoyeur['birth_date'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Genre</label>
                    <select name="gender" class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">—</option>
                        <option value="M" <?= ($convoyeur['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Masculin</option>
                        <option value="F" <?= ($convoyeur['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Féminin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">N° CNI</label>
                    <input type="text" name="national_id" value="<?= e($convoyeur['national_id'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Expiration CNI</label>
                    <input type="date" name="national_id_expiry" value="<?= e($convoyeur['national_id_expiry'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>

        <!-- Contact -->
        <div class="bg-white rounded-xl border p-6 space-y-4">
            <h2 class="text-lg font-semibold text-slate-800 border-b pb-2">Contact</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Telephone <span class="text-rose-500">*</span></label>
                    <input type="text" name="phone" value="<?= e($convoyeur['phone'] ?? '') ?>" required
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Telephone secondaire</label>
                    <input type="text" name="phone_alt" value="<?= e($convoyeur['phone_alt'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= e($convoyeur['email'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Adresse</label>
                    <input type="text" name="address" value="<?= e($convoyeur['address'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ville</label>
                    <input type="text" name="city" value="<?= e($convoyeur['city'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>

        <!-- Urgence -->
        <div class="bg-white rounded-xl border p-6 space-y-4">
            <h2 class="text-lg font-semibold text-slate-800 border-b pb-2">Contact d'urgence</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
                    <input type="text" name="emergency_name" value="<?= e($convoyeur['emergency_name'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Telephone</label>
                    <input type="text" name="emergency_phone" value="<?= e($convoyeur['emergency_phone'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Lien de parenté</label>
                    <input type="text" name="emergency_relation" value="<?= e($convoyeur['emergency_relation'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>

        <!-- Emploi -->
        <div class="bg-white rounded-xl border p-6 space-y-4">
            <h2 class="text-lg font-semibold text-slate-800 border-b pb-2">Emploi</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date d'embauche <span class="text-rose-500">*</span></label>
                    <input type="date" name="hire_date" value="<?= e($convoyeur['hire_date'] ?? '') ?>" required
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Agence</label>
                    <select name="agency_id" class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="0">— Aucune —</option>
                        <?php foreach ($agencies as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= (int)($convoyeur['agency_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Statut <span class="text-rose-500">*</span></label>
                    <select name="status" required class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <?php foreach (Convoyeur::STATUSES as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($convoyeur['status'] ?? 'actif') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Rémunération -->
        <div class="bg-white rounded-xl border p-6 space-y-4">
            <h2 class="text-lg font-semibold text-slate-800 border-b pb-2">Rémunération</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Salaire de base (FCFA)</label>
                    <input type="number" name="salary_base" value="<?= (int)($convoyeur['salary_base'] ?? 0) ?>" min="0"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Prime journalière (FCFA)</label>
                    <input type="number" name="daily_bonus" value="<?= (int)($convoyeur['daily_bonus'] ?? 0) ?>" min="0"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Banque</label>
                    <input type="text" name="bank_name" value="<?= e($convoyeur['bank_name'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">N° compte</label>
                    <input type="text" name="bank_account" value="<?= e($convoyeur['bank_account'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mobile money</label>
                    <input type="text" name="mobile_money_number" value="<?= e($convoyeur['mobile_money_number'] ?? '') ?>"
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="bg-white rounded-xl border p-6 space-y-4">
            <h2 class="text-lg font-semibold text-slate-800 border-b pb-2">Notes internes</h2>
            <textarea name="notes" rows="3" class="w-full rounded-lg border-slate-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="Notes libres..."><?= e($convoyeur['notes'] ?? '') ?></textarea>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <a href="<?= url('referentiel/convoyeurs') ?>" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Annuler</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition shadow-sm">
                <?= $isEdit ? 'Enregistrer' : 'Créer le convoyeur' ?>
            </button>
        </div>
    </form>
</div>

<?php $view->stop() ?>
