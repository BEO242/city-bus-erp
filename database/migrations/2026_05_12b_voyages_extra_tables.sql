-- Complément migration voyages : tables annexes manquantes
-- (la migration principale s'est arrêtée après les ALTER TABLE)

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

CREATE TABLE IF NOT EXISTS trip_costs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    cost_type     ENUM('peage','carburant','parking','prime_chauffeur','prime_convoyeur','reparation','amende','divers') NOT NULL,
    amount_fcfa   INT UNSIGNED NOT NULL,
    description   VARCHAR(255) NULL,
    receipt_path  VARCHAR(255) NULL,
    paid_at       DATETIME NULL,
    paid_by       BIGINT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by    BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tc_trip (trip_id),
    INDEX idx_tc_type (cost_type, paid_at),
    CONSTRAINT fk_tc_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trip_messages (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    channel       ENUM('sms','email','push','whatsapp','call') NOT NULL DEFAULT 'sms',
    audience      ENUM('all_passengers','crew','specific','agency','admin') NOT NULL DEFAULT 'all_passengers',
    recipient     VARCHAR(150) NULL,
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

-- Permissions (idempotent)
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

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('voyages.delete','voyages.lock_manifest','voyages.unlock_manifest',
     'voyages.replace_bus','voyages.replace_driver','voyages.communicate',
     'voyages.export','voyages.view_pnl','voyages.view_audit',
     'voyages.dispute.manage','voyages.documents.upload','voyages.costs.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings (idempotent)
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('voyage.delay_tolerance_minutes',         'voyage', 'integer', '15',  'Tolérance retard (min)',               '', 100, 0),
('voyage.conflict_buffer_hours',           'voyage', 'integer', '2',   'Marge conflit horaire (h)',            '', 101, 0),
('voyage.lock_sales_after_departure_min',  'voyage', 'integer', '15',  'Verrouiller ventes après départ (min)','', 102, 0),
('voyage.require_inspection_for_departure','voyage', 'boolean', '0',   'Pré-vérification obligatoire',         '', 103, 0),
('voyage.auto_boarding_minutes',           'voyage', 'integer', '30',  'Auto-embarquement à H-X min',          '', 104, 0),
('voyage.detect_signal_lost_minutes',      'voyage', 'integer', '15',  'Signal GPS perdu (min)',               '', 106, 0),
('voyage.allow_same_day_creation_only',    'voyage', 'boolean', '0',   'Création voyage J+0 uniquement',       '', 107, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
