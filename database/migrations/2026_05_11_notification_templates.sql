-- Templates de notifications avec variables (GAP-19)

CREATE TABLE IF NOT EXISTS notification_templates (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key  VARCHAR(80)     NOT NULL,
    channel       ENUM('sms','email','push','whatsapp') NOT NULL DEFAULT 'sms',
    label         VARCHAR(120)    NOT NULL,
    subject       VARCHAR(200)    NULL COMMENT 'Pour email uniquement',
    body          TEXT            NOT NULL COMMENT 'Avec placeholders {{variable}}',
    variables     JSON            NULL COMMENT 'Variables disponibles (auto-doc)',
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    is_system     TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = template système, non supprimable',
    version       INT UNSIGNED    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tpl_key_channel (template_key, channel),
    INDEX idx_tpl_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique d'envoi
CREATE TABLE IF NOT EXISTS notification_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id   BIGINT UNSIGNED NULL,
    template_key  VARCHAR(80)     NOT NULL,
    channel       ENUM('sms','email','push','whatsapp') NOT NULL,
    recipient     VARCHAR(200)    NOT NULL,
    customer_id   BIGINT UNSIGNED NULL,
    subject       VARCHAR(200)    NULL,
    body          TEXT            NOT NULL,
    status        ENUM('queued','sent','failed','bounced') NOT NULL DEFAULT 'queued',
    error         TEXT            NULL,
    sent_at       DATETIME        NULL,
    related_table VARCHAR(50)     NULL,
    related_id    BIGINT UNSIGNED NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_log_recipient (recipient, created_at),
    INDEX idx_log_template (template_key, channel, created_at),
    INDEX idx_log_customer (customer_id, created_at),
    CONSTRAINT fk_log_template FOREIGN KEY (template_id) REFERENCES notification_templates(id) ON DELETE SET NULL,
    CONSTRAINT fk_log_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Templates par défaut (système)
INSERT INTO notification_templates (template_key, channel, label, subject, body, variables, is_system) VALUES
('ticket.sold', 'sms', 'Ticket émis (SMS)', NULL,
 'CITY BUS · Billet {{ticket_number}} émis pour {{passenger_name}} sur le voyage {{trip_code}} du {{trip_date}} départ {{departure_time}}. Siège {{seat_number}}. Bon voyage !',
 '["ticket_number","passenger_name","trip_code","trip_date","departure_time","seat_number","price","line_name"]', 1),
('ticket.sold', 'email', 'Ticket émis (Email)', '[CITY BUS] Confirmation de votre billet {{ticket_number}}',
 'Bonjour {{passenger_name}},\r\n\r\nVotre billet est confirmé :\r\n- Voyage : {{trip_code}}\r\n- Ligne : {{line_name}}\r\n- Date : {{trip_date}} - Départ {{departure_time}}\r\n- Siège : {{seat_number}}\r\n- Prix : {{price}} FCFA\r\n\r\nMerci pour votre confiance.\r\nL''équipe CITY BUS',
 '["ticket_number","passenger_name","trip_code","trip_date","departure_time","seat_number","price","line_name"]', 1),
('trip.reminder_24h', 'sms', 'Rappel J-1 voyage', NULL,
 'CITY BUS · Rappel : votre voyage {{trip_code}} part demain {{departure_time}} depuis {{origin}} vers {{destination}}. Siège {{seat_number}}. À demain !',
 '["trip_code","departure_time","origin","destination","seat_number","passenger_name"]', 1),
('trip.delayed', 'sms', 'Voyage retardé', NULL,
 'CITY BUS · Votre voyage {{trip_code}} est retardé. Nouveau départ prévu : {{new_departure}}. Désolés pour la gêne occasionnée.',
 '["trip_code","new_departure","old_departure","reason"]', 1),
('trip.cancelled', 'sms', 'Voyage annulé', NULL,
 'CITY BUS · Désolés, le voyage {{trip_code}} du {{trip_date}} est annulé. Avoir {{voucher_amount}} FCFA (code {{voucher_code}}) valable jusqu''au {{voucher_expiry}}.',
 '["trip_code","trip_date","voucher_amount","voucher_code","voucher_expiry","reason"]', 1),
('parcel.deposited', 'sms', 'Colis déposé', NULL,
 'CITY BUS · Colis {{parcel_number}} déposé à {{origin}} pour {{recipient_name}}. Suivi : {{parcel_number}}',
 '["parcel_number","origin","destination","recipient_name","sender_name"]', 1),
('parcel.arrived', 'sms', 'Colis arrivé', NULL,
 'CITY BUS · Votre colis {{parcel_number}} est arrivé à {{destination}}. Présentez-vous avec une pièce d''identité. Tel : {{agency_phone}}',
 '["parcel_number","destination","agency_phone","recipient_name"]', 1),
('reservation.created', 'sms', 'Réservation créée', NULL,
 'CITY BUS · Réservation {{pnr_code}} confirmée. Total {{total_amount}} FCFA. Validité jusqu''au {{hold_expires}}.',
 '["pnr_code","total_amount","hold_expires","contact_name"]', 1),
('feedback.request', 'sms', 'Demande d''avis', NULL,
 'CITY BUS · Comment s''est passé votre voyage {{trip_code}} ? Notez-nous : {{feedback_url}}',
 '["trip_code","feedback_url","passenger_name"]', 1)
ON DUPLICATE KEY UPDATE template_key = template_key;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('notifications.view', 'notifications', 'view', 'Voir templates notifications', 320),
    ('notifications.manage', 'notifications', 'manage', 'Gérer templates notifications', 321)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'notifications.%'
ON DUPLICATE KEY UPDATE role_id = role_id;
