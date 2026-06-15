-- P&L par voyage / ligne (GAP-22)
-- Snapshot immuable du compte de résultat de chaque voyage clôturé.

CREATE TABLE IF NOT EXISTS trip_pnl (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id             BIGINT UNSIGNED NOT NULL,
    line_id             BIGINT UNSIGNED NULL,
    bus_id              BIGINT UNSIGNED NULL,

    -- Recettes
    revenue_tickets     INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_baggage     INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_cargo       INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_total       INT UNSIGNED    NOT NULL DEFAULT 0,
    tax_total           INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_ht          INT UNSIGNED    NOT NULL DEFAULT 0,

    -- Coûts directs
    cost_fuel           INT UNSIGNED    NOT NULL DEFAULT 0,
    cost_crew_bonus     INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Primes équipage du voyage',
    cost_tolls          INT UNSIGNED    NOT NULL DEFAULT 0,
    cost_misc           INT UNSIGNED    NOT NULL DEFAULT 0,
    cost_direct_total   INT UNSIGNED    NOT NULL DEFAULT 0,

    -- Coûts indirects alloués
    cost_depreciation   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Amortissement bus alloué au km',
    cost_insurance      INT UNSIGNED    NOT NULL DEFAULT 0,
    cost_maintenance    INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Maintenance moyenne au km',
    cost_overhead       INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Frais structure (% recettes)',
    cost_indirect_total INT UNSIGNED    NOT NULL DEFAULT 0,

    -- Résultats
    margin_contribution INT             NOT NULL DEFAULT 0 COMMENT 'Recettes - coûts directs (peut être négatif)',
    margin_net          INT             NOT NULL DEFAULT 0 COMMENT 'Recettes - tous coûts',

    -- Volumétrie
    distance_km         DECIMAL(8,2)    NULL,
    passengers_count    INT UNSIGNED    NOT NULL DEFAULT 0,
    parcels_count       INT UNSIGNED    NOT NULL DEFAULT 0,
    fuel_liters         DECIMAL(8,2)    NULL,
    load_factor_pct     DECIMAL(5,2)    NULL,

    -- Métadonnées
    computed_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    computed_by         BIGINT UNSIGNED NULL,
    computation_notes   TEXT            NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_trip_pnl_trip (trip_id),
    INDEX idx_trip_pnl_line (line_id),
    INDEX idx_trip_pnl_bus  (bus_id),
    INDEX idx_trip_pnl_margin (margin_net),
    CONSTRAINT fk_trip_pnl_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_trip_pnl_line FOREIGN KEY (line_id) REFERENCES bus_lines(id) ON DELETE SET NULL,
    CONSTRAINT fk_trip_pnl_bus  FOREIGN KEY (bus_id)  REFERENCES buses(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Règles d'allocation (paramétrables)
CREATE TABLE IF NOT EXISTS cost_allocation_rules (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key        VARCHAR(50)     NOT NULL,
    label           VARCHAR(120)    NOT NULL,
    method          ENUM('per_km','per_trip','percent_revenue','fixed') NOT NULL DEFAULT 'per_km',
    value_numeric   DECIMAL(12,4)   NOT NULL DEFAULT 0,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    notes           VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cost_rule (rule_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cost_allocation_rules (rule_key, label, method, value_numeric, notes) VALUES
    ('depreciation_per_km', 'Amortissement bus / km',  'per_km', 50,    'FCFA/km en moyenne'),
    ('insurance_per_km',    'Assurance / km',          'per_km', 15,    'FCFA/km'),
    ('maintenance_per_km',  'Maintenance / km',        'per_km', 80,    'FCFA/km'),
    ('overhead_pct',        'Frais structure (%)',     'percent_revenue', 8.0, '% des recettes'),
    ('crew_bonus_per_trip', 'Prime équipage / voyage', 'per_trip', 15000, 'FCFA forfaitaire')
ON DUPLICATE KEY UPDATE rule_key = rule_key;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('finance.pnl.view', 'finance', 'view', 'Voir P&L voyages', 210),
    ('finance.pnl.export', 'finance', 'export', 'Exporter P&L', 211)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'finance.pnl.%'
ON DUPLICATE KEY UPDATE role_id = role_id;
