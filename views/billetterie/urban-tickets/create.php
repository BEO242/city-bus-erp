<?php
/** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div class="max-w-4xl mx-auto space-y-5"
     x-data="urbanTicketForm()"
     x-init="$nextTick(() => lucide.createIcons())">

  <!-- En-tête -->
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="plus-circle" class="w-6 h-6 text-cb-primary"></i> Nouvelle série de tickets urbains
      </h1>
      <p class="text-slate-500 text-sm mt-0.5">Configurez les paramètres et générez le PDF A4 prêt à imprimer.</p>
    </div>
    <a href="<?= e(url('billetterie/urban-tickets')) ?>"
       class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-semibold inline-flex items-center gap-2 hover:bg-slate-50 transition text-sm">
      <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
    </a>
  </div>

  <form method="post" action="<?= e(url('billetterie/urban-tickets')) ?>" @submit.prevent="submit()">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <div class="grid lg:grid-cols-5 gap-5">

      <!-- Colonne gauche : Formulaire -->
      <div class="lg:col-span-3 space-y-4">

        <!-- Bloc 1 : Paramètres de base -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-4">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2 text-sm">
            <i data-lucide="settings" class="w-4 h-4 text-cb-primary"></i> Paramètres de la série
          </h2>

          <div class="grid sm:grid-cols-2 gap-4">
            <!-- Date -->
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Date d'utilisation *</label>
              <input type="date" name="ticket_date" x-model="form.ticket_date" required
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm focus:ring-2 focus:ring-cb-bg">
            </div>

            <!-- Prix -->
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Prix unitaire FCFA *</label>
              <input type="number" name="price_fcfa" x-model.number="form.price_fcfa" min="1" step="1" required
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right focus:ring-2 focus:ring-cb-bg">
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <!-- Bus -->
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Bus affecté *</label>
              <select name="bus_code" x-model="form.bus_code" required
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm bg-white focus:ring-2 focus:ring-cb-bg">
                <option value="">-- Choisir --</option>
                <?php foreach ($buses as $b): ?>
                <option value="<?= e($b['code']) ?>"><?= e($b['code']) ?> (<?= e($b['plate']) ?>)</option>
                <?php endforeach ?>
              </select>
            </div>

            <!-- Réseau -->
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Libellé réseau</label>
              <input type="text" name="network_label" x-model="form.network_label" maxlength="100"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm focus:ring-2 focus:ring-cb-bg">
            </div>
          </div>

          <!-- Route -->
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Arrêt de départ *</label>
              <input type="text" name="departure" x-model="form.departure" maxlength="100" required placeholder="Ex. Ngagaligolo"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm focus:ring-2 focus:ring-cb-bg">
            </div>
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Arrêt terminus *</label>
              <input type="text" name="arrival" x-model="form.arrival" maxlength="100" required placeholder="Ex. Kintélé"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm focus:ring-2 focus:ring-cb-bg">
            </div>
          </div>
        </div>

        <!-- Bloc 2 : Symbole secret -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-4">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2 text-sm">
            <i data-lucide="shield" class="w-4 h-4 text-cb-primary"></i> Symbole secret anti-fraude
          </h2>
          <p class="text-xs text-slate-400">Ce symbole sera imprimé sur chaque ticket. Seul l'équipage du jour le connaît.</p>
          <input type="hidden" name="symbol_id" :value="form.symbol_id">
          <div class="grid grid-cols-5 sm:grid-cols-8 md:grid-cols-10 gap-2">
            <?php foreach ($symbols as $sym): ?>
            <button type="button"
                    @click="form.symbol_id = <?= (int)$sym['id'] ?>; form.symbol_char = '<?= e($sym['symbol']) ?>'"
                    class="aspect-square rounded-xl border-2 flex items-center justify-center text-2xl transition-all hover:scale-110 cursor-pointer"
                    :class="form.symbol_id == <?= (int)$sym['id'] ?>
                      ? 'border-cb-primary bg-cb-bg shadow-md ring-2 ring-cb-primary/20'
                      : 'border-slate-200 bg-white hover:border-slate-300'"
                    title="<?= e($sym['label']) ?>">
              <?= e($sym['symbol']) ?>
            </button>
            <?php endforeach ?>
          </div>
          <div x-show="!form.symbol_id" class="text-xs text-rose-500 font-medium">
            <i data-lucide="alert-circle" class="w-3 h-3 inline"></i> Choisissez un symbole.
          </div>
        </div>

        <!-- Bloc 3 : Numérotation -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-4">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2 text-sm">
            <i data-lucide="hash" class="w-4 h-4 text-cb-primary"></i> Numérotation & quantité
          </h2>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Premier numéro</label>
              <input type="number" name="num_start" x-model.number="form.num_start" min="1" step="1"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right focus:ring-2 focus:ring-cb-bg">
              <p class="text-[10px] text-slate-400 mt-1">Continu depuis la dernière série</p>
            </div>
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Nombre de tickets *</label>
              <input type="number" name="ticket_count" x-model.number="form.ticket_count"
                     min="1" step="1" required
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 focus:border-cb-primary outline-none text-sm font-mono text-right focus:ring-2 focus:ring-cb-bg">
              <p class="text-[10px] text-slate-400 mt-1">24 tickets = 1 page A4</p>
            </div>
          </div>
          <div class="bg-slate-50 rounded-xl p-3 text-xs text-slate-600 space-y-1">
            <div class="flex justify-between"><span>Plage :</span> <span class="font-mono font-semibold" x-text="numRange"></span></div>
            <div class="flex justify-between"><span>Pages A4 :</span> <span class="font-semibold" x-text="pages"></span></div>
            <div class="flex justify-between"><span>Recette attendue :</span> <span class="font-mono font-bold text-emerald-700" x-text="revenueFormatted"></span></div>
          </div>
        </div>

        <!-- Erreur & Soumission -->
        <div x-show="formError" x-transition
             class="flex items-center gap-2 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3">
          <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
          <span x-text="formError"></span>
        </div>

        <button type="submit" :disabled="submitting || !canSubmit"
                class="w-full py-3 rounded-xl bg-cb-primary text-white font-bold text-sm hover:bg-cb-dark transition shadow-soft disabled:opacity-50 flex items-center justify-center gap-2">
          <svg x-show="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
          <i data-lucide="printer" class="w-4 h-4" x-show="!submitting"></i>
          <span x-text="submitting ? 'Génération du PDF en cours...' : 'Créer la série et générer le PDF'"></span>
        </button>
      </div>

      <!-- Colonne droite : Preview ticket -->
      <div class="lg:col-span-2">
        <div class="sticky top-24 space-y-4">
          <h2 class="font-semibold text-slate-800 flex items-center gap-2 text-sm">
            <i data-lucide="eye" class="w-4 h-4 text-cb-primary"></i> Aperçu du ticket
          </h2>
          <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-4">
            <!-- Ticket preview -->
            <div class="border-2 border-dashed border-slate-300 rounded-xl overflow-hidden" style="max-width:220px; margin:0 auto;">
              <!-- Header -->
              <div class="bg-slate-900 text-white px-3 py-2 flex items-center justify-between">
                <span class="font-bold text-xs tracking-wide">CITY BUS</span>
                <span class="font-bold text-xs" x-text="form.price_fcfa + ' FCFA'"></span>
              </div>
              <!-- Network -->
              <div class="text-center text-[10px] text-slate-500 py-1 border-b border-slate-200" x-text="form.network_label || 'Réseau urbain · Brazzaville'"></div>
              <!-- Number -->
              <div class="text-center font-mono font-bold text-sm py-1.5 tracking-wide text-slate-800" x-text="previewNumber"></div>
              <!-- Route -->
              <div class="border border-slate-800 rounded-lg mx-3 px-2 py-1.5 text-center text-xs font-bold text-slate-900">
                <span x-text="form.departure || 'Départ'"></span>
                <span class="text-slate-400 mx-1">&rarr;</span>
                <span x-text="form.arrival || 'Arrivée'"></span>
              </div>
              <!-- Details + Symbol -->
              <div class="flex items-center justify-between px-3 py-2">
                <div class="text-[10px] text-slate-600 leading-relaxed">
                  <div>Date : <span x-text="previewDate"></span></div>
                  <div>Bus : <span x-text="form.bus_code || 'CB-???'" class="font-semibold"></span></div>
                </div>
                <div class="w-10 h-10 border-2 border-slate-800 rounded-lg flex items-center justify-center text-2xl leading-none"
                     x-text="form.symbol_char || '?'"></div>
              </div>
              <!-- Footer -->
              <div class="bg-slate-100 text-center text-[9px] text-slate-500 py-1">1 voyage &middot; non remboursable</div>
            </div>
          </div>

          <!-- Infos impression -->
          <div class="bg-amber-50 rounded-xl border border-amber-200 p-4 text-xs text-amber-800 space-y-2">
            <div class="font-semibold flex items-center gap-1.5"><i data-lucide="info" class="w-3.5 h-3.5"></i> Instructions d'impression</div>
            <ul class="list-disc list-inside space-y-1 text-amber-700">
              <li>Imprimante laser standard, papier A4 blanc</li>
              <li>4 colonnes &times; 6 lignes = 24 tickets/page</li>
              <li>Découper aux pointillés après impression</li>
              <li>Communiquer le symbole à l'équipage le jour J</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
function urbanTicketForm() {
  var today = new Date().toISOString().split('T')[0];
  return {
    submitting: false,
    formError: null,
    form: {
      ticket_date:   today,
      price_fcfa:    150,
      bus_code:      '',
      departure:     '',
      arrival:       '',
      network_label: 'Réseau urbain · Brazzaville',
      symbol_id:     0,
      symbol_char:   '',
      num_start:     <?= (int)$nextNum ?>,
      ticket_count:  96,
    },

    get pages() { return Math.ceil(this.form.ticket_count / 24); },
    get numEnd() { return this.form.num_start + this.form.ticket_count - 1; },
    get numRange() {
      var pad = function(n) { return String(n).padStart(4, '0'); };
      return pad(this.form.num_start) + ' – ' + pad(this.numEnd);
    },
    get revenueFormatted() {
      var r = this.form.ticket_count * this.form.price_fcfa;
      return r.toLocaleString('fr-FR') + ' FCFA';
    },
    get previewDate() {
      if (!this.form.ticket_date) return '--/--/----';
      var d = new Date(this.form.ticket_date + 'T00:00:00');
      return d.toLocaleDateString('fr-FR');
    },
    get previewNumber() {
      if (!this.form.ticket_date) return 'CB-??????-????';
      var d = new Date(this.form.ticket_date + 'T00:00:00');
      var yy = String(d.getFullYear()).slice(2);
      var mm = String(d.getMonth()+1).padStart(2,'0');
      var dd = String(d.getDate()).padStart(2,'0');
      return 'CB-' + yy + mm + dd + '-' + String(this.form.num_start).padStart(4, '0');
    },
    get canSubmit() {
      return this.form.ticket_date
          && this.form.bus_code
          && this.form.departure.trim().length >= 2
          && this.form.arrival.trim().length >= 2
          && this.form.symbol_id > 0
          && this.form.num_start > 0
          && this.form.ticket_count >= 1
          && this.form.price_fcfa > 0;
    },

    async submit() {
      if (!this.canSubmit) return;
      this.submitting = true;
      this.formError = null;

      var body = new FormData();
      body.append('_csrf', '<?= e(csrf_token()) ?>');
      Object.keys(this.form).forEach(function(k) { body.append(k, String(this.form[k])); }.bind(this));

      try {
        var res = await fetch('<?= e(url('billetterie/urban-tickets')) ?>', {
          method: 'POST', body: body,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        var json = await res.json();
        if (json.success && json.redirect) {
          window.location.href = json.redirect;
        } else {
          this.formError = json.error || 'Erreur lors de la création.';
        }
      } catch(e) {
        this.formError = 'Erreur réseau, veuillez réessayer.';
      }
      this.submitting = false;
    },
  };
}
</script>
<?php $view->end() ?>
