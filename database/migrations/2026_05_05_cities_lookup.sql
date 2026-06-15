-- Migration : table de référence cities + bascule de agencies.city et bus_lines.*_city en FK
-- Permet l'expansion à n'importe quelle ville sans modifier le schéma.

CREATE TABLE IF NOT EXISTS cities (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(50)     NOT NULL,
    name          VARCHAR(100)    NOT NULL,
    region        VARCHAR(50)     NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    display_order INT             NOT NULL DEFAULT 100,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cities_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cities (slug, name, region, display_order) VALUES
    ('brazzaville',  'Brazzaville',  'Brazzaville',  10),
    ('pointe_noire', 'Pointe-Noire', 'Kouilou',      20),
    ('dolisie',      'Dolisie',      'Niari',        30),
    ('nkayi',        'Nkayi',        'Bouenza',      40),
    ('owando',       'Owando',       'Cuvette',      50),
    ('ouesso',       'Ouesso',       'Sangha',       60),
    ('impfondo',     'Impfondo',     'Likouala',     70),
    ('djambala',     'Djambala',     'Plateaux',     80),
    ('madingou',     'Madingou',     'Bouenza',      90),
    ('gamboma',      'Gamboma',      'Plateaux',    100)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ========== agencies ==========
ALTER TABLE agencies ADD COLUMN city_id BIGINT UNSIGNED NULL AFTER name;
UPDATE agencies a JOIN cities c ON c.slug = a.city SET a.city_id = c.id;
ALTER TABLE agencies MODIFY city_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE agencies ADD CONSTRAINT fk_agencies_city
    FOREIGN KEY (city_id) REFERENCES cities(id) ON UPDATE CASCADE;
ALTER TABLE agencies DROP COLUMN city;

-- ========== bus_lines ==========
ALTER TABLE bus_lines ADD COLUMN departure_city_id BIGINT UNSIGNED NULL AFTER name;
ALTER TABLE bus_lines ADD COLUMN arrival_city_id   BIGINT UNSIGNED NULL AFTER departure_city_id;
UPDATE bus_lines bl
   JOIN cities c1 ON c1.slug = CONVERT(bl.departure_city USING utf8mb4) COLLATE utf8mb4_unicode_ci
   JOIN cities c2 ON c2.slug = CONVERT(bl.arrival_city   USING utf8mb4) COLLATE utf8mb4_unicode_ci
   SET bl.departure_city_id = c1.id, bl.arrival_city_id = c2.id;
ALTER TABLE bus_lines MODIFY departure_city_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE bus_lines MODIFY arrival_city_id   BIGINT UNSIGNED NOT NULL;
ALTER TABLE bus_lines ADD CONSTRAINT fk_lines_dep_city
    FOREIGN KEY (departure_city_id) REFERENCES cities(id) ON UPDATE CASCADE;
ALTER TABLE bus_lines ADD CONSTRAINT fk_lines_arr_city
    FOREIGN KEY (arrival_city_id) REFERENCES cities(id) ON UPDATE CASCADE;
ALTER TABLE bus_lines DROP COLUMN departure_city;
ALTER TABLE bus_lines DROP COLUMN arrival_city;
