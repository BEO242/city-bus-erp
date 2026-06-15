-- ═══════════════════════════════════════════════════════════════════════════
-- Migration majeure : Évolutions structurelles CityBus ERP
-- Date: 2026-05-26
-- ═══════════════════════════════════════════════════════════════════════════
-- Contenu :
--   1. Lignes urbaines / interurbaines + arrêts liés aux lignes
--   2. Voyages fret-only
--   3. États de paiement (billets + fret)
--   4. Module convoyeurs (table dédiée, séparée des chauffeurs)
--   5. Fret : origines/destinations par arrêt, multi-lignes colis
--   6. Pré-imprimés redesign (billets, talons bagage, talons colis)
--   7. Programme de fidélité simplifié (paramétrable)
--   8. Remboursements (billetterie + fret)
-- ═══════════════════════════════════════════════════════════════════════════

-- ──────────────────────────────────────────────────────────────────────────
-- 1. LIGNES : type urbain / interurbain + agences de départ/arrivée
-- ──────────────────────────────────────────────────────────────────────────

ALTER TABLE bus_lines
  ADD COLUMN IF NOT EXISTS line_type ENUM('interurbain','urbain') NOT NULL DEFAULT 'interurbain' AFTER name,
  ADD COLUMN IF NOT EXISTS city_id BIGINT UNSIGNED NULL AFTER line_type,
  ADD COLUMN IF NOT EXISTS departure_agency_id BIGINT UNSIGNED NULL AFTER arrival_city_id,
  ADD COLUMN IF NOT EXISTS arrival_agency_id BIGINT UNSIGNED NULL AFTER departure_agency_id;

-- Arrêts liés aux lignes (pour lignes urbaines : liste ordonnée)
-- La table stops existe déjà (line_id, agency_id, name, order_position, km_from_origin)
-- On ajoute quelques colonnes utiles
ALTER TABLE stops
  ADD COLUMN IF NOT EXISTS city_id BIGINT UNSIGNED NULL AFTER agency_id,
  ADD COLUMN IF NOT EXISTS is_terminal TINYINT(1) NOT NULL DEFAULT 0 AFTER km_from_origin,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER is_terminal,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude;

-- ──────────────────────────────────────────────────────────────────────────
-- 2. VOYAGES : support fret-only
-- ──────────────────────────────────────────────────────────────────────────

-- trip_type ENUM déjà inclut 'commercial','affretement','interne','formation','test'
-- On ajoute 'fret' pour les voyages spécial fret sans passagers
ALTER TABLE trips MODIFY COLUMN trip_type
  ENUM('commercial','fret','affretement','interne','formation','test') NOT NULL DEFAULT 'commercial';


-- ──────────────────────────────────────────────────────────────────────────
-- 3. ÉTATS DE PAIEMENT — BILLETS
-- ──────────────────────────────────────────────────────────────────────────

-- Ajout d'un état de paiement séparé du statut opérationnel
ALTER TABLE tickets
  ADD COLUMN IF NOT EXISTS payment_status ENUM('en_attente','paye','rembourse_partiel','rembourse') NOT NULL DEFAULT 'paye' AFTER status,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER payment_status,
  ADD COLUMN IF NOT EXISTS paid_amount_fcfa INT UNSIGNED NULL AFTER paid_at,
  ADD COLUMN IF NOT EXISTS refund_amount_fcfa INT UNSIGNED NOT NULL DEFAULT 0 AFTER paid_amount_fcfa,
  ADD COLUMN IF NOT EXISTS refund_reason VARCHAR(300) NULL AFTER refund_amount_fcfa,
  ADD COLUMN IF NOT EXISTS refunded_at DATETIME NULL AFTER refund_reason,
  ADD COLUMN IF NOT EXISTS refunded_by BIGINT UNSIGNED NULL AFTER refunded_at;

-- Pour les billets existants : marquer comme payés (rétro-compatibilité)
UPDATE tickets SET payment_status = 'paye', paid_at = sold_at, paid_amount_fcfa = price_fcfa
  WHERE payment_status = 'paye' AND paid_at IS NULL AND status != 'annule';

-- Billets annulés → marquer la cohérence
UPDATE tickets SET payment_status = 'rembourse', paid_at = sold_at, paid_amount_fcfa = price_fcfa
  WHERE status = 'annule' AND payment_status = 'paye' AND refund_amount_fcfa = 0;


-- ──────────────────────────────────────────────────────────────────────────
-- 4. ÉTATS DE PAIEMENT — FRET
-- ──────────────────────────────────────────────────────────────────────────

ALTER TABLE fret_items
  ADD COLUMN IF NOT EXISTS payment_status ENUM('en_attente','paye','rembourse_partiel','rembourse','non_applicable') NOT NULL DEFAULT 'en_attente' AFTER status,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER payment_status,
  ADD COLUMN IF NOT EXISTS paid_amount_fcfa INT UNSIGNED NULL AFTER paid_at,
  ADD COLUMN IF NOT EXISTS refund_amount_fcfa INT UNSIGNED NOT NULL DEFAULT 0 AFTER paid_amount_fcfa,
  ADD COLUMN IF NOT EXISTS refund_reason VARCHAR(300) NULL AFTER refund_amount_fcfa,
  ADD COLUMN IF NOT EXISTS refunded_at DATETIME NULL AFTER refund_reason,
  ADD COLUMN IF NOT EXISTS refunded_by BIGINT UNSIGNED NULL AFTER refunded_at;

-- Franchise → non_applicable (gratuit), autres existants → payés
UPDATE fret_items SET payment_status = 'non_applicable' WHERE is_franchise = 1;
UPDATE fret_items SET payment_status = 'paye', paid_at = created_at, paid_amount_fcfa = total_price_fcfa
  WHERE is_franchise = 0 AND payment_status = 'en_attente' AND status != 'annule';

-- Origines / destinations par arrêt (en plus des agences)
ALTER TABLE fret_items
  ADD COLUMN IF NOT EXISTS origin_stop_id BIGINT UNSIGNED NULL AFTER origin_agency_id,
  ADD COLUMN IF NOT EXISTS destination_stop_id BIGINT UNSIGNED NULL AFTER destination_agency_id;


-- ──────────────────────────────────────────────────────────────────────────
-- 5. MODULE CONVOYEURS (table dédiée)
-- ──────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS convoyeurs (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  matricule             VARCHAR(20)     NOT NULL UNIQUE,
  first_name            VARCHAR(60)     NOT NULL,
  last_name             VARCHAR(60)     NOT NULL,
  birth_date            DATE            NULL,
  gender                ENUM('M','F')   NULL,
  national_id           VARCHAR(40)     NULL,
  national_id_expiry    DATE            NULL,
  phone                 VARCHAR(20)     NOT NULL,
  phone_alt             VARCHAR(20)     NULL,
  email                 VARCHAR(120)    NULL,
  address               TEXT            NULL,
  city                  VARCHAR(60)     NULL,
  emergency_name        VARCHAR(100)    NULL,
  emergency_phone       VARCHAR(20)     NULL,
  emergency_relation    VARCHAR(50)     NULL,
  hire_date             DATE            NOT NULL,
  agency_id             BIGINT UNSIGNED NULL,
  status                ENUM('actif','conge','suspendu','en_formation','quitte') NOT NULL DEFAULT 'actif',
  salary_base           INT UNSIGNED    NULL DEFAULT 0,
  daily_bonus           INT UNSIGNED    NULL DEFAULT 0,
  bank_name             VARCHAR(80)     NULL,
  bank_account          VARCHAR(60)     NULL,
  mobile_money_number   VARCHAR(20)     NULL,
  rating_score          DECIMAL(3,1)    NOT NULL DEFAULT 5.0,
  total_trips           INT UNSIGNED    NOT NULL DEFAULT 0,
  warnings_count        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  notes                 TEXT            NULL,
  created_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at            TIMESTAMP       NULL,
  INDEX idx_convoyeurs_status (status),
  INDEX idx_convoyeurs_agency (agency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrer convoyeur_id de trips : il pointe actuellement vers drivers.id
-- On garde la colonne pour le moment, mais on ajoute une nouvelle FK
-- ALTER TABLE trips ADD COLUMN IF NOT EXISTS convoyeur_type ENUM('driver','convoyeur') NULL DEFAULT 'driver' AFTER convoyeur_id;
-- Pour l'instant, on réutilise convoyeur_id mais avec une logique applicative


-- ──────────────────────────────────────────────────────────────────────────
-- 6. PRÉ-IMPRIMÉS : redesign
-- ──────────────────────────────────────────────────────────────────────────

-- Pré-imprimés : ajout du type (billet, talon_bagage, talon_colis)
ALTER TABLE pre_printed_tickets
  ADD COLUMN IF NOT EXISTS preprint_type ENUM('billet','talon_bagage','talon_colis') NOT NULL DEFAULT 'billet' AFTER id;

-- Code court alphanumérique 6 chars (lisible, imprimé en gros sur le talon)
ALTER TABLE pre_printed_tickets
  ADD COLUMN IF NOT EXISTS short_code VARCHAR(10) NULL UNIQUE AFTER qr_code_hash;

-- Pour les talons bagage / colis : champs manuscrits (remplis à la main par le convoyeur)
ALTER TABLE pre_printed_tickets
  ADD COLUMN IF NOT EXISTS linked_ticket_code VARCHAR(20) NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS fret_category_slug VARCHAR(60) NULL AFTER linked_ticket_code,
  ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(6,2) NULL AFTER fret_category_slug,
  ADD COLUMN IF NOT EXISTS sender_name VARCHAR(120) NULL AFTER weight_kg,
  ADD COLUMN IF NOT EXISTS sender_phone VARCHAR(30) NULL AFTER sender_name,
  ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(120) NULL AFTER sender_phone,
  ADD COLUMN IF NOT EXISTS recipient_phone VARCHAR(30) NULL AFTER recipient_name,
  ADD COLUMN IF NOT EXISTS fret_item_id BIGINT UNSIGNED NULL AFTER recipient_phone;


-- ──────────────────────────────────────────────────────────────────────────
-- 7. PROGRAMME DE FIDÉLITÉ : paramètres simplifiés
-- ──────────────────────────────────────────────────────────────────────────

-- Table de paramétrage du programme de fidélité
CREATE TABLE IF NOT EXISTS loyalty_program_config (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  is_enabled             TINYINT(1)   NOT NULL DEFAULT 1,
  required_trips         INT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Nombre de voyages pour obtenir l''avantage',
  discount_percent       DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Réduction en % sur le billet',
  enrollment_message     VARCHAR(500) NULL COMMENT 'Message à afficher au convoyeur',
  period_months          INT UNSIGNED NOT NULL DEFAULT 12 COMMENT 'Période de validité en mois (0=illimité)',
  created_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO loyalty_program_config (id, required_trips, discount_percent, enrollment_message, period_months)
VALUES (1, 10, 10.00, 'Proposer au client de rejoindre le programme de fidélité City Bus pour bénéficier de réductions.', 12);

-- Ajout du code client unique + flag fidélité dans customers
ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS customer_code VARCHAR(10) NULL UNIQUE AFTER id,
  ADD COLUMN IF NOT EXISTS is_loyalty_member TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_code,
  ADD COLUMN IF NOT EXISTS loyalty_enrolled_at DATETIME NULL AFTER is_loyalty_member,
  ADD COLUMN IF NOT EXISTS loyalty_enrolled_by BIGINT UNSIGNED NULL AFTER loyalty_enrolled_at;

-- Générer les codes clients pour les clients existants qui n'en ont pas
-- (sera fait par script PHP pour avoir un format propre)


-- ──────────────────────────────────────────────────────────────────────────
-- 8. TABLE DES REMBOURSEMENTS (historique centralisé)
-- ──────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS refunds (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  refund_type         ENUM('ticket','fret','baggage') NOT NULL,
  reference_id        BIGINT UNSIGNED NOT NULL COMMENT 'ID du ticket, fret_item, ou baggage_ticket',
  original_amount_fcfa INT UNSIGNED   NOT NULL,
  refund_amount_fcfa  INT UNSIGNED    NOT NULL,
  refund_percent      DECIMAL(5,2)    NOT NULL DEFAULT 100.00,
  reason              VARCHAR(500)    NOT NULL,
  cash_register_id    BIGINT UNSIGNED NULL,
  agency_id           BIGINT UNSIGNED NULL,
  refunded_by         BIGINT UNSIGNED NOT NULL,
  approved_by         BIGINT UNSIGNED NULL,
  status              ENUM('en_attente','approuve','execute','rejete') NOT NULL DEFAULT 'execute',
  created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  executed_at         DATETIME        NULL,
  INDEX idx_refunds_type_ref (refund_type, reference_id),
  INDEX idx_refunds_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ──────────────────────────────────────────────────────────────────────────
-- INDEX complémentaires
-- ──────────────────────────────────────────────────────────────────────────

ALTER TABLE bus_lines ADD INDEX IF NOT EXISTS idx_lines_type (line_type);
ALTER TABLE tickets ADD INDEX IF NOT EXISTS idx_tickets_payment (payment_status);
ALTER TABLE fret_items ADD INDEX IF NOT EXISTS idx_fret_payment (payment_status);
