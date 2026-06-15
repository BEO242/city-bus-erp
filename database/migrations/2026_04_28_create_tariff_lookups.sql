-- =====================================================================
-- Création des tables de référentiel tarifaire (P0)
-- Tables utilisées par le modèle Tariff::ticketTypesFull/passengerCategoriesFull/travelClassesFull
-- mais jamais déclarées dans schema.sql.
-- (tariff_baggage_natures et tariff_services sont créées par 2026_04_30_baggage_tariffs.sql)
-- =====================================================================

CREATE TABLE IF NOT EXISTS tariff_ticket_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    icon VARCHAR(60) DEFAULT NULL,
    color_class VARCHAR(60) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tariff_passenger_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    icon VARCHAR(60) DEFAULT NULL,
    color_class VARCHAR(60) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tariff_travel_classes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    icon VARCHAR(60) DEFAULT NULL,
    color_class VARCHAR(60) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données initiales : 4 valeurs alignées sur l'ENUM tickets.ticket_type
INSERT IGNORE INTO tariff_ticket_types (slug, label, icon, color_class, description, sort_order) VALUES
    ('passager',         'Passage final',      'user',          'bg-blue-100 text-blue-800',      'Billet passager classique', 10),
    ('arret_route',      'Arrêt en route',     'map-pin',       'bg-amber-100 text-amber-800',    'Embarquement en cours de route', 20),
    ('bagage_franchise', 'Bagage en franchise','briefcase',     'bg-emerald-100 text-emerald-800','Bagage inclus', 30),
    ('bagage_excedent',  'Bagage excédentaire','luggage',       'bg-rose-100 text-rose-800',      'Excédent payant', 40);

INSERT IGNORE INTO tariff_passenger_categories (slug, label, icon, color_class, sort_order) VALUES
    ('adulte',     'Adulte',         'user',          'bg-slate-100 text-slate-800',   10),
    ('enfant',     'Enfant',         'baby',          'bg-pink-100 text-pink-800',     20),
    ('etudiant',   'Étudiant',       'graduation-cap','bg-indigo-100 text-indigo-800', 30),
    ('senior',     'Senior',         'user',          'bg-yellow-100 text-yellow-800', 40);

INSERT IGNORE INTO tariff_travel_classes (slug, label, icon, color_class, sort_order) VALUES
    ('standard',   'Standard',       'star',          'bg-slate-100 text-slate-800',   10),
    ('vip',        'VIP',            'crown',         'bg-purple-100 text-purple-800', 20);
