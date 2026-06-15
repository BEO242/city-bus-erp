-- Conformité temps de conduite chauffeurs (GAP-13)
-- Journal des heures de conduite, repos, pauses pour conformité légale.

CREATE TABLE IF NOT EXISTS driver_hours_log (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    driver_id         BIGINT UNSIGNED NOT NULL,
    trip_id           BIGINT UNSIGNED NULL,
    log_type          ENUM('conduite','pause','repos_quotidien','repos_hebdo','disponibilite','autre') NOT NULL,
    start_at          DATETIME        NOT NULL,
    end_at            DATETIME        NULL,
    duration_minutes  INT             NULL COMMENT 'Calculé à la fermeture',
    location          VARCHAR(150)    NULL,
    notes             TEXT            NULL,
    source            ENUM('auto_trip','manual','tachograph','app_mobile') NOT NULL DEFAULT 'manual',
    created_by        BIGINT UNSIGNED NULL,
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_dhl_driver (driver_id),
    INDEX idx_dhl_trip (trip_id),
    INDEX idx_dhl_period (driver_id, start_at, end_at),
    INDEX idx_dhl_type (log_type),
    CONSTRAINT fk_dhl_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_dhl_trip   FOREIGN KEY (trip_id)   REFERENCES trips(id)   ON DELETE SET NULL,
    CONSTRAINT fk_dhl_user   FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('hos.view', 'hos', 'view', 'Voir HOS chauffeurs', 220),
    ('hos.edit', 'hos', 'edit', 'Saisir/éditer HOS', 221),
    ('hos.override', 'hos', 'override', 'Outrepasser limites HOS', 222)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation') AND p.slug LIKE 'hos.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings réglementaires
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('hos.daily_max_hours',          'rh', 'integer', '9',  'Conduite max journalière (h)',     'Maximum d''heures de conduite par jour (réglementation OIT/CEMAC).', 230, 0),
('hos.daily_max_extended_hours', 'rh', 'integer', '10', 'Conduite max étendue (h)',         'Maximum exceptionnel autorisé 2 fois par semaine.', 231, 0),
('hos.weekly_max_hours',         'rh', 'integer', '56', 'Conduite max hebdomadaire (h)',    'Maximum d''heures de conduite sur 7 jours glissants.', 232, 0),
('hos.biweekly_max_hours',       'rh', 'integer', '90', 'Conduite max bihebdo (h)',         'Maximum sur 14 jours glissants.', 233, 0),
('hos.continuous_max_minutes',   'rh', 'integer', '270','Conduite continue max (min)',      'Au-delà : pause obligatoire de 45 min minimum (4h30).', 234, 0),
('hos.required_break_minutes',   'rh', 'integer', '45', 'Pause obligatoire min (min)',      'Pause obligatoire après conduite continue.', 235, 0),
('hos.daily_rest_minutes',       'rh', 'integer', '660','Repos quotidien (min)',            'Repos minimum entre deux journées (11 h = 660 min).', 236, 0),
('hos.weekly_rest_hours',        'rh', 'integer', '45', 'Repos hebdomadaire (h)',           'Repos minimum sur 7 jours (45 h consécutives).', 237, 0),
('hos.enforcement_mode',         'rh', 'string',  'warning', 'Mode d''application',         'warning = avertir / block = empêcher l''affectation.', 238, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
