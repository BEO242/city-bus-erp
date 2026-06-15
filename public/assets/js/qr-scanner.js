/* ============================================================
   QR Scanner — utilise la lib jsQR via CDN ou BarcodeDetector natif
   ============================================================ */
(function () {
  'use strict';

  let stream = null;
  let scanning = false;

  async function startScanner(videoId, onDetect, onError, onWaiting) {
    const video = document.getElementById(videoId);
    if (!video) return;

    // Vérifier que le contexte permet l'accès caméra
    if (!window.isSecureContext) {
      if (typeof onError === 'function') {
        const err = new Error('La caméra nécessite une connexion sécurisée (HTTPS ou localhost). Accédez via https:// ou http://localhost.');
        err.name = 'InsecureContext';
        onError(err);
      }
      return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      if (typeof onError === 'function') {
        const err = new Error('Votre navigateur ne supporte pas l\'accès caméra. Utilisez Chrome, Edge ou Firefox.');
        err.name = 'NotSupported';
        onError(err);
      }
      return;
    }

    // Afficher l'état "en attente" pendant que le navigateur demande l'autorisation
    if (typeof onWaiting === 'function') onWaiting();

    try {
      // Cascade : arrière → avant → n'importe quelle caméra disponible
      const constraints = [
        { video: { facingMode: { ideal: 'environment' } } }, // arrière (idéal)
        { video: { facingMode: { ideal: 'user' } } },        // avant (fallback)
        { video: true },                                      // n'importe laquelle
      ];
      let lastErr;
      for (const c of constraints) {
        try {
          stream = await navigator.mediaDevices.getUserMedia(c);
          break;
        } catch (e) {
          lastErr = e;
          console.warn('QR Scanner: échec avec', JSON.stringify(c.video), '→', e.name);
        }
      }
      if (!stream) throw lastErr;
      video.srcObject = stream;
      await video.play();
      scanning = true;

      // BarcodeDetector natif (Chrome/Edge)
      if ('BarcodeDetector' in window) {
        const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        const tick = async () => {
          if (!scanning) return;
          try {
            const codes = await detector.detect(video);
            if (codes.length > 0) {
              const code = codes[0].rawValue;
              stopScanner();
              onDetect(code);
              return;
            }
          } catch (e) {}
          requestAnimationFrame(tick);
        };
        tick();
      } else {
        // Fallback jsQR (charger CDN)
        if (!window.jsQR) {
          await loadScript('https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js');
        }
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const tick = () => {
          if (!scanning) return;
          if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = window.jsQR(img.data, img.width, img.height);
            if (code) {
              stopScanner();
              onDetect(code.data);
              return;
            }
          }
          requestAnimationFrame(tick);
        };
        tick();
      }
    } catch (err) {
      if (typeof onError === 'function') {
        onError(err);
      } else {
        console.error('QR Scanner:', err);
      }
    }
  }

  function stopScanner() {
    scanning = false;
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }
  }

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = src;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  window.startQrScanner = startScanner;
  window.stopQrScanner = stopScanner;
})();
