<?php /** @var \CityBus\Core\View $view */
$view->extends('layouts/app');
?>
<?php $view->start('content') ?>
<div x-data="qrControle()" class="grid lg:grid-cols-3 gap-5">

  <div class="lg:col-span-3">
    <h1 class="text-2xl font-bold">Contrôle des tickets</h1>
    <p class="text-slate-500 text-sm">Scannez les QR codes des passagers à chaque point de contrôle.</p>
  </div>

  <!-- Scanner -->
  <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
    <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
      <h3 class="font-semibold flex items-center gap-2"><i data-lucide="qr-code" class="w-5 h-5 text-cb-primary"></i> Scanner</h3>
      <select x-model="checkpoint" class="px-3 py-2 rounded-xl border border-slate-200">
        <?php foreach ($checkpoints as $c): ?>
          <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?> (<?= e($c['city']) ?>)</option>
        <?php endforeach ?>
      </select>
    </div>

    <div id="scanner-region" class="bg-slate-900 rounded-xl aspect-video flex items-center justify-center overflow-hidden relative">
      <video id="qr-video" class="w-full h-full object-cover"></video>
      <!-- Idle : bouton démarrer -->
      <div x-show="!scanning && cameraState === 'idle'" class="absolute inset-0 flex flex-col items-center justify-center text-white">
        <i data-lucide="camera" class="w-16 h-16 mb-3 opacity-60"></i>
        <button @click="startScanner" class="px-6 py-3 rounded-xl bg-cb-primary font-semibold">
          Démarrer la caméra
        </button>
      </div>

      <!-- Waiting : le navigateur est en train de demander l'autorisation -->
      <div x-show="scanning && cameraState === 'waiting'" class="absolute inset-0 flex flex-col items-center justify-center text-white px-6 text-center pointer-events-none">
        <div class="w-14 h-14 mb-3 rounded-full border-4 border-white/20 border-t-white animate-spin"></div>
        <p class="text-sm text-white/80">En attente de votre autorisation…</p>
        <p class="text-xs text-white/50 mt-1">Répondez à la demande de votre navigateur</p>
      </div>

      <!-- Erreur bloquée : permission définitivement refusée dans le navigateur -->
      <div x-show="!scanning && cameraState === 'error_blocked'" class="absolute inset-0 flex flex-col items-center justify-center text-white px-6 text-center">
        <i data-lucide="shield-off" class="w-12 h-12 mb-3 text-rose-400"></i>
        <p class="text-sm text-rose-300 mb-1 font-semibold">Caméra bloquée</p>
        <p class="text-xs text-white/60 mb-4">Cliquez sur le 🔒 dans la barre d'adresse → <strong>Paramètres du site</strong> → <strong>Caméra</strong> → <strong>Autoriser</strong>, puis rechargez la page.</p>
        <button @click="window.location.reload()" class="px-5 py-2.5 rounded-xl bg-white/10 border border-white/20 text-sm font-medium hover:bg-white/20">
          Recharger la page
        </button>
      </div>

      <!-- Autre erreur caméra -->
      <div x-show="!scanning && cameraState === 'error_other'" class="absolute inset-0 flex flex-col items-center justify-center text-white px-6 text-center">
        <i data-lucide="camera-off" class="w-12 h-12 mb-3 text-rose-400"></i>
        <p class="text-sm text-rose-300 mb-4" x-text="cameraError"></p>
        <button @click="cameraState = 'idle'; startScanner()" class="px-5 py-2.5 rounded-xl bg-white/10 border border-white/20 text-sm font-medium hover:bg-white/20">
          Réessayer
        </button>
      </div>
    </div>

    <div class="mt-4 flex gap-2">
      <input x-model="manualCode" placeholder="Saisir un numéro de ticket manuellement" class="flex-1 px-3 py-2.5 rounded-xl border border-slate-200">
      <button @click="validateCode(manualCode); manualCode=''" class="px-5 py-2.5 rounded-xl bg-slate-900 text-white">Valider</button>
    </div>

    <div x-show="lastResult" class="mt-4 p-4 rounded-xl"
         :class="lastResult?.ok ? 'bg-emerald-50 border border-emerald-200' : 'bg-rose-50 border border-rose-200'">
      <div class="flex items-center gap-3">
        <template x-if="lastResult?.ok"><i data-lucide="check-circle" class="w-8 h-8 text-emerald-600"></i></template>
        <template x-if="!lastResult?.ok"><i data-lucide="x-circle" class="w-8 h-8 text-rose-600"></i></template>
        <div>
          <div class="font-bold" x-text="lastResult?.message"></div>
          <div class="text-sm text-slate-600" x-text="lastResult?.passenger || ''"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Historique -->
  <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-soft">
    <h3 class="font-semibold mb-3">Validations récentes</h3>
    <ul class="space-y-2 max-h-[500px] overflow-auto">
      <template x-for="v in history" :key="v.id">
        <li class="flex items-center gap-3 p-2 rounded-lg bg-slate-50">
          <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
          <div class="flex-1 min-w-0">
            <div class="font-medium text-sm truncate" x-text="v.ticket"></div>
            <div class="text-xs text-slate-400" x-text="v.time"></div>
          </div>
        </li>
      </template>
      <li x-show="!history.length" class="text-center text-slate-400 text-sm py-6">Aucune validation</li>
    </ul>
  </div>
</div>

<?php $view->end() ?>
<?php $view->start('scripts') ?>
<script src="<?= e(asset('js/qr-scanner.js')) ?>"></script>
<script src="<?= e(asset('js/offline.js')) ?>"></script>
<script>
function qrControle() {
  return {
    scanning: false,
    cameraState: 'idle', // idle | waiting | error_blocked | error_other
    cameraError: null,
    checkpoint: '<?= e($checkpoints[0]['id'] ?? '') ?>',
    manualCode: '',
    lastResult: null,
    history: [],
    startScanner() {
      this.scanning = true;
      this.cameraState = 'idle';
      this.cameraError = null;
      window.startQrScanner(
        'qr-video',
        code => this.validateCode(code),
        err => {
          this.scanning = false;
          if (err.name === 'InsecureContext') {
            this.cameraState = 'error_blocked';
            this.cameraError = err.message;
          } else if (err.name === 'NotSupported') {
            this.cameraState = 'error_other';
            this.cameraError = err.message;
          } else if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            this.cameraState = 'error_blocked';
            this.cameraError = 'Permission caméra refusée. Cliquez sur 🔒 → Paramètres du site → Caméra → Autoriser, puis rechargez.';
          } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            this.cameraState = 'error_other';
            this.cameraError = 'Aucune caméra détectée sur cet appareil.';
          } else if (err.name === 'NotReadableError') {
            this.cameraState = 'error_other';
            this.cameraError = 'La caméra est déjà utilisée par une autre application.';
          } else {
            this.cameraState = 'error_other';
            this.cameraError = 'Erreur caméra (' + err.name + ') : ' + err.message;
            console.error('QR Scanner error:', err.name, err.message, err);
          }
        },
        () => { this.cameraState = 'waiting'; }
      );
    },
    async validateCode(code) {
      if (!code) return;
      try {
        const res = await fetch('<?= e(url('controle/validate')) ?>', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ qr_code: code, checkpoint_id: this.checkpoint })
        });
        const data = await res.json();
        this.lastResult = {
          ok: data.success,
          message: data.message || (data.success ? 'Ticket validé' : 'Erreur'),
          passenger: data.ticket?.passenger_name || ''
        };
        if (data.success && data.ticket) {
          this.history.unshift({ id: Date.now(), ticket: data.ticket.ticket_number + ' · ' + data.ticket.passenger_name, time: new Date().toLocaleTimeString('fr-FR') });
          if (this.history.length > 20) this.history.pop();
        }
      } catch (e) {
        this.lastResult = { ok: false, message: 'Erreur réseau (mode offline ?)' };
      }
    }
  }
}
</script>
<?php $view->end() ?>
