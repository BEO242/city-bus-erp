<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/plain');
?>
<?php $view->start('content') ?>
<div class="min-h-screen bg-gradient-to-br from-cb-bg to-white p-8">
  <div class="max-w-xl mx-auto bg-white rounded-2xl shadow-soft p-8">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-bold text-slate-900">Notez votre voyage</h1>
      <p class="text-slate-500 mt-2">Voyage <strong><?= e($feedback['trip_code'] ?? '') ?></strong>
        <?php if ($feedback['line_name']): ?> · <?= e($feedback['line_name']) ?><?php endif ?></p>
    </div>

    <?php if ($feedback['submitted_at'] && $feedback['nps_score'] !== null): ?>
      <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-center">
        <p class="font-semibold text-emerald-900">✓ Vous avez déjà soumis votre avis. Merci !</p>
      </div>
    <?php else: ?>
      <form method="post" action="<?= e(url('feedback/' . $token)) ?>" class="space-y-5">
        <?= csrf_field() ?>

        <div>
          <label class="block text-sm font-medium mb-2">Recommanderiez-vous CITY BUS ? (0 = jamais, 10 = absolument)</label>
          <div class="grid grid-cols-11 gap-1">
            <?php for ($n = 0; $n <= 10; $n++): ?>
              <label class="flex items-center justify-center cursor-pointer rounded-lg border border-slate-200 py-2 hover:bg-cb-bg/40 has-[:checked]:bg-cb-primary has-[:checked]:text-white has-[:checked]:border-cb-primary">
                <input type="radio" name="nps_score" value="<?= $n ?>" class="hidden" required>
                <?= $n ?>
              </label>
            <?php endfor ?>
          </div>
        </div>

        <?php $criteria = [
          'rating_overall'      => 'Note globale',
          'rating_punctuality'  => 'Ponctualité',
          'rating_comfort'      => 'Confort',
          'rating_driver'       => 'Chauffeur',
          'rating_cleanliness'  => 'Propreté',
        ]; ?>
        <?php foreach ($criteria as $k => $lbl): ?>
          <div>
            <label class="block text-sm font-medium mb-1"><?= e($lbl) ?></label>
            <div class="flex gap-2">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <label class="cursor-pointer flex-1 text-center rounded-lg border border-slate-200 py-2 hover:bg-cb-bg/40 has-[:checked]:bg-amber-100 has-[:checked]:border-amber-400">
                  <input type="radio" name="<?= $k ?>" value="<?= $s ?>" class="hidden">
                  <?= str_repeat('⭐', $s) ?>
                </label>
              <?php endfor ?>
            </div>
          </div>
        <?php endforeach ?>

        <div>
          <label class="block text-sm font-medium mb-1">Commentaire (optionnel)</label>
          <textarea name="comment" rows="3" placeholder="Dites-nous tout..." class="w-full px-3 py-2 rounded-xl border border-slate-200"></textarea>
        </div>

        <button class="w-full px-4 py-3 rounded-xl bg-cb-primary text-white font-semibold hover:bg-cb-secondary">
          Envoyer mon avis
        </button>
      </form>
    <?php endif ?>
  </div>
</div>
<?php $view->end() ?>
