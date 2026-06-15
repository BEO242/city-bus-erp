<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Request;
use CityBus\Models\Note;

/**
 * Gère l'ajout et la suppression de notes horodatées
 * pour les bus et les chauffeurs.
 */
final class NoteController extends Controller
{
    // ─── Routes bus ──────────────────────────────────────────────────────────────

    /** POST /referentiel/vehicules/{id}/notes */
    public function storeBus(Request $request, string $id): void
    {
        $this->doStore('buses', $id, $request);
    }

    /** POST /referentiel/vehicules/{id}/notes/{noteId}/delete */
    public function destroyBus(Request $request, string $id, string $noteId): void
    {
        $this->doDestroy('buses', $id, $noteId, $request);
    }

    // ─── Routes chauffeur ────────────────────────────────────────────────────────

    /** POST /referentiel/drivers/{id}/notes */
    public function storeDriver(Request $request, string $id): void
    {
        $this->doStore('drivers', $id, $request);
    }

    /** POST /referentiel/drivers/{id}/notes/{noteId}/delete */
    public function destroyDriver(Request $request, string $id, string $noteId): void
    {
        $this->doDestroy('drivers', $id, $noteId, $request);
    }

    // ─── Routes ligne ────────────────────────────────────────────────────────────

    /** POST /referentiel/lines/{id}/notes */
    public function storeLine(Request $request, string $id): void
    {
        $this->doStore('lines', $id, $request);
    }

    /** POST /referentiel/lines/{id}/notes/{noteId}/delete */
    public function destroyLine(Request $request, string $id, string $noteId): void
    {
        $this->doDestroy('lines', $id, $noteId, $request);
    }

    // ─── Méthodes internes ────────────────────────────────────────────────────────

    private function doStore(string $type, string $entityId, Request $request): void
    {
        $content = trim((string)$request->input('content', ''));

        if ($content === '') {
            $this->flash('danger', 'La note ne peut pas être vide.');
            redirect("referentiel/{$type}/{$entityId}");
            return;
        }

        if (mb_strlen($content) > 2000) {
            $this->flash('danger', 'La note ne doit pas dépasser 2 000 caractères.');
            redirect("referentiel/{$type}/{$entityId}");
            return;
        }

        $user = auth();
        Note::add($type, (int)$entityId, $content, (int)($user['id'] ?? 0));

        $this->flash('success', 'Note enregistrée.');
        redirect("referentiel/{$type}/{$entityId}#notes");
    }

    private function doDestroy(string $type, string $entityId, string $noteId, Request $request): void
    {
        $user    = auth();
        $userId  = (int)($user['id'] ?? 0);
        $role    = $user['role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'superadmin'], true);

        Note::softDelete((int)$noteId, $userId, $isAdmin);

        $this->flash('success', 'Note supprimée.');
        redirect("referentiel/{$type}/{$entityId}#notes");
    }
}
