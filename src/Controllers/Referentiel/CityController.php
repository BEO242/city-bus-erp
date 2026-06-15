<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

/**
 * CRUD des villes (table cities) — utilisées par agences, lignes, etc.
 */
final class CityController extends Controller
{
    public function index(Request $request): void
    {
        $q = trim((string)$request->input('q', ''));
        $where  = '1=1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (name LIKE ? OR slug LIKE ? OR region LIKE ?)';
            $like   = "%$q%";
            array_push($params, $like, $like, $like);
        }
        $cities = Database::select(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM agencies WHERE city_id = c.id) AS agencies_count,
                    (SELECT COUNT(*) FROM bus_lines WHERE departure_city_id = c.id OR arrival_city_id = c.id) AS lines_count
             FROM cities c
             WHERE $where
             ORDER BY c.display_order, c.name",
            $params
        );

        $this->view('referentiel/cities/index', [
            'title'  => 'Villes',
            'cities' => $cities,
            'q'      => $q,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('referentiel/cities/form', [
            'title' => 'Nouvelle ville',
            'city'  => null,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request, [
            'slug'          => 'required|min:2|max:50|unique:cities,slug',
            'name'          => 'required|min:2|max:100',
            'region'        => 'max:50',
            'display_order' => 'integer',
        ]);
        $data['slug']          = strtolower(preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$data['slug'])));
        $data['display_order'] = isset($data['display_order']) ? (int)$data['display_order'] : 100;
        $data['is_active']     = (int)$request->input('is_active', 1);

        $id = (int)Database::insert(
            "INSERT INTO cities (slug, name, region, display_order, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$data['slug'], $data['name'], $data['region'] ?? null, $data['display_order'], $data['is_active']]
        );
        AuditLog::record('city.create', 'city', $id, ['name' => $data['name'], 'slug' => $data['slug']]);
        $this->flash('success', 'Ville créée.');
        redirect('referentiel/cities');
    }

    public function edit(Request $request, string $id): void
    {
        $city = Database::selectOne("SELECT * FROM cities WHERE id = ?", [(int)$id]);
        if (!$city) {
            $this->flash('danger', 'Ville introuvable.');
            redirect('referentiel/cities');
            return;
        }
        $this->view('referentiel/cities/form', [
            'title' => 'Modifier ' . $city['name'],
            'city'  => $city,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $cityId = (int)$id;
        $city = Database::selectOne("SELECT * FROM cities WHERE id = ?", [$cityId]);
        if (!$city) {
            $this->flash('danger', 'Ville introuvable.');
            redirect('referentiel/cities');
            return;
        }
        $data = $this->validate($request, [
            'slug'          => "required|min:2|max:50|unique:cities,slug,$cityId",
            'name'          => 'required|min:2|max:100',
            'region'        => 'max:50',
            'display_order' => 'integer',
        ]);
        Database::execute(
            "UPDATE cities SET slug=?, name=?, region=?, display_order=?, is_active=? WHERE id=?",
            [
                strtolower(preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$data['slug']))),
                $data['name'],
                $data['region'] ?? null,
                isset($data['display_order']) ? (int)$data['display_order'] : 100,
                (int)$request->input('is_active', 0),
                $cityId,
            ]
        );
        AuditLog::record('city.update', 'city', $cityId, ['name' => $data['name']]);
        $this->flash('success', 'Ville mise à jour.');
        redirect('referentiel/cities');
    }

    public function destroy(Request $request, string $id): void
    {
        $cityId = (int)$id;
        // Empêcher la suppression si des dépendances existent
        $agencies = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM agencies WHERE city_id=?", [$cityId]
        )['n'] ?? 0);
        if ($agencies > 0) {
            $this->flash('danger', "Impossible : {$agencies} agence(s) rattachée(s) à cette ville.");
            back(); return;
        }
        $lines = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM bus_lines WHERE departure_city_id=? OR arrival_city_id=?",
            [$cityId, $cityId]
        )['n'] ?? 0);
        if ($lines > 0) {
            $this->flash('danger', "Impossible : {$lines} ligne(s) référencée(s) sur cette ville.");
            back(); return;
        }

        $city = Database::selectOne("SELECT name FROM cities WHERE id=?", [$cityId]);
        Database::execute("DELETE FROM cities WHERE id=?", [$cityId]);
        AuditLog::record('city.delete', 'city', $cityId, ['name' => $city['name'] ?? '']);
        $this->flash('success', 'Ville supprimée.');
        redirect('referentiel/cities');
    }
}
