-- ============================================================
-- Migration : statut de confirmation + source sur les transactions trésorerie
-- ============================================================

-- 1. Statut de confirmation sur chaque transaction
ALTER TABLE treasury_transactions
  ADD COLUMN status          ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending' AFTER created_by,
  ADD COLUMN confirmed_by    INT          NULL AFTER status,
  ADD COLUMN confirmed_at    DATETIME     NULL AFTER confirmed_by,
  ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER confirmed_at,
  ADD KEY idx_tx_status (status);

-- 2. Source sur les catégories (trésorerie manuelle vs vente vs colis vs autre)
ALTER TABLE treasury_categories
  ADD COLUMN source ENUM('tresorerie','vente','colis','autre') NOT NULL DEFAULT 'tresorerie' AFTER type;

-- 3. Marquer les catégories "vente" et "colis" si elles existent déjà
UPDATE treasury_categories SET source = 'vente'  WHERE code LIKE 'VENTE%'  OR label LIKE '%vente%'  OR label LIKE '%billet%';
UPDATE treasury_categories SET source = 'colis'  WHERE code LIKE 'COLIS%'  OR label LIKE '%colis%'  OR label LIKE '%cargo%';
