<?php

declare(strict_types=1);

namespace CityBus\Controllers\Cargo;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

final class ParcelTariffController extends Controller
{
    // ─── Catégories suggérées par défaut (l'utilisateur peut en saisir d'autres) ──
    public const DEFAULT_CATEGORIES = [
        'document'   => 'Document',
        'colis'      => 'Colis standard',
        'aliments'   => 'Aliments',
        'fragile'    => 'Fragile',
        'electronique' => 'Électronique',
        'textile'    => 'Textile / Vêtements',
        'medicament' => 'Médicaments',
        'special'    => 'Spécial / Hors gabarit',
    ];

    /** Charge les catégories actives depuis fret_categories */
    public static function loadCategories(): array
    {
        $rows = Database::select(
            "SELECT slug, label FROM fret_categories WHERE is_active = 1 ORDER BY sort_order ASC, label ASC"
        );
        if (empty($rows)) {
            return self::DEFAULT_CATEGORIES;
        }
        $cats = [];
        foreach ($rows as $r) {
            $cats[$r['slug']] = $r['label'];
        }
        return $cats;
    }

    public function index(Request $request): void
    {
        $tariffs = Database::select(
            "SELECT id, label, category, price_per_kg, min_price_fcfa,
                    is_active, valid_from, valid_until, sort_order
             FROM parcel_tariffs
             ORDER BY category ASC, sort_order ASC, id ASC"
        );

        // Grouper par catégorie pour l'affichage
        $byCategory = [];
        foreach ($tariffs as $t) {
            $byCategory[$t['category']][] = $t;
        }

        $this->view('cargo/tariffs/index', [
            'title'      => 'Tarifs fret',
            'tariffs'    => $tariffs,
            'byCategory' => $byCategory,
            'categories' => self::loadCategories(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('cargo/tariffs/form', [
            'title'      => 'Nouveau tarif fret',
            'tariff'     => null,
            'categories' => self::loadCategories(),
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validateData($request);
        $id = (int)Database::insert(
            "INSERT INTO parcel_tariffs
                (label, category, price_per_kg, min_price_fcfa,
                 valid_from, valid_until, is_active, sort_order)
             VALUES (?,?,?,?,?,?,?,?)",
            $this->params($data)
        );
        AuditLog::record('parcel_tariff.create', 'parcel_tariff', $id, [
            'label'    => $data['label'],
            'category' => $data['category'],
        ]);
        $this->flash('success', 'Tarif fret créé.');
        redirect('cargo/tariffs');
    }

    public function edit(Request $request, string $id): void
    {
        $tariff = Database::selectOne("SELECT * FROM parcel_tariffs WHERE id=?", [(int)$id]);
        if (!$tariff) { $this->flash('danger', 'Introuvable.'); redirect('cargo/tariffs'); }
        $this->view('cargo/tariffs/form', [
            'title'      => 'Modifier le tarif fret',
            'tariff'     => $tariff,
            'categories' => self::loadCategories(),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $data = $this->validateData($request);
        Database::execute(
            "UPDATE parcel_tariffs
                SET label=?, category=?, price_per_kg=?, min_price_fcfa=?,
                    valid_from=?, valid_until=?, is_active=?, sort_order=?
              WHERE id=?",
            [...$this->params($data), (int)$id]
        );
        AuditLog::record('parcel_tariff.update', 'parcel_tariff', (int)$id);
        $this->flash('success', 'Tarif fret mis à jour.');
        redirect('cargo/tariffs');
    }

    public function destroy(Request $request, string $id): void
    {
        $usage = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM parcels WHERE parcel_tariff_id=?", [(int)$id]
        )['n'] ?? 0);
        if ($usage > 0) {
            Database::execute("UPDATE parcel_tariffs SET is_active=0 WHERE id=?", [(int)$id]);
            $this->flash('warning', "Tarif désactivé (utilisé par $usage colis).");
        } else {
            Database::execute("DELETE FROM parcel_tariffs WHERE id=?", [(int)$id]);
            $this->flash('success', 'Tarif supprimé.');
        }
        AuditLog::record('parcel_tariff.delete', 'parcel_tariff', (int)$id);
        redirect('cargo/tariffs');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function validateData(Request $request): array
    {
        $category = trim((string)$request->input('category', ''));
        if ($category === '') {
            $this->flash('danger', 'La catégorie est requise.');
            back(); exit;
        }
        $pricePerKg = max(0, (int)$request->input('price_per_kg', 0));
        if ($pricePerKg <= 0) {
            $this->flash('danger', 'Le prix au kg doit être supérieur à 0.');
            back(); exit;
        }
        return [
            'label'         => trim((string)$request->input('label', '')),
            'category'      => $category,
            'price_per_kg'  => $pricePerKg,
            'min_price_fcfa'=> max(0, (int)$request->input('min_price_fcfa', 0)),
            'valid_from'    => trim((string)$request->input('valid_from', ''))  ?: null,
            'valid_until'   => trim((string)$request->input('valid_until', '')) ?: null,
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
            'sort_order'    => max(0, (int)$request->input('sort_order', 100)),
        ];
    }

    private function params(array $d): array
    {
        return [
            $d['label'],
            $d['category'],
            $d['price_per_kg'],
            $d['min_price_fcfa'],
            $d['valid_from'],
            $d['valid_until'],
            $d['is_active'],
            $d['sort_order'],
        ];
    }
}
