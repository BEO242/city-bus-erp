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
