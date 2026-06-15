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
