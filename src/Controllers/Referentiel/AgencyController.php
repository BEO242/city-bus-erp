<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\Agency;
use CityBus\Models\AuditLog;
use CityBus\Models\City;

final class AgencyController extends Controller
{
    public function index(Request $request): void
    {
        $page = max(1, (int)$request->input('page', 1));
        $search = trim((string)$request->input('q', ''));

        $where = '1=1';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (a.name LIKE ? OR a.address LIKE ?)';
            $params = ["%$search%", "%$search%"];
        }

        // Pagination manuelle avec JOIN cities pour récupérer le nom lisible
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT a.*, c.name AS city_name, c.slug AS city
             FROM agencies a JOIN cities c ON c.id = a.city_id
             WHERE $where ORDER BY c.name ASC, a.type ASC, a.name ASC
             LIMIT $perPage OFFSET $offset",
            $params
        );
        $total = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM agencies a JOIN cities c ON c.id=a.city_id WHERE $where",
            $params
        )['c'] ?? 0);
        $data = [
            'items' => $rows,
            'total' => $total,
            'page'  => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ];

        $this->view('referentiel/agencies/index', [
            'title' => 'Agences', 'data' => $data, 'q' => $search,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('referentiel/agencies/form', [
            'title' => 'Nouvelle agence', 'agency' => null,
            'cities' => City::active(),
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request, [
            'name'    => 'required|min:3|max:120',
            'city_id' => 'required|integer|exists:cities,id',
            'type'    => 'required|in:principale,point_vente,controle,parking',
            'address' => 'max:200',
            'phone'   => 'max:20',
        ]);
        $data['is_active'] = 1;
        $newId = Agency::create($data);
        AuditLog::record('agency.create', 'agency', (int)$newId, ['name' => $data['name']]);
        $this->flash('success', 'Agence créée.');
        redirect('referentiel/agencies');
    }

    public function edit(Request $request, string $id): void
    {
        $this->view('referentiel/agencies/form', [
            'title' => 'Modifier agence',
            'agency' => Agency::findOrFail((int)$id),
            'cities' => City::active(),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $data = $this->validate($request, [
            'name'    => 'required|min:3|max:120',
            'city_id' => 'required|integer|exists:cities,id',
            'type'    => 'required|in:principale,point_vente,controle,parking',
            'address' => 'max:200',
            'phone'   => 'max:20',
        ]);
        $data['is_active'] = (int)$request->input('is_active', 0);
        Agency::update((int)$id, $data);
        $this->flash('success', 'Agence mise à jour.');
        redirect('referentiel/agencies');
    }

    public function destroy(Request $request, string $id): void
    {
        // Vérifier l'absence d'utilisateurs, de bus et de caisses ouvertes
        $userCount = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM users WHERE agency_id=? AND deleted_at IS NULL", [(int)$id]
        )['n'] ?? 0);
        if ($userCount > 0) {
            $this->flash('danger', "Impossible : {$userCount} utilisateur(s) rattaché(s) à cette agence.");
            back(); return;
        }
        $busCount = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM buses WHERE agency_id=? AND deleted_at IS NULL", [(int)$id]
        )['n'] ?? 0);
        if ($busCount > 0) {
            $this->flash('danger', "Impossible : {$busCount} bus rattaché(s) à cette agence.");
            back(); return;
        }
        $openCaisse = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM cash_registers WHERE agency_id=? AND closed_at IS NULL", [(int)$id]
        )['n'] ?? 0);
        if ($openCaisse > 0) {
            $this->flash('danger', "Impossible : {$openCaisse} caisse(s) ouverte(s) dans cette agence.");
            back(); return;
        }

        Agency::delete((int)$id);
        $this->flash('success', 'Agence supprimée.');
        redirect('referentiel/agencies');
    }
}
