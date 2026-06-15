/* ============================================================
   Offline Mode — Cache de voyages (IndexedDB) + queue de sync
   v2 : versioning, retry exponentiel, conflits, isolement
   ============================================================ */
(function () {
  'use strict';

  const DB_NAME = 'citybus_offline';
  const DB_VERSION = 2;
  const MAX_RETRIES = 5;
  let db = null;

  function openDB() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = e => {
        const d = e.target.result;
        if (!d.objectStoreNames.contains('trips'))    d.createObjectStore('trips',   { keyPath: 'id' });
        if (!d.objectStoreNames.contains('tickets'))  d.createObjectStore('tickets', { keyPath: 'qr_code' });
        if (!d.objectStoreNames.contains('queue'))    d.createObjectStore('queue',   { keyPath: 'id', autoIncrement: true });
        if (!d.objectStoreNames.contains('conflicts')) d.createObjectStore('conflicts', { keyPath: 'id', autoIncrement: true });
      };
      req.onsuccess = () => { db = req.result; resolve(db); };
      req.onerror = () => reject(req.error);
    });
  }

  async function tx(store, mode = 'readonly') {
    if (!db) await openDB();
    return db.transaction(store, mode).objectStore(store);
  }

  async function cacheTrip(tripId) {
    try {
      const data = await window.api(`/controle/trip/${tripId}/cache`);
      const trips = await tx('trips', 'readwrite');
      trips.put({ id: tripId, cached_at: Date.now(), ...data.trip });
      const tickets = await tx('tickets', 'readwrite');
      (data.tickets || []).forEach(t => tickets.put(t));
      return true;
    } catch (e) { console.warn('[offline] cacheTrip', e); return false; }
  }

  async function findTicketLocal(qrCode) {
    const store = await tx('tickets');
    return new Promise((resolve, reject) => {
      const r = store.get(qrCode);
      r.onsuccess = () => resolve(r.result);
      r.onerror = () => reject(r.error);
    });
  }

  async function queueValidation(payload) {
    const store = await tx('queue', 'readwrite');
    store.add({ ...payload, queued_at: Date.now(), attempts: 0 });
  }

  async function logConflict(item, response) {
    const store = await tx('conflicts', 'readwrite');
    store.add({ item, response, at: Date.now() });
  }

  let flushing = false;
  async function flushQueue() {
    if (flushing || !navigator.onLine) return;
    flushing = true;
    try {
      const store = await tx('queue', 'readwrite');
      const items = await new Promise((res, rej) => {
        const r = store.getAll();
        r.onsuccess = () => res(r.result);
        r.onerror = () => rej(r.error);
      });
      if (!items.length) return;

      const r = await window.api('/controle/sync', { method: 'POST', body: { batch: items } });

      // Retire les items syncés OK ; conserve ceux en échec avec attempts++
      const results = (r && r.results) || [];
      const writeStore = await tx('queue', 'readwrite');
      for (let i = 0; i < items.length; i++) {
        const res = results[i] || { status: 'ok' };
        const it = items[i];
        if (res.status === 'ok' || res.status === 'success') {
          writeStore.delete(it.id);
        } else if (res.status === 'conflict' || res.status === 'rejected') {
          await logConflict(it, res);
          writeStore.delete(it.id);
        } else {
          // erreur : retry
          const attempts = (it.attempts || 0) + 1;
          if (attempts >= MAX_RETRIES) {
            await logConflict(it, { ...res, reason: 'max_retries' });
            writeStore.delete(it.id);
          } else {
            writeStore.put({ ...it, attempts, last_error: res.message || 'unknown' });
          }
        }
      }
      console.log('[offline] sync %d items', items.length);
    } catch (e) {
      console.warn('[offline] sync échoué', e);
    } finally {
      flushing = false;
    }
  }

  // Retry périodique si encore online (toutes les 60s)
  setInterval(() => { if (navigator.onLine) flushQueue(); }, 60000);
  window.addEventListener('online', flushQueue);
  document.addEventListener('DOMContentLoaded', () => { openDB().then(flushQueue); });

  async function getConflicts() {
    const store = await tx('conflicts');
    return new Promise((res, rej) => {
      const r = store.getAll();
      r.onsuccess = () => res(r.result);
      r.onerror = () => rej(r.error);
    });
  }

  window.cityBusOffline = { cacheTrip, findTicketLocal, queueValidation, flushQueue, getConflicts };
})();
