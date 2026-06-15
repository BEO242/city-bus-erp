-- Tarifs par origine-destination (GAP-04)
-- Permet de tarifer par couple (arrêt embarquement, arrêt débarquement) et
-- de revendre un siège sur un segment aval libre.

CREATE TABLE IF NOT EXISTS od_pricing (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    line_id             BIGINT UNSIGNED NOT NULL,
    from_stop_id        BIGINT UNSIGNED NOT NULL,
    to_stop_id          BIGINT UNSIGNED NOT NULL,
    ticket_type         VARCHAR(50)     NOT NULL DEFAULT 'finale',
    passenger_category  VARCHAR(50)     NOT NULL DEFAULT 'adulte',
    travel_class        VARCHAR(50)     NOT NULL DEFAULT 'standard',
    price_fcfa          INT UNSIGNED    NOT NULL,
    distance_km         DECIMAL(8,2)    NULL,
    valid_from          DATE            NULL,
    valid_until         DATE            NULL,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_od_pricing (line_id, from_stop_id, to_stop_id, ticket_type, passenger_category, travel_class),
    INDEX idx_od_pricing_line (line_id, is_active),
    CONSTRAINT fk_od_line FOREIGN KEY (line_id)      REFERENCES bus_lines(id) ON DELETE CASCADE,
    CONSTRAINT fk_od_from FOREIGN KEY (from_stop_id) REFERENCES stops(id)     ON DELETE CASCADE,
    CONSTRAINT fk_od_to   FOREIGN KEY (to_stop_id)   REFERENCES stops(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings O-D pricing
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('pricing.od_enabled',     'voyage', 'boolean', '1',  'Tarifs origine-destination', 'Active la grille tarifaire par couple O-D. Si désactivé, tarifs ligne globaux.', 90, 0),
('pricing.od_resale_seat', 'voyage', 'boolean', '1',  'Revente de sièges en aval', 'Permet de revendre un siège libéré à un arrêt intermédiaire.', 91, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
