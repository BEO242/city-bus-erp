<?php

declare(strict_types=1);

namespace CityBus\Controllers\Cargo;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

final class FretCategoryController extends Controller
{
    public const COLORS = [
        'slate'   => 'Gris',
        'red'     => 'Rouge',
        'orange'  => 'Orange',
        'amber'   => 'Jaune',
        'green'   => 'Vert',
        'blue'    => 'Bleu',
        'violet'  => 'Violet',
        'pink'    => 'Rose',
    ];

    public function create(Request $request): void
    {
        $this->view('cargo/categories/form', [
            'title'    => 'Nouvelle catégorie',
            'category' => null,
            'colors'   => self::COLORS,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validateData($request);
        if (!$data) return;

        // Vérifier unicité du slug
        $exists = Database::selectOne("SELECT id FROM fret_categories WHERE slug = ?", [$data['slug']]);
        if ($exists) {
            $this->flash('danger', "Le slug « {$data['slug']} » existe déjà.");
            back(); return;
        }

        $id = (int)Database::insert(
            "INSERT INTO fret_categories (slug, label, description, color, price_per_kg, min_price_fcfa, is_active, sort_order)
             VALUES (?,?,?,?,?,?,?,?)",
            [$data['slug'], $data['label'], $data['description'], $data['color'],
             $data['price_per_kg'], $data['min_price_fcfa'],
             $data['is_active'], $data['sort_order']]
        );
        AuditLog::record('fret_category.create', 'fret_category', $id, ['slug' => $data['slug']]);
        $this->flash('success', "Catégorie « {$data['label']} » créée.");
        redirect('referentiel/tariffs?tab=cargo');
    }

    public function edit(Request $request, string $id): void
    {
        $category = Database::selectOne("SELECT * FROM fret_categories WHERE id = ?", [(int)$id]);
        if (!$category) { $this->flash('danger', 'Introuvable.'); redirect('referentiel/tariffs?tab=cargo'); }

        $this->view('cargo/categories/form', [
            'title'    => 'Modifier la catégorie',
            'category' => $category,
            'colors'   => self::COLORS,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $category = Database::selectOne("SELECT * FROM fret_categories WHERE id = ?", [(int)$id]);
        if (!$category) { $this->flash('danger', 'Introuvable.'); redirect('referentiel/tariffs?tab=cargo'); }

        $data = $this->validateData($request, (int)$id);
        if (!$data) return;

        // Vérifier unicité du slug (sauf pour soi-même)
        $exists = Database::selectOne(
            "SELECT id FROM fret_categories WHERE slug = ? AND id != ?", [$data['slug'], (int)$id]
        );
        if ($exists) {
            $this->flash('danger', "Le slug « {$data['slug']} » est déjà utilisé par une autre catégorie.");
            back(); return;
        }

        $oldSlug = $category['slug'];
        Database::execute(
            "UPDATE fret_categories
                SET slug=?, label=?, description=?, color=?, price_per_kg=?, min_price_fcfa=?,
                    is_active=?, sort_order=?
              WHERE id=?",
            [$data['slug'], $data['label'], $data['description'], $data['color'],
             $data['price_per_kg'], $data['min_price_fcfa'],
             $data['is_active'], $data['sort_order'], (int)$id]
        );

        // Si le slug a changé, mettre à jour les tables dépendantes
        if ($oldSlug !== $data['slug']) {
            Database::execute(
                "UPDATE parcel_tariffs SET category = ? WHERE category = ?",
                [$data['slug'], $oldSlug]
            );
            Database::execute(
                "UPDATE parcels SET parcel_type = ? WHERE parcel_type = ?",
                [$data['slug'], $oldSlug]
            );
        }

        AuditLog::record('fret_category.update', 'fret_category', (int)$id);
        $this->flash('success', "Catégorie « {$data['label']} » mise à jour.");
        redirect('referentiel/tariffs?tab=cargo');
    }

    public function destroy(Request $request, string $id): void
    {
        $category = Database::selectOne("SELECT * FROM fret_categories WHERE id = ?", [(int)$id]);
        if (!$category) { $this->flash('danger', 'Introuvable.'); redirect('referentiel/tariffs?tab=cargo'); }

        $tariffCount = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM parcel_tariffs WHERE category = ?", [$category['slug']]
        )['n'] ?? 0);
        $parcelCount = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM parcels WHERE parcel_type = ?", [$category['slug']]
        )['n'] ?? 0);

        if ($tariffCount > 0 || $parcelCount > 0) {
            $this->flash('danger',
                "Impossible de supprimer « {$category['label']} » : " .
                "$tariffCount tarif(s) et $parcelCount colis y sont rattachés. Désactivez-la plutôt."
            );
            redirect('referentiel/tariffs?tab=cargo'); return;
        }

        Database::execute("DELETE FROM fret_categories WHERE id = ?", [(int)$id]);
        AuditLog::record('fret_category.delete', 'fret_category', (int)$id);
        $this->flash('success', "Catégorie supprimée.");
        redirect('referentiel/tariffs?tab=cargo');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function validateData(Request $request, int $excludeId = 0): ?array
    {
        $slug  = trim(strtolower((string)$request->input('slug', '')));
        $label = trim((string)$request->input('label', ''));

        if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
            $this->flash('danger', 'Le slug est invalide (lettres minuscules, chiffres, tirets et underscores uniquement).');
            back(); return null;
        }
        if (mb_strlen($slug) > 60) {
            $this->flash('danger', 'Le slug ne doit pas dépasser 60 caractères.');
            back(); return null;
        }
        if ($label === '' || mb_strlen($label) > 100) {
            $this->flash('danger', 'Le libellé est requis (max 100 caractères).');
            back(); return null;
        }

        $pricePerKg = max(0, (int)$request->input('price_per_kg', 0));
        if ($pricePerKg <= 0) {
            $this->flash('danger', 'Le prix au kg doit être supérieur à 0.');
            back(); return null;
        }

        return [
            'slug'           => $slug,
            'label'          => $label,
            'description'    => trim((string)$request->input('description', '')) ?: null,
            'color'          => in_array($request->input('color'), array_keys(self::COLORS), true)
                                ? $request->input('color') : 'slate',
            'price_per_kg'   => $pricePerKg,
            'min_price_fcfa' => max(0, (int)$request->input('min_price_fcfa', 0)),
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
            'sort_order'     => max(0, (int)$request->input('sort_order', 100)),
        ];
    }
}
