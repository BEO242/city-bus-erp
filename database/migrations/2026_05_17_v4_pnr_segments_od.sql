-- =============================================================
-- V4.A — PNR multi-segments + O-D pricing
-- Étend reservations + reservation_items + ajoute od_fares + pnr_passengers
-- =============================================================

-- Étend reservations (PNR header)
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS total_segments TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER total_amount_fcfa,
    ADD COLUMN IF NOT EXISTS total_pax TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER total_segments,
    ADD COLUMN IF NOT EXISTS booking_class CHAR(1) NULL AFTER total_pax,
    ADD COLUMN IF NOT EXISTS issue_status ENUM('held','ticketed','partially_ticketed') NOT NULL DEFAULT 'held' AFTER status,
    ADD COLUMN IF NOT EXISTS source_locator VARCHAR(40) NULL COMMENT 'GDS external reference';

-- Étend reservation_items (segments)
ALTER TABLE reservation_items
    ADD COLUMN IF NOT EXISTS sequence TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER reservation_id,
    ADD COLUMN IF NOT EXISTS pnr_passenger_id BIGINT UNSIGNED NULL AFTER trip_id,
    ADD COLUMN IF NOT EXISTS booking_class CHAR(1) NOT NULL DEFAULT 'M' AFTER travel_class,
    ADD COLUMN IF NOT EXISTS fare_basis VARCHAR(20) NULL AFTER booking_class,
    ADD COLUMN IF NOT EXISTS od_fare_id BIGINT UNSIGNED NULL AFTER fare_basis,
    ADD COLUMN IF NOT EXISTS pax_type ENUM('ADT','CHD','INF','SNR','STU','MIL','VIP') NOT NULL DEFAULT 'ADT' AFTER booking_class,
    ADD COLUMN IF NOT EXISTS segment_status ENUM('booked','confirmed','flown','no_show','cancelled','irop','transferred') NOT NULL DEFAULT 'booked' AFTER price_fcfa,
    ADD INDEX IF NOT EXISTS idx_resi_seq (reservation_id, sequence);

-- Passagers (séparés des segments — 1 PNR peut avoir N pax × N segments)
CREATE TABLE IF NOT EXISTS pnr_passengers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reservation_id  BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    title           ENUM('M','Mme','Mlle','Dr','Pr') NOT NULL DEFAULT 'M',
    first_name      VARCHAR(60) NOT NULL,
    last_name       VARCHAR(60) NOT NULL,
    dob             DATE NULL,
    pax_type        ENUM('ADT','CHD','INF','SNR','STU','MIL','VIP') NOT NULL DEFAULT 'ADT',
    document_type   ENUM('cni','passport','permis','none') NOT NULL DEFAULT 'cni',
    document_number VARCHAR(40) NULL,
    nationality     CHAR(3) NULL COMMENT 'ISO 3166-1 alpha-3',
    phone           VARCHAR(30) NULL,
    email           VARCHAR(120) NULL,
    seat_preference ENUM('window','aisle','any') NULL,
    special_request TEXT NULL,
    sequence        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uk_pp_seq (reservation_id, sequence),
    INDEX idx_pp_customer (customer_id),
    CONSTRAINT fk_pp_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tarifs O-D (origin-destination par classe/fare_basis)
CREATE TABLE IF NOT EXISTS od_fares (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    line_id         BIGINT UNSIGNED NOT NULL,
    from_stop_id    BIGINT UNSIGNED NOT NULL,
    to_stop_id      BIGINT UNSIGNED NOT NULL,
    booking_class   CHAR(1) NOT NULL DEFAULT 'M',
    fare_basis      VARCHAR(20) NOT NULL DEFAULT 'STD',
    pax_type        ENUM('ADT','CHD','INF','SNR','STU','MIL','VIP') NOT NULL DEFAULT 'ADT',
    base_price_fcfa INT UNSIGNED NOT NULL,
    refundable      TINYINT(1) NOT NULL DEFAULT 1,
    changeable      TINYINT(1) NOT NULL DEFAULT 1,
    refund_fee_fcfa INT NOT NULL DEFAULT 0,
    change_fee_fcfa INT NOT NULL DEFAULT 0,
    valid_from      DATE NULL,
    valid_until     DATE NULL,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_od (line_id, from_stop_id, to_stop_id, booking_class, fare_basis, pax_type),
    INDEX idx_od_line (line_id, active),
    CONSTRAINT fk_odv4_line FOREIGN KEY (line_id) REFERENCES bus_lines(id) ON DELETE CASCADE,
    CONSTRAINT fk_odv4_from FOREIGN KEY (from_stop_id) REFERENCES stops(id) ON DELETE CASCADE,
    CONSTRAINT fk_odv4_to   FOREIGN KEY (to_stop_id)   REFERENCES stops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liens correspondances (segment N -> segment N+1 du même PNR)
CREATE TABLE IF NOT EXISTS pnr_connections (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reservation_id  BIGINT UNSIGNED NOT NULL,
    inbound_segment_id BIGINT UNSIGNED NOT NULL,
    outbound_segment_id BIGINT UNSIGNED NOT NULL,
    connection_minutes INT NOT NULL COMMENT 'temps de transit',
    is_protected    TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'si retard inbound, outbound est rebooké auto',
    UNIQUE KEY uk_conn (inbound_segment_id, outbound_segment_id),
    CONSTRAINT fk_conn_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_conn_in  FOREIGN KEY (inbound_segment_id)  REFERENCES reservation_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_conn_out FOREIGN KEY (outbound_segment_id) REFERENCES reservation_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('pnr.modify',          'pnr', 'modify',     'Modifier un PNR existant', 320),
  ('pnr.ticket',          'pnr', 'ticket',     'Émettre les billets d''un PNR', 321),
  ('pnr.refund',          'pnr', 'refund',     'Rembourser un PNR', 322),
  ('od_fares.view',       'pnr', 'fares_view', 'Voir les tarifs O-D', 330),
  ('od_fares.manage',     'pnr', 'fares_manage','Gérer les tarifs O-D', 331)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('pnr.min_connection_time_min', 'billetterie','integer','30', 'MCT - Minimum Connection Time (min)', 'Temps min entre 2 segments de même PNR', 340, 0),
  ('pnr.max_segments',            'billetterie','integer','4',  'Segments max par PNR', '', 341, 0),
  ('pnr.auto_protect_connections','billetterie','boolean','1',  'Auto-rebook correspondance ratée', '', 342, 0),
  ('odfares.fallback_to_line_price','billetterie','boolean','1','Fallback prix ligne si OD inconnu', '', 343, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
