<?php

declare(strict_types=1);

namespace CityBus\Controllers\Admin;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\Permission;
use CityBus\Models\Role;

final class RoleController extends Controller
{
    public function index(Request $request): void
    {
        $roles = Database::select(
            "SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS users_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perms_count
             FROM roles r ORDER BY r.sort_order, r.label"
        );
        $this->view('admin/roles/index', ['title' => 'Rôles', 'roles' => $roles]);
    }

    public function create(Request $request): void
    {
        $this->view('admin/roles/form', [
            'title'         => 'Nouveau rôle',
            'role'          => null,
            'permsByModule' => Permission::allByModule(),
            'rolePerms'     => [],
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request, [
            'slug'  => 'required|max:50',
            'label' => 'required|max:120',
        ]);
        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$data['slug']));
        if (Role::findBySlug($slug)) {
            $this->flash('danger', 'Ce slug existe déjà.'); back();
        }
        $id = Database::insert(
            "INSERT INTO roles (slug, label, description, sort_order) VALUES (?, ?, ?, ?)",
            [$slug, trim((string)$data['label']), trim((string)$request->input('description', '')) ?: null, (int)$request->input('sort_order', 100)]
        );
        $perms = (array)$request->input('permissions', []);
        Role::syncPermissions((int)$id, $perms);
        Auth::flushCache();
        $this->flash('success', 'Rôle créé.');
        redirect('admin/roles');
    }

    public function edit(Request $request, string $id): void
    {
        $role = Database::selectOne("SELECT * FROM roles WHERE id = ?", [(int)$id]);
        if (!$role) { $this->flash('danger', 'Rôle introuvable.'); redirect('admin/roles'); }
        $rolePerms = Database::select(
            "SELECT permission_id FROM role_permissions WHERE role_id = ?", [(int)$id]
        );
        $this->view('admin/roles/form', [
            'title'         => 'Modifier rôle',
            'role'          => $role,
            'permsByModule' => Permission::allByModule(),
            'rolePerms'     => array_column($rolePerms, 'permission_id'),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $role = Database::selectOne("SELECT * FROM roles WHERE id = ?", [(int)$id]);
        if (!$role) { $this->flash('danger', 'Rôle introuvable.'); redirect('admin/roles'); }

        $data = $this->validate($request, [
            'label' => 'required|max:120',
        ]);

        // slug est non modifiable pour un rôle système (admin)
        Database::execute(
            "UPDATE roles SET label=?, description=?, sort_order=? WHERE id=?",
            [
                trim((string)$data['label']),
                trim((string)$request->input('description', '')) ?: null,
                (int)$request->input('sort_order', 100),
                (int)$id,
            ]
        );
        $perms = (array)$request->input('permissions', []);
        Role::syncPermissions((int)$id, $perms);
        Auth::flushCache();
        $this->flash('success', 'Rôle mis à jour.');
        redirect('admin/roles');
    }

    public function destroy(Request $request, string $id): void
    {
        $role = Database::selectOne("SELECT * FROM roles WHERE id = ?", [(int)$id]);
        if (!$role) { $this->flash('danger', 'Rôle introuvable.'); redirect('admin/roles'); }
        if ((int)$role['is_system'] === 1) {
            $this->flash('danger', 'Impossible de supprimer un rôle système.');
            redirect('admin/roles');
        }
        $used = Database::selectOne("SELECT COUNT(*) AS n FROM users WHERE role_id = ? AND deleted_at IS NULL", [(int)$id]);
        if ((int)($used['n'] ?? 0) > 0) {
            $this->flash('danger', 'Ce rôle est attribué à des utilisateurs : réaffectez-les d\'abord.');
            redirect('admin/roles');
        }
        Database::execute("DELETE FROM roles WHERE id = ?", [(int)$id]);
        Auth::flushCache();
        $this->flash('success', 'Rôle supprimé.');
        redirect('admin/roles');
    }
}
