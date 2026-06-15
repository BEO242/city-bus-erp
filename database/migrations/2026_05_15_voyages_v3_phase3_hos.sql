-- =============================================================
-- VOYAGES V3 — PHASE 3 : Hours of Service Compliance
-- =============================================================
-- Inspiré règles UE/UEMOA: max 9h conduite/jour (10h x 2/sem),
-- 56h/semaine, 90h/2 semaines, 11h repos quotidien (réduit 9h x 3/sem),
-- 45h repos hebdo (réduit 24h compensé)

CREATE TABLE IF NOT EXISTS driver_duty_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    driver_id       BIGINT UNSIGNED NOT NULL,
    trip_id         BIGINT UNSIGNED NULL,
    duty_type       ENUM('drive','rest','break','other_work','available') NOT NULL,
    started_at      DATETIME NOT NULL,
    ended_at        DATETIME NULL,
    duration_min    INT UNSIGNED NULL COMMENT 'computed on close',
    location        VARCHAR(120) NULL,
    notes           TEXT NULL,
    source          ENUM('manual','auto_trip','tachograph','mobile') NOT NULL DEFAULT 'manual',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ddl_driver_date (driver_id, started_at),
    INDEX idx_ddl_trip (trip_id),
    INDEX idx_ddl_open (driver_id, ended_at),
    CONSTRAINT fk_ddl_driver FOREIGN KEY (driver_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_ddl_trip   FOREIGN KEY (trip_id)   REFERENCES trips(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hos_violations (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    driver_id       BIGINT UNSIGNED NOT NULL,
    trip_id         BIGINT UNSIGNED NULL,
    rule_code       VARCHAR(40) NOT NULL COMMENT 'DAILY_DRIVE_MAX, WEEKLY_DRIVE_MAX, REST_DAILY_MIN, etc',
    severity        ENUM('warning','minor','major','critical') NOT NULL DEFAULT 'minor',
    detected_at     DATETIME NOT NULL,
    period_start    DATETIME NULL,
    period_end      DATETIME NULL,
    actual_value    DECIMAL(10,2) NULL,
    limit_value     DECIMAL(10,2) NULL,
    description     TEXT NULL,
    acknowledged    TINYINT(1) NOT NULL DEFAULT 0,
    acknowledged_by BIGINT UNSIGNED NULL,
    acknowledged_at TIMESTAMP NULL,
    resolution_notes TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hosv_driver (driver_id, detected_at),
    INDEX idx_hosv_severity (severity, acknowledged),
    CONSTRAINT fk_hosv_driver FOREIGN KEY (driver_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_hosv_trip   FOREIGN KEY (trip_id)   REFERENCES trips(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions
INSERT IGNORE INTO permissions (slug, module, action, label, sort_order) VALUES
  ('hos.view',         'hos', 'view',     'Voir tableau HOS chauffeurs', 250),
  ('hos.log',          'hos', 'log',      'Saisir entrées de service', 251),
  ('hos.manage',       'hos', 'manage',   'Gérer les logs HOS (édition, suppression)', 252),
  ('hos.violations',   'hos', 'violations','Voir et acquitter les violations', 253);

-- Settings (limites configurables)
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('hos.daily_drive_max_min',     'hos','integer','540', 'Conduite max / jour (min)',           '540 = 9h',                          300, 0),
  ('hos.daily_drive_extended_min','hos','integer','600', 'Conduite étendue / jour (min)',       '10h max 2x/sem',                    301, 0),
  ('hos.weekly_drive_max_min',    'hos','integer','3360','Conduite max / semaine (min)',        '56h',                                302, 0),
  ('hos.biweekly_drive_max_min',  'hos','integer','5400','Conduite max / 2 sem (min)',          '90h',                                303, 0),
  ('hos.daily_rest_min',          'hos','integer','660', 'Repos quotidien min (min)',           '11h',                                304, 0),
  ('hos.daily_rest_reduced_min',  'hos','integer','540', 'Repos quotidien réduit (min)',        '9h max 3x/sem',                     305, 0),
  ('hos.weekly_rest_min',         'hos','integer','2700','Repos hebdo min (min)',               '45h',                                306, 0),
  ('hos.continuous_drive_max_min','hos','integer','270', 'Conduite continue max (min)',         '4h30 avant pause',                  307, 0),
  ('hos.break_min',               'hos','integer','45',  'Pause obligatoire (min)',             'Après conduite continue',           308, 0),
  ('hos.fatigue_warning_pct',     'hos','integer','80',  'Seuil alerte fatigue (%)',            '',                                  309, 0),
  ('hos.auto_check_enabled',      'hos','boolean','1',   'Vérification HOS à l''assignation',   '',                                  310, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
