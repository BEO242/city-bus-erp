-- Paramètres pour les webhooks sortants

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret)
VALUES
('integration.webhook_url',    'integration', 'string', '',
 'URL du webhook',
 'URL HTTPS qui recevra les événements (ticket.sold, trip.departure, …) en POST JSON. Vide = désactivé.',
 10, 0),
('integration.webhook_secret', 'integration', 'secret', '',
 'Secret HMAC',
 'Clé partagée pour signer le payload (en-tête X-CityBus-Signature : sha256=…).',
 11, 1),
('integration.webhook_events', 'integration', 'string', '',
 'Événements abonnés',
 'Liste d''événements (un par ligne ou virgule). Vide ou * = tous les événements. Ex: ticket.sold, trip.departure.',
 12, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
