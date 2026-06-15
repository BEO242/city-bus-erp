-- ============================================================================
-- Types de billets pour le transport urbain
-- ============================================================================
-- Les lignes urbaines (bus_lines.line_type = 'urbain') n'utilisent pas les
-- mêmes types de billets que les lignes interurbaines. Ce script insère les
-- types spécifiques au réseau urbain.
-- ============================================================================

INSERT INTO tariff_ticket_types (slug, label, icon, color_class, description, is_active, sort_order) VALUES
    ('course_simple',         'Course (trajet unique)',   'bus',           'indigo', 'Trajet unique sur réseau urbain',        1, 10),
    ('carnet_10',             'Carnet 10 voyages',        'layers',        'teal',   'Prépayé 10 trajets urbains',             1, 11),
    ('abonnement_journalier', 'Abonnement journalier',    'sun',           'amber',  'Pass valable 1 journée complète',        1, 12),
    ('abonnement_hebdo',      'Abonnement hebdomadaire',  'calendar-days', 'orange', 'Pass valable 7 jours consécutifs',       1, 13)
ON DUPLICATE KEY UPDATE label = VALUES(label), sort_order = VALUES(sort_order);
