<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\Line;
use CityBus\Models\Media;
use CityBus\Models\Note;
use CityBus\Services\MediaService;

final class LineController extends Controller
{
    public function index(Request $request): void
    {
        // ─── Filtres ────────────────────────────────────────────────
        $search        = trim((string)$request->input('q', ''));
        $statusFilter  = trim((string)$request->input('status', ''));   // active | inactive | ''
        $cityFilter    = trim((string)$request->input('city', ''));     // brazzaville | pointe_noire | ''
        $alertsFilter  = trim((string)$request->input('alerts', ''));   // yes | no | ''
        $typeFilter    = trim((string)$request->input('type', ''));     // interurbain | urbain | ''

        $validSorts = ['code', 'name', 'departure_city', 'departure_city_id', 'distance_km', 'duration_hours', 'is_active', 'line_type', 'created_at', 'alerts_n', 'stops_count'];
        $sortField  = in_array($request->input('sort'), $validSorts, true) ? $request->input('sort') : 'code';
        $sortDir    = $request->input('dir') === 'desc' ? 'DESC' : 'ASC';

        $perPageOpts = [12, 24, 48, 9999];
        $perPage     = in_array((int)$request->input('per_page'), $perPageOpts, true) ? (int)$request->input('per_page') : 24;
        $page        = max(1, (int)$request->input('page', 1));

        // ─── SQL de base ─────────────────────────────────────────────
        $sql    = "SELECT l.*,
                          cd.slug AS departure_city, cd.name AS departure_city_name,
                          ca.slug AS arrival_city,   ca.name AS arrival_city_name,
                          (SELECT COUNT(*) FROM stops    s WHERE s.line_id = l.id)                      AS stops_count,
                          (SELECT COUNT(*) FROM tariffs  t WHERE t.line_id = l.id AND t.is_active = 1) AS tariffs_active,
                          (SELECT COUNT(*) FROM trips    tr WHERE tr.line_id = l.id)                    AS trips_total
                   FROM bus_lines l
                   JOIN cities cd ON cd.id = l.departure_city_id
                   JOIN cities ca ON ca.id = l.arrival_city_id";
        $where  = [];
        $params = [];

        if ($statusFilter === 'active') {
            $where[] = 'l.is_active = 1';
        } elseif ($statusFilter === 'inactive') {
            $where[] = 'l.is_active = 0';
        }
        if ($typeFilter !== '') {
            $where[] = 'l.line_type = ?';
            $params[] = $typeFilter;
        }
        if ($cityFilter !== '') {
            $where[] = '(cd.slug = ? OR ca.slug = ?)';
            $params[] = $cityFilter;
            $params[] = $cityFilter;
        }
        if ($search !== '') {
            $where[] = '(l.code LIKE ? OR l.name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // Tri SQL (sauf alerts_n calculé en PHP)
        $sqlSort = $sortField === 'alerts_n' ? 'code' : $sortField;
        if ($sqlSort === 'departure_city') { $sqlSort = 'cd.name'; }
        $sql    .= " ORDER BY {$sqlSort} {$sortDir}";

        $lines = Database::select($sql, $params);

        // ─── Enrichir : alertes ──────────────────────────────────────
        foreach ($lines as &$line) {
            $line['alerts']   = Line::alerts($line);
            $line['alerts_n'] = count(array_filter($line['alerts'], fn($a) => $a['level'] === 'danger'));
            $line['warns_n']  = count(array_filter($line['alerts'], fn($a) => $a['level'] === 'warn'));
        }
        unset($line);

        // Tri PHP pour alerts_n
        if ($sortField === 'alerts_n') {
            usort($lines, fn($a, $b) => $sortDir === 'ASC'
                ? $a['alerts_n'] <=> $b['alerts_n']
                : $b['alerts_n'] <=> $a['alerts_n']
            );
        }

        // Filtre PHP alertes
        if ($alertsFilter === 'yes') {
            $lines = array_values(array_filter($lines, fn($l) => $l['alerts_n'] > 0 || $l['warns_n'] > 0));
        } elseif ($alertsFilter === 'no') {
            $lines = array_values(array_filter($lines, fn($l) => $l['alerts_n'] === 0 && $l['warns_n'] === 0));
        }

        // Pagination PHP
        $total    = count($lines);
        $lastPage = $perPage === 9999 ? 1 : (int)ceil($total / $perPage);
        $lastPage = max(1, $lastPage);
        $page     = max(1, min($page, $lastPage));
        $lines    = $perPage === 9999 ? $lines : array_slice($lines, ($page - 1) * $perPage, $perPage);

        $this->view('referentiel/lines/index', [
            'title'        => 'Lignes',
            'lines'        => $lines,
            'search'       => $search,
            'statusFilter' => $statusFilter,
            'cityFilter'   => $cityFilter,
            'typeFilter'   => $typeFilter,
            'alertsFilter' => $alertsFilter,
            'sortField'    => $sortField,
            'sortDir'      => strtolower($sortDir),
            'perPage'      => $perPage,
            'page'         => $page,
            'total'        => $total,
            'lastPage'     => $lastPage,
        ]);
    }

    /** Fiche détaillée d'une ligne. */
    public function show(Request $request, string $id): void
    {
        $line = Database::selectOne(
            "SELECT l.*, cd.slug AS departure_city, cd.name AS departure_city_name,
                         ca.slug AS arrival_city,   ca.name AS arrival_city_name,
                         cu.name AS city_name
             FROM bus_lines l
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             LEFT JOIN cities cu ON cu.id = l.city_id
             WHERE l.id = ?",
            [(int)$id]
        );
        if (!$line) { http_response_code(404); $this->view('errors/404'); return; }

        // ─── Arrêts ─────────────────────────────────────────────────
        $stops = Database::select(
            "SELECT s.*, a.name AS agency_name
             FROM stops s LEFT JOIN agencies a ON a.id = s.agency_id
             WHERE s.line_id=? ORDER BY s.km_from_origin ASC, s.order_position ASC",
            [$line['id']]
        );

        // ─── Tarifs ──────────────────────────────────────────────────
        $tariffs = Database::select(
            "SELECT * FROM tariffs WHERE line_id=? ORDER BY ticket_type",
            [$line['id']]
        );

        // ─── Stats activité ─────────────────────────────────────────
        $stats       = Line::stats((int)$line['id']);
        $recentTrips = Line::recentTrips((int)$line['id'], 10);
        $alerts      = Line::alerts($line);
        $notes       = Note::forEntity('lines', (int)$line['id']);

        // ─── Stats tickets ──────────────────────────────────────────
        $ticketStats = Database::selectOne(
            "SELECT COUNT(*)                                                          AS total_n,
                    SUM(CASE WHEN tk.status='annule' THEN 1 ELSE 0 END)              AS cancelled_n,
                    SUM(CASE WHEN tk.ticket_type='passager'
                              AND tk.status != 'annule' THEN 1 ELSE 0 END)           AS passengers_n,
                    SUM(CASE WHEN tk.status != 'annule' THEN tk.price_fcfa ELSE 0 END) AS revenue_gross
             FROM tickets tk
             INNER JOIN trips t ON t.id = tk.trip_id
             WHERE t.line_id=? AND tk.deleted_at IS NULL",
            [$line['id']]
        ) ?: [];

        // ─── Revenus par type de ticket ──────────────────────────────
        $revenueByType = Database::select(
            "SELECT tk.ticket_type,
                    COUNT(*)            AS count_n,
                    SUM(tk.price_fcfa)  AS revenue
             FROM tickets tk
             INNER JOIN trips t ON t.id = tk.trip_id
             WHERE t.line_id=? AND tk.deleted_at IS NULL AND tk.status != 'annule'
             GROUP BY tk.ticket_type ORDER BY revenue DESC",
            [$line['id']]
        );

        // ─── Top bus ─────────────────────────────────────────────────
        $topBuses = Database::select(
            "SELECT b.id, b.code, b.plate, b.brand, b.model, b.seats,
                    COUNT(*) AS trips_n
             FROM trips t
             INNER JOIN buses b ON b.id = t.bus_id
             WHERE t.line_id=?
             GROUP BY t.bus_id ORDER BY trips_n DESC LIMIT 5",
            [$line['id']]
        );

        // ─── Top chauffeurs ──────────────────────────────────────────
        $topDrivers = Database::select(
            "SELECT d.id, d.first_name, d.last_name, d.matricule,
                    COUNT(*) AS trips_n
             FROM trips t
             INNER JOIN drivers d ON d.id = t.driver_id
             WHERE t.line_id=?
             GROUP BY t.driver_id ORDER BY trips_n DESC LIMIT 5",
            [$line['id']]
        );

        // ─── Prochain voyage planifié ────────────────────────────────
        $nextTrip = Database::selectOne(
            "SELECT t.*, t.trip_code,
                    b.code AS bus_code, b.plate AS bus_plate,
                    CONCAT(d.first_name,' ',d.last_name) AS driver_name
             FROM trips t
             LEFT JOIN buses   b ON b.id = t.bus_id
             LEFT JOIN drivers d ON d.id = t.driver_id
             WHERE t.line_id=?
               AND t.trip_date >= CURDATE()
               AND t.status NOT IN ('annule','cloture')
             ORDER BY t.trip_date ASC, t.departure_scheduled ASC
             LIMIT 1",
            [$line['id']]
        );

        $this->view('referentiel/lines/show', [
            'title'         => $line['code'] . ' — ' . $line['name'],
            'line'          => $line,
            'stops'         => $stops,
            'tariffs'       => $tariffs,
            'stats'         => $stats,
            'recentTrips'   => $recentTrips,
            'alerts'        => $alerts,
            'notes'         => $notes,
            'ticketStats'   => $ticketStats,
            'revenueByType' => $revenueByType,
            'topBuses'      => $topBuses,
            'topDrivers'    => $topDrivers,
            'nextTrip'      => $nextTrip,
        ]);
    }

    public function create(Request $request): void
    {
        $agencies = Database::select("SELECT id, name FROM agencies WHERE is_active=1 ORDER BY name");
        $this->view('referentiel/lines/form', [
            'title'    => 'Nouvelle ligne',
            'line'     => null,
            'stops'    => [],
            'tariffs'  => [],
            'gallery'  => [],
            'docs'     => [],
            'agencies' => $agencies,
            'cities'   => \CityBus\Models\City::active(),
        ]);
    }

    public function store(Request $request): void
    {
        $lineType = $request->input('line_type', 'interurbain');
        $rules = [
            'code'      => 'required|max:20|unique:bus_lines,code',
            'name'      => 'required|max:120',
            'line_type' => 'required',
        ];

        if ($lineType === 'urbain') {
            $rules['city_id'] = 'required|integer|exists:cities,id';
        } else {
            $rules['departure_city_id'] = 'required|integer|exists:cities,id';
            $rules['arrival_city_id']   = 'required|integer|exists:cities,id';
        }
        $rules['distance_km']   = 'numeric';
        $rules['duration_hours'] = 'numeric';

        $data = $this->validate($request, $rules);

        // Pour lignes urbaines : départ et arrivée = même ville
        if ($lineType === 'urbain') {
            $data['departure_city_id'] = $data['city_id'];
            $data['arrival_city_id']   = $data['city_id'];
        }
        // Agences de départ/arrivée (interurbain optionnel)
        $data['departure_agency_id'] = $request->input('departure_agency_id') ?: null;
        $data['arrival_agency_id']   = $request->input('arrival_agency_id') ?: null;

        // Pour lignes urbaines : valider qu'il y a au moins 2 arrêts (départ + arrivée)
        if ($lineType === 'urbain') {
            $stopsData = json_decode($request->input('stops_json', '[]'), true);
            $validStops = is_array($stopsData) ? array_filter($stopsData, fn($s) => trim($s['name'] ?? '') !== '') : [];
            if (count($validStops) < 2) {
                $this->flash('danger', 'Une ligne urbaine doit avoir au moins 2 arrêts (départ et arrivée).');
                back();
                return;
            }
        }

        $data['is_active'] = 1;
        $newId = Line::create($data);
        $this->saveStops((int)$newId, $request->input('stops_json', '[]'));
        $this->flash('success', 'Ligne créée avec succès.');
        redirect('referentiel/lines/' . $newId);
    }

    public function edit(Request $request, string $id): void
    {
        $line = Database::selectOne(
            "SELECT l.*, cd.slug AS departure_city, cd.name AS departure_city_name,
                         ca.slug AS arrival_city,   ca.name AS arrival_city_name
             FROM bus_lines l
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             WHERE l.id = ?",
            [(int)$id]
        );
        if (!$line) { http_response_code(404); $this->view('errors/404'); return; }
        $stops    = Database::select("SELECT s.*, a.name AS agency_name FROM stops s LEFT JOIN agencies a ON a.id=s.agency_id WHERE s.line_id=? ORDER BY s.km_from_origin ASC, s.order_position ASC", [$line['id']]);
        $tariffs  = Database::select("SELECT * FROM tariffs WHERE line_id=? ORDER BY ticket_type", [$line['id']]);
        $gallery  = MediaService::enrichAll(Media::forModel('lines', (int)$id, 'gallery'));
        $docs     = MediaService::enrichAll(Media::forModel('lines', (int)$id, 'documents'));
        $agencies = Database::select("SELECT id, name FROM agencies WHERE is_active=1 ORDER BY name");
        $this->view('referentiel/lines/form', [
            'title'    => 'Modifier ligne',
            'line'     => $line,
            'stops'    => $stops,
            'tariffs'  => $tariffs,
            'gallery'  => $gallery,
            'docs'     => $docs,
            'agencies' => $agencies,
            'cities'   => \CityBus\Models\City::active(),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $lineType = $request->input('line_type', 'interurbain');
        $rules = [
            'code'      => "required|max:20|unique:bus_lines,code,$id",
            'name'      => 'required|max:120',
            'line_type' => 'required',
        ];

        if ($lineType === 'urbain') {
            $rules['city_id'] = 'required|integer|exists:cities,id';
        } else {
            $rules['departure_city_id'] = 'required|integer|exists:cities,id';
            $rules['arrival_city_id']   = 'required|integer|exists:cities,id';
        }
        $rules['distance_km']   = 'numeric';
        $rules['duration_hours'] = 'numeric';

        $data = $this->validate($request, $rules);

        // Pour lignes urbaines : départ et arrivée = même ville
        if ($lineType === 'urbain') {
            $data['departure_city_id'] = $data['city_id'];
            $data['arrival_city_id']   = $data['city_id'];
        }
        // Agences de départ/arrivée (interurbain optionnel)
        $data['departure_agency_id'] = $request->input('departure_agency_id') ?: null;
        $data['arrival_agency_id']   = $request->input('arrival_agency_id') ?: null;

        // Pour lignes urbaines : valider qu'il y a au moins 2 arrêts (départ + arrivée)
        if ($lineType === 'urbain') {
            $stopsData = json_decode($request->input('stops_json', '[]'), true);
            $validStops = is_array($stopsData) ? array_filter($stopsData, fn($s) => trim($s['name'] ?? '') !== '') : [];
            if (count($validStops) < 2) {
                $this->flash('danger', 'Une ligne urbaine doit avoir au moins 2 arrêts (départ et arrivée).');
                back();
                return;
            }
        }

        $data['is_active'] = (int)$request->input('is_active', 0);
        Line::update((int)$id, $data);
        $this->saveStops((int)$id, $request->input('stops_json', '[]'));
        $this->flash('success', 'Ligne mise à jour.');
        redirect('referentiel/lines/' . $id);
    }

    private function saveStops(int $lineId, string $stopsJson): void
    {
        $stops = json_decode($stopsJson, true);
        if (!is_array($stops)) {
            return;
        }

        // IDs des arrêts soumis par le formulaire (0 = nouvel arrêt)
        $submittedIds = [];

        foreach ($stops as $i => $s) {
            $name = trim($s['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $stopId      = isset($s['id']) ? (int)$s['id'] : 0;
            $agencyId    = !empty($s['agency_id']) ? (int)$s['agency_id'] : null;
            $order       = (int)($s['order_position'] ?? $i + 1);
            $km          = isset($s['km_from_origin']) && $s['km_from_origin'] !== '' ? (float)$s['km_from_origin'] : null;

            if ($stopId > 0) {
                // Arrêt existant → UPDATE
                Database::execute(
                    "UPDATE stops SET name=?, agency_id=?, order_position=?, km_from_origin=? WHERE id=? AND line_id=?",
                    [$name, $agencyId, $order, $km, $stopId, $lineId]
                );
                $submittedIds[] = $stopId;
            } else {
                // Nouvel arrêt → INSERT
                $newId = Database::insert(
                    "INSERT INTO stops (line_id, agency_id, name, order_position, km_from_origin) VALUES (?, ?, ?, ?, ?)",
                    [$lineId, $agencyId, $name, $order, $km]
                );
                $submittedIds[] = $newId;
            }
        }

        // Supprimer uniquement les anciens arrêts non soumis ET non référencés dans trip_stops
        if (!empty($submittedIds)) {
            $placeholders = implode(',', array_fill(0, count($submittedIds), '?'));
            Database::execute(
                "DELETE FROM stops
                  WHERE line_id = ?
                    AND id NOT IN ($placeholders)
                    AND id NOT IN (SELECT DISTINCT stop_id FROM trip_stops)",
                array_merge([$lineId], $submittedIds)
            );
        } else {
            // Aucun arrêt soumis : supprimer seulement ceux sans référence
            Database::execute(
                "DELETE FROM stops
                  WHERE line_id = ?
                    AND id NOT IN (SELECT DISTINCT stop_id FROM trip_stops)",
                [$lineId]
            );
        }
    }

    public function destroy(Request $request, string $id): void
    {
        // Vérifier qu'aucun voyage n'est lié avant suppression
        $tripsN = (int)(Database::selectOne("SELECT COUNT(*) AS n FROM trips WHERE line_id=?", [(int)$id])['n'] ?? 0);
        if ($tripsN > 0) {
            $this->flash('danger', "Suppression impossible : {$tripsN} voyage(s) sont rattaché(s) à cette ligne. Désactivez-la plutôt.");
            redirect('referentiel/lines');
            return;
        }
        Line::delete((int)$id);
        $this->flash('success', 'Ligne supprimée.');
        redirect('referentiel/lines');
    }
}

