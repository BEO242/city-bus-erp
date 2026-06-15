-- ============================================================
-- Module Voyages v3 — Phase 1 Foundation Pro
-- Booking classes (Y/B/M/H/L) + Stop tracking + Briefing
-- Date : 13 mai 2026
-- ============================================================

-- ─── 1. CLASSES D'INVENTAIRE (booking classes) ──────────────
CREATE TABLE IF NOT EXISTS inventory_classes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            CHAR(2)         NOT NULL COMMENT 'Y, B, M, H, L, V…',
    label           VARCHAR(80)     NOT NULL,
    description     VARCHAR(255)    NULL,
    flexibility     ENUM('full','medium','restricted','non_refundable') NOT NULL DEFAULT 'medium',
    refund_policy_pct INT UNSIGNED  NOT NULL DEFAULT 50 COMMENT '% remboursable si annulation',
    change_fee_fcfa INT UNSIGNED    NOT NULL DEFAULT 0,
    no_show_fee_pct INT UNSIGNED    NOT NULL DEFAULT 100,
    priority_boarding INT UNSIGNED  NOT NULL DEFAULT 5 COMMENT '1=premier, 9=dernier',
    priority_standby  INT UNSIGNED  NOT NULL DEFAULT 5,
    color_hex       VARCHAR(7)      NOT NULL DEFAULT '#64748b',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order      INT             NOT NULL DEFAULT 100,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_inv_class_code (code),
    INDEX idx_inv_class_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO inventory_classes (code, label, description, flexibility, refund_policy_pct, change_fee_fcfa, priority_boarding, color_hex, sort_order) VALUES
    ('Y', 'Première',     'Tarif premium · 100% flexible',         'full',           100, 0,    1, '#7c3aed', 10),
    ('B', 'Affaires',     'Confort + flexibilité',                 'full',            90, 1000, 2, '#2563eb', 20),
    ('M', 'Standard',     'Tarif courant',                         'medium',          70, 2000, 4, '#10b981', 30),
    ('H', 'Économique',   'Tarif réduit · modifications limitées', 'restricted',      30, 5000, 6, '#f59e0b', 40),
    ('L', 'Promo',        'Tarif promotionnel · non remboursable', 'non_refundable',   0, 0,    8, '#ef4444', 50)
ON DUPLICATE KEY UPDATE code = code;

-- ─── 2. INVENTAIRE PAR VOYAGE ──────────────────────────────
CREATE TABLE IF NOT EXISTS trip_inventory (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    class_id        BIGINT UNSIGNED NOT NULL,
    class_code      CHAR(2)         NOT NULL COMMENT 'Dénormalisé pour rapidité',
    capacity        INT UNSIGNED    NOT NULL DEFAULT 0,
    sold_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    reserved_count  INT UNSIGNED    NOT NULL DEFAULT 0,
    blocked_count   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Bloqué (groupes, VIP)',
    waitlist_count  INT UNSIGNED    NOT NULL DEFAULT 0,
    price_fcfa      INT UNSIGNED    NOT NULL,
    base_price_fcfa INT UNSIGNED    NOT NULL COMMENT 'Prix avant ajustement yield',
    bid_price_fcfa  INT UNSIGNED    NULL COMMENT 'Prix marginal yield',
    overbooking_pct INT UNSIGNED    NOT NULL DEFAULT 0,
    last_price_change_at DATETIME   NULL,
    last_price_reason VARCHAR(120)  NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_trip_class (trip_id, class_id),
    INDEX idx_trip_inv_trip (trip_id),
    INDEX idx_trip_inv_class (class_code),
    CONSTRAINT fk_ti_trip  FOREIGN KEY (trip_id)  REFERENCES trips(id)             ON DELETE CASCADE,
    CONSTRAINT fk_ti_class FOREIGN KEY (class_id) REFERENCES inventory_classes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien tickets ↔ classe
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS inventory_class_code CHAR(2) NULL AFTER passenger_category,
    ADD INDEX IF NOT EXISTS idx_tickets_inv_class (inventory_class_code);

-- ─── 3. ARRÊTS DU VOYAGE (stop-by-stop) ─────────────────────
-- Snapshot des arrêts pour CE voyage (matérialisation depuis stops de la ligne)
-- Permet le tracking individuel sans modifier le référentiel
CREATE TABLE IF NOT EXISTS trip_stops (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    stop_id         BIGINT UNSIGNED NOT NULL COMMENT 'FK vers stops (référentiel)',
    sequence        INT UNSIGNED    NOT NULL COMMENT 'Ordre dans le voyage',
    -- Prévisionnel
    scheduled_arrival   DATETIME    NULL,
    scheduled_departure DATETIME    NULL,
    distance_from_origin_km DECIMAL(8,2) NULL,
    -- Réel
    actual_arrival      DATETIME    NULL,
    actual_departure    DATETIME    NULL,
    -- ETA dynamique (recalculée lors d'un retard)
    estimated_arrival   DATETIME    NULL,
    estimated_departure DATETIME    NULL,
    -- Mouvements PAX/Cargo
    pax_boarded         INT UNSIGNED NOT NULL DEFAULT 0,
    pax_alighted        INT UNSIGNED NOT NULL DEFAULT 0,
    parcels_loaded      INT UNSIGNED NOT NULL DEFAULT 0,
    parcels_unloaded    INT UNSIGNED NOT NULL DEFAULT 0,
    -- Métadonnées
    delay_inherited_min INT NOT NULL DEFAULT 0 COMMENT 'Retard hérité de l''arrêt précédent',
    delay_added_min     INT NOT NULL DEFAULT 0 COMMENT 'Retard généré ici',
    notes               TEXT NULL,
    is_skipped          TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Arrêt sauté (vide / fermé)',
    skip_reason         VARCHAR(120) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_trip_stop_seq (trip_id, sequence),
    UNIQUE KEY uk_trip_stop (trip_id, stop_id),
    INDEX idx_trip_stops_trip (trip_id, sequence),
    INDEX idx_trip_stops_stop (stop_id),
    CONSTRAINT fk_ts_trip FOREIGN KEY (trip_id) REFERENCES trips(id)  ON DELETE CASCADE,
    CONSTRAINT fk_ts_stop FOREIGN KEY (stop_id) REFERENCES stops(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. JOURNAL D'ÉVÉNEMENTS PROGRESSIFS ────────────────────
CREATE TABLE IF NOT EXISTS trip_progress_events (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    stop_id         BIGINT UNSIGNED NULL,
    event_type      ENUM(
        'departure_origin','arrived_at_stop','departed_stop','arrived_destination',
        'boarding_started','boarding_priority','boarding_general','boarding_closed',
        'pax_boarded','pax_alighted','no_show',
        'parcel_loaded','parcel_unloaded',
        'fueling_start','fueling_end',
        'rest_break_start','rest_break_end',
        'incident_reported','incident_cleared',
        'eta_recalculated',
        'manual_note'
    ) NOT NULL,
    occurred_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actor_id        BIGINT UNSIGNED NULL,
    actor_label     VARCHAR(120) NULL,
    location_name   VARCHAR(150) NULL,
    location_lat    DECIMAL(10,7) NULL,
    location_lng    DECIMAL(10,7) NULL,
    metadata_json   JSON NULL,
    notes           TEXT NULL,
    PRIMARY KEY (id),
    INDEX idx_tpe_trip_time (trip_id, occurred_at),
    INDEX idx_tpe_event (event_type, occurred_at),
    INDEX idx_tpe_stop (stop_id),
    CONSTRAINT fk_tpe_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tpe_user FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tpe_stop FOREIGN KEY (stop_id) REFERENCES stops(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. SUB-STATES OPÉRATIONNELS ────────────────────────────
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS sub_status VARCHAR(40) NULL COMMENT 'boarding_open, at_stop_X, mechanical…' AFTER status,
    ADD COLUMN IF NOT EXISTS on_block_at DATETIME NULL COMMENT 'Bus arrivé au quai' AFTER closed_at,
    ADD COLUMN IF NOT EXISTS off_block_at DATETIME NULL COMMENT 'Bus quitte le quai',
    ADD COLUMN IF NOT EXISTS block_minutes INT NULL COMMENT 'Durée totale bus immobilisé (off_block - on_block)',
    ADD COLUMN IF NOT EXISTS current_stop_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS next_stop_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS current_eta DATETIME NULL COMMENT 'ETA actualisée pour la destination finale',
    ADD COLUMN IF NOT EXISTS departure_terminal VARCHAR(40) NULL COMMENT 'Quai/terminal de départ',
    ADD COLUMN IF NOT EXISTS arrival_terminal VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS briefing_pdf_path VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS briefing_generated_at DATETIME NULL,
    ADD INDEX IF NOT EXISTS idx_trips_substatus (sub_status);

ALTER TABLE trips
    ADD CONSTRAINT fk_trip_current_stop FOREIGN KEY (current_stop_id) REFERENCES stops(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trip_next_stop    FOREIGN KEY (next_stop_id)    REFERENCES stops(id) ON DELETE SET NULL;

-- ─── 6. PERMISSIONS ────────────────────────────────────────
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('voyages.inventory.view',   'voyages', 'view',          'Voir l''inventaire (classes)',  460),
    ('voyages.inventory.manage', 'voyages', 'manage',        'Gérer l''inventaire',           461),
    ('voyages.boarding.control', 'voyages', 'boarding',      'Contrôle d''embarquement',     462),
    ('voyages.tracking.update',  'voyages', 'tracking',      'Mettre à jour la progression', 463),
    ('voyages.briefing.view',    'voyages', 'briefing_view', 'Voir le briefing voyage',      464),
    ('voyages.briefing.print',   'voyages', 'briefing_print','Imprimer le briefing',         465),
    ('ops.occ.view',             'ops',     'occ_view',      'Voir Operations Control',     470),
    ('public.departures.view',   'public',  'departures',    'Voir tableau d''affichage',    471)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('voyages.inventory.view','voyages.inventory.manage','voyages.boarding.control',
     'voyages.tracking.update','voyages.briefing.view','voyages.briefing.print',
     'ops.occ.view','public.departures.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Caissiers / agents : embarquement + briefing view
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('caissier','agent','chef_agence') AND p.slug IN
    ('voyages.boarding.control','voyages.briefing.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ─── 7. SETTINGS ───────────────────────────────────────────
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('voyage.inventory.enabled',                'voyage', 'boolean', '1',  'Booking classes activées',           'Active la gestion multi-classes (Y/B/M/H/L) par voyage.', 200, 0),
('voyage.inventory.default_classes',        'voyage', 'string',  'Y,B,M,H,L', 'Classes par défaut',          'Codes de classes activées par défaut au create.',         201, 0),
('voyage.boarding.opens_minutes_before',    'voyage', 'integer', '60', 'Embarquement ouvre (min)',           'Combien de minutes avant le départ.',                      210, 0),
('voyage.boarding.priority_minutes_before', 'voyage', 'integer', '45', 'Embarquement prioritaire (min)',     'Embarquement classes Y et B uniquement.',                  211, 0),
('voyage.boarding.closes_minutes_before',   'voyage', 'integer', '5',  'Embarquement ferme (min)',           'Plus aucun embarquement après ce délai.',                  212, 0),
('voyage.tracking.eta_recalc_enabled',      'voyage', 'boolean', '1',  'Recalcul auto ETA',                   'Recalcule les ETA des arrêts suivants en cas de retard.',  220, 0),
('voyage.tracking.delay_notify_threshold',  'voyage', 'integer', '15', 'Seuil notif retard (min)',           'Au-delà : SMS auto aux passagers en aval.',                221, 0),
('voyage.briefing.auto_generate',           'voyage', 'boolean', '1',  'Briefing auto-généré',                'Génère le briefing PDF à H-2h du départ.',                 230, 0),
('voyage.briefing.required_for_departure',  'voyage', 'boolean', '0',  'Briefing requis pour départ',         'Bloque la transition vers en_route si pas de briefing imprimé.', 231, 0),
('public.departures.refresh_seconds',       'public', 'integer', '30', 'Refresh tableau départs (s)',         '',                                                          240, 0),
('public.departures.window_hours',          'public', 'integer', '6',  'Fenêtre tableau départs (h)',         'Affiche les voyages des prochaines N heures.',             241, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
