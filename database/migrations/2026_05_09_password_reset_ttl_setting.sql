-- Paramètre durée de validité du jeton de réinitialisation de mot de passe

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret)
VALUES (
    'security.password_reset_ttl_minutes',
    'security',
    'integer',
    '60',
    'Validité lien réinit. mot de passe (min)',
    'Durée pendant laquelle le lien envoyé par e-mail pour réinitialiser un mot de passe reste valide.',
    65,
    0
)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
