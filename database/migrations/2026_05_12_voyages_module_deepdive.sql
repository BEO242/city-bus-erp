-- ============================================================
-- Module Voyages — Audit en profondeur (10 mai 2026)
-- Couvre les 30 problèmes critiques + 40 importants identifiés.
-- ============================================================

-- ─── 1. Enrichissement table trips ───────────────────────────────
ALTER TABLE trips
    ADD COLUMN trip_type ENUM('commercial','affretement','interne','formation','test') NOT NULL DEFAULT 'commercial' AFTER trip_code,
    ADD COLUMN priority ENUM('normale','vip','express','convoi') NOT NULL DEFAULT 'normale' AFTER trip_type,
    ADD COLUMN public_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER priority,
    ADD COLUMN parent_trip_id BIGINT UNSIGNED NULL COMMENT 'Voyage parent si voyage de remplacement',
    ADD COLUMN delay_minutes INT NULL COMMENT 'Retard en minutes au départ',
    ADD COLUMN delay_reason ENUM('mecanique','traffic','meteo','accident','controle','retard_chauffeur','autre') NULL,
    ADD COLUMN distance_actual_km DECIMAL(8,2) NULL,
    ADD COLUMN fuel_consumed_liters DECIMAL(8,2) NULL,
    ADD COLUMN toll_amount_fcfa INT UNSIGNED NULL DEFAULT 0,
    ADD COLUMN external_reference VARCHAR(60) NULL COMMENT 'Réf bon de commande / agréeur',
    ADD COLUMN manifest_pdf_path VARCHAR(255) NULL,
    ADD COLUMN manifest_locked_at DATETIME NULL COMMENT 'Si renseigné, ventes verrouillées',
    ADD COLUMN manifest_locked_by BIGINT UNSIGNED NULL,
    ADD COLUMN seat_map_override_json JSON NULL COMMENT 'Sièges désactivés ponctuellement',
    ADD COLUMN weather_temp_celsius INT NULL,
    ADD COLUMN replaced_bus_id BIGINT UNSIGNED NULL COMMENT 'Si bus changé en cours',
    ADD COLUMN replaced_driver_id BIGINT UNSIGNED NULL,
    ADD COLUMN agency_origin_id BIGINT UNSIGNED NULL COMMENT 'Agence de départ (scoping)',
    ADD COLUMN agency_destination_id BIGINT UNSIGNED NULL,
    ADD COLUMN closed_at DATETIME NULL COMMENT 'Date/heure de clôture',
    ADD COLUMN closed_by BIGINT UNSIGNED NULL,
    ADD COLUMN cancelled_at DATETIME NULL,
    ADD COLUMN cancelled_by BIGINT UNSIGNED NULL;

-- Élargir l'enum statut pour couvrir tous les cas réels
ALTER TABLE trips
    MODIFY COLUMN status ENUM('planifie','valide','embarquement','en_route','arrive','cloture','incident','retourne','litige','annule')
                     NOT NULL DEFAULT 'planifie';

-- Index pour performance
CREATE INDEX idx_trips_date_status     ON trips (trip_date, status);
CREATE INDEX idx_trips_agency_origin   ON trips (agency_origin_id, trip_date);
CREATE INDEX idx_trips_agency_dest     ON trips (agency_destination_id, trip_date);
CREATE INDEX idx_trips_parent          ON trips (parent_trip_id);
CREATE INDEX idx_trips_status          ON trips (status);
CREATE INDEX idx_trips_priority        ON trips (priority);

-- FK pour parent_trip
ALTER TABLE trips
    ADD CONSTRAINT fk_trips_parent FOREIGN KEY (parent_trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trips_replaced_bus FOREIGN KEY (replaced_bus_id) REFERENCES buses(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trips_replaced_driver FOREIGN KEY (replaced_driver_id) REFERENCES employees(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trips_agency_origin FOREIGN KEY (agency_origin_id) REFERENCES agencies(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trips_agency_dest FOREIGN KEY (agency_destination_id) REFERENCES agencies(id) ON DELETE SET NULL;

-- Backfill agency_origin/destination depuis bus_lines + cities + agencies
-- Si une agence "principale" existe dans la ville de départ/arrivée de la ligne
UPDATE trips tr
JOIN bus_lines l ON l.id = tr.line_id
JOIN agencies ao ON ao.city_id = l.departure_city_id AND ao.type = 'principale' AND ao.is_active = 1
SET tr.agency_origin_id = ao.id
WHERE tr.agency_origin_id IS NULL;

UPDATE trips tr
JOIN bus_lines l ON l.id = tr.line_id
JOIN agencies ad ON ad.city_id = l.arrival_city_id AND ad.type = 'principale' AND ad.is_active = 1
SET tr.agency_destination_id = ad.id
WHERE tr.agency_destination_id IS NULL;

-- ─── 2. trip_status_log : audit dédié au cycle de vie ──────────────
CREATE TABLE IF NOT EXISTS trip_status_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    from_status   VARCHAR(40) NULL,
    to_status     VARCHAR(40) NOT NULL,
    reason        VARCHAR(255) NULL,
    metadata      JSON NULL,
    changed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by    BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tsl_trip (trip_id, changed_at),
    INDEX idx_tsl_to_status (to_status, changed_at),
    CONSTRAINT fk_tsl_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tsl_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. trip_documents : bordereaux / fichiers attachés ────────────
CREATE TABLE IF NOT EXISTS trip_documents (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    doc_type      ENUM('autorisation','bordereau_route','photo','rapport_incident','manifeste','autre') NOT NULL DEFAULT 'autre',
    title         VARCHAR(150) NOT NULL,
    file_path     VARCHAR(255) NOT NULL,
    file_size     INT UNSIGNED NULL,
    mime_type     VARCHAR(80) NULL,
    notes         TEXT NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by   BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_td_trip (trip_id, uploaded_at),
    CONSTRAINT fk_td_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_td_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. trip_disputes : litiges / réclamations ─────────────────────
CREATE TABLE IF NOT EXISTS trip_disputes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    ticket_id       BIGINT UNSIGNED NULL,
    parcel_id       BIGINT UNSIGNED NULL,
    type            ENUM('retard','perte_bagage','blessure','remboursement','reclamation_qualite','autre') NOT NULL DEFAULT 'autre',
    status          ENUM('ouvert','en_cours','resolu','rejete','escalade') NOT NULL DEFAULT 'ouvert',
    title           VARCHAR(180) NOT NULL,
    description     TEXT NOT NULL,
    claim_amount_fcfa INT UNSIGNED NULL,
    resolution_notes  TEXT NULL,
    refund_amount_fcfa INT UNSIGNED NULL,
    voucher_code    VARCHAR(40) NULL,
    opened_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opened_by       BIGINT UNSIGNED NULL,
    closed_at       DATETIME NULL,
    closed_by       BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_disp_trip (trip_id),
    INDEX idx_disp_status (status, opened_at),
    INDEX idx_disp_customer (customer_id),
    CONSTRAINT fk_disp_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_disp_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_disp_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE SET NULL,
    CONSTRAINT fk_disp_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. trip_costs : détail des coûts par voyage ───────────────────
CREATE TABLE IF NOT EXISTS trip_costs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    cost_type     ENUM('peage','carburant','parking','prime_chauffeur','prime_convoyeur','reparation','amende','divers') NOT NULL,
    amount_fcfa   INT UNSIGNED NOT NULL,
    description   VARCHAR(255) NULL,
    receipt_path  VARCHAR(255) NULL,
    paid_at       DATETIME NULL,
    paid_by       BIGINT UNSIGNED NULL COMMENT 'employee_id ou user_id',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by    BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tc_trip (trip_id),
    INDEX idx_tc_type (cost_type, paid_at),
    CONSTRAINT fk_tc_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 6. trip_messages : journal des notifications envoyées ─────────
CREATE TABLE IF NOT EXISTS trip_messages (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    channel       ENUM('sms','email','push','whatsapp','call') NOT NULL DEFAULT 'sms',
    audience      ENUM('all_passengers','crew','specific','agency','admin') NOT NULL DEFAULT 'all_passengers',
    recipient     VARCHAR(150) NULL COMMENT 'Téléphone/email si specific',
    subject       VARCHAR(200) NULL,
    body          TEXT NOT NULL,
    recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
    success_count INT UNSIGNED NOT NULL DEFAULT 0,
    sent_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_by       BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tm_trip (trip_id, sent_at),
    CONSTRAINT fk_tm_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tm_user FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 7. Permissions enrichies ──────────────────────────────────────
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('voyages.delete',           'voyages', 'delete',           'Supprimer un voyage',            440),
    ('voyages.lock_manifest',    'voyages', 'lock_manifest',    'Verrouiller manifeste/ventes',   441),
    ('voyages.unlock_manifest',  'voyages', 'unlock_manifest',  'Déverrouiller manifeste',        442),
    ('voyages.replace_bus',      'voyages', 'replace_bus',      'Changer le bus en cours',        443),
    ('voyages.replace_driver',   'voyages', 'replace_driver',   'Changer le chauffeur en cours',  444),
    ('voyages.communicate',      'voyages', 'communicate',      'Communiquer aux passagers',      445),
    ('voyages.export',           'voyages', 'export',           'Exporter listings voyages',      446),
    ('voyages.view_pnl',         'voyages', 'view_pnl',         'Voir P&L voyages',               447),
    ('voyages.view_audit',       'voyages', 'view_audit',       'Voir audit complet voyages',     448),
    ('voyages.dispute.manage',   'voyages', 'dispute_manage',   'Gérer litiges voyages',          449),
    ('voyages.documents.upload', 'voyages', 'documents_upload', 'Uploader documents voyages',     450),
    ('voyages.costs.manage',     'voyages', 'costs_manage',     'Gérer coûts voyage',             451)
ON DUPLICATE KEY UPDATE slug = slug;

-- Donner les nouvelles permissions à admin/raf/exploitation
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('voyages.delete','voyages.lock_manifest','voyages.unlock_manifest',
     'voyages.replace_bus','voyages.replace_driver','voyages.communicate',
     'voyages.export','voyages.view_pnl','voyages.view_audit',
     'voyages.dispute.manage','voyages.documents.upload','voyages.costs.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ─── 8. Settings nouveaux ──────────────────────────────────────────
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('voyage.delay_tolerance_minutes',       'voyage', 'integer', '15',  'Tolérance retard (min)',              'Au-delà : voyage marqué retardé.', 100, 0),
('voyage.conflict_buffer_hours',         'voyage', 'integer', '2',   'Marge conflit horaire (h)',           'Si arrivée non renseignée, on bloque sur N heures après le départ.', 101, 0),
('voyage.lock_sales_after_departure_min','voyage', 'integer', '15',  'Verrouiller ventes après départ (min)','Empêche la vente de tickets après ce délai post-départ. 0 = jamais auto.', 102, 0),
('voyage.require_inspection_for_departure','voyage','boolean', '0',  'Pré-vérification obligatoire',        'Bloque la transition vers en_route si aucune pré-vérif n''est PASS.', 103, 0),
('voyage.auto_boarding_minutes',         'voyage', 'integer', '30',  'Auto-embarquement à H-X min',         'Cron passe planifie→embarquement N min avant départ. 0 = désactivé.', 104, 0),
('voyage.auto_close_minutes',            'voyage', 'integer', '120', 'Auto-clôture (min)',                  'Voyages "arrive" depuis N min sont auto-clôturés. 0 = désactivé.', 105, 0),
('voyage.detect_signal_lost_minutes',    'voyage', 'integer', '15',  'Signal GPS perdu (min)',              'Crée un incident automatique si aucune position GPS depuis N min.', 106, 0),
('voyage.allow_same_day_creation_only',  'voyage', 'boolean', '0',   'Création voyage J+0 uniquement',     'Si activé, empêche la création de voyages dans le passé.', 107, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
