-- Yield management, comptes corporate, partenaires (GAP-05, GAP-09, GAP-25)

-- ─── Règles de tarification dynamique (yield) ──────────────────────
CREATE TABLE IF NOT EXISTS pricing_rules (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key        VARCHAR(60)     NOT NULL,
    label           VARCHAR(120)    NOT NULL,
    line_id         BIGINT UNSIGNED NULL COMMENT 'NULL = toutes lignes',
    rule_type       ENUM('early_bird','last_minute','peak_day','off_peak','load_factor','date_range') NOT NULL,
    days_before_min INT             NULL,
    days_before_max INT             NULL,
    days_of_week    VARCHAR(20)     NULL COMMENT 'CSV 1-7',
    load_factor_min INT             NULL COMMENT '% min pour déclencher',
    date_from       DATE            NULL,
    date_until      DATE            NULL,
    adjustment_type ENUM('percent_discount','percent_surcharge','fixed_discount','fixed_surcharge') NOT NULL,
    adjustment_value DECIMAL(10,2)  NOT NULL,
    priority        INT             NOT NULL DEFAULT 100,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pr_key (rule_key),
    INDEX idx_pr_active (is_active, line_id),
    CONSTRAINT fk_pr_line FOREIGN KEY (line_id) REFERENCES bus_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pricing_rules (rule_key, label, rule_type, days_before_min, days_before_max, adjustment_type, adjustment_value, priority) VALUES
    ('early_bird_7d',  'Early bird J-7+',     'early_bird',  7,  NULL, 'percent_discount',  10, 50),
    ('last_minute_1d', 'Last minute J-0/J-1', 'last_minute', 0,  1,    'percent_surcharge', 15, 60),
    ('weekend_peak',   'Surcharge week-end',  'peak_day',    NULL,NULL,'percent_surcharge', 20, 70)
ON DUPLICATE KEY UPDATE rule_key = rule_key;
UPDATE pricing_rules SET days_of_week = '6,7' WHERE rule_key = 'weekend_peak';

-- ─── Comptes corporate B2B ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS corporate_accounts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name    VARCHAR(150)    NOT NULL,
    legal_id        VARCHAR(60)     NULL,
    contact_name    VARCHAR(120)    NOT NULL,
    contact_phone   VARCHAR(30)     NOT NULL,
    contact_email   VARCHAR(120)    NULL,
    address         VARCHAR(255)    NULL,
    discount_percent DECIMAL(5,2)   NOT NULL DEFAULT 0,
    credit_limit_fcfa BIGINT UNSIGNED NOT NULL DEFAULT 0,
    current_balance_fcfa BIGINT     NOT NULL DEFAULT 0 COMMENT 'Solde dû (positif)',
    payment_terms_days INT UNSIGNED NOT NULL DEFAULT 30,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    notes           TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_corp_active (is_active),
    INDEX idx_corp_phone (contact_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS corporate_invoices (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    corporate_id    BIGINT UNSIGNED NOT NULL,
    invoice_number  VARCHAR(40)     NOT NULL,
    period_from     DATE            NOT NULL,
    period_to       DATE            NOT NULL,
    total_ht_fcfa   INT UNSIGNED    NOT NULL DEFAULT 0,
    total_tax_fcfa  INT UNSIGNED    NOT NULL DEFAULT 0,
    total_ttc_fcfa  INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    due_date        DATE            NULL,
    paid_at         DATETIME        NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_corp_inv_number (invoice_number),
    INDEX idx_corp_inv_corp (corporate_id, status),
    CONSTRAINT fk_ci_corp FOREIGN KEY (corporate_id) REFERENCES corporate_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien tickets ↔ corporate
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS corporate_id BIGINT UNSIGNED NULL,
    ADD INDEX idx_tickets_corporate (corporate_id);

-- ─── Partenaires commerciaux (revendeurs) ─────────────────────────
CREATE TABLE IF NOT EXISTS sales_partners (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)    NOT NULL,
    code            VARCHAR(40)     NOT NULL,
    contact_name    VARCHAR(120)    NULL,
    contact_phone   VARCHAR(30)     NULL,
    contact_email   VARCHAR(120)    NULL,
    commission_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    payout_schedule ENUM('weekly','bi_weekly','monthly') NOT NULL DEFAULT 'monthly',
    bank_details    TEXT            NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_partner_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS partner_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS partner_commission_fcfa INT UNSIGNED NULL,
    ADD INDEX idx_tickets_partner (partner_id);

CREATE TABLE IF NOT EXISTS partner_payouts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_id      BIGINT UNSIGNED NOT NULL,
    period_from     DATE            NOT NULL,
    period_to       DATE            NOT NULL,
    tickets_count   INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_fcfa    INT UNSIGNED    NOT NULL DEFAULT 0,
    commission_fcfa INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('pending','paid','disputed') NOT NULL DEFAULT 'pending',
    paid_at         DATETIME        NULL,
    notes           TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pp_partner (partner_id, period_from),
    CONSTRAINT fk_pp_partner FOREIGN KEY (partner_id) REFERENCES sales_partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('pricing.rules.view', 'pricing', 'view', 'Voir règles tarifaires', 340),
    ('pricing.rules.manage', 'pricing', 'manage', 'Gérer règles tarifaires', 341),
    ('corporate.view', 'corporate', 'view', 'Voir comptes corporate', 342),
    ('corporate.manage', 'corporate', 'manage', 'Gérer comptes corporate', 343),
    ('partners.view', 'partners', 'view', 'Voir partenaires', 344),
    ('partners.manage', 'partners', 'manage', 'Gérer partenaires', 345)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug IN
    ('pricing.rules.view','pricing.rules.manage','corporate.view','corporate.manage','partners.view','partners.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('pricing.yield_enabled',  'commerce', 'boolean', '1',   'Yield management activé', 'Active la tarification dynamique selon les règles définies.', 350, 0),
('pricing.peak_surcharge_pct', 'commerce', 'integer', '20', 'Surcharge week-end (%)', '', 351, 0),
('pricing.early_bird_pct',     'commerce', 'integer', '10', 'Réduction anticipation (%)', '', 352, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
