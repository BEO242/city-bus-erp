-- ============================================================================
-- Correction : support des ventes fret dans la table sales
-- ============================================================================
-- La table sales n'avait que les types 'ticket' et 'baggage'.
-- Le paiement des colis fret échouait avec une FK constraint violation car
-- FretService::pay() insérait l'ID du fret_item dans ticket_id (FK → tickets).
-- Ce script ajoute 'fret' à l'enum sale_type et une colonne fret_item_id.
-- ============================================================================

-- 1. Étendre l'enum sale_type pour inclure 'fret'
ALTER TABLE sales
    MODIFY COLUMN sale_type ENUM('ticket','baggage','fret') NOT NULL DEFAULT 'ticket';

-- 2. Ajouter la colonne fret_item_id (nullable, FK vers fret_items)
ALTER TABLE sales
    ADD COLUMN IF NOT EXISTS fret_item_id BIGINT UNSIGNED NULL AFTER baggage_ticket_id;

-- 3. Index sur fret_item_id pour les recherches rapides
ALTER TABLE sales
    ADD INDEX IF NOT EXISTS idx_sales_fret_item (fret_item_id);

-- 4. Contrainte FK (permissive : SET NULL si le fret_item est supprimé)
ALTER TABLE sales
    ADD CONSTRAINT fk_sales_fret_item
    FOREIGN KEY (fret_item_id) REFERENCES fret_items(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
