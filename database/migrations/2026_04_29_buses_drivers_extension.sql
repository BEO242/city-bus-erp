-- =====================================================================
-- Extension Bus + Module Chauffeur (29 avril 2026)
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Extension table buses
-- ---------------------------------------------------------------------
ALTER TABLE buses
  ADD COLUMN IF NOT EXISTS body_type VARCHAR(30) NULL AFTER model,
  ADD COLUMN IF NOT EXISTS length_m DECIMAL(5,2) NULL,
  ADD COLUMN IF NOT EXISTS width_m DECIMAL(5,2) NULL,
  ADD COLUMN IF NOT EXISTS height_m DECIMAL(5,2) NULL,
  ADD COLUMN IF NOT EXISTS weight_empty_kg INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS weight_max_kg INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS cargo_capacity_kg INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS fuel_tank_l SMALLINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS consumption_avg_l DECIMAL(5,2) NULL,
  ADD COLUMN IF NOT EXISTS axles_count TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS abs_brakes TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS esp_system TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS retarder TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS seatbelts_all TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS tachograph TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS airbags_count TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS gps_provider VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS gps_device_id VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS gps_sim_number VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS purchase_price_fcfa BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS supplier VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS financing_type ENUM('cash','leasing','credit','don') NULL,
  ADD COLUMN IF NOT EXISTS registration_card_number VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS registration_card_date DATE NULL,
  ADD COLUMN IF NOT EXISTS primary_driver_id BIGINT UNSIGNED NULL,
  ADD INDEX IF NOT EXISTS idx_buses_primary_driver (primary_driver_id);

-- ---------------------------------------------------------------------
-- 2. Table drivers (module chauffeur dédié)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS drivers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Identité
  matricule VARCHAR(20) NOT NULL UNIQUE,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  birth_date DATE NULL,
  birth_place VARCHAR(80) NULL,
  gender ENUM('M','F') NULL,
  marital_status ENUM('celibataire','marie','divorce','veuf') NULL,
  children_count TINYINT UNSIGNED NULL DEFAULT 0,
  nationality VARCHAR(50) DEFAULT 'Congolaise',
  blood_type VARCHAR(5) NULL,
  national_id VARCHAR(40) NULL,
  national_id_expiry DATE NULL,

  -- Contact
  phone VARCHAR(20) NOT NULL,
  phone_alt VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  address TEXT NULL,
  city VARCHAR(60) NULL,
  emergency_name VARCHAR(100) NULL,
  emergency_phone VARCHAR(20) NULL,
  emergency_relation VARCHAR(50) NULL,

  -- Permis & qualifications
  license_number VARCHAR(50) NOT NULL,
  license_categories VARCHAR(40) NULL,
  license_issue_date DATE NULL,
  license_expiry DATE NOT NULL,
  license_authority VARCHAR(100) NULL,
  medical_cert_expiry DATE NULL,
  psycho_test_expiry DATE NULL,
  drug_test_last DATE NULL,

  -- Carrière
  hire_date DATE NOT NULL,
  experience_years TINYINT UNSIGNED DEFAULT 0,
  previous_employer VARCHAR(120) NULL,

  -- Affectation
  agency_id BIGINT UNSIGNED NULL,
  primary_bus_id BIGINT UNSIGNED NULL,
  status ENUM('actif','conge','suspendu','en_formation','quitte','accident') NOT NULL DEFAULT 'actif',

  -- Rémunération
  salary_base INT UNSIGNED DEFAULT 0,
  daily_bonus INT UNSIGNED DEFAULT 0,
  km_bonus_rate DECIMAL(8,2) DEFAULT 0,
  bank_name VARCHAR(80) NULL,
  bank_account VARCHAR(60) NULL,
  mobile_money_number VARCHAR(20) NULL,

  -- Performance (mise à jour via triggers / batch)
  rating_score DECIMAL(3,1) NOT NULL DEFAULT 5.0,
  total_trips INT UNSIGNED NOT NULL DEFAULT 0,
  total_km INT UNSIGNED NOT NULL DEFAULT 0,
  accidents_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  warnings_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  -- Divers
  notes TEXT NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,

  INDEX idx_drivers_status (status),
  INDEX idx_drivers_agency (agency_id),
  INDEX idx_drivers_primary_bus (primary_bus_id),
  INDEX idx_drivers_license_expiry (license_expiry)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. Incidents (bus & chauffeurs — table mutualisée polymorphe)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_type ENUM('bus','driver') NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  bus_id BIGINT UNSIGNED NULL,
  driver_id BIGINT UNSIGNED NULL,
  type ENUM('accident','panne','retard','infraction','altercation','vol','autre') NOT NULL,
  severity ENUM('mineur','modere','grave','critique') NOT NULL DEFAULT 'mineur',
  occurred_at DATETIME NOT NULL,
  location VARCHAR(150) NULL,
  description TEXT NOT NULL,
  cost_fcfa BIGINT UNSIGNED NULL DEFAULT 0,
  resolved TINYINT(1) NOT NULL DEFAULT 0,
  resolved_at DATETIME NULL,
  resolution_notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_inc_subject (subject_type, subject_id),
  INDEX idx_inc_bus (bus_id),
  INDEX idx_inc_driver (driver_id),
  INDEX idx_inc_date (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
