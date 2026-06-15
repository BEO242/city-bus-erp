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
