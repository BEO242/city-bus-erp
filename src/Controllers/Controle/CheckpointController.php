<?php

declare(strict_types=1);

namespace CityBus\Controllers\Controle;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

/**
 * CRUD des postes de contrôle (checkpoints) utilisés pour la validation des billets.
 */
final class CheckpointController extends Controller
{
    public function index(Request $request): void
    {
        $q       = trim((string)$request->input('q', ''));
        $agencyF = (int)$request->input('agency_id', 0);
        $statusF = trim((string)$request->input('status', ''));

        $where  = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(c.name LIKE ? OR a.name LIKE ?)';
            $like = "%$q%";
            array_push($params, $like, $like);
        }
        if ($agencyF > 0) { $where[] = 'c.agency_id = ?'; $params[] = $agencyF; }
        if ($statusF === 'active')   { $where[] = 'c.is_active = 1'; }
        if ($statusF === 'inactive') { $where[] = 'c.is_active = 0'; }

        // Scope agence : un chef d'agence ne voit que ses propres checkpoints
        $user = Auth::user();
        if (Auth::role() !== 'admin' && !empty($user['agency_id'])) {
            $where[]  = 'c.agency_id = ?';
            $params[] = (int)$user['agency_id'];
        }

        $checkpoints = Database::select(
            "SELECT c.*, a.name AS agency_name, l.code AS line_code, l.name AS line_name,
                    ct.name AS city_name,
                    (SELECT COUNT(*) FROM validations v WHERE v.checkpoint_id = c.id) AS validations_count
             FROM checkpoints c
             JOIN agencies a ON a.id = c.agency_id
             LEFT JOIN cities ct ON ct.id = a.city_id
             LEFT JOIN bus_lines l ON l.id = c.line_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY a.name, c.name",
            $params
        );

        $this->view('controle/checkpoints/index', [
            'title'       => 'Postes de contrôle',
            'checkpoints' => $checkpoints,
            'agencies'    => Database::select("SELECT id, name FROM agencies WHERE is_active=1 ORDER BY name"),
            'q'           => $q,
            'agencyF'     => $agencyF,
            'statusF'     => $statusF,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('controle/checkpoints/form', [
            'title'      => 'Nouveau poste de contrôle',
            'checkpoint' => null,
            'agencies'   => $this->scopedAgencies(),
            'lines'      => Database::select("SELECT id, code, name FROM bus_lines WHERE is_active=1 ORDER BY code"),
        ]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('controle.manage')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $data = $this->validate($request, [
            'name'       => 'required|min:2|max:100',
            'agency_id'  => 'required|integer',
            'line_id'    => 'integer',
            'km_on_line' => 'numeric',
        ]);
        $this->ensureAgencyAllowed((int)$data['agency_id']);

        $id = (int)Database::insert(
            "INSERT INTO checkpoints (agency_id, line_id, name, km_on_line, is_active)
             VALUES (?, ?, ?, ?, ?)",
            [
                (int)$data['agency_id'],
                !empty($data['line_id']) ? (int)$data['line_id'] : null,
                $data['name'],
                isset($data['km_on_line']) && $data['km_on_line'] !== '' ? (float)$data['km_on_line'] : null,
                (int)$request->input('is_active', 1),
            ]
        );
        AuditLog::record('checkpoint.create', 'checkpoint', $id, ['name' => $data['name']]);
        $this->flash('success', 'Poste de contrôle créé.');
        redirect('controle/checkpoints');
    }

    public function edit(Request $request, string $id): void
    {
        $cp = Database::selectOne("SELECT * FROM checkpoints WHERE id = ?", [(int)$id]);
        if (!$cp) {
            $this->flash('danger', 'Poste introuvable.');
            redirect('controle/checkpoints');
        }
        $this->ensureAgencyAllowed((int)$cp['agency_id']);

        $this->view('controle/checkpoints/form', [
            'title'      => 'Modifier ' . $cp['name'],
            'checkpoint' => $cp,
            'agencies'   => $this->scopedAgencies(),
            'lines'      => Database::select("SELECT id, code, name FROM bus_lines WHERE is_active=1 ORDER BY code"),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        if (!Auth::can('controle.manage')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $cpId = (int)$id;
        $cp = Database::selectOne("SELECT * FROM checkpoints WHERE id = ?", [$cpId]);
        if (!$cp) {
            $this->flash('danger', 'Poste introuvable.');
            redirect('controle/checkpoints');
        }
        $this->ensureAgencyAllowed((int)$cp['agency_id']);

        $data = $this->validate($request, [
            'name'       => 'required|min:2|max:100',
            'agency_id'  => 'required|integer',
            'line_id'    => 'integer',
            'km_on_line' => 'numeric',
        ]);
        $this->ensureAgencyAllowed((int)$data['agency_id']);

        Database::execute(
            "UPDATE checkpoints SET agency_id=?, line_id=?, name=?, km_on_line=?, is_active=? WHERE id=?",
            [
                (int)$data['agency_id'],
                !empty($data['line_id']) ? (int)$data['line_id'] : null,
                $data['name'],
                isset($data['km_on_line']) && $data['km_on_line'] !== '' ? (float)$data['km_on_line'] : null,
                (int)$request->input('is_active', 0),
                $cpId,
            ]
        );
        AuditLog::record('checkpoint.update', 'checkpoint', $cpId, ['name' => $data['name']]);
        $this->flash('success', 'Poste mis à jour.');
        redirect('controle/checkpoints');
    }

    public function destroy(Request $request, string $id): void
    {
        if (!Auth::can('controle.manage')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $cpId = (int)$id;
        $usage = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM validations WHERE checkpoint_id = ?", [$cpId]
        )['n'] ?? 0);
        if ($usage > 0) {
            // Désactivation seulement (préservation de l'historique)
            Database::execute("UPDATE checkpoints SET is_active = 0 WHERE id = ?", [$cpId]);
            AuditLog::record('checkpoint.deactivate', 'checkpoint', $cpId);
            $this->flash('warning', "Poste désactivé : {$usage} validation(s) historique(s) empêchent la suppression.");
        } else {
            Database::execute("DELETE FROM checkpoints WHERE id = ?", [$cpId]);
            AuditLog::record('checkpoint.delete', 'checkpoint', $cpId);
            $this->flash('success', 'Poste supprimé.');
        }
        redirect('controle/checkpoints');
    }

    /** Liste agences accessibles à l'utilisateur courant pour les selects. */
    private function scopedAgencies(): array
    {
        $user = Auth::user();
        if (Auth::role() === 'admin' || empty($user['agency_id'])) {
            return Database::select("SELECT id, name FROM agencies WHERE is_active=1 ORDER BY name");
        }
        return Database::select(
            "SELECT id, name FROM agencies WHERE is_active=1 AND id = ? ORDER BY name",
            [(int)$user['agency_id']]
        );
    }

    private function ensureAgencyAllowed(int $agencyId): void
    {
        $user = Auth::user();
        if (Auth::role() === 'admin' || empty($user['agency_id'])) return;
        if ((int)$user['agency_id'] !== $agencyId) {
            $this->flash('danger', "Vous ne pouvez gérer que les postes de votre agence.");
            redirect('controle/checkpoints');
        }
    }
}
