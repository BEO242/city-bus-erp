-- GPS temps réel + tour de contrôle (GAP-27, GAP-28)

CREATE TABLE IF NOT EXISTS gps_positions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bus_id        BIGINT UNSIGNED NOT NULL,
    trip_id       BIGINT UNSIGNED NULL,
    lat           DECIMAL(10,7)   NOT NULL,
    lng           DECIMAL(10,7)   NOT NULL,
    speed_kmh     DECIMAL(6,2)    NULL,
    heading       DECIMAL(5,2)    NULL,
    accuracy_m    DECIMAL(8,2)    NULL,
    altitude_m    DECIMAL(8,2)    NULL,
    recorded_at   DATETIME        NOT NULL,
    received_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_gps_bus_time (bus_id, recorded_at),
    INDEX idx_gps_trip (trip_id, recorded_at),
    CONSTRAINT fk_gps_bus  FOREIGN KEY (bus_id)  REFERENCES buses(id) ON DELETE CASCADE,
    CONSTRAINT fk_gps_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dernière position connue (cache rapide)
CREATE TABLE IF NOT EXISTS bus_last_position (
    bus_id        BIGINT UNSIGNED NOT NULL,
    lat           DECIMAL(10,7)   NOT NULL,
    lng           DECIMAL(10,7)   NOT NULL,
    speed_kmh     DECIMAL(6,2)    NULL,
    heading       DECIMAL(5,2)    NULL,
    trip_id       BIGINT UNSIGNED NULL,
    recorded_at   DATETIME        NOT NULL,
    PRIMARY KEY (bus_id),
    CONSTRAINT fk_lp_bus FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Géofences (zones autorisées par ligne)
CREATE TABLE IF NOT EXISTS geofences (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    line_id      BIGINT UNSIGNED NULL,
    name         VARCHAR(120)    NOT NULL,
    type         ENUM('corridor','restricted','speed_zone') NOT NULL DEFAULT 'corridor',
    polygon_geojson JSON         NOT NULL,
    speed_limit_kmh INT           NULL,
    is_active    TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    INDEX idx_gf_line (line_id, is_active),
    CONSTRAINT fk_gf_line FOREIGN KEY (line_id) REFERENCES bus_lines(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alertes GPS
CREATE TABLE IF NOT EXISTS gps_alerts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bus_id       BIGINT UNSIGNED NOT NULL,
    trip_id      BIGINT UNSIGNED NULL,
    alert_type   ENUM('speeding','geofence_exit','signal_lost','idle','stop_unauthorized') NOT NULL,
    severity     ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
    description  VARCHAR(255)    NULL,
    lat          DECIMAL(10,7)   NULL,
    lng          DECIMAL(10,7)   NULL,
    occurred_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acknowledged_by BIGINT UNSIGNED NULL,
    acknowledged_at DATETIME    NULL,
    PRIMARY KEY (id),
    INDEX idx_alert_bus (bus_id, occurred_at),
    INDEX idx_alert_unack (acknowledged_at, occurred_at),
    CONSTRAINT fk_alert_bus FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens d'authentification appareil GPS
CREATE TABLE IF NOT EXISTS gps_device_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bus_id      BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64)     NOT NULL,
    device_id   VARCHAR(80)     NULL,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    last_used_at DATETIME       NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_gps_token (token_hash),
    INDEX idx_gps_token_bus (bus_id),
    CONSTRAINT fk_gpst_bus FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('gps.live.view', 'gps', 'view', 'Voir GPS temps réel', 360),
    ('gps.alerts.acknowledge', 'gps', 'acknowledge', 'Acquitter alertes GPS', 361),
    ('ops.control_tower.view', 'ops', 'view', 'Tour de contrôle ops', 362)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN ('gps.live.view','gps.alerts.acknowledge','ops.control_tower.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('gps.max_position_age_min',    'voyage', 'integer', '15', 'Âge max position GPS (min)', 'Au-delà : alerte signal perdu.', 370, 0),
('gps.geofence_tolerance_km',   'voyage', 'integer', '5',  'Tolérance géofence (km)',     'Distance max hors corridor avant alerte.', 371, 0),
('gps.speed_limit_default_kmh', 'voyage', 'integer', '90', 'Vitesse max par défaut (km/h)','', 372, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
