-- =============================================================
-- V4.H — Corporate B2B contracts + commissions partenaires
-- =============================================================

CREATE TABLE IF NOT EXISTS corporate_contracts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    corporate_id    BIGINT UNSIGNED NOT NULL,
    contract_number VARCHAR(40) UNIQUE,
    valid_from      DATE NOT NULL,
    valid_until     DATE NULL,
    discount_pct    DECIMAL(5,2) NOT NULL DEFAULT 0,
    free_seats_per_year INT NOT NULL DEFAULT 0,
    quota_seats_per_month INT NULL,
    auto_renew      TINYINT(1) NOT NULL DEFAULT 0,
    pdf_path        VARCHAR(255) NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cc_corp (corporate_id, valid_until),
    CONSTRAINT fk_cc_corp FOREIGN KEY (corporate_id) REFERENCES corporate_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE corporate_accounts
    ADD COLUMN IF NOT EXISTS account_manager_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS billing_email VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS tax_id VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS preferred_payment_method VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS sla_terms TEXT NULL;

CREATE TABLE IF NOT EXISTS partner_commissions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id      BIGINT UNSIGNED NOT NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    ticket_id       BIGINT UNSIGNED NULL,
    sale_amount     INT NOT NULL,
    commission_amount INT NOT NULL,
    period_month    CHAR(7) NOT NULL,
    status          ENUM('pending','accrued','invoiced','paid','reversed') NOT NULL DEFAULT 'pending',
    payout_id       BIGINT UNSIGNED NULL,
    invoice_id      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pc_partner_period (partner_id, period_month),
    INDEX idx_pc_status (status),
    CONSTRAINT fk_pc_partner FOREIGN KEY (partner_id) REFERENCES sales_partners(id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_payout FOREIGN KEY (payout_id) REFERENCES partner_payouts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('corporate.contracts.view',   'corporate', 'contract_view',   'Voir contrats corporate', 360),
  ('corporate.contracts.manage', 'corporate', 'contract_manage', 'Gérer contrats corporate', 361),
  ('corporate.invoice.generate', 'corporate', 'invoice_gen',     'Générer factures corporate (mensuelle)', 362),
  ('partners.commissions.view',  'partners',  'comm_view',       'Voir commissions partenaires', 370),
  ('partners.commissions.payout','partners',  'comm_payout',     'Émettre paiements partenaires', 371)
ON DUPLICATE KEY UPDATE slug = slug;
