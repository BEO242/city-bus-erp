<?php

declare(strict_types=1);

namespace CityBus\Controllers\RH;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

final class RhPositionController extends Controller
{
    public const COLORS = [
        'slate'  => 'Gris',
        'red'    => 'Rouge',
        'orange' => 'Orange',
        'amber'  => 'Jaune',
        'green'  => 'Vert',
        'blue'   => 'Bleu',
        'violet' => 'Violet',
        'pink'   => 'Rose',
    ];

    public function index(Request $request): void
    {
        $positions = Database::select(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM employees e WHERE e.position = p.code AND e.deleted_at IS NULL) AS employee_count
             FROM rh_positions p
             ORDER BY p.sort_order ASC, p.label ASC"
        );

        // Grouper par département
        $byDept = [];
        foreach ($positions as $pos) {
            $dept = $pos['department'] ?: 'Autre';
            $byDept[$dept][] = $pos;
        }

        $this->view('rh/positions/index', [
            'title'     => 'Postes & fonctions',
            'positions' => $positions,
            'byDept'    => $byDept,
            'colors'    => self::COLORS,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('rh/positions/form', [
            'title'    => 'Nouveau poste',
            'position' => null,
            'colors'   => self::COLORS,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validateData($request);
        if (!$data) return;

        $exists = Database::selectOne("SELECT id FROM rh_positions WHERE code = ?", [$data['code']]);
        if ($exists) {
            $this->flash('danger', "Le code « {$data['code']} » existe déjà.");
            back(); return;
        }

        $id = (int)Database::insert(
            "INSERT INTO rh_positions (code, label, department, description, color, is_active, sort_order)
             VALUES (?,?,?,?,?,?,?)",
            [$data['code'], $data['label'], $data['department'], $data['description'],
             $data['color'], $data['is_active'], $data['sort_order']]
        );
        AuditLog::record('rh_position.create', 'rh_position', $id, ['code' => $data['code']]);
        $this->flash('success', "Poste « {$data['label']} » créé.");
        redirect('rh/positions');
    }

    public function edit(Request $request, string $id): void
    {
        $position = Database::selectOne("SELECT * FROM rh_positions WHERE id = ?", [(int)$id]);
        if (!$position) { $this->flash('danger', 'Introuvable.'); redirect('rh/positions'); }

        $this->view('rh/positions/form', [
            'title'    => 'Modifier le poste',
            'position' => $position,
            'colors'   => self::COLORS,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $position = Database::selectOne("SELECT * FROM rh_positions WHERE id = ?", [(int)$id]);
        if (!$position) { $this->flash('danger', 'Introuvable.'); redirect('rh/positions'); }

        $data = $this->validateData($request);
        if (!$data) return;

        $exists = Database::selectOne(
            "SELECT id FROM rh_positions WHERE code = ? AND id != ?", [$data['code'], (int)$id]
        );
        if ($exists) {
            $this->flash('danger', "Le code « {$data['code']} » est déjà utilisé.");
            back(); return;
        }

        $oldCode = $position['code'];
        Database::execute(
            "UPDATE rh_positions
                SET code=?, label=?, department=?, description=?, color=?, is_active=?, sort_order=?
              WHERE id=?",
            [$data['code'], $data['label'], $data['department'], $data['description'],
             $data['color'], $data['is_active'], $data['sort_order'], (int)$id]
        );

        // Cascade sur employees si le code change
        if ($oldCode !== $data['code']) {
            Database::execute(
                "UPDATE employees SET position = ? WHERE position = ?",
                [$data['code'], $oldCode]
            );
        }

        AuditLog::record('rh_position.update', 'rh_position', (int)$id);
        $this->flash('success', "Poste « {$data['label']} » mis à jour.");
        redirect('rh/positions');
    }

    public function destroy(Request $request, string $id): void
    {
        $position = Database::selectOne("SELECT * FROM rh_positions WHERE id = ?", [(int)$id]);
        if (!$position) { $this->flash('danger', 'Introuvable.'); redirect('rh/positions'); }

        $count = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM employees WHERE position = ? AND deleted_at IS NULL",
            [$position['code']]
        )['n'] ?? 0);

        if ($count > 0) {
            $this->flash('danger',
                "Impossible de supprimer « {$position['label']} » : $count employé(s) occupent ce poste. Désactivez-le plutôt."
            );
            redirect('rh/positions'); return;
        }

        Database::execute("DELETE FROM rh_positions WHERE id = ?", [(int)$id]);
        AuditLog::record('rh_position.delete', 'rh_position', (int)$id);
        $this->flash('success', "Poste supprimé.");
        redirect('rh/positions');
    }

    /** Retourne les postes actifs sous forme slug => label (pour les selects). */
    public static function loadPositions(): array
    {
        $rows = Database::select(
            "SELECT code, label FROM rh_positions WHERE is_active = 1 ORDER BY sort_order ASC, label ASC"
        );
        if (empty($rows)) {
            return ['chauffeur'=>'Chauffeur','convoyeur'=>'Convoyeur','controleur'=>'Contrôleur',
                    'caissier'=>'Caissier','superviseur'=>'Superviseur','agent'=>'Agent','admin'=>'Administrateur'];
        }
        $out = [];
        foreach ($rows as $r) $out[$r['code']] = $r['label'];
        return $out;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function validateData(Request $request): ?array
    {
        $code  = trim(strtolower((string)$request->input('code', '')));
        $label = trim((string)$request->input('label', ''));

        if ($code === '' || !preg_match('/^[a-z0-9_-]+$/', $code)) {
            $this->flash('danger', 'Le code est invalide (minuscules, chiffres, tirets et underscores uniquement).');
            back(); return null;
        }
        if (mb_strlen($code) > 30) {
            $this->flash('danger', 'Le code ne doit pas dépasser 30 caractères.');
            back(); return null;
        }
        if ($label === '' || mb_strlen($label) > 100) {
            $this->flash('danger', 'Le libellé est requis (max 100 caractères).');
            back(); return null;
        }

        return [
            'code'        => $code,
            'label'       => $label,
            'department'  => trim((string)$request->input('department', '')) ?: null,
            'description' => trim((string)$request->input('description', '')) ?: null,
            'color'       => in_array($request->input('color'), array_keys(self::COLORS), true)
                             ? $request->input('color') : 'slate',
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
            'sort_order'  => max(0, (int)$request->input('sort_order', 100)),
        ];
    }
}
