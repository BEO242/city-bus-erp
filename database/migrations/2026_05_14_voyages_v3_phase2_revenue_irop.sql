-- =============================================================
-- VOYAGES V3 — PHASE 2 : Revenue Management + IROP
-- =============================================================

-- ─── Règles de pricing dynamique ─────────────────────────────
CREATE TABLE IF NOT EXISTS voyage_pricing_rules (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    description     TEXT NULL,
    rule_type       ENUM('load_factor','days_to_departure','time_of_day','day_of_week','class','line') NOT NULL,
    scope_line_id   BIGINT UNSIGNED NULL,
    scope_class     CHAR(1) NULL,
    -- Conditions (JSON-like via colonnes typées)
    threshold_min   DECIMAL(10,2) NULL COMMENT 'ex: load>=70%, dtd<=3j',
    threshold_max   DECIMAL(10,2) NULL,
    -- Action
    multiplier      DECIMAL(5,3) NOT NULL DEFAULT 1.000 COMMENT 'ex: 1.20 = +20%',
    delta_fcfa      INT NOT NULL DEFAULT 0 COMMENT 'addition en FCFA',
    -- Activation
    active          TINYINT(1) NOT NULL DEFAULT 1,
    priority        INT NOT NULL DEFAULT 100 COMMENT 'plus bas = appliqué en premier',
    valid_from      DATE NULL,
    valid_until     DATE NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pricing_active (active, priority),
    INDEX idx_pricing_line (scope_line_id),
    CONSTRAINT fk_pricing_line FOREIGN KEY (scope_line_id) REFERENCES bus_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Historique des prix appliqués par classe ───────────────
CREATE TABLE IF NOT EXISTS trip_price_history (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    trip_id         BIGINT UNSIGNED NOT NULL,
    class_code      CHAR(1) NOT NULL,
    old_price       INT NOT NULL,
    new_price       INT NOT NULL,
    change_reason   VARCHAR(255) NULL,
    rule_id         INT UNSIGNED NULL,
    actor_id        BIGINT UNSIGNED NULL,
    changed_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tph_trip (trip_id, class_code),
    CONSTRAINT fk_tph_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tph_rule FOREIGN KEY (rule_id) REFERENCES voyage_pricing_rules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Demand forecast (simple, par ligne × jour) ─────────────
CREATE TABLE IF NOT EXISTS demand_forecast (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    line_id         BIGINT UNSIGNED NOT NULL,
    forecast_date   DATE NOT NULL,
    expected_pax    INT UNSIGNED NOT NULL DEFAULT 0,
    confidence_pct  TINYINT UNSIGNED NOT NULL DEFAULT 50,
    method          ENUM('historical','manual','ml') NOT NULL DEFAULT 'historical',
    notes           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_forecast_line_date (line_id, forecast_date),
    CONSTRAINT fk_df_line FOREIGN KEY (line_id) REFERENCES bus_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── IROP events (Irregular Operations) ─────────────────────
CREATE TABLE IF NOT EXISTS irop_events (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    trip_id         BIGINT UNSIGNED NOT NULL,
    irop_type       ENUM('cancellation','major_delay','equipment_swap','route_diversion','incident','strike','weather') NOT NULL,
    severity        ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    reason          TEXT NOT NULL,
    impact_pax      INT NOT NULL DEFAULT 0 COMMENT 'nb passagers impactés',
    delay_minutes   INT NOT NULL DEFAULT 0,
    replacement_trip_id BIGINT UNSIGNED NULL,
    status          ENUM('open','rebooking','resolved','closed') NOT NULL DEFAULT 'open',
    opened_by       INT UNSIGNED NULL,
    closed_by       INT UNSIGNED NULL,
    opened_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at     TIMESTAMP NULL,
    closed_at       TIMESTAMP NULL,
    notes           TEXT NULL,
    INDEX idx_irop_trip (trip_id),
    INDEX idx_irop_status (status),
    INDEX idx_irop_type_date (irop_type, opened_at),
    CONSTRAINT fk_irop_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_irop_repl FOREIGN KEY (replacement_trip_id) REFERENCES trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Rebooking requests (rerouter passagers IROP) ───────────
CREATE TABLE IF NOT EXISTS rebooking_requests (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    irop_id         INT UNSIGNED NOT NULL,
    original_ticket_id BIGINT UNSIGNED NULL,
    original_pnr    VARCHAR(20) NULL,
    new_trip_id     BIGINT UNSIGNED NULL,
    new_ticket_id   BIGINT UNSIGNED NULL,
    refund_amount   INT NOT NULL DEFAULT 0,
    compensation    INT NOT NULL DEFAULT 0,
    status          ENUM('pending','offered','accepted','refused','refunded','rebooked') NOT NULL DEFAULT 'pending',
    customer_phone  VARCHAR(30) NULL,
    customer_email  VARCHAR(120) NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at     TIMESTAMP NULL,
    INDEX idx_rebook_irop (irop_id),
    INDEX idx_rebook_status (status),
    CONSTRAINT fk_rebook_irop FOREIGN KEY (irop_id) REFERENCES irop_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_rebook_newtrip FOREIGN KEY (new_trip_id) REFERENCES trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Permissions ────────────────────────────────────────────
INSERT IGNORE INTO permissions (slug, module, action, label, sort_order) VALUES
  ('voyages.pricing.view',    'voyages', 'pricing.view',   'Voir les règles de pricing', 220),
  ('voyages.pricing.manage',  'voyages', 'pricing.manage', 'Gérer les règles de pricing', 221),
  ('voyages.pricing.apply',   'voyages', 'pricing.apply',  'Appliquer pricing dynamique sur un voyage', 222),
  ('voyages.irop.view',       'voyages', 'irop.view',      'Voir les événements IROP', 230),
  ('voyages.irop.manage',     'voyages', 'irop.manage',    'Gérer (ouvrir/fermer) les IROP', 231),
  ('voyages.irop.rebook',     'voyages', 'irop.rebook',    'Effectuer un rebooking IROP', 232),
  ('voyages.forecast.view',   'voyages', 'forecast.view',  'Voir les prévisions de demande', 240);

-- ─── Settings ───────────────────────────────────────────────
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('pricing.dynamic.enabled',     'voyage','boolean','1',  'Pricing dynamique activé',           'Active le yield management dynamique par voyage.', 250, 0),
  ('pricing.dynamic.recalc_minutes','voyage','integer','15','Recalc toutes les N minutes',       'Fréquence du recalcul automatique.',               251, 0),
  ('pricing.max_increase_pct',    'voyage','integer','50', 'Hausse max vs base (%)',             '',                                                  252, 0),
  ('pricing.max_decrease_pct',    'voyage','integer','40', 'Baisse max vs base (%)',             '',                                                  253, 0),
  ('irop.auto_open_on_cancel',    'voyage','boolean','1',  'Auto-IROP à l''annulation',          'Ouvre automatiquement un IROP quand un voyage est annulé.', 260, 0),
  ('irop.compensation_delay_min', 'voyage','integer','60', 'Délai compensation (min)',           'À partir de combien de minutes de retard.',         261, 0),
  ('irop.compensation_pct',       'voyage','integer','20', 'Compensation (% prix billet)',       '',                                                  262, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
