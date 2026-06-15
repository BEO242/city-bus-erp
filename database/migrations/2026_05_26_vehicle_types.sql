-- ═══════════════════════════════════════════════════════════════════
-- Migration: Bus → Véhicule — types de véhicules dynamiques
-- Date: 2026-05-26
-- ═══════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS vehicle_types (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(30)     NOT NULL UNIQUE,
  label       VARCHAR(80)     NOT NULL,
  description VARCHAR(255)    NULL,
  icon        VARCHAR(30)     NULL DEFAULT 'truck',
  is_active   TINYINT(1)      NOT NULL DEFAULT 1,
  sort_order  SMALLINT        NOT NULL DEFAULT 0,
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed : anciens body_types + types supplémentaires
INSERT IGNORE INTO vehicle_types (code, label, description, icon, sort_order) VALUES
  ('autocar',      'Autocar (grand tourisme)', 'Bus longue distance grand confort',       'bus',   1),
  ('minibus',      'Minibus',                  'Véhicule de transport 15-25 places',      'bus',   2),
  ('midibus',      'Midibus',                  'Véhicule intermédiaire 25-35 places',     'bus',   3),
  ('double_etage', 'Double étage',             'Bus à impériale',                         'bus',   4),
  ('urbain',       'Bus urbain',               'Transport urbain standard',               'bus',   5),
  ('utilitaire',   'Utilitaire',               'Véhicule utilitaire (fret / logistique)', 'truck', 6),
  ('pickup',       'Pick-up',                  'Véhicule tout terrain / livraison',       'truck', 7),
  ('fourgon',      'Fourgon',                  'Véhicule de livraison fermé',             'truck', 8);

-- Ajouter vehicle_type_id à la table buses
ALTER TABLE buses ADD COLUMN IF NOT EXISTS vehicle_type_id BIGINT UNSIGNED NULL AFTER body_type;
ALTER TABLE buses ADD CONSTRAINT fk_buses_vehicle_type
  FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types(id) ON DELETE SET NULL;

-- Migrer les body_type existants vers vehicle_type_id
UPDATE buses b
  JOIN vehicle_types vt ON vt.code = b.body_type
  SET b.vehicle_type_id = vt.id
  WHERE b.vehicle_type_id IS NULL AND b.body_type IS NOT NULL AND b.body_type != '';
