<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

/**
 * Modèle Media — système polymorphique réutilisable.
 * Attachable à n'importe quel modèle via mediable_type + mediable_id.
 */
final class Media extends BaseModel
{
    protected static string $table = 'media';

    /** Icônes Lucide selon le MIME */
    private const MIME_ICONS = [
        'application/pdf'  => 'file-text',
        'application/msword' => 'file-text',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'file-text',
        'application/vnd.ms-excel' => 'file-spreadsheet',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'file-spreadsheet',
    ];

    /** Retourne tous les médias d'un modèle dans une collection donnée. */
    public static function forModel(string $mediableType, int $mediableId, ?string $collection = null): array
    {
        $sql = "SELECT m.*, u.first_name AS uploader_first, u.last_name AS uploader_last
                FROM media m
                LEFT JOIN users u ON u.id = m.uploaded_by
                WHERE m.mediable_type = ? AND m.mediable_id = ?";
        $params = [$mediableType, $mediableId];

        if ($collection !== null) {
            $sql    .= " AND m.collection = ?";
            $params[] = $collection;
        }

        $sql .= " ORDER BY m.sort_order ASC, m.id ASC";

        return Database::select($sql, $params);
    }

    /** Vrai si le media est une image. */
    public static function isImage(array $row): bool
    {
        return str_starts_with($row['mime_type'] ?? '', 'image/');
    }

    /** URL publique pour accéder au fichier (via MediaController). */
    public static function getUrl(array $row): string
    {
        return url('media/' . $row['id'] . '/file');
    }

    /** URL publique de la miniature (fallback vers l'original si pas d'image). */
    public static function getThumbUrl(array $row): string
    {
        if (!self::isImage($row)) {
            return url('media/' . $row['id'] . '/file');
        }
        return url('media/' . $row['id'] . '/thumb');
    }

    /** Icône Lucide selon le MIME. */
    public static function getIcon(array $row): string
    {
        $mime = $row['mime_type'] ?? '';
        if (str_starts_with($mime, 'image/')) return 'image';
        return self::MIME_ICONS[$mime] ?? 'file';
    }

    /** Taille humaine. */
    public static function humanSize(array $row): string
    {
        $size = (int)($row['size'] ?? 0);
        if ($size < 1024) return $size . ' o';
        if ($size < 1024 * 1024) return round($size / 1024, 1) . ' Ko';
        return round($size / 1024 / 1024, 1) . ' Mo';
    }

    /** Retourne le premier média (image de couverture) d'un modèle. */
    public static function cover(string $mediableType, int $mediableId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM media WHERE mediable_type=? AND mediable_id=? AND collection='gallery' AND mime_type LIKE 'image/%' ORDER BY sort_order ASC, id ASC LIMIT 1",
            [$mediableType, $mediableId]
        );
    }
}
