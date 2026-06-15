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
