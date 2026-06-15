-- Migration: lier les pré-imprimés à un voyage + numéro de siège
-- Date: 2026-05-04

ALTER TABLE pre_printed_tickets
    ADD COLUMN trip_id     BIGINT UNSIGNED NULL AFTER batch_id,
    ADD COLUMN seat_number SMALLINT UNSIGNED NULL AFTER trip_id,
    ADD CONSTRAINT fk_pp_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    ADD INDEX idx_pp_trip (trip_id);
