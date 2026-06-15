-- TVA & facturation fiscale conforme (GAP-21)
-- Permet de stocker les taux applicables, ventiler HT/TVA/TTC et générer des factures séquentielles.

CREATE TABLE IF NOT EXISTS tax_rates (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(20)     NOT NULL COMMENT 'Code court ex: TVA-18, EXO',
    label         VARCHAR(100)    NOT NULL,
    rate_percent  DECIMAL(6,3)    NOT NULL DEFAULT 0,
    is_default    TINYINT(1)      NOT NULL DEFAULT 0,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    valid_from    DATE            NULL,
    valid_until   DATE            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tax_rates_code (code),
    INDEX idx_tax_rates_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tax_rates (code, label, rate_percent, is_default, is_active) VALUES
    ('EXO',    'Exonéré',                       0.000, 0, 1),
    ('TVA-0',  'TVA 0 % (export)',              0.000, 0, 1),
    ('TVA-5',  'TVA 5 % (réduit)',              5.000, 0, 1),
    ('TVA-18', 'TVA 18 % (taux normal Congo)', 18.000, 1, 1)
ON DUPLICATE KEY UPDATE code = code;

-- Compteur séquentiel pour numéros de facture (par année + agence)
CREATE TABLE IF NOT EXISTS invoice_sequences (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `year`      SMALLINT UNSIGNED NOT NULL,
    agency_id   BIGINT UNSIGNED NULL,
    next_number INT UNSIGNED    NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_inv_seq (`year`, agency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout colonnes fiscales sur tickets (idempotent)
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS price_ht_fcfa     INT UNSIGNED NULL AFTER price_fcfa,
    ADD COLUMN IF NOT EXISTS tax_rate_id       BIGINT UNSIGNED NULL AFTER price_ht_fcfa,
    ADD COLUMN IF NOT EXISTS tax_rate_percent  DECIMAL(6,3) NULL AFTER tax_rate_id,
    ADD COLUMN IF NOT EXISTS tax_amount_fcfa   INT UNSIGNED NULL AFTER tax_rate_percent,
    ADD COLUMN IF NOT EXISTS invoice_number    VARCHAR(30)  NULL AFTER tax_amount_fcfa,
    ADD INDEX idx_tickets_invoice (invoice_number);

-- Ajout colonnes fiscales sur baggage_tickets
ALTER TABLE baggage_tickets
    ADD COLUMN IF NOT EXISTS price_ht_fcfa     INT UNSIGNED NULL AFTER total_price_fcfa,
    ADD COLUMN IF NOT EXISTS tax_rate_id       BIGINT UNSIGNED NULL AFTER price_ht_fcfa,
    ADD COLUMN IF NOT EXISTS tax_rate_percent  DECIMAL(6,3) NULL AFTER tax_rate_id,
    ADD COLUMN IF NOT EXISTS tax_amount_fcfa   INT UNSIGNED NULL AFTER tax_rate_percent,
    ADD COLUMN IF NOT EXISTS invoice_number    VARCHAR(30)  NULL AFTER tax_amount_fcfa;

-- Ajout colonne tax_rate_id sur tariffs
ALTER TABLE tariffs
    ADD COLUMN IF NOT EXISTS tax_rate_id BIGINT UNSIGNED NULL,
    ADD CONSTRAINT fk_tariffs_tax FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL;

-- Permission
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('finance.tax.view', 'finance', 'view', 'Voir les déclarations fiscales', 200),
    ('finance.tax.export', 'finance', 'export', 'Exporter les déclarations fiscales', 201)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'finance.tax.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('tax.default_rate_id',     'finance', 'integer', '4',  'Taux TVA par défaut', 'ID du taux applicable aux ventes par défaut.', 200, 0),
('tax.prices_include_tax',  'finance', 'boolean', '1',  'Prix TTC',            'Si activé, les prix saisis sont considérés TTC ; sinon HT.', 201, 0),
('tax.invoice_prefix',      'finance', 'string',  'FCT', 'Préfixe facture',     'Préfixe utilisé dans le numéro de facture (FCT-AAAA-NNNNNN).', 202, 0),
('tax.legal_mention',       'finance', 'string',  'TVA non applicable - Article 293-B du CGI Congo', 'Mention fiscale ticket', 'Texte affiché sur les tickets/factures.', 203, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
