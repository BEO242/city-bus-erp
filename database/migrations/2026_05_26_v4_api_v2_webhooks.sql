-- =============================================================
-- V4.L — API v2 (idempotency, webhooks, GDS sync)
-- =============================================================

ALTER TABLE oauth_clients
    ADD COLUMN IF NOT EXISTS scopes JSON NULL,
    ADD COLUMN IF NOT EXISTS rate_limit_per_min INT NOT NULL DEFAULT 60,
    ADD COLUMN IF NOT EXISTS webhook_url VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS webhook_secret VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS webhook_events JSON NULL;

CREATE TABLE IF NOT EXISTS api_idempotency_keys (
    `key`           VARCHAR(80) PRIMARY KEY,
    client_id       BIGINT UNSIGNED NULL,
    request_hash    VARCHAR(64),
    response        LONGTEXT,
    response_status SMALLINT,
    expires_at      TIMESTAMP NOT NULL,
    INDEX idx_iok_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhooks_outgoing (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id       BIGINT UNSIGNED NULL,
    event_type      VARCHAR(60) NOT NULL,
    url             VARCHAR(255) NOT NULL,
    payload         LONGTEXT NOT NULL,
    status          ENUM('pending','sent','retrying','failed') NOT NULL DEFAULT 'pending',
    attempts        TINYINT NOT NULL DEFAULT 0,
    next_attempt_at TIMESTAMP NULL,
    response_status SMALLINT NULL,
    response_body   TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at    TIMESTAMP NULL,
    INDEX idx_wo_status (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS channel_inventory_sync (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel         VARCHAR(40) NOT NULL COMMENT 'distribusion, busbud, omio',
    trip_id         BIGINT UNSIGNED NOT NULL,
    sync_status     ENUM('synced','pending','error') NOT NULL DEFAULT 'pending',
    last_synced_at  TIMESTAMP NULL,
    external_ref    VARCHAR(120) NULL,
    last_error      TEXT NULL,
    UNIQUE KEY uk_cis (channel, trip_id),
    INDEX idx_cis_status (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sso_providers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(30) UNIQUE,
    label           VARCHAR(80),
    type            ENUM('oidc','saml','ldap'),
    config          JSON NOT NULL,
    active          TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('api.v2.access',         'api', 'v2_access',   'Accéder API v2', 980),
  ('api.clients.manage',    'api', 'clients',     'Gérer clients OAuth2', 981),
  ('api.webhooks.view',     'api', 'wh_view',     'Voir webhooks sortants', 982),
  ('gds.channels.manage',   'gds', 'manage',      'Gérer canaux GDS', 990),
  ('sso.providers.manage',  'sso', 'manage',      'Gérer providers SSO', 991)
ON DUPLICATE KEY UPDATE slug = slug;
