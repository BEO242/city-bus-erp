<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\MediaUploader;
use CityBus\Models\Media;

/**
 * Orchestrateur Media : upload, suppression, réordonnancement, mise à jour métadonnées.
 * Utilisable depuis n'importe quel contrôleur.
 */
final class MediaService
{
    /**
     * Upload un fichier et crée l'enregistrement en base.
     *
     * @param  string $mediableType  Ex : 'buses', 'employees'
     * @param  int    $mediableId    ID du modèle associé
     * @param  string $collection   'gallery' ou 'documents'
     * @param  array  $fileInput    Entrée $_FILES['xxx']
     * @param  array  $meta         ['alt_text', 'caption', 'crop'] optionnels
     * @param  int    $uploadedBy   ID utilisateur
     * @return array  La ligne media nouvellement insérée
     */
    public function upload(
        string $mediableType,
        int    $mediableId,
        string $collection,
        array  $fileInput,
        array  $meta = [],
        int    $uploadedBy = 0
    ): array {
        // 1. Valider
        MediaUploader::validate($fileInput, $collection);

        // 2. Déplacer + générer thumb
        $info = MediaUploader::store($fileInput, $mediableType, $mediableId, $collection);

        // 3. Appliquer le recadrage si fourni (données JSON de Cropper.js)
        if (!empty($meta['crop'])) {
            $crop = is_string($meta['crop']) ? json_decode($meta['crop'], true) : $meta['crop'];
            if (is_array($crop) && ($crop['width'] ?? 0) > 0) {
                MediaUploader::applyCrop($info['path'], $crop, $info['mime']);
            }
        }

        // 4. Prochain sort_order
        $maxOrder = Database::selectOne(
            "SELECT MAX(sort_order) AS m FROM media WHERE mediable_type=? AND mediable_id=? AND collection=?",
            [$mediableType, $mediableId, $collection]
        );
        $sortOrder = (int)($maxOrder['m'] ?? 0) + 1;

        // 5. Insérer en base
        $id = Database::insert(
            "INSERT INTO media (mediable_type, mediable_id, collection, file_path, file_name, file_hash, mime_type, size, width, height, alt_text, caption, sort_order, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $mediableType,
                $mediableId,
                $collection,
                $info['path'],
                $info['name'],
                $info['hash'] ?? null,
                $info['mime'],
                $info['size'],
                $info['width']  ?? null,
                $info['height'] ?? null,
                $meta['alt_text'] ?? null,
                $meta['caption']  ?? null,
                $sortOrder,
                $uploadedBy ?: null,
            ]
        );

        $row = Media::find($id);
        $row['url']       = Media::getUrl($row);
        $row['thumb_url'] = Media::getThumbUrl($row);
        $row['icon']      = Media::getIcon($row);
        $row['human_size'] = Media::humanSize($row);

        return $row;
    }

    /**
     * Supprime un media : fichier + miniature + ligne DB.
     */
    public function delete(int $mediaId): void
    {
        $row = Media::find($mediaId);
        if (!$row) return;

        // Supprimer les fichiers physiques
        $absPath = BASE_PATH . '/storage/media/' . $row['file_path'];
        if (is_file($absPath)) @unlink($absPath);

        // Miniature
        $thumbPath = dirname($absPath) . '/thumbs/' . basename($absPath);
        if (is_file($thumbPath)) @unlink($thumbPath);

        Database::execute("DELETE FROM media WHERE id=?", [$mediaId]);
    }

    /**
     * Réordonne les médias.
     * @param int[] $orderedIds  IDs dans le nouvel ordre souhaité
     */
    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $i => $id) {
            Database::execute(
                "UPDATE media SET sort_order=? WHERE id=?",
                [$i + 1, (int)$id]
            );
        }
    }

    /**
     * Met à jour les métadonnées textuelles (alt, caption).
     */
    public function updateMeta(int $mediaId, string $altText, string $caption): void
    {
        Database::execute(
            "UPDATE media SET alt_text=?, caption=? WHERE id=?",
            [$altText ?: null, $caption ?: null, $mediaId]
        );
    }

    /**
     * Enrichit un tableau de médias avec les URLs calculées.
     */
    public static function enrichAll(array $rows): array
    {
        return array_map(fn($r) => array_merge($r, [
            'url'        => Media::getUrl($r),
            'thumb_url'  => Media::getThumbUrl($r),
            'icon'       => Media::getIcon($r),
            'human_size' => Media::humanSize($r),
            'is_image'   => Media::isImage($r),
        ]), $rows);
    }
}
