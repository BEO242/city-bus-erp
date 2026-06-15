-- =============================================================
-- V4.B — CRM passager unifié + programme fidélité
-- =============================================================

ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS frequent_flyer_number VARCHAR(20) UNIQUE NULL AFTER email,
    ADD COLUMN IF NOT EXISTS tier ENUM('basic','silver','gold','platinum') NOT NULL DEFAULT 'basic' AFTER frequent_flyer_number,
    ADD COLUMN IF NOT EXISTS tier_qualifying_until DATE NULL AFTER tier,
    ADD COLUMN IF NOT EXISTS total_segments INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_trips,
    ADD COLUMN IF NOT EXISTS total_distance_km INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_segments,
    ADD COLUMN IF NOT EXISTS points_lifetime INT NOT NULL DEFAULT 0 AFTER total_spent,
    ADD COLUMN IF NOT EXISTS preferences JSON NULL AFTER preferred_contact,
    ADD COLUMN IF NOT EXISTS data_consent_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS marketing_opt_in TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS rfm_recency TINYINT NULL COMMENT '1-5 RFM scoring',
    ADD COLUMN IF NOT EXISTS rfm_frequency TINYINT NULL,
    ADD COLUMN IF NOT EXISTS rfm_monetary TINYINT NULL,
    ADD INDEX IF NOT EXISTS idx_customers_tier (tier);

CREATE TABLE IF NOT EXISTS customer_history_segments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id     BIGINT UNSIGNED NOT NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    segment_id      BIGINT UNSIGNED NULL COMMENT 'reservation_items.id',
    ticket_id       BIGINT UNSIGNED NULL,
    trip_id         BIGINT UNSIGNED NULL,
    flown_at        DATETIME NOT NULL,
    distance_km     INT UNSIGNED NOT NULL DEFAULT 0,
    revenue_fcfa    INT NOT NULL DEFAULT 0,
    booking_class   CHAR(1) NULL,
    points_earned   INT NOT NULL DEFAULT 0,
    INDEX idx_chs_customer (customer_id, flown_at DESC),
    INDEX idx_chs_trip (trip_id),
    CONSTRAINT fk_chs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- (loyalty_transactions existe déjà dans le schéma de base — pas de doublon)

CREATE TABLE IF NOT EXISTS customer_complaints (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id     BIGINT UNSIGNED NOT NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    trip_id         BIGINT UNSIGNED NULL,
    category        ENUM('delay','baggage','staff','comfort','price','safety','overbooking','other') NOT NULL,
    severity        ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status          ENUM('open','investigating','resolved','closed','escalated') NOT NULL DEFAULT 'open',
    description     TEXT NOT NULL,
    resolution      TEXT NULL,
    compensation_fcfa INT NOT NULL DEFAULT 0,
    voucher_code    VARCHAR(40) NULL,
    assigned_to     BIGINT UNSIGNED NULL,
    opened_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at     TIMESTAMP NULL,
    closed_at       TIMESTAMP NULL,
    INDEX idx_cc_customer (customer_id, opened_at DESC),
    INDEX idx_cc_status (status, severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loyalty_tiers (
    code            VARCHAR(20) PRIMARY KEY,
    label           VARCHAR(60) NOT NULL,
    min_points_year INT NOT NULL DEFAULT 0,
    min_segments_year INT NOT NULL DEFAULT 0,
    points_multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT '1.5 = +50% pts',
    priority_boarding TINYINT(1) NOT NULL DEFAULT 0,
    free_baggage_kg TINYINT NOT NULL DEFAULT 0,
    voucher_birthday_fcfa INT NOT NULL DEFAULT 0,
    color_hex       VARCHAR(7) NOT NULL DEFAULT '#94a3b8'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO loyalty_tiers (code, label, min_points_year, min_segments_year, points_multiplier, priority_boarding, free_baggage_kg, voucher_birthday_fcfa, color_hex) VALUES
  ('basic',    'Basic',    0,    0,  1.0, 0, 0,    0,    '#94a3b8'),
  ('silver',   'Silver',   500,  10, 1.25, 1, 5,   2000, '#cbd5e1'),
  ('gold',     'Gold',     1500, 25, 1.5,  1, 10,  5000, '#fbbf24'),
  ('platinum', 'Platinum', 3000, 50, 2.0,  1, 20,  15000,'#1e293b')
ON DUPLICATE KEY UPDATE code = code;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('crm.customers.view',     'crm', 'cust_view',    'Voir fiches clients', 400),
  ('crm.customers.edit',     'crm', 'cust_edit',    'Éditer fiches clients', 401),
  ('crm.customers.merge',    'crm', 'cust_merge',   'Fusionner doublons clients', 402),
  ('crm.loyalty.view',       'crm', 'loy_view',     'Voir fidélité', 410),
  ('crm.loyalty.adjust',     'crm', 'loy_adjust',   'Ajuster points fidélité', 411),
  ('crm.complaints.view',    'crm', 'comp_view',    'Voir réclamations', 420),
  ('crm.complaints.manage',  'crm', 'comp_manage',  'Gérer réclamations + compensation', 421)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('loyalty.points_per_fcfa',     'crm','decimal','0.01','Points par FCFA dépensé', '1 FCFA = 0.01 pt par défaut', 500, 0),
  ('loyalty.points_expiry_months','crm','integer','24',  'Expiration des points (mois)', '', 501, 0),
  ('loyalty.tier_recompute_hour', 'crm','integer','3',   'Heure recalc tier (cron)', '0-23', 502, 0),
  ('crm.dedupe_phone_e164',       'crm','boolean','1',   'Normaliser téléphones E.164', '', 510, 0),
  ('crm.country_code_default',    'crm','string','+242', 'Indicatif pays défaut',     'Pour normalisation', 511, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
