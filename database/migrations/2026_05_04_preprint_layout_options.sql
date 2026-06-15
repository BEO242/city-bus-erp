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