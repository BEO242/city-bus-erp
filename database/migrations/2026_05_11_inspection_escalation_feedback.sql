-- Pre-trip inspection (GAP-14), Escalation matrix (GAP-15), Customer feedback (GAP-20)

-- ─── Pre-trip inspections ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pre_trip_inspections (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    bus_id          BIGINT UNSIGNED NOT NULL,
    driver_id       BIGINT UNSIGNED NULL,
    inspected_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    inspected_by    BIGINT UNSIGNED NULL,
    -- Checklist (boolean fields)
    fluids_ok       TINYINT(1)      NOT NULL DEFAULT 0,
    tires_ok        TINYINT(1)      NOT NULL DEFAULT 0,
    lights_ok       TINYINT(1)      NOT NULL DEFAULT 0,
    brakes_ok       TINYINT(1)      NOT NULL DEFAULT 0,
    extinguisher_ok TINYINT(1)      NOT NULL DEFAULT 0,
    first_aid_kit_ok TINYINT(1)     NOT NULL DEFAULT 0,
    triangle_vest_ok TINYINT(1)     NOT NULL DEFAULT 0,
    seat_belts_ok   TINYINT(1)      NOT NULL DEFAULT 0,
    cleanliness_ok  TINYINT(1)      NOT NULL DEFAULT 0,
    documents_ok    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Carte grise, assurance, contrôle technique',
    -- Décision
    overall_status  ENUM('pass','pass_with_remarks','fail') NOT NULL DEFAULT 'pass',
    remarks         TEXT            NULL,
    odometer_km     INT UNSIGNED    NULL,
    fuel_level_pct  INT UNSIGNED    NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_inspection_trip (trip_id),
    INDEX idx_inspection_bus (bus_id),
    CONSTRAINT fk_insp_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_insp_bus  FOREIGN KEY (bus_id)  REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Matrice d'escalade incidents ──────────────────────────────────
CREATE TABLE IF NOT EXISTS escalation_rules (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    incident_type   VARCHAR(40)     NULL COMMENT 'NULL = tous types',
    min_severity    ENUM('mineur','modere','grave','critique') NOT NULL DEFAULT 'grave',
    notify_emails   TEXT            NULL COMMENT 'Liste séparée par , ou \\n',
    notify_phones   TEXT            NULL,
    notify_role_slugs VARCHAR(255)  NULL COMMENT 'CSV de rôles à notifier',
    delay_minutes   INT             NOT NULL DEFAULT 0,
    label           VARCHAR(120)    NOT NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_esc_active (is_active, min_severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO escalation_rules (incident_type, min_severity, label, notify_role_slugs) VALUES
    (NULL,       'critique', 'Incidents critiques → admin + RAF + exploitation', 'admin,raf,exploitation'),
    ('accident', 'grave',    'Accidents graves → direction + HSE',               'admin,raf,exploitation'),
    ('vol',      'mineur',   'Vols → direction',                                  'admin,raf')
ON DUPLICATE KEY UPDATE incident_type = incident_type;

-- ─── Customer feedback ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS customer_feedback (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id     BIGINT UNSIGNED NULL,
    ticket_id       BIGINT UNSIGNED NULL,
    trip_id         BIGINT UNSIGNED NULL,
    nps_score       INT             NULL COMMENT 'Net Promoter Score 0-10',
    rating_overall  INT             NULL COMMENT '1-5',
    rating_punctuality INT          NULL,
    rating_comfort  INT             NULL,
    rating_driver   INT             NULL,
    rating_cleanliness INT          NULL,
    comment         TEXT            NULL,
    submitted_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    request_token   VARCHAR(64)     NULL COMMENT 'Lien unique pour soumettre l''avis',
    request_sent_at DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fb_token (request_token),
    INDEX idx_fb_customer (customer_id),
    INDEX idx_fb_trip (trip_id),
    INDEX idx_fb_submitted (submitted_at),
    CONSTRAINT fk_fb_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_fb_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE SET NULL,
    CONSTRAINT fk_fb_trip     FOREIGN KEY (trip_id)     REFERENCES trips(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('inspection.create', 'inspection', 'create', 'Créer pré-vérification', 380),
    ('inspection.view', 'inspection', 'view', 'Voir pré-vérifications', 381),
    ('escalation.manage', 'escalation', 'manage', 'Gérer matrice d''escalade', 382),
    ('feedback.view',     'feedback',   'view',   'Voir avis clients', 383),
    ('feedback.manage',   'feedback',   'manage', 'Gérer demandes d''avis', 384)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('inspection.create','inspection.view','escalation.manage','feedback.view','feedback.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('inspection.required_for_departure', 'voyage', 'boolean', '0', 'Pré-vérification obligatoire au départ', 'Si activé, le statut "embarquement" requiert une pré-vérification PASS.', 390, 0),
('feedback.auto_request_after_hours', 'crm', 'integer', '24', 'Délai envoi demande avis (heures)', 'Heures après clôture du voyage pour envoyer la demande d''avis.', 391, 0),
('feedback.auto_request_enabled',     'crm', 'boolean', '1',  'Demandes d''avis automatiques',       '', 392, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
