-- ============================================================
-- Migration : Module billets bagages séparé
-- Date      : 2026_04_30
-- ============================================================

-- Table principale des billets bagages
CREATE TABLE IF NOT EXISTS baggage_tickets (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identification
    ticket_number       VARCHAR(30) NOT NULL UNIQUE,           -- ex: BGG-2026-000001

    -- Voyage
    trip_id             BIGINT UNSIGNED NOT NULL,
    line_id             BIGINT UNSIGNED NOT NULL,

    -- Passager propriétaire
    passenger_ticket_id BIGINT UNSIGNED NULL,                  -- FK vers tickets.id (optionnel)
    passenger_name      VARCHAR(120) NOT NULL,
    passenger_phone     VARCHAR(20) NULL,

    -- Tarif bagage appliqué
    baggage_tariff_id   BIGINT UNSIGNED NOT NULL,
    baggage_nature_id   BIGINT UNSIGNED NOT NULL,

    -- Mesures physiques
    weight_kg           DECIMAL(6,2) NOT NULL,
    length_cm           SMALLINT UNSIGNED NULL,
    width_cm            SMALLINT UNSIGNED NULL,
    height_cm           SMALLINT UNSIGNED NULL,
    description         VARCHAR(255) NULL,                     -- ex : "Valise noire, poignée rouge"

    -- Prix calculé
    base_fee_fcfa       INT UNSIGNED NOT NULL DEFAULT 0,
    weight_fee_fcfa     INT UNSIGNED NOT NULL DEFAULT 0,
    volume_surcharge_fcfa INT UNSIGNED NOT NULL DEFAULT 0,
    total_price_fcfa    INT UNSIGNED NOT NULL,

    -- Caisse / vendeur
    agency_id           BIGINT UNSIGNED NOT NULL DEFAULT 1,
    sold_by             BIGINT UNSIGNED NOT NULL,
    cash_register_id    BIGINT UNSIGNED NULL,

    -- Statut
    status              ENUM('emis','annule') NOT NULL DEFAULT 'emis',
    cancelled_at        DATETIME NULL,
    cancelled_by        INT UNSIGNED NULL,
    cancel_reason       VARCHAR(255) NULL,

    -- PDF
    pdf_path            VARCHAR(255) NULL,
    qr_code_path        VARCHAR(255) NULL,

    -- Timestamps
    sold_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME NULL,

    -- FK
    CONSTRAINT fk_bgt_trip       FOREIGN KEY (trip_id)             REFERENCES trips(id),
    CONSTRAINT fk_bgt_line       FOREIGN KEY (line_id)             REFERENCES bus_lines(id),
    CONSTRAINT fk_bgt_ptkt       FOREIGN KEY (passenger_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_bgt_tariff     FOREIGN KEY (baggage_tariff_id)   REFERENCES baggage_tariffs(id),
    CONSTRAINT fk_bgt_nature     FOREIGN KEY (baggage_nature_id)   REFERENCES tariff_baggage_natures(id),
    CONSTRAINT fk_bgt_seller     FOREIGN KEY (sold_by)             REFERENCES users(id),

    INDEX idx_bgt_trip   (trip_id),
    INDEX idx_bgt_line   (line_id),
    INDEX idx_bgt_ptkt   (passenger_ticket_id),
    INDEX idx_bgt_sold   (sold_at),
    INDEX idx_bgt_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Numérotation auto des billets bagages
CREATE TABLE IF NOT EXISTS baggage_ticket_sequences (
    year        SMALLINT UNSIGNED NOT NULL,
    last_seq    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed séquence année courante
INSERT IGNORE INTO baggage_ticket_sequences (year, last_seq) VALUES (YEAR(CURDATE()), 0);
