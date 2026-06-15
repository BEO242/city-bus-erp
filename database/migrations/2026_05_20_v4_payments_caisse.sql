-- =============================================================
-- V4.D — Paiements multi-providers + Caisse + rapprochement
-- =============================================================

CREATE TABLE IF NOT EXISTS payment_providers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(30) UNIQUE NOT NULL,
    label           VARCHAR(80) NOT NULL,
    type            ENUM('mobile_money','card','wallet','cash','voucher','bank_transfer') NOT NULL,
    api_endpoint    VARCHAR(255) NULL,
    api_key_encrypted TEXT NULL,
    api_secret_encrypted TEXT NULL,
    callback_url    VARCHAR(255) NULL,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    fee_pct         DECIMAL(5,3) NOT NULL DEFAULT 0,
    fee_fixed       INT NOT NULL DEFAULT 0,
    sandbox_mode    TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO payment_providers (code, label, type, active, fee_pct, fee_fixed, sandbox_mode) VALUES
  ('CASH',          'Espèces',          'cash',          1, 0,    0, 0),
  ('AIRTEL_MONEY',  'Airtel Money',     'mobile_money',  1, 1.5,  0, 1),
  ('MTN_MOMO',      'MTN Mobile Money', 'mobile_money',  1, 1.5,  0, 1),
  ('ORANGE_MONEY',  'Orange Money',     'mobile_money',  1, 1.5,  0, 1),
  ('CINETPAY',      'Cinetpay (multi)', 'card',          1, 2.5,  100, 1),
  ('PAWAPAY',       'Pawapay',          'mobile_money',  1, 1.8,  0, 1),
  ('VOUCHER',       'Bon d''achat',     'voucher',       1, 0,    0, 0),
  ('BANK_TRANSFER', 'Virement bancaire','bank_transfer', 1, 0,    500, 0)
ON DUPLICATE KEY UPDATE code = code;

-- Crée table payments centralisée (n'existait pas)
CREATE TABLE IF NOT EXISTS payments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id      BIGINT UNSIGNED NULL,
    ticket_id       BIGINT UNSIGNED NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    amount          INT NOT NULL,
    payment_method  VARCHAR(40) NOT NULL DEFAULT 'CASH',
    provider_id     INT UNSIGNED NULL,
    provider_transaction_id VARCHAR(120) NULL,
    provider_status ENUM('pending','authorized','confirmed','failed','refunded','expired') NULL,
    provider_fee    INT NOT NULL DEFAULT 0,
    provider_callback_received_at TIMESTAMP NULL,
    reconciled_at   TIMESTAMP NULL,
    reconciliation_batch_id BIGINT UNSIGNED NULL,
    paid_at         TIMESTAMP NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pay_invoice (invoice_id),
    INDEX idx_pay_pnr (pnr_id),
    INDEX idx_pay_reconcile (provider_id, reconciled_at),
    INDEX idx_pay_provider_tx (provider_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reconciliation batches
CREATE TABLE IF NOT EXISTS reconciliation_batches (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT UNSIGNED NOT NULL,
    period_start    DATE,
    period_end      DATE,
    statement_file  VARCHAR(255) NULL,
    expected_total  BIGINT NOT NULL DEFAULT 0,
    matched_total   BIGINT NOT NULL DEFAULT 0,
    unmatched_total BIGINT NOT NULL DEFAULT 0,
    matched_count   INT NOT NULL DEFAULT 0,
    unmatched_count INT NOT NULL DEFAULT 0,
    status          ENUM('pending','partial','complete','disputed') NOT NULL DEFAULT 'pending',
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at    TIMESTAMP NULL,
    INDEX idx_recon_provider (provider_id, period_end DESC),
    CONSTRAINT fk_recon_provider FOREIGN KEY (provider_id) REFERENCES payment_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reconciliation_unmatched (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id        BIGINT UNSIGNED NOT NULL,
    external_tx_id  VARCHAR(120),
    external_amount INT,
    external_date   DATETIME,
    raw_data        JSON,
    INDEX idx_recunm_batch (batch_id),
    CONSTRAINT fk_recunm_batch FOREIGN KEY (batch_id) REFERENCES reconciliation_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Caisses (sessions)
CREATE TABLE IF NOT EXISTS cash_drawers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cashier_id      BIGINT UNSIGNED NOT NULL,
    agency_id       BIGINT UNSIGNED NULL,
    drawer_code     VARCHAR(30) NULL COMMENT 'matricule physique',
    opened_at       TIMESTAMP NOT NULL,
    closed_at       TIMESTAMP NULL,
    opening_balance INT NOT NULL DEFAULT 0,
    declared_cash_close INT NULL,
    expected_cash_close INT NULL,
    variance        INT NULL,
    notes           TEXT NULL,
    INDEX idx_cd_cashier_open (cashier_id, opened_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_drawer_movements (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    drawer_id       BIGINT UNSIGNED NOT NULL,
    movement_type   ENUM('sale','refund','withdraw','deposit','correction','transfer') NOT NULL,
    payment_method  VARCHAR(30) NOT NULL,
    amount          INT NOT NULL,
    reference       VARCHAR(60) NULL,
    notes           VARCHAR(180) NULL,
    occurred_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cdm_drawer (drawer_id, occurred_at DESC),
    CONSTRAINT fk_cdm_drawer FOREIGN KEY (drawer_id) REFERENCES cash_drawers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('payments.providers.view',    'payments', 'prov_view',    'Voir providers de paiement', 660),
  ('payments.providers.manage',  'payments', 'prov_manage',  'Configurer providers', 661),
  ('payments.reconcile.view',    'payments', 'recon_view',   'Voir rapprochement', 662),
  ('payments.reconcile.manage',  'payments', 'recon_manage', 'Faire rapprochement', 663),
  ('caisse.drawers.open',        'caisse',   'open',         'Ouvrir caisse', 670),
  ('caisse.drawers.close',       'caisse',   'close',        'Fermer caisse', 671),
  ('caisse.drawers.view',        'caisse',   'view',         'Voir caisses + variance', 672)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('payments.default_provider',    'payments','string','CASH',  'Provider par défaut', '', 800, 0),
  ('payments.momo_callback_url',   'payments','string','/api/v1/payments/momo/callback', 'Callback URL MoMo', '', 801, 0),
  ('payments.timeout_seconds',     'payments','integer','120',  'Timeout transaction (s)', '', 802, 0),
  ('payments.auto_reconcile',      'payments','boolean','0',    'Rapprochement auto par tx_id', '', 803, 0),
  ('caisse.variance_threshold_fcfa','caisse','integer','5000',  'Seuil alerte variance (FCFA)', '', 810, 0),
  ('caisse.require_supervisor_close','caisse','boolean','0',    'Superviseur requis pour clôture', '', 811, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
