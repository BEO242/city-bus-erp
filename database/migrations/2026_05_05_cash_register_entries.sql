-- ============================================================================
-- 2026_05_05 : Table cash_register_entries pour mouvements caisse divers
--   (remboursements, ajustements). Référencée par BaggageTicketService et
--   désormais aussi par TicketService::cancel().
-- ============================================================================

CREATE TABLE IF NOT EXISTS cash_register_entries (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cash_register_id  BIGINT UNSIGNED NOT NULL,
    entry_type        VARCHAR(40) NOT NULL,
    amount_fcfa       INT NOT NULL,                 -- signé : négatif = sortie
    reference_type    VARCHAR(40) NULL,
    reference_id      BIGINT UNSIGNED NULL,
    note              TEXT NULL,
    created_by        BIGINT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cre_register (cash_register_id),
    INDEX idx_cre_ref (reference_type, reference_id),
    INDEX idx_cre_created (created_at),
    CONSTRAINT fk_cre_register
        FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id)
) ENGINE=InnoDB CHARSET=utf8mb4;
