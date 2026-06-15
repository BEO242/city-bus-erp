-- Comptabilité analytique SYSCOHADA (GAP-23)
-- Génère des écritures comptables exportables (Sage, Excel, …)

CREATE TABLE IF NOT EXISTS accounting_entries (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_date      DATE            NOT NULL,
    journal_code    VARCHAR(10)     NOT NULL DEFAULT 'VTE' COMMENT 'VTE, ACH, CSE, BNQ, OD',
    account_code    VARCHAR(20)     NOT NULL COMMENT 'Code SYSCOHADA (411, 706, 521, …)',
    account_label   VARCHAR(120)    NULL,
    label           VARCHAR(255)    NOT NULL COMMENT 'Libellé écriture',
    debit_fcfa      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    credit_fcfa     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reference       VARCHAR(60)     NULL COMMENT 'Numéro pièce (facture, ticket, …)',
    source_table    VARCHAR(50)     NULL,
    source_id       BIGINT UNSIGNED NULL,
    agency_id       BIGINT UNSIGNED NULL,
    third_party     VARCHAR(120)    NULL COMMENT 'Tiers (client, fournisseur)',
    is_locked       TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 si exporté définitivement',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_acc_date (entry_date),
    INDEX idx_acc_account (account_code, entry_date),
    INDEX idx_acc_journal (journal_code, entry_date),
    INDEX idx_acc_source (source_table, source_id),
    INDEX idx_acc_agency (agency_id, entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plan comptable simplifié (référentiel)
CREATE TABLE IF NOT EXISTS chart_of_accounts (
    code         VARCHAR(20)     NOT NULL,
    label        VARCHAR(120)    NOT NULL,
    account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
    is_active    TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO chart_of_accounts (code, label, account_type) VALUES
    ('411000', 'Clients',                                      'asset'),
    ('445660', 'TVA collectée',                                'liability'),
    ('521000', 'Banque',                                       'asset'),
    ('531000', 'Caisse',                                       'asset'),
    ('601000', 'Achats de marchandises',                       'expense'),
    ('605000', 'Achats carburants et lubrifiants',             'expense'),
    ('614000', 'Charges locatives',                            'expense'),
    ('615000', 'Entretien et réparations',                     'expense'),
    ('616000', 'Primes d''assurance',                          'expense'),
    ('641000', 'Salaires bruts',                               'expense'),
    ('645000', 'Charges sociales',                             'expense'),
    ('706000', 'Prestations de services - billetterie',        'revenue'),
    ('706100', 'Prestations de services - bagages',            'revenue'),
    ('706200', 'Prestations de services - cargo',              'revenue'),
    ('706900', 'Annulations et remboursements',                'revenue'),
    ('421000', 'Personnel - rémunérations dues',               'liability')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- Settings comptabilité
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('accounting.enabled',          'finance', 'boolean', '1',      'Comptabilité activée', 'Active la génération automatique d''écritures comptables.', 220, 0),
('accounting.account_clients',  'finance', 'string',  '411000', 'Compte clients',           'Compte SYSCOHADA pour les clients.', 221, 0),
('accounting.account_cash',     'finance', 'string',  '531000', 'Compte caisse',            '', 222, 0),
('accounting.account_bank',     'finance', 'string',  '521000', 'Compte banque',            '', 223, 0),
('accounting.account_vat',      'finance', 'string',  '445660', 'Compte TVA collectée',     '', 224, 0),
('accounting.account_tickets',  'finance', 'string',  '706000', 'Compte ventes billets',    '', 225, 0),
('accounting.account_baggage',  'finance', 'string',  '706100', 'Compte ventes bagages',    '', 226, 0),
('accounting.account_cargo',    'finance', 'string',  '706200', 'Compte ventes cargo',      '', 227, 0),
('accounting.account_fuel',     'finance', 'string',  '605000', 'Compte achat carburant',   '', 228, 0),
('accounting.account_salary',   'finance', 'string',  '641000', 'Compte salaires',          '', 229, 0),
('accounting.account_personnel','finance', 'string',  '421000', 'Compte rém. dues',         '', 230, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('finance.accounting.view', 'finance', 'view', 'Voir le journal comptable', 240),
    ('finance.accounting.export', 'finance', 'export', 'Exporter le journal', 241),
    ('finance.accounting.manage', 'finance', 'manage', 'Gérer les écritures', 242)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'finance.accounting.%'
ON DUPLICATE KEY UPDATE role_id = role_id;
