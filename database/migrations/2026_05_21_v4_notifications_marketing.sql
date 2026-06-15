-- =============================================================
-- V4.F — Notifications & marketing complets
-- =============================================================

-- notification_templates existe (template_key, body, is_active)
INSERT INTO notification_templates (template_key, channel, label, subject, body, is_active) VALUES
  ('BOOKING_CONFIRMED_SMS', 'sms', 'PNR confirmé SMS', NULL, 'CityBus: PNR {pnr} confirmé. Voyage {trip_code} le {date} à {time}.', 1),
  ('BOOKING_CONFIRMED_EMAIL', 'email', 'PNR confirmé email', 'Confirmation réservation {pnr}', 'Bonjour {name}, voyage {trip_code} le {date} à {time} confirmé. PNR : {pnr}.', 1),
  ('REMINDER_J1_SMS', 'sms', 'Rappel J-1', NULL, 'CityBus: votre voyage {trip_code} part demain {date} à {time}.', 1),
  ('DELAY_NOTIFY_SMS', 'sms', 'Retard', NULL, 'CityBus: Voyage {trip_code} retardé de {delay} min. Nouveau départ : {new_time}.', 1),
  ('IROP_REBOOK_SMS', 'sms', 'IROP rebooking', NULL, 'CityBus: Voyage {trip_code} annulé. Rebooké sur {new_trip}.', 1),
  ('FEEDBACK_REQUEST_SMS', 'sms', 'Feedback', NULL, 'CityBus: Comment s''est passé votre voyage {trip_code} ?', 1),
  ('LOYALTY_TIER_UP_SMS', 'sms', 'Tier upgrade', NULL, 'CityBus: Félicitations {name} ! Vous êtes désormais {tier}.', 1),
  ('PAYMENT_RECEIVED_SMS', 'sms', 'Paiement reçu', NULL, 'CityBus: Paiement de {amount} FCFA reçu pour PNR {pnr}.', 1)
ON DUPLICATE KEY UPDATE template_key = template_key;

CREATE TABLE IF NOT EXISTS notification_dispatches (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_code   VARCHAR(60) NULL,
    channel         VARCHAR(20) NOT NULL,
    recipient_phone VARCHAR(30) NULL,
    recipient_email VARCHAR(120) NULL,
    customer_id     BIGINT UNSIGNED NULL,
    pnr_id          BIGINT UNSIGNED NULL,
    payload         JSON NULL,
    rendered_subject VARCHAR(180) NULL,
    rendered_body   TEXT NULL,
    status          ENUM('queued','sent','delivered','opened','clicked','failed','bounced') NOT NULL DEFAULT 'queued',
    sent_at         TIMESTAMP NULL,
    delivered_at    TIMESTAMP NULL,
    opened_at       TIMESTAMP NULL,
    clicked_at      TIMESTAMP NULL,
    error_msg       TEXT NULL,
    retry_count     TINYINT NOT NULL DEFAULT 0,
    provider        VARCHAR(40) NULL,
    provider_msg_id VARCHAR(120) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nd_status (status, sent_at),
    INDEX idx_nd_customer (customer_id),
    INDEX idx_nd_pnr (pnr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(180) NOT NULL,
    description     TEXT NULL,
    audience_query  TEXT NULL,
    template_code   VARCHAR(60),
    scheduled_at    DATETIME NULL,
    sent_count      INT NOT NULL DEFAULT 0,
    delivered_count INT NOT NULL DEFAULT 0,
    opened_count    INT NOT NULL DEFAULT 0,
    clicked_count   INT NOT NULL DEFAULT 0,
    revenue_attributed_fcfa BIGINT NOT NULL DEFAULT 0,
    status          ENUM('draft','scheduled','running','done','cancelled') NOT NULL DEFAULT 'draft',
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('notifications.templates.view',   'notifications', 'tpl_view',   'Voir templates', 700),
  ('notifications.templates.manage', 'notifications', 'tpl_manage', 'Gérer templates', 701),
  ('notifications.dispatches.view',  'notifications', 'disp_view',  'Voir envois', 702),
  ('marketing.campaigns.view',       'marketing',     'camp_view',  'Voir campagnes', 710),
  ('marketing.campaigns.manage',     'marketing',     'camp_manage','Lancer campagnes', 711)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('notif.sms_provider',     'notifications','string','africastalking','Provider SMS','africastalking/twilio/local', 900, 0),
  ('notif.sms_api_key',      'notifications','string','',              'Clé API SMS','', 901, 1),
  ('notif.email_provider',   'notifications','string','smtp',           'Provider email','smtp/brevo/mailgun', 902, 0),
  ('notif.smtp_host',        'notifications','string','smtp.gmail.com', 'SMTP host','', 903, 0),
  ('notif.smtp_port',        'notifications','integer','587',           'SMTP port','', 904, 0),
  ('notif.smtp_username',    'notifications','string','',              'SMTP user','', 905, 1),
  ('notif.smtp_password',    'notifications','string','',              'SMTP pass','', 906, 1),
  ('notif.from_name',        'notifications','string','City Bus',       'Nom expéditeur','', 907, 0),
  ('notif.from_email',       'notifications','string','noreply@citybus.cg','Email expéditeur','', 908, 0),
  ('notif.reminder_j1_hour', 'notifications','integer','18',             'Heure cron rappel J-1','0-23', 910, 0),
  ('notif.feedback_after_hours','notifications','integer','3',           'Demande feedback après (h)','', 911, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
