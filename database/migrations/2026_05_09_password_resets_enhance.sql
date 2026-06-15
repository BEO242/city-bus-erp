-- Renforce la table password_resets pour le workflow self-service mot-de-passe oublié.
-- - ajout d'un id auto-incrément (la PK email seule empêchait plusieurs demandes simultanées)
-- - ajout d'expires_at (validité courte du lien)
-- - ajout de used_at (jeton à usage unique)
-- - ajout de ip_address (audit)

CREATE TABLE IF NOT EXISTS password_resets (
    email      VARCHAR(120) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Drop PK email s'il existe pour permettre plusieurs lignes par email (purge ensuite)
ALTER TABLE password_resets DROP PRIMARY KEY;

-- Ajout des colonnes manquantes (idempotent côté MySQL : utiliser un script externe pour vérifier)
ALTER TABLE password_resets
    ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
    ADD COLUMN expires_at TIMESTAMP NULL AFTER created_at,
    ADD COLUMN used_at    TIMESTAMP NULL AFTER expires_at,
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER used_at,
    ADD INDEX idx_password_resets_email (email),
    ADD INDEX idx_password_resets_token (token);
