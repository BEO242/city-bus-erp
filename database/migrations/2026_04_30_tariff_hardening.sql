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
