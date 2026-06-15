<?php

declare(strict_types=1);

namespace CityBus\Controllers\Admin;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\PasswordPolicy;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;
use CityBus\Models\Permission;

final class UserController extends Controller
{
    public function index(Request $request): void
    {
        $q       = trim((string)$request->input('q', ''));
        $roleF   = trim((string)$request->input('role', ''));
        $status  = trim((string)$request->input('status', '')); // active|inactive|locked
        $page    = max(1, (int)$request->input('page', 1));
        $perPage = 25;

        $base = "FROM users u
                 LEFT JOIN roles r    ON r.id = u.role_id
                 LEFT JOIN agencies a ON a.id = u.agency_id
                 WHERE u.deleted_at IS NULL";
        $params = [];
        if ($q !== '') {
            $base .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $like = "%{$q}%";
            array_push($params, $like, $like, $like);
        }
        if ($roleF !== '') {
            $base .= " AND r.slug = ?"; $params[] = $roleF;
        }
        if ($status === 'active')   $base .= " AND u.is_active = 1 AND (u.locked_until IS NULL OR u.locked_until <= NOW())";
        if ($status === 'inactive') $base .= " AND u.is_active = 0";
        if ($status === 'locked')   $base .= " AND u.locked_until IS NOT NULL AND u.locked_until > NOW()";

        $total    = (int)(Database::selectOne("SELECT COUNT(*) AS c $base", $params)['c'] ?? 0);
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $offset   = ($page - 1) * $perPage;

        $sql = "SELECT u.*, r.label AS role_label, r.slug AS role_slug, a.name AS agency_name
                $base
                ORDER BY u.last_name, u.first_name
                LIMIT $perPage OFFSET $offset";

        $users = Database::select($sql, $params);
        $roles = Database::select("SELECT * FROM roles ORDER BY sort_order, label");

        $this->view('admin/users/index', [
            'title'   => 'Utilisateurs',
            'users'   => $users,
            'roles'   => $roles,
            'q'       => $q,
            'roleF'   => $roleF,
            'status'  => $status,
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $total,
            'lastPage'=> $lastPage,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('admin/users/form', [
            'title'    => 'Nouvel utilisateur',
            'user'     => null,
            'roles'    => Database::select("SELECT * FROM roles ORDER BY sort_order, label"),
            'agencies' => Database::select("SELECT a.*, c.slug AS city, c.name AS city_name FROM agencies a LEFT JOIN cities c ON c.id=a.city_id WHERE a.is_active=1 ORDER BY a.name"),
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request, [
            'first_name' => 'required|max:50',
            'last_name'  => 'required|max:50',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'max:20',
            'role_id'    => 'required|integer',
            'agency_id'  => 'integer',
            'password'   => 'required',
        ]);

        $errors = PasswordPolicy::validate((string)$data['password']);
        if (!empty($errors)) {
            \CityBus\Core\Session::set('_errors', ['password' => $errors]);
            \CityBus\Core\Session::set('_old', $request->all());
            $this->flash('danger', implode(' ', $errors));
            back();
        }

        $role = Database::selectOne("SELECT slug FROM roles WHERE id = ?", [(int)$data['role_id']]);
        if (!$role) { $this->flash('danger', 'Rôle invalide.'); back(); }

        $userId = (int)Database::insert(
            "INSERT INTO users (first_name, last_name, email, phone, role, role_id, agency_id, password_hash, password_changed_at, password_expires_at, is_active, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 1, ?)",
            [
                trim((string)$data['first_name']),
                trim((string)$data['last_name']),
                strtolower(trim((string)$data['email'])),
                trim((string)($data['phone'] ?? '')) ?: null,
                $role['slug'],
                (int)$data['role_id'],
                !empty($data['agency_id']) ? (int)$data['agency_id'] : null,
                'placeholder',
                PasswordPolicy::expirationDate(),
                (int)$request->input('must_change_password', 0),
            ]
        );
        // Hash réel + historique
        $hash = PasswordPolicy::hashAndStore($userId, (string)$data['password']);
        Database::execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $userId]);

        $this->flash('success', 'Utilisateur créé.');
        AuditLog::record('user.create', 'user', $userId, ['email' => strtolower(trim((string)$data['email'])), 'role' => $role['slug']]);
        redirect('admin/users');
    }

    public function edit(Request $request, string $id): void
    {
        $user = Database::selectOne("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [(int)$id]);
        if (!$user) { $this->flash('danger', 'Utilisateur introuvable.'); redirect('admin/users'); }

        $overrides = Database::select(
            "SELECT permission_id, granted FROM user_permissions WHERE user_id = ?",
            [(int)$id]
        );

        $this->view('admin/users/form', [
            'title'      => 'Modifier utilisateur',
            'user'       => $user,
            'roles'      => Database::select("SELECT * FROM roles ORDER BY sort_order, label"),
            'agencies'   => Database::select("SELECT a.*, c.slug AS city, c.name AS city_name FROM agencies a LEFT JOIN cities c ON c.id=a.city_id WHERE a.is_active=1 ORDER BY a.name"),
            'permsByModule' => Permission::allByModule(),
            'overrides'  => array_column($overrides, 'granted', 'permission_id'),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $userId = (int)$id;
        $user = Database::selectOne("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if (!$user) { $this->flash('danger', 'Utilisateur introuvable.'); redirect('admin/users'); }

        $data = $this->validate($request, [
            'first_name' => 'required|max:50',
            'last_name'  => 'required|max:50',
            'email'      => "required|email|unique:users,email,$id",
            'phone'      => 'max:20',
            'role_id'    => 'required|integer',
            'agency_id'  => 'integer',
        ]);
        $role = Database::selectOne("SELECT slug FROM roles WHERE id = ?", [(int)$data['role_id']]);
        if (!$role) { $this->flash('danger', 'Rôle invalide.'); back(); }

        Database::execute(
            "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, role_id=?, agency_id=?, is_active=?, must_change_password=? WHERE id=?",
            [
                trim((string)$data['first_name']),
                trim((string)$data['last_name']),
                strtolower(trim((string)$data['email'])),
                trim((string)($data['phone'] ?? '')) ?: null,
                $role['slug'],
                (int)$data['role_id'],
                !empty($data['agency_id']) ? (int)$data['agency_id'] : null,
                (int)$request->input('is_active', 0),
                (int)$request->input('must_change_password', 0),
                $userId,
            ]
        );

        // Mot de passe optionnel
        $newPwd = (string)$request->input('password', '');
        if ($newPwd !== '') {
            $errs = PasswordPolicy::validate($newPwd, $userId);
            if (!empty($errs)) { $this->flash('danger', implode(' ', $errs)); back(); }
            $hash = PasswordPolicy::hashAndStore($userId, $newPwd);
            Database::execute(
                "UPDATE users SET password_hash=?, password_changed_at=NOW(), password_expires_at=? WHERE id=?",
                [$hash, PasswordPolicy::expirationDate(), $userId]
            );
        }

        // Overrides permissions (par-utilisateur)
        $rawOverrides = (array)$request->input('overrides', []);
        Database::execute("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);
        foreach ($rawOverrides as $pid => $val) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;
            if ($val === 'grant')  Database::execute("INSERT INTO user_permissions (user_id, permission_id, granted) VALUES (?, ?, 1)", [$userId, $pid]);
            if ($val === 'revoke') Database::execute("INSERT INTO user_permissions (user_id, permission_id, granted) VALUES (?, ?, 0)", [$userId, $pid]);
        }

        Auth::flushCache();
        $this->flash('success', 'Utilisateur mis à jour.');
        AuditLog::record('user.update', 'user', $userId, ['email' => strtolower(trim((string)$data['email'])), 'role' => $role['slug']]);
        redirect('admin/users');
    }

    public function destroy(Request $request, string $id): void
    {
        $userId = (int)$id;
        if ($userId === Auth::id()) {
            $this->flash('danger', 'Vous ne pouvez pas vous supprimer vous-même.');
            redirect('admin/users');
            return;
        }
        $target = Database::selectOne("SELECT email FROM users WHERE id = ?", [$userId]);
        Database::execute(
            "UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ?",
            [$userId]
        );
        AuditLog::record('user.delete', 'user', $userId, ['email' => $target['email'] ?? '']);
        $this->flash('success', 'Utilisateur désactivé.');
        redirect('admin/users');
    }

    public function unlock(Request $request, string $id): void
    {
        Database::execute(
            "UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = ?",
            [(int)$id]
        );
        $this->flash('success', 'Compte déverrouillé.');
        redirect('admin/users');
    }

    public function resetPassword(Request $request, string $id): void
    {
        $userId = (int)$id;
        $newPwd = (string)$request->input('password', '');
        if ($newPwd === '') $newPwd = bin2hex(random_bytes(8)); // mdp aléatoire
        $errs = PasswordPolicy::validate($newPwd, $userId);
        if (!empty($errs)) {
            $this->flash('danger', implode(' ', $errs));
            redirect('admin/users');
        }
        $hash = PasswordPolicy::hashAndStore($userId, $newPwd);
        Database::execute(
            "UPDATE users SET password_hash=?, password_changed_at=NOW(), password_expires_at=?, must_change_password=1, failed_login_count=0, locked_until=NULL WHERE id=?",
            [$hash, PasswordPolicy::expirationDate(), $userId]
        );
        AuditLog::record('user.reset_password', 'user', $userId);
        $this->flash('success', 'Mot de passe réinitialisé. L\'utilisateur devra changer son mot de passe à la prochaine connexion.');
        redirect('admin/users');
    }

    public function reset2fa(Request $request, string $id): void
    {
        $userId = (int)$id;
        Database::execute("DELETE FROM two_factor_secrets WHERE user_id = ?", [$userId]);
        Database::execute("UPDATE users SET two_factor_enabled = 0 WHERE id = ?", [$userId]);
        $this->flash('success', '2FA désactivée pour cet utilisateur.');
        redirect('admin/users');
    }
}
