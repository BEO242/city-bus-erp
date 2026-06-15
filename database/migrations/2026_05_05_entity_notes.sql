-- Migration : table entity_notes (notes horodatées liées à des entités)
-- Colonnes alignées sur src/Models/Note.php : entity_type, entity_id, content, author_id, deleted_at

CREATE TABLE IF NOT EXISTS entity_notes (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(50)     NOT NULL,
    entity_id   BIGINT UNSIGNED NOT NULL,
    content     TEXT            NOT NULL,
    author_id   BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_entity_notes_entity (entity_type, entity_id, deleted_at),
    KEY idx_entity_notes_author (author_id),
    CONSTRAINT fk_entity_notes_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
