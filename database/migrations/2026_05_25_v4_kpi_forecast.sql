-- =============================================================
-- V4.J — KPI snapshots + forecast avancé + promo codes
-- =============================================================

CREATE TABLE IF NOT EXISTS kpi_snapshots (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_date   DATE NOT NULL,
    scope_type      ENUM('global','line','agency','bus') NOT NULL,
    scope_id        BIGINT UNSIGNED NULL,
    load_factor_pct DECIMAL(5,2) NULL,
    otp_pct         DECIMAL(5,2) NULL COMMENT 'on-time perf, ±15min',
    cancellation_rate DECIMAL(5,2) NULL,
    no_show_rate    DECIMAL(5,2) NULL,
    avg_yield_per_seat INT NULL,
    revenue_total   BIGINT NOT NULL DEFAULT 0,
    cost_total      BIGINT NOT NULL DEFAULT 0,
    margin_pct      DECIMAL(5,2) NULL,
    nps_score       DECIMAL(4,1) NULL,
    rask            DECIMAL(10,2) NULL COMMENT 'Revenue per Available Seat Km',
    cask            DECIMAL(10,2) NULL COMMENT 'Cost per Available Seat Km',
    computed_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_kpi (snapshot_date, scope_type, scope_id),
    INDEX idx_kpi_date_scope (snapshot_date, scope_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promo_codes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(30) UNIQUE NOT NULL,
    description     VARCHAR(180),
    type            ENUM('promo_code','student','senior','employee','press','first_time','referral') NOT NULL DEFAULT 'promo_code',
    discount_type   ENUM('percent','fixed') NOT NULL,
    discount_value  INT NOT NULL,
    valid_from      DATE NULL,
    valid_until     DATE NULL,
    max_uses        INT NULL,
    used_count      INT NOT NULL DEFAULT 0,
    max_per_customer INT NULL DEFAULT 1,
    line_id         BIGINT UNSIGNED NULL,
    booking_class   CHAR(1) NULL,
    min_amount_fcfa INT NOT NULL DEFAULT 0,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promo_redemptions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promo_id        INT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    discount_applied INT NOT NULL,
    redeemed_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pr_promo (promo_id),
    INDEX idx_pr_customer (customer_id),
    CONSTRAINT fk_pr_promo FOREIGN KEY (promo_id) REFERENCES promo_codes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('kpi.view',             'analytics', 'kpi_view',     'Voir KPIs', 950),
  ('kpi.recompute',        'analytics', 'kpi_recompute','Recalculer KPIs', 951),
  ('promo.codes.view',     'promo',     'codes_view',   'Voir promos', 960),
  ('promo.codes.manage',   'promo',     'codes_manage', 'Gérer promos', 961),
  ('forecast.view',        'analytics', 'fc_view',      'Voir forecast', 970),
  ('forecast.compute',     'analytics', 'fc_compute',   'Calculer forecast', 971)
ON DUPLICATE KEY UPDATE slug = slug;
