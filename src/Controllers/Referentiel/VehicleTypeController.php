<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

/**
 * CRUD pour les types de véhicules (table vehicle_types).
 */
final class VehicleTypeController extends Controller
{
    public function index(Request $request): void
    {
        $types = Database::select(
            "SELECT vt.*,
                    (SELECT COUNT(*) FROM buses b WHERE b.vehicle_type_id = vt.id) AS vehicles_count
             FROM vehicle_types vt
             ORDER BY vt.sort_order, vt.label"
        );

        $this->view('referentiel/vehicle-types/index', [
            'title' => 'Types de véhicules',
            'types' => $types,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('referentiel/vehicle-types/form', [
            'title' => 'Nouveau type de véhicule',
            'type'  => null,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validateData($request);
        if (!$data) return;

        $exists = Database::selectOne("SELECT id FROM vehicle_types WHERE code = ?", [$data['code']]);
        if ($exists) {
            $this->flash('danger', "Le code « {$data['code']} » existe déjà.");
            back();
            return;
        }

        $id = (int)Database::insert(
            "INSERT INTO vehicle_types (code, label, description, icon, is_active, sort_order)
             VALUES (?,?,?,?,?,?)",
            [$data['code'], $data['label'], $data['description'], $data['icon'],
             $data['is_active'], $data['sort_order']]
        );
        AuditLog::record('vehicle_type.create', 'vehicle_type', $id, ['code' => $data['code']]);
        $this->flash('success', "Type « {$data['label']} » créé.");
        redirect('referentiel/vehicle-types');
    }

    public function edit(Request $request, string $id): void
    {
        $type = Database::selectOne("SELECT * FROM vehicle_types WHERE id = ?", [(int)$id]);
        if (!$type) {
            $this->flash('danger', 'Type introuvable.');
            redirect('referentiel/vehicle-types');
            return;
        }

        $this->view('referentiel/vehicle-types/form', [
            'title' => 'Modifier le type',
            'type'  => $type,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $type = Database::selectOne("SELECT * FROM vehicle_types WHERE id = ?", [(int)$id]);
        if (!$type) {
            $this->flash('danger', 'Type introuvable.');
            redirect('referentiel/vehicle-types');
            return;
        }

        $data = $this->validateData($request, (int)$id);
        if (!$data) return;

        $exists = Database::selectOne(
            "SELECT id FROM vehicle_types WHERE code = ? AND id != ?",
            [$data['code'], (int)$id]
        );
        if ($exists) {
            $this->flash('danger', "Le code « {$data['code']} » est déjà utilisé.");
            back();
            return;
        }

        Database::execute(
            "UPDATE vehicle_types
             SET code=?, label=?, description=?, icon=?, is_active=?, sort_order=?, updated_at=NOW()
             WHERE id=?",
            [$data['code'], $data['label'], $data['description'], $data['icon'],
             $data['is_active'], $data['sort_order'], (int)$id]
        );
        AuditLog::record('vehicle_type.update', 'vehicle_type', (int)$id, ['code' => $data['code']]);
        $this->flash('success', "Type « {$data['label']} » mis à jour.");
        redirect('referentiel/vehicle-types');
    }

    public function destroy(Request $request, string $id): void
    {
        $count = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM buses WHERE vehicle_type_id = ?",
            [(int)$id]
        )['n'] ?? 0);

        if ($count > 0) {
            $this->flash('danger', "Impossible de supprimer : {$count} véhicule(s) utilisent ce type.");
            back();
            return;
        }

        Database::execute("DELETE FROM vehicle_types WHERE id = ?", [(int)$id]);
        AuditLog::record('vehicle_type.delete', 'vehicle_type', (int)$id);
        $this->flash('success', 'Type de véhicule supprimé.');
        redirect('referentiel/vehicle-types');
    }

    /** Validation commune. */
    private function validateData(Request $request, ?int $excludeId = null): ?array
    {
        $code        = trim((string)$request->input('code'));
        $label       = trim((string)$request->input('label'));
        $description = trim((string)$request->input('description'));
        $icon        = trim((string)$request->input('icon')) ?: 'truck';
        $isActive    = $request->input('is_active') ? 1 : 0;
        $sortOrder   = max(0, (int)$request->input('sort_order', 0));

        if ($code === '' || $label === '') {
            $this->flash('danger', 'Le code et le libellé sont obligatoires.');
            back();
            return null;
        }

        // Slug-ify le code
        $code = preg_replace('/[^a-z0-9_]/', '_', strtolower($code));

        return [
            'code'        => $code,
            'label'       => $label,
            'description' => $description ?: null,
            'icon'        => $icon,
            'is_active'   => $isActive,
            'sort_order'  => $sortOrder,
        ];
    }
}
