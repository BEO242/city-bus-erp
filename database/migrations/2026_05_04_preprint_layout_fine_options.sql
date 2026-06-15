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