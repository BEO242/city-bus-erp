<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

/**
 * Modèle pour les notes / observations horodatées, liées à un bus ou un chauffeur.
 * Les notes sont soft-deletées (deleted_at).
 */
final class Note
{
    /**
     * Retourne toutes les notes actives d'une entité, triées par date DESC.
     *
     * @param string $entityType  'buses' | 'drivers'
     * @param int    $entityId    ID de l'entité
     */
    public static function forEntity(string $entityType, int $entityId): array
    {
        return Database::select(
            "SELECT n.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS author_name,
                    u.role AS author_role
             FROM entity_notes n
             LEFT JOIN users u ON u.id = n.author_id
             WHERE n.entity_type = ? AND n.entity_id = ? AND n.deleted_at IS NULL
             ORDER BY n.created_at DESC",
            [$entityType, $entityId]
        );
    }

    /**
     * Ajoute une note et retourne son ID.
     */
    public static function add(string $entityType, int $entityId, string $content, int $authorId): int
    {
        return Database::insert(
            "INSERT INTO entity_notes (entity_type, entity_id, content, author_id, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$entityType, $entityId, $content, $authorId]
        );
    }

    /**
     * Supprime (soft-delete) une note.
     * Un admin peut supprimer n'importe quelle note ; sinon uniquement la sienne.
     */
    public static function softDelete(int $id, int $authorId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            return Database::execute(
                "UPDATE entity_notes SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL",
                [$id]
            ) > 0;
        }

        return Database::execute(
            "UPDATE entity_notes SET deleted_at = NOW()
             WHERE id = ? AND author_id = ? AND deleted_at IS NULL",
            [$id, $authorId]
        ) > 0;
    }
}
