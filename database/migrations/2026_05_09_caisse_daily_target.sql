-- Ajout du paramètre objectif journalier de caisse
-- Utilisé par DashboardController pour afficher la jauge de progression

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret)
VALUES (
    'caisse.daily_target',
    'caisse',
    'integer',
    '2500000',
    'Objectif journalier (FCFA)',
    'Montant cible pour la jauge de progression du chiffre d''affaires sur le tableau de bord.',
    50,
    0
)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
