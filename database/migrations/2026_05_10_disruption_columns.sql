-- Colonnes nécessaires pour la disruption (GAP-08)

ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS cancellation_reason VARCHAR(255) NULL AFTER status;

ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME    NULL,
    ADD COLUMN IF NOT EXISTS cancelled_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) NULL,
    ADD INDEX idx_tickets_cancelled (cancelled_at);
