-- ============================================================
-- Migration : catégories système par défaut pour la trésorerie
-- Date : 2026-05-27
-- ============================================================

-- 1. Promouvoir les catégories existantes en système
UPDATE treasury_categories SET is_system = 1
WHERE code IN ('carburant', 'entretien', 'salaire_avance', 'versement_banque', 'retrait_banque', 'fournitures');

-- 2. Réorganiser les sort_order par groupement logique
--    Encaissements 1-19  |  Banque 10-19  |  Exploitation 20-29
--    Personnel 40-49     |  Fonctionnement 50-59  |  Catch-all 90+
UPDATE treasury_categories SET sort_order = 1  WHERE code = 'billetterie';
UPDATE treasury_categories SET sort_order = 2  WHERE code = 'fret';
UPDATE treasury_categories SET sort_order = 10 WHERE code = 'versement_banque';
UPDATE treasury_categories SET sort_order = 11 WHERE code = 'retrait_banque';
UPDATE treasury_categories SET sort_order = 20 WHERE code = 'carburant';
UPDATE treasury_categories SET sort_order = 21 WHERE code = 'entretien';
UPDATE treasury_categories SET sort_order = 40 WHERE code = 'salaire_avance';
UPDATE treasury_categories SET sort_order = 50 WHERE code = 'fournitures';
UPDATE treasury_categories SET sort_order = 60 WHERE code = 'remboursement';
UPDATE treasury_categories SET sort_order = 90 WHERE code = 'autre_recette';
UPDATE treasury_categories SET sort_order = 91 WHERE code = 'autre_depense';

-- 3. Nouvelles catégories système
INSERT IGNORE INTO treasury_categories
    (code, label, type, source, is_system, color, is_active, sort_order)
VALUES
-- ── Encaissements ─────────────────────────────────────────────────────
('quittance_chauffeur', 'Quittance chauffeur',          'encaissement', 'tresorerie', 1, 'green',  1, 4),
('location_bus',        'Location de bus',              'encaissement', 'tresorerie', 1, 'orange', 1, 5),
('subvention',          'Subventions / aides',          'encaissement', 'tresorerie', 1, 'green',  1, 6),
('penalite',            'Pénalités / retenues',         'encaissement', 'tresorerie', 1, 'red',    1, 7),
('consigne_bagages',    'Consigne bagages',             'encaissement', 'tresorerie', 1, 'amber',  1, 8),

-- ── Décaissements exploitation ────────────────────────────────────────
('lavage_bus',          'Lavage de bus',                'decaissement', 'tresorerie', 1, 'blue',   1, 22),
('parking',             'Frais de parking',             'decaissement', 'tresorerie', 1, 'slate',  1, 23),
('peage',               'Péages routiers',              'decaissement', 'tresorerie', 1, 'amber',  1, 24),
('pneumatique',         'Pneumatiques / pneus',         'decaissement', 'tresorerie', 1, 'slate',  1, 25),

-- ── Décaissements personnel ───────────────────────────────────────────
('salaire',             'Salaires',                     'decaissement', 'tresorerie', 1, 'pink',   1, 41),
('prime_journaliere',   'Prime journalière',            'decaissement', 'tresorerie', 1, 'blue',   1, 42),
('prime_autre',         'Autres primes',                'decaissement', 'tresorerie', 1, 'violet', 1, 43),
('commission_agent',    'Commissions agents',           'decaissement', 'tresorerie', 1, 'violet', 1, 44),
('indemnite',           'Indemnités de déplacement',    'decaissement', 'tresorerie', 1, 'blue',   1, 45),

-- ── Décaissements admin / fonctionnement ──────────────────────────────
('quittance',           'Quittances / redevances',      'decaissement', 'tresorerie', 1, 'slate',  1, 51),
('loyer',               'Loyer locaux / gare',          'decaissement', 'tresorerie', 1, 'slate',  1, 52),
('assurance',           'Assurances véhicules',         'decaissement', 'tresorerie', 1, 'green',  1, 53),
('amende',              'Amendes / contraventions',     'decaissement', 'tresorerie', 1, 'red',    1, 54),
('impot_taxe',          'Impôts et taxes',              'decaissement', 'tresorerie', 1, 'orange', 1, 55),
('telecoms',            'Télécoms / internet',          'decaissement', 'tresorerie', 1, 'blue',   1, 56),
('electricite_eau',     'Électricité / eau',            'decaissement', 'tresorerie', 1, 'amber',  1, 57),
('visite_technique',    'Visite technique',             'decaissement', 'tresorerie', 1, 'green',  1, 58);
