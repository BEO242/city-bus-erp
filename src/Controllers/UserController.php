<?php

declare(strict_types=1);

namespace CityBus\Controllers;

use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;

final class UserController extends Controller
{
    public function index(Request $request): void
    {
        $users = Database::select(
            "SELECT u.*, a.name AS agency_name FROM users u
             LEFT JOIN agencies a ON a.id=u.agency_id
             WHERE u.deleted_at IS NULL ORDER BY u.role, u.last_name"
        );
        $this->view('users/index', ['title' => 'Utilisateurs', 'users' => $users]);
    }

    public function create(Request $request): void
    {
        $this->view('users/form', [
            'title' => 'Nouvel utilisateur', 'user' => null,
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
            'role'       => 'required|in:admin,raf,exploitation,chef_agence,caissier,controleur,mecanicien,chauffeur',
            'agency_id'  => 'integer',
            'password'   => 'required|min:6',
        ]);
        $data['password_hash'] = Auth::hash($data['password']);
        unset($data['password']);
        $data['is_active'] = 1;
        Database::insert(
            "INSERT INTO users (first_name,last_name,email,phone,role,agency_id,password_hash,is_active)
             VALUES (?,?,?,?,?,?,?,?)",
            [$data['first_name'],$data['last_name'],strtolower($data['email']),$data['phone']??null,$data['role'],$data['agency_id']??null,$data['password_hash'],1]
        );
        $this->flash('success', 'Utilisateur crÃ©Ã©.');
        redirect('users');
    }

    public function edit(Request $request, string $id): void
    {
        $this->view('users/form', [
            'title' => 'Modifier utilisateur',
            'user' => Database::selectOne("SELECT * FROM users WHERE id=?", [$id]),
            'agencies' => Database::select("SELECT a.*, c.slug AS city, c.name AS city_name FROM agencies a LEFT JOIN cities c ON c.id=a.city_id WHERE a.is_active=1 ORDER BY a.name"),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $data = $this->validate($request, [
            'first_name' => 'required|max:50',
            'last_name'  => 'required|max:50',
            'email'      => "required|email|unique:users,email,$id",
            'phone'      => 'max:20',
            'role'       => 'required|in:admin,raf,exploitation,chef_agence,caissier,controleur,mecanicien,chauffeur',
            'agency_id'  => 'integer',
        ]);
        $data['is_active'] = (int)$request->input('is_active', 0);
        Database::execute(
            "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, agency_id=?, is_active=? WHERE id=?",
            [$data['first_name'],$data['last_name'],strtolower($data['email']),$data['phone']??null,$data['role'],$data['agency_id']??null,$data['is_active'],$id]
        );

        if ($request->input('password')) {
            Database::execute("UPDATE users SET password_hash=? WHERE id=?",
                [Auth::hash($request->input('password')), $id]);
        }

        $this->flash('success', 'Utilisateur mis Ã  jour.');
        redirect('users');
    }

    public function destroy(Request $request, string $id): void
    {
        Database::execute("UPDATE users SET deleted_at=NOW(), is_active=0 WHERE id=?", [$id]);
        $this->flash('success', 'Utilisateur dÃ©sactivÃ©.');
        redirect('users');
    }
}
