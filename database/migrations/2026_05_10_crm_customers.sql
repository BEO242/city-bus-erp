-- CRM passagers (GAP-02)
-- Dossier client unifié, dédupliqué par numéro de téléphone normalisé.

CREATE TABLE IF NOT EXISTS customers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    phone_norm      VARCHAR(20)     NOT NULL COMMENT 'Numéro normalisé (chiffres uniquement, +indicatif)',
    phone_display   VARCHAR(30)     NULL,
    first_name      VARCHAR(80)     NULL,
    last_name       VARCHAR(80)     NULL,
    email           VARCHAR(120)    NULL,
    id_doc_type     ENUM('cni','passeport','permis','autre') NULL,
    id_doc_number   VARCHAR(50)     NULL,
    date_of_birth   DATE            NULL,
    gender          ENUM('M','F','O') NULL,
    notes           TEXT            NULL,

    -- Compteurs (mis à jour par triggers/jobs)
    total_trips     INT UNSIGNED    NOT NULL DEFAULT 0,
    total_spent     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_baggage   INT UNSIGNED    NOT NULL DEFAULT 0,
    total_parcels   INT UNSIGNED    NOT NULL DEFAULT 0,
    last_trip_at    DATETIME        NULL,
    first_trip_at   DATETIME        NULL,

    -- Préférences
    preferred_seat        VARCHAR(10)  NULL,
    preferred_contact     ENUM('sms','email','call','whatsapp') NULL DEFAULT 'sms',
    sms_opt_in            TINYINT(1)   NOT NULL DEFAULT 1,
    email_opt_in          TINYINT(1)   NOT NULL DEFAULT 1,

    -- Méta
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_customers_phone_norm (phone_norm),
    INDEX idx_customers_email (email),
    INDEX idx_customers_last_trip (last_trip_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien tickets ↔ customers
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS customer_id BIGINT UNSIGNED NULL AFTER passenger_phone,
    ADD INDEX idx_tickets_customer (customer_id),
    ADD CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- Lien baggage_tickets ↔ customers
ALTER TABLE baggage_tickets
    ADD COLUMN IF NOT EXISTS customer_id BIGINT UNSIGNED NULL,
    ADD INDEX idx_bag_customer (customer_id),
    ADD CONSTRAINT fk_bag_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('crm.view', 'crm', 'view', 'Voir le CRM clients', 250),
    ('crm.edit', 'crm', 'edit', 'Modifier les clients', 251),
    ('crm.export', 'crm', 'export', 'Exporter le CRM', 252)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation','chef_agence') AND p.slug LIKE 'crm.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('crm.enabled',          'crm', 'boolean', '1', 'CRM activé', 'Active la création/déduplication automatique des dossiers clients à la vente.', 260, 0),
('crm.dedup_strategy',   'crm', 'string',  'phone_normalized', 'Stratégie de déduplication', 'phone_normalized | email | name_phone', 261, 0),
('crm.country_code',     'crm', 'string',  '+242', 'Indicatif pays par défaut', 'Préfixe ajouté aux numéros locaux pour normalisation.', 262, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
