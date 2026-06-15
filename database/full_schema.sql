-- ============================================================
-- Migration: 2026_04_28_create_tariff_lookups.sql
-- ============================================================

-- =====================================================================
-- Création des tables de référentiel tarifaire (P0)
-- Tables utilisées par le modèle Tariff::ticketTypesFull/passengerCategoriesFull/travelClassesFull
-- mais jamais déclarées dans schema.sql.
-- (tariff_baggage_natures et tariff_services sont créées par 2026_04_30_baggage_tariffs.sql)
-- =====================================================================

CREATE TABLE IF NOT EXISTS tariff_ticket_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    icon VARCHAR(60) DEFAULT NULL,
    color_class VARCHAR(60) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tariff_passenger_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    icon VARCHAR(60) DEFAULT NULL,
    color_class VARCHAR(60) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tariff_travel_classes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    icon VARCHAR(60) DEFAULT NULL,
    color_class VARCHAR(60) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données initiales : 4 valeurs alignées sur l'ENUM tickets.ticket_type
INSERT IGNORE INTO tariff_ticket_types (slug, label, icon, color_class, description, sort_order) VALUES
    ('passager',         'Passage final',      'user',          'bg-blue-100 text-blue-800',      'Billet passager classique', 10),
    ('arret_route',      'Arrêt en route',     'map-pin',       'bg-amber-100 text-amber-800',    'Embarquement en cours de route', 20),
    ('bagage_franchise', 'Bagage en franchise','briefcase',     'bg-emerald-100 text-emerald-800','Bagage inclus', 30),
    ('bagage_excedent',  'Bagage excédentaire','luggage',       'bg-rose-100 text-rose-800',      'Excédent payant', 40);

INSERT IGNORE INTO tariff_passenger_categories (slug, label, icon, color_class, sort_order) VALUES
    ('adulte',     'Adulte',         'user',          'bg-slate-100 text-slate-800',   10),
    ('enfant',     'Enfant',         'baby',          'bg-pink-100 text-pink-800',     20),
    ('etudiant',   'Étudiant',       'graduation-cap','bg-indigo-100 text-indigo-800', 30),
    ('senior',     'Senior',         'user',          'bg-yellow-100 text-yellow-800', 40);

INSERT IGNORE INTO tariff_travel_classes (slug, label, icon, color_class, sort_order) VALUES
    ('standard',   'Standard',       'star',          'bg-slate-100 text-slate-800',   10),
    ('vip',        'VIP',            'crown',         'bg-purple-100 text-purple-800', 20);


-- ============================================================
-- Migration: 2026_04_29_buses_drivers_extension.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_04_30_baggage_tariffs.sql
-- ============================================================

-- =====================================================================
-- Séparation Tarifs Passagers / Tarifs Bagages (30 avril 2026)
-- =====================================================================
-- Ce fichier EST idempotent : utilise CREATE TABLE IF NOT EXISTS,
-- ALTER TABLE ... ADD COLUMN IF NOT EXISTS, et INSERT IGNORE.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Désactiver les slugs bagage dans tariff_ticket_types
--    (ils ne s'appliquent plus aux tarifs passagers)
-- ---------------------------------------------------------------------
UPDATE tariff_ticket_types
   SET is_active = 0
 WHERE slug IN ('bagage_franchise', 'bagage_excedent');

-- Retirer les tarifs orphelins de type bagage de la table tariffs
DELETE FROM tariffs
 WHERE ticket_type IN ('bagage_franchise', 'bagage_excedent');

-- ---------------------------------------------------------------------
-- 2. Enrichir la table tariffs (franchise bagage incluse dans le billet)
-- ---------------------------------------------------------------------
ALTER TABLE tariffs
  ADD COLUMN IF NOT EXISTS baggage_included_qty TINYINT UNSIGNED NOT NULL DEFAULT 1
      COMMENT 'Nombre de bagages inclus dans le prix du billet',
  ADD COLUMN IF NOT EXISTS baggage_included_kg  DECIMAL(5,1) NOT NULL DEFAULT 15.0
      COMMENT 'Poids total en kg inclus dans le prix du billet';

-- ---------------------------------------------------------------------
-- 3. Table de configuration : natures de bagages
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tariff_baggage_natures (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(50)  NOT NULL UNIQUE COMMENT 'Identifiant machine (ex: standard, fragile)',
  label       VARCHAR(100) NOT NULL COMMENT 'Libellé affiché',
  icon        VARCHAR(50)  NOT NULL DEFAULT 'package',
  color_class VARCHAR(60)  NOT NULL DEFAULT 'bg-slate-100 text-slate-700',
  description TEXT         NULL,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_baggage_natures_active (is_active, sort_order)
) ENGINE=InnoDB CHARSET=utf8mb4;

INSERT IGNORE INTO tariff_baggage_natures (slug, label, icon, color_class, description, sort_order) VALUES
  ('standard',            'Bagage standard',          'luggage',          'bg-slate-100 text-slate-700',    'Valise ou sac de voyage ordinaire',                    1),
  ('fragile',             'Bagage fragile',            'package-open',     'bg-amber-100 text-amber-700',    'Objet nécessitant une manipulation soigneuse',          2),
  ('volumineux',          'Bagage volumineux',         'box',              'bg-orange-100 text-orange-700',  'Colis hors gabarit standard (dimensions importantes)',  3),
  ('animal',              'Animal vivant',             'paw-print',        'bg-green-100 text-green-700',    'Transport d\'animaux domestiques',                     4),
  ('denrees_perissables', 'Denrées périssables',       'thermometer',      'bg-blue-100 text-blue-700',      'Aliments ou produits nécessitant le froid',             5),
  ('medical',             'Matériel médical',          'stethoscope',      'bg-violet-100 text-violet-700',  'Équipements médicaux ou produits pharmaceutiques',     6);

-- ---------------------------------------------------------------------
-- 4. Table de configuration : services inclus dans un tarif passager
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tariff_services (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(50)  NOT NULL UNIQUE,
  label       VARCHAR(100) NOT NULL,
  icon        VARCHAR(50)  NOT NULL DEFAULT 'check',
  color_class VARCHAR(60)  NOT NULL DEFAULT 'bg-slate-100 text-slate-700',
  description TEXT         NULL,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tariff_services_active (is_active, sort_order)
) ENGINE=InnoDB CHARSET=utf8mb4;

INSERT IGNORE INTO tariff_services (slug, label, icon, color_class, description, sort_order) VALUES
  ('repas_chaud',    'Repas chaud',        'utensils',    'bg-amber-100 text-amber-700',  'Repas servi à bord',                    1),
  ('eau_minerale',   'Eau minérale',       'droplets',    'bg-sky-100 text-sky-700',      'Bouteille d\'eau offerte',              2),
  ('wifi',           'Wi-Fi à bord',       'wifi',        'bg-indigo-100 text-indigo-700','Connexion internet pendant le trajet',  3),
  ('climatisation',  'Climatisation',      'wind',        'bg-cyan-100 text-cyan-700',    'Véhicule climatisé',                   4),
  ('prise_courant',  'Prise de courant',   'plug',        'bg-slate-100 text-slate-700',  'Prise 220V ou USB disponible',         5),
  ('assurance',      'Assurance voyage',   'shield-check','bg-emerald-100 text-emerald-700','Couverture assurance incluse',       6),
  ('bagages_extra',  'Bagages supplémentaires', 'luggage','bg-violet-100 text-violet-700','Quota bagage supplémentaire inclus',   7);

-- ---------------------------------------------------------------------
-- 5. Table de jonction : services inclus par tarif passager
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tariff_service_map (
  tariff_id  BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (tariff_id, service_id),
  CONSTRAINT fk_tsm_tariff  FOREIGN KEY (tariff_id)  REFERENCES tariffs(id)          ON DELETE CASCADE,
  CONSTRAINT fk_tsm_service FOREIGN KEY (service_id) REFERENCES tariff_services(id)  ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. Table principale : tarifs excédent bagage
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS baggage_tariffs (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  line_id             BIGINT UNSIGNED NOT NULL,
  baggage_nature_id   BIGINT UNSIGNED NOT NULL,
  label               VARCHAR(150)    NOT NULL COMMENT 'Libellé descriptif (ex: Excédent standard – ligne BZV-PNR)',
  -- Modèle tarifaire combinable
  base_fee_fcfa       INT UNSIGNED    NOT NULL DEFAULT 0   COMMENT 'Frais fixes par colis (peut être 0)',
  per_kg_fcfa         INT UNSIGNED    NULL                 COMMENT 'Prix fixe par kg – NULL si mode tranches',
  bracket_mode        TINYINT(1)      NOT NULL DEFAULT 0   COMMENT '1 = utiliser les tranches de baggage_tariff_brackets',
  volume_surcharge_fcfa INT UNSIGNED  NULL                 COMMENT 'Surcharge si hors dimensions autorisées',
  -- Contraintes physiques (toutes optionnelles)
  max_weight_kg       DECIMAL(5,1)    NULL COMMENT 'Poids max accepté (kg)',
  max_length_cm       SMALLINT UNSIGNED NULL,
  max_width_cm        SMALLINT UNSIGNED NULL,
  max_height_cm       SMALLINT UNSIGNED NULL,
  max_girth_cm        SMALLINT UNSIGNED NULL COMMENT 'Périmètre (2×(L+l)) max en cm',
  -- Validité
  valid_from          DATE            NULL,
  valid_until         DATE            NULL,
  notes               TEXT            NULL,
  is_active           TINYINT(1)      NOT NULL DEFAULT 1,
  created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bt_line    FOREIGN KEY (line_id)           REFERENCES bus_lines(id)              ON DELETE CASCADE,
  CONSTRAINT fk_bt_nature  FOREIGN KEY (baggage_nature_id) REFERENCES tariff_baggage_natures(id),
  INDEX idx_bt_line_active  (line_id, is_active),
  INDEX idx_bt_nature       (baggage_nature_id),
  -- Un seul tarif actif par combinaison ligne + nature pour éviter les doublons
  UNIQUE KEY uniq_bt_active (line_id, baggage_nature_id, is_active)
) ENGINE=InnoDB CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. Table des tranches de poids (pour bracket_mode = 1)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS baggage_tariff_brackets (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  baggage_tariff_id  BIGINT UNSIGNED NOT NULL,
  weight_from_kg     DECIMAL(5,1)    NOT NULL COMMENT 'Poids minimum de la tranche (inclus)',
  weight_to_kg       DECIMAL(5,1)    NULL     COMMENT 'Poids maximum (inclus) – NULL = sans limite',
  price_fcfa         INT UNSIGNED    NOT NULL COMMENT 'Prix pour cette tranche entière',
  sort_order         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_btb_tariff FOREIGN KEY (baggage_tariff_id) REFERENCES baggage_tariffs(id) ON DELETE CASCADE,
  INDEX idx_btb_tariff_sort (baggage_tariff_id, sort_order)
) ENGINE=InnoDB CHARSET=utf8mb4;


-- ============================================================
-- Migration: 2026_04_30_baggage_tickets.sql
-- ============================================================

-- ============================================================
-- Migration : Module billets bagages séparé
-- Date      : 2026_04_30
-- ============================================================

-- Table principale des billets bagages
CREATE TABLE IF NOT EXISTS baggage_tickets (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identification
    ticket_number       VARCHAR(30) NOT NULL UNIQUE,           -- ex: BGG-2026-000001

    -- Voyage
    trip_id             BIGINT UNSIGNED NOT NULL,
    line_id             BIGINT UNSIGNED NOT NULL,

    -- Passager propriétaire
    passenger_ticket_id BIGINT UNSIGNED NULL,                  -- FK vers tickets.id (optionnel)
    passenger_name      VARCHAR(120) NOT NULL,
    passenger_phone     VARCHAR(20) NULL,

    -- Tarif bagage appliqué
    baggage_tariff_id   BIGINT UNSIGNED NOT NULL,
    baggage_nature_id   BIGINT UNSIGNED NOT NULL,

    -- Mesures physiques
    weight_kg           DECIMAL(6,2) NOT NULL,
    length_cm           SMALLINT UNSIGNED NULL,
    width_cm            SMALLINT UNSIGNED NULL,
    height_cm           SMALLINT UNSIGNED NULL,
    description         VARCHAR(255) NULL,                     -- ex : "Valise noire, poignée rouge"

    -- Prix calculé
    base_fee_fcfa       INT UNSIGNED NOT NULL DEFAULT 0,
    weight_fee_fcfa     INT UNSIGNED NOT NULL DEFAULT 0,
    volume_surcharge_fcfa INT UNSIGNED NOT NULL DEFAULT 0,
    total_price_fcfa    INT UNSIGNED NOT NULL,

    -- Caisse / vendeur
    agency_id           BIGINT UNSIGNED NOT NULL DEFAULT 1,
    sold_by             BIGINT UNSIGNED NOT NULL,
    cash_register_id    BIGINT UNSIGNED NULL,

    -- Statut
    status              ENUM('emis','annule') NOT NULL DEFAULT 'emis',
    cancelled_at        DATETIME NULL,
    cancelled_by        INT UNSIGNED NULL,
    cancel_reason       VARCHAR(255) NULL,

    -- PDF
    pdf_path            VARCHAR(255) NULL,
    qr_code_path        VARCHAR(255) NULL,

    -- Timestamps
    sold_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME NULL,

    -- FK
    CONSTRAINT fk_bgt_trip       FOREIGN KEY (trip_id)             REFERENCES trips(id),
    CONSTRAINT fk_bgt_line       FOREIGN KEY (line_id)             REFERENCES bus_lines(id),
    CONSTRAINT fk_bgt_ptkt       FOREIGN KEY (passenger_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_bgt_tariff     FOREIGN KEY (baggage_tariff_id)   REFERENCES baggage_tariffs(id),
    CONSTRAINT fk_bgt_nature     FOREIGN KEY (baggage_nature_id)   REFERENCES tariff_baggage_natures(id),
    CONSTRAINT fk_bgt_seller     FOREIGN KEY (sold_by)             REFERENCES users(id),

    INDEX idx_bgt_trip   (trip_id),
    INDEX idx_bgt_line   (line_id),
    INDEX idx_bgt_ptkt   (passenger_ticket_id),
    INDEX idx_bgt_sold   (sold_at),
    INDEX idx_bgt_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Numérotation auto des billets bagages
CREATE TABLE IF NOT EXISTS baggage_ticket_sequences (
    year        SMALLINT UNSIGNED NOT NULL,
    last_seq    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed séquence année courante
INSERT IGNORE INTO baggage_ticket_sequences (year, last_seq) VALUES (YEAR(CURDATE()), 0);


-- ============================================================
-- Migration: 2026_04_30_tariff_fusion.sql
-- ============================================================

-- =====================================================================
-- Fusion modules tarif passager + bagage
-- Chaque tarif bagage peut désormais être rattaché à son tarif passager.
-- La colonne tariff_id est nullable pour ne pas casser les anciens enregistrements.
-- =====================================================================

ALTER TABLE baggage_tariffs
  ADD COLUMN tariff_id BIGINT UNSIGNED NULL AFTER line_id,
  ADD INDEX idx_bgt_tariff_id (tariff_id),
  ADD CONSTRAINT fk_bgttar_tariff
      FOREIGN KEY (tariff_id) REFERENCES tariffs(id) ON DELETE SET NULL;


-- ============================================================
-- Migration: 2026_04_30_tariff_hardening.sql
-- ============================================================

-- =====================================================================
-- Phase 1 — Robustesse tarifaire (30 avril 2026)
-- Empêche tout chevauchement de périmètre entre 2 tarifs actifs
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. tariffs : nettoyer + UNIQUE strict + index temporels
-- ---------------------------------------------------------------------

-- 1a. S'assurer que les colonnes existent (idempotent)
ALTER TABLE tariffs
  ADD COLUMN IF NOT EXISTS passenger_category VARCHAR(50) NOT NULL DEFAULT 'adulte',
  ADD COLUMN IF NOT EXISTS travel_class       VARCHAR(50) NOT NULL DEFAULT 'standard',
  ADD COLUMN IF NOT EXISTS valid_from         DATE        NULL,
  ADD COLUMN IF NOT EXISTS valid_until        DATE        NULL,
  ADD COLUMN IF NOT EXISTS label              VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS notes              TEXT        NULL,
  ADD COLUMN IF NOT EXISTS passenger_categories LONGTEXT  NULL,
  ADD COLUMN IF NOT EXISTS service_ids         LONGTEXT   NULL;

-- 1a-bis. Garantir des valeurs non-nulles (évite NULL != NULL en UNIQUE)
UPDATE tariffs
   SET passenger_category = COALESCE(NULLIF(passenger_category,''), 'adulte'),
       travel_class       = COALESCE(NULLIF(travel_class,''), 'standard');

ALTER TABLE tariffs
  MODIFY COLUMN passenger_category VARCHAR(50) NOT NULL DEFAULT 'adulte',
  MODIFY COLUMN travel_class       VARCHAR(50) NOT NULL DEFAULT 'standard';

-- 1b. Détecter les doublons existants (info — n'échoue pas)
--     Ces lignes seront refusées par l'UNIQUE ci-dessous, donc on les
--     liste avant pour que l'admin puisse corriger.
SELECT line_id, ticket_type, passenger_category, travel_class, COUNT(*) AS n
  FROM tariffs
 WHERE is_active = 1
 GROUP BY line_id, ticket_type, passenger_category, travel_class
HAVING n > 1;

-- 1c. Supprimer l'ancienne UNIQUE (line_id, ticket_type) si elle existe
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tariffs'
     AND INDEX_NAME   = 'uk_line_type'
);
SET @sql := IF(@idx_exists > 0, 'ALTER TABLE tariffs DROP INDEX uk_line_type', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- même chose pour le nom auto-généré possible
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tariffs'
     AND INDEX_NAME   = 'line_id'
);
SET @sql := IF(@idx_exists > 0, 'ALTER TABLE tariffs DROP INDEX line_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1d. UNIQUE strict sur le périmètre complet pour les tarifs ACTIFS
--     (is_active inclus pour permettre l'historisation : un tarif désactivé
--      peut coexister avec son successeur)
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tariffs'
     AND INDEX_NAME   = 'uk_tariff_scope'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE tariffs ADD UNIQUE KEY uk_tariff_scope
     (line_id, ticket_type, passenger_category, travel_class, is_active)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1e. Index pour accélérer la résolution temporelle
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tariffs'
     AND INDEX_NAME   = 'idx_tariff_resolve'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE tariffs ADD INDEX idx_tariff_resolve
     (line_id, ticket_type, passenger_category, travel_class, is_active, valid_from, valid_until)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2. baggage_tariffs : index temporel
-- ---------------------------------------------------------------------
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'baggage_tariffs'
     AND INDEX_NAME   = 'idx_baggage_resolve'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE baggage_tariffs ADD INDEX idx_baggage_resolve
     (line_id, baggage_nature_id, is_active, valid_from, valid_until)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- Done.
-- =====================================================================


-- ============================================================
-- Migration: 2026_04_30_tickets_scope.sql
-- ============================================================

-- =====================================================================
-- Phase 1 — tickets : tracer le périmètre tarifaire (30 avril 2026)
-- =====================================================================
-- Ajoute les colonnes nécessaires pour audit/reporting/statistiques :
--   - passenger_category, travel_class : périmètre du tarif appliqué
--   - tariff_id : référence vers le tarif utilisé (audit)
-- =====================================================================

ALTER TABLE tickets
  ADD COLUMN IF NOT EXISTS passenger_category VARCHAR(50) NOT NULL DEFAULT 'adulte'
      AFTER ticket_type,
  ADD COLUMN IF NOT EXISTS travel_class       VARCHAR(50) NOT NULL DEFAULT 'standard'
      AFTER passenger_category,
  ADD COLUMN IF NOT EXISTS tariff_id          BIGINT UNSIGNED NULL
      AFTER travel_class;

-- Index utiles pour reporting
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tickets'
     AND INDEX_NAME   = 'idx_tickets_scope'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE tickets ADD INDEX idx_tickets_scope (passenger_category, travel_class)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tickets'
     AND INDEX_NAME   = 'idx_tickets_tariff'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE tickets ADD INDEX idx_tickets_tariff (tariff_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ============================================================
-- Migration: 2026_05_01_baggage_volume.sql
-- ============================================================

-- Migration: 2026_05_01 — Ajout max_volume_cm3 dans baggage_tariffs
ALTER TABLE baggage_tariffs
  ADD COLUMN max_volume_cm3 INT UNSIGNED NULL COMMENT 'Volume max du colis (cm³) au-delà duquel la surcharge volumineux s''applique' AFTER max_girth_cm;


-- ============================================================
-- Migration: 2026_05_01_json_categories_natures.sql
-- ============================================================

-- =====================================================================
-- Multi-catégories passager + multi-natures bagage (01 mai 2026)
-- Remplace les colonnes scalaires par des colonnes JSON
-- afin de stocker plusieurs valeurs dans un seul enregistrement.
-- =====================================================================

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────────────
-- 1. tariffs : passenger_category → passenger_categories (JSON)
-- ─────────────────────────────────────────────────────────────────────

-- 1a. Supprimer l'index UNIQUE sur le périmètre (inclut passenger_category)
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tariffs' AND INDEX_NAME = 'uk_tariff_scope'
);
SET @sql := IF(@idx > 0, 'ALTER TABLE tariffs DROP INDEX uk_tariff_scope', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1b. Supprimer l'index de résolution (inclut passenger_category)
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tariffs' AND INDEX_NAME = 'idx_tariff_resolve'
);
SET @sql := IF(@idx > 0, 'ALTER TABLE tariffs DROP INDEX idx_tariff_resolve', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1c. Ajouter la nouvelle colonne JSON
ALTER TABLE tariffs
  ADD COLUMN IF NOT EXISTS passenger_categories JSON NULL
    COMMENT 'Tableau JSON des catégories passager (ex: ["adulte","etudiant"])'
    AFTER passenger_category;

-- 1d. Migrer les données existantes
UPDATE tariffs
   SET passenger_categories = JSON_ARRAY(passenger_category)
 WHERE passenger_category IS NOT NULL
   AND passenger_category != ''
   AND passenger_categories IS NULL;

UPDATE tariffs
   SET passenger_categories = JSON_ARRAY('adulte')
 WHERE passenger_categories IS NULL;

-- 1e. Supprimer l'ancienne colonne scalaire
ALTER TABLE tariffs DROP COLUMN IF EXISTS passenger_category;

-- 1f. Recréer un index de résolution sans passenger_category (utiliser JSON_CONTAINS au runtime)
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tariffs' AND INDEX_NAME = 'idx_tariff_resolve'
);
SET @sql := IF(@idx = 0,
  'ALTER TABLE tariffs ADD INDEX idx_tariff_resolve (line_id, ticket_type, travel_class, is_active, valid_from, valid_until)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─────────────────────────────────────────────────────────────────────
-- 2. baggage_tariffs : baggage_nature_id → baggage_nature_ids (JSON)
-- ─────────────────────────────────────────────────────────────────────

-- 2a. Supprimer la FK sur baggage_nature_id
SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'baggage_tariffs' AND CONSTRAINT_NAME = 'fk_bt_nature'
);
SET @sql := IF(@fk > 0, 'ALTER TABLE baggage_tariffs DROP FOREIGN KEY fk_bt_nature', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2b. Supprimer l'index de résolution (inclut baggage_nature_id)
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'baggage_tariffs' AND INDEX_NAME = 'idx_baggage_resolve'
);
SET @sql := IF(@idx > 0, 'ALTER TABLE baggage_tariffs DROP INDEX idx_baggage_resolve', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2c. Supprimer l'index simple sur baggage_nature_id (s'il existe)
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'baggage_tariffs' AND INDEX_NAME = 'idx_bt_nature'
);
SET @sql := IF(@idx > 0, 'ALTER TABLE baggage_tariffs DROP INDEX idx_bt_nature', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2c-bis. Supprimer le UNIQUE composite contenant baggage_nature_id
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'baggage_tariffs' AND INDEX_NAME = 'uniq_bt_active'
);
SET @sql := IF(@idx > 0, 'ALTER TABLE baggage_tariffs DROP INDEX uniq_bt_active', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2d. Ajouter la nouvelle colonne JSON
ALTER TABLE baggage_tariffs
  ADD COLUMN IF NOT EXISTS baggage_nature_ids JSON NULL
    COMMENT 'Tableau JSON des IDs de natures de bagage (ex: [1,3])'
    AFTER baggage_nature_id;

-- 2e. Migrer les données existantes
UPDATE baggage_tariffs
   SET baggage_nature_ids = JSON_ARRAY(CAST(baggage_nature_id AS UNSIGNED))
 WHERE baggage_nature_id IS NOT NULL
   AND baggage_nature_ids IS NULL;

UPDATE baggage_tariffs
   SET baggage_nature_ids = JSON_ARRAY(1)
 WHERE baggage_nature_ids IS NULL;

-- 2f. Supprimer l'ancienne colonne scalaire
ALTER TABLE baggage_tariffs DROP COLUMN IF EXISTS baggage_nature_id;

-- 2g. Recréer un index de résolution sans baggage_nature_id
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'baggage_tariffs' AND INDEX_NAME = 'idx_baggage_resolve'
);
SET @sql := IF(@idx = 0,
  'ALTER TABLE baggage_tariffs ADD INDEX idx_baggage_resolve (line_id, is_active, valid_from, valid_until)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- Done.
-- =====================================================================


-- ============================================================
-- Migration: 2026_05_02_admin_security.sql
-- ============================================================

-- =====================================================================
-- 2026-05-02 — Module Administration & Durcissement Sécurité
-- =====================================================================
-- Roles + Permissions DB (RBAC granulaire)
-- App Settings (clé-valeur typée)
-- Sécurité : login_attempts, password_history, 2FA, user_sessions
-- Extension table users (lockout, 2FA, password_changed_at)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop FK users.role_id si existe (sera reposée plus bas)
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND CONSTRAINT_NAME='fk_users_role');
SET @sql := IF(@fk_exists > 0, 'ALTER TABLE users DROP FOREIGN KEY fk_users_role', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop tables Spatie/Laravel résiduelles si présentes (pour repartir propre)
DROP TABLE IF EXISTS role_has_permissions;
DROP TABLE IF EXISTS model_has_roles;
DROP TABLE IF EXISTS model_has_permissions;
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;

-- ─── ROLES ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(50)  NOT NULL UNIQUE,
  label        VARCHAR(120) NOT NULL,
  description  VARCHAR(255) NULL,
  is_system    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order   INT          NOT NULL DEFAULT 100,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── PERMISSIONS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS permissions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(80)  NOT NULL UNIQUE,
  module       VARCHAR(40)  NOT NULL,
  action       VARCHAR(40)  NOT NULL,
  label        VARCHAR(180) NOT NULL,
  description  VARCHAR(255) NULL,
  sort_order   INT          NOT NULL DEFAULT 100,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_permissions_module (module)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id        BIGINT UNSIGNED NOT NULL,
  permission_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;

-- Overrides per user (grant=1 ajoute, grant=0 retire)
CREATE TABLE IF NOT EXISTS user_permissions (
  user_id        BIGINT UNSIGNED NOT NULL,
  permission_id  BIGINT UNSIGNED NOT NULL,
  granted        TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, permission_id),
  CONSTRAINT fk_up_user FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
  CONSTRAINT fk_up_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;

-- ─── SETTINGS ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS app_settings (
  setting_key  VARCHAR(100) NOT NULL PRIMARY KEY,
  category     VARCHAR(40)  NOT NULL,
  setting_type ENUM('string','int','bool','json','secret','text') NOT NULL DEFAULT 'string',
  setting_value TEXT NULL,
  label        VARCHAR(180) NOT NULL,
  description  VARCHAR(255) NULL,
  is_secret    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order   INT          NOT NULL DEFAULT 100,
  updated_by   BIGINT UNSIGNED NULL,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_settings_cat (category, sort_order)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── SÉCURITÉ ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(120) NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  user_agent   VARCHAR(255) NULL,
  success      TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempts_email_time (email, attempted_at),
  INDEX idx_attempts_ip_time    (ip_address, attempted_at)
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_history (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pwh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pwh_user_time (user_id, created_at)
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS two_factor_secrets (
  user_id        BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  secret         VARCHAR(64)  NOT NULL,
  recovery_codes JSON NULL,
  enabled        TINYINT(1)   NOT NULL DEFAULT 0,
  confirmed_at   TIMESTAMP NULL,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_sessions (
  id            VARCHAR(128) NOT NULL PRIMARY KEY,
  user_id       BIGINT UNSIGNED NOT NULL,
  ip_address    VARCHAR(45) NULL,
  user_agent    VARCHAR(255) NULL,
  fingerprint   VARCHAR(64) NULL,
  last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_us_user (user_id, last_activity)
) ENGINE=InnoDB CHARSET=utf8mb4;

-- ─── EXTENSION users ─────────────────────────────────────────────────
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS role_id              BIGINT UNSIGNED NULL AFTER role,
  ADD COLUMN IF NOT EXISTS password_changed_at  TIMESTAMP NULL AFTER password_hash,
  ADD COLUMN IF NOT EXISTS password_expires_at  TIMESTAMP NULL AFTER password_changed_at,
  ADD COLUMN IF NOT EXISTS failed_login_count   INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS locked_until         TIMESTAMP NULL,
  ADD COLUMN IF NOT EXISTS two_factor_enabled   TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS email_verified_at    TIMESTAMP NULL;

-- Index idempotents
SET @i1 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='idx_users_role_id');
SET @sql := IF(@i1 = 0, 'ALTER TABLE users ADD INDEX idx_users_role_id (role_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @i2 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='idx_users_locked');
SET @sql := IF(@i2 = 0, 'ALTER TABLE users ADD INDEX idx_users_locked (locked_until)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK users.role_id → roles.id (idempotent)
SET @fk_now := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND CONSTRAINT_NAME='fk_users_role');
SET @sql := IF(@fk_now = 0, 'ALTER TABLE users ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── SEED ROLES ──────────────────────────────────────────────────────
INSERT INTO roles (slug, label, description, is_system, sort_order) VALUES
  ('admin',        'Administrateur-Gérant',          'Accès total système',                  1, 1),
  ('raf',          'Resp. Administratif et Financier','Pilotage finance, paie, audit',         0, 10),
  ('exploitation', 'Resp. Exploitation',             'Voyages, lignes, flotte',               0, 20),
  ('chef_agence',  'Chef d''agence',                 'Caisse, billetterie, RH agence',        0, 30),
  ('caissier',     'Caissier Bus',                   'Vente billets, ouverture/fermeture',    0, 40),
  ('controleur',   'Contrôleur',                     'Validation embarquement',               0, 50),
  ('mecanicien',   'Chef mécanicien',                'Maintenance flotte',                    0, 60),
  ('chauffeur',    'Chauffeur',                      'Lecture voyages assignés',              0, 70)
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description);

-- ─── SEED PERMISSIONS (catalogue exhaustif granulaire) ───────────────
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  -- Administration
  ('admin.users.view',        'admin', 'view',   'Voir utilisateurs',          10),
  ('admin.users.create',      'admin', 'create', 'Créer utilisateurs',         11),
  ('admin.users.edit',        'admin', 'edit',   'Modifier utilisateurs',      12),
  ('admin.users.delete',      'admin', 'delete', 'Supprimer utilisateurs',     13),
  ('admin.users.reset_pwd',   'admin', 'reset',  'Réinitialiser mots de passe',14),
  ('admin.users.unlock',      'admin', 'unlock', 'Déverrouiller comptes',      15),
  ('admin.users.impersonate', 'admin', 'impersonate','Se connecter en tant que',16),
  ('admin.roles.view',        'admin', 'view',   'Voir rôles',                 20),
  ('admin.roles.manage',      'admin', 'manage', 'Gérer rôles & permissions', 21),
  ('admin.settings.view',     'admin', 'view',   'Voir paramètres',            30),
  ('admin.settings.edit',     'admin', 'edit',   'Modifier paramètres',        31),
  ('admin.audit.view',        'admin', 'view',   'Consulter journaux audit',   40),
  ('admin.audit.export',      'admin', 'export', 'Exporter journaux',          41),
  ('admin.maintenance.toggle','admin', 'toggle', 'Activer mode maintenance',   50),
  -- Référentiel
  ('referentiel.view',           'referentiel', 'view',   'Voir référentiel',           100),
  ('referentiel.create',         'referentiel', 'create', 'Créer entrées référentiel', 101),
  ('referentiel.edit',           'referentiel', 'edit',   'Modifier référentiel',       102),
  ('referentiel.delete',         'referentiel', 'delete', 'Supprimer référentiel',      103),
  ('referentiel.tariffs.manage', 'referentiel', 'tariffs','Gérer grille tarifaire',     104),
  -- Voyages
  ('voyages.view',     'voyages', 'view',   'Voir voyages',          200),
  ('voyages.create',   'voyages', 'create', 'Créer voyages',         201),
  ('voyages.edit',     'voyages', 'edit',   'Modifier voyages',      202),
  ('voyages.close',    'voyages', 'close',  'Clôturer voyages',      203),
  ('voyages.cancel',   'voyages', 'cancel', 'Annuler voyages',       204),
  -- Billetterie
  ('billetterie.view',     'billetterie', 'view',     'Voir billets',         300),
  ('billetterie.create',   'billetterie', 'create',   'Vendre billets',       301),
  ('billetterie.cancel',   'billetterie', 'cancel',   'Annuler billets',      302),
  ('billetterie.reprint',  'billetterie', 'reprint',  'Réimprimer billets',   303),
  ('billetterie.preprint', 'billetterie', 'preprint', 'Pré-impression',       304),
  ('billetterie.refund',   'billetterie', 'refund',   'Rembourser billets',   305),
  ('billetterie.bagage',   'billetterie', 'bagage',   'Gérer billets bagage', 306),
  -- Contrôle
  ('controle.view',     'controle', 'view',     'Accès contrôle',     400),
  ('controle.validate', 'controle', 'validate', 'Valider embarquement',401),
  -- Caisse
  ('caisse.view',     'caisse', 'view',     'Voir caisses',     500),
  ('caisse.open',     'caisse', 'open',     'Ouvrir caisse',    501),
  ('caisse.close',    'caisse', 'close',    'Fermer caisse',    502),
  ('caisse.validate', 'caisse', 'validate', 'Valider clôtures', 503),
  ('caisse.reverse',  'caisse', 'reverse',  'Annuler clôtures', 504),
  -- Flotte
  ('flotte.view',                'flotte', 'view',     'Voir flotte',                600),
  ('flotte.maintenance.create',  'flotte', 'maint+',   'Créer ordres maintenance',   601),
  ('flotte.maintenance.edit',    'flotte', 'maint~',   'Modifier ordres maintenance',602),
  ('flotte.maintenance.close',   'flotte', 'maint-',   'Clôturer ordres maintenance',603),
  ('flotte.fuel.log',            'flotte', 'fuel',     'Saisir carburant',           604),
  ('flotte.fuel.validate',       'flotte', 'fuelv',    'Valider carburant',          605),
  -- RH
  ('rh.view',          'rh', 'view',     'Voir RH',            700),
  ('rh.create',        'rh', 'create',   'Créer employés',     701),
  ('rh.edit',          'rh', 'edit',     'Modifier employés',  702),
  ('rh.delete',        'rh', 'delete',   'Supprimer employés', 703),
  ('rh.payroll',       'rh', 'payroll',  'Gérer paie',         704),
  ('rh.payroll.validate','rh','payrollv','Valider paie',       705),
  -- Reporting
  ('reporting.view',   'reporting', 'view',   'Voir rapports',    800),
  ('reporting.export', 'reporting', 'export', 'Exporter rapports',801),
  ('reporting.financial','reporting','fin',  'Rapports financiers',802)
ON DUPLICATE KEY UPDATE label=VALUES(label), module=VALUES(module), action=VALUES(action);

-- ─── SEED role_permissions ───────────────────────────────────────────
-- admin = toutes les permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.slug='admin';

-- raf
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='raf' AND p.slug IN (
  'admin.users.view','admin.audit.view',
  'reporting.view','reporting.export','reporting.financial',
  'caisse.view','caisse.validate','caisse.reverse',
  'rh.view','rh.payroll','rh.payroll.validate',
  'flotte.view'
);

-- exploitation
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='exploitation' AND p.slug IN (
  'referentiel.view','referentiel.create','referentiel.edit','referentiel.tariffs.manage',
  'voyages.view','voyages.create','voyages.edit','voyages.close','voyages.cancel',
  'flotte.view','flotte.maintenance.create','flotte.maintenance.edit','flotte.maintenance.close',
  'flotte.fuel.log','flotte.fuel.validate',
  'reporting.view'
);

-- chef_agence
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='chef_agence' AND p.slug IN (
  'voyages.view',
  'billetterie.view','billetterie.create','billetterie.cancel','billetterie.reprint','billetterie.preprint','billetterie.refund','billetterie.bagage',
  'caisse.view','caisse.open','caisse.close','caisse.validate',
  'rh.view',
  'reporting.view'
);

-- caissier
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='caissier' AND p.slug IN (
  'voyages.view',
  'billetterie.view','billetterie.create','billetterie.bagage',
  'caisse.view','caisse.open','caisse.close'
);

-- controleur
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='controleur' AND p.slug IN ('controle.view','controle.validate');

-- mecanicien
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='mecanicien' AND p.slug IN (
  'flotte.view','flotte.maintenance.create','flotte.maintenance.edit','flotte.maintenance.close'
);

-- chauffeur
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='chauffeur' AND p.slug='voyages.view';

-- ─── Lier les users existants à role_id ──────────────────────────────
UPDATE users u INNER JOIN roles r ON r.slug = u.role
   SET u.role_id = r.id
 WHERE u.role_id IS NULL;

-- ─── SEED app_settings ───────────────────────────────────────────────
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order) VALUES
  -- Identité société
  ('company.name',          'company','string','City Bus',                  'Nom de la société',          'Affiché dans l''entête, billets, rapports', 10),
  ('company.legal_name',    'company','string','City Bus SARL',             'Raison sociale',             '', 11),
  ('company.address',       'company','text',  'Brazzaville, Congo',         'Adresse complète',           '', 12),
  ('company.phone',         'company','string','+242 06 000 00 00',          'Téléphone',                  '', 13),
  ('company.email',         'company','string','contact@citybus.cg',         'E-mail',                     '', 14),
  ('company.niu',           'company','string','',                            'NIU',                        'Numéro d''Identification Unique', 15),
  ('company.rccm',          'company','string','',                            'RCCM',                       'Registre Commerce', 16),
  ('company.logo_path',     'company','string','',                            'Logo (chemin)',              '', 17),
  -- Sécurité
  ('security.session_lifetime',   'security','int',  '120', 'Durée session (minutes)',                  'Délai d''inactivité avant déconnexion automatique', 100),
  ('security.password_min_length','security','int',  '12',  'Longueur minimale mot de passe',           'Recommandé : 12+', 101),
  ('security.password_require_mix','security','bool','1',   'Mixité requise',                            'Majuscule, minuscule, chiffre, symbole', 102),
  ('security.password_history',   'security','int',  '5',   'Historique mots de passe',                  'Nombre de mots de passe précédents interdits', 103),
  ('security.password_max_age',   'security','int',  '90',  'Durée de vie mot de passe (jours)',         '0 = jamais', 104),
  ('security.login_max_attempts', 'security','int',  '5',   'Tentatives login max',                      'Avant verrouillage temporaire', 105),
  ('security.login_lockout_minutes','security','int','15',  'Durée verrouillage (minutes)',              '', 106),
  ('security.rate_limit_per_minute','security','int','10',  'Rate limit /minute /IP sur /login',         '', 107),
  ('security.two_factor_required','security','bool','0',   '2FA obligatoire pour tous',                 '', 108),
  ('security.two_factor_required_admin','security','bool','1','2FA obligatoire pour admin',           '', 109),
  -- Billetterie
  ('billetterie.ticket_prefix',     'billetterie','string','CB',  'Préfixe numéro billet',         '', 200),
  ('billetterie.ticket_expiration_h','billetterie','int',  '24',  'Expiration billets non utilisés (h)','', 201),
  ('billetterie.qr_secret_rotation','billetterie','int',  '0',   'Rotation secret QR (jours)',     '0 = jamais', 202),
  -- Caisse
  ('caisse.currency',     'caisse','string','FCFA','Devise',      '', 300),
  ('caisse.rounding',     'caisse','int',  '5',   'Arrondi (multiple)','Arrondit les montants au multiple choisi (FCFA)', 301),
  ('caisse.alert_threshold','caisse','int','100000','Seuil alerte caisse (FCFA)','', 302),
  -- Notifications
  ('mail.smtp_host',     'mail','string','',     'SMTP host',     '', 400),
  ('mail.smtp_port',     'mail','int',   '587',  'SMTP port',     '', 401),
  ('mail.smtp_user',     'mail','string','',     'SMTP user',     '', 402),
  ('mail.smtp_password', 'mail','secret','',     'SMTP password', '', 403),
  ('mail.smtp_encryption','mail','string','tls','Chiffrement SMTP','tls/ssl/none', 404),
  ('mail.from_address',  'mail','string','noreply@citybus.cg','Expéditeur','', 405),
  ('mail.from_name',     'mail','string','City Bus ERP','Nom expéditeur','', 406),
  -- Backups
  ('backup.enabled',     'backup','bool','0',   'Backups automatiques',     '', 500),
  ('backup.schedule',    'backup','string','daily','Fréquence',              'daily/weekly/monthly', 501),
  ('backup.retention_days','backup','int','30',  'Rétention (jours)',        '', 502),
  ('backup.path',        'backup','string','storage/backups','Dossier sauvegardes','', 503),
  -- Maintenance
  ('maintenance.enabled', 'maintenance','bool','0',     'Mode maintenance',        'Bloque tous les accès sauf admin', 600),
  ('maintenance.message', 'maintenance','text','Maintenance en cours, retour bientôt.','Message public','', 601),
  ('maintenance.allowed_ips','maintenance','text','127.0.0.1','IPs autorisées','Une IP par ligne',     602),
  -- Audit
  ('audit.retention_days','audit','int','365', 'Rétention audit (jours)',     '', 700),
  ('audit.level',         'audit','string','info','Niveau journalisation',     'debug/info/warning/error', 701)
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description), category=VALUES(category);

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- Migration: 2026_05_04_buses_missing_columns.sql
-- ============================================================

-- =========================================================
-- Migration : Ajout des colonnes manquantes de la table buses
-- Date      : 2026-05-04
-- =========================================================

ALTER TABLE buses
  ADD COLUMN IF NOT EXISTS engine_number        VARCHAR(50)  NULL AFTER model,
  ADD COLUMN IF NOT EXISTS vin                  VARCHAR(50)  NULL AFTER engine_number,
  ADD COLUMN IF NOT EXISTS color                VARCHAR(30)  NULL AFTER body_type,
  ADD COLUMN IF NOT EXISTS fuel_type            ENUM('diesel','essence','hybride','electrique') NULL AFTER color,
  ADD COLUMN IF NOT EXISTS transmission         ENUM('manuelle','automatique') NULL AFTER fuel_type,
  ADD COLUMN IF NOT EXISTS mileage_at_purchase  INT UNSIGNED NULL DEFAULT 0 AFTER km_current,
  ADD COLUMN IF NOT EXISTS insurance_company    VARCHAR(100) NULL AFTER insurance_expiry,
  ADD COLUMN IF NOT EXISTS insurance_policy     VARCHAR(100) NULL AFTER insurance_company,
  ADD COLUMN IF NOT EXISTS tech_control_center  VARCHAR(100) NULL AFTER tech_control_expiry,
  ADD COLUMN IF NOT EXISTS next_maintenance_at  DATE NULL,
  ADD COLUMN IF NOT EXISTS next_maintenance_km  INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS ac                   TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS wifi                 TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS gps_tracker          TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS equipment_extra      JSON         NULL;


-- ============================================================
-- Migration: 2026_05_04_preprint_layout_fine_options.sql
-- ============================================================

-- Migration : options fines d'affichage des supports pré-imprimés
-- Date : 2026-05-04
--
-- Ajoute sur ticket_type_configs :
--   show_company_phone      → afficher le téléphone société séparément
--   show_agency_stub        → afficher le bloc agence sur le stub
--   show_passenger_reference→ afficher la référence billet passager

-- Garantit l'existence de la table (créée pleinement par 2026_05_05_preprint_numbering)
CREATE TABLE IF NOT EXISTS ticket_type_configs (
  type_key     VARCHAR(40)  NOT NULL PRIMARY KEY,
  label        VARCHAR(120) NOT NULL,
  color        CHAR(7)      NOT NULL DEFAULT '#1A237E',
  text_color   CHAR(7)      NOT NULL DEFAULT '#FFFFFF',
  description  TEXT NULL,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garantit aussi les colonnes prérequises pour les ALTER ci-dessous
ALTER TABLE ticket_type_configs
  ADD COLUMN IF NOT EXISTS show_qr TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS show_company_contact TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS show_trip_info TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS show_seat_info TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS show_price_field TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE ticket_type_configs
  ADD COLUMN IF NOT EXISTS show_company_phone TINYINT(1) NOT NULL DEFAULT 1 AFTER show_company_contact,
  ADD COLUMN IF NOT EXISTS show_agency_stub TINYINT(1) NOT NULL DEFAULT 1 AFTER show_price_field,
  ADD COLUMN IF NOT EXISTS show_passenger_reference TINYINT(1) NOT NULL DEFAULT 1 AFTER show_agency_stub;

UPDATE ticket_type_configs
SET show_company_phone = 1,
    show_agency_stub = 1,
    show_passenger_reference = 1;

-- ============================================================
-- Migration: 2026_05_04_preprint_layout_options.sql
-- ============================================================

-- Migration : variantes de modèle et options d'affichage des supports pré-imprimés
-- Date : 2026-05-04
--
-- Ajoute sur ticket_type_configs :
--   layout_variant       → modèle visuel A/B
--   row_height_mm        → hauteur du support en millimètres
--   show_qr              → afficher le QR code
--   show_company_contact → afficher les contacts société
--   show_trip_info       → afficher les informations de trajet/date
--   show_seat_info       → afficher siège / bus
--   show_price_field     → afficher le bloc prix

-- S'assure que la table existe (sa création complète est faite plus tard
-- par 2026_05_05_preprint_numbering, ici on garantit juste sa présence
-- pour que les ALTER suivants ne plantent pas).
CREATE TABLE IF NOT EXISTS ticket_type_configs (
  type_key     VARCHAR(40)  NOT NULL PRIMARY KEY,
  label        VARCHAR(120) NOT NULL,
  color        CHAR(7)      NOT NULL DEFAULT '#1A237E',
  text_color   CHAR(7)      NOT NULL DEFAULT '#FFFFFF',
  description  TEXT NULL,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garantit aussi les colonnes utilisées dans les ALTER ci-dessous
ALTER TABLE ticket_type_configs
  ADD COLUMN IF NOT EXISTS number_prefix  VARCHAR(12) NOT NULL DEFAULT 'CB-PP' AFTER description,
  ADD COLUMN IF NOT EXISTS number_padding TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER number_prefix,
  ADD COLUMN IF NOT EXISTS number_reset   ENUM('yearly','never') NOT NULL DEFAULT 'yearly' AFTER number_padding;

ALTER TABLE ticket_type_configs
  MODIFY COLUMN type_key ENUM('passage_arret','passage_final','bagage_excedent','bagage_inclus','talon_arret') NOT NULL,
  ADD COLUMN IF NOT EXISTS layout_variant ENUM('A','B') NOT NULL DEFAULT 'A' AFTER number_reset,
  ADD COLUMN IF NOT EXISTS row_height_mm SMALLINT UNSIGNED NOT NULL DEFAULT 62 AFTER layout_variant,
  ADD COLUMN IF NOT EXISTS show_qr TINYINT(1) NOT NULL DEFAULT 1 AFTER row_height_mm,
  ADD COLUMN IF NOT EXISTS show_company_contact TINYINT(1) NOT NULL DEFAULT 1 AFTER show_qr,
  ADD COLUMN IF NOT EXISTS show_trip_info TINYINT(1) NOT NULL DEFAULT 1 AFTER show_company_contact,
  ADD COLUMN IF NOT EXISTS show_seat_info TINYINT(1) NOT NULL DEFAULT 1 AFTER show_trip_info,
  ADD COLUMN IF NOT EXISTS show_price_field TINYINT(1) NOT NULL DEFAULT 1 AFTER show_seat_info;

UPDATE ticket_type_configs
SET layout_variant = 'A',
    row_height_mm = CASE WHEN type_key = 'talon_arret' THEN 80 ELSE 62 END,
    show_qr = 1,
    show_company_contact = 1,
    show_trip_info = 1,
    show_seat_info = 1,
    show_price_field = 1;

DELETE FROM ticket_type_configs
WHERE TRIM(type_key) = '';

INSERT INTO ticket_type_configs (
  type_key, label, color, text_color, description,
  number_prefix, number_padding, number_reset,
  layout_variant, row_height_mm,
  show_qr, show_company_contact, show_trip_info, show_seat_info, show_price_field
)
VALUES
  ('passage_arret',   'Arrêt anticipé',       '#C62828', '#FFFFFF', 'Billet passager avec arrêt anticipé. 4 sections.',      'CB-PA', 5, 'yearly', 'A', 62, 1, 1, 1, 1, 1),
  ('passage_final',   'Destination finale',   '#1A237E', '#FFFFFF', 'Billet passager destination finale. 4 sections.',      'CB-PF', 5, 'yearly', 'A', 62, 1, 1, 1, 1, 1),
  ('bagage_excedent', 'Bagages excédentaires','#F57C00', '#FFFFFF', 'Talon bagage excédentaire. 4 sections.',               'CB-BE', 5, 'yearly', 'A', 62, 1, 1, 1, 1, 1),
  ('bagage_inclus',   'Bagages inclus',       '#1A237E', '#FFFFFF', 'Talon bagage inclus. 4 sections.',                     'CB-BI', 5, 'yearly', 'A', 62, 1, 1, 1, 1, 1),
  ('talon_arret',     'Talon arrêt anticipé', '#C62828', '#FFFFFF', 'Talon lié au billet passager arrêt anticipé. 3 sections.', 'CB-TA', 5, 'yearly', 'A', 80, 1, 1, 1, 1, 1)
ON DUPLICATE KEY UPDATE
  layout_variant = VALUES(layout_variant),
  row_height_mm = VALUES(row_height_mm),
  show_qr = VALUES(show_qr),
  show_company_contact = VALUES(show_company_contact),
  show_trip_info = VALUES(show_trip_info),
  show_seat_info = VALUES(show_seat_info),
  show_price_field = VALUES(show_price_field);

-- ============================================================
-- Migration: 2026_05_04_preprint_trip.sql
-- ============================================================

-- Migration: lier les pré-imprimés à un voyage + numéro de siège
-- Date: 2026-05-04

ALTER TABLE pre_printed_tickets
    ADD COLUMN trip_id     BIGINT UNSIGNED NULL AFTER batch_id,
    ADD COLUMN seat_number SMALLINT UNSIGNED NULL AFTER trip_id,
    ADD CONSTRAINT fk_pp_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    ADD INDEX idx_pp_trip (trip_id);


-- ============================================================
-- Migration: 2026_05_04_trip_crew_and_extra.sql
-- ============================================================

-- Migration: équipage multiple + colonnes supplémentaires sur trips
-- Date: 2026-05-04

-- 1. Colonnes supplémentaires sur trips
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS arrival_scheduled  TIME          NULL AFTER departure_scheduled,
    ADD COLUMN IF NOT EXISTS mileage_start      INT UNSIGNED  NULL AFTER arrival_actual,
    ADD COLUMN IF NOT EXISTS mileage_end        INT UNSIGNED  NULL AFTER mileage_start,
    ADD COLUMN IF NOT EXISTS weather_conditions VARCHAR(100)  NULL AFTER mileage_end,
    ADD COLUMN IF NOT EXISTS incident_notes     TEXT          NULL AFTER weather_conditions;

-- 2. Table équipage (plusieurs membres par voyage)
CREATE TABLE IF NOT EXISTS trip_crew (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    trip_id     BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    role        ENUM('chauffeur','convoyeur','caissier','controleur','guide','autre') NOT NULL DEFAULT 'convoyeur',
    notes       VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id)     REFERENCES trips(id)     ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY uq_trip_employee (trip_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Migration: 2026_05_05_cash_register_entries.sql
-- ============================================================

-- ============================================================================
-- 2026_05_05 : Table cash_register_entries pour mouvements caisse divers
--   (remboursements, ajustements). Référencée par BaggageTicketService et
--   désormais aussi par TicketService::cancel().
-- ============================================================================

CREATE TABLE IF NOT EXISTS cash_register_entries (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cash_register_id  BIGINT UNSIGNED NOT NULL,
    entry_type        VARCHAR(40) NOT NULL,
    amount_fcfa       INT NOT NULL,                 -- signé : négatif = sortie
    reference_type    VARCHAR(40) NULL,
    reference_id      BIGINT UNSIGNED NULL,
    note              TEXT NULL,
    created_by        BIGINT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cre_register (cash_register_id),
    INDEX idx_cre_ref (reference_type, reference_id),
    INDEX idx_cre_created (created_at),
    CONSTRAINT fk_cre_register
        FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id)
) ENGINE=InnoDB CHARSET=utf8mb4;


-- ============================================================
-- Migration: 2026_05_05_cities_lookup.sql
-- ============================================================

-- Migration : table de référence cities + bascule de agencies.city et bus_lines.*_city en FK
-- Permet l'expansion à n'importe quelle ville sans modifier le schéma.

CREATE TABLE IF NOT EXISTS cities (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(50)     NOT NULL,
    name          VARCHAR(100)    NOT NULL,
    region        VARCHAR(50)     NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    display_order INT             NOT NULL DEFAULT 100,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cities_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cities (slug, name, region, display_order) VALUES
    ('brazzaville',  'Brazzaville',  'Brazzaville',  10),
    ('pointe_noire', 'Pointe-Noire', 'Kouilou',      20),
    ('dolisie',      'Dolisie',      'Niari',        30),
    ('nkayi',        'Nkayi',        'Bouenza',      40),
    ('owando',       'Owando',       'Cuvette',      50),
    ('ouesso',       'Ouesso',       'Sangha',       60),
    ('impfondo',     'Impfondo',     'Likouala',     70),
    ('djambala',     'Djambala',     'Plateaux',     80),
    ('madingou',     'Madingou',     'Bouenza',      90),
    ('gamboma',      'Gamboma',      'Plateaux',    100)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ========== agencies ==========
ALTER TABLE agencies ADD COLUMN city_id BIGINT UNSIGNED NULL AFTER name;
UPDATE agencies a JOIN cities c ON c.slug = a.city SET a.city_id = c.id;
ALTER TABLE agencies MODIFY city_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE agencies ADD CONSTRAINT fk_agencies_city
    FOREIGN KEY (city_id) REFERENCES cities(id) ON UPDATE CASCADE;
ALTER TABLE agencies DROP COLUMN city;

-- ========== bus_lines ==========
ALTER TABLE bus_lines ADD COLUMN departure_city_id BIGINT UNSIGNED NULL AFTER name;
ALTER TABLE bus_lines ADD COLUMN arrival_city_id   BIGINT UNSIGNED NULL AFTER departure_city_id;
UPDATE bus_lines bl
   JOIN cities c1 ON c1.slug = CONVERT(bl.departure_city USING utf8mb4) COLLATE utf8mb4_unicode_ci
   JOIN cities c2 ON c2.slug = CONVERT(bl.arrival_city   USING utf8mb4) COLLATE utf8mb4_unicode_ci
   SET bl.departure_city_id = c1.id, bl.arrival_city_id = c2.id;
ALTER TABLE bus_lines MODIFY departure_city_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE bus_lines MODIFY arrival_city_id   BIGINT UNSIGNED NOT NULL;
ALTER TABLE bus_lines ADD CONSTRAINT fk_lines_dep_city
    FOREIGN KEY (departure_city_id) REFERENCES cities(id) ON UPDATE CASCADE;
ALTER TABLE bus_lines ADD CONSTRAINT fk_lines_arr_city
    FOREIGN KEY (arrival_city_id) REFERENCES cities(id) ON UPDATE CASCADE;
ALTER TABLE bus_lines DROP COLUMN departure_city;
ALTER TABLE bus_lines DROP COLUMN arrival_city;


-- ============================================================
-- Migration: 2026_05_05_entity_notes.sql
-- ============================================================

-- Migration : table entity_notes (notes horodatées liées à des entités)
-- Colonnes alignées sur src/Models/Note.php : entity_type, entity_id, content, author_id, deleted_at

CREATE TABLE IF NOT EXISTS entity_notes (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(50)     NOT NULL,
    entity_id   BIGINT UNSIGNED NOT NULL,
    content     TEXT            NOT NULL,
    author_id   BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_entity_notes_entity (entity_type, entity_id, deleted_at),
    KEY idx_entity_notes_author (author_id),
    CONSTRAINT fk_entity_notes_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Migration: 2026_05_05_lines_code_length.sql
-- ============================================================

-- Migration : agrandir bus_lines.code à VARCHAR(20) et tariffs.travel_class si besoin
ALTER TABLE bus_lines MODIFY code VARCHAR(20) NOT NULL;


-- ============================================================
-- Migration: 2026_05_05_preprint_numbering.sql
-- ============================================================

-- Migration : règles de numérotation des supports pré-imprimés par type
-- Date : 2026-05-05
--
-- Ajoute :
--   ticket_type_configs.number_prefix  → préfixe de la série (ex: CB-PA)
--   ticket_type_configs.number_padding → largeur de la séquence numérique (défaut 5)
--   ticket_type_configs.number_reset   → 'yearly' = remise à 0 au 1er janvier
-- La numérotation générée : {prefix}-{YYYY}-{NNNNN}

CREATE TABLE IF NOT EXISTS ticket_type_configs (
  type_key     VARCHAR(40)  NOT NULL PRIMARY KEY,
  label        VARCHAR(120) NOT NULL,
  color        CHAR(7)      NOT NULL DEFAULT '#1A237E',
  text_color   CHAR(7)      NOT NULL DEFAULT '#FFFFFF',
  description  TEXT NULL,
  number_prefix  VARCHAR(12) NOT NULL DEFAULT 'CB-PP',
  number_padding TINYINT UNSIGNED NOT NULL DEFAULT 5,
  number_reset   ENUM('yearly','never') NOT NULL DEFAULT 'yearly',
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter colonnes si table existait déjà
ALTER TABLE ticket_type_configs
  ADD COLUMN IF NOT EXISTS number_prefix  VARCHAR(12) NOT NULL DEFAULT 'CB-PP' AFTER description,
  ADD COLUMN IF NOT EXISTS number_padding TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER number_prefix,
  ADD COLUMN IF NOT EXISTS number_reset   ENUM('yearly','never') NOT NULL DEFAULT 'yearly' AFTER number_padding;

-- Seed des préfixes par défaut (INSERT OR UPDATE)
INSERT INTO ticket_type_configs
  (type_key, label, color, text_color, description, number_prefix, number_padding, number_reset)
VALUES
  ('passage_arret',   'Arrêt anticipé',         '#C62828', '#FFFFFF', 'Billet passager avec arrêt en cours de route. 4 sections.',        'CB-PA', 5, 'yearly'),
  ('passage_final',   'Destination finale',      '#1A237E', '#FFFFFF', 'Billet passager jusqu''à la destination finale. 4 sections.',      'CB-PF', 5, 'yearly'),
  ('bagage_excedent', 'Bagages excédentaires',   '#F57C00', '#FFFFFF', 'Talon bagages/colis excédentaires hors quota. 4 sections.',        'CB-BE', 5, 'yearly'),
  ('bagage_inclus',   'Bagages inclus',          '#1A237E', '#FFFFFF', 'Talon bagages inclus dans le prix du billet. 4 sections.',         'CB-BI', 5, 'yearly'),
  ('talon_arret',     'Talon arrêt anticipé',    '#C62828', '#FFFFFF', 'Talon rouge lié au billet passager arrêt anticipé. 3 sections.',   'CB-TA', 5, 'yearly')
ON DUPLICATE KEY UPDATE
  number_prefix  = VALUES(number_prefix),
  number_padding = VALUES(number_padding),
  number_reset   = VALUES(number_reset);


-- ============================================================
-- Migration: 2026_05_05_settings_enrich.sql
-- ============================================================

-- ============================================================
-- Enrichissement module Paramètres
-- Nouvelles catégories + settings manquants
-- ============================================================

-- Nouvelles catégories : voyage, impression, rh, sms, integration
INSERT IGNORE INTO app_settings
    (setting_key, category, setting_type, setting_value, label, description, sort_order)
VALUES
-- ─── VOYAGE ───────────────────────────────────────────────────────────────
('voyage.checkin_open_minutes',   'voyage', 'int',    '60',    'Ouverture embarquement (min avant départ)',  'Durée en minutes avant le départ où l\'embarquement s\'ouvre', 10),
('voyage.checkin_close_minutes',  'voyage', 'int',    '10',    'Fermeture embarquement (min avant départ)', 'Délai minimal avant départ pour fermer l\'embarquement', 11),
('voyage.allow_overbooking',      'voyage', 'bool',   '0',     'Autoriser surbooking',                      'Si activé, le nombre de tickets peut dépasser la capacité du bus', 12),
('voyage.overbooking_pct',        'voyage', 'int',    '10',    'Taux de surbooking (%)',                    'Pourcentage maximal de surbooking autorisé (ex: 10 = +10%)', 13),
('voyage.auto_close_minutes',     'voyage', 'int',    '30',    'Clôture automatique (min après arrivée)',   '0 = désactivé', 14),
('voyage.incident_notify_admin',  'voyage', 'bool',   '1',     'Notifier admin sur incident voyage',        '', 15),
('voyage.min_driver_rest_hours',  'voyage', 'int',    '8',     'Repos minimal chauffeur entre voyages (h)', '', 16),

-- ─── IMPRESSION / BILLETS ─────────────────────────────────────────────────
('print.receipt_copies',          'impression', 'int',    '1',    'Copies reçu caisse',                        '1 = original uniquement', 20),
('print.ticket_logo_enabled',     'impression', 'bool',   '1',    'Afficher logo sur billets',                 '', 21),
('print.ticket_footer_text',      'impression', 'text',   'Merci de voyager avec City Bus. Conservez ce billet jusqu\'à destination.',
                                                                   'Pied de page billet PDF',                   '', 22),
('print.preprint_watermark',      'impression', 'bool',   '0',    'Filigrane sur pré-imprimés',                '', 23),
('print.qr_size',                 'impression', 'int',    '200',  'Taille QR (pixels)',                        'Taille du code QR sur le billet PDF', 24),
('print.pdf_engine',              'impression', 'string', 'mpdf', 'Moteur PDF',                               'mpdf (installé)', 25),

-- ─── RH ───────────────────────────────────────────────────────────────────
('rh.payslip_period',             'rh', 'string', 'monthly', 'Périodicité fiche de paie',                    'monthly / biweekly', 30),
('rh.leave_days_annual',          'rh', 'int',    '30',      'Jours de congé annuels',                       '', 31),
('rh.cnss_rate_employee',         'rh', 'int',    '4',       'Cotisation CNSS salarié (%)',                  '', 32),
('rh.cnss_rate_employer',         'rh', 'int',    '16',      'Cotisation CNSS employeur (%)',                '', 33),
('rh.irpp_enabled',               'rh', 'bool',   '0',       'Activer calcul IRPP',                          '', 34),
('rh.overtime_rate_multiplier',   'rh', 'string', '1.5',     'Coefficient heures supplémentaires',           '', 35),
('rh.medical_cert_alert_days',    'rh', 'int',    '30',      'Alerte expiration visite médicale (jours)',    '', 36),
('rh.license_alert_days',         'rh', 'int',    '45',      'Alerte expiration permis (jours)',             '', 37),

-- ─── SMS ──────────────────────────────────────────────────────────────────
('sms.enabled',                   'sms', 'bool',   '0',    'SMS actifs',                                     '', 40),
('sms.provider',                  'sms', 'string', '',     'Fournisseur SMS',                                'Ex: twilio, orange, africas_talking', 41),
('sms.api_key',                   'sms', 'secret', '',     'API Key SMS',                                    '', 42),
('sms.sender_id',                 'sms', 'string', 'CityBus', 'Identifiant émetteur SMS',                   'Max 11 caractères', 43),
('sms.notify_ticket_sold',        'sms', 'bool',   '0',    'SMS à la vente d\'un billet',                   '', 44),
('sms.notify_trip_departure',     'sms', 'bool',   '0',    'SMS rappel départ (1h avant)',                  '', 45),
('sms.notify_trip_delay',         'sms', 'bool',   '0',    'SMS en cas de retard voyage',                   '', 46),

-- ─── INTÉGRATION ──────────────────────────────────────────────────────────
('integration.webhook_url',       'integration', 'string', '',  'Webhook sortant (URL)',                     'POST JSON à chaque événement métier', 50),
('integration.webhook_secret',    'integration', 'secret', '',  'Webhook secret',                            'Signature HMAC-SHA256', 51),
('integration.webhook_events',    'integration', 'text',   '',  'Événements webhook',                        'Un par ligne : ticket.sold, trip.closed, ...', 52),
('integration.api_enabled',       'integration', 'bool',   '0', 'API REST activée',                         '', 53),
('integration.api_key',           'integration', 'secret', '',  'API Key REST',                              '', 54),

-- ─── Compléments BILLETTERIE ──────────────────────────────────────────────
('billetterie.allow_seat_choice',     'billetterie', 'bool', '1',  'Choix de siège par le passager',             '', 203),
('billetterie.max_seats_per_sale',    'billetterie', 'int',  '10', 'Maximum de billets par transaction',         '', 204),
('billetterie.cancellation_delay_h',  'billetterie', 'int',  '2',  'Délai annulation billet (h avant départ)',   '0 = non autorisé', 205),
('billetterie.refund_pct',            'billetterie', 'int',  '80', 'Remboursement annulation (%)',               '', 206),
('billetterie.print_on_sale',         'billetterie', 'bool', '1',  'Imprimer automatiquement à la vente',        '', 207),

-- ─── Compléments CAISSE ───────────────────────────────────────────────────
('caisse.require_open_session',   'caisse', 'bool', '1',     'Exiger session ouverte pour vendre',             '', 303),
('caisse.session_max_hours',      'caisse', 'int',  '12',    'Durée max session caisse (h)',                   '', 304),
('caisse.auto_close',             'caisse', 'bool', '0',     'Clôture automatique à minuit',                   '', 305),
('caisse.allow_multi_currency',   'caisse', 'bool', '0',     'Accepter USD / EUR',                            '', 306),
('caisse.usd_rate',               'caisse', 'int',  '600',   'Taux USD → FCFA',                               '', 307),
('caisse.eur_rate',               'caisse', 'int',  '655',   'Taux EUR → FCFA',                               '', 308);


-- ============================================================
-- Migration: 2026_05_06_settings_cleanup.sql
-- ============================================================

-- Cleanup paramètres inutiles (jamais utilisés par le code) : 
-- supprimés après audit du 2026-05-06.

DELETE FROM app_settings WHERE setting_key IN (
    'caisse.usd_rate',
    'caisse.eur_rate',
    'caisse.allow_multi_currency',
    'caisse.currency',
    'billetterie.qr_secret_rotation',
    'billetterie.max_seats_per_sale',
    'print.pdf_engine',
    'audit.level',
    'rh.payslip_period',
    'rh.leave_days_annual'
);


-- ============================================================
-- Migration: 2026_05_06_trips_fk_and_sales_baggage.sql
-- ============================================================

-- Migration : 2026-05-06
-- Ajoute des contraintes manquantes sur trips et étend la table sales aux bagages.

-- 1. FK trips.driver_id → drivers.id (nullable, ON DELETE SET NULL)
ALTER TABLE trips
    ADD CONSTRAINT fk_trips_driver
    FOREIGN KEY (driver_id) REFERENCES drivers(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- 2. FK trips.convoyeur_id → drivers.id (nullable)
ALTER TABLE trips
    ADD CONSTRAINT fk_trips_convoyeur
    FOREIGN KEY (convoyeur_id) REFERENCES drivers(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 3. Étendre sales pour gérer les ventes de bagages
--    Mise en place d'un schéma polymorphique : sale_type + reference_id (tickets ou baggage_tickets)
ALTER TABLE sales
    ADD COLUMN sale_type ENUM('ticket','baggage') NOT NULL DEFAULT 'ticket' AFTER cash_register_id,
    ADD COLUMN baggage_ticket_id BIGINT UNSIGNED NULL AFTER ticket_id;

-- ticket_id devient nullable pour permettre lignes baggage
ALTER TABLE sales MODIFY ticket_id BIGINT UNSIGNED NULL;

ALTER TABLE sales
    ADD INDEX idx_sales_baggage (baggage_ticket_id),
    ADD INDEX idx_sales_type (sale_type);

ALTER TABLE sales
    ADD CONSTRAINT fk_sales_baggage_ticket
    FOREIGN KEY (baggage_ticket_id) REFERENCES baggage_tickets(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Marquer les ventes existantes comme 'ticket' (déjà la valeur par défaut)
UPDATE sales SET sale_type = 'ticket' WHERE ticket_id IS NOT NULL;


-- ============================================================
-- Migration: 2026_05_07_create_media_table.sql
-- ============================================================

-- Table polymorphique des médias (galerie + documents) attachés à n'importe quel modèle.
CREATE TABLE IF NOT EXISTS media (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mediable_type VARCHAR(64)     NOT NULL,
    mediable_id   BIGINT UNSIGNED NOT NULL,
    collection    VARCHAR(32)     NOT NULL DEFAULT 'gallery',
    file_path     VARCHAR(255)    NOT NULL,
    file_name     VARCHAR(255)    NOT NULL,
    file_hash     CHAR(64)        NULL,
    mime_type     VARCHAR(128)    NOT NULL,
    size          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    width         INT UNSIGNED    NULL,
    height        INT UNSIGNED    NULL,
    alt_text      VARCHAR(255)    NULL,
    caption       VARCHAR(255)    NULL,
    sort_order    INT             NOT NULL DEFAULT 0,
    uploaded_by   BIGINT UNSIGNED NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_media_mediable (mediable_type, mediable_id),
    INDEX idx_media_collection (mediable_type, mediable_id, collection),
    INDEX idx_media_sort (mediable_type, mediable_id, sort_order),
    CONSTRAINT fk_media_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Migration: 2026_05_08_ticket_embarque_status.sql
-- ============================================================

-- Ajout du statut 'embarque' pour les tickets (validation à l'embarquement, distinct de 'valide' à la destination)
ALTER TABLE tickets MODIFY COLUMN status
    ENUM('emis','embarque','valide','arrive','annule') NOT NULL DEFAULT 'emis';

-- Idem pour billets bagages
ALTER TABLE baggage_tickets MODIFY COLUMN status
    ENUM('emis','embarque','valide','arrive','annule') NOT NULL DEFAULT 'emis';


-- ============================================================
-- Migration: 2026_05_09_audit_mac_useragent.sql
-- ============================================================

-- Journal d'audit : ajout colonnes adresse MAC et User-Agent
-- Idempotent (IF NOT EXISTS, MariaDB 10.5+)

ALTER TABLE audit_logs
    ADD COLUMN IF NOT EXISTS mac_address VARCHAR(17)  NULL AFTER ip_address,
    ADD COLUMN IF NOT EXISTS user_agent  VARCHAR(512) NULL AFTER mac_address;


-- ============================================================
-- Migration: 2026_05_09_caisse_daily_target.sql
-- ============================================================

-- Ajout du paramètre objectif journalier de caisse
-- Utilisé par DashboardController pour afficher la jauge de progression

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret)
VALUES (
    'caisse.daily_target',
    'caisse',
    'integer',
    '2500000',
    'Objectif journalier (FCFA)',
    'Montant cible pour la jauge de progression du chiffre d''affaires sur le tableau de bord.',
    50,
    0
)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_09_create_notifications_table.sql
-- ============================================================

-- Table de persistance des notifications envoyées (audit + retry)
CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel     ENUM('email','sms','push','webhook') NOT NULL,
    recipient   VARCHAR(255)    NOT NULL,
    subject     VARCHAR(255)    NULL,
    body        TEXT            NOT NULL,
    status      ENUM('queued','sent','failed','retrying') NOT NULL DEFAULT 'sent',
    meta        JSON            NULL,
    error       VARCHAR(500)    NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_notif_channel_status (channel, status),
    INDEX idx_notif_recipient (recipient),
    INDEX idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Migration: 2026_05_09_flotte_alert_settings.sql
-- ============================================================

-- Ajout des paramètres d'alerte flotte (contrôle technique, assurance)

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret)
VALUES
('flotte.tech_control_alert_days', 'voyage', 'integer', '30', 'Alerte contrôle technique (jours)', 'Nombre de jours avant expiration du contrôle technique pour générer une alerte au tableau de bord.', 80, 0),
('flotte.insurance_alert_days', 'voyage', 'integer', '30', 'Alerte assurance (jours)', 'Nombre de jours avant expiration de l''assurance bus pour générer une alerte au tableau de bord.', 81, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_09_password_reset_ttl_setting.sql
-- ============================================================

-- Paramètre durée de validité du jeton de réinitialisation de mot de passe

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret)
VALUES (
    'security.password_reset_ttl_minutes',
    'security',
    'integer',
    '60',
    'Validité lien réinit. mot de passe (min)',
    'Durée pendant laquelle le lien envoyé par e-mail pour réinitialiser un mot de passe reste valide.',
    65,
    0
)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_09_password_resets_enhance.sql
-- ============================================================

-- Renforce la table password_resets pour le workflow self-service mot-de-passe oublié.
-- - ajout d'un id auto-incrément (la PK email seule empêchait plusieurs demandes simultanées)
-- - ajout d'expires_at (validité courte du lien)
-- - ajout de used_at (jeton à usage unique)
-- - ajout de ip_address (audit)

CREATE TABLE IF NOT EXISTS password_resets (
    email      VARCHAR(120) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Drop PK email s'il existe pour permettre plusieurs lignes par email (purge ensuite)
ALTER TABLE password_resets DROP PRIMARY KEY;

-- Ajout des colonnes manquantes (idempotent côté MySQL : utiliser un script externe pour vérifier)
ALTER TABLE password_resets
    ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
    ADD COLUMN expires_at TIMESTAMP NULL AFTER created_at,
    ADD COLUMN used_at    TIMESTAMP NULL AFTER expires_at,
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER used_at,
    ADD INDEX idx_password_resets_email (email),
    ADD INDEX idx_password_resets_token (token);


-- ============================================================
-- Migration: 2026_05_09_webhooks_settings.sql
-- ============================================================

-- Paramètres pour les webhooks sortants

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret)
VALUES
('integration.webhook_url',    'integration', 'string', '',
 'URL du webhook',
 'URL HTTPS qui recevra les événements (ticket.sold, trip.departure, …) en POST JSON. Vide = désactivé.',
 10, 0),
('integration.webhook_secret', 'integration', 'secret', '',
 'Secret HMAC',
 'Clé partagée pour signer le payload (en-tête X-CityBus-Signature : sha256=…).',
 11, 1),
('integration.webhook_events', 'integration', 'string', '',
 'Événements abonnés',
 'Liste d''événements (un par ligne ou virgule). Vide ou * = tous les événements. Ex: ticket.sold, trip.departure.',
 12, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_10_accounting.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_05_10_cargo_module.sql
-- ============================================================

-- Module Cargo / Colis — gestion des envois entre agences
-- (GAP-10 du plan d'implémentation v2.0)

CREATE TABLE IF NOT EXISTS parcel_tariffs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    label           VARCHAR(100)    NOT NULL,
    parcel_type     ENUM('document','colis','fragile','special') NOT NULL DEFAULT 'colis',
    weight_min_kg   DECIMAL(8,2)    NOT NULL DEFAULT 0,
    weight_max_kg   DECIMAL(8,2)    NULL,
    fixed_fee_fcfa  INT UNSIGNED    NOT NULL DEFAULT 0,
    price_per_kg    INT UNSIGNED    NOT NULL DEFAULT 0,
    price_per_m3    INT UNSIGNED    NOT NULL DEFAULT 0,
    insurance_pct   DECIMAL(5,2)    NULL COMMENT 'Pourcentage sur la valeur déclarée',
    valid_from      DATE            NULL,
    valid_until     DATE            NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order      INT             NOT NULL DEFAULT 100,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_parcel_tariffs_type (parcel_type),
    INDEX idx_parcel_tariffs_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parcels (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parcel_number          VARCHAR(30)     NOT NULL COMMENT 'Numéro unique : PCL-YYYYMM-XXXXXX',
    qr_token               VARCHAR(64)     NOT NULL COMMENT 'Token unique pour QR / scan',

    -- Affectation au voyage (peut rester null tant que pas chargé)
    trip_id                BIGINT UNSIGNED NULL,
    origin_agency_id       BIGINT UNSIGNED NOT NULL,
    destination_agency_id  BIGINT UNSIGNED NOT NULL,

    -- Expéditeur
    sender_name            VARCHAR(120)    NOT NULL,
    sender_phone           VARCHAR(30)     NOT NULL,
    sender_id_doc          VARCHAR(50)     NULL,
    sender_address         VARCHAR(200)    NULL,

    -- Destinataire
    recipient_name         VARCHAR(120)    NOT NULL,
    recipient_phone        VARCHAR(30)     NOT NULL,
    recipient_id_doc       VARCHAR(50)     NULL,
    recipient_address      VARCHAR(200)    NULL,

    -- Caractéristiques
    parcel_type            ENUM('document','colis','fragile','special') NOT NULL DEFAULT 'colis',
    description            VARCHAR(500)    NOT NULL,
    weight_kg              DECIMAL(8,2)    NOT NULL,
    volume_m3              DECIMAL(8,3)    NULL,
    declared_value_fcfa    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    pieces_count           INT UNSIGNED    NOT NULL DEFAULT 1,

    -- Tarification
    parcel_tariff_id       BIGINT UNSIGNED NULL,
    base_price_fcfa        INT UNSIGNED    NOT NULL DEFAULT 0,
    insurance_fee_fcfa     INT UNSIGNED    NOT NULL DEFAULT 0,
    tax_amount_fcfa        INT UNSIGNED    NOT NULL DEFAULT 0,
    total_price_fcfa       INT UNSIGNED    NOT NULL DEFAULT 0,
    payment_method         ENUM('especes','mobile_money','carte','virement','a_destination') NOT NULL DEFAULT 'especes',
    paid_at_origin         TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '0 = paiement à destination',

    -- Workflow
    status                 ENUM('depose','en_transit','arrive','retire','perdu','endommage','retourne') NOT NULL DEFAULT 'depose',

    -- Dépôt
    deposited_at           DATETIME        NOT NULL,
    deposited_by           BIGINT UNSIGNED NOT NULL COMMENT 'user_id',
    cash_register_id       BIGINT UNSIGNED NULL,

    -- Retrait
    picked_up_at           DATETIME        NULL,
    picked_up_by           BIGINT UNSIGNED NULL COMMENT 'user_id qui remet',
    pickup_recipient_name  VARCHAR(120)    NULL COMMENT 'Personne ayant retiré (si différent)',
    pickup_id_doc          VARCHAR(50)     NULL,
    pickup_signature_path  VARCHAR(255)    NULL COMMENT 'Image de signature ou photo CNI',
    pickup_notes           TEXT            NULL,

    -- Métadonnées
    notes                  TEXT            NULL,
    cancelled_at           DATETIME        NULL,
    cancelled_by           BIGINT UNSIGNED NULL,
    cancel_reason          VARCHAR(255)    NULL,
    created_at             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at             TIMESTAMP       NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_parcel_number (parcel_number),
    UNIQUE KEY uk_parcel_qr_token (qr_token),
    INDEX idx_parcels_trip (trip_id),
    INDEX idx_parcels_status (status),
    INDEX idx_parcels_dest_agency (destination_agency_id),
    INDEX idx_parcels_origin_agency (origin_agency_id),
    INDEX idx_parcels_recipient_phone (recipient_phone),
    INDEX idx_parcels_sender_phone (sender_phone),
    INDEX idx_parcels_deposited_at (deposited_at),
    CONSTRAINT fk_parcels_trip          FOREIGN KEY (trip_id)               REFERENCES trips(id)            ON DELETE SET NULL,
    CONSTRAINT fk_parcels_origin        FOREIGN KEY (origin_agency_id)      REFERENCES agencies(id),
    CONSTRAINT fk_parcels_destination   FOREIGN KEY (destination_agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_parcels_tariff        FOREIGN KEY (parcel_tariff_id)      REFERENCES parcel_tariffs(id)   ON DELETE SET NULL,
    CONSTRAINT fk_parcels_deposited_by  FOREIGN KEY (deposited_by)          REFERENCES users(id),
    CONSTRAINT fk_parcels_picked_up_by  FOREIGN KEY (picked_up_by)          REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_parcels_cancelled_by  FOREIGN KEY (cancelled_by)          REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal de suivi (timeline)
CREATE TABLE IF NOT EXISTS parcel_tracking_events (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parcel_id    BIGINT UNSIGNED NOT NULL,
    event_type   ENUM('depose','charge','depart','arrivee_etape','arrivee_destination','retrait','litige','retour','annule','message') NOT NULL,
    description  VARCHAR(255)    NULL,
    location     VARCHAR(150)    NULL,
    actor_id     BIGINT UNSIGNED NULL,
    occurred_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pte_parcel (parcel_id),
    INDEX idx_pte_occurred (occurred_at),
    CONSTRAINT fk_pte_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE,
    CONSTRAINT fk_pte_actor  FOREIGN KEY (actor_id)  REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions cargo
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('cargo.view', 'cargo', 'view', 'Voir le cargo', 110),
    ('cargo.create', 'cargo', 'create', 'Déposer un colis', 111),
    ('cargo.edit', 'cargo', 'edit', 'Modifier un colis', 112),
    ('cargo.delete', 'cargo', 'delete', 'Annuler un colis', 113),
    ('cargo.pickup', 'cargo', 'pickup', 'Remettre un colis', 114),
    ('cargo.tariffs', 'cargo', 'tariffs', 'Gérer les tarifs cargo', 115),
    ('cargo.export', 'cargo', 'export', 'Exporter cargo', 116)
ON DUPLICATE KEY UPDATE slug = slug;

-- Donner ces permissions au rôle admin et raf
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'cargo.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings cargo
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('cargo.enabled',                'cargo', 'boolean', '1',   'Module Cargo activé', 'Active la prise en charge des colis et du fret.', 10, 0),
('cargo.numbering_format',       'cargo', 'string',  'PCL-{YYYYMM}-{seq:06d}', 'Format numérotation', 'Format du numéro de colis. Variables: {YYYY}, {YYYYMM}, {seq:06d}.', 11, 0),
('cargo.default_pickup_days',    'cargo', 'integer', '7',   'Délai retrait (jours)', 'Nombre de jours après arrivée au-delà duquel un colis non retiré est marqué pour relance.', 12, 0),
('cargo.notify_recipient_sms',   'cargo', 'boolean', '1',   'SMS au destinataire', 'Envoyer un SMS au destinataire à l''arrivée du colis.', 13, 0),
('cargo.notify_recipient_at_deposit', 'cargo', 'boolean', '1', 'SMS au dépôt', 'Envoyer un SMS au destinataire dès le dépôt.', 14, 0),
('cargo.tax_rate_percent',       'cargo', 'decimal', '0',   'TVA cargo (%)',     'Taux de TVA applicable aux envois cargo.', 15, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Tarifs par défaut (à ajuster ensuite par l'opérateur)
INSERT INTO parcel_tariffs (label, parcel_type, weight_min_kg, weight_max_kg, fixed_fee_fcfa, price_per_kg, insurance_pct, sort_order) VALUES
    ('Document jusqu''à 2 kg',   'document', 0,    2,   2000,    0,  0.5, 10),
    ('Petit colis 0-5 kg',       'colis',    0,    5,   3000,  500,  0.5, 20),
    ('Colis standard 5-20 kg',   'colis',    5,   20,   2500,  400,  0.5, 30),
    ('Gros colis 20-50 kg',      'colis',   20,   50,   2000,  350,  0.5, 40),
    ('Volumineux > 50 kg',       'colis',   50, NULL,   2000,  300,  0.5, 50),
    ('Fragile (à manipuler)',    'fragile',  0, NULL,   5000,  600,  1.0, 60),
    ('Spécial (sur devis)',      'special',  0, NULL,  10000,  800,  2.0, 70)
ON DUPLICATE KEY UPDATE label = label;


-- ============================================================
-- Migration: 2026_05_10_crm_customers.sql
-- ============================================================

-- CRM passagers (GAP-02)
-- Dossier client unifié, dédupliqué par numéro de téléphone normalisé.

CREATE TABLE IF NOT EXISTS customers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    phone_norm      VARCHAR(20)     NOT NULL COMMENT 'Numéro normalisé (chiffres uniquement, +indicatif)',
    phone_display   VARCHAR(30)     NULL,
    first_name      VARCHAR(80)     NULL,
    last_name       VARCHAR(80)     NULL,
    email           VARCHAR(120)    NULL,
    id_doc_type     ENUM('cni','passeport','permis','autre') NULL,
    id_doc_number   VARCHAR(50)     NULL,
    date_of_birth   DATE            NULL,
    gender          ENUM('M','F','O') NULL,
    notes           TEXT            NULL,

    -- Compteurs (mis à jour par triggers/jobs)
    total_trips     INT UNSIGNED    NOT NULL DEFAULT 0,
    total_spent     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_baggage   INT UNSIGNED    NOT NULL DEFAULT 0,
    total_parcels   INT UNSIGNED    NOT NULL DEFAULT 0,
    last_trip_at    DATETIME        NULL,
    first_trip_at   DATETIME        NULL,

    -- Préférences
    preferred_seat        VARCHAR(10)  NULL,
    preferred_contact     ENUM('sms','email','call','whatsapp') NULL DEFAULT 'sms',
    sms_opt_in            TINYINT(1)   NOT NULL DEFAULT 1,
    email_opt_in          TINYINT(1)   NOT NULL DEFAULT 1,

    -- Méta
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP       NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_customers_phone_norm (phone_norm),
    INDEX idx_customers_email (email),
    INDEX idx_customers_last_trip (last_trip_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien tickets ↔ customers
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS customer_id BIGINT UNSIGNED NULL AFTER passenger_phone,
    ADD INDEX idx_tickets_customer (customer_id),
    ADD CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- Lien baggage_tickets ↔ customers
ALTER TABLE baggage_tickets
    ADD COLUMN IF NOT EXISTS customer_id BIGINT UNSIGNED NULL,
    ADD INDEX idx_bag_customer (customer_id),
    ADD CONSTRAINT fk_bag_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('crm.view', 'crm', 'view', 'Voir le CRM clients', 250),
    ('crm.edit', 'crm', 'edit', 'Modifier les clients', 251),
    ('crm.export', 'crm', 'export', 'Exporter le CRM', 252)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation','chef_agence') AND p.slug LIKE 'crm.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('crm.enabled',          'crm', 'boolean', '1', 'CRM activé', 'Active la création/déduplication automatique des dossiers clients à la vente.', 260, 0),
('crm.dedup_strategy',   'crm', 'string',  'phone_normalized', 'Stratégie de déduplication', 'phone_normalized | email | name_phone', 261, 0),
('crm.country_code',     'crm', 'string',  '+242', 'Indicatif pays par défaut', 'Préfixe ajouté aux numéros locaux pour normalisation.', 262, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_10_disruption_columns.sql
-- ============================================================

-- Colonnes nécessaires pour la disruption (GAP-08)

ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS cancellation_reason VARCHAR(255) NULL AFTER status;

ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME    NULL,
    ADD COLUMN IF NOT EXISTS cancelled_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) NULL,
    ADD INDEX idx_tickets_cancelled (cancelled_at);


-- ============================================================
-- Migration: 2026_05_10_driver_hos.sql
-- ============================================================

-- Conformité temps de conduite chauffeurs (GAP-13)
-- Journal des heures de conduite, repos, pauses pour conformité légale.

CREATE TABLE IF NOT EXISTS driver_hours_log (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    driver_id         BIGINT UNSIGNED NOT NULL,
    trip_id           BIGINT UNSIGNED NULL,
    log_type          ENUM('conduite','pause','repos_quotidien','repos_hebdo','disponibilite','autre') NOT NULL,
    start_at          DATETIME        NOT NULL,
    end_at            DATETIME        NULL,
    duration_minutes  INT             NULL COMMENT 'Calculé à la fermeture',
    location          VARCHAR(150)    NULL,
    notes             TEXT            NULL,
    source            ENUM('auto_trip','manual','tachograph','app_mobile') NOT NULL DEFAULT 'manual',
    created_by        BIGINT UNSIGNED NULL,
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_dhl_driver (driver_id),
    INDEX idx_dhl_trip (trip_id),
    INDEX idx_dhl_period (driver_id, start_at, end_at),
    INDEX idx_dhl_type (log_type),
    CONSTRAINT fk_dhl_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_dhl_trip   FOREIGN KEY (trip_id)   REFERENCES trips(id)   ON DELETE SET NULL,
    CONSTRAINT fk_dhl_user   FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('hos.view', 'hos', 'view', 'Voir HOS chauffeurs', 220),
    ('hos.edit', 'hos', 'edit', 'Saisir/éditer HOS', 221),
    ('hos.override', 'hos', 'override', 'Outrepasser limites HOS', 222)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug LIKE 'hos.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings réglementaires
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('hos.daily_max_hours',          'rh', 'integer', '9',  'Conduite max journalière (h)',     'Maximum d''heures de conduite par jour (réglementation OIT/CEMAC).', 230, 0),
('hos.daily_max_extended_hours', 'rh', 'integer', '10', 'Conduite max étendue (h)',         'Maximum exceptionnel autorisé 2 fois par semaine.', 231, 0),
('hos.weekly_max_hours',         'rh', 'integer', '56', 'Conduite max hebdomadaire (h)',    'Maximum d''heures de conduite sur 7 jours glissants.', 232, 0),
('hos.biweekly_max_hours',       'rh', 'integer', '90', 'Conduite max bihebdo (h)',         'Maximum sur 14 jours glissants.', 233, 0),
('hos.continuous_max_minutes',   'rh', 'integer', '270','Conduite continue max (min)',      'Au-delà : pause obligatoire de 45 min minimum (4h30).', 234, 0),
('hos.required_break_minutes',   'rh', 'integer', '45', 'Pause obligatoire min (min)',      'Pause obligatoire après conduite continue.', 235, 0),
('hos.daily_rest_minutes',       'rh', 'integer', '660','Repos quotidien (min)',            'Repos minimum entre deux journées (11 h = 660 min).', 236, 0),
('hos.weekly_rest_hours',        'rh', 'integer', '45', 'Repos hebdomadaire (h)',           'Repos minimum sur 7 jours (45 h consécutives).', 237, 0),
('hos.enforcement_mode',         'rh', 'string',  'warning', 'Mode d''application',         'warning = avertir / block = empêcher l''affectation.', 238, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_10_od_pricing.sql
-- ============================================================

-- Tarifs par origine-destination (GAP-04)
-- Permet de tarifer par couple (arrêt embarquement, arrêt débarquement) et
-- de revendre un siège sur un segment aval libre.

CREATE TABLE IF NOT EXISTS od_pricing (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    line_id             BIGINT UNSIGNED NOT NULL,
    from_stop_id        BIGINT UNSIGNED NOT NULL,
    to_stop_id          BIGINT UNSIGNED NOT NULL,
    ticket_type         VARCHAR(50)     NOT NULL DEFAULT 'finale',
    passenger_category  VARCHAR(50)     NOT NULL DEFAULT 'adulte',
    travel_class        VARCHAR(50)     NOT NULL DEFAULT 'standard',
    price_fcfa          INT UNSIGNED    NOT NULL,
    distance_km         DECIMAL(8,2)    NULL,
    valid_from          DATE            NULL,
    valid_until         DATE            NULL,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_od_pricing (line_id, from_stop_id, to_stop_id, ticket_type, passenger_category, travel_class),
    INDEX idx_od_pricing_line (line_id, is_active),
    CONSTRAINT fk_od_line FOREIGN KEY (line_id)      REFERENCES bus_lines(id) ON DELETE CASCADE,
    CONSTRAINT fk_od_from FOREIGN KEY (from_stop_id) REFERENCES stops(id)     ON DELETE CASCADE,
    CONSTRAINT fk_od_to   FOREIGN KEY (to_stop_id)   REFERENCES stops(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings O-D pricing
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('pricing.od_enabled',     'voyage', 'boolean', '1',  'Tarifs origine-destination', 'Active la grille tarifaire par couple O-D. Si désactivé, tarifs ligne globaux.', 90, 0),
('pricing.od_resale_seat', 'voyage', 'boolean', '1',  'Revente de sièges en aval', 'Permet de revendre un siège libéré à un arrêt intermédiaire.', 91, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_10_tax_invoicing.sql
-- ============================================================

-- TVA & facturation fiscale conforme (GAP-21)
-- Permet de stocker les taux applicables, ventiler HT/TVA/TTC et générer des factures séquentielles.

CREATE TABLE IF NOT EXISTS tax_rates (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(20)     NOT NULL COMMENT 'Code court ex: TVA-18, EXO',
    label         VARCHAR(100)    NOT NULL,
    rate_percent  DECIMAL(6,3)    NOT NULL DEFAULT 0,
    is_default    TINYINT(1)      NOT NULL DEFAULT 0,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    valid_from    DATE            NULL,
    valid_until   DATE            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tax_rates_code (code),
    INDEX idx_tax_rates_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tax_rates (code, label, rate_percent, is_default, is_active) VALUES
    ('EXO',    'Exonéré',                       0.000, 0, 1),
    ('TVA-0',  'TVA 0 % (export)',              0.000, 0, 1),
    ('TVA-5',  'TVA 5 % (réduit)',              5.000, 0, 1),
    ('TVA-18', 'TVA 18 % (taux normal Congo)', 18.000, 1, 1)
ON DUPLICATE KEY UPDATE code = code;

-- Compteur séquentiel pour numéros de facture (par année + agence)
CREATE TABLE IF NOT EXISTS invoice_sequences (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `year`      SMALLINT UNSIGNED NOT NULL,
    agency_id   BIGINT UNSIGNED NULL,
    next_number INT UNSIGNED    NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_inv_seq (`year`, agency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout colonnes fiscales sur tickets (idempotent)
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS price_ht_fcfa     INT UNSIGNED NULL AFTER price_fcfa,
    ADD COLUMN IF NOT EXISTS tax_rate_id       BIGINT UNSIGNED NULL AFTER price_ht_fcfa,
    ADD COLUMN IF NOT EXISTS tax_rate_percent  DECIMAL(6,3) NULL AFTER tax_rate_id,
    ADD COLUMN IF NOT EXISTS tax_amount_fcfa   INT UNSIGNED NULL AFTER tax_rate_percent,
    ADD COLUMN IF NOT EXISTS invoice_number    VARCHAR(30)  NULL AFTER tax_amount_fcfa,
    ADD INDEX idx_tickets_invoice (invoice_number);

-- Ajout colonnes fiscales sur baggage_tickets
ALTER TABLE baggage_tickets
    ADD COLUMN IF NOT EXISTS price_ht_fcfa     INT UNSIGNED NULL AFTER total_price_fcfa,
    ADD COLUMN IF NOT EXISTS tax_rate_id       BIGINT UNSIGNED NULL AFTER price_ht_fcfa,
    ADD COLUMN IF NOT EXISTS tax_rate_percent  DECIMAL(6,3) NULL AFTER tax_rate_id,
    ADD COLUMN IF NOT EXISTS tax_amount_fcfa   INT UNSIGNED NULL AFTER tax_rate_percent,
    ADD COLUMN IF NOT EXISTS invoice_number    VARCHAR(30)  NULL AFTER tax_amount_fcfa;

-- Ajout colonne tax_rate_id sur tariffs
ALTER TABLE tariffs
    ADD COLUMN IF NOT EXISTS tax_rate_id BIGINT UNSIGNED NULL,
    ADD CONSTRAINT fk_tariffs_tax FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL;

-- Permission
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('finance.tax.view', 'finance', 'view', 'Voir les déclarations fiscales', 200),
    ('finance.tax.export', 'finance', 'export', 'Exporter les déclarations fiscales', 201)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'finance.tax.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('tax.default_rate_id',     'finance', 'integer', '4',  'Taux TVA par défaut', 'ID du taux applicable aux ventes par défaut.', 200, 0),
('tax.prices_include_tax',  'finance', 'boolean', '1',  'Prix TTC',            'Si activé, les prix saisis sont considérés TTC ; sinon HT.', 201, 0),
('tax.invoice_prefix',      'finance', 'string',  'FCT', 'Préfixe facture',     'Préfixe utilisé dans le numéro de facture (FCT-AAAA-NNNNNN).', 202, 0),
('tax.legal_mention',       'finance', 'string',  'TVA non applicable - Article 293-B du CGI Congo', 'Mention fiscale ticket', 'Texte affiché sur les tickets/factures.', 203, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_10_trip_pnl.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_05_10_waitlist_promo_vouchers.sql
-- ============================================================

-- Liste d'attente, codes promo, fidélité et avoirs (GAP-06, GAP-08, GAP-12)

-- ─── Liste d'attente (GAP-12) ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS waitlist_entries (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    passenger_name  VARCHAR(120)    NOT NULL,
    passenger_phone VARCHAR(30)     NOT NULL,
    seats_requested INT UNSIGNED    NOT NULL DEFAULT 1,
    position        INT UNSIGNED    NOT NULL,
    status          ENUM('waiting','notified','converted','expired','cancelled') NOT NULL DEFAULT 'waiting',
    requested_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notified_at     DATETIME        NULL,
    confirmation_deadline DATETIME  NULL,
    converted_ticket_id   BIGINT UNSIGNED NULL,
    notes           VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    INDEX idx_wl_trip (trip_id, status, position),
    INDEX idx_wl_customer (customer_id),
    INDEX idx_wl_phone (passenger_phone),
    CONSTRAINT fk_wl_trip     FOREIGN KEY (trip_id)             REFERENCES trips(id)     ON DELETE CASCADE,
    CONSTRAINT fk_wl_customer FOREIGN KEY (customer_id)         REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_wl_ticket   FOREIGN KEY (converted_ticket_id) REFERENCES tickets(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Codes promo (GAP-06) ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS promo_codes (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                 VARCHAR(40)     NOT NULL,
    label                VARCHAR(120)    NOT NULL,
    discount_type        ENUM('percent','fixed','free_seat') NOT NULL DEFAULT 'percent',
    discount_value       INT UNSIGNED    NOT NULL DEFAULT 0,
    min_amount_fcfa      INT UNSIGNED    NOT NULL DEFAULT 0,
    max_discount_fcfa    INT UNSIGNED    NULL,
    max_uses             INT UNSIGNED    NULL,
    max_uses_per_customer INT UNSIGNED   NULL DEFAULT 1,
    used_count           INT UNSIGNED    NOT NULL DEFAULT 0,
    valid_from           DATETIME        NULL,
    valid_until          DATETIME        NULL,
    applicable_lines     JSON            NULL COMMENT 'Liste des line_id (vide = toutes)',
    applicable_categories JSON           NULL COMMENT 'passenger_category (vide = toutes)',
    is_active            TINYINT(1)      NOT NULL DEFAULT 1,
    created_by           BIGINT UNSIGNED NULL,
    created_at           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_promo_code (code),
    INDEX idx_promo_active (is_active, valid_from, valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promo_redemptions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    promo_id      BIGINT UNSIGNED NOT NULL,
    customer_id   BIGINT UNSIGNED NULL,
    ticket_id     BIGINT UNSIGNED NULL,
    discount_fcfa INT UNSIGNED    NOT NULL,
    used_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pr_promo (promo_id),
    INDEX idx_pr_customer (customer_id),
    CONSTRAINT fk_pr_promo    FOREIGN KEY (promo_id)    REFERENCES promo_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_customer FOREIGN KEY (customer_id) REFERENCES customers(id)   ON DELETE SET NULL,
    CONSTRAINT fk_pr_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Programme fidélité (GAP-06) ────────────────────────────────────
ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS loyalty_points INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS loyalty_tier   ENUM('standard','silver','gold','platinum') NOT NULL DEFAULT 'standard';

CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    points_delta INT            NOT NULL COMMENT 'Positif = gain, négatif = utilisation',
    balance_after INT UNSIGNED  NOT NULL,
    reason      VARCHAR(120)    NOT NULL,
    ticket_id   BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_loyalty_customer (customer_id, created_at),
    CONSTRAINT fk_loyalty_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_loyalty_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Avoirs / vouchers (GAP-08 disruption) ──────────────────────────
CREATE TABLE IF NOT EXISTS vouchers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(40)     NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    issued_amount   INT UNSIGNED    NOT NULL,
    remaining_amount INT UNSIGNED   NOT NULL,
    reason          VARCHAR(255)    NULL,
    source_trip_id  BIGINT UNSIGNED NULL COMMENT 'Voyage à l''origine (incident, annulation)',
    issued_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valid_until     DATE            NULL,
    used_at         TIMESTAMP       NULL,
    used_on_ticket  BIGINT UNSIGNED NULL,
    is_void         TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_voucher_code (code),
    INDEX idx_voucher_customer (customer_id),
    CONSTRAINT fk_v_customer FOREIGN KEY (customer_id)    REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_v_trip     FOREIGN KEY (source_trip_id) REFERENCES trips(id)     ON DELETE SET NULL,
    CONSTRAINT fk_v_ticket   FOREIGN KEY (used_on_ticket) REFERENCES tickets(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Permissions et settings ────────────────────────────────────────
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('waitlist.view',   'waitlist', 'view',   'Voir liste d''attente',  270),
    ('waitlist.manage', 'waitlist', 'manage', 'Gérer liste d''attente', 271),
    ('promo.view', 'promo', 'view', 'Voir codes promo', 280),
    ('promo.manage', 'promo', 'manage', 'Gérer codes promo', 281),
    ('vouchers.view', 'vouchers', 'view', 'Voir avoirs', 282),
    ('vouchers.issue', 'vouchers', 'issue', 'Émettre un avoir', 283)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('waitlist.view','waitlist.manage','promo.view','promo.manage','vouchers.view','vouchers.issue')
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('waitlist.confirmation_minutes', 'billetterie', 'integer', '30', 'Délai confirmation liste d''attente', 'Minutes laissées au passager notifié pour confirmer.', 290, 0),
('loyalty.enabled',               'commerce',   'boolean', '1',   'Programme fidélité activé', '', 291, 0),
('loyalty.points_per_fcfa',       'commerce',   'decimal', '0.001', 'Points / FCFA dépensé', 'Points gagnés par FCFA dépensé. 0.001 = 1 point pour 1000 FCFA.', 292, 0),
('loyalty.points_to_fcfa',        'commerce',   'integer', '10', 'FCFA par point', 'Conversion à l''utilisation : 1 point = X FCFA de remise.', 293, 0),
('loyalty.tier_silver_points',    'commerce',   'integer', '500',   'Seuil silver (points)', '', 294, 0),
('loyalty.tier_gold_points',      'commerce',   'integer', '2000',  'Seuil gold (points)', '', 295, 0),
('loyalty.tier_platinum_points',  'commerce',   'integer', '10000', 'Seuil platinum (points)', '', 296, 0),
('vouchers.default_validity_days','commerce',   'integer', '90',  'Durée validité avoir (jours)', '', 297, 0),
('disruption.auto_voucher_pct',   'commerce',   'integer', '100', 'Avoir auto en cas d''annulation (%)', '% du prix transformé en avoir lors d''annulation par opérateur.', 298, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_11_caisse_multi_modes.sql
-- ============================================================

-- Caisse multi-modes (GAP-26) : ventilation et clôture par mode de paiement

-- Ajout colonnes mode-spécifiques sur clôtures (idempotent)
ALTER TABLE daily_closures
    ADD COLUMN IF NOT EXISTS counted_cash_fcfa            INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS counted_mobile_money_fcfa    INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS counted_card_fcfa            INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS counted_bank_transfer_fcfa   INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS counted_voucher_fcfa         INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_cash_fcfa           INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_mobile_money_fcfa   INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_card_fcfa           INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_bank_transfer_fcfa  INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_voucher_fcfa        INT UNSIGNED NULL;

-- S'assurer que la colonne payment_method de sales accepte tous les modes
ALTER TABLE sales
    MODIFY COLUMN payment_method ENUM('especes','mobile_money','carte','virement','voucher','autre','mobile') NOT NULL DEFAULT 'especes';


-- ============================================================
-- Migration: 2026_05_11_currencies_oauth.sql
-- ============================================================

-- Multi-devises (GAP-24) + OAuth2 API publique (GAP-34)

CREATE TABLE IF NOT EXISTS currencies (
    code         CHAR(3)         NOT NULL COMMENT 'ISO 4217',
    label        VARCHAR(60)     NOT NULL,
    symbol       VARCHAR(10)     NOT NULL,
    rate_to_base DECIMAL(15,6)   NOT NULL DEFAULT 1 COMMENT 'Taux vers la devise de base (FCFA)',
    is_base      TINYINT(1)      NOT NULL DEFAULT 0,
    is_active    TINYINT(1)      NOT NULL DEFAULT 1,
    decimals     TINYINT         NOT NULL DEFAULT 0,
    updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO currencies (code, label, symbol, rate_to_base, is_base, decimals) VALUES
    ('XAF', 'Franc CFA BEAC', 'FCFA',  1.000000, 1, 0),
    ('XOF', 'Franc CFA BCEAO','CFA',   1.000000, 0, 0),
    ('EUR', 'Euro',           '€',   655.957000, 0, 2),
    ('USD', 'Dollar US',      '$',   600.000000, 0, 2),
    ('CDF', 'Franc Congolais','FC',    0.220000, 0, 0)
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- OAuth2 clients (API publique)
CREATE TABLE IF NOT EXISTS oauth_clients (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id       VARCHAR(80)     NOT NULL,
    client_secret_hash VARCHAR(255) NOT NULL,
    name            VARCHAR(120)    NOT NULL,
    description     VARCHAR(255)    NULL,
    scopes          VARCHAR(500)    NOT NULL DEFAULT 'read',
    rate_limit_per_min INT UNSIGNED NOT NULL DEFAULT 60,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    partner_id      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at    DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_oauth_client_id (client_id),
    INDEX idx_oauth_active (is_active),
    INDEX idx_oauth_partner (partner_id)
    -- FK vers sales_partners(id) volontairement omise (résout l'ordre de migrations)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth_access_tokens (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    BIGINT UNSIGNED NOT NULL,
    token_hash   VARCHAR(64)     NOT NULL,
    scopes       VARCHAR(500)    NOT NULL,
    issued_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME        NOT NULL,
    revoked      TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_token_hash (token_hash),
    INDEX idx_oauth_token_client (client_id, expires_at),
    CONSTRAINT fk_oat_client FOREIGN KEY (client_id) REFERENCES oauth_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_request_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    BIGINT UNSIGNED NULL,
    method       VARCHAR(10)     NOT NULL,
    path         VARCHAR(255)    NOT NULL,
    status_code  INT             NULL,
    duration_ms  INT             NULL,
    ip_address   VARCHAR(45)     NULL,
    request_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_api_client_time (client_id, request_at),
    INDEX idx_api_path (path, request_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('api.tokens.manage', 'api', 'manage', 'Gérer clients API', 400),
    ('currencies.manage', 'currencies', 'manage', 'Gérer devises', 401)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'admin' AND p.slug IN ('api.tokens.manage','currencies.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('currency.base_code',     'finance', 'string',  'XAF', 'Devise de base', 'Code ISO 4217 de la devise principale.', 410, 0),
('currency.display_alt',   'finance', 'boolean', '0',   'Afficher devise alternative', 'Affiche le prix dans une devise secondaire à côté.', 411, 0),
('api.token_ttl_hours',    'admin',   'integer', '24',  'Durée jeton API (h)', '', 412, 0),
('api.rate_limit_per_min', 'admin',   'integer', '60',  'Limite de requêtes / min', 'Par défaut, surchargeable par client.', 413, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_11_gps_control_tower.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_05_11_inspection_escalation_feedback.sql
-- ============================================================

-- Pre-trip inspection (GAP-14), Escalation matrix (GAP-15), Customer feedback (GAP-20)

-- ─── Pre-trip inspections ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pre_trip_inspections (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    bus_id          BIGINT UNSIGNED NOT NULL,
    driver_id       BIGINT UNSIGNED NULL,
    inspected_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    inspected_by    BIGINT UNSIGNED NULL,
    -- Checklist (boolean fields)
    fluids_ok       TINYINT(1)      NOT NULL DEFAULT 0,
    tires_ok        TINYINT(1)      NOT NULL DEFAULT 0,
    lights_ok       TINYINT(1)      NOT NULL DEFAULT 0,
    brakes_ok       TINYINT(1)      NOT NULL DEFAULT 0,
    extinguisher_ok TINYINT(1)      NOT NULL DEFAULT 0,
    first_aid_kit_ok TINYINT(1)     NOT NULL DEFAULT 0,
    triangle_vest_ok TINYINT(1)     NOT NULL DEFAULT 0,
    seat_belts_ok   TINYINT(1)      NOT NULL DEFAULT 0,
    cleanliness_ok  TINYINT(1)      NOT NULL DEFAULT 0,
    documents_ok    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Carte grise, assurance, contrôle technique',
    -- Décision
    overall_status  ENUM('pass','pass_with_remarks','fail') NOT NULL DEFAULT 'pass',
    remarks         TEXT            NULL,
    odometer_km     INT UNSIGNED    NULL,
    fuel_level_pct  INT UNSIGNED    NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_inspection_trip (trip_id),
    INDEX idx_inspection_bus (bus_id),
    CONSTRAINT fk_insp_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_insp_bus  FOREIGN KEY (bus_id)  REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Matrice d'escalade incidents ──────────────────────────────────
CREATE TABLE IF NOT EXISTS escalation_rules (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    incident_type   VARCHAR(40)     NULL COMMENT 'NULL = tous types',
    min_severity    ENUM('mineur','modere','grave','critique') NOT NULL DEFAULT 'grave',
    notify_emails   TEXT            NULL COMMENT 'Liste séparée par , ou \\n',
    notify_phones   TEXT            NULL,
    notify_role_slugs VARCHAR(255)  NULL COMMENT 'CSV de rôles à notifier',
    delay_minutes   INT             NOT NULL DEFAULT 0,
    label           VARCHAR(120)    NOT NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_esc_active (is_active, min_severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO escalation_rules (incident_type, min_severity, label, notify_role_slugs) VALUES
    (NULL,       'critique', 'Incidents critiques → admin + RAF + exploitation', 'admin,raf,exploitation'),
    ('accident', 'grave',    'Accidents graves → direction + HSE',               'admin,raf,exploitation'),
    ('vol',      'mineur',   'Vols → direction',                                  'admin,raf')
ON DUPLICATE KEY UPDATE incident_type = incident_type;

-- ─── Customer feedback ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS customer_feedback (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id     BIGINT UNSIGNED NULL,
    ticket_id       BIGINT UNSIGNED NULL,
    trip_id         BIGINT UNSIGNED NULL,
    nps_score       INT             NULL COMMENT 'Net Promoter Score 0-10',
    rating_overall  INT             NULL COMMENT '1-5',
    rating_punctuality INT          NULL,
    rating_comfort  INT             NULL,
    rating_driver   INT             NULL,
    rating_cleanliness INT          NULL,
    comment         TEXT            NULL,
    submitted_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    request_token   VARCHAR(64)     NULL COMMENT 'Lien unique pour soumettre l''avis',
    request_sent_at DATETIME        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fb_token (request_token),
    INDEX idx_fb_customer (customer_id),
    INDEX idx_fb_trip (trip_id),
    INDEX idx_fb_submitted (submitted_at),
    CONSTRAINT fk_fb_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_fb_ticket   FOREIGN KEY (ticket_id)   REFERENCES tickets(id)   ON DELETE SET NULL,
    CONSTRAINT fk_fb_trip     FOREIGN KEY (trip_id)     REFERENCES trips(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('inspection.create', 'inspection', 'create', 'Créer pré-vérification', 380),
    ('inspection.view', 'inspection', 'view', 'Voir pré-vérifications', 381),
    ('escalation.manage', 'escalation', 'manage', 'Gérer matrice d''escalade', 382),
    ('feedback.view',     'feedback',   'view',   'Voir avis clients', 383),
    ('feedback.manage',   'feedback',   'manage', 'Gérer demandes d''avis', 384)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('inspection.create','inspection.view','escalation.manage','feedback.view','feedback.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('inspection.required_for_departure', 'voyage', 'boolean', '0', 'Pré-vérification obligatoire au départ', 'Si activé, le statut "embarquement" requiert une pré-vérification PASS.', 390, 0),
('feedback.auto_request_after_hours', 'crm', 'integer', '24', 'Délai envoi demande avis (heures)', 'Heures après clôture du voyage pour envoyer la demande d''avis.', 391, 0),
('feedback.auto_request_enabled',     'crm', 'boolean', '1',  'Demandes d''avis automatiques',       '', 392, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_11_notification_templates.sql
-- ============================================================

-- Templates de notifications avec variables (GAP-19)

CREATE TABLE IF NOT EXISTS notification_templates (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key  VARCHAR(80)     NOT NULL,
    channel       ENUM('sms','email','push','whatsapp') NOT NULL DEFAULT 'sms',
    label         VARCHAR(120)    NOT NULL,
    subject       VARCHAR(200)    NULL COMMENT 'Pour email uniquement',
    body          TEXT            NOT NULL COMMENT 'Avec placeholders {{variable}}',
    variables     JSON            NULL COMMENT 'Variables disponibles (auto-doc)',
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    is_system     TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = template système, non supprimable',
    version       INT UNSIGNED    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tpl_key_channel (template_key, channel),
    INDEX idx_tpl_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique d'envoi
CREATE TABLE IF NOT EXISTS notification_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id   BIGINT UNSIGNED NULL,
    template_key  VARCHAR(80)     NOT NULL,
    channel       ENUM('sms','email','push','whatsapp') NOT NULL,
    recipient     VARCHAR(200)    NOT NULL,
    customer_id   BIGINT UNSIGNED NULL,
    subject       VARCHAR(200)    NULL,
    body          TEXT            NOT NULL,
    status        ENUM('queued','sent','failed','bounced') NOT NULL DEFAULT 'queued',
    error         TEXT            NULL,
    sent_at       DATETIME        NULL,
    related_table VARCHAR(50)     NULL,
    related_id    BIGINT UNSIGNED NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_log_recipient (recipient, created_at),
    INDEX idx_log_template (template_key, channel, created_at),
    INDEX idx_log_customer (customer_id, created_at),
    CONSTRAINT fk_log_template FOREIGN KEY (template_id) REFERENCES notification_templates(id) ON DELETE SET NULL,
    CONSTRAINT fk_log_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Templates par défaut (système)
INSERT INTO notification_templates (template_key, channel, label, subject, body, variables, is_system) VALUES
('ticket.sold', 'sms', 'Ticket émis (SMS)', NULL,
 'CITY BUS · Billet {{ticket_number}} émis pour {{passenger_name}} sur le voyage {{trip_code}} du {{trip_date}} départ {{departure_time}}. Siège {{seat_number}}. Bon voyage !',
 '["ticket_number","passenger_name","trip_code","trip_date","departure_time","seat_number","price","line_name"]', 1),
('ticket.sold', 'email', 'Ticket émis (Email)', '[CITY BUS] Confirmation de votre billet {{ticket_number}}',
 'Bonjour {{passenger_name}},\r\n\r\nVotre billet est confirmé :\r\n- Voyage : {{trip_code}}\r\n- Ligne : {{line_name}}\r\n- Date : {{trip_date}} - Départ {{departure_time}}\r\n- Siège : {{seat_number}}\r\n- Prix : {{price}} FCFA\r\n\r\nMerci pour votre confiance.\r\nL''équipe CITY BUS',
 '["ticket_number","passenger_name","trip_code","trip_date","departure_time","seat_number","price","line_name"]', 1),
('trip.reminder_24h', 'sms', 'Rappel J-1 voyage', NULL,
 'CITY BUS · Rappel : votre voyage {{trip_code}} part demain {{departure_time}} depuis {{origin}} vers {{destination}}. Siège {{seat_number}}. À demain !',
 '["trip_code","departure_time","origin","destination","seat_number","passenger_name"]', 1),
('trip.delayed', 'sms', 'Voyage retardé', NULL,
 'CITY BUS · Votre voyage {{trip_code}} est retardé. Nouveau départ prévu : {{new_departure}}. Désolés pour la gêne occasionnée.',
 '["trip_code","new_departure","old_departure","reason"]', 1),
('trip.cancelled', 'sms', 'Voyage annulé', NULL,
 'CITY BUS · Désolés, le voyage {{trip_code}} du {{trip_date}} est annulé. Avoir {{voucher_amount}} FCFA (code {{voucher_code}}) valable jusqu''au {{voucher_expiry}}.',
 '["trip_code","trip_date","voucher_amount","voucher_code","voucher_expiry","reason"]', 1),
('parcel.deposited', 'sms', 'Colis déposé', NULL,
 'CITY BUS · Colis {{parcel_number}} déposé à {{origin}} pour {{recipient_name}}. Suivi : {{parcel_number}}',
 '["parcel_number","origin","destination","recipient_name","sender_name"]', 1),
('parcel.arrived', 'sms', 'Colis arrivé', NULL,
 'CITY BUS · Votre colis {{parcel_number}} est arrivé à {{destination}}. Présentez-vous avec une pièce d''identité. Tel : {{agency_phone}}',
 '["parcel_number","destination","agency_phone","recipient_name"]', 1),
('reservation.created', 'sms', 'Réservation créée', NULL,
 'CITY BUS · Réservation {{pnr_code}} confirmée. Total {{total_amount}} FCFA. Validité jusqu''au {{hold_expires}}.',
 '["pnr_code","total_amount","hold_expires","contact_name"]', 1),
('feedback.request', 'sms', 'Demande d''avis', NULL,
 'CITY BUS · Comment s''est passé votre voyage {{trip_code}} ? Notez-nous : {{feedback_url}}',
 '["trip_code","feedback_url","passenger_name"]', 1)
ON DUPLICATE KEY UPDATE template_key = template_key;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('notifications.view', 'notifications', 'view', 'Voir templates notifications', 320),
    ('notifications.manage', 'notifications', 'manage', 'Gérer templates notifications', 321)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'notifications.%'
ON DUPLICATE KEY UPDATE role_id = role_id;


-- ============================================================
-- Migration: 2026_05_11_pnr_reservations.sql
-- ============================================================

-- PNR / Réservations distinctes des billets (GAP-01)
-- Permet de bloquer un siège avec une option de paiement différé.

CREATE TABLE IF NOT EXISTS reservations (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pnr_code        VARCHAR(8)      NOT NULL COMMENT '6-8 caractères type Amadeus (ex: AB12CD)',
    customer_id     BIGINT UNSIGNED NULL,
    contact_name    VARCHAR(120)    NOT NULL,
    contact_phone   VARCHAR(30)     NOT NULL,
    contact_email   VARCHAR(120)    NULL,
    channel         ENUM('counter','phone','website','partner','corporate','agent') NOT NULL DEFAULT 'counter',
    partner_id      BIGINT UNSIGNED NULL COMMENT 'sales_partners.id si vente partenaire',
    corporate_id    BIGINT UNSIGNED NULL COMMENT 'corporate_accounts.id si compte B2B',
    status          ENUM('hold','confirmed','paid','partially_paid','cancelled','expired','converted') NOT NULL DEFAULT 'hold',
    total_amount_fcfa     INT UNSIGNED NOT NULL DEFAULT 0,
    paid_amount_fcfa      INT UNSIGNED NOT NULL DEFAULT 0,
    discount_fcfa         INT UNSIGNED NOT NULL DEFAULT 0,
    promo_code            VARCHAR(40)  NULL,
    voucher_code          VARCHAR(40)  NULL,
    notes                 TEXT         NULL,
    hold_expires_at       DATETIME     NULL COMMENT 'Date d''expiration du hold',
    confirmed_at          DATETIME     NULL,
    cancelled_at          DATETIME     NULL,
    cancel_reason         VARCHAR(255) NULL,
    created_by            BIGINT UNSIGNED NULL,
    agency_id             BIGINT UNSIGNED NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pnr_code (pnr_code),
    INDEX idx_res_customer (customer_id),
    INDEX idx_res_status (status, hold_expires_at),
    INDEX idx_res_phone (contact_phone),
    CONSTRAINT fk_res_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_res_user     FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL,
    CONSTRAINT fk_res_agency   FOREIGN KEY (agency_id)   REFERENCES agencies(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lignes de réservation : un PNR contient N passagers / sièges
CREATE TABLE IF NOT EXISTS reservation_items (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id      BIGINT UNSIGNED NOT NULL,
    trip_id             BIGINT UNSIGNED NOT NULL,
    seat_number         VARCHAR(10)     NULL,
    passenger_name      VARCHAR(120)    NOT NULL,
    passenger_phone     VARCHAR(30)     NULL,
    passenger_category  VARCHAR(50)     NOT NULL DEFAULT 'adulte',
    travel_class        VARCHAR(50)     NOT NULL DEFAULT 'standard',
    boarding_stop_id    BIGINT UNSIGNED NULL,
    alighting_stop_id   BIGINT UNSIGNED NULL,
    price_fcfa          INT UNSIGNED    NOT NULL DEFAULT 0,
    converted_ticket_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_resi_reservation (reservation_id),
    INDEX idx_resi_trip (trip_id),
    CONSTRAINT fk_resi_res    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_resi_trip   FOREIGN KEY (trip_id)        REFERENCES trips(id)        ON DELETE RESTRICT,
    CONSTRAINT fk_resi_ticket FOREIGN KEY (converted_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_resi_board  FOREIGN KEY (boarding_stop_id)    REFERENCES stops(id)   ON DELETE SET NULL,
    CONSTRAINT fk_resi_alight FOREIGN KEY (alighting_stop_id)   REFERENCES stops(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('reservations.view', 'reservations', 'view', 'Voir les réservations', 300),
    ('reservations.create', 'reservations', 'create', 'Créer une réservation', 301),
    ('reservations.confirm', 'reservations', 'confirm', 'Confirmer une réservation', 302),
    ('reservations.cancel', 'reservations', 'cancel', 'Annuler une réservation', 303)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation','chef_agence','caissier','agent') AND p.slug LIKE 'reservations.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings PNR
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('reservation.hold_default_minutes', 'billetterie', 'integer', '60',  'Durée hold par défaut (min)', 'Temps avant expiration automatique d''une réservation non payée.', 310, 0),
('reservation.allow_partial_payment','billetterie', 'boolean', '1',   'Paiement partiel autorisé',  'Permet d''encaisser un acompte avec solde à destination.', 311, 0),
('reservation.pnr_format',           'billetterie', 'string',  '6char',  'Format code PNR',         '6char (lettres+chiffres) ou 8char.', 312, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_11_schedule_patterns.sql
-- ============================================================

-- Patterns d'horaires récurrents (GAP-11)
-- Évite la saisie manuelle des voyages : un pattern génère les voyages des N prochains jours.

CREATE TABLE IF NOT EXISTS schedule_patterns (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    label           VARCHAR(120)    NOT NULL,
    line_id         BIGINT UNSIGNED NOT NULL,
    bus_id          BIGINT UNSIGNED NULL COMMENT 'Bus par défaut, peut être null pour rotation',
    primary_driver_id BIGINT UNSIGNED NULL,
    days_of_week    VARCHAR(20)     NOT NULL DEFAULT '1,2,3,4,5,6,7' COMMENT 'CSV : 1=lundi..7=dimanche',
    departure_time  TIME            NOT NULL,
    arrival_time    TIME            NULL,
    base_price_fcfa INT UNSIGNED    NULL,
    valid_from      DATE            NOT NULL,
    valid_until     DATE            NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    auto_generate_days INT UNSIGNED NOT NULL DEFAULT 14 COMMENT 'Combien de jours générer à l''avance',
    last_generated_until DATE       NULL,
    notes           TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sp_line (line_id, is_active),
    INDEX idx_sp_active (is_active, valid_from, valid_until),
    CONSTRAINT fk_sp_line FOREIGN KEY (line_id) REFERENCES bus_lines(id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_bus  FOREIGN KEY (bus_id)  REFERENCES buses(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exceptions (jours fériés, journées spéciales)
CREATE TABLE IF NOT EXISTS schedule_pattern_exceptions (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pattern_id     BIGINT UNSIGNED NULL COMMENT 'NULL = applique à tous',
    exception_date DATE            NOT NULL,
    type           ENUM('skip','custom_time') NOT NULL DEFAULT 'skip',
    custom_departure_time TIME     NULL,
    notes          VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    INDEX idx_spe_pattern_date (pattern_id, exception_date),
    CONSTRAINT fk_spe_pattern FOREIGN KEY (pattern_id) REFERENCES schedule_patterns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien voyage → pattern (pour traçabilité)
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS schedule_pattern_id BIGINT UNSIGNED NULL,
    ADD INDEX idx_trips_pattern (schedule_pattern_id);

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('voyages.schedule.view', 'voyages', 'view', 'Voir patterns horaires', 330),
    ('voyages.schedule.manage', 'voyages', 'manage', 'Gérer patterns horaires', 331)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug LIKE 'voyages.schedule.%'
ON DUPLICATE KEY UPDATE role_id = role_id;


-- ============================================================
-- Migration: 2026_05_11_yield_corporate_partners.sql
-- ============================================================

-- Yield management, comptes corporate, partenaires (GAP-05, GAP-09, GAP-25)

-- ─── Règles de tarification dynamique (yield) ──────────────────────
CREATE TABLE IF NOT EXISTS pricing_rules (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key        VARCHAR(60)     NOT NULL,
    label           VARCHAR(120)    NOT NULL,
    line_id         BIGINT UNSIGNED NULL COMMENT 'NULL = toutes lignes',
    rule_type       ENUM('early_bird','last_minute','peak_day','off_peak','load_factor','date_range') NOT NULL,
    days_before_min INT             NULL,
    days_before_max INT             NULL,
    days_of_week    VARCHAR(20)     NULL COMMENT 'CSV 1-7',
    load_factor_min INT             NULL COMMENT '% min pour déclencher',
    date_from       DATE            NULL,
    date_until      DATE            NULL,
    adjustment_type ENUM('percent_discount','percent_surcharge','fixed_discount','fixed_surcharge') NOT NULL,
    adjustment_value DECIMAL(10,2)  NOT NULL,
    priority        INT             NOT NULL DEFAULT 100,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pr_key (rule_key),
    INDEX idx_pr_active (is_active, line_id),
    CONSTRAINT fk_pr_line FOREIGN KEY (line_id) REFERENCES bus_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pricing_rules (rule_key, label, rule_type, days_before_min, days_before_max, adjustment_type, adjustment_value, priority) VALUES
    ('early_bird_7d',  'Early bird J-7+',     'early_bird',  7,  NULL, 'percent_discount',  10, 50),
    ('last_minute_1d', 'Last minute J-0/J-1', 'last_minute', 0,  1,    'percent_surcharge', 15, 60),
    ('weekend_peak',   'Surcharge week-end',  'peak_day',    NULL,NULL,'percent_surcharge', 20, 70)
ON DUPLICATE KEY UPDATE rule_key = rule_key;
UPDATE pricing_rules SET days_of_week = '6,7' WHERE rule_key = 'weekend_peak';

-- ─── Comptes corporate B2B ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS corporate_accounts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name    VARCHAR(150)    NOT NULL,
    legal_id        VARCHAR(60)     NULL,
    contact_name    VARCHAR(120)    NOT NULL,
    contact_phone   VARCHAR(30)     NOT NULL,
    contact_email   VARCHAR(120)    NULL,
    address         VARCHAR(255)    NULL,
    discount_percent DECIMAL(5,2)   NOT NULL DEFAULT 0,
    credit_limit_fcfa BIGINT UNSIGNED NOT NULL DEFAULT 0,
    current_balance_fcfa BIGINT     NOT NULL DEFAULT 0 COMMENT 'Solde dû (positif)',
    payment_terms_days INT UNSIGNED NOT NULL DEFAULT 30,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    notes           TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_corp_active (is_active),
    INDEX idx_corp_phone (contact_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS corporate_invoices (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    corporate_id    BIGINT UNSIGNED NOT NULL,
    invoice_number  VARCHAR(40)     NOT NULL,
    period_from     DATE            NOT NULL,
    period_to       DATE            NOT NULL,
    total_ht_fcfa   INT UNSIGNED    NOT NULL DEFAULT 0,
    total_tax_fcfa  INT UNSIGNED    NOT NULL DEFAULT 0,
    total_ttc_fcfa  INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    due_date        DATE            NULL,
    paid_at         DATETIME        NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_corp_inv_number (invoice_number),
    INDEX idx_corp_inv_corp (corporate_id, status),
    CONSTRAINT fk_ci_corp FOREIGN KEY (corporate_id) REFERENCES corporate_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien tickets ↔ corporate
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS corporate_id BIGINT UNSIGNED NULL,
    ADD INDEX idx_tickets_corporate (corporate_id);

-- ─── Partenaires commerciaux (revendeurs) ─────────────────────────
CREATE TABLE IF NOT EXISTS sales_partners (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)    NOT NULL,
    code            VARCHAR(40)     NOT NULL,
    contact_name    VARCHAR(120)    NULL,
    contact_phone   VARCHAR(30)     NULL,
    contact_email   VARCHAR(120)    NULL,
    commission_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    payout_schedule ENUM('weekly','bi_weekly','monthly') NOT NULL DEFAULT 'monthly',
    bank_details    TEXT            NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_partner_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS partner_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS partner_commission_fcfa INT UNSIGNED NULL,
    ADD INDEX idx_tickets_partner (partner_id);

CREATE TABLE IF NOT EXISTS partner_payouts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_id      BIGINT UNSIGNED NOT NULL,
    period_from     DATE            NOT NULL,
    period_to       DATE            NOT NULL,
    tickets_count   INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_fcfa    INT UNSIGNED    NOT NULL DEFAULT 0,
    commission_fcfa INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('pending','paid','disputed') NOT NULL DEFAULT 'pending',
    paid_at         DATETIME        NULL,
    notes           TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pp_partner (partner_id, period_from),
    CONSTRAINT fk_pp_partner FOREIGN KEY (partner_id) REFERENCES sales_partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('pricing.rules.view', 'pricing', 'view', 'Voir règles tarifaires', 340),
    ('pricing.rules.manage', 'pricing', 'manage', 'Gérer règles tarifaires', 341),
    ('corporate.view', 'corporate', 'view', 'Voir comptes corporate', 342),
    ('corporate.manage', 'corporate', 'manage', 'Gérer comptes corporate', 343),
    ('partners.view', 'partners', 'view', 'Voir partenaires', 344),
    ('partners.manage', 'partners', 'manage', 'Gérer partenaires', 345)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug IN
    ('pricing.rules.view','pricing.rules.manage','corporate.view','corporate.manage','partners.view','partners.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('pricing.yield_enabled',  'commerce', 'boolean', '1',   'Yield management activé', 'Active la tarification dynamique selon les règles définies.', 350, 0),
('pricing.peak_surcharge_pct', 'commerce', 'integer', '20', 'Surcharge week-end (%)', '', 351, 0),
('pricing.early_bird_pct',     'commerce', 'integer', '10', 'Réduction anticipation (%)', '', 352, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_12_voyages_module_deepdive.sql
-- ============================================================

-- ============================================================
-- Module Voyages — Audit en profondeur (10 mai 2026)
-- Couvre les 30 problèmes critiques + 40 importants identifiés.
-- ============================================================

-- ─── 1. Enrichissement table trips ───────────────────────────────
ALTER TABLE trips
    ADD COLUMN trip_type ENUM('commercial','affretement','interne','formation','test') NOT NULL DEFAULT 'commercial' AFTER trip_code,
    ADD COLUMN priority ENUM('normale','vip','express','convoi') NOT NULL DEFAULT 'normale' AFTER trip_type,
    ADD COLUMN public_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER priority,
    ADD COLUMN parent_trip_id BIGINT UNSIGNED NULL COMMENT 'Voyage parent si voyage de remplacement',
    ADD COLUMN delay_minutes INT NULL COMMENT 'Retard en minutes au départ',
    ADD COLUMN delay_reason ENUM('mecanique','traffic','meteo','accident','controle','retard_chauffeur','autre') NULL,
    ADD COLUMN distance_actual_km DECIMAL(8,2) NULL,
    ADD COLUMN fuel_consumed_liters DECIMAL(8,2) NULL,
    ADD COLUMN toll_amount_fcfa INT UNSIGNED NULL DEFAULT 0,
    ADD COLUMN external_reference VARCHAR(60) NULL COMMENT 'Réf bon de commande / agréeur',
    ADD COLUMN manifest_pdf_path VARCHAR(255) NULL,
    ADD COLUMN manifest_locked_at DATETIME NULL COMMENT 'Si renseigné, ventes verrouillées',
    ADD COLUMN manifest_locked_by BIGINT UNSIGNED NULL,
    ADD COLUMN seat_map_override_json JSON NULL COMMENT 'Sièges désactivés ponctuellement',
    ADD COLUMN weather_temp_celsius INT NULL,
    ADD COLUMN replaced_bus_id BIGINT UNSIGNED NULL COMMENT 'Si bus changé en cours',
    ADD COLUMN replaced_driver_id BIGINT UNSIGNED NULL,
    ADD COLUMN agency_origin_id BIGINT UNSIGNED NULL COMMENT 'Agence de départ (scoping)',
    ADD COLUMN agency_destination_id BIGINT UNSIGNED NULL,
    ADD COLUMN closed_at DATETIME NULL COMMENT 'Date/heure de clôture',
    ADD COLUMN closed_by BIGINT UNSIGNED NULL,
    ADD COLUMN cancelled_at DATETIME NULL,
    ADD COLUMN cancelled_by BIGINT UNSIGNED NULL;

-- Élargir l'enum statut pour couvrir tous les cas réels
ALTER TABLE trips
    MODIFY COLUMN status ENUM('planifie','valide','embarquement','en_route','arrive','cloture','incident','retourne','litige','annule')
                     NOT NULL DEFAULT 'planifie';

-- Index pour performance
CREATE INDEX idx_trips_date_status     ON trips (trip_date, status);
CREATE INDEX idx_trips_agency_origin   ON trips (agency_origin_id, trip_date);
CREATE INDEX idx_trips_agency_dest     ON trips (agency_destination_id, trip_date);
CREATE INDEX idx_trips_parent          ON trips (parent_trip_id);
CREATE INDEX idx_trips_status          ON trips (status);
CREATE INDEX idx_trips_priority        ON trips (priority);

-- FK pour parent_trip
ALTER TABLE trips
    ADD CONSTRAINT fk_trips_parent FOREIGN KEY (parent_trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trips_replaced_bus FOREIGN KEY (replaced_bus_id) REFERENCES buses(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trips_replaced_driver FOREIGN KEY (replaced_driver_id) REFERENCES employees(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trips_agency_origin FOREIGN KEY (agency_origin_id) REFERENCES agencies(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trips_agency_dest FOREIGN KEY (agency_destination_id) REFERENCES agencies(id) ON DELETE SET NULL;

-- Backfill agency_origin/destination depuis bus_lines + cities + agencies
-- Si une agence "principale" existe dans la ville de départ/arrivée de la ligne
UPDATE trips tr
JOIN bus_lines l ON l.id = tr.line_id
JOIN agencies ao ON ao.city_id = l.departure_city_id AND ao.type = 'principale' AND ao.is_active = 1
SET tr.agency_origin_id = ao.id
WHERE tr.agency_origin_id IS NULL;

UPDATE trips tr
JOIN bus_lines l ON l.id = tr.line_id
JOIN agencies ad ON ad.city_id = l.arrival_city_id AND ad.type = 'principale' AND ad.is_active = 1
SET tr.agency_destination_id = ad.id
WHERE tr.agency_destination_id IS NULL;

-- ─── 2. trip_status_log : audit dédié au cycle de vie ──────────────
CREATE TABLE IF NOT EXISTS trip_status_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    from_status   VARCHAR(40) NULL,
    to_status     VARCHAR(40) NOT NULL,
    reason        VARCHAR(255) NULL,
    metadata      JSON NULL,
    changed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by    BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tsl_trip (trip_id, changed_at),
    INDEX idx_tsl_to_status (to_status, changed_at),
    CONSTRAINT fk_tsl_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tsl_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. trip_documents : bordereaux / fichiers attachés ────────────
CREATE TABLE IF NOT EXISTS trip_documents (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    doc_type      ENUM('autorisation','bordereau_route','photo','rapport_incident','manifeste','autre') NOT NULL DEFAULT 'autre',
    title         VARCHAR(150) NOT NULL,
    file_path     VARCHAR(255) NOT NULL,
    file_size     INT UNSIGNED NULL,
    mime_type     VARCHAR(80) NULL,
    notes         TEXT NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by   BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_td_trip (trip_id, uploaded_at),
    CONSTRAINT fk_td_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_td_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. trip_disputes : litiges / réclamations ─────────────────────
CREATE TABLE IF NOT EXISTS trip_disputes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    ticket_id       BIGINT UNSIGNED NULL,
    parcel_id       BIGINT UNSIGNED NULL,
    type            ENUM('retard','perte_bagage','blessure','remboursement','reclamation_qualite','autre') NOT NULL DEFAULT 'autre',
    status          ENUM('ouvert','en_cours','resolu','rejete','escalade') NOT NULL DEFAULT 'ouvert',
    title           VARCHAR(180) NOT NULL,
    description     TEXT NOT NULL,
    claim_amount_fcfa INT UNSIGNED NULL,
    resolution_notes  TEXT NULL,
    refund_amount_fcfa INT UNSIGNED NULL,
    voucher_code    VARCHAR(40) NULL,
    opened_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opened_by       BIGINT UNSIGNED NULL,
    closed_at       DATETIME NULL,
    closed_by       BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_disp_trip (trip_id),
    INDEX idx_disp_status (status, opened_at),
    INDEX idx_disp_customer (customer_id),
    CONSTRAINT fk_disp_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_disp_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_disp_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE SET NULL,
    CONSTRAINT fk_disp_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. trip_costs : détail des coûts par voyage ───────────────────
CREATE TABLE IF NOT EXISTS trip_costs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    cost_type     ENUM('peage','carburant','parking','prime_chauffeur','prime_convoyeur','reparation','amende','divers') NOT NULL,
    amount_fcfa   INT UNSIGNED NOT NULL,
    description   VARCHAR(255) NULL,
    receipt_path  VARCHAR(255) NULL,
    paid_at       DATETIME NULL,
    paid_by       BIGINT UNSIGNED NULL COMMENT 'employee_id ou user_id',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by    BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tc_trip (trip_id),
    INDEX idx_tc_type (cost_type, paid_at),
    CONSTRAINT fk_tc_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 6. trip_messages : journal des notifications envoyées ─────────
CREATE TABLE IF NOT EXISTS trip_messages (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    channel       ENUM('sms','email','push','whatsapp','call') NOT NULL DEFAULT 'sms',
    audience      ENUM('all_passengers','crew','specific','agency','admin') NOT NULL DEFAULT 'all_passengers',
    recipient     VARCHAR(150) NULL COMMENT 'Téléphone/email si specific',
    subject       VARCHAR(200) NULL,
    body          TEXT NOT NULL,
    recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
    success_count INT UNSIGNED NOT NULL DEFAULT 0,
    sent_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_by       BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tm_trip (trip_id, sent_at),
    CONSTRAINT fk_tm_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tm_user FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 7. Permissions enrichies ──────────────────────────────────────
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('voyages.delete',           'voyages', 'delete',           'Supprimer un voyage',            440),
    ('voyages.lock_manifest',    'voyages', 'lock_manifest',    'Verrouiller manifeste/ventes',   441),
    ('voyages.unlock_manifest',  'voyages', 'unlock_manifest',  'Déverrouiller manifeste',        442),
    ('voyages.replace_bus',      'voyages', 'replace_bus',      'Changer le bus en cours',        443),
    ('voyages.replace_driver',   'voyages', 'replace_driver',   'Changer le chauffeur en cours',  444),
    ('voyages.communicate',      'voyages', 'communicate',      'Communiquer aux passagers',      445),
    ('voyages.export',           'voyages', 'export',           'Exporter listings voyages',      446),
    ('voyages.view_pnl',         'voyages', 'view_pnl',         'Voir P&L voyages',               447),
    ('voyages.view_audit',       'voyages', 'view_audit',       'Voir audit complet voyages',     448),
    ('voyages.dispute.manage',   'voyages', 'dispute_manage',   'Gérer litiges voyages',          449),
    ('voyages.documents.upload', 'voyages', 'documents_upload', 'Uploader documents voyages',     450),
    ('voyages.costs.manage',     'voyages', 'costs_manage',     'Gérer coûts voyage',             451)
ON DUPLICATE KEY UPDATE slug = slug;

-- Donner les nouvelles permissions à admin/raf/exploitation
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('voyages.delete','voyages.lock_manifest','voyages.unlock_manifest',
     'voyages.replace_bus','voyages.replace_driver','voyages.communicate',
     'voyages.export','voyages.view_pnl','voyages.view_audit',
     'voyages.dispute.manage','voyages.documents.upload','voyages.costs.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ─── 8. Settings nouveaux ──────────────────────────────────────────
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('voyage.delay_tolerance_minutes',       'voyage', 'integer', '15',  'Tolérance retard (min)',              'Au-delà : voyage marqué retardé.', 100, 0),
('voyage.conflict_buffer_hours',         'voyage', 'integer', '2',   'Marge conflit horaire (h)',           'Si arrivée non renseignée, on bloque sur N heures après le départ.', 101, 0),
('voyage.lock_sales_after_departure_min','voyage', 'integer', '15',  'Verrouiller ventes après départ (min)','Empêche la vente de tickets après ce délai post-départ. 0 = jamais auto.', 102, 0),
('voyage.require_inspection_for_departure','voyage','boolean', '0',  'Pré-vérification obligatoire',        'Bloque la transition vers en_route si aucune pré-vérif n''est PASS.', 103, 0),
('voyage.auto_boarding_minutes',         'voyage', 'integer', '30',  'Auto-embarquement à H-X min',         'Cron passe planifie→embarquement N min avant départ. 0 = désactivé.', 104, 0),
('voyage.auto_close_minutes',            'voyage', 'integer', '120', 'Auto-clôture (min)',                  'Voyages "arrive" depuis N min sont auto-clôturés. 0 = désactivé.', 105, 0),
('voyage.detect_signal_lost_minutes',    'voyage', 'integer', '15',  'Signal GPS perdu (min)',              'Crée un incident automatique si aucune position GPS depuis N min.', 106, 0),
('voyage.allow_same_day_creation_only',  'voyage', 'boolean', '0',   'Création voyage J+0 uniquement',     'Si activé, empêche la création de voyages dans le passé.', 107, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_12b_voyages_extra_tables.sql
-- ============================================================

-- Complément migration voyages : tables annexes manquantes
-- (la migration principale s'est arrêtée après les ALTER TABLE)

CREATE TABLE IF NOT EXISTS trip_status_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    from_status   VARCHAR(40) NULL,
    to_status     VARCHAR(40) NOT NULL,
    reason        VARCHAR(255) NULL,
    metadata      JSON NULL,
    changed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by    BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tsl_trip (trip_id, changed_at),
    INDEX idx_tsl_to_status (to_status, changed_at),
    CONSTRAINT fk_tsl_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tsl_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trip_documents (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    doc_type      ENUM('autorisation','bordereau_route','photo','rapport_incident','manifeste','autre') NOT NULL DEFAULT 'autre',
    title         VARCHAR(150) NOT NULL,
    file_path     VARCHAR(255) NOT NULL,
    file_size     INT UNSIGNED NULL,
    mime_type     VARCHAR(80) NULL,
    notes         TEXT NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by   BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_td_trip (trip_id, uploaded_at),
    CONSTRAINT fk_td_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_td_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trip_disputes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    ticket_id       BIGINT UNSIGNED NULL,
    parcel_id       BIGINT UNSIGNED NULL,
    type            ENUM('retard','perte_bagage','blessure','remboursement','reclamation_qualite','autre') NOT NULL DEFAULT 'autre',
    status          ENUM('ouvert','en_cours','resolu','rejete','escalade') NOT NULL DEFAULT 'ouvert',
    title           VARCHAR(180) NOT NULL,
    description     TEXT NOT NULL,
    claim_amount_fcfa INT UNSIGNED NULL,
    resolution_notes  TEXT NULL,
    refund_amount_fcfa INT UNSIGNED NULL,
    voucher_code    VARCHAR(40) NULL,
    opened_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opened_by       BIGINT UNSIGNED NULL,
    closed_at       DATETIME NULL,
    closed_by       BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_disp_trip (trip_id),
    INDEX idx_disp_status (status, opened_at),
    INDEX idx_disp_customer (customer_id),
    CONSTRAINT fk_disp_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_disp_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_disp_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE SET NULL,
    CONSTRAINT fk_disp_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trip_costs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    cost_type     ENUM('peage','carburant','parking','prime_chauffeur','prime_convoyeur','reparation','amende','divers') NOT NULL,
    amount_fcfa   INT UNSIGNED NOT NULL,
    description   VARCHAR(255) NULL,
    receipt_path  VARCHAR(255) NULL,
    paid_at       DATETIME NULL,
    paid_by       BIGINT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by    BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tc_trip (trip_id),
    INDEX idx_tc_type (cost_type, paid_at),
    CONSTRAINT fk_tc_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trip_messages (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id       BIGINT UNSIGNED NOT NULL,
    channel       ENUM('sms','email','push','whatsapp','call') NOT NULL DEFAULT 'sms',
    audience      ENUM('all_passengers','crew','specific','agency','admin') NOT NULL DEFAULT 'all_passengers',
    recipient     VARCHAR(150) NULL,
    subject       VARCHAR(200) NULL,
    body          TEXT NOT NULL,
    recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
    success_count INT UNSIGNED NOT NULL DEFAULT 0,
    sent_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_by       BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_tm_trip (trip_id, sent_at),
    CONSTRAINT fk_tm_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tm_user FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions (idempotent)
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('voyages.delete',           'voyages', 'delete',           'Supprimer un voyage',            440),
    ('voyages.lock_manifest',    'voyages', 'lock_manifest',    'Verrouiller manifeste/ventes',   441),
    ('voyages.unlock_manifest',  'voyages', 'unlock_manifest',  'Déverrouiller manifeste',        442),
    ('voyages.replace_bus',      'voyages', 'replace_bus',      'Changer le bus en cours',        443),
    ('voyages.replace_driver',   'voyages', 'replace_driver',   'Changer le chauffeur en cours',  444),
    ('voyages.communicate',      'voyages', 'communicate',      'Communiquer aux passagers',      445),
    ('voyages.export',           'voyages', 'export',           'Exporter listings voyages',      446),
    ('voyages.view_pnl',         'voyages', 'view_pnl',         'Voir P&L voyages',               447),
    ('voyages.view_audit',       'voyages', 'view_audit',       'Voir audit complet voyages',     448),
    ('voyages.dispute.manage',   'voyages', 'dispute_manage',   'Gérer litiges voyages',          449),
    ('voyages.documents.upload', 'voyages', 'documents_upload', 'Uploader documents voyages',     450),
    ('voyages.costs.manage',     'voyages', 'costs_manage',     'Gérer coûts voyage',             451)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('voyages.delete','voyages.lock_manifest','voyages.unlock_manifest',
     'voyages.replace_bus','voyages.replace_driver','voyages.communicate',
     'voyages.export','voyages.view_pnl','voyages.view_audit',
     'voyages.dispute.manage','voyages.documents.upload','voyages.costs.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings (idempotent)
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('voyage.delay_tolerance_minutes',         'voyage', 'integer', '15',  'Tolérance retard (min)',               '', 100, 0),
('voyage.conflict_buffer_hours',           'voyage', 'integer', '2',   'Marge conflit horaire (h)',            '', 101, 0),
('voyage.lock_sales_after_departure_min',  'voyage', 'integer', '15',  'Verrouiller ventes après départ (min)','', 102, 0),
('voyage.require_inspection_for_departure','voyage', 'boolean', '0',   'Pré-vérification obligatoire',         '', 103, 0),
('voyage.auto_boarding_minutes',           'voyage', 'integer', '30',  'Auto-embarquement à H-X min',          '', 104, 0),
('voyage.detect_signal_lost_minutes',      'voyage', 'integer', '15',  'Signal GPS perdu (min)',               '', 106, 0),
('voyage.allow_same_day_creation_only',    'voyage', 'boolean', '0',   'Création voyage J+0 uniquement',       '', 107, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_13_voyages_v3_phase1.sql
-- ============================================================

-- ============================================================
-- Module Voyages v3 — Phase 1 Foundation Pro
-- Booking classes (Y/B/M/H/L) + Stop tracking + Briefing
-- Date : 13 mai 2026
-- ============================================================

-- ─── 1. CLASSES D'INVENTAIRE (booking classes) ──────────────
CREATE TABLE IF NOT EXISTS inventory_classes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            CHAR(2)         NOT NULL COMMENT 'Y, B, M, H, L, V…',
    label           VARCHAR(80)     NOT NULL,
    description     VARCHAR(255)    NULL,
    flexibility     ENUM('full','medium','restricted','non_refundable') NOT NULL DEFAULT 'medium',
    refund_policy_pct INT UNSIGNED  NOT NULL DEFAULT 50 COMMENT '% remboursable si annulation',
    change_fee_fcfa INT UNSIGNED    NOT NULL DEFAULT 0,
    no_show_fee_pct INT UNSIGNED    NOT NULL DEFAULT 100,
    priority_boarding INT UNSIGNED  NOT NULL DEFAULT 5 COMMENT '1=premier, 9=dernier',
    priority_standby  INT UNSIGNED  NOT NULL DEFAULT 5,
    color_hex       VARCHAR(7)      NOT NULL DEFAULT '#64748b',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order      INT             NOT NULL DEFAULT 100,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_inv_class_code (code),
    INDEX idx_inv_class_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO inventory_classes (code, label, description, flexibility, refund_policy_pct, change_fee_fcfa, priority_boarding, color_hex, sort_order) VALUES
    ('Y', 'Première',     'Tarif premium · 100% flexible',         'full',           100, 0,    1, '#7c3aed', 10),
    ('B', 'Affaires',     'Confort + flexibilité',                 'full',            90, 1000, 2, '#2563eb', 20),
    ('M', 'Standard',     'Tarif courant',                         'medium',          70, 2000, 4, '#10b981', 30),
    ('H', 'Économique',   'Tarif réduit · modifications limitées', 'restricted',      30, 5000, 6, '#f59e0b', 40),
    ('L', 'Promo',        'Tarif promotionnel · non remboursable', 'non_refundable',   0, 0,    8, '#ef4444', 50)
ON DUPLICATE KEY UPDATE code = code;

-- ─── 2. INVENTAIRE PAR VOYAGE ──────────────────────────────
CREATE TABLE IF NOT EXISTS trip_inventory (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    class_id        BIGINT UNSIGNED NOT NULL,
    class_code      CHAR(2)         NOT NULL COMMENT 'Dénormalisé pour rapidité',
    capacity        INT UNSIGNED    NOT NULL DEFAULT 0,
    sold_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    reserved_count  INT UNSIGNED    NOT NULL DEFAULT 0,
    blocked_count   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Bloqué (groupes, VIP)',
    waitlist_count  INT UNSIGNED    NOT NULL DEFAULT 0,
    price_fcfa      INT UNSIGNED    NOT NULL,
    base_price_fcfa INT UNSIGNED    NOT NULL COMMENT 'Prix avant ajustement yield',
    bid_price_fcfa  INT UNSIGNED    NULL COMMENT 'Prix marginal yield',
    overbooking_pct INT UNSIGNED    NOT NULL DEFAULT 0,
    last_price_change_at DATETIME   NULL,
    last_price_reason VARCHAR(120)  NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_trip_class (trip_id, class_id),
    INDEX idx_trip_inv_trip (trip_id),
    INDEX idx_trip_inv_class (class_code),
    CONSTRAINT fk_ti_trip  FOREIGN KEY (trip_id)  REFERENCES trips(id)             ON DELETE CASCADE,
    CONSTRAINT fk_ti_class FOREIGN KEY (class_id) REFERENCES inventory_classes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien tickets ↔ classe
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS inventory_class_code CHAR(2) NULL AFTER passenger_category,
    ADD INDEX IF NOT EXISTS idx_tickets_inv_class (inventory_class_code);

-- ─── 3. ARRÊTS DU VOYAGE (stop-by-stop) ─────────────────────
-- Snapshot des arrêts pour CE voyage (matérialisation depuis stops de la ligne)
-- Permet le tracking individuel sans modifier le référentiel
CREATE TABLE IF NOT EXISTS trip_stops (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    stop_id         BIGINT UNSIGNED NOT NULL COMMENT 'FK vers stops (référentiel)',
    sequence        INT UNSIGNED    NOT NULL COMMENT 'Ordre dans le voyage',
    -- Prévisionnel
    scheduled_arrival   DATETIME    NULL,
    scheduled_departure DATETIME    NULL,
    distance_from_origin_km DECIMAL(8,2) NULL,
    -- Réel
    actual_arrival      DATETIME    NULL,
    actual_departure    DATETIME    NULL,
    -- ETA dynamique (recalculée lors d'un retard)
    estimated_arrival   DATETIME    NULL,
    estimated_departure DATETIME    NULL,
    -- Mouvements PAX/Cargo
    pax_boarded         INT UNSIGNED NOT NULL DEFAULT 0,
    pax_alighted        INT UNSIGNED NOT NULL DEFAULT 0,
    parcels_loaded      INT UNSIGNED NOT NULL DEFAULT 0,
    parcels_unloaded    INT UNSIGNED NOT NULL DEFAULT 0,
    -- Métadonnées
    delay_inherited_min INT NOT NULL DEFAULT 0 COMMENT 'Retard hérité de l''arrêt précédent',
    delay_added_min     INT NOT NULL DEFAULT 0 COMMENT 'Retard généré ici',
    notes               TEXT NULL,
    is_skipped          TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Arrêt sauté (vide / fermé)',
    skip_reason         VARCHAR(120) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_trip_stop_seq (trip_id, sequence),
    UNIQUE KEY uk_trip_stop (trip_id, stop_id),
    INDEX idx_trip_stops_trip (trip_id, sequence),
    INDEX idx_trip_stops_stop (stop_id),
    CONSTRAINT fk_ts_trip FOREIGN KEY (trip_id) REFERENCES trips(id)  ON DELETE CASCADE,
    CONSTRAINT fk_ts_stop FOREIGN KEY (stop_id) REFERENCES stops(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. JOURNAL D'ÉVÉNEMENTS PROGRESSIFS ────────────────────
CREATE TABLE IF NOT EXISTS trip_progress_events (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id         BIGINT UNSIGNED NOT NULL,
    stop_id         BIGINT UNSIGNED NULL,
    event_type      ENUM(
        'departure_origin','arrived_at_stop','departed_stop','arrived_destination',
        'boarding_started','boarding_priority','boarding_general','boarding_closed',
        'pax_boarded','pax_alighted','no_show',
        'parcel_loaded','parcel_unloaded',
        'fueling_start','fueling_end',
        'rest_break_start','rest_break_end',
        'incident_reported','incident_cleared',
        'eta_recalculated',
        'manual_note'
    ) NOT NULL,
    occurred_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actor_id        BIGINT UNSIGNED NULL,
    actor_label     VARCHAR(120) NULL,
    location_name   VARCHAR(150) NULL,
    location_lat    DECIMAL(10,7) NULL,
    location_lng    DECIMAL(10,7) NULL,
    metadata_json   JSON NULL,
    notes           TEXT NULL,
    PRIMARY KEY (id),
    INDEX idx_tpe_trip_time (trip_id, occurred_at),
    INDEX idx_tpe_event (event_type, occurred_at),
    INDEX idx_tpe_stop (stop_id),
    CONSTRAINT fk_tpe_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tpe_user FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tpe_stop FOREIGN KEY (stop_id) REFERENCES stops(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. SUB-STATES OPÉRATIONNELS ────────────────────────────
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS sub_status VARCHAR(40) NULL COMMENT 'boarding_open, at_stop_X, mechanical…' AFTER status,
    ADD COLUMN IF NOT EXISTS on_block_at DATETIME NULL COMMENT 'Bus arrivé au quai' AFTER closed_at,
    ADD COLUMN IF NOT EXISTS off_block_at DATETIME NULL COMMENT 'Bus quitte le quai',
    ADD COLUMN IF NOT EXISTS block_minutes INT NULL COMMENT 'Durée totale bus immobilisé (off_block - on_block)',
    ADD COLUMN IF NOT EXISTS current_stop_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS next_stop_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS current_eta DATETIME NULL COMMENT 'ETA actualisée pour la destination finale',
    ADD COLUMN IF NOT EXISTS departure_terminal VARCHAR(40) NULL COMMENT 'Quai/terminal de départ',
    ADD COLUMN IF NOT EXISTS arrival_terminal VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS briefing_pdf_path VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS briefing_generated_at DATETIME NULL,
    ADD INDEX IF NOT EXISTS idx_trips_substatus (sub_status);

ALTER TABLE trips
    ADD CONSTRAINT fk_trip_current_stop FOREIGN KEY (current_stop_id) REFERENCES stops(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_trip_next_stop    FOREIGN KEY (next_stop_id)    REFERENCES stops(id) ON DELETE SET NULL;

-- ─── 6. PERMISSIONS ────────────────────────────────────────
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('voyages.inventory.view',   'voyages', 'view',          'Voir l''inventaire (classes)',  460),
    ('voyages.inventory.manage', 'voyages', 'manage',        'Gérer l''inventaire',           461),
    ('voyages.boarding.control', 'voyages', 'boarding',      'Contrôle d''embarquement',     462),
    ('voyages.tracking.update',  'voyages', 'tracking',      'Mettre à jour la progression', 463),
    ('voyages.briefing.view',    'voyages', 'briefing_view', 'Voir le briefing voyage',      464),
    ('voyages.briefing.print',   'voyages', 'briefing_print','Imprimer le briefing',         465),
    ('ops.occ.view',             'ops',     'occ_view',      'Voir Operations Control',     470),
    ('public.departures.view',   'public',  'departures',    'Voir tableau d''affichage',    471)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug IN
    ('voyages.inventory.view','voyages.inventory.manage','voyages.boarding.control',
     'voyages.tracking.update','voyages.briefing.view','voyages.briefing.print',
     'ops.occ.view','public.departures.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Caissiers / agents : embarquement + briefing view
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('caissier','agent','chef_agence') AND p.slug IN
    ('voyages.boarding.control','voyages.briefing.view')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ─── 7. SETTINGS ───────────────────────────────────────────
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('voyage.inventory.enabled',                'voyage', 'boolean', '1',  'Booking classes activées',           'Active la gestion multi-classes (Y/B/M/H/L) par voyage.', 200, 0),
('voyage.inventory.default_classes',        'voyage', 'string',  'Y,B,M,H,L', 'Classes par défaut',          'Codes de classes activées par défaut au create.',         201, 0),
('voyage.boarding.opens_minutes_before',    'voyage', 'integer', '60', 'Embarquement ouvre (min)',           'Combien de minutes avant le départ.',                      210, 0),
('voyage.boarding.priority_minutes_before', 'voyage', 'integer', '45', 'Embarquement prioritaire (min)',     'Embarquement classes Y et B uniquement.',                  211, 0),
('voyage.boarding.closes_minutes_before',   'voyage', 'integer', '5',  'Embarquement ferme (min)',           'Plus aucun embarquement après ce délai.',                  212, 0),
('voyage.tracking.eta_recalc_enabled',      'voyage', 'boolean', '1',  'Recalcul auto ETA',                   'Recalcule les ETA des arrêts suivants en cas de retard.',  220, 0),
('voyage.tracking.delay_notify_threshold',  'voyage', 'integer', '15', 'Seuil notif retard (min)',           'Au-delà : SMS auto aux passagers en aval.',                221, 0),
('voyage.briefing.auto_generate',           'voyage', 'boolean', '1',  'Briefing auto-généré',                'Génère le briefing PDF à H-2h du départ.',                 230, 0),
('voyage.briefing.required_for_departure',  'voyage', 'boolean', '0',  'Briefing requis pour départ',         'Bloque la transition vers en_route si pas de briefing imprimé.', 231, 0),
('public.departures.refresh_seconds',       'public', 'integer', '30', 'Refresh tableau départs (s)',         '',                                                          240, 0),
('public.departures.window_hours',          'public', 'integer', '6',  'Fenêtre tableau départs (h)',         'Affiche les voyages des prochaines N heures.',             241, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_14_voyages_v3_phase2_revenue_irop.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_05_15_voyages_v3_phase3_hos.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_05_16_v4_platform.sql
-- ============================================================

-- =============================================================
-- V4.K — Plateforme transverse : queue, cache, feature flags, logs
-- =============================================================

CREATE TABLE IF NOT EXISTS jobs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    queue           VARCHAR(40) NOT NULL DEFAULT 'default',
    job_class       VARCHAR(180) NOT NULL,
    payload         LONGTEXT NOT NULL,
    status          ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 5,
    available_at    DATETIME NOT NULL,
    started_at      TIMESTAMP NULL,
    finished_at     TIMESTAMP NULL,
    error           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jobs_queue (queue, status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs_failed (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job_class       VARCHAR(180),
    payload         LONGTEXT,
    error           TEXT,
    failed_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_failed_class (job_class, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_cache (
    cache_key       VARCHAR(180) NOT NULL PRIMARY KEY,
    value           LONGTEXT NOT NULL,
    expires_at      TIMESTAMP NULL,
    INDEX idx_cache_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feature_flags (
    flag_key        VARCHAR(60) NOT NULL PRIMARY KEY,
    enabled         TINYINT(1) NOT NULL DEFAULT 0,
    rollout_pct     TINYINT UNSIGNED NOT NULL DEFAULT 100,
    target_roles   JSON NULL,
    target_users    JSON NULL,
    description     TEXT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS structured_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    level           ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
    channel         VARCHAR(60) NOT NULL DEFAULT 'app',
    message         VARCHAR(255) NOT NULL,
    context         JSON NULL,
    actor_id        BIGINT UNSIGNED NULL,
    request_id      VARCHAR(40) NULL,
    occurred_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_level_time (level, occurred_at),
    INDEX idx_log_channel (channel, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('platform.jobs.view',    'platform', 'jobs_view',    'Voir la file de travaux', 900),
  ('platform.jobs.manage',  'platform', 'jobs_manage',  'Relancer / supprimer des travaux', 901),
  ('platform.flags.manage', 'platform', 'flags_manage', 'Gérer les feature flags', 902),
  ('platform.logs.view',    'platform', 'logs_view',    'Consulter les logs structurés', 903)
ON DUPLICATE KEY UPDATE slug = slug;


-- ============================================================
-- Migration: 2026_05_17_v4_pnr_segments_od.sql
-- ============================================================

-- =============================================================
-- V4.A — PNR multi-segments + O-D pricing
-- Étend reservations + reservation_items + ajoute od_fares + pnr_passengers
-- =============================================================

-- Étend reservations (PNR header)
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS total_segments TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER total_amount_fcfa,
    ADD COLUMN IF NOT EXISTS total_pax TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER total_segments,
    ADD COLUMN IF NOT EXISTS booking_class CHAR(1) NULL AFTER total_pax,
    ADD COLUMN IF NOT EXISTS issue_status ENUM('held','ticketed','partially_ticketed') NOT NULL DEFAULT 'held' AFTER status,
    ADD COLUMN IF NOT EXISTS source_locator VARCHAR(40) NULL COMMENT 'GDS external reference';

-- Étend reservation_items (segments)
ALTER TABLE reservation_items
    ADD COLUMN IF NOT EXISTS sequence TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER reservation_id,
    ADD COLUMN IF NOT EXISTS pnr_passenger_id BIGINT UNSIGNED NULL AFTER trip_id,
    ADD COLUMN IF NOT EXISTS booking_class CHAR(1) NOT NULL DEFAULT 'M' AFTER travel_class,
    ADD COLUMN IF NOT EXISTS fare_basis VARCHAR(20) NULL AFTER booking_class,
    ADD COLUMN IF NOT EXISTS od_fare_id BIGINT UNSIGNED NULL AFTER fare_basis,
    ADD COLUMN IF NOT EXISTS pax_type ENUM('ADT','CHD','INF','SNR','STU','MIL','VIP') NOT NULL DEFAULT 'ADT' AFTER booking_class,
    ADD COLUMN IF NOT EXISTS segment_status ENUM('booked','confirmed','flown','no_show','cancelled','irop','transferred') NOT NULL DEFAULT 'booked' AFTER price_fcfa,
    ADD INDEX IF NOT EXISTS idx_resi_seq (reservation_id, sequence);

-- Passagers (séparés des segments — 1 PNR peut avoir N pax × N segments)
CREATE TABLE IF NOT EXISTS pnr_passengers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reservation_id  BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    title           ENUM('M','Mme','Mlle','Dr','Pr') NOT NULL DEFAULT 'M',
    first_name      VARCHAR(60) NOT NULL,
    last_name       VARCHAR(60) NOT NULL,
    dob             DATE NULL,
    pax_type        ENUM('ADT','CHD','INF','SNR','STU','MIL','VIP') NOT NULL DEFAULT 'ADT',
    document_type   ENUM('cni','passport','permis','none') NOT NULL DEFAULT 'cni',
    document_number VARCHAR(40) NULL,
    nationality     CHAR(3) NULL COMMENT 'ISO 3166-1 alpha-3',
    phone           VARCHAR(30) NULL,
    email           VARCHAR(120) NULL,
    seat_preference ENUM('window','aisle','any') NULL,
    special_request TEXT NULL,
    sequence        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uk_pp_seq (reservation_id, sequence),
    INDEX idx_pp_customer (customer_id),
    CONSTRAINT fk_pp_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tarifs O-D (origin-destination par classe/fare_basis)
CREATE TABLE IF NOT EXISTS od_fares (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    line_id         BIGINT UNSIGNED NOT NULL,
    from_stop_id    BIGINT UNSIGNED NOT NULL,
    to_stop_id      BIGINT UNSIGNED NOT NULL,
    booking_class   CHAR(1) NOT NULL DEFAULT 'M',
    fare_basis      VARCHAR(20) NOT NULL DEFAULT 'STD',
    pax_type        ENUM('ADT','CHD','INF','SNR','STU','MIL','VIP') NOT NULL DEFAULT 'ADT',
    base_price_fcfa INT UNSIGNED NOT NULL,
    refundable      TINYINT(1) NOT NULL DEFAULT 1,
    changeable      TINYINT(1) NOT NULL DEFAULT 1,
    refund_fee_fcfa INT NOT NULL DEFAULT 0,
    change_fee_fcfa INT NOT NULL DEFAULT 0,
    valid_from      DATE NULL,
    valid_until     DATE NULL,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_od (line_id, from_stop_id, to_stop_id, booking_class, fare_basis, pax_type),
    INDEX idx_od_line (line_id, active),
    CONSTRAINT fk_odv4_line FOREIGN KEY (line_id) REFERENCES bus_lines(id) ON DELETE CASCADE,
    CONSTRAINT fk_odv4_from FOREIGN KEY (from_stop_id) REFERENCES stops(id) ON DELETE CASCADE,
    CONSTRAINT fk_odv4_to   FOREIGN KEY (to_stop_id)   REFERENCES stops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liens correspondances (segment N -> segment N+1 du même PNR)
CREATE TABLE IF NOT EXISTS pnr_connections (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reservation_id  BIGINT UNSIGNED NOT NULL,
    inbound_segment_id BIGINT UNSIGNED NOT NULL,
    outbound_segment_id BIGINT UNSIGNED NOT NULL,
    connection_minutes INT NOT NULL COMMENT 'temps de transit',
    is_protected    TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'si retard inbound, outbound est rebooké auto',
    UNIQUE KEY uk_conn (inbound_segment_id, outbound_segment_id),
    CONSTRAINT fk_conn_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_conn_in  FOREIGN KEY (inbound_segment_id)  REFERENCES reservation_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_conn_out FOREIGN KEY (outbound_segment_id) REFERENCES reservation_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('pnr.modify',          'pnr', 'modify',     'Modifier un PNR existant', 320),
  ('pnr.ticket',          'pnr', 'ticket',     'Émettre les billets d''un PNR', 321),
  ('pnr.refund',          'pnr', 'refund',     'Rembourser un PNR', 322),
  ('od_fares.view',       'pnr', 'fares_view', 'Voir les tarifs O-D', 330),
  ('od_fares.manage',     'pnr', 'fares_manage','Gérer les tarifs O-D', 331)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('pnr.min_connection_time_min', 'billetterie','integer','30', 'MCT - Minimum Connection Time (min)', 'Temps min entre 2 segments de même PNR', 340, 0),
  ('pnr.max_segments',            'billetterie','integer','4',  'Segments max par PNR', '', 341, 0),
  ('pnr.auto_protect_connections','billetterie','boolean','1',  'Auto-rebook correspondance ratée', '', 342, 0),
  ('odfares.fallback_to_line_price','billetterie','boolean','1','Fallback prix ligne si OD inconnu', '', 343, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_18_v4_crm_loyalty.sql
-- ============================================================

-- =============================================================
-- V4.B — CRM passager unifié + programme fidélité
-- =============================================================

ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS frequent_flyer_number VARCHAR(20) UNIQUE NULL AFTER email,
    ADD COLUMN IF NOT EXISTS tier ENUM('basic','silver','gold','platinum') NOT NULL DEFAULT 'basic' AFTER frequent_flyer_number,
    ADD COLUMN IF NOT EXISTS tier_qualifying_until DATE NULL AFTER tier,
    ADD COLUMN IF NOT EXISTS total_segments INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_trips,
    ADD COLUMN IF NOT EXISTS total_distance_km INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_segments,
    ADD COLUMN IF NOT EXISTS points_lifetime INT NOT NULL DEFAULT 0 AFTER total_spent,
    ADD COLUMN IF NOT EXISTS preferences JSON NULL AFTER preferred_contact,
    ADD COLUMN IF NOT EXISTS data_consent_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS marketing_opt_in TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS rfm_recency TINYINT NULL COMMENT '1-5 RFM scoring',
    ADD COLUMN IF NOT EXISTS rfm_frequency TINYINT NULL,
    ADD COLUMN IF NOT EXISTS rfm_monetary TINYINT NULL,
    ADD INDEX IF NOT EXISTS idx_customers_tier (tier);

CREATE TABLE IF NOT EXISTS customer_history_segments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id     BIGINT UNSIGNED NOT NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    segment_id      BIGINT UNSIGNED NULL COMMENT 'reservation_items.id',
    ticket_id       BIGINT UNSIGNED NULL,
    trip_id         BIGINT UNSIGNED NULL,
    flown_at        DATETIME NOT NULL,
    distance_km     INT UNSIGNED NOT NULL DEFAULT 0,
    revenue_fcfa    INT NOT NULL DEFAULT 0,
    booking_class   CHAR(1) NULL,
    points_earned   INT NOT NULL DEFAULT 0,
    INDEX idx_chs_customer (customer_id, flown_at DESC),
    INDEX idx_chs_trip (trip_id),
    CONSTRAINT fk_chs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- (loyalty_transactions existe déjà dans le schéma de base — pas de doublon)

CREATE TABLE IF NOT EXISTS customer_complaints (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id     BIGINT UNSIGNED NOT NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    trip_id         BIGINT UNSIGNED NULL,
    category        ENUM('delay','baggage','staff','comfort','price','safety','overbooking','other') NOT NULL,
    severity        ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status          ENUM('open','investigating','resolved','closed','escalated') NOT NULL DEFAULT 'open',
    description     TEXT NOT NULL,
    resolution      TEXT NULL,
    compensation_fcfa INT NOT NULL DEFAULT 0,
    voucher_code    VARCHAR(40) NULL,
    assigned_to     BIGINT UNSIGNED NULL,
    opened_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at     TIMESTAMP NULL,
    closed_at       TIMESTAMP NULL,
    INDEX idx_cc_customer (customer_id, opened_at DESC),
    INDEX idx_cc_status (status, severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loyalty_tiers (
    code            VARCHAR(20) PRIMARY KEY,
    label           VARCHAR(60) NOT NULL,
    min_points_year INT NOT NULL DEFAULT 0,
    min_segments_year INT NOT NULL DEFAULT 0,
    points_multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT '1.5 = +50% pts',
    priority_boarding TINYINT(1) NOT NULL DEFAULT 0,
    free_baggage_kg TINYINT NOT NULL DEFAULT 0,
    voucher_birthday_fcfa INT NOT NULL DEFAULT 0,
    color_hex       VARCHAR(7) NOT NULL DEFAULT '#94a3b8'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO loyalty_tiers (code, label, min_points_year, min_segments_year, points_multiplier, priority_boarding, free_baggage_kg, voucher_birthday_fcfa, color_hex) VALUES
  ('basic',    'Basic',    0,    0,  1.0, 0, 0,    0,    '#94a3b8'),
  ('silver',   'Silver',   500,  10, 1.25, 1, 5,   2000, '#cbd5e1'),
  ('gold',     'Gold',     1500, 25, 1.5,  1, 10,  5000, '#fbbf24'),
  ('platinum', 'Platinum', 3000, 50, 2.0,  1, 20,  15000,'#1e293b')
ON DUPLICATE KEY UPDATE code = code;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('crm.customers.view',     'crm', 'cust_view',    'Voir fiches clients', 400),
  ('crm.customers.edit',     'crm', 'cust_edit',    'Éditer fiches clients', 401),
  ('crm.customers.merge',    'crm', 'cust_merge',   'Fusionner doublons clients', 402),
  ('crm.loyalty.view',       'crm', 'loy_view',     'Voir fidélité', 410),
  ('crm.loyalty.adjust',     'crm', 'loy_adjust',   'Ajuster points fidélité', 411),
  ('crm.complaints.view',    'crm', 'comp_view',    'Voir réclamations', 420),
  ('crm.complaints.manage',  'crm', 'comp_manage',  'Gérer réclamations + compensation', 421)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('loyalty.points_per_fcfa',     'crm','decimal','0.01','Points par FCFA dépensé', '1 FCFA = 0.01 pt par défaut', 500, 0),
  ('loyalty.points_expiry_months','crm','integer','24',  'Expiration des points (mois)', '', 501, 0),
  ('loyalty.tier_recompute_hour', 'crm','integer','3',   'Heure recalc tier (cron)', '0-23', 502, 0),
  ('crm.dedupe_phone_e164',       'crm','boolean','1',   'Normaliser téléphones E.164', '', 510, 0),
  ('crm.country_code_default',    'crm','string','+242', 'Indicatif pays défaut',     'Pour normalisation', 511, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_19_v4_finance_compta.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_05_20_v4_payments_caisse.sql
-- ============================================================

-- =============================================================
-- V4.D — Paiements multi-providers + Caisse + rapprochement
-- =============================================================

CREATE TABLE IF NOT EXISTS payment_providers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(30) UNIQUE NOT NULL,
    label           VARCHAR(80) NOT NULL,
    type            ENUM('mobile_money','card','wallet','cash','voucher','bank_transfer') NOT NULL,
    api_endpoint    VARCHAR(255) NULL,
    api_key_encrypted TEXT NULL,
    api_secret_encrypted TEXT NULL,
    callback_url    VARCHAR(255) NULL,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    fee_pct         DECIMAL(5,3) NOT NULL DEFAULT 0,
    fee_fixed       INT NOT NULL DEFAULT 0,
    sandbox_mode    TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO payment_providers (code, label, type, active, fee_pct, fee_fixed, sandbox_mode) VALUES
  ('CASH',          'Espèces',          'cash',          1, 0,    0, 0),
  ('AIRTEL_MONEY',  'Airtel Money',     'mobile_money',  1, 1.5,  0, 1),
  ('MTN_MOMO',      'MTN Mobile Money', 'mobile_money',  1, 1.5,  0, 1),
  ('ORANGE_MONEY',  'Orange Money',     'mobile_money',  1, 1.5,  0, 1),
  ('CINETPAY',      'Cinetpay (multi)', 'card',          1, 2.5,  100, 1),
  ('PAWAPAY',       'Pawapay',          'mobile_money',  1, 1.8,  0, 1),
  ('VOUCHER',       'Bon d''achat',     'voucher',       1, 0,    0, 0),
  ('BANK_TRANSFER', 'Virement bancaire','bank_transfer', 1, 0,    500, 0)
ON DUPLICATE KEY UPDATE code = code;

-- Crée table payments centralisée (n'existait pas)
CREATE TABLE IF NOT EXISTS payments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id      BIGINT UNSIGNED NULL,
    ticket_id       BIGINT UNSIGNED NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    amount          INT NOT NULL,
    payment_method  VARCHAR(40) NOT NULL DEFAULT 'CASH',
    provider_id     INT UNSIGNED NULL,
    provider_transaction_id VARCHAR(120) NULL,
    provider_status ENUM('pending','authorized','confirmed','failed','refunded','expired') NULL,
    provider_fee    INT NOT NULL DEFAULT 0,
    provider_callback_received_at TIMESTAMP NULL,
    reconciled_at   TIMESTAMP NULL,
    reconciliation_batch_id BIGINT UNSIGNED NULL,
    paid_at         TIMESTAMP NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pay_invoice (invoice_id),
    INDEX idx_pay_pnr (pnr_id),
    INDEX idx_pay_reconcile (provider_id, reconciled_at),
    INDEX idx_pay_provider_tx (provider_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reconciliation batches
CREATE TABLE IF NOT EXISTS reconciliation_batches (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT UNSIGNED NOT NULL,
    period_start    DATE,
    period_end      DATE,
    statement_file  VARCHAR(255) NULL,
    expected_total  BIGINT NOT NULL DEFAULT 0,
    matched_total   BIGINT NOT NULL DEFAULT 0,
    unmatched_total BIGINT NOT NULL DEFAULT 0,
    matched_count   INT NOT NULL DEFAULT 0,
    unmatched_count INT NOT NULL DEFAULT 0,
    status          ENUM('pending','partial','complete','disputed') NOT NULL DEFAULT 'pending',
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at    TIMESTAMP NULL,
    INDEX idx_recon_provider (provider_id, period_end DESC),
    CONSTRAINT fk_recon_provider FOREIGN KEY (provider_id) REFERENCES payment_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reconciliation_unmatched (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id        BIGINT UNSIGNED NOT NULL,
    external_tx_id  VARCHAR(120),
    external_amount INT,
    external_date   DATETIME,
    raw_data        JSON,
    INDEX idx_recunm_batch (batch_id),
    CONSTRAINT fk_recunm_batch FOREIGN KEY (batch_id) REFERENCES reconciliation_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Caisses (sessions)
CREATE TABLE IF NOT EXISTS cash_drawers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cashier_id      BIGINT UNSIGNED NOT NULL,
    agency_id       BIGINT UNSIGNED NULL,
    drawer_code     VARCHAR(30) NULL COMMENT 'matricule physique',
    opened_at       TIMESTAMP NOT NULL,
    closed_at       TIMESTAMP NULL,
    opening_balance INT NOT NULL DEFAULT 0,
    declared_cash_close INT NULL,
    expected_cash_close INT NULL,
    variance        INT NULL,
    notes           TEXT NULL,
    INDEX idx_cd_cashier_open (cashier_id, opened_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_drawer_movements (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    drawer_id       BIGINT UNSIGNED NOT NULL,
    movement_type   ENUM('sale','refund','withdraw','deposit','correction','transfer') NOT NULL,
    payment_method  VARCHAR(30) NOT NULL,
    amount          INT NOT NULL,
    reference       VARCHAR(60) NULL,
    notes           VARCHAR(180) NULL,
    occurred_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cdm_drawer (drawer_id, occurred_at DESC),
    CONSTRAINT fk_cdm_drawer FOREIGN KEY (drawer_id) REFERENCES cash_drawers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('payments.providers.view',    'payments', 'prov_view',    'Voir providers de paiement', 660),
  ('payments.providers.manage',  'payments', 'prov_manage',  'Configurer providers', 661),
  ('payments.reconcile.view',    'payments', 'recon_view',   'Voir rapprochement', 662),
  ('payments.reconcile.manage',  'payments', 'recon_manage', 'Faire rapprochement', 663),
  ('caisse.drawers.open',        'caisse',   'open',         'Ouvrir caisse', 670),
  ('caisse.drawers.close',       'caisse',   'close',        'Fermer caisse', 671),
  ('caisse.drawers.view',        'caisse',   'view',         'Voir caisses + variance', 672)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('payments.default_provider',    'payments','string','CASH',  'Provider par défaut', '', 800, 0),
  ('payments.momo_callback_url',   'payments','string','/api/v1/payments/momo/callback', 'Callback URL MoMo', '', 801, 0),
  ('payments.timeout_seconds',     'payments','integer','120',  'Timeout transaction (s)', '', 802, 0),
  ('payments.auto_reconcile',      'payments','boolean','0',    'Rapprochement auto par tx_id', '', 803, 0),
  ('caisse.variance_threshold_fcfa','caisse','integer','5000',  'Seuil alerte variance (FCFA)', '', 810, 0),
  ('caisse.require_supervisor_close','caisse','boolean','0',    'Superviseur requis pour clôture', '', 811, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_21_v4_notifications_marketing.sql
-- ============================================================

-- =============================================================
-- V4.F — Notifications & marketing complets
-- =============================================================

-- notification_templates existe (template_key, body, is_active)
INSERT INTO notification_templates (template_key, channel, label, subject, body, is_active) VALUES
  ('BOOKING_CONFIRMED_SMS', 'sms', 'PNR confirmé SMS', NULL, 'CityBus: PNR {pnr} confirmé. Voyage {trip_code} le {date} à {time}.', 1),
  ('BOOKING_CONFIRMED_EMAIL', 'email', 'PNR confirmé email', 'Confirmation réservation {pnr}', 'Bonjour {name}, voyage {trip_code} le {date} à {time} confirmé. PNR : {pnr}.', 1),
  ('REMINDER_J1_SMS', 'sms', 'Rappel J-1', NULL, 'CityBus: votre voyage {trip_code} part demain {date} à {time}.', 1),
  ('DELAY_NOTIFY_SMS', 'sms', 'Retard', NULL, 'CityBus: Voyage {trip_code} retardé de {delay} min. Nouveau départ : {new_time}.', 1),
  ('IROP_REBOOK_SMS', 'sms', 'IROP rebooking', NULL, 'CityBus: Voyage {trip_code} annulé. Rebooké sur {new_trip}.', 1),
  ('FEEDBACK_REQUEST_SMS', 'sms', 'Feedback', NULL, 'CityBus: Comment s''est passé votre voyage {trip_code} ?', 1),
  ('LOYALTY_TIER_UP_SMS', 'sms', 'Tier upgrade', NULL, 'CityBus: Félicitations {name} ! Vous êtes désormais {tier}.', 1),
  ('PAYMENT_RECEIVED_SMS', 'sms', 'Paiement reçu', NULL, 'CityBus: Paiement de {amount} FCFA reçu pour PNR {pnr}.', 1)
ON DUPLICATE KEY UPDATE template_key = template_key;

CREATE TABLE IF NOT EXISTS notification_dispatches (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_code   VARCHAR(60) NULL,
    channel         VARCHAR(20) NOT NULL,
    recipient_phone VARCHAR(30) NULL,
    recipient_email VARCHAR(120) NULL,
    customer_id     BIGINT UNSIGNED NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    payload         JSON NULL,
    rendered_subject VARCHAR(180) NULL,
    rendered_body   TEXT NULL,
    status          ENUM('queued','sent','delivered','opened','clicked','failed','bounced') NOT NULL DEFAULT 'queued',
    sent_at         TIMESTAMP NULL,
    delivered_at    TIMESTAMP NULL,
    opened_at       TIMESTAMP NULL,
    clicked_at      TIMESTAMP NULL,
    error_msg       TEXT NULL,
    retry_count     TINYINT NOT NULL DEFAULT 0,
    provider        VARCHAR(40) NULL,
    provider_msg_id VARCHAR(120) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nd_status (status, sent_at),
    INDEX idx_nd_customer (customer_id),
    INDEX idx_nd_pnr (pnr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(180) NOT NULL,
    description     TEXT NULL,
    audience_query  TEXT NULL,
    template_code   VARCHAR(60),
    scheduled_at    DATETIME NULL,
    sent_count      INT NOT NULL DEFAULT 0,
    delivered_count INT NOT NULL DEFAULT 0,
    opened_count    INT NOT NULL DEFAULT 0,
    clicked_count   INT NOT NULL DEFAULT 0,
    revenue_attributed_fcfa BIGINT NOT NULL DEFAULT 0,
    status          ENUM('draft','scheduled','running','done','cancelled') NOT NULL DEFAULT 'draft',
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('notifications.templates.view',   'notifications', 'tpl_view',   'Voir templates', 700),
  ('notifications.templates.manage', 'notifications', 'tpl_manage', 'Gérer templates', 701),
  ('notifications.dispatches.view',  'notifications', 'disp_view',  'Voir envois', 702),
  ('marketing.campaigns.view',       'marketing',     'camp_view',  'Voir campagnes', 710),
  ('marketing.campaigns.manage',     'marketing',     'camp_manage','Lancer campagnes', 711)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('notif.sms_provider',     'notifications','string','africastalking','Provider SMS','africastalking/twilio/local', 900, 0),
  ('notif.sms_api_key',      'notifications','string','',              'Clé API SMS','', 901, 1),
  ('notif.email_provider',   'notifications','string','smtp',           'Provider email','smtp/brevo/mailgun', 902, 0),
  ('notif.smtp_host',        'notifications','string','smtp.gmail.com', 'SMTP host','', 903, 0),
  ('notif.smtp_port',        'notifications','integer','587',           'SMTP port','', 904, 0),
  ('notif.smtp_username',    'notifications','string','',              'SMTP user','', 905, 1),
  ('notif.smtp_password',    'notifications','string','',              'SMTP pass','', 906, 1),
  ('notif.from_name',        'notifications','string','City Bus',       'Nom expéditeur','', 907, 0),
  ('notif.from_email',       'notifications','string','noreply@citybus.cg','Email expéditeur','', 908, 0),
  ('notif.reminder_j1_hour', 'notifications','integer','18',             'Heure cron rappel J-1','0-23', 910, 0),
  ('notif.feedback_after_hours','notifications','integer','3',           'Demande feedback après (h)','', 911, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_22_v4_cargo_advanced.sql
-- ============================================================

-- =============================================================
-- V4.G — Cargo full-featured : routage multi-segments, events, POD, COD
-- =============================================================

ALTER TABLE parcels
    ADD COLUMN IF NOT EXISTS sender_customer_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS recipient_customer_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS package_priority ENUM('standard','express','overnight') NOT NULL DEFAULT 'standard',
    ADD COLUMN IF NOT EXISTS cod_amount_fcfa INT NOT NULL DEFAULT 0 COMMENT 'cash on delivery',
    ADD COLUMN IF NOT EXISTS cod_collected_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS cod_collected_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS pod_signature_data MEDIUMTEXT NULL COMMENT 'Base64 signature tactile',
    ADD COLUMN IF NOT EXISTS pod_photo_path VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS pod_recipient_name VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS pod_recipient_id_doc VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS routed_via_segments JSON NULL COMMENT 'Liste de trip_id si multi-segment';

CREATE TABLE IF NOT EXISTS parcel_events (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_id       BIGINT UNSIGNED NOT NULL,
    event_type      ENUM('registered','picked_up','loaded','in_transit','arrived','transferred','out_for_delivery','delivered','returned','lost','damaged','held','customs') NOT NULL,
    occurred_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location        VARCHAR(120) NULL,
    trip_id         BIGINT UNSIGNED NULL,
    actor_id        BIGINT UNSIGNED NULL,
    notes           TEXT NULL,
    proof_photo     VARCHAR(255) NULL,
    INDEX idx_pe_parcel (parcel_id, occurred_at),
    CONSTRAINT fk_pe_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parcel_routes (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_id           BIGINT UNSIGNED NOT NULL,
    sequence            TINYINT NOT NULL,
    trip_id             BIGINT UNSIGNED NOT NULL,
    boarding_stop_id    BIGINT UNSIGNED NULL,
    alighting_stop_id   BIGINT UNSIGNED NULL,
    status              ENUM('planned','loaded','in_transit','unloaded','transferred','delivered','skipped') NOT NULL DEFAULT 'planned',
    loaded_at           TIMESTAMP NULL,
    unloaded_at         TIMESTAMP NULL,
    UNIQUE KEY uk_pr_seq (parcel_id, sequence),
    CONSTRAINT fk_pr_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_trip   FOREIGN KEY (trip_id)   REFERENCES trips(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('cargo.scan',          'cargo', 'scan',     'Scanner colis (QR)', 750),
  ('cargo.deliver',       'cargo', 'deliver',  'Remettre colis (POD)', 751),
  ('cargo.cod.collect',   'cargo', 'cod',      'Collecter COD', 752),
  ('cargo.events.view',   'cargo', 'events',   'Voir événements', 753),
  ('cargo.public.tracking','cargo','pub_track','Tracking public colis', 754)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('cargo.public_tracking_enabled','cargo','boolean','1','Tracking public activé','', 1000, 0),
  ('cargo.qr_label_format',         'cargo','string','GS1-128','Format étiquette QR','', 1001, 0),
  ('cargo.cod_max_fcfa',            'cargo','integer','500000','Montant COD max','', 1002, 0),
  ('cargo.insurance_pct',           'cargo','decimal','1.5','Assurance % valeur déclarée','', 1003, 0),
  ('cargo.notify_sender',           'cargo','boolean','1','Notifier expéditeur événements','', 1004, 0),
  ('cargo.notify_recipient',        'cargo','boolean','1','Notifier destinataire événements','', 1005, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- ============================================================
-- Migration: 2026_05_23_v4_corporate_contracts.sql
-- ============================================================

-- =============================================================
-- V4.H — Corporate B2B contracts + commissions partenaires
-- =============================================================

CREATE TABLE IF NOT EXISTS corporate_contracts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    corporate_id    BIGINT UNSIGNED NOT NULL,
    contract_number VARCHAR(40) UNIQUE,
    valid_from      DATE NOT NULL,
    valid_until     DATE NULL,
    discount_pct    DECIMAL(5,2) NOT NULL DEFAULT 0,
    free_seats_per_year INT NOT NULL DEFAULT 0,
    quota_seats_per_month INT NULL,
    auto_renew      TINYINT(1) NOT NULL DEFAULT 0,
    pdf_path        VARCHAR(255) NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cc_corp (corporate_id, valid_until),
    CONSTRAINT fk_cc_corp FOREIGN KEY (corporate_id) REFERENCES corporate_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE corporate_accounts
    ADD COLUMN IF NOT EXISTS account_manager_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS billing_email VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS tax_id VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS preferred_payment_method VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS sla_terms TEXT NULL;

CREATE TABLE IF NOT EXISTS partner_commissions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id      BIGINT UNSIGNED NOT NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    ticket_id       BIGINT UNSIGNED NULL,
    sale_amount     INT NOT NULL,
    commission_amount INT NOT NULL,
    period_month    CHAR(7) NOT NULL,
    status          ENUM('pending','accrued','invoiced','paid','reversed') NOT NULL DEFAULT 'pending',
    payout_id       BIGINT UNSIGNED NULL,
    invoice_id      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pc_partner_period (partner_id, period_month),
    INDEX idx_pc_status (status),
    CONSTRAINT fk_pc_partner FOREIGN KEY (partner_id) REFERENCES sales_partners(id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_payout FOREIGN KEY (payout_id) REFERENCES partner_payouts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('corporate.contracts.view',   'corporate', 'contract_view',   'Voir contrats corporate', 360),
  ('corporate.contracts.manage', 'corporate', 'contract_manage', 'Gérer contrats corporate', 361),
  ('corporate.invoice.generate', 'corporate', 'invoice_gen',     'Générer factures corporate (mensuelle)', 362),
  ('partners.commissions.view',  'partners',  'comm_view',       'Voir commissions partenaires', 370),
  ('partners.commissions.payout','partners',  'comm_payout',     'Émettre paiements partenaires', 371)
ON DUPLICATE KEY UPDATE slug = slug;


-- ============================================================
-- Migration: 2026_05_24_v4_gps_realtime.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_05_25_fret_module.sql
-- ============================================================

-- ============================================================================
-- Module Fret unifié — gestion des bagages passagers et colis légers (fret)
-- ============================================================================
-- Ce module unifie la prise en charge du fret en deux catégories :
--   - baggage : bagage passager lié à un voyage et éventuellement un billet
--   - colis   : envoi autonome entre agences (expéditeur → destinataire)
--
-- La table fret_items s'appuie sur fret_categories (déjà existante) pour la
-- tarification par slug. Les prix sont snapshotés à la création de l'item
-- afin de garantir l'intégrité historique même si les tarifs évoluent.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fret_items (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracking_code           VARCHAR(12)     NOT NULL COMMENT 'Format FRT-XXXXXX (alphanumérique aléatoire)',
    item_type               ENUM('baggage','colis') NOT NULL,
    category_slug           VARCHAR(60)     NOT NULL COMMENT 'Référence vers fret_categories.slug',

    -- Liaison voyage / billet (obligatoire pour baggage)
    trip_id                 BIGINT UNSIGNED NULL,
    passenger_ticket_id     BIGINT UNSIGNED NULL COMMENT 'Billet passager associé (baggage)',

    -- Expéditeur
    sender_name             VARCHAR(120)    NOT NULL,
    sender_phone            VARCHAR(30)     NULL,

    -- Destinataire (colis)
    recipient_name          VARCHAR(120)    NULL,
    recipient_phone         VARCHAR(30)     NULL,

    -- Caractéristiques
    weight_kg               DECIMAL(8,2)    NOT NULL DEFAULT 0,
    pieces_count            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    description             TEXT            NULL,
    is_franchise            TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Bagage dans la franchise gratuite',

    -- Tarification (snapshot au moment de la création)
    price_per_kg            INT UNSIGNED    NOT NULL DEFAULT 0,
    min_price_fcfa          INT UNSIGNED    NOT NULL DEFAULT 0,
    total_price_fcfa        INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Total calculé',

    -- Agences
    origin_agency_id        BIGINT UNSIGNED NULL,
    destination_agency_id   BIGINT UNSIGNED NULL,

    -- Workflow
    status                  ENUM('enregistre','charge','en_transit','arrive','retire','annule') NOT NULL DEFAULT 'enregistre',

    -- Talon
    talon_printed           TINYINT(1)      NOT NULL DEFAULT 0,
    talon_printed_at        DATETIME        NULL,

    -- Contexte opérationnel
    agency_id               BIGINT UNSIGNED NULL,
    registered_by           BIGINT UNSIGNED NULL COMMENT 'Utilisateur ayant enregistré',
    cash_register_id        BIGINT UNSIGNED NULL,

    -- Annulation
    cancelled_at            DATETIME        NULL,
    cancelled_by            BIGINT UNSIGNED NULL,
    cancel_reason           VARCHAR(255)    NULL,

    -- Timestamps
    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP       NULL,

    -- Clé primaire
    PRIMARY KEY (id),

    -- Index
    UNIQUE KEY uk_fret_items_tracking_code (tracking_code),
    INDEX idx_fret_items_item_type (item_type),
    INDEX idx_fret_items_trip (trip_id),
    INDEX idx_fret_items_status (status),
    INDEX idx_fret_items_category_slug (category_slug),
    INDEX idx_fret_items_created_at (created_at),
    INDEX idx_fret_items_sender_phone (sender_phone),

    -- Clés étrangères
    CONSTRAINT fk_fret_items_trip              FOREIGN KEY (trip_id)              REFERENCES trips(id)    ON DELETE SET NULL,
    CONSTRAINT fk_fret_items_ticket            FOREIGN KEY (passenger_ticket_id)  REFERENCES tickets(id)  ON DELETE SET NULL,
    CONSTRAINT fk_fret_items_origin_agency     FOREIGN KEY (origin_agency_id)     REFERENCES agencies(id),
    CONSTRAINT fk_fret_items_dest_agency       FOREIGN KEY (destination_agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_fret_items_registered_by     FOREIGN KEY (registered_by)        REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Permissions fret
-- ============================================================================
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('fret.view',        'fret', 'view',        'Voir le fret',             120),
    ('fret.create',      'fret', 'create',      'Enregistrer un fret',      121),
    ('fret.edit',        'fret', 'edit',         'Modifier un fret',         122),
    ('fret.cancel',      'fret', 'cancel',      'Annuler un fret',          123),
    ('fret.print_talon', 'fret', 'print_talon', 'Imprimer le talon fret',   124)
ON DUPLICATE KEY UPDATE slug = slug;

-- Accorder les permissions fret aux rôles admin et raf
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'fret.%'
ON DUPLICATE KEY UPDATE role_id = role_id;


-- ============================================================
-- Migration: 2026_05_25_v4_kpi_forecast.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_05_26_major_evolution.sql
-- ============================================================

-- ═══════════════════════════════════════════════════════════════════════════
-- Migration majeure : Évolutions structurelles CityBus ERP
-- Date: 2026-05-26
-- ═══════════════════════════════════════════════════════════════════════════
-- Contenu :
--   1. Lignes urbaines / interurbaines + arrêts liés aux lignes
--   2. Voyages fret-only
--   3. États de paiement (billets + fret)
--   4. Module convoyeurs (table dédiée, séparée des chauffeurs)
--   5. Fret : origines/destinations par arrêt, multi-lignes colis
--   6. Pré-imprimés redesign (billets, talons bagage, talons colis)
--   7. Programme de fidélité simplifié (paramétrable)
--   8. Remboursements (billetterie + fret)
-- ═══════════════════════════════════════════════════════════════════════════

-- ──────────────────────────────────────────────────────────────────────────
-- 1. LIGNES : type urbain / interurbain + agences de départ/arrivée
-- ──────────────────────────────────────────────────────────────────────────

ALTER TABLE bus_lines
  ADD COLUMN IF NOT EXISTS line_type ENUM('interurbain','urbain') NOT NULL DEFAULT 'interurbain' AFTER name,
  ADD COLUMN IF NOT EXISTS city_id BIGINT UNSIGNED NULL AFTER line_type,
  ADD COLUMN IF NOT EXISTS departure_agency_id BIGINT UNSIGNED NULL AFTER arrival_city_id,
  ADD COLUMN IF NOT EXISTS arrival_agency_id BIGINT UNSIGNED NULL AFTER departure_agency_id;

-- Arrêts liés aux lignes (pour lignes urbaines : liste ordonnée)
-- La table stops existe déjà (line_id, agency_id, name, order_position, km_from_origin)
-- On ajoute quelques colonnes utiles
ALTER TABLE stops
  ADD COLUMN IF NOT EXISTS city_id BIGINT UNSIGNED NULL AFTER agency_id,
  ADD COLUMN IF NOT EXISTS is_terminal TINYINT(1) NOT NULL DEFAULT 0 AFTER km_from_origin,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER is_terminal,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude;

-- ──────────────────────────────────────────────────────────────────────────
-- 2. VOYAGES : support fret-only
-- ──────────────────────────────────────────────────────────────────────────

-- trip_type ENUM déjà inclut 'commercial','affretement','interne','formation','test'
-- On ajoute 'fret' pour les voyages spécial fret sans passagers
ALTER TABLE trips MODIFY COLUMN trip_type
  ENUM('commercial','fret','affretement','interne','formation','test') NOT NULL DEFAULT 'commercial';


-- ──────────────────────────────────────────────────────────────────────────
-- 3. ÉTATS DE PAIEMENT — BILLETS
-- ──────────────────────────────────────────────────────────────────────────

-- Ajout d'un état de paiement séparé du statut opérationnel
ALTER TABLE tickets
  ADD COLUMN IF NOT EXISTS payment_status ENUM('en_attente','paye','rembourse_partiel','rembourse') NOT NULL DEFAULT 'paye' AFTER status,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER payment_status,
  ADD COLUMN IF NOT EXISTS paid_amount_fcfa INT UNSIGNED NULL AFTER paid_at,
  ADD COLUMN IF NOT EXISTS refund_amount_fcfa INT UNSIGNED NOT NULL DEFAULT 0 AFTER paid_amount_fcfa,
  ADD COLUMN IF NOT EXISTS refund_reason VARCHAR(300) NULL AFTER refund_amount_fcfa,
  ADD COLUMN IF NOT EXISTS refunded_at DATETIME NULL AFTER refund_reason,
  ADD COLUMN IF NOT EXISTS refunded_by BIGINT UNSIGNED NULL AFTER refunded_at;

-- Pour les billets existants : marquer comme payés (rétro-compatibilité)
UPDATE tickets SET payment_status = 'paye', paid_at = sold_at, paid_amount_fcfa = price_fcfa
  WHERE payment_status = 'paye' AND paid_at IS NULL AND status != 'annule';

-- Billets annulés → marquer la cohérence
UPDATE tickets SET payment_status = 'rembourse', paid_at = sold_at, paid_amount_fcfa = price_fcfa
  WHERE status = 'annule' AND payment_status = 'paye' AND refund_amount_fcfa = 0;


-- ──────────────────────────────────────────────────────────────────────────
-- 4. ÉTATS DE PAIEMENT — FRET
-- ──────────────────────────────────────────────────────────────────────────

ALTER TABLE fret_items
  ADD COLUMN IF NOT EXISTS payment_status ENUM('en_attente','paye','rembourse_partiel','rembourse','non_applicable') NOT NULL DEFAULT 'en_attente' AFTER status,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER payment_status,
  ADD COLUMN IF NOT EXISTS paid_amount_fcfa INT UNSIGNED NULL AFTER paid_at,
  ADD COLUMN IF NOT EXISTS refund_amount_fcfa INT UNSIGNED NOT NULL DEFAULT 0 AFTER paid_amount_fcfa,
  ADD COLUMN IF NOT EXISTS refund_reason VARCHAR(300) NULL AFTER refund_amount_fcfa,
  ADD COLUMN IF NOT EXISTS refunded_at DATETIME NULL AFTER refund_reason,
  ADD COLUMN IF NOT EXISTS refunded_by BIGINT UNSIGNED NULL AFTER refunded_at;

-- Franchise → non_applicable (gratuit), autres existants → payés
UPDATE fret_items SET payment_status = 'non_applicable' WHERE is_franchise = 1;
UPDATE fret_items SET payment_status = 'paye', paid_at = created_at, paid_amount_fcfa = total_price_fcfa
  WHERE is_franchise = 0 AND payment_status = 'en_attente' AND status != 'annule';

-- Origines / destinations par arrêt (en plus des agences)
ALTER TABLE fret_items
  ADD COLUMN IF NOT EXISTS origin_stop_id BIGINT UNSIGNED NULL AFTER origin_agency_id,
  ADD COLUMN IF NOT EXISTS destination_stop_id BIGINT UNSIGNED NULL AFTER destination_agency_id;


-- ──────────────────────────────────────────────────────────────────────────
-- 5. MODULE CONVOYEURS (table dédiée)
-- ──────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS convoyeurs (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  matricule             VARCHAR(20)     NOT NULL UNIQUE,
  first_name            VARCHAR(60)     NOT NULL,
  last_name             VARCHAR(60)     NOT NULL,
  birth_date            DATE            NULL,
  gender                ENUM('M','F')   NULL,
  national_id           VARCHAR(40)     NULL,
  national_id_expiry    DATE            NULL,
  phone                 VARCHAR(20)     NOT NULL,
  phone_alt             VARCHAR(20)     NULL,
  email                 VARCHAR(120)    NULL,
  address               TEXT            NULL,
  city                  VARCHAR(60)     NULL,
  emergency_name        VARCHAR(100)    NULL,
  emergency_phone       VARCHAR(20)     NULL,
  emergency_relation    VARCHAR(50)     NULL,
  hire_date             DATE            NOT NULL,
  agency_id             BIGINT UNSIGNED NULL,
  status                ENUM('actif','conge','suspendu','en_formation','quitte') NOT NULL DEFAULT 'actif',
  salary_base           INT UNSIGNED    NULL DEFAULT 0,
  daily_bonus           INT UNSIGNED    NULL DEFAULT 0,
  bank_name             VARCHAR(80)     NULL,
  bank_account          VARCHAR(60)     NULL,
  mobile_money_number   VARCHAR(20)     NULL,
  rating_score          DECIMAL(3,1)    NOT NULL DEFAULT 5.0,
  total_trips           INT UNSIGNED    NOT NULL DEFAULT 0,
  warnings_count        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  notes                 TEXT            NULL,
  created_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at            TIMESTAMP       NULL,
  INDEX idx_convoyeurs_status (status),
  INDEX idx_convoyeurs_agency (agency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrer convoyeur_id de trips : il pointe actuellement vers drivers.id
-- On garde la colonne pour le moment, mais on ajoute une nouvelle FK
-- ALTER TABLE trips ADD COLUMN IF NOT EXISTS convoyeur_type ENUM('driver','convoyeur') NULL DEFAULT 'driver' AFTER convoyeur_id;
-- Pour l'instant, on réutilise convoyeur_id mais avec une logique applicative


-- ──────────────────────────────────────────────────────────────────────────
-- 6. PRÉ-IMPRIMÉS : redesign
-- ──────────────────────────────────────────────────────────────────────────

-- Pré-imprimés : ajout du type (billet, talon_bagage, talon_colis)
ALTER TABLE pre_printed_tickets
  ADD COLUMN IF NOT EXISTS preprint_type ENUM('billet','talon_bagage','talon_colis') NOT NULL DEFAULT 'billet' AFTER id;

-- Code court alphanumérique 6 chars (lisible, imprimé en gros sur le talon)
ALTER TABLE pre_printed_tickets
  ADD COLUMN IF NOT EXISTS short_code VARCHAR(10) NULL UNIQUE AFTER qr_code_hash;

-- Pour les talons bagage / colis : champs manuscrits (remplis à la main par le convoyeur)
ALTER TABLE pre_printed_tickets
  ADD COLUMN IF NOT EXISTS linked_ticket_code VARCHAR(20) NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS fret_category_slug VARCHAR(60) NULL AFTER linked_ticket_code,
  ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(6,2) NULL AFTER fret_category_slug,
  ADD COLUMN IF NOT EXISTS sender_name VARCHAR(120) NULL AFTER weight_kg,
  ADD COLUMN IF NOT EXISTS sender_phone VARCHAR(30) NULL AFTER sender_name,
  ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(120) NULL AFTER sender_phone,
  ADD COLUMN IF NOT EXISTS recipient_phone VARCHAR(30) NULL AFTER recipient_name,
  ADD COLUMN IF NOT EXISTS fret_item_id BIGINT UNSIGNED NULL AFTER recipient_phone;


-- ──────────────────────────────────────────────────────────────────────────
-- 7. PROGRAMME DE FIDÉLITÉ : paramètres simplifiés
-- ──────────────────────────────────────────────────────────────────────────

-- Table de paramétrage du programme de fidélité
CREATE TABLE IF NOT EXISTS loyalty_program_config (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  is_enabled             TINYINT(1)   NOT NULL DEFAULT 1,
  required_trips         INT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Nombre de voyages pour obtenir l''avantage',
  discount_percent       DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Réduction en % sur le billet',
  enrollment_message     VARCHAR(500) NULL COMMENT 'Message à afficher au convoyeur',
  period_months          INT UNSIGNED NOT NULL DEFAULT 12 COMMENT 'Période de validité en mois (0=illimité)',
  created_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO loyalty_program_config (id, required_trips, discount_percent, enrollment_message, period_months)
VALUES (1, 10, 10.00, 'Proposer au client de rejoindre le programme de fidélité City Bus pour bénéficier de réductions.', 12);

-- Ajout du code client unique + flag fidélité dans customers
ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS customer_code VARCHAR(10) NULL UNIQUE AFTER id,
  ADD COLUMN IF NOT EXISTS is_loyalty_member TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_code,
  ADD COLUMN IF NOT EXISTS loyalty_enrolled_at DATETIME NULL AFTER is_loyalty_member,
  ADD COLUMN IF NOT EXISTS loyalty_enrolled_by BIGINT UNSIGNED NULL AFTER loyalty_enrolled_at;

-- Générer les codes clients pour les clients existants qui n'en ont pas
-- (sera fait par script PHP pour avoir un format propre)


-- ──────────────────────────────────────────────────────────────────────────
-- 8. TABLE DES REMBOURSEMENTS (historique centralisé)
-- ──────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS refunds (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  refund_type         ENUM('ticket','fret','baggage') NOT NULL,
  reference_id        BIGINT UNSIGNED NOT NULL COMMENT 'ID du ticket, fret_item, ou baggage_ticket',
  original_amount_fcfa INT UNSIGNED   NOT NULL,
  refund_amount_fcfa  INT UNSIGNED    NOT NULL,
  refund_percent      DECIMAL(5,2)    NOT NULL DEFAULT 100.00,
  reason              VARCHAR(500)    NOT NULL,
  cash_register_id    BIGINT UNSIGNED NULL,
  agency_id           BIGINT UNSIGNED NULL,
  refunded_by         BIGINT UNSIGNED NOT NULL,
  approved_by         BIGINT UNSIGNED NULL,
  status              ENUM('en_attente','approuve','execute','rejete') NOT NULL DEFAULT 'execute',
  created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  executed_at         DATETIME        NULL,
  INDEX idx_refunds_type_ref (refund_type, reference_id),
  INDEX idx_refunds_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ──────────────────────────────────────────────────────────────────────────
-- INDEX complémentaires
-- ──────────────────────────────────────────────────────────────────────────

ALTER TABLE bus_lines ADD INDEX IF NOT EXISTS idx_lines_type (line_type);
ALTER TABLE tickets ADD INDEX IF NOT EXISTS idx_tickets_payment (payment_status);
ALTER TABLE fret_items ADD INDEX IF NOT EXISTS idx_fret_payment (payment_status);


-- ============================================================
-- Migration: 2026_05_26_treasury_tx_status_source.sql
-- ============================================================

-- ============================================================
-- Migration : statut de confirmation + source sur les transactions trésorerie
-- ============================================================

-- 1. Statut de confirmation sur chaque transaction
ALTER TABLE treasury_transactions
  ADD COLUMN status          ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending' AFTER created_by,
  ADD COLUMN confirmed_by    INT          NULL AFTER status,
  ADD COLUMN confirmed_at    DATETIME     NULL AFTER confirmed_by,
  ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER confirmed_at,
  ADD KEY idx_tx_status (status);

-- 2. Source sur les catégories (trésorerie manuelle vs vente vs colis vs autre)
ALTER TABLE treasury_categories
  ADD COLUMN source ENUM('tresorerie','vente','colis','autre') NOT NULL DEFAULT 'tresorerie' AFTER type;

-- 3. Marquer les catégories "vente" et "colis" si elles existent déjà
UPDATE treasury_categories SET source = 'vente'  WHERE code LIKE 'VENTE%'  OR label LIKE '%vente%'  OR label LIKE '%billet%';
UPDATE treasury_categories SET source = 'colis'  WHERE code LIKE 'COLIS%'  OR label LIKE '%colis%'  OR label LIKE '%cargo%';


-- ============================================================
-- Migration: 2026_05_26_v4_api_v2_webhooks.sql
-- ============================================================

-- =============================================================
-- V4.L — API v2 (idempotency, webhooks, GDS sync)
-- =============================================================

ALTER TABLE oauth_clients
    ADD COLUMN IF NOT EXISTS scopes JSON NULL,
    ADD COLUMN IF NOT EXISTS rate_limit_per_min INT NOT NULL DEFAULT 60,
    ADD COLUMN IF NOT EXISTS webhook_url VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS webhook_secret VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS webhook_events JSON NULL;

CREATE TABLE IF NOT EXISTS api_idempotency_keys (
    `key`           VARCHAR(80) PRIMARY KEY,
    client_id       BIGINT UNSIGNED NULL,
    request_hash    VARCHAR(64),
    response        LONGTEXT,
    response_status SMALLINT,
    expires_at      TIMESTAMP NOT NULL,
    INDEX idx_iok_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhooks_outgoing (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id       BIGINT UNSIGNED NULL,
    event_type      VARCHAR(60) NOT NULL,
    url             VARCHAR(255) NOT NULL,
    payload         LONGTEXT NOT NULL,
    status          ENUM('pending','sent','retrying','failed') NOT NULL DEFAULT 'pending',
    attempts        TINYINT NOT NULL DEFAULT 0,
    next_attempt_at TIMESTAMP NULL,
    response_status SMALLINT NULL,
    response_body   TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at    TIMESTAMP NULL,
    INDEX idx_wo_status (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS channel_inventory_sync (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel         VARCHAR(40) NOT NULL COMMENT 'distribusion, busbud, omio',
    trip_id         BIGINT UNSIGNED NOT NULL,
    sync_status     ENUM('synced','pending','error') NOT NULL DEFAULT 'pending',
    last_synced_at  TIMESTAMP NULL,
    external_ref    VARCHAR(120) NULL,
    last_error      TEXT NULL,
    UNIQUE KEY uk_cis (channel, trip_id),
    INDEX idx_cis_status (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sso_providers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(30) UNIQUE,
    label           VARCHAR(80),
    type            ENUM('oidc','saml','ldap'),
    config          JSON NOT NULL,
    active          TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('api.v2.access',         'api', 'v2_access',   'Accéder API v2', 980),
  ('api.clients.manage',    'api', 'clients',     'Gérer clients OAuth2', 981),
  ('api.webhooks.view',     'api', 'wh_view',     'Voir webhooks sortants', 982),
  ('gds.channels.manage',   'gds', 'manage',      'Gérer canaux GDS', 990),
  ('sso.providers.manage',  'sso', 'manage',      'Gérer providers SSO', 991)
ON DUPLICATE KEY UPDATE slug = slug;


-- ============================================================
-- Migration: 2026_05_26_vehicle_types.sql
-- ============================================================

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


-- ============================================================
-- Migration: 2026_05_27_fret_sales_fix.sql
-- ============================================================

-- ============================================================================
-- Correction : support des ventes fret dans la table sales
-- ============================================================================
-- La table sales n'avait que les types 'ticket' et 'baggage'.
-- Le paiement des colis fret échouait avec une FK constraint violation car
-- FretService::pay() insérait l'ID du fret_item dans ticket_id (FK → tickets).
-- Ce script ajoute 'fret' à l'enum sale_type et une colonne fret_item_id.
-- ============================================================================

-- 1. Étendre l'enum sale_type pour inclure 'fret'
ALTER TABLE sales
    MODIFY COLUMN sale_type ENUM('ticket','baggage','fret') NOT NULL DEFAULT 'ticket';

-- 2. Ajouter la colonne fret_item_id (nullable, FK vers fret_items)
ALTER TABLE sales
    ADD COLUMN IF NOT EXISTS fret_item_id BIGINT UNSIGNED NULL AFTER baggage_ticket_id;

-- 3. Index sur fret_item_id pour les recherches rapides
ALTER TABLE sales
    ADD INDEX IF NOT EXISTS idx_sales_fret_item (fret_item_id);

-- 4. Contrainte FK (permissive : SET NULL si le fret_item est supprimé)
ALTER TABLE sales
    ADD CONSTRAINT fk_sales_fret_item
    FOREIGN KEY (fret_item_id) REFERENCES fret_items(id)
    ON DELETE SET NULL ON UPDATE CASCADE;


-- ============================================================
-- Migration: 2026_05_27_treasury_driver_id.sql
-- ============================================================

-- ============================================================
-- Migration : driver_id sur les transactions trésorerie
-- Permet le rattachement bidirectionnel des dépenses aux chauffeurs
-- ============================================================
ALTER TABLE treasury_transactions
  ADD COLUMN driver_id BIGINT UNSIGNED NULL AFTER bus_id,
  ADD KEY idx_tx_driver (driver_id);


-- ============================================================
-- Migration: 2026_05_27_treasury_system_categories.sql
-- ============================================================

-- ============================================================
-- Migration : catégories système par défaut pour la trésorerie
-- Date : 2026-05-27
-- ============================================================

-- 1. Promouvoir les catégories existantes en système
UPDATE treasury_categories SET is_system = 1
WHERE code IN ('carburant', 'entretien', 'salaire_avance', 'versement_banque', 'retrait_banque', 'fournitures');

-- 2. Réorganiser les sort_order par groupement logique
--    Encaissements 1-19  |  Banque 10-19  |  Exploitation 20-29
--    Personnel 40-49     |  Fonctionnement 50-59  |  Catch-all 90+
UPDATE treasury_categories SET sort_order = 1  WHERE code = 'billetterie';
UPDATE treasury_categories SET sort_order = 2  WHERE code = 'fret';
UPDATE treasury_categories SET sort_order = 10 WHERE code = 'versement_banque';
UPDATE treasury_categories SET sort_order = 11 WHERE code = 'retrait_banque';
UPDATE treasury_categories SET sort_order = 20 WHERE code = 'carburant';
UPDATE treasury_categories SET sort_order = 21 WHERE code = 'entretien';
UPDATE treasury_categories SET sort_order = 40 WHERE code = 'salaire_avance';
UPDATE treasury_categories SET sort_order = 50 WHERE code = 'fournitures';
UPDATE treasury_categories SET sort_order = 60 WHERE code = 'remboursement';
UPDATE treasury_categories SET sort_order = 90 WHERE code = 'autre_recette';
UPDATE treasury_categories SET sort_order = 91 WHERE code = 'autre_depense';

-- 3. Nouvelles catégories système
INSERT IGNORE INTO treasury_categories
    (code, label, type, source, is_system, color, is_active, sort_order)
VALUES
-- ── Encaissements ─────────────────────────────────────────────────────
('quittance_chauffeur', 'Quittance chauffeur',          'encaissement', 'tresorerie', 1, 'green',  1, 4),
('location_bus',        'Location de bus',              'encaissement', 'tresorerie', 1, 'orange', 1, 5),
('subvention',          'Subventions / aides',          'encaissement', 'tresorerie', 1, 'green',  1, 6),
('penalite',            'Pénalités / retenues',         'encaissement', 'tresorerie', 1, 'red',    1, 7),
('consigne_bagages',    'Consigne bagages',             'encaissement', 'tresorerie', 1, 'amber',  1, 8),

-- ── Décaissements exploitation ────────────────────────────────────────
('lavage_bus',          'Lavage de bus',                'decaissement', 'tresorerie', 1, 'blue',   1, 22),
('parking',             'Frais de parking',             'decaissement', 'tresorerie', 1, 'slate',  1, 23),
('peage',               'Péages routiers',              'decaissement', 'tresorerie', 1, 'amber',  1, 24),
('pneumatique',         'Pneumatiques / pneus',         'decaissement', 'tresorerie', 1, 'slate',  1, 25),

-- ── Décaissements personnel ───────────────────────────────────────────
('salaire',             'Salaires',                     'decaissement', 'tresorerie', 1, 'pink',   1, 41),
('prime_journaliere',   'Prime journalière',            'decaissement', 'tresorerie', 1, 'blue',   1, 42),
('prime_autre',         'Autres primes',                'decaissement', 'tresorerie', 1, 'violet', 1, 43),
('commission_agent',    'Commissions agents',           'decaissement', 'tresorerie', 1, 'violet', 1, 44),
('indemnite',           'Indemnités de déplacement',    'decaissement', 'tresorerie', 1, 'blue',   1, 45),

-- ── Décaissements admin / fonctionnement ──────────────────────────────
('quittance',           'Quittances / redevances',      'decaissement', 'tresorerie', 1, 'slate',  1, 51),
('loyer',               'Loyer locaux / gare',          'decaissement', 'tresorerie', 1, 'slate',  1, 52),
('assurance',           'Assurances véhicules',         'decaissement', 'tresorerie', 1, 'green',  1, 53),
('amende',              'Amendes / contraventions',     'decaissement', 'tresorerie', 1, 'red',    1, 54),
('impot_taxe',          'Impôts et taxes',              'decaissement', 'tresorerie', 1, 'orange', 1, 55),
('telecoms',            'Télécoms / internet',          'decaissement', 'tresorerie', 1, 'blue',   1, 56),
('electricite_eau',     'Électricité / eau',            'decaissement', 'tresorerie', 1, 'amber',  1, 57),
('visite_technique',    'Visite technique',             'decaissement', 'tresorerie', 1, 'green',  1, 58);


-- ============================================================
-- Migration: 2026_05_28_urban_ticket_types.sql
-- ============================================================

-- ============================================================================
-- Types de billets pour le transport urbain
-- ============================================================================
-- Les lignes urbaines (bus_lines.line_type = 'urbain') n'utilisent pas les
-- mêmes types de billets que les lignes interurbaines. Ce script insère les
-- types spécifiques au réseau urbain.
-- ============================================================================

INSERT INTO tariff_ticket_types (slug, label, icon, color_class, description, is_active, sort_order) VALUES
    ('course_simple',         'Course (trajet unique)',   'bus',           'indigo', 'Trajet unique sur réseau urbain',        1, 10),
    ('carnet_10',             'Carnet 10 voyages',        'layers',        'teal',   'Prépayé 10 trajets urbains',             1, 11),
    ('abonnement_journalier', 'Abonnement journalier',    'sun',           'amber',  'Pass valable 1 journée complète',        1, 12),
    ('abonnement_hebdo',      'Abonnement hebdomadaire',  'calendar-days', 'orange', 'Pass valable 7 jours consécutifs',       1, 13)
ON DUPLICATE KEY UPDATE label = VALUES(label), sort_order = VALUES(sort_order);


-- ============================================================
-- Migration: 2026_06_14_urban_tickets.sql
-- ============================================================

-- ============================================================
-- Migration : Module tickets pré-imprimés urbains
-- Date : 2026-06-14
-- ============================================================

-- Bibliothèque de symboles anti-fraude
CREATE TABLE IF NOT EXISTS urban_ticket_symbols (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol      VARCHAR(10)   NOT NULL,
    label       VARCHAR(50)   NOT NULL,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order  SMALLINT      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_symbol (symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Symboles par défaut (≥16)
INSERT IGNORE INTO urban_ticket_symbols (symbol, label, sort_order) VALUES
('★', 'Étoile pleine',      1),
('☆', 'Étoile vide',        2),
('▲', 'Triangle haut',      3),
('▼', 'Triangle bas',       4),
('◆', 'Losange plein',      5),
('◇', 'Losange vide',       6),
('●', 'Cercle plein',       7),
('○', 'Cercle vide',        8),
('■', 'Carré plein',        9),
('□', 'Carré vide',        10),
('▶', 'Flèche droite',     11),
('◀', 'Flèche gauche',     12),
('♦', 'Diamant',           13),
('♠', 'Pique',             14),
('♣', 'Trèfle',            15),
('♥', 'Cœur',              16),
('✦', 'Étoile 4 branches', 17),
('⬟', 'Pentagone',         18),
('⬡', 'Hexagone',          19),
('✚', 'Croix épaisse',     20);

-- Séries de tickets urbains
CREATE TABLE IF NOT EXISTS urban_ticket_series (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    series_code     VARCHAR(30)     NOT NULL,
    ticket_date     DATE            NOT NULL,
    date_code       VARCHAR(6)      NOT NULL COMMENT 'AAMMJJ',
    symbol_id       BIGINT UNSIGNED NOT NULL,
    symbol_char     VARCHAR(10)     NOT NULL,
    price_fcfa      INT UNSIGNED    NOT NULL DEFAULT 150,
    bus_code        VARCHAR(20)     NOT NULL,
    departure       VARCHAR(100)    NOT NULL,
    arrival         VARCHAR(100)    NOT NULL,
    network_label   VARCHAR(100)    NOT NULL DEFAULT 'Réseau urbain · Brazzaville',
    num_start       INT UNSIGNED    NOT NULL,
    num_end         INT UNSIGNED    NOT NULL,
    ticket_count    INT UNSIGNED    NOT NULL,
    page_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('planifiee','en_cours','cloturee','annulee') NOT NULL DEFAULT 'planifiee',
    tickets_sold    INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_expected INT UNSIGNED   NOT NULL DEFAULT 0,
    revenue_actual  INT UNSIGNED    DEFAULT NULL,
    notes           TEXT            DEFAULT NULL,
    pdf_path        VARCHAR(255)    DEFAULT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    closed_by       BIGINT UNSIGNED DEFAULT NULL,
    closed_at       TIMESTAMP       NULL DEFAULT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_series_code (series_code),
    KEY idx_ticket_date (ticket_date),
    KEY idx_status (status),
    KEY idx_bus_code (bus_code),
    CONSTRAINT fk_uts_symbol FOREIGN KEY (symbol_id) REFERENCES urban_ticket_symbols(id),
    CONSTRAINT fk_uts_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


