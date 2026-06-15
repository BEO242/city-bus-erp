<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\Convoyeur;
use CityBus\Models\Note;

final class ConvoyeurController extends Controller
{
    public function index(Request $request): void
    {
        $statusFilter = trim((string)$request->input('status', ''));
        $agencyFilter = (int)$request->input('agency_id', 0);
        $search       = trim((string)$request->input('q', ''));

        $validSorts = ['last_name', 'matricule', 'hire_date', 'rating_score', 'status', 'created_at', 'total_trips'];
        $sortField  = in_array($request->input('sort'), $validSorts, true) ? $request->input('sort') : 'last_name';
        $sortDir    = $request->input('dir') === 'desc' ? 'DESC' : 'ASC';

        $page    = max(1, (int)$request->input('page', 1));
        $perPage = 24;

        $sql    = "SELECT c.*, a.name AS agency_name
                   FROM convoyeurs c
                   LEFT JOIN agencies a ON a.id = c.agency_id
                   WHERE c.deleted_at IS NULL";
        $params = [];

        if ($statusFilter !== '' && isset(Convoyeur::STATUSES[$statusFilter])) {
            $sql .= ' AND c.status = ?';
            $params[] = $statusFilter;
        }
        if ($agencyFilter > 0) {
            $sql .= ' AND c.agency_id = ?';
            $params[] = $agencyFilter;
        }
        if ($search !== '') {
            $sql .= ' AND (c.matricule LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $sql .= " ORDER BY c.{$sortField} {$sortDir}";

        $all = Database::select($sql, $params);

        // Enrichir avec alertes
        foreach ($all as &$c) {
            $c['alerts'] = Convoyeur::alerts($c);
        }
        unset($c);

        // Pagination PHP
        $total    = count($all);
        $lastPage = max(1, (int)ceil($total / $perPage));
        $page     = min($page, $lastPage);
        $convoyeurs = array_slice($all, ($page - 1) * $perPage, $perPage);

        // KPIs
        $kpis = Database::selectOne(
            "SELECT
               COUNT(*)                          AS total,
               SUM(status = 'actif')             AS actif_n,
               SUM(status IN ('conge','en_formation')) AS indispo_n,
               SUM(status = 'suspendu')          AS suspendu_n
             FROM convoyeurs WHERE deleted_at IS NULL"
        ) ?: [];

        $statusCounts = Database::select(
            "SELECT status, COUNT(*) AS n FROM convoyeurs WHERE deleted_at IS NULL GROUP BY status"
        );
        $statusCountMap = array_column($statusCounts, 'n', 'status');

        $this->view('referentiel/convoyeurs/index', [
            'title'          => 'Convoyeurs',
            'convoyeurs'     => $convoyeurs,
            'agencies'       => Database::select("SELECT * FROM agencies WHERE is_active=1 ORDER BY name"),
            'statusFilter'   => $statusFilter,
            'agencyFilter'   => $agencyFilter,
            'search'         => $search,
            'sortField'      => $sortField,
            'sortDir'        => strtolower($sortDir),
            'page'           => $page,
            'total'          => $total,
            'lastPage'       => $lastPage,
            'kpis'           => $kpis,
            'statusCountMap' => $statusCountMap,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('referentiel/convoyeurs/form', [
            'title'      => 'Nouveau convoyeur',
            'convoyeur'  => null,
            'agencies'   => Database::select("SELECT * FROM agencies WHERE is_active=1 ORDER BY name"),
        ]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('referentiel.create')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        $data = $this->validate($request, $this->rules());
        $this->normalize($data, $request);

        // Auto-génération du matricule si pas fourni
        if (empty($data['matricule'])) {
            $data['matricule'] = Convoyeur::generateMatricule();
        }

        $data = array_filter($data, fn($v) => $v !== '' && $v !== null);
        $id = Convoyeur::create($data);

        $this->flash('success', 'Convoyeur créé avec succès.');
        redirect('referentiel/convoyeurs/' . $id);
    }

    public function show(Request $request, string $id): void
    {
        $convoyeur = Convoyeur::findOrFail((int)$id);

        $agencyName = null;
        if (!empty($convoyeur['agency_id'])) {
            $row = Database::selectOne("SELECT name FROM agencies WHERE id=?", [$convoyeur['agency_id']]);
            $agencyName = $row['name'] ?? null;
        }
        $convoyeur['agency_name'] = $agencyName;

        $stats  = Convoyeur::stats((int)$id);
        $alerts = Convoyeur::alerts($convoyeur);
        $trips  = Convoyeur::recentTrips((int)$id, 10);
        $notes  = Note::forEntity('convoyeurs', (int)$id);

        $this->view('referentiel/convoyeurs/show', [
            'title'     => Convoyeur::fullName($convoyeur) . ' — Fiche convoyeur',
            'convoyeur' => $convoyeur,
            'stats'     => $stats,
            'alerts'    => $alerts,
            'trips'     => $trips,
            'notes'     => $notes,
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        $convoyeur = Convoyeur::findOrFail((int)$id);

        $this->view('referentiel/convoyeurs/form', [
            'title'     => 'Modifier ' . Convoyeur::fullName($convoyeur),
            'convoyeur' => $convoyeur,
            'agencies'  => Database::select("SELECT * FROM agencies WHERE is_active=1 ORDER BY name"),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        if (!Auth::can('referentiel.edit')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        $data = $this->validate($request, $this->rules((int)$id));
        $this->normalize($data, $request);

        if (isset($data['agency_id']) && (int)$data['agency_id'] === 0) {
            $data['agency_id'] = null;
        }

        Convoyeur::update((int)$id, $data);
        $this->flash('success', 'Convoyeur mis à jour.');
        redirect('referentiel/convoyeurs/' . $id);
    }

    public function destroy(Request $request, string $id): void
    {
        if (!Auth::can('referentiel.delete')) {
            $this->flash('danger', 'Permission refusée.');
            back();
            return;
        }

        // Vérifier qu'il n'a pas de voyages actifs
        $activeTrips = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM trips WHERE convoyeur_id = ? AND status NOT IN ('cloture','annule')",
            [(int)$id]
        )['c'] ?? 0);

        if ($activeTrips > 0) {
            $this->flash('danger', "Impossible de supprimer : {$activeTrips} voyage(s) actif(s) assigné(s).");
            back();
            return;
        }

        Convoyeur::delete((int)$id);
        $this->flash('success', 'Convoyeur supprimé.');
        redirect('referentiel/convoyeurs');
    }

    private function rules(?int $id = null): array
    {
        $matRule = 'max:20' . ($id ? '' : '');
        return [
            'matricule'         => $matRule,
            'first_name'        => 'required|max:60',
            'last_name'         => 'required|max:60',
            'birth_date'        => 'date',
            'gender'            => 'in:M,F',
            'national_id'       => 'max:40',
            'national_id_expiry'=> 'date',
            'phone'             => 'required|max:20',
            'phone_alt'         => 'max:20',
            'email'             => 'email|max:120',
            'address'           => '',
            'city'              => 'max:60',
            'emergency_name'    => 'max:100',
            'emergency_phone'   => 'max:20',
            'emergency_relation'=> 'max:50',
            'hire_date'         => 'required|date',
            'agency_id'         => 'integer',
            'status'            => 'required|in:actif,conge,suspendu,en_formation,quitte',
            'salary_base'       => 'integer',
            'daily_bonus'       => 'integer',
            'bank_name'         => 'max:80',
            'bank_account'      => 'max:60',
            'mobile_money_number'=> 'max:20',
        ];
    }

    private function normalize(array &$data, Request $request): void
    {
        $data['notes'] = $request->input('notes') ?? null;
    }
}
