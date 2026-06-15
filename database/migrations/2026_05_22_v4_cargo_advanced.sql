-- =============================================================
-- V4.G — Cargo full-featured : routage multi-segments, events, POD, COD
-- =============================================================

ALTER TABLE parcels
    ADD COLUMN IF NOT EXISTS sender_customer_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS recipient_customer_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS package_priority ENUM('standard','express','overnight') NOT NULL DEFAULT 'standard',
    ADD COLUMN IF NOT EXISTS cod_amount_fcfa INT NOT NULL DEFAULT 0 COMMENT 'cash on delivery',
    ADD COLUMN IF NOT EXISTS cod_collected_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS cod_collected_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS pod_signature_data MEDIUMTEXT NULL COMMENT 'Base64 signature tactile',
    ADD COLUMN IF NOT EXISTS pod_photo_path VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS pod_recipient_name VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS pod_recipient_id_doc VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS routed_via_segments JSON NULL COMMENT 'Liste de trip_id si multi-segment';

CREATE TABLE IF NOT EXISTS parcel_events (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_id       BIGINT UNSIGNED NOT NULL,
    event_type      ENUM('registered','picked_up','loaded','in_transit','arrived','transferred','out_for_delivery','delivered','returned','lost','damaged','held','customs') NOT NULL,
    occurred_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location        VARCHAR(120) NULL,
    trip_id         BIGINT UNSIGNED NULL,
    actor_id        BIGINT UNSIGNED NULL,
    notes           TEXT NULL,
    proof_photo     VARCHAR(255) NULL,
    INDEX idx_pe_parcel (parcel_id, occurred_at),
    CONSTRAINT fk_pe_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parcel_routes (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_id           BIGINT UNSIGNED NOT NULL,
    sequence            TINYINT NOT NULL,
    trip_id             BIGINT UNSIGNED NOT NULL,
    boarding_stop_id    BIGINT UNSIGNED NULL,
    alighting_stop_id   BIGINT UNSIGNED NULL,
    status              ENUM('planned','loaded','in_transit','unloaded','transferred','delivered','skipped') NOT NULL DEFAULT 'planned',
    loaded_at           TIMESTAMP NULL,
    unloaded_at         TIMESTAMP NULL,
    UNIQUE KEY uk_pr_seq (parcel_id, sequence),
    CONSTRAINT fk_pr_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_trip   FOREIGN KEY (trip_id)   REFERENCES trips(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('cargo.scan',          'cargo', 'scan',     'Scanner colis (QR)', 750),
  ('cargo.deliver',       'cargo', 'deliver',  'Remettre colis (POD)', 751),
  ('cargo.cod.collect',   'cargo', 'cod',      'Collecter COD', 752),
  ('cargo.events.view',   'cargo', 'events',   'Voir événements', 753),
  ('cargo.public.tracking','cargo','pub_track','Tracking public colis', 754)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order, is_secret) VALUES
  ('cargo.public_tracking_enabled','cargo','boolean','1','Tracking public activé','', 1000, 0),
  ('cargo.qr_label_format',         'cargo','string','GS1-128','Format étiquette QR','', 1001, 0),
  ('cargo.cod_max_fcfa',            'cargo','integer','500000','Montant COD max','', 1002, 0),
  ('cargo.insurance_pct',           'cargo','decimal','1.5','Assurance % valeur déclarée','', 1003, 0),
  ('cargo.notify_sender',           'cargo','boolean','1','Notifier expéditeur événements','', 1004, 0),
  ('cargo.notify_recipient',        'cargo','boolean','1','Notifier destinataire événements','', 1005, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
