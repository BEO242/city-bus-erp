-- Migration: 2026_05_01 — Ajout max_volume_cm3 dans baggage_tariffs
ALTER TABLE baggage_tariffs
  ADD COLUMN max_volume_cm3 INT UNSIGNED NULL COMMENT 'Volume max du colis (cm³) au-delà duquel la surcharge volumineux s''applique' AFTER max_girth_cm;
