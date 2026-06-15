<?php

declare(strict_types=1);

namespace CityBus\Controllers\Treasury;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

final class TreasuryCategoryController extends Controller
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
        $categories = Database::select(
            "SELECT tc.*,
                    (SELECT COUNT(*) FROM treasury_transactions tt WHERE tt.category_id = tc.id) AS tx_count
             FROM treasury_categories tc
             ORDER BY tc.sort_order ASC, tc.label ASC"
        );

        $this->view('treasury/categories/index', [
            'title'      => 'Catégories de transactions',
            'categories' => $categories,
            'colors'     => self::COLORS,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('treasury/categories/form', [
            'title'    => 'Nouvelle catégorie',
            'category' => null,
            'colors'   => self::COLORS,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validateData($request);
        if (!$data) return;

        $exists = Database::selectOne("SELECT id FROM treasury_categories WHERE code = ?", [$data['code']]);
        if ($exists) {
            $this->flash('danger', "Le code « {$data['code']} » existe déjà.");
            back(); return;
        }

        $id = (int)Database::insert(
            "INSERT INTO treasury_categories (code, label, type, color, is_active, sort_order)
             VALUES (?,?,?,?,?,?)",
            [$data['code'], $data['label'], $data['type'], $data['color'], $data['is_active'], $data['sort_order']]
        );
        AuditLog::record('treasury_category.create', 'treasury_category', $id, ['code' => $data['code']]);
        $this->flash('success', "Catégorie « {$data['label']} » créée.");
        redirect('finance/treasury/categories');
    }

    public function edit(Request $request, string $id): void
    {
        $category = Database::selectOne("SELECT * FROM treasury_categories WHERE id = ?", [(int)$id]);
        if (!$category) { $this->flash('danger', 'Introuvable.'); redirect('finance/treasury/categories'); }

        $this->view('treasury/categories/form', [
            'title'    => 'Modifier la catégorie',
            'category' => $category,
            'colors'   => self::COLORS,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $category = Database::selectOne("SELECT * FROM treasury_categories WHERE id = ?", [(int)$id]);
        if (!$category) { $this->flash('danger', 'Introuvable.'); redirect('finance/treasury/categories'); }

        if ($category['is_system']) {
            $this->flash('danger', 'Les catégories système ne peuvent pas être modifiées.');
            redirect('finance/treasury/categories'); return;
        }

        $data = $this->validateData($request);
        if (!$data) return;

        $exists = Database::selectOne(
            "SELECT id FROM treasury_categories WHERE code = ? AND id != ?", [$data['code'], (int)$id]
        );
        if ($exists) {
            $this->flash('danger', "Le code « {$data['code']} » est déjà utilisé.");
            back(); return;
        }

        Database::execute(
            "UPDATE treasury_categories SET code=?, label=?, type=?, color=?, is_active=?, sort_order=? WHERE id=?",
            [$data['code'], $data['label'], $data['type'], $data['color'], $data['is_active'], $data['sort_order'], (int)$id]
        );

        AuditLog::record('treasury_category.update', 'treasury_category', (int)$id);
        $this->flash('success', "Catégorie « {$data['label']} » mise à jour.");
        redirect('finance/treasury/categories');
    }

    public function destroy(Request $request, string $id): void
    {
        $category = Database::selectOne("SELECT * FROM treasury_categories WHERE id = ?", [(int)$id]);
        if (!$category) { $this->flash('danger', 'Introuvable.'); redirect('finance/treasury/categories'); }

        if ($category['is_system']) {
            $this->flash('danger', 'Les catégories système ne peuvent pas être supprimées.');
            redirect('finance/treasury/categories'); return;
        }

        $count = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM treasury_transactions WHERE category_id = ?", [(int)$id]
        )['n'] ?? 0);

        if ($count > 0) {
            $this->flash('danger', "Impossible : $count transaction(s) utilisent cette catégorie. Désactivez-la plutôt.");
            redirect('finance/treasury/categories'); return;
        }

        Database::execute("DELETE FROM treasury_categories WHERE id = ?", [(int)$id]);
        AuditLog::record('treasury_category.delete', 'treasury_category', (int)$id);
        $this->flash('success', 'Catégorie supprimée.');
        redirect('finance/treasury/categories');
    }

    /** Retourne les catégories actives groupées par type */
    public static function loadByType(): array
    {
        $rows = Database::select(
            "SELECT id, code, label, type, color FROM treasury_categories WHERE is_active = 1 ORDER BY sort_order ASC, label ASC"
        );
        $out = ['encaissement' => [], 'decaissement' => []];
        foreach ($rows as $r) {
            $out[$r['type']][] = $r;
        }
        return $out;
    }

    private function validateData(Request $request): ?array
    {
        $code  = trim(strtolower((string)$request->input('code', '')));
        $label = trim((string)$request->input('label', ''));
        $type  = (string)$request->input('type', '');

        if ($code === '' || !preg_match('/^[a-z0-9_-]+$/', $code)) {
            $this->flash('danger', 'Le code est invalide.');
            back(); return null;
        }
        if (mb_strlen($label) < 2 || mb_strlen($label) > 120) {
            $this->flash('danger', 'Le libellé est requis (2-120 caractères).');
            back(); return null;
        }
        if (!in_array($type, ['encaissement', 'decaissement'], true)) {
            $this->flash('danger', 'Le type doit être encaissement ou décaissement.');
            back(); return null;
        }

        return [
            'code'       => $code,
            'label'      => $label,
            'type'       => $type,
            'color'      => in_array($request->input('color'), array_keys(self::COLORS), true)
                            ? $request->input('color') : 'slate',
            'is_active'  => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => max(0, (int)$request->input('sort_order', 100)),
        ];
    }
}
