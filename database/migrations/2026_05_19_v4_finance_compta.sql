-- =============================================================
-- V4.C — TVA + Comptabilité SYSCOHADA + P&L analytique par voyage
-- =============================================================

-- tax_rates existe déjà avec rate_percent, is_active. On ajoute juste tax_type pour V4.
ALTER TABLE tax_rates
    ADD COLUMN IF NOT EXISTS tax_type ENUM('vat','sales_tax','fee','levy') NOT NULL DEFAULT 'vat' AFTER rate_percent;

INSERT INTO tax_rates (code, label, rate_percent, is_default, is_active, tax_type) VALUES
  ('TVA_CG_18',     'TVA Congo 18%',         18.000, 1, 1, 'vat'),
  ('TVA_CG_5',      'TVA Congo réduite 5%',   5.000, 0, 1, 'vat'),
  ('TVA_CG_0',      'TVA Congo 0% (export)',  0.000, 0, 1, 'vat'),
  ('EXEMPT',        'Exonéré',                0.000, 0, 1, 'vat'),
  ('STAMP_DUTY',    'Droit de timbre',        2.000, 0, 1, 'levy')
ON DUPLICATE KEY UPDATE code = code;

-- Plan comptable SYSCOHADA simplifié (chart_of_accounts existe avec account_type)
ALTER TABLE chart_of_accounts
    ADD COLUMN IF NOT EXISTS parent_code VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS is_analytical TINYINT(1) NOT NULL DEFAULT 0;

INSERT INTO chart_of_accounts (code, label, account_type, is_analytical) VALUES
  -- Classe 4 - Tiers
  ('411', 'Clients', 'asset', 0),
  ('411100', 'Clients - ventes guichet', 'asset', 0),
  ('411200', 'Clients - ventes en ligne', 'asset', 0),
  ('411300', 'Clients - corporate', 'asset', 0),
  ('4456', 'TVA collectée', 'liability', 0),
  ('44561', 'TVA collectée 18%', 'liability', 0),
  ('445660', 'TVA déductible', 'asset', 0),
  ('467', 'Compte transitoire', 'liability', 0),
  -- Classe 5 - Trésorerie
  ('531', 'Caisse', 'asset', 0),
  ('531100', 'Caisse espèces', 'asset', 0),
  ('531200', 'Caisse Mobile Money', 'asset', 0),
  ('512', 'Banques', 'asset', 0),
  ('512100', 'Banque BCH', 'asset', 0),
  -- Classe 6 - Charges
  ('601', 'Achats de matières', 'expense', 1),
  ('606100', 'Carburant', 'expense', 1),
  ('606200', 'Entretien véhicules', 'expense', 1),
  ('614', 'Charges location', 'expense', 0),
  ('618', 'Divers', 'expense', 0),
  ('641', 'Salaires', 'expense', 1),
  ('647', 'Charges sociales', 'expense', 1),
  ('681', 'Dotations amortissements', 'expense', 1),
  -- Classe 7 - Produits
  ('701', 'Ventes de produits', 'revenue', 1),
  ('706100', 'Recettes voyageurs', 'revenue', 1),
  ('706200', 'Recettes bagages', 'revenue', 1),
  ('706300', 'Recettes colis (cargo)', 'revenue', 1),
  ('706400', 'Recettes affrètement', 'revenue', 1),
  ('758',    'Produits divers', 'revenue', 0)
ON DUPLICATE KEY UPDATE code = code;

-- Factures (header)
CREATE TABLE IF NOT EXISTS invoices (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number  VARCHAR(30) UNIQUE NOT NULL,
    type            ENUM('sale','refund','corporate','partner_commission','credit_note','proforma') NOT NULL DEFAULT 'sale',
    customer_id     BIGINT UNSIGNED NULL,
    corporate_id    BIGINT UNSIGNED NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    issued_at       DATETIME NOT NULL,
    due_at          DATE NULL,
    currency        CHAR(3) NOT NULL DEFAULT 'XAF',
    total_ht        INT NOT NULL,
    total_tax       INT NOT NULL,
    total_ttc       INT NOT NULL,
    paid_amount     INT NOT NULL DEFAULT 0,
    status          ENUM('draft','issued','paid','partial','overdue','void','cancelled') NOT NULL DEFAULT 'draft',
    paid_at         TIMESTAMP NULL,
    pdf_path        VARCHAR(255) NULL,
    notes           TEXT NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inv_status (status, due_at),
    INDEX idx_inv_pnr (pnr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_lines (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id      BIGINT UNSIGNED NOT NULL,
    line_type       ENUM('ticket','baggage','parcel','fee','discount','tax','other') NOT NULL,
    description     VARCHAR(180) NOT NULL,
    quantity        INT NOT NULL DEFAULT 1,
    unit_price_ht   INT NOT NULL DEFAULT 0,
    amount_ht       INT NOT NULL,
    tax_rate_id     BIGINT UNSIGNED NULL,
    tax_pct         DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_amount      INT NOT NULL DEFAULT 0,
    amount_ttc      INT NOT NULL,
    sequence        TINYINT NOT NULL DEFAULT 1,
    INDEX idx_il_invoice (invoice_id),
    CONSTRAINT fk_il_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_il_tax     FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Écritures comptables (journal)
CREATE TABLE IF NOT EXISTS accounting_entries (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal         ENUM('sales','purchases','bank','cash','salary','misc','opening','closing') NOT NULL,
    entry_date      DATE NOT NULL,
    label           VARCHAR(255) NOT NULL,
    reference       VARCHAR(60) NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    invoice_id      BIGINT UNSIGNED NULL,
    trip_id         BIGINT UNSIGNED NULL,
    line_id         BIGINT UNSIGNED NULL COMMENT 'analytical: bus_line.id',
    agency_id       BIGINT UNSIGNED NULL,
    posted_at       TIMESTAMP NULL,
    posted_by       BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ae_period (entry_date, journal),
    INDEX idx_ae_trip   (trip_id),
    INDEX idx_ae_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accounting_lines (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id        BIGINT UNSIGNED NOT NULL,
    account_code    VARCHAR(20) NOT NULL,
    label           VARCHAR(180) NULL,
    debit           INT NOT NULL DEFAULT 0,
    credit          INT NOT NULL DEFAULT 0,
    cost_center     VARCHAR(20) NULL COMMENT 'analytique: ligne, agence, voyage',
    sequence        TINYINT NOT NULL DEFAULT 1,
    INDEX idx_al_entry (entry_id),
    INDEX idx_al_account (account_code, entry_id),
    CONSTRAINT fk_al_entry FOREIGN KEY (entry_id) REFERENCES accounting_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- P&L par voyage (snapshot)
CREATE TABLE IF NOT EXISTS trip_pnl (
    trip_id             BIGINT UNSIGNED PRIMARY KEY,
    revenue_pax         INT NOT NULL DEFAULT 0,
    revenue_baggage     INT NOT NULL DEFAULT 0,
    revenue_parcel      INT NOT NULL DEFAULT 0,
    revenue_other       INT NOT NULL DEFAULT 0,
    revenue_total       INT NOT NULL DEFAULT 0,
    cost_fuel           INT NOT NULL DEFAULT 0,
    cost_toll           INT NOT NULL DEFAULT 0,
    cost_driver_bonus   INT NOT NULL DEFAULT 0,
    cost_maintenance    INT NOT NULL DEFAULT 0,
    cost_indirect_alloc INT NOT NULL DEFAULT 0,
    cost_total          INT NOT NULL DEFAULT 0,
    margin              INT NOT NULL DEFAULT 0,
    margin_pct          DECIMAL(6,2) NOT NULL DEFAULT 0,
    pax_count           INT NOT NULL DEFAULT 0,
    cost_per_pax        INT NOT NULL DEFAULT 0,
    revenue_per_pax     INT NOT NULL DEFAULT 0,
    computed_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tpnl_margin (margin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('finance.invoices.view',    'finance', 'inv_view',    'Voir factures', 600),
  ('finance.invoices.create',  'finance', 'inv_create',  'Créer factures', 601),
  ('finance.invoices.cancel',  'finance', 'inv_cancel',  'Annuler factures', 602),
  ('finance.tax.declare',      'finance', 'tax_declare', 'Générer déclaration TVA', 603),
  ('finance.accounting.view',  'finance', 'acc_view',    'Voir écritures comptables', 610),
  ('finance.accounting.post',  'finance', 'acc_post',    'Valider/poster écritures', 611),
  ('finance.accounting.export','finance', 'acc_export',  'Exporter Sage/SYSCOHADA', 612),
  ('finance.pnl.view',         'finance', 'pnl_view',    'Voir P&L par voyage/ligne', 620),
  ('finance.pnl.recompute',    'finance', 'pnl_recomp',  'Recalculer P&L', 621),
  ('finance.coa.manage',       'finance', 'coa_manage',  'Gérer plan comptable', 630)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('finance.tax_rate_default', 'finance','string','TVA_CG_18','Taux TVA défaut','Code tax_rates utilisé pour ventes', 700, 0),
  ('finance.invoice_prefix',   'finance','string','FAC',       'Préfixe numéro facture','', 701, 0),
  ('finance.fiscal_year_start','finance','string','01-01',     'Début exercice (MM-DD)','', 702, 0),
  ('finance.currency_default', 'finance','string','XAF',       'Devise par défaut','XAF/EUR/USD', 703, 0),
  ('finance.pnl.indirect_pct', 'finance','integer','15',       'Coûts indirects alloués (%)','% de la recette voyage', 704, 0),
  ('finance.auto_post',        'finance','boolean','0',        'Auto-validation écritures','Sinon brouillon', 705, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
