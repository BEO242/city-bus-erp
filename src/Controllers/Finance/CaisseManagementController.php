<?php

declare(strict_types=1);

namespace CityBus\Controllers\Finance;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

final class CaisseManagementController extends Controller
{
    public const TYPES = [
        'principale'  => 'Caisse principale',
        'point_vente' => 'Point de vente',
        'agence'      => 'Agence',
        'mobile'      => 'Mobile',
    ];

    public const TYPE_COLORS = [
        'principale'  => ['bg' => 'bg-cb-bg',      'text' => 'text-cb-primary',  'dot' => 'bg-cb-primary'],
        'point_vente' => ['bg' => 'bg-emerald-50',  'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
        'agence'      => ['bg' => 'bg-blue-50',     'text' => 'text-blue-700',    'dot' => 'bg-blue-500'],
        'mobile'      => ['bg' => 'bg-amber-50',    'text' => 'text-amber-700',   'dot' => 'bg-amber-500'],
    ];

    public function index(Request $request): void
    {
        $caisses = Database::select(
            "SELECT c.*, a.name AS agency_name,
                    (SELECT COUNT(*) FROM cash_registers cr WHERE cr.agency_id = c.agency_id AND cr.status = 'ouverte') AS sessions_ouvertes,
                    (SELECT COUNT(*) FROM treasury_transactions tt
                     JOIN cash_registers cr ON cr.id = tt.cash_register_id
                     WHERE cr.agency_id = c.agency_id) AS tx_count
             FROM caisses c
             JOIN agencies a ON a.id = c.agency_id
             ORDER BY c.sort_order ASC, a.name ASC, c.name ASC"
        );

        $agencies = Database::select("SELECT id, name FROM agencies WHERE is_active = 1 ORDER BY name");

        $this->view('finance/caisses/index', [
            'title'    => 'Postes de caisse',
            'caisses'  => $caisses,
            'agencies' => $agencies,
            'types'    => self::TYPES,
            'colors'   => self::TYPE_COLORS,
        ]);
    }

    public function create(Request $request): void
    {
        $agencies = Database::select(
            "SELECT id, name FROM agencies WHERE is_active = 1 ORDER BY name"
        );

        $this->view('finance/caisses/form', [
            'title'    => 'Nouvelle caisse',
            'caisse'   => null,
            'agencies' => $agencies,
            'types'    => self::TYPES,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validateData($request);
        if (!$data) return;

        $exists = Database::selectOne("SELECT id FROM caisses WHERE code = ?", [$data['code']]);
        if ($exists) {
            $this->flash('danger', "Le code « {$data['code']} » existe déjà.");
            back(); return;
        }

        $id = (int)Database::insert(
            "INSERT INTO caisses (code, name, type, agency_id, description, is_active, sort_order)
             VALUES (?,?,?,?,?,?,?)",
            [$data['code'], $data['name'], $data['type'], $data['agency_id'],
             $data['description'], $data['is_active'], $data['sort_order']]
        );

        AuditLog::record('caisse.create', 'caisse', $id, ['code' => $data['code']]);
        $this->flash('success', "Caisse « {$data['name']} » créée.");
        redirect('finance/caisses');
    }

    public function edit(Request $request, string $id): void
    {
        $caisse = Database::selectOne("SELECT * FROM caisses WHERE id = ?", [(int)$id]);
        if (!$caisse) { $this->flash('danger', 'Introuvable.'); redirect('finance/caisses'); }

        $agencies = Database::select(
            "SELECT id, name FROM agencies WHERE is_active = 1 ORDER BY name"
        );

        $this->view('finance/caisses/form', [
            'title'    => 'Modifier la caisse',
            'caisse'   => $caisse,
            'agencies' => $agencies,
            'types'    => self::TYPES,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $caisse = Database::selectOne("SELECT * FROM caisses WHERE id = ?", [(int)$id]);
        if (!$caisse) { $this->flash('danger', 'Introuvable.'); redirect('finance/caisses'); }

        $data = $this->validateData($request);
        if (!$data) return;

        $exists = Database::selectOne(
            "SELECT id FROM caisses WHERE code = ? AND id != ?", [$data['code'], (int)$id]
        );
        if ($exists) {
            $this->flash('danger', "Le code « {$data['code']} » est déjà utilisé.");
            back(); return;
        }

        Database::execute(
            "UPDATE caisses SET code=?, name=?, type=?, agency_id=?, description=?, is_active=?, sort_order=? WHERE id=?",
            [$data['code'], $data['name'], $data['type'], $data['agency_id'],
             $data['description'], $data['is_active'], $data['sort_order'], (int)$id]
        );

        AuditLog::record('caisse.update', 'caisse', (int)$id);
        $this->flash('success', "Caisse « {$data['name']} » mise à jour.");
        redirect('finance/caisses');
    }

    public function destroy(Request $request, string $id): void
    {
        $caisse = Database::selectOne("SELECT * FROM caisses WHERE id = ?", [(int)$id]);
        if (!$caisse) { $this->flash('danger', 'Introuvable.'); redirect('finance/caisses'); }

        // Vérifier s'il y a des sessions actives sur l'agence de cette caisse
        $sessions = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM cash_registers WHERE agency_id = ? AND status = 'ouverte'",
            [(int)$caisse['agency_id']]
        )['n'] ?? 0);

        if ($sessions > 0) {
            $this->flash('danger', "Impossible de supprimer : $sessions session(s) de caisse ouverte(s) sur cette agence.");
            redirect('finance/caisses'); return;
        }

        Database::execute("DELETE FROM caisses WHERE id = ?", [(int)$id]);
        AuditLog::record('caisse.delete', 'caisse', (int)$id);
        $this->flash('success', "Caisse « {$caisse['name']} » supprimée.");
        redirect('finance/caisses');
    }

    public function toggle(Request $request, string $id): void
    {
        $caisse = Database::selectOne("SELECT * FROM caisses WHERE id = ?", [(int)$id]);
        if (!$caisse) { $this->flash('danger', 'Introuvable.'); redirect('finance/caisses'); }

        $newState = $caisse['is_active'] ? 0 : 1;
        Database::execute("UPDATE caisses SET is_active = ? WHERE id = ?", [$newState, (int)$id]);
        AuditLog::record('caisse.toggle', 'caisse', (int)$id, ['is_active' => $newState]);
        $this->flash('success', "Poste « {$caisse['name']} » " . ($newState ? 'activé' : 'désactivé') . '.');
        redirect('finance/caisses');
    }

    /** Charge les postes actifs pour les selects opérationnels */
    public static function loadActive(): array
    {
        return Database::select(
            "SELECT c.*, a.name AS agency_name
             FROM caisses c
             JOIN agencies a ON a.id = c.agency_id
             WHERE c.is_active = 1
             ORDER BY c.sort_order, c.name"
        );
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function validateData(Request $request): ?array
    {
        $code = trim(strtolower((string)$request->input('code', '')));
        $name = trim((string)$request->input('name', ''));
        $type = (string)$request->input('type', '');
        $agencyId = (int)$request->input('agency_id', 0);

        if ($code === '' || !preg_match('/^[a-z0-9_-]+$/', $code)) {
            $this->flash('danger', 'Le code est invalide (minuscules, chiffres, tirets et underscores uniquement).');
            back(); return null;
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $this->flash('danger', 'Le nom est requis (2-120 caractères).');
            back(); return null;
        }
        if (!array_key_exists($type, self::TYPES)) {
            $this->flash('danger', 'Type de caisse invalide.');
            back(); return null;
        }
        if ($agencyId <= 0 || !Database::selectOne("SELECT id FROM agencies WHERE id = ?", [$agencyId])) {
            $this->flash('danger', 'Agence invalide.');
            back(); return null;
        }

        return [
            'code'        => $code,
            'name'        => $name,
            'type'        => $type,
            'agency_id'   => $agencyId,
            'description' => trim((string)$request->input('description', '')) ?: null,
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
            'sort_order'  => max(0, (int)$request->input('sort_order', 100)),
        ];
    }
}
