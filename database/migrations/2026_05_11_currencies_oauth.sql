-- Multi-devises (GAP-24) + OAuth2 API publique (GAP-34)

CREATE TABLE IF NOT EXISTS currencies (
    code         CHAR(3)         NOT NULL COMMENT 'ISO 4217',
    label        VARCHAR(60)     NOT NULL,
    symbol       VARCHAR(10)     NOT NULL,
    rate_to_base DECIMAL(15,6)   NOT NULL DEFAULT 1 COMMENT 'Taux vers la devise de base (FCFA)',
    is_base      TINYINT(1)      NOT NULL DEFAULT 0,
    is_active    TINYINT(1)      NOT NULL DEFAULT 1,
    decimals     TINYINT         NOT NULL DEFAULT 0,
    updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO currencies (code, label, symbol, rate_to_base, is_base, decimals) VALUES
    ('XAF', 'Franc CFA BEAC', 'FCFA',  1.000000, 1, 0),
    ('XOF', 'Franc CFA BCEAO','CFA',   1.000000, 0, 0),
    ('EUR', 'Euro',           '€',   655.957000, 0, 2),
    ('USD', 'Dollar US',      '$',   600.000000, 0, 2),
    ('CDF', 'Franc Congolais','FC',    0.220000, 0, 0)
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- OAuth2 clients (API publique)
CREATE TABLE IF NOT EXISTS oauth_clients (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id       VARCHAR(80)     NOT NULL,
    client_secret_hash VARCHAR(255) NOT NULL,
    name            VARCHAR(120)    NOT NULL,
    description     VARCHAR(255)    NULL,
    scopes          VARCHAR(500)    NOT NULL DEFAULT 'read',
    rate_limit_per_min INT UNSIGNED NOT NULL DEFAULT 60,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    partner_id      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at    DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_oauth_client_id (client_id),
    INDEX idx_oauth_active (is_active),
    INDEX idx_oauth_partner (partner_id)
    -- FK vers sales_partners(id) volontairement omise (résout l'ordre de migrations)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth_access_tokens (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    BIGINT UNSIGNED NOT NULL,
    token_hash   VARCHAR(64)     NOT NULL,
    scopes       VARCHAR(500)    NOT NULL,
    issued_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME        NOT NULL,
    revoked      TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_token_hash (token_hash),
    INDEX idx_oauth_token_client (client_id, expires_at),
    CONSTRAINT fk_oat_client FOREIGN KEY (client_id) REFERENCES oauth_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_request_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    BIGINT UNSIGNED NULL,
    method       VARCHAR(10)     NOT NULL,
    path         VARCHAR(255)    NOT NULL,
    status_code  INT             NULL,
    duration_ms  INT             NULL,
    ip_address   VARCHAR(45)     NULL,
    request_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_api_client_time (client_id, request_at),
    INDEX idx_api_path (path, request_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('api.tokens.manage', 'api', 'manage', 'Gérer clients API', 400),
    ('currencies.manage', 'currencies', 'manage', 'Gérer devises', 401)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'admin' AND p.slug IN ('api.tokens.manage','currencies.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('currency.base_code',     'finance', 'string',  'XAF', 'Devise de base', 'Code ISO 4217 de la devise principale.', 410, 0),
('currency.display_alt',   'finance', 'boolean', '0',   'Afficher devise alternative', 'Affiche le prix dans une devise secondaire à côté.', 411, 0),
('api.token_ttl_hours',    'admin',   'integer', '24',  'Durée jeton API (h)', '', 412, 0),
('api.rate_limit_per_min', 'admin',   'integer', '60',  'Limite de requêtes / min', 'Par défaut, surchargeable par client.', 413, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
