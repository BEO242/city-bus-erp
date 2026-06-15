-- Patterns d'horaires récurrents (GAP-11)
-- Évite la saisie manuelle des voyages : un pattern génère les voyages des N prochains jours.

CREATE TABLE IF NOT EXISTS schedule_patterns (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    label           VARCHAR(120)    NOT NULL,
    line_id         BIGINT UNSIGNED NOT NULL,
    bus_id          BIGINT UNSIGNED NULL COMMENT 'Bus par défaut, peut être null pour rotation',
    primary_driver_id BIGINT UNSIGNED NULL,
    days_of_week    VARCHAR(20)     NOT NULL DEFAULT '1,2,3,4,5,6,7' COMMENT 'CSV : 1=lundi..7=dimanche',
    departure_time  TIME            NOT NULL,
    arrival_time    TIME            NULL,
    base_price_fcfa INT UNSIGNED    NULL,
    valid_from      DATE            NOT NULL,
    valid_until     DATE            NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    auto_generate_days INT UNSIGNED NOT NULL DEFAULT 14 COMMENT 'Combien de jours générer à l''avance',
    last_generated_until DATE       NULL,
    notes           TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sp_line (line_id, is_active),
    INDEX idx_sp_active (is_active, valid_from, valid_until),
    CONSTRAINT fk_sp_line FOREIGN KEY (line_id) REFERENCES bus_lines(id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_bus  FOREIGN KEY (bus_id)  REFERENCES buses(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exceptions (jours fériés, journées spéciales)
CREATE TABLE IF NOT EXISTS schedule_pattern_exceptions (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pattern_id     BIGINT UNSIGNED NULL COMMENT 'NULL = applique à tous',
    exception_date DATE            NOT NULL,
    type           ENUM('skip','custom_time') NOT NULL DEFAULT 'skip',
    custom_departure_time TIME     NULL,
    notes          VARCHAR(255)    NULL,
    PRIMARY KEY (id),
    INDEX idx_spe_pattern_date (pattern_id, exception_date),
    CONSTRAINT fk_spe_pattern FOREIGN KEY (pattern_id) REFERENCES schedule_patterns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien voyage → pattern (pour traçabilité)
ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS schedule_pattern_id BIGINT UNSIGNED NULL,
    ADD INDEX idx_trips_pattern (schedule_pattern_id);

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('voyages.schedule.view', 'voyages', 'view', 'Voir patterns horaires', 330),
    ('voyages.schedule.manage', 'voyages', 'manage', 'Gérer patterns horaires', 331)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug LIKE 'voyages.schedule.%'
ON DUPLICATE KEY UPDATE role_id = role_id;
