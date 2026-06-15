-- Liste d'attente, codes promo, fidélité et avoirs (GAP-06, GAP-08, GAP-12)

-- ─── Liste d'attente (GAP-12) ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS waitlist_entries (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    passenger_name  VARCHAR(120)    NOT NULL,
    passenger_phone VARCHAR(30)     NOT NULL,
    seats_requested INT UNSIGNED    NOT NULL DEFAULT 1,
    position        INT UNSIGNED    NOT NULL,
    status          ENUM('waiting','notified','converted','expired','cancelled') NOT NULL DEFAULT 'waiting',
    requested_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notified_at     DATETIME        NULL,
    confirmation_deadline DATETIME  NULL,
    converted_ticket_id   BIGINT UNSIGNED NULL,
    notes           VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    INDEX idx_wl_trip (trip_id, status, position),
    INDEX idx_wl_customer (customer_id),
    INDEX idx_wl_phone (passenger_phone),
    CONSTRAINT fk_wl_trip     FOREIGN KEY (trip_id)             REFERENCES trips(id)     ON DELETE CASCADE,
    CONSTRAINT fk_wl_customer FOREIGN KEY (customer_id)         REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_wl_ticket   FOREIGN KEY (converted_ticket_id) REFERENCES tickets(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Codes promo (GAP-06) ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS promo_codes (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                 VARCHAR(40)     NOT NULL,
    label                VARCHAR(120)    NOT NULL,
    discount_type        ENUM('percent','fixed','free_seat') NOT NULL DEFAULT 'percent',
    discount_value       INT UNSIGNED    NOT NULL DEFAULT 0,
    min_amount_fcfa      INT UNSIGNED    NOT NULL DEFAULT 0,
    max_discount_fcfa    INT UNSIGNED    NULL,
    max_uses             INT UNSIGNED    NULL,
    max_uses_per_customer INT UNSIGNED   NULL DEFAULT 1,
    used_count           INT UNSIGNED    NOT NULL DEFAULT 0,
    valid_from           DATETIME        NULL,
    valid_until          DATETIME        NULL,
    applicable_lines     JSON            NULL COMMENT 'Liste des line_id (vide = toutes)',
    applicable_categories JSON           NULL COMMENT 'passenger_category (vide = toutes)',
    is_active            TINYINT(1)      NOT NULL DEFAULT 1,
    created_by           BIGINT UNSIGNED NULL,
    created_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_promo_code (code),
    INDEX idx_promo_active (is_active, valid_from, valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promo_redemptions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    promo_id      BIGINT UNSIGNED NOT NULL,
    customer_id   BIGINT UNSIGNED NULL,
    ticket_id     BIGINT UNSIGNED NULL,
    discount_fcfa INT UNSIGNED    NOT NULL,
    used_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pr_promo (promo_id),
    INDEX idx_pr_customer (customer_id),
    CONSTRAINT fk_pr_promo    FOREIGN KEY (promo_id)    REFERENCES promo_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_customer FOREIGN KEY (customer_id) REFERENCES customers(id)   ON DELETE SET NULL,
    CONSTRAINT fk_pr_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Programme fidélité (GAP-06) ────────────────────────────────────
ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS loyalty_points INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS loyalty_tier   ENUM('standard','silver','gold','platinum') NOT NULL DEFAULT 'standard';

CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    points_delta INT            NOT NULL COMMENT 'Positif = gain, négatif = utilisation',
    balance_after INT UNSIGNED  NOT NULL,
    reason      VARCHAR(120)    NOT NULL,
    ticket_id   BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_loyalty_customer (customer_id, created_at),
    CONSTRAINT fk_loyalty_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_loyalty_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Avoirs / vouchers (GAP-08 disruption) ──────────────────────────
CREATE TABLE IF NOT EXISTS vouchers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(40)     NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    issued_amount   INT UNSIGNED    NOT NULL,
    remaining_amount INT UNSIGNED   NOT NULL,
    reason          VARCHAR(255)    NULL,
    source_trip_id  BIGINT UNSIGNED NULL COMMENT 'Voyage à l''origine (incident, annulation)',
    issued_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valid_until     DATE            NULL,
    used_at         TIMESTAMP       NULL,
    used_on_ticket  BIGINT UNSIGNED NULL,
    is_void         TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_voucher_code (code),
    INDEX idx_voucher_customer (customer_id),
    CONSTRAINT fk_v_customer FOREIGN KEY (customer_id)    REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_v_trip     FOREIGN KEY (source_trip_id) REFERENCES trips(id)     ON DELETE SET NULL,
    CONSTRAINT fk_v_ticket   FOREIGN KEY (used_on_ticket) REFERENCES tickets(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Permissions et settings ────────────────────────────────────────
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('waitlist.view',   'waitlist', 'view',   'Voir liste d''attente',  270),
    ('waitlist.manage', 'waitlist', 'manage', 'Gérer liste d''attente', 271),
    ('promo.view', 'promo', 'view', 'Voir codes promo', 280),
    ('promo.manage', 'promo', 'manage', 'Gérer codes promo', 281),
    ('vouchers.view', 'vouchers', 'view', 'Voir avoirs', 282),
    ('vouchers.issue', 'vouchers', 'issue', 'Émettre un avoir', 283)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('waitlist.view','waitlist.manage','promo.view','promo.manage','vouchers.view','vouchers.issue')
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('waitlist.confirmation_minutes', 'billetterie', 'integer', '30', 'Délai confirmation liste d''attente', 'Minutes laissées au passager notifié pour confirmer.', 290, 0),
('loyalty.enabled',               'commerce',   'boolean', '1',   'Programme fidélité activé', '', 291, 0),
('loyalty.points_per_fcfa',       'commerce',   'decimal', '0.001', 'Points / FCFA dépensé', 'Points gagnés par FCFA dépensé. 0.001 = 1 point pour 1000 FCFA.', 292, 0),
('loyalty.points_to_fcfa',        'commerce',   'integer', '10', 'FCFA par point', 'Conversion à l''utilisation : 1 point = X FCFA de remise.', 293, 0),
('loyalty.tier_silver_points',    'commerce',   'integer', '500',   'Seuil silver (points)', '', 294, 0),
('loyalty.tier_gold_points',      'commerce',   'integer', '2000',  'Seuil gold (points)', '', 295, 0),
('loyalty.tier_platinum_points',  'commerce',   'integer', '10000', 'Seuil platinum (points)', '', 296, 0),
('vouchers.default_validity_days','commerce',   'integer', '90',  'Durée validité avoir (jours)', '', 297, 0),
('disruption.auto_voucher_pct',   'commerce',   'integer', '100', 'Avoir auto en cas d''annulation (%)', '% du prix transformé en avoir lors d''annulation par opérateur.', 298, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
