-- ============================================================================
-- Module Fret unifié — gestion des bagages passagers et colis légers (fret)
-- ============================================================================
-- Ce module unifie la prise en charge du fret en deux catégories :
--   - baggage : bagage passager lié à un voyage et éventuellement un billet
--   - colis   : envoi autonome entre agences (expéditeur → destinataire)
--
-- La table fret_items s'appuie sur fret_categories (déjà existante) pour la
-- tarification par slug. Les prix sont snapshotés à la création de l'item
-- afin de garantir l'intégrité historique même si les tarifs évoluent.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fret_items (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracking_code           VARCHAR(12)     NOT NULL COMMENT 'Format FRT-XXXXXX (alphanumérique aléatoire)',
    item_type               ENUM('baggage','colis') NOT NULL,
    category_slug           VARCHAR(60)     NOT NULL COMMENT 'Référence vers fret_categories.slug',

    -- Liaison voyage / billet (obligatoire pour baggage)
    trip_id                 BIGINT UNSIGNED NULL,
    passenger_ticket_id     BIGINT UNSIGNED NULL COMMENT 'Billet passager associé (baggage)',

    -- Expéditeur
    sender_name             VARCHAR(120)    NOT NULL,
    sender_phone            VARCHAR(30)     NULL,

    -- Destinataire (colis)
    recipient_name          VARCHAR(120)    NULL,
    recipient_phone         VARCHAR(30)     NULL,

    -- Caractéristiques
    weight_kg               DECIMAL(8,2)    NOT NULL DEFAULT 0,
    pieces_count            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    description             TEXT            NULL,
    is_franchise            TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Bagage dans la franchise gratuite',

    -- Tarification (snapshot au moment de la création)
    price_per_kg            INT UNSIGNED    NOT NULL DEFAULT 0,
    min_price_fcfa          INT UNSIGNED    NOT NULL DEFAULT 0,
    total_price_fcfa        INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Total calculé',

    -- Agences
    origin_agency_id        BIGINT UNSIGNED NULL,
    destination_agency_id   BIGINT UNSIGNED NULL,

    -- Workflow
    status                  ENUM('enregistre','charge','en_transit','arrive','retire','annule') NOT NULL DEFAULT 'enregistre',

    -- Talon
    talon_printed           TINYINT(1)      NOT NULL DEFAULT 0,
    talon_printed_at        DATETIME        NULL,

    -- Contexte opérationnel
    agency_id               BIGINT UNSIGNED NULL,
    registered_by           BIGINT UNSIGNED NULL COMMENT 'Utilisateur ayant enregistré',
    cash_register_id        BIGINT UNSIGNED NULL,

    -- Annulation
    cancelled_at            DATETIME        NULL,
    cancelled_by            BIGINT UNSIGNED NULL,
    cancel_reason           VARCHAR(255)    NULL,

    -- Timestamps
    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP       NULL,

    -- Clé primaire
    PRIMARY KEY (id),

    -- Index
    UNIQUE KEY uk_fret_items_tracking_code (tracking_code),
    INDEX idx_fret_items_item_type (item_type),
    INDEX idx_fret_items_trip (trip_id),
    INDEX idx_fret_items_status (status),
    INDEX idx_fret_items_category_slug (category_slug),
    INDEX idx_fret_items_created_at (created_at),
    INDEX idx_fret_items_sender_phone (sender_phone),

    -- Clés étrangères
    CONSTRAINT fk_fret_items_trip              FOREIGN KEY (trip_id)              REFERENCES trips(id)    ON DELETE SET NULL,
    CONSTRAINT fk_fret_items_ticket            FOREIGN KEY (passenger_ticket_id)  REFERENCES tickets(id)  ON DELETE SET NULL,
    CONSTRAINT fk_fret_items_origin_agency     FOREIGN KEY (origin_agency_id)     REFERENCES agencies(id),
    CONSTRAINT fk_fret_items_dest_agency       FOREIGN KEY (destination_agency_id) REFERENCES agencies(id),
    CONSTRAINT fk_fret_items_registered_by     FOREIGN KEY (registered_by)        REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Permissions fret
-- ============================================================================
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
    ('fret.view',        'fret', 'view',        'Voir le fret',             120),
    ('fret.create',      'fret', 'create',      'Enregistrer un fret',      121),
    ('fret.edit',        'fret', 'edit',         'Modifier un fret',         122),
    ('fret.cancel',      'fret', 'cancel',      'Annuler un fret',          123),
    ('fret.print_talon', 'fret', 'print_talon', 'Imprimer le talon fret',   124)
ON DUPLICATE KEY UPDATE slug = slug;

-- Accorder les permissions fret aux rôles admin et raf
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin','raf') AND p.slug LIKE 'fret.%'
ON DUPLICATE KEY UPDATE role_id = role_id;
