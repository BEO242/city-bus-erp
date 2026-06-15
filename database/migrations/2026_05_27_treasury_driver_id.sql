-- ============================================================
-- Migration : driver_id sur les transactions trésorerie
-- Permet le rattachement bidirectionnel des dépenses aux chauffeurs
-- ============================================================
ALTER TABLE treasury_transactions
  ADD COLUMN driver_id BIGINT UNSIGNED NULL AFTER bus_id,
  ADD KEY idx_tx_driver (driver_id);
