-- ============================================================
-- Migration : Module tickets pré-imprimés urbains
-- Date : 2026-06-14
-- ============================================================

-- Bibliothèque de symboles anti-fraude
CREATE TABLE IF NOT EXISTS urban_ticket_symbols (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol      VARCHAR(10)   NOT NULL,
    label       VARCHAR(50)   NOT NULL,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order  SMALLINT      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_symbol (symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Symboles par défaut (≥16)
INSERT IGNORE INTO urban_ticket_symbols (symbol, label, sort_order) VALUES
('★', 'Étoile pleine',      1),
('☆', 'Étoile vide',        2),
('▲', 'Triangle haut',      3),
('▼', 'Triangle bas',       4),
('◆', 'Losange plein',      5),
('◇', 'Losange vide',       6),
('●', 'Cercle plein',       7),
('○', 'Cercle vide',        8),
('■', 'Carré plein',        9),
('□', 'Carré vide',        10),
('▶', 'Flèche droite',     11),
('◀', 'Flèche gauche',     12),
('♦', 'Diamant',           13),
('♠', 'Pique',             14),
('♣', 'Trèfle',            15),
('♥', 'Cœur',              16),
('✦', 'Étoile 4 branches', 17),
('⬟', 'Pentagone',         18),
('⬡', 'Hexagone',          19),
('✚', 'Croix épaisse',     20);

-- Séries de tickets urbains
CREATE TABLE IF NOT EXISTS urban_ticket_series (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    series_code     VARCHAR(30)     NOT NULL,
    ticket_date     DATE            NOT NULL,
    date_code       VARCHAR(6)      NOT NULL COMMENT 'AAMMJJ',
    symbol_id       BIGINT UNSIGNED NOT NULL,
    symbol_char     VARCHAR(10)     NOT NULL,
    price_fcfa      INT UNSIGNED    NOT NULL DEFAULT 150,
    bus_code        VARCHAR(20)     NOT NULL,
    departure       VARCHAR(100)    NOT NULL,
    arrival         VARCHAR(100)    NOT NULL,
    network_label   VARCHAR(100)    NOT NULL DEFAULT 'Réseau urbain · Brazzaville',
    num_start       INT UNSIGNED    NOT NULL,
    num_end         INT UNSIGNED    NOT NULL,
    ticket_count    INT UNSIGNED    NOT NULL,
    page_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('planifiee','en_cours','cloturee','annulee') NOT NULL DEFAULT 'planifiee',
    tickets_sold    INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_expected INT UNSIGNED   NOT NULL DEFAULT 0,
    revenue_actual  INT UNSIGNED    DEFAULT NULL,
    notes           TEXT            DEFAULT NULL,
    pdf_path        VARCHAR(255)    DEFAULT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    closed_by       BIGINT UNSIGNED DEFAULT NULL,
    closed_at       TIMESTAMP       NULL DEFAULT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_series_code (series_code),
    KEY idx_ticket_date (ticket_date),
    KEY idx_status (status),
    KEY idx_bus_code (bus_code),
    CONSTRAINT fk_uts_symbol FOREIGN KEY (symbol_id) REFERENCES urban_ticket_symbols(id),
    CONSTRAINT fk_uts_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
