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
