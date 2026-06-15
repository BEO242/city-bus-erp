<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Gestion bas-niveau des uploads de fichiers.
 * Validation MIME, déplacement, génération de miniatures (GD) et recadrage.
 */
final class MediaUploader
{
    // Types MIME autorisés par catégorie
    public const ALLOWED_IMAGES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    ];

    public const ALLOWED_DOCS = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10 Mo
    public const MAX_DOC_SIZE   = 20 * 1024 * 1024; // 20 Mo

    /** Valide un fichier uploadé ($_FILES entry). Lève \RuntimeException si invalide. */
    public static function validate(array $file, string $collection = 'gallery'): void
    {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $msg = self::uploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE);
            throw new \RuntimeException($msg);
        }

        $size = $file['size'] ?? 0;
        $mime = self::detectMime($file['tmp_name']);

        $allowed = $collection === 'documents'
            ? array_merge(self::ALLOWED_IMAGES, self::ALLOWED_DOCS)
            : self::ALLOWED_IMAGES;

        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException(
                "Type de fichier non autorisé : $mime. Autorisés : " . implode(', ', $allowed)
            );
        }

        $maxSize = $collection === 'documents' ? self::MAX_DOC_SIZE : self::MAX_IMAGE_SIZE;
        if ($size > $maxSize) {
            throw new \RuntimeException(
                'Fichier trop volumineux (' . round($size / 1024 / 1024, 1) . ' Mo). Maximum : ' . round($maxSize / 1024 / 1024) . ' Mo'
            );
        }
    }

    /**
     * Déplace le fichier uploadé vers le répertoire de destination.
     * Retourne le chemin relatif (depuis BASE_PATH/storage/media/).
     */
    public static function store(array $file, string $mediableType, int $mediableId, string $collection): array
    {
        $mime      = self::detectMime($file['tmp_name']);
        $ext       = self::mimeToExt($mime);
        $uuid      = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $fileName  = $uuid . '.' . $ext;

        // Ex : buses/6/gallery/
        $relDir   = $mediableType . '/' . $mediableId . '/' . $collection;
        $absDir   = BASE_PATH . '/storage/media/' . $relDir;
        if (!is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }

        $absPath = $absDir . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new \RuntimeException('Impossible de déplacer le fichier uploadé.');
        }

        $relPath = $relDir . '/' . $fileName;
        $info    = ['path' => $relPath, 'name' => $file['name'] ?? $fileName, 'mime' => $mime, 'size' => $file['size'] ?? 0, 'hash' => hash_file('sha256', $absPath)];

        // Dimensions pour les images
        if (str_starts_with($mime, 'image/') && function_exists('getimagesize')) {
            $sz = @getimagesize($absPath);
            if ($sz) {
                $info['width']  = $sz[0];
                $info['height'] = $sz[1];
            }
        }

        // Génération miniature pour les images
        if (str_starts_with($mime, 'image/')) {
            $thumbDir = $absDir . '/thumbs';
            if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
            $thumbPath = $thumbDir . '/' . $fileName;
            self::createThumb($absPath, $mime, $thumbPath, 400, 280);
            $info['thumb_path'] = $relDir . '/thumbs/' . $fileName;
        }

        return $info;
    }

    /**
     * Applique un recadrage (données JSON de Cropper.js) sur une image existante.
     * Remplace le fichier original + régénère la miniature.
     */
    public static function applyCrop(string $relPath, array $crop, string $mime): void
    {
        $absPath = BASE_PATH . '/storage/media/' . $relPath;
        if (!is_file($absPath)) {
            throw new \RuntimeException('Fichier introuvable pour le recadrage.');
        }

        $x  = (int)round((float)($crop['x']  ?? 0));
        $y  = (int)round((float)($crop['y']  ?? 0));
        $w  = (int)round((float)($crop['width']  ?? 0));
        $h  = (int)round((float)($crop['height'] ?? 0));
        $sx = (float)($crop['scaleX'] ?? 1.0);
        $sy = (float)($crop['scaleY'] ?? 1.0);

        if ($w <= 0 || $h <= 0) {
            throw new \RuntimeException('Dimensions de recadrage invalides.');
        }

        $src = self::gdCreate($absPath, $mime);
        if (!$src) {
            throw new \RuntimeException('Impossible de lire l\'image source.');
        }

        // Flippage si scale négatif
        if ($sx < 0 || $sy < 0) {
            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $flipped = imagecreatetruecolor($srcW, $srcH);
            imagealphablending($flipped, false);
            imagesavealpha($flipped, true);
            imagecopyresampled($flipped, $src, 0, 0, $sx < 0 ? $srcW - 1 : 0, $sy < 0 ? $srcH - 1 : 0, $sx < 0 ? -$srcW : $srcW, $sy < 0 ? -$srcH : $srcH, $srcW, $srcH);
            imagedestroy($src);
            $src = $flipped;
        }

        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefill($dst, 0, 0, $transparent);

        imagecopyresampled($dst, $src, 0, 0, $x, $y, $w, $h, $w, $h);
        imagedestroy($src);

        self::gdSave($dst, $absPath, $mime);
        imagedestroy($dst);

        // Regénérer le thumb
        $thumbPath = dirname($absPath) . '/thumbs/' . basename($absPath);
        if (is_dir(dirname($thumbPath))) {
            self::createThumb($absPath, $mime, $thumbPath, 400, 280);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Méthodes privées
    // ──────────────────────────────────────────────────────────────────────────

    /** Génère une miniature redimensionnée (conserve les proportions). */
    private static function createThumb(string $src, string $mime, string $dst, int $maxW, int $maxH): void
    {
        $img = self::gdCreate($src, $mime);
        if (!$img) return;

        $origW = imagesx($img);
        $origH = imagesy($img);

        $ratio  = min($maxW / $origW, $maxH / $origH);
        $newW   = (int)round($origW * $ratio);
        $newH   = (int)round($origH * $ratio);

        $thumb = imagecreatetruecolor($newW, $newH);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $bg = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefill($thumb, 0, 0, $bg);

        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($img);

        self::gdSave($thumb, $dst, $mime);
        imagedestroy($thumb);
    }

    /** Crée une ressource GD depuis un fichier. */
    private static function gdCreate(string $path, string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif'  => @imagecreatefromgif($path),
            default      => false,
        };
    }

    /** Sauvegarde une ressource GD dans un fichier. */
    private static function gdSave(\GdImage $img, string $path, string $mime): void
    {
        match ($mime) {
            'image/jpeg' => imagejpeg($img, $path, 88),
            'image/webp' => imagewebp($img, $path, 88),
            'image/gif'  => imagegif($img, $path),
            default      => imagepng($img, $path, 6),
        };
    }

    /** Détecte le MIME réel depuis le contenu (pas depuis l'extension). */
    private static function detectMime(string $tmpPath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            return $mime ?: 'application/octet-stream';
        }
        // Fallback
        return mime_content_type($tmpPath) ?: 'application/octet-stream';
    }

    /** Mappe un MIME vers une extension de fichier. */
    private static function mimeToExt(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => 'bin',
        };
    }

    /** Message d'erreur upload humain. */
    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée.',
            UPLOAD_ERR_PARTIAL  => 'Le fichier n\'a été que partiellement uploadé.',
            UPLOAD_ERR_NO_FILE  => 'Aucun fichier sélectionné.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque.',
            default => 'Erreur d\'upload inconnue (code ' . $code . ').',
        };
    }
}
