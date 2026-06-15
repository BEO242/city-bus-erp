<?php

declare(strict_types=1);

namespace CityBus\Controllers\Media;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Models\Media;
use CityBus\Services\MediaService;

/**
 * Contrôleur AJAX pour le système media réutilisable.
 * Toutes les réponses sont en JSON sauf serve() et thumb() qui retournent le fichier brut.
 */
final class MediaController extends Controller
{
    /**
     * Whitelist des entit\u00e9s autoris\u00e9es \u00e0 recevoir un m\u00e9dia, mapp\u00e9es vers
     * la permission requise pour pouvoir uploader/modifier/supprimer.
     */
    private const MEDIABLE_PERMISSIONS = [
        'buses'           => 'referentiel.edit',
        'lines'           => 'referentiel.edit',
        'bus_lines'       => 'referentiel.edit',
        'agencies'        => 'referentiel.edit',
        'drivers'         => 'referentiel.edit',
        'tariffs'         => 'referentiel.edit',
        'baggage_tariffs' => 'referentiel.edit',
        'employees'       => 'rh.edit',
        'maintenance_orders' => 'flotte.maintenance.edit',
    ];

    private MediaService $service;

    public function __construct()
    {
        $this->service = new MediaService();
    }

    /** V\u00e9rifie que l'utilisateur a le droit de modifier un media donn\u00e9. */
    private function authorizeMediable(string $mediableType): void
    {
        $perm = self::MEDIABLE_PERMISSIONS[$mediableType] ?? null;
        if ($perm === null) {
            Response::json(['error' => 'Type de ressource non autoris\u00e9.'], 403);
        }
        if (!Auth::can($perm)) {
            Response::json(['error' => 'Permission refus\u00e9e.'], 403);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Servir les fichiers
    // ──────────────────────────────────────────────────────────────────────────

    /** Sert le fichier original d'un media (lecture sécurisée via PHP). */
    public function serve(Request $request, string $id): void
    {
        $row = Media::findOrFail((int)$id);
        $absPath = BASE_PATH . '/storage/media/' . $row['file_path'];

        if (!is_file($absPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Fichier introuvable.']);
            exit;
        }

        $disposition = Media::isImage($row) ? 'inline' : 'attachment';
        header('Content-Type: ' . $row['mime_type']);
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($row['file_name']) . '"');
        header('Content-Length: ' . filesize($absPath));
        header('Cache-Control: private, max-age=86400');
        readfile($absPath);
        exit;
    }

    /** Sert la miniature d'un media image (fallback vers l'original). */
    public function thumb(Request $request, string $id): void
    {
        $row = Media::findOrFail((int)$id);

        if (!Media::isImage($row)) {
            // Pour les documents, retourner une icône ou erreur 404
            http_response_code(404);
            exit;
        }

        $dir       = dirname($row['file_path']);
        $base      = basename($row['file_path']);
        $thumbRel  = $dir . '/thumbs/' . $base;
        $thumbAbs  = BASE_PATH . '/storage/media/' . $thumbRel;

        $servePath = is_file($thumbAbs) ? $thumbAbs : BASE_PATH . '/storage/media/' . $row['file_path'];

        header('Content-Type: ' . $row['mime_type']);
        header('Content-Disposition: inline; filename="thumb_' . rawurlencode($row['file_name']) . '"');
        header('Content-Length: ' . filesize($servePath));
        header('Cache-Control: private, max-age=86400');
        readfile($servePath);
        exit;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CRUD AJAX
    // ──────────────────────────────────────────────────────────────────────────

    /** Upload d'un fichier — multipart/form-data. */
    public function upload(Request $request): void
    {
        if ($request->method !== 'POST') {
            Response::json(['error' => 'Méthode invalide.'], 405);
        }

        $mediableType = trim($request->input('mediable_type') ?? '');
        $mediableId   = (int)($request->input('mediable_id')   ?? 0);
        $collection   = trim($request->input('collection')     ?? 'gallery');

        if (!$mediableType || $mediableId <= 0) {
            Response::json(['error' => 'Paramètres manquants (mediable_type, mediable_id).'], 422);
        }

        // Contrôle d'autorisation : type whitelégal + permission appropriée
        $this->authorizeMediable($mediableType);

        if (!in_array($collection, ['gallery', 'documents'], true)) {
            Response::json(['error' => 'Collection invalide.'], 422);
        }

        $fileKey  = $collection === 'documents' ? 'document' : 'image';
        $fileData = $_FILES[$fileKey] ?? $_FILES['file'] ?? null;

        if (!$fileData) {
            Response::json(['error' => 'Aucun fichier reçu.'], 422);
        }

        $user = Auth::user();
        $meta = [
            'alt_text' => $request->input('alt_text') ?? '',
            'caption'  => $request->input('caption')  ?? '',
            'crop'     => $request->input('crop')      ?? null,
        ];

        try {
            $row = $this->service->upload(
                $mediableType,
                $mediableId,
                $collection,
                $fileData,
                $meta,
                (int)($user['id'] ?? 0)
            );

            Response::json(['success' => true, 'media' => $row]);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    /** Mise à jour des métadonnées (alt, caption). */
    public function update(Request $request, string $id): void
    {
        if ($request->method !== 'POST') {
            Response::json(['error' => 'Méthode invalide.'], 405);
        }

        $row = Media::find((int)$id);
        if (!$row) {
            Response::json(['error' => 'Media introuvable.'], 404);
        }

        $this->authorizeMediable($row['mediable_type']);

        $this->service->updateMeta(
            (int)$id,
            $request->input('alt_text') ?? '',
            $request->input('caption')  ?? ''
        );

        Response::json(['success' => true]);
    }

    /** Suppression d'un media. */
    public function destroy(Request $request, string $id): void
    {
        if ($request->method !== 'POST') {
            Response::json(['error' => 'Méthode invalide.'], 405);
        }

        $row = Media::find((int)$id);
        if (!$row) {
            Response::json(['error' => 'Media introuvable.'], 404);
        }

        $this->authorizeMediable($row['mediable_type']);

        try {
            $this->service->delete((int)$id);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /** Réordonnancement en masse. */
    public function reorder(Request $request): void
    {
        if ($request->method !== 'POST') {
            Response::json(['error' => 'Méthode invalide.'], 405);
        }

        $ids = $request->input('ids') ?? '';
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        }

        if (empty($ids)) {
            Response::json(['error' => 'IDs manquants.'], 422);
        }

        try {
            $this->service->reorder(array_values($ids));
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
