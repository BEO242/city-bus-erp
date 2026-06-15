-- =============================================================
-- V4.I — GPS realtime + geofencing + alertes + rotation flotte
-- =============================================================

-- Étend gps_positions (table existante)
ALTER TABLE gps_positions
    ADD COLUMN IF NOT EXISTS battery_pct TINYINT NULL,
    ADD COLUMN IF NOT EXISTS engine_on TINYINT(1) NULL,
    ADD COLUMN IF NOT EXISTS odometer_km INT NULL;

CREATE TABLE IF NOT EXISTS geofences (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    type            ENUM('depot','stop','restricted','corridor') NOT NULL,
    polygon_geojson JSON NOT NULL,
    line_id         BIGINT UNSIGNED NULL,
    alert_on_enter  TINYINT(1) NOT NULL DEFAULT 0,
    alert_on_exit   TINYINT(1) NOT NULL DEFAULT 0,
    color_hex       VARCHAR(7) DEFAULT '#3b82f6',
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gf_line (line_id),
    INDEX idx_gf_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- gps_alerts existe déjà avec ENUM différent — on étend les colonnes manquantes
ALTER TABLE gps_alerts
    ADD COLUMN IF NOT EXISTS severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    ADD COLUMN IF NOT EXISTS detail TEXT NULL,
    ADD COLUMN IF NOT EXISTS speed_kmh INT NULL,
    ADD COLUMN IF NOT EXISTS location_lat DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS location_lng DECIMAL(10,7) NULL,
    MODIFY COLUMN alert_type VARCHAR(40) NOT NULL;

CREATE TABLE IF NOT EXISTS fleet_rotation_plans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_date       DATE NOT NULL,
    bus_id          BIGINT UNSIGNED NOT NULL,
    sequence        TINYINT NOT NULL,
    trip_id         BIGINT UNSIGNED NOT NULL,
    positioning_required TINYINT(1) NOT NULL DEFAULT 0,
    positioning_distance_km INT NULL,
    UNIQUE KEY uk_frp (plan_date, bus_id, sequence),
    INDEX idx_frp_date (plan_date),
    CONSTRAINT fk_frp_bus FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    CONSTRAINT fk_frp_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('gps.realtime.view',     'gps', 'realtime',  'Voir carte temps réel', 850),
  ('gps.alerts.view',       'gps', 'alerts',    'Voir alertes GPS', 851),
  ('gps.alerts.ack',        'gps', 'ack',       'Acquitter alertes', 852),
  ('gps.geofences.manage',  'gps', 'gf_manage', 'Gérer geofences', 853),
  ('fleet.rotation.view',   'fleet','rot_view', 'Voir rotation flotte', 860),
  ('fleet.rotation.manage', 'fleet','rot_manage','Planifier rotation', 861)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('gps.overspeed_kmh',         'gps','integer','90',  'Seuil vitesse (km/h)', '', 1100, 0),
  ('gps.idle_threshold_min',    'gps','integer','20',  'Idle long (min)', '', 1101, 0),
  ('gps.offline_threshold_min', 'gps','integer','10',  'Offline si pas de ping (min)', '', 1102, 0),
  ('gps.refresh_interval_sec',  'gps','integer','15',  'Refresh carte (s)', '', 1103, 0),
  ('gps.eta_recompute_enabled', 'gps','boolean','1',   'Recalc ETA à chaque ping', '', 1104, 0),
  ('fleet.rotation.algo',       'fleet','string','greedy','Algo rotation (greedy/manual)', '', 1110, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
