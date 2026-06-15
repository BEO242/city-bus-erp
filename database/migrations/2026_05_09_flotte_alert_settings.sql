-- Ajout des paramètres d'alerte flotte (contrôle technique, assurance)

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret)
VALUES
('flotte.tech_control_alert_days', 'voyage', 'integer', '30', 'Alerte contrôle technique (jours)', 'Nombre de jours avant expiration du contrôle technique pour générer une alerte au tableau de bord.', 80, 0),
('flotte.insurance_alert_days', 'voyage', 'integer', '30', 'Alerte assurance (jours)', 'Nombre de jours avant expiration de l''assurance bus pour générer une alerte au tableau de bord.', 81, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
