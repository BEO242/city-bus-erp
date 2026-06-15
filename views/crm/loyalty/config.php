<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
$cfg = $config ?? [];
$isEnabled = (int)($cfg['is_enabled'] ?? 0);
?>
<?php $view->start('content') ?>

<div class="space-y-6 pb-8">

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Programme de fidélité</h1>
      <p class="text-sm text-slate-500 mt-1">Configurez les conditions du programme et inscrivez les clients fidèles.</p>
    </div>
    <div class="flex items-center gap-2">
      <?php if ($isEnabled): ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
          <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Programme actif
        </span>
      <?php else: ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">
          <i data-lucide="pause-circle" class="w-3.5 h-3.5"></i> Programme désactivé
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
          <i data-lucide="users" class="w-5 h-5 text-indigo-600"></i>
        </div>
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Membres inscrits</div>
      </div>
      <div class="text-2xl font-bold text-slate-900"><?= number_format((int)($stats['total_members'] ?? 0), 0, ',', ' ') ?></div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
          <i data-lucide="activity" class="w-5 h-5 text-emerald-600"></i>
        </div>
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Membres actifs (30j)</div>
      </div>
      <div class="text-2xl font-bold text-slate-900"><?= number_format((int)($stats['active_members'] ?? 0), 0, ',', ' ') ?></div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
          <i data-lucide="trending-up" class="w-5 h-5 text-amber-600"></i>
        </div>
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Moy. voyages/membre</div>
      </div>
      <div class="text-2xl font-bold text-slate-900"><?= number_format((float)($stats['avg_trips'] ?? 0), 1, ',', ' ') ?></div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center">
          <i data-lucide="banknote" class="w-5 h-5 text-rose-600"></i>
        </div>
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">CA membres</div>
      </div>
      <div class="text-2xl font-bold text-slate-900"><?= number_format((int)($stats['total_spent'] ?? 0), 0, ',', ' ') ?> <span class="text-sm font-normal text-slate-400">FCFA</span></div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Configuration form (1/3) -->
    <div class="lg:col-span-1">
      <form method="POST" action="<?= url('crm/loyalty/config') ?>" class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
        <?= csrf_field() ?>
        <div class="px-6 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="settings" class="w-4 h-4 text-cb-primary"></i>
            Configuration
          </h2>
        </div>

        <div class="p-6 space-y-5">
          <!-- Activer/Désactiver -->
          <label class="flex items-center gap-3 cursor-pointer select-none group">
            <input type="checkbox" name="is_enabled" value="1"
                   <?= $isEnabled ? 'checked' : '' ?>
                   class="w-5 h-5 rounded-md border-slate-300 accent-cb-primary cursor-pointer">
            <div>
              <div class="text-sm font-medium text-slate-800 group-hover:text-cb-primary transition">
                Programme actif
              </div>
              <p class="text-xs text-slate-400">Les clients peuvent bénéficier de réductions.</p>
            </div>
          </label>

          <div class="border-t border-slate-100 pt-4">
            <label class="cb-label">Voyages requis <span class="text-rose-500">*</span></label>
            <input type="number" name="required_trips" min="1" max="100" required
                   value="<?= (int)($cfg['required_trips'] ?? 10) ?>"
                   class="cb-input">
            <p class="text-[11px] text-slate-400 mt-1">Nombre de voyages avant d'obtenir la réduction.</p>
          </div>

          <div>
            <label class="cb-label">Réduction (%) <span class="text-rose-500">*</span></label>
            <input type="number" name="discount_percent" min="0" max="100" step="0.5" required
                   value="<?= number_format((float)($cfg['discount_percent'] ?? 10), 2, '.', '') ?>"
                   class="cb-input">
            <p class="text-[11px] text-slate-400 mt-1">Pourcentage de réduction sur le prochain billet.</p>
          </div>

          <div>
            <label class="cb-label">Période de validité (mois)</label>
            <input type="number" name="period_months" min="0" max="120"
                   value="<?= (int)($cfg['period_months'] ?? 12) ?>"
                   class="cb-input">
            <p class="text-[11px] text-slate-400 mt-1">0 = illimité. Les voyages hors période ne comptent pas.</p>
          </div>

          <div>
            <label class="cb-label">Message d'inscription</label>
            <textarea name="enrollment_message" rows="3"
                      class="cb-input resize-y"
                      placeholder="Message affiché au convoyeur lors de l'inscription..."><?= e($cfg['enrollment_message'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between gap-3">
          <form method="POST" action="<?= url('crm/loyalty/generate-codes') ?>" class="inline">
            <?= csrf_field() ?>
            <button type="submit"
                    class="text-xs text-slate-500 hover:text-cb-primary transition flex items-center gap-1"
                    onclick="return confirm('Générer les codes clients manquants ?')">
              <i data-lucide="hash" class="w-3.5 h-3.5"></i> Générer codes manquants
            </button>
          </form>
          <button type="submit"
                  class="px-5 py-2 rounded-xl bg-cb-primary text-white text-sm font-semibold hover:bg-cb-dark transition flex items-center gap-2">
            <i data-lucide="save" class="w-4 h-4"></i>
            Enregistrer
          </button>
        </div>
      </form>
    </div>

    <!-- Right: Lists (2/3) -->
    <div class="lg:col-span-2 space-y-6">

      <!-- Clients éligibles non inscrits -->
      <?php if (!empty($eligibleNotEnrolled)): ?>
      <div class="bg-white rounded-2xl border border-amber-200 shadow-soft overflow-hidden">
        <div class="px-6 py-4 border-b border-amber-100 bg-amber-50">
          <h2 class="text-sm font-semibold text-amber-800 flex items-center gap-2">
            <i data-lucide="user-plus" class="w-4 h-4 text-amber-600"></i>
            Clients éligibles non inscrits
            <span class="text-xs font-normal text-amber-600">(<?= count($eligibleNotEnrolled) ?>)</span>
          </h2>
          <p class="text-xs text-amber-600 mt-0.5">Ces clients ont atteint le seuil de voyages requis mais ne sont pas encore membres.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wide">
              <tr>
                <th class="px-4 py-2.5 text-left">Client</th>
                <th class="px-4 py-2.5 text-left">Téléphone</th>
                <th class="px-4 py-2.5 text-right">Voyages</th>
                <th class="px-4 py-2.5 text-right">Dépenses</th>
                <th class="px-4 py-2.5 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php foreach ($eligibleNotEnrolled as $c): ?>
              <tr class="hover:bg-amber-50/30 transition">
                <td class="px-4 py-2.5 font-medium text-slate-700"><?= e(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?></td>
                <td class="px-4 py-2.5 text-slate-500 font-mono text-xs"><?= e($c['phone_display'] ?? '—') ?></td>
                <td class="px-4 py-2.5 text-right font-bold text-cb-primary"><?= (int)$c['total_trips'] ?></td>
                <td class="px-4 py-2.5 text-right text-slate-600"><?= number_format((int)$c['total_spent'], 0, ',', ' ') ?> F</td>
                <td class="px-4 py-2.5 text-center">
                  <form method="POST" action="<?= url('crm/loyalty/' . $c['id'] . '/enroll') ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200 hover:bg-emerald-100 transition">
                      <i data-lucide="user-plus" class="w-3 h-3"></i> Inscrire
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Dernières inscriptions -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-soft overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="crown" class="w-4 h-4 text-amber-500"></i>
            Membres récents
            <span class="text-xs font-normal text-slate-400">(<?= count($recentEnrollments ?? []) ?>)</span>
          </h2>
        </div>

        <?php if (empty($recentEnrollments)): ?>
          <div class="py-12 text-center">
            <i data-lucide="users" class="w-10 h-10 text-slate-200 mx-auto mb-3"></i>
            <p class="text-sm text-slate-500">Aucun membre inscrit pour le moment.</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                <tr>
                  <th class="px-4 py-2.5 text-left">Code</th>
                  <th class="px-4 py-2.5 text-left">Client</th>
                  <th class="px-4 py-2.5 text-left">Téléphone</th>
                  <th class="px-4 py-2.5 text-right">Voyages</th>
                  <th class="px-4 py-2.5 text-right">Dépenses</th>
                  <th class="px-4 py-2.5 text-left">Inscrit par</th>
                  <th class="px-4 py-2.5 text-left">Date</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <?php foreach ($recentEnrollments as $c): ?>
                <tr class="hover:bg-cb-bg/30 transition">
                  <td class="px-4 py-2.5">
                    <span class="font-mono text-xs font-bold text-cb-primary bg-cb-bg px-2 py-0.5 rounded"><?= e($c['customer_code'] ?? '—') ?></span>
                  </td>
                  <td class="px-4 py-2.5">
                    <a href="<?= url('crm/customers/' . $c['id']) ?>" class="font-medium text-slate-700 hover:text-cb-primary transition">
                      <?= e(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?>
                    </a>
                  </td>
                  <td class="px-4 py-2.5 text-slate-500 font-mono text-xs"><?= e($c['phone_display'] ?? '—') ?></td>
                  <td class="px-4 py-2.5 text-right font-bold text-cb-primary"><?= (int)$c['total_trips'] ?></td>
                  <td class="px-4 py-2.5 text-right text-slate-600"><?= number_format((int)$c['total_spent'], 0, ',', ' ') ?> F</td>
                  <td class="px-4 py-2.5 text-slate-500 text-xs"><?= e($c['enrolled_by_name'] ?? '—') ?></td>
                  <td class="px-4 py-2.5 text-slate-500 text-xs">
                    <?= $c['loyalty_enrolled_at'] ? date('d/m/Y', strtotime($c['loyalty_enrolled_at'])) : '—' ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Info block: How the program works -->
      <div class="bg-indigo-50 rounded-2xl border border-indigo-100 p-5">
        <h3 class="text-sm font-semibold text-indigo-800 flex items-center gap-2 mb-3">
          <i data-lucide="info" class="w-4 h-4 text-indigo-600"></i>
          Fonctionnement du programme
        </h3>
        <div class="text-xs text-indigo-700 space-y-2">
          <p><strong>1.</strong> Le convoyeur propose au client de rejoindre le programme lors de la vente.</p>
          <p><strong>2.</strong> Un code client unique (6 caractères) est généré automatiquement.</p>
          <p><strong>3.</strong> Tous les <strong><?= (int)($cfg['required_trips'] ?? 10) ?></strong> voyages, le client bénéficie de <strong><?= number_format((float)($cfg['discount_percent'] ?? 10), 0) ?>%</strong> de réduction sur son prochain billet.</p>
          <?php if ((int)($cfg['period_months'] ?? 12) > 0): ?>
            <p><strong>4.</strong> Seuls les voyages des <strong><?= (int)($cfg['period_months'] ?? 12) ?></strong> derniers mois sont comptabilisés.</p>
          <?php else: ?>
            <p><strong>4.</strong> Tous les voyages sont comptabilisés (aucune limite de temps).</p>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

</div>

<?php $view->end() ?>
