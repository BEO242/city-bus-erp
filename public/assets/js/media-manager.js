/**
 * MediaManager — Gestionnaire de médias réutilisable (galerie & documents).
 * Dépendances CDN : Cropper.js 1.6.x (pour cropEnabled:true)
 *
 * Usage :
 *   new MediaManager({
 *     container:    '#gallery-zone',
 *     mediableType: 'buses',
 *     mediableId:   6,
 *     collection:   'gallery',   // 'gallery' | 'documents'
 *     csrf:         '...',
 *     cropEnabled:  true,        // modal recadrage avant upload (images)
 *     maxFiles:     20,
 *     initialItems: [],          // items pré-chargés (côté serveur)
 *   });
 */
class MediaManager {
  constructor(opts) {
    this.opts = Object.assign({
      container: null, mediableType: '', mediableId: 0,
      collection: 'gallery', csrf: '', cropEnabled: false,
      maxFiles: 30, initialItems: [],
    }, opts);

    this.items       = [...this.opts.initialItems];
    this.isGallery   = this.opts.collection === 'gallery';
    this.dragSrcIdx  = null;
    this.cropper     = null;
    this.pendingFile = null;

    this.$root = typeof this.opts.container === 'string'
      ? document.querySelector(this.opts.container)
      : this.opts.container;

    if (!this.$root) { console.warn('MediaManager: container introuvable'); return; }

    this._buildUI();
    this._render();
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Construction de l'UI
  // ────────────────────────────────────────────────────────────────────────────
  _buildUI() {
    this.$root.innerHTML = '';
    this.$root.classList.add('mm-root');

    // Zone de drop
    this.$dropzone = this._el('div', 'mm-dropzone');
    this.$dropzone.innerHTML = `
      <input type="file" class="mm-file-input" accept="${this._acceptAttr()}" ${this.isGallery ? 'multiple' : 'multiple'}>
      <div class="mm-drop-hint">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <span class="mm-drop-label">Glissez des fichiers ici ou <u>cliquez pour sélectionner</u></span>
        <span class="mm-drop-sub">${this._acceptHint()}</span>
      </div>`;

    // Grille des items
    this.$grid = this._el('div', this.isGallery ? 'mm-grid' : 'mm-list');

    // Modal crop
    this.$cropModal = this._el('div', 'mm-crop-modal mm-hidden');
    this.$cropModal.innerHTML = `
      <div class="mm-crop-overlay"></div>
      <div class="mm-crop-box">
        <div class="mm-crop-header">
          <span class="mm-crop-title">Recadrer l'image</span>
          <button type="button" class="mm-crop-close" title="Annuler">✕</button>
        </div>
        <div class="mm-crop-img-wrap"><img class="mm-crop-img" src="" alt=""></div>
        <div class="mm-crop-actions">
          <div class="mm-crop-btns">
            <button type="button" class="mm-cbtn" data-action="rotate-l" title="Rotation gauche">↺</button>
            <button type="button" class="mm-cbtn" data-action="rotate-r" title="Rotation droite">↻</button>
            <button type="button" class="mm-cbtn" data-action="flip-h" title="Miroir H">⇄</button>
            <button type="button" class="mm-cbtn" data-action="flip-v" title="Miroir V">⇅</button>
            <button type="button" class="mm-cbtn" data-action="free" title="Libre">⛶</button>
            <button type="button" class="mm-cbtn" data-action="16:9">16:9</button>
            <button type="button" class="mm-cbtn" data-action="4:3">4:3</button>
            <button type="button" class="mm-cbtn" data-action="1:1">1:1</button>
          </div>
          <div class="mm-crop-confirm-row">
            <button type="button" class="mm-btn-cancel">Annuler</button>
            <button type="button" class="mm-btn-confirm">Confirmer & envoyer</button>
          </div>
        </div>
      </div>`;

    // Modal édition métadonnées
    this.$metaModal = this._el('div', 'mm-meta-modal mm-hidden');
    this.$metaModal.innerHTML = `
      <div class="mm-crop-overlay"></div>
      <div class="mm-meta-box">
        <div class="mm-crop-header">
          <span class="mm-crop-title">Modifier les informations</span>
          <button type="button" class="mm-meta-close">✕</button>
        </div>
        <div class="mm-meta-body">
          <label class="mm-label">Texte alternatif (SEO / accessibilité)</label>
          <input type="text" class="mm-input mm-meta-alt" placeholder="Description de l'image…">
          <label class="mm-label" style="margin-top:12px">Légende</label>
          <textarea class="mm-input mm-meta-caption" rows="2" placeholder="Légende affichée sous l'image…"></textarea>
        </div>
        <div class="mm-meta-footer">
          <button type="button" class="mm-btn-cancel mm-meta-cancel">Annuler</button>
          <button type="button" class="mm-btn-confirm mm-meta-save">Enregistrer</button>
        </div>
      </div>`;

    this.$root.append(this.$dropzone, this.$grid, this.$cropModal, this.$metaModal);
    this._injectStyles();
    this._bindDropzone();
    this._bindCropModal();
    this._bindMetaModal();
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Rendu de la grille
  // ────────────────────────────────────────────────────────────────────────────
  _render() {
    this.$grid.innerHTML = '';
    if (!this.items.length) {
      this.$grid.innerHTML = `<p class="mm-empty">Aucun fichier pour l'instant.</p>`;
      return;
    }

    this.items.forEach((item, idx) => {
      const card = this.isGallery ? this._renderImageCard(item, idx) : this._renderDocCard(item, idx);
      this.$grid.appendChild(card);
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  _renderImageCard(item, idx) {
    const card = this._el('div', 'mm-img-card');
    card.dataset.id  = item.id;
    card.dataset.idx = idx;
    card.draggable   = true;

    card.innerHTML = `
      <div class="mm-img-wrap">
        <img src="${item.thumb_url}" alt="${this._esc(item.alt_text || '')}" loading="lazy">
        <div class="mm-img-overlay">
          <button type="button" class="mm-icon-btn mm-btn-edit" title="Modifier" data-id="${item.id}"><i data-lucide="pencil" class="w-4 h-4"></i></button>
          <button type="button" class="mm-icon-btn mm-btn-view" title="Voir original" data-url="${item.url}"><i data-lucide="eye" class="w-4 h-4"></i></button>
          <button type="button" class="mm-icon-btn mm-btn-del" title="Supprimer" data-id="${item.id}"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
        </div>
        ${idx === 0 ? '<span class="mm-cover-badge">Couverture</span>' : ''}
      </div>
      <div class="mm-img-meta">
        <span class="mm-img-name" title="${this._esc(item.file_name)}">${this._esc(this._shortName(item.file_name))}</span>
        <span class="mm-img-size">${item.human_size}</span>
      </div>`;

    // Drag & drop réordonnancement
    this._bindDrag(card, idx);

    card.querySelector('.mm-btn-edit').addEventListener('click', () => this._openMeta(item));
    card.querySelector('.mm-btn-view').addEventListener('click', () => window.open(item.url, '_blank'));
    card.querySelector('.mm-btn-del').addEventListener('click', () => this._deleteItem(item.id));

    return card;
  }

  _renderDocCard(item, idx) {
    const card = this._el('div', 'mm-doc-row');
    card.dataset.id = item.id;

    const icon = this._mimeIcon(item.mime_type);
    card.innerHTML = `
      <div class="mm-doc-icon"><i data-lucide="${icon}" class="w-5 h-5"></i></div>
      <div class="mm-doc-info">
        <span class="mm-doc-name" title="${this._esc(item.file_name)}">${this._esc(item.file_name)}</span>
        <span class="mm-doc-meta">${item.human_size} · ${this._formatDate(item.created_at)}</span>
      </div>
      <div class="mm-doc-actions">
        <a href="${item.url}" target="_blank" class="mm-icon-btn" title="Télécharger"><i data-lucide="download" class="w-4 h-4"></i></a>
        <button type="button" class="mm-icon-btn mm-btn-del" title="Supprimer" data-id="${item.id}"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
      </div>`;

    card.querySelector('.mm-btn-del').addEventListener('click', () => this._deleteItem(item.id));
    return card;
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Drag & drop réordonnancement (HTML5)
  // ────────────────────────────────────────────────────────────────────────────
  _bindDrag(card, idx) {
    card.addEventListener('dragstart', e => {
      this.dragSrcIdx = idx;
      e.dataTransfer.effectAllowed = 'move';
      card.classList.add('mm-dragging');
    });
    card.addEventListener('dragend', () => card.classList.remove('mm-dragging'));

    card.addEventListener('dragover', e => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      card.classList.add('mm-drag-over');
    });
    card.addEventListener('dragleave', () => card.classList.remove('mm-drag-over'));

    card.addEventListener('drop', e => {
      e.preventDefault();
      card.classList.remove('mm-drag-over');
      const targetIdx = parseInt(card.dataset.idx);
      if (this.dragSrcIdx === null || this.dragSrcIdx === targetIdx) return;

      // Réordonner localement
      const moved = this.items.splice(this.dragSrcIdx, 1)[0];
      this.items.splice(targetIdx, 0, moved);
      this.dragSrcIdx = null;
      this._render();

      // Persister en base
      this._saveOrder();
    });
  }

  _saveOrder() {
    const ids = this.items.map(i => i.id).join(',');
    this._post('/media/reorder', { ids })
      .catch(err => console.error('Erreur réordonnancement:', err));
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Dropzone
  // ────────────────────────────────────────────────────────────────────────────
  _bindDropzone() {
    const zone  = this.$dropzone;
    const input = zone.querySelector('.mm-file-input');

    zone.addEventListener('click', e => {
      if (!e.target.closest('.mm-file-input')) input.click();
    });
    input.addEventListener('change', () => this._handleFiles(Array.from(input.files)));
    input.value = '';

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('mm-drop-active'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('mm-drop-active'));
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('mm-drop-active');
      this._handleFiles(Array.from(e.dataTransfer.files));
    });
  }

  _handleFiles(files) {
    if (!files.length) return;
    if (this.items.length + files.length > this.opts.maxFiles) {
      this._toast(`Maximum ${this.opts.maxFiles} fichiers.`, 'error'); return;
    }

    files.forEach(file => {
      const canCrop = this.isGallery && this.opts.cropEnabled
        && file.type.startsWith('image/')
        && typeof Cropper !== 'undefined';
      if (canCrop) {
        this._openCrop(file);
      } else {
        this._uploadFile(file, null);
      }
    });
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Modal Crop
  // ────────────────────────────────────────────────────────────────────────────
  _bindCropModal() {
    const modal = this.$cropModal;
    const img   = modal.querySelector('.mm-crop-img');

    modal.querySelector('.mm-crop-close').addEventListener('click', () => this._closeCrop());
    modal.querySelector('.mm-crop-overlay').addEventListener('click', () => this._closeCrop());
    modal.querySelector('.mm-btn-cancel').addEventListener('click', () => this._closeCrop());

    modal.querySelectorAll('.mm-cbtn[data-action]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (!this.cropper) return;
        const a = btn.dataset.action;
        if (a === 'rotate-l') this.cropper.rotate(-90);
        else if (a === 'rotate-r') this.cropper.rotate(90);
        else if (a === 'flip-h') this.cropper.scaleX(-this.cropper.getData().scaleX || -1);
        else if (a === 'flip-v') this.cropper.scaleY(-this.cropper.getData().scaleY || -1);
        else if (a === 'free') this.cropper.setAspectRatio(NaN);
        else {
          const [w, h] = a.split(':').map(Number);
          this.cropper.setAspectRatio(w / h);
        }
      });
    });

    modal.querySelector('.mm-btn-confirm').addEventListener('click', () => {
      if (!this.pendingFile) return;
      const cropData = this.cropper ? this.cropper.getData(true) : null;
      const file = this.pendingFile;
      this._closeCrop();
      this._uploadFile(file, cropData);
    });
  }

  _openCrop(file) {
    const modal = this.$cropModal;
    const img   = modal.querySelector('.mm-crop-img');

    this.pendingFile = file;

    // Détruire l'éventuel cropper précédent
    if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
    img.src = '';

    modal.classList.remove('mm-hidden');
    document.body.style.overflow = 'hidden';

    const objectUrl = URL.createObjectURL(file);

    img.onload = () => {
      if (typeof Cropper !== 'undefined') {
        this.cropper = new Cropper(img, {
          viewMode: 1,
          autoCropArea: 0.85,
          movable: true, rotatable: true, scalable: true, zoomable: true,
          background: true, guides: true, center: true, highlight: true,
          ready: () => { URL.revokeObjectURL(objectUrl); },
        });
      } else {
        URL.revokeObjectURL(objectUrl);
      }
    };

    img.src = objectUrl;
  }

  _closeCrop() {
    if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
    this.pendingFile = null;
    this.$cropModal.classList.add('mm-hidden');
    document.body.style.overflow = '';
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Upload
  // ────────────────────────────────────────────────────────────────────────────
  _uploadFile(file, cropData) {
    // Barre de progression dans la dropzone
    const progressWrap = this._el('div', 'mm-progress-wrap');
    progressWrap.innerHTML = `
      <span class="mm-progress-name">${this._esc(this._shortName(file.name))}</span>
      <div class="mm-progress-bar"><div class="mm-progress-fill" style="width:0%"></div></div>`;
    this.$dropzone.querySelector('.mm-drop-hint').after(progressWrap);

    const fill = progressWrap.querySelector('.mm-progress-fill');

    const fd = new FormData();
    fd.append('_csrf', this.opts.csrf);
    fd.append('mediable_type', this.opts.mediableType);
    fd.append('mediable_id', String(this.opts.mediableId));
    fd.append('collection', this.opts.collection);
    fd.append(this.isGallery ? 'image' : 'document', file);
    if (cropData) fd.append('crop', JSON.stringify(cropData));

    const xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', e => {
      if (e.lengthComputable) fill.style.width = Math.round(e.loaded / e.total * 100) + '%';
    });

    xhr.addEventListener('load', () => {
      progressWrap.remove();
      try {
        const res = JSON.parse(xhr.responseText);
        if (res.success && res.media) {
          this.items.push(res.media);
          this._render();
          this._toast('Fichier ajouté avec succès.', 'success');
        } else {
          this._toast(res.error || 'Erreur lors de l\'upload.', 'error');
        }
      } catch (e) {
        this._toast('Réponse invalide du serveur.', 'error');
      }
    });

    xhr.addEventListener('error', () => {
      progressWrap.remove();
      this._toast('Erreur réseau lors de l\'upload.', 'error');
    });

    xhr.open('POST', this._url('/media/upload'));
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.send(fd);
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Suppression
  // ────────────────────────────────────────────────────────────────────────────
  _deleteItem(id) {
    if (!confirm('Supprimer ce fichier définitivement ?')) return;

    this._post(`/media/${id}/delete`, {})
      .then(res => {
        if (res.success) {
          this.items = this.items.filter(i => i.id !== id);
          this._render();
          this._toast('Fichier supprimé.', 'success');
        } else {
          this._toast(res.error || 'Erreur lors de la suppression.', 'error');
        }
      })
      .catch(() => this._toast('Erreur réseau.', 'error'));
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Modal Métadonnées
  // ────────────────────────────────────────────────────────────────────────────
  _bindMetaModal() {
    const modal = this.$metaModal;
    const close = () => modal.classList.add('mm-hidden');

    modal.querySelector('.mm-meta-close').addEventListener('click', close);
    modal.querySelector('.mm-meta-cancel').addEventListener('click', close);
    modal.querySelector('.mm-crop-overlay').addEventListener('click', close);

    modal.querySelector('.mm-meta-save').addEventListener('click', () => {
      const id      = modal.dataset.editId;
      const altText = modal.querySelector('.mm-meta-alt').value.trim();
      const caption = modal.querySelector('.mm-meta-caption').value.trim();

      this._post(`/media/${id}/update`, { alt_text: altText, caption })
        .then(res => {
          if (res.success) {
            const item = this.items.find(i => String(i.id) === String(id));
            if (item) { item.alt_text = altText; item.caption = caption; }
            this._render();
            close();
            this._toast('Informations mises à jour.', 'success');
          }
        });
    });
  }

  _openMeta(item) {
    const modal = this.$metaModal;
    modal.dataset.editId = item.id;
    modal.querySelector('.mm-meta-alt').value     = item.alt_text || '';
    modal.querySelector('.mm-meta-caption').value = item.caption  || '';
    modal.classList.remove('mm-hidden');
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Helpers
  // ────────────────────────────────────────────────────────────────────────────
  _post(path, data) {
    const fd = new FormData();
    fd.append('_csrf', this.opts.csrf);
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));

    return fetch(this._url(path), {
      method: 'POST', body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).then(r => r.json());
  }

  _url(path) {
    const base = (window._appBase || '').replace(/\/$/, '');
    return base + path;
  }

  _el(tag, cls) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    return el;
  }

  _esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  _shortName(name, max = 22) {
    if (!name || name.length <= max) return name || '';
    const ext  = name.includes('.') ? name.slice(name.lastIndexOf('.')) : '';
    return name.slice(0, max - ext.length - 1) + '…' + ext;
  }

  _formatDate(dateStr) {
    if (!dateStr) return '';
    try {
      return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch { return dateStr; }
  }

  _mimeIcon(mime) {
    if (!mime) return 'file';
    if (mime.startsWith('image/')) return 'image';
    if (mime === 'application/pdf') return 'file-text';
    if (mime.includes('word')) return 'file-text';
    if (mime.includes('excel') || mime.includes('spreadsheet')) return 'file-spreadsheet';
    return 'file';
  }

  _acceptAttr() {
    return this.isGallery
      ? 'image/jpeg,image/png,image/webp,image/gif'
      : 'image/*,application/pdf,.doc,.docx,.xls,.xlsx';
  }

  _acceptHint() {
    return this.isGallery
      ? 'JPEG, PNG, WebP, GIF — max 10 Mo par fichier'
      : 'PDF, Word, Excel, Images — max 20 Mo par fichier';
  }

  _toast(msg, type = 'info') {
    let container = document.getElementById('mm-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'mm-toast-container';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `mm-toast mm-toast-${type}`;
    toast.textContent = msg;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('mm-toast-show'), 10);
    setTimeout(() => { toast.classList.remove('mm-toast-show'); setTimeout(() => toast.remove(), 300); }, 3500);
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Styles injectés (isolation du composant)
  // ────────────────────────────────────────────────────────────────────────────
  _injectStyles() {
    if (document.getElementById('mm-styles')) return;
    const style = document.createElement('style');
    style.id = 'mm-styles';
    style.textContent = `
      /* MediaManager Styles */
      .mm-root { position:relative; }

      /* Dropzone */
      .mm-dropzone {
        border: 2px dashed #cbd5e1; border-radius:16px; padding:28px 20px;
        cursor:pointer; text-align:center; background:#f8faff; transition:all .2s;
        position:relative; overflow:hidden;
      }
      .mm-dropzone:hover, .mm-drop-active { border-color:#1565C0; background:#eff6ff; }
      .mm-file-input { display:none; }
      .mm-drop-hint { display:flex; flex-direction:column; align-items:center; gap:8px; color:#64748b; pointer-events:none; }
      .mm-drop-hint svg { color:#94a3b8; }
      .mm-drop-label { font-size:14px; font-weight:500; }
      .mm-drop-sub { font-size:12px; color:#94a3b8; }

      /* Progress */
      .mm-progress-wrap { margin-top:12px; text-align:left; }
      .mm-progress-name { font-size:12px; color:#64748b; display:block; margin-bottom:4px; }
      .mm-progress-bar { background:#e2e8f0; border-radius:999px; height:6px; overflow:hidden; }
      .mm-progress-fill { height:100%; background:#1565C0; border-radius:999px; transition:width .1s; }

      /* Image grid */
      .mm-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; margin-top:16px; }
      .mm-img-card { border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; background:#fff;
        cursor:grab; transition:box-shadow .2s, transform .15s; }
      .mm-img-card:hover { box-shadow:0 4px 20px rgba(21,101,192,.14); transform:translateY(-2px); }
      .mm-img-card.mm-dragging { opacity:.45; transform:scale(.97); cursor:grabbing; }
      .mm-img-card.mm-drag-over { box-shadow:0 0 0 3px #1565C0; }
      .mm-img-wrap { position:relative; aspect-ratio:4/3; overflow:hidden; background:#f1f5f9; }
      .mm-img-wrap img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .2s; }
      .mm-img-card:hover .mm-img-wrap img { transform:scale(1.04); }
      .mm-img-overlay { position:absolute; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center;
        justify-content:center; gap:8px; opacity:0; transition:opacity .2s; }
      .mm-img-card:hover .mm-img-overlay { opacity:1; }
      .mm-cover-badge { position:absolute; top:6px; left:6px; background:#1565C0; color:#fff;
        font-size:10px; font-weight:600; padding:2px 8px; border-radius:999px; }
      .mm-img-meta { padding:6px 8px; display:flex; justify-content:space-between; align-items:center; }
      .mm-img-name { font-size:11px; font-weight:500; color:#334155; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100px; }
      .mm-img-size { font-size:10px; color:#94a3b8; }

      /* Icon buttons */
      .mm-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px;
        border-radius:8px; border:none; cursor:pointer; transition:background .15s; background:rgba(255,255,255,.18); color:#fff; }
      .mm-icon-btn:hover { background:rgba(255,255,255,.35); }
      .mm-btn-del:hover { background:rgba(239,68,68,.75) !important; }

      /* Documents list */
      .mm-list { display:flex; flex-direction:column; gap:8px; margin-top:16px; }
      .mm-doc-row { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:12px;
        border:1px solid #e2e8f0; background:#fff; }
      .mm-doc-icon { color:#64748b; flex-shrink:0; }
      .mm-doc-info { flex:1; min-width:0; }
      .mm-doc-name { display:block; font-size:13px; font-weight:500; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .mm-doc-meta { font-size:11px; color:#94a3b8; }
      .mm-doc-actions { display:flex; gap:6px; flex-shrink:0; }
      .mm-doc-actions .mm-icon-btn { background:#f1f5f9; color:#475569; }
      .mm-doc-actions .mm-icon-btn:hover { background:#e2e8f0; }
      .mm-doc-actions .mm-btn-del:hover { background:rgba(239,68,68,.12); color:#ef4444; }

      /* Empty */
      .mm-empty { font-size:13px; color:#94a3b8; padding:16px; text-align:center; }

      /* Modals */
      .mm-hidden { display:none !important; }
      .mm-crop-modal, .mm-meta-modal {
        position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center;
      }
      .mm-crop-overlay { position:absolute; inset:0; background:rgba(0,0,0,.65); }

      .mm-crop-box {
        position:relative; z-index:1; background:#fff; border-radius:20px; overflow:hidden;
        width:min(92vw,820px); display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.35);
      }
      .mm-crop-header { display:flex; align-items:center; justify-content:space-between;
        padding:16px 20px; border-bottom:1px solid #e2e8f0; }
      .mm-crop-title { font-weight:700; font-size:16px; }
      .mm-crop-close { background:none; border:none; font-size:18px; cursor:pointer; color:#64748b; width:32px; height:32px; border-radius:8px; }
      .mm-crop-close:hover { background:#f1f5f9; }
      .mm-crop-img-wrap { position:relative; height:55vh; min-height:280px; max-height:500px; overflow:hidden; background:#1e293b; display:block; }
      .mm-crop-img { display:block; max-width:100%; max-height:100%; opacity:0; }/* Cropper.js remplace via canvas */

      .mm-crop-actions { padding:16px 20px; border-top:1px solid #e2e8f0; }
      .mm-crop-btns { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; }
      .mm-cbtn { padding:5px 12px; border-radius:8px; border:1px solid #e2e8f0; background:#f8faff;
        cursor:pointer; font-size:13px; font-weight:500; transition:all .15s; }
      .mm-cbtn:hover { background:#eff6ff; border-color:#1565C0; color:#1565C0; }
      .mm-crop-confirm-row { display:flex; justify-content:flex-end; gap:10px; }

      .mm-btn-cancel, .mm-btn-confirm {
        padding:9px 22px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none;
      }
      .mm-btn-cancel { background:#f1f5f9; color:#475569; }
      .mm-btn-cancel:hover { background:#e2e8f0; }
      .mm-btn-confirm { background:#1565C0; color:#fff; }
      .mm-btn-confirm:hover { background:#0d47a1; }

      /* Meta modal */
      .mm-meta-box {
        position:relative; z-index:1; background:#fff; border-radius:20px; overflow:hidden;
        width:min(92vw,500px); box-shadow:0 20px 60px rgba(0,0,0,.3);
      }
      .mm-meta-body { padding:20px; display:flex; flex-direction:column; gap:6px; }
      .mm-label { font-size:13px; font-weight:500; color:#374151; }
      .mm-input { width:100%; padding:9px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:13px;
        font-family:inherit; outline:none; transition:border-color .15s; }
      .mm-input:focus { border-color:#1565C0; }
      .mm-meta-footer { padding:16px 20px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px; }

      /* Toasts */
      #mm-toast-container { position:fixed; bottom:24px; right:24px; z-index:10000; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
      .mm-toast { background:#1e293b; color:#fff; padding:12px 18px; border-radius:12px; font-size:13px; font-weight:500;
        opacity:0; transform:translateY(10px); transition:all .25s; max-width:320px; }
      .mm-toast-show { opacity:1; transform:translateY(0); }
      .mm-toast-success { background:#166534; }
      .mm-toast-error   { background:#991b1b; }
    `;
    document.head.appendChild(style);
  }
}
