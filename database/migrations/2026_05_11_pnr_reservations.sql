-- PNR / Réservations distinctes des billets (GAP-01)
-- Permet de bloquer un siège avec une option de paiement différé.

CREATE TABLE IF NOT EXISTS reservations (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pnr_code        VARCHAR(8)      NOT NULL COMMENT '6-8 caractères type Amadeus (ex: AB12CD)',
    customer_id     BIGINT UNSIGNED NULL,
    contact_name    VARCHAR(120)    NOT NULL,
    contact_phone   VARCHAR(30)     NOT NULL,
    contact_email   VARCHAR(120)    NULL,
    channel         ENUM('counter','phone','website','partner','corporate','agent') NOT NULL DEFAULT 'counter',
    partner_id      BIGINT UNSIGNED NULL COMMENT 'sales_partners.id si vente partenaire',
    corporate_id    BIGINT UNSIGNED NULL COMMENT 'corporate_accounts.id si compte B2B',
    status          ENUM('hold','confirmed','paid','partially_paid','cancelled','expired','converted') NOT NULL DEFAULT 'hold',
    total_amount_fcfa     INT UNSIGNED NOT NULL DEFAULT 0,
    paid_amount_fcfa      INT UNSIGNED NOT NULL DEFAULT 0,
    discount_fcfa         INT UNSIGNED NOT NULL DEFAULT 0,
    promo_code            VARCHAR(40)  NULL,
    voucher_code          VARCHAR(40)  NULL,
    notes                 TEXT         NULL,
    hold_expires_at       DATETIME     NULL COMMENT 'Date d''expiration du hold',
    confirmed_at          DATETIME     NULL,
    cancelled_at          DATETIME     NULL,
    cancel_reason         VARCHAR(255) NULL,
    created_by            BIGINT UNSIGNED NULL,
    agency_id             BIGINT UNSIGNED NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pnr_code (pnr_code),
    INDEX idx_res_customer (customer_id),
    INDEX idx_res_status (status, hold_expires_at),
    INDEX idx_res_phone (contact_phone),
    CONSTRAINT fk_res_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_res_user     FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL,
    CONSTRAINT fk_res_agency   FOREIGN KEY (agency_id)   REFERENCES agencies(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lignes de réservation : un PNR contient N passagers / sièges
CREATE TABLE IF NOT EXISTS reservation_items (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id      BIGINT UNSIGNED NOT NULL,
    trip_id             BIGINT UNSIGNED NOT NULL,
    seat_number         VARCHAR(10)     NULL,
    passenger_name      VARCHAR(120)    NOT NULL,
    passenger_phone     VARCHAR(30)     NULL,
    passenger_category  VARCHAR(50)     NOT NULL DEFAULT 'adulte',
    travel_class        VARCHAR(50)     NOT NULL DEFAULT 'standard',
    boarding_stop_id    BIGINT UNSIGNED NULL,
    alighting_stop_id   BIGINT UNSIGNED NULL,
    price_fcfa          INT UNSIGNED    NOT NULL DEFAULT 0,
    converted_ticket_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_resi_reservation (reservation_id),
    INDEX idx_resi_trip (trip_id),
    CONSTRAINT fk_resi_res    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_resi_trip   FOREIGN KEY (trip_id)        REFERENCES trips(id)        ON DELETE RESTRICT,
    CONSTRAINT fk_resi_ticket FOREIGN KEY (converted_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_resi_board  FOREIGN KEY (boarding_stop_id)    REFERENCES stops(id)   ON DELETE SET NULL,
    CONSTRAINT fk_resi_alight FOREIGN KEY (alighting_stop_id)   REFERENCES stops(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('reservations.view', 'reservations', 'view', 'Voir les réservations', 300),
    ('reservations.create', 'reservations', 'create', 'Créer une réservation', 301),
    ('reservations.confirm', 'reservations', 'confirm', 'Confirmer une réservation', 302),
    ('reservations.cancel', 'reservations', 'cancel', 'Annuler une réservation', 303)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf','exploitation','chef_agence','caissier','agent') AND p.slug LIKE 'reservations.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings PNR
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('reservation.hold_default_minutes', 'billetterie', 'integer', '60',  'Durée hold par défaut (min)', 'Temps avant expiration automatique d''une réservation non payée.', 310, 0),
('reservation.allow_partial_payment','billetterie', 'boolean', '1',   'Paiement partiel autorisé',  'Permet d''encaisser un acompte avec solde à destination.', 311, 0),
('reservation.pnr_format',           'billetterie', 'string',  '6char',  'Format code PNR',         '6char (lettres+chiffres) ou 8char.', 312, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
