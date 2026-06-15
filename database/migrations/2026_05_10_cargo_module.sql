-- Module Cargo / Colis — gestion des envois entre agences
-- (GAP-10 du plan d'implémentation v2.0)

CREATE TABLE IF NOT EXISTS parcel_tariffs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    label           VARCHAR(100)    NOT NULL,
    parcel_type     ENUM('document','colis','fragile','special') NOT NULL DEFAULT 'colis',
    weight_min_kg   DECIMAL(8,2)    NOT NULL DEFAULT 0,
    weight_max_kg   DECIMAL(8,2)    NULL,
    fixed_fee_fcfa  INT UNSIGNED    NOT NULL DEFAULT 0,
    price_per_kg    INT UNSIGNED    NOT NULL DEFAULT 0,
    price_per_m3    INT UNSIGNED    NOT NULL DEFAULT 0,
    insurance_pct   DECIMAL(5,2)    NULL COMMENT 'Pourcentage sur la valeur déclarée',
    valid_from      DATE            NULL,
    valid_until     DATE            NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order      INT             NOT NULL DEFAULT 100,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_parcel_tariffs_type (parcel_type),
    INDEX idx_parcel_tariffs_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parcels (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parcel_number          VARCHAR(30)     NOT NULL COMMENT 'Numéro unique : PCL-YYYYMM-XXXXXX',
    qr_token               VARCHAR(64)     NOT NULL COMMENT 'Token unique pour QR / scan',

    -- Affectation au voyage (peut rester null tant que pas chargé)
    trip_id                BIGINT UNSIGNED NULL,
    origin_agency_id       BIGINT UNSIGNED NOT NULL,
    destination_agency_id  BIGINT UNSIGNED NOT NULL,

    -- Expéditeur
    sender_name            VARCHAR(120)    NOT NULL,
    sender_phone           VARCHAR(30)     NOT NULL,
    sender_id_doc          VARCHAR(50)     NULL,
    sender_address         VARCHAR(200)    NULL,

    -- Destinataire
    recipient_name         VARCHAR(120)    NOT NULL,
    recipient_phone        VARCHAR(30)     NOT NULL,
    recipient_id_doc       VARCHAR(50)     NULL,
    recipient_address      VARCHAR(200)    NULL,

    -- Caractéristiques
    parcel_type            ENUM('document','colis','fragile','special') NOT NULL DEFAULT 'colis',
    description            VARCHAR(500)    NOT NULL,
    weight_kg              DECIMAL(8,2)    NOT NULL,
    volume_m3              DECIMAL(8,3)    NULL,
    declared_value_fcfa    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    pieces_count           INT UNSIGNED    NOT NULL DEFAULT 1,

    -- Tarification
    parcel_tariff_id       BIGINT UNSIGNED NULL,
    base_price_fcfa        INT UNSIGNED    NOT NULL DEFAULT 0,
    insurance_fee_fcfa     INT UNSIGNED    NOT NULL DEFAULT 0,
    tax_amount_fcfa        INT UNSIGNED    NOT NULL DEFAULT 0,
    total_price_fcfa       INT UNSIGNED    NOT NULL DEFAULT 0,
    payment_method         ENUM('especes','mobile_money','carte','virement','a_destination') NOT NULL DEFAULT 'especes',
    paid_at_origin         TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '0 = paiement à destination',

    -- Workflow
    status                 ENUM('depose','en_transit','arrive','retire','perdu','endommage','retourne') NOT NULL DEFAULT 'depose',

    -- Dépôt
    deposited_at           DATETIME        NOT NULL,
    deposited_by           BIGINT UNSIGNED NOT NULL COMMENT 'user_id',
    cash_register_id       BIGINT UNSIGNED NULL,

    -- Retrait
    picked_up_at           DATETIME        NULL,
    picked_up_by           BIGINT UNSIGNED NULL COMMENT 'user_id qui remet',
    pickup_recipient_name  VARCHAR(120)    NULL COMMENT 'Personne ayant retiré (si différent)',
    pickup_id_doc          VARCHAR(50)     NULL,
    pickup_signature_path  VARCHAR(255)    NULL COMMENT 'Image de signature ou photo CNI',
    pickup_notes           TEXT            NULL,

    -- Métadonnées
    notes                  TEXT            NULL,
    cancelled_at           DATETIME        NULL,
    cancelled_by           BIGINT UNSIGNED NULL,
    cancel_reason          VARCHAR(255)    NULL,
    created_at             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at             TIMESTAMP       NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_parcel_number (parcel_number),
    UNIQUE KEY uk_parcel_qr_token (qr_token),
    INDEX idx_parcels_trip (trip_id),
    INDEX idx_parcels_status (status),
    INDEX idx_parcels_dest_agency (destination_agency_id),
    INDEX idx_parcels_origin_agency (origin_agency_id),
    INDEX idx_parcels_recipient_phone (recipient_phone),
    INDEX idx_parcels_sender_phone (sender_phone),
    INDEX idx_parcels_deposited_at (deposited_at),
    CONSTRAINT fk_parcels_trip          FOREIGN KEY (trip_id)               REFERENCES trips(id)            ON DELETE SET NULL,
    CONSTRAINT fk_parcels_origin        FOREIGN KEY (origin_agency_id)      REFERENCES agencies(id),
    CONSTRAINT fk_parcels_destination   FOREIGN KEY (destination_agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_parcels_tariff        FOREIGN KEY (parcel_tariff_id)      REFERENCES parcel_tariffs(id)   ON DELETE SET NULL,
    CONSTRAINT fk_parcels_deposited_by  FOREIGN KEY (deposited_by)          REFERENCES users(id),
    CONSTRAINT fk_parcels_picked_up_by  FOREIGN KEY (picked_up_by)          REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_parcels_cancelled_by  FOREIGN KEY (cancelled_by)          REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal de suivi (timeline)
CREATE TABLE IF NOT EXISTS parcel_tracking_events (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parcel_id    BIGINT UNSIGNED NOT NULL,
    event_type   ENUM('depose','charge','depart','arrivee_etape','arrivee_destination','retrait','litige','retour','annule','message') NOT NULL,
    description  VARCHAR(255)    NULL,
    location     VARCHAR(150)    NULL,
    actor_id     BIGINT UNSIGNED NULL,
    occurred_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pte_parcel (parcel_id),
    INDEX idx_pte_occurred (occurred_at),
    CONSTRAINT fk_pte_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE,
    CONSTRAINT fk_pte_actor  FOREIGN KEY (actor_id)  REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions cargo
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('cargo.view', 'cargo', 'view', 'Voir le cargo', 110),
    ('cargo.create', 'cargo', 'create', 'Déposer un colis', 111),
    ('cargo.edit', 'cargo', 'edit', 'Modifier un colis', 112),
    ('cargo.delete', 'cargo', 'delete', 'Annuler un colis', 113),
    ('cargo.pickup', 'cargo', 'pickup', 'Remettre un colis', 114),
    ('cargo.tariffs', 'cargo', 'tariffs', 'Gérer les tarifs cargo', 115),
    ('cargo.export', 'cargo', 'export', 'Exporter cargo', 116)
ON DUPLICATE KEY UPDATE slug = slug;

-- Donner ces permissions au rôle admin et raf
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'cargo.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Settings cargo
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
('cargo.enabled',                'cargo', 'boolean', '1',   'Module Cargo activé', 'Active la prise en charge des colis et du fret.', 10, 0),
('cargo.numbering_format',       'cargo', 'string',  'PCL-{YYYYMM}-{seq:06d}', 'Format numérotation', 'Format du numéro de colis. Variables: {YYYY}, {YYYYMM}, {seq:06d}.', 11, 0),
('cargo.default_pickup_days',    'cargo', 'integer', '7',   'Délai retrait (jours)', 'Nombre de jours après arrivée au-delà duquel un colis non retiré est marqué pour relance.', 12, 0),
('cargo.notify_recipient_sms',   'cargo', 'boolean', '1',   'SMS au destinataire', 'Envoyer un SMS au destinataire à l''arrivée du colis.', 13, 0),
('cargo.notify_recipient_at_deposit', 'cargo', 'boolean', '1', 'SMS au dépôt', 'Envoyer un SMS au destinataire dès le dépôt.', 14, 0),
('cargo.tax_rate_percent',       'cargo', 'decimal', '0',   'TVA cargo (%)',     'Taux de TVA applicable aux envois cargo.', 15, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Tarifs par défaut (à ajuster ensuite par l'opérateur)
INSERT INTO parcel_tariffs (label, parcel_type, weight_min_kg, weight_max_kg, fixed_fee_fcfa, price_per_kg, insurance_pct, sort_order) VALUES
    ('Document jusqu''à 2 kg',   'document', 0,    2,   2000,    0,  0.5, 10),
    ('Petit colis 0-5 kg',       'colis',    0,    5,   3000,  500,  0.5, 20),
    ('Colis standard 5-20 kg',   'colis',    5,   20,   2500,  400,  0.5, 30),
    ('Gros colis 20-50 kg',      'colis',   20,   50,   2000,  350,  0.5, 40),
    ('Volumineux > 50 kg',       'colis',   50, NULL,   2000,  300,  0.5, 50),
    ('Fragile (à manipuler)',    'fragile',  0, NULL,   5000,  600,  1.0, 60),
    ('Spécial (sur devis)',      'special',  0, NULL,  10000,  800,  2.0, 70)
ON DUPLICATE KEY UPDATE label = label;
