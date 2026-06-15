<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;

final class TariffConfigController extends Controller
{
    // ─── Configuration des 5 tables ───────────────────────────────────────────
    private const TYPES = [
        'ticket-types' => [
            'table'   => 'tariff_ticket_types',
            'label'   => 'Types de billets',
            'fk_col'  => 'ticket_type',
            'icon'    => 'ticket',
        ],
        'passenger-categories' => [
            'table'   => 'tariff_passenger_categories',
            'label'   => 'Catégories de passagers',
            'fk_col'  => 'passenger_category',
            'icon'    => 'users',
        ],
        'travel-classes' => [
            'table'   => 'tariff_travel_classes',
            'label'   => 'Classes de voyage',
            'fk_col'  => 'travel_class',
            'icon'    => 'armchair',
        ],
        'baggage-natures' => [
            'table'   => 'tariff_baggage_natures',
            'label'   => 'Natures de bagages',
            'fk_col'  => null,  // référencée par baggage_tariffs.baggage_nature_id (FK id, pas slug)
            'icon'    => 'package',
        ],
        'services' => [
            'table'   => 'tariff_services',
            'label'   => 'Services inclus',
            'fk_col'  => null,  // référencée par tariff_service_map.service_id (FK id)
            'icon'    => 'star',
        ],
    ];

    // ─── index ────────────────────────────────────────────────────────────────
    public function index(Request $request): void
    {
        $sections = [];
        foreach (self::TYPES as $key => $cfg) {
            $sections[$key] = [
                'meta' => array_merge(['key' => $key], $cfg),
                'rows' => Database::select(
                    "SELECT * FROM {$cfg['table']} ORDER BY sort_order ASC, id ASC"
                ),
            ];
        }

        $this->view('referentiel/tariffs/config', [
            'title'    => 'Configuration des tarifs',
            'sections' => $sections,
        ]);
    }

    // ─── store ────────────────────────────────────────────────────────────────
    public function store(Request $request, string $type): void
    {
        $cfg  = $this->resolveType($type);
        $data = $this->validateRow($request);

        try {
            Database::execute(
                "INSERT INTO {$cfg['table']} (slug, label, icon, color_class, description, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)",
                [$data['slug'], $data['label'], $data['icon'], $data['color_class'],
                 $data['description'] ?: null, $data['sort_order']]
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                $this->flash('danger', "Le slug « {$data['slug']} » est déjà utilisé dans cette section.");
            } else {
                $this->flash('danger', $e->getMessage());
            }
            back(); return;
        }

        $this->flash('success', "Élément ajouté dans « {$cfg['label']} ».");
        redirect("referentiel/tariffs/config#{$type}");
    }

    // ─── update ───────────────────────────────────────────────────────────────
    public function update(Request $request, string $type, string $id): void
    {
        $cfg = $this->resolveType($type);
        $row = Database::selectOne("SELECT id FROM {$cfg['table']} WHERE id=?", [(int)$id]);
        if (!$row) {
            $this->flash('danger', 'Élément introuvable.');
            redirect('referentiel/tariffs/config'); return;
        }

        $data = $this->validateRow($request);
        $data['is_active'] = (int)$request->input('is_active', 0);

        try {
            Database::execute(
                "UPDATE {$cfg['table']}
                 SET slug=?, label=?, icon=?, color_class=?, description=?, sort_order=?, is_active=?
                 WHERE id=?",
                [$data['slug'], $data['label'], $data['icon'], $data['color_class'],
                 $data['description'] ?: null, $data['sort_order'], $data['is_active'], (int)$id]
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                $this->flash('danger', "Le slug « {$data['slug']} » est déjà utilisé par un autre élément.");
            } else {
                $this->flash('danger', $e->getMessage());
            }
            back(); return;
        }

        $this->flash('success', 'Élément mis à jour.');
        redirect("referentiel/tariffs/config#{$type}");
    }

    // ─── destroy ──────────────────────────────────────────────────────────────
    public function destroy(Request $request, string $type, string $id): void
    {
        $cfg = $this->resolveType($type);
        $row = Database::selectOne("SELECT id, slug FROM {$cfg['table']} WHERE id=?", [(int)$id]);
        if (!$row) {
            $this->flash('danger', 'Élément introuvable.');
            redirect('referentiel/tariffs/config'); return;
        }

        // Vérifier l'utilisation selon le type de FK
        if ($cfg['fk_col'] !== null) {
            // FK par slug (ticket_type, passenger_category, travel_class)
            $used = Database::selectOne(
                "SELECT COUNT(*) AS n FROM tariffs WHERE {$cfg['fk_col']} = ?",
                [$row['slug']]
            );
            if ((int)($used['n'] ?? 0) > 0) {
                $this->flash('danger', "Impossible de supprimer : {$used['n']} tarif(s) référence(nt) « {$row['slug']} ».");
                redirect("referentiel/tariffs/config#{$type}"); return;
            }
        } elseif ($cfg['table'] === 'tariff_baggage_natures') {
            // FK par id (JSON) dans baggage_tariffs
            $used = Database::selectOne(
                "SELECT COUNT(*) AS n FROM baggage_tariffs WHERE JSON_CONTAINS(baggage_nature_ids, ?)",
                [json_encode((int)$id)]
            );
            if ((int)($used['n'] ?? 0) > 0) {
                $this->flash('danger', "Impossible de supprimer : {$used['n']} tarif(s) bagage référence(nt) cette nature.");
                redirect("referentiel/tariffs/config#{$type}"); return;
            }
        } elseif ($cfg['table'] === 'tariff_services') {
            // FK par id dans tariff_service_map
            $used = Database::selectOne(
                "SELECT COUNT(*) AS n FROM tariff_service_map WHERE service_id = ?",
                [(int)$id]
            );
            if ((int)($used['n'] ?? 0) > 0) {
                $this->flash('danger', "Impossible de supprimer : ce service est inclus dans {$used['n']} tarif(s) passager.");
                redirect("referentiel/tariffs/config#{$type}"); return;
            }
        }

        Database::execute("DELETE FROM {$cfg['table']} WHERE id=?", [(int)$id]);
        $this->flash('success', 'Élément supprimé.');
        redirect("referentiel/tariffs/config#{$type}");
    }

    // ─── toggle is_active ─────────────────────────────────────────────────────
    public function toggle(Request $request, string $type, string $id): void
    {
        $cfg = $this->resolveType($type);
        Database::execute(
            "UPDATE {$cfg['table']} SET is_active = 1 - is_active WHERE id=?",
            [(int)$id]
        );
        redirect("referentiel/tariffs/config#{$type}");
    }

    // ─── reorder (AJAX) ───────────────────────────────────────────────────────
    public function reorder(Request $request, string $type): void
    {
        $cfg = $this->resolveType($type);
        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            $this->json(['ok' => false, 'error' => 'ids must be an array'], 400); return;
        }
        foreach ($ids as $i => $id) {
            Database::execute(
                "UPDATE {$cfg['table']} SET sort_order=? WHERE id=?",
                [$i + 1, (int)$id]
            );
        }
        $this->json(['ok' => true]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function resolveType(string $type): array
    {
        if (!array_key_exists($type, self::TYPES)) {
            $this->flash('danger', 'Type de configuration invalide.');
            redirect('referentiel/tariffs/config');
            exit;
        }
        return array_merge(['key' => $type], self::TYPES[$type]);
    }

    private function validateRow(Request $request): array
    {
        $slug        = trim(strtolower((string)$request->input('slug', '')));
        $label       = trim((string)$request->input('label', ''));
        $icon        = trim((string)$request->input('icon', ''));
        $color_class = trim((string)$request->input('color_class', ''));
        $description = trim((string)$request->input('description', ''));
        $sort_order  = max(0, (int)$request->input('sort_order', 0));

        if (!preg_match('/^[a-z0-9_]{1,50}$/', $slug)) {
            $this->flash('danger', 'Slug invalide : minuscules, chiffres et _ uniquement, max 50 caractères.');
            back(); exit;
        }
        if ($label === '' || mb_strlen($label) > 100) {
            $this->flash('danger', 'Le libellé est requis (max 100 caractères).');
            back(); exit;
        }
        if ($icon === '' || mb_strlen($icon) > 50) {
            $this->flash('danger', "Le nom d'icône Lucide est requis (max 50 caractères).");
            back(); exit;
        }
        if ($color_class === '') {
            $this->flash('danger', 'Veuillez choisir une couleur.');
            back(); exit;
        }

        return compact('slug', 'label', 'icon', 'color_class', 'description', 'sort_order');
    }
}
