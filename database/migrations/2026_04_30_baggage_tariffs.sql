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
