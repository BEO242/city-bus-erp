/* ============================================================
   City Bus ERP — JavaScript global
   ============================================================ */
(function () {
  'use strict';

  // CSRF helper depuis meta tag
  window.csrfToken = function () {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  };

  // Wrapper fetch avec CSRF + JSON
  window.api = async function (url, opts = {}) {
    const headers = Object.assign({
      'X-CSRF-TOKEN': window.csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    }, opts.headers || {});
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    const res = await fetch(url, Object.assign({}, opts, { headers, credentials: 'same-origin' }));
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(new Error(data.message || 'Erreur'), { status: res.status, data });
    return data;
  };

  // Indicateur offline
  function updateConnection() {
    let banner = document.getElementById('offline-banner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'offline-banner';
      banner.className = 'offline-banner';
      banner.textContent = '⚠️ Mode hors-ligne — les actions seront synchronisées dès le retour de la connexion.';
      document.body.appendChild(banner);
    }
    banner.classList.toggle('show', !navigator.onLine);
  }
  window.addEventListener('online', updateConnection);
  window.addEventListener('offline', updateConnection);
  document.addEventListener('DOMContentLoaded', updateConnection);

  // Composant Alpine — sidebar (utilisé dans le layout)
  document.addEventListener('alpine:init', () => {
    if (!window.Alpine) return;

    Alpine.data('sidebar', () => ({
      open: window.innerWidth >= 1024,
      toggle() { this.open = !this.open; }
    }));

    Alpine.data('profile', () => ({
      open: false,
      toggle() { this.open = !this.open; },
      close() { this.open = false; }
    }));
  });

  // Re-render Lucide icons après navigation Alpine
  document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) lucide.createIcons();
  });

  // Confirmations sur formulaires
  document.addEventListener('submit', e => {
    const c = e.target.dataset.confirm;
    if (c && !confirm(c)) e.preventDefault();
  });
})();
