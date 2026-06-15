-- Migration : 2026-05-06
-- Ajoute des contraintes manquantes sur trips et étend la table sales aux bagages.

-- 1. FK trips.driver_id → drivers.id (nullable, ON DELETE SET NULL)
ALTER TABLE trips
    ADD CONSTRAINT fk_trips_driver
    FOREIGN KEY (driver_id) REFERENCES drivers(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- 2. FK trips.convoyeur_id → drivers.id (nullable)
ALTER TABLE trips
    ADD CONSTRAINT fk_trips_convoyeur
    FOREIGN KEY (convoyeur_id) REFERENCES drivers(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 3. Étendre sales pour gérer les ventes de bagages
--    Mise en place d'un schéma polymorphique : sale_type + reference_id (tickets ou baggage_tickets)
ALTER TABLE sales
    ADD COLUMN sale_type ENUM('ticket','baggage') NOT NULL DEFAULT 'ticket' AFTER cash_register_id,
    ADD COLUMN baggage_ticket_id BIGINT UNSIGNED NULL AFTER ticket_id;

-- ticket_id devient nullable pour permettre lignes baggage
ALTER TABLE sales MODIFY ticket_id BIGINT UNSIGNED NULL;

ALTER TABLE sales
    ADD INDEX idx_sales_baggage (baggage_ticket_id),
    ADD INDEX idx_sales_type (sale_type);

ALTER TABLE sales
    ADD CONSTRAINT fk_sales_baggage_ticket
    FOREIGN KEY (baggage_ticket_id) REFERENCES baggage_tickets(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Marquer les ventes existantes comme 'ticket' (déjà la valeur par défaut)
UPDATE sales SET sale_type = 'ticket' WHERE ticket_id IS NOT NULL;
