<?php

declare(strict_types=1);

namespace CityBus\Controllers\Voyage;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\PricingService;

final class PricingController extends Controller
{
    private PricingService $svc;
    public function __construct() { $this->svc = new PricingService(); }

    public function index(Request $request): void
    {
        if (!Auth::can('voyages.pricing.view')) { back(); return; }
        $this->view('voyages/pricing/index', [
            'title' => 'Règles de pricing dynamique',
            'rules' => $this->svc->listRules(),
            'lines' => Database::select("SELECT id, code, name FROM bus_lines ORDER BY code"),
        ]);
    }

    public function create(Request $request): void
    {
        if (!Auth::can('voyages.pricing.manage')) { back(); return; }
        $this->view('voyages/pricing/edit', [
            'title' => 'Nouvelle règle pricing',
            'rule'  => null,
            'lines' => Database::select("SELECT id, code, name FROM bus_lines ORDER BY code"),
        ]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('voyages.pricing.manage')) { back(); return; }
        try {
            $id = $this->svc->createRule($this->collect($request));
            $this->flash('success', "Règle créée (#$id).");
            $this->redirect('voyages/pricing');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            back();
        }
    }

    public function edit(Request $request, string $id): void
    {
        if (!Auth::can('voyages.pricing.manage')) { back(); return; }
        $rule = Database::selectOne("SELECT * FROM voyage_pricing_rules WHERE id = ?", [(int)$id]);
        if (!$rule) { $this->flash('danger', 'Règle introuvable.'); $this->redirect('voyages/pricing'); return; }
        $this->view('voyages/pricing/edit', [
            'title' => 'Modifier règle',
            'rule'  => $rule,
            'lines' => Database::select("SELECT id, code, name FROM bus_lines ORDER BY code"),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        if (!Auth::can('voyages.pricing.manage')) { back(); return; }
        try {
            $this->svc->updateRule((int)$id, $this->collect($request));
            $this->flash('success', 'Règle mise à jour.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect('voyages/pricing');
    }

    public function destroy(Request $request, string $id): void
    {
        if (!Auth::can('voyages.pricing.manage')) { back(); return; }
        $this->svc->deleteRule((int)$id);
        $this->flash('success', 'Règle supprimée.');
        $this->redirect('voyages/pricing');
    }

    public function applyToTrip(Request $request, string $tripId): void
    {
        if (!Auth::can('voyages.pricing.apply')) { back(); return; }
        $r = $this->svc->recalcTrip((int)$tripId, Auth::user()['id'] ?? null);
        if (!empty($r['skipped'])) {
            $this->flash('warning', 'Recalc ignoré: ' . ($r['reason'] ?? ''));
        } else {
            $this->flash('success', "Pricing recalculé · {$r['count']} changement(s) · load={$r['load_pct']}%, J-{$r['dtd']}");
        }
        back();
    }

    private function collect(Request $request): array
    {
        return [
            'name'           => trim((string)$request->input('name', '')),
            'description'    => $request->input('description'),
            'rule_type'      => $request->input('rule_type'),
            'scope_line_id'  => $request->input('scope_line_id') ? (int)$request->input('scope_line_id') : null,
            'scope_class'    => $request->input('scope_class') ?: null,
            'threshold_min'  => $request->input('threshold_min') !== '' ? (float)$request->input('threshold_min') : null,
            'threshold_max'  => $request->input('threshold_max') !== '' ? (float)$request->input('threshold_max') : null,
            'multiplier'     => (float)$request->input('multiplier', 1.0),
            'delta_fcfa'     => (int)$request->input('delta_fcfa', 0),
            'active'         => $request->input('active') ? 1 : 0,
            'priority'       => (int)$request->input('priority', 100),
            'valid_from'     => $request->input('valid_from') ?: null,
            'valid_until'    => $request->input('valid_until') ?: null,
        ];
    }
}
