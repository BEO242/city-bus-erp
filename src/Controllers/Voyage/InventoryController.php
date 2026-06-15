<?php

declare(strict_types=1);

namespace CityBus\Controllers\Voyage;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\InventoryClass;
use CityBus\Models\TripInventory;
use CityBus\Services\InventoryService;

/**
 * Gestion de l'inventaire (booking classes) pour les voyages.
 *
 * Routes :
 *   GET  /voyages/{id}/inventory          → vue gestion inventaire d'un voyage
 *   POST /voyages/{id}/inventory/{code}   → mise à jour d'une classe
 *   POST /voyages/{id}/inventory/regenerate → régénérer l'inventaire
 *   GET  /admin/inventory-classes         → CRUD du référentiel des classes
 */
final class InventoryController extends Controller
{
    private InventoryService $svc;

    public function __construct()
    {
        $this->svc = new InventoryService();
    }

    /** Page de gestion de l'inventaire d'un voyage. */
    public function tripInventory(Request $request, string $tripId): void
    {
        if (!Auth::can('voyages.inventory.view')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }

        $trip = Database::selectOne(
            "SELECT tr.*, l.code AS line_code, l.name AS line_name,
                    b.code AS bus_code, b.plate, b.seats
             FROM trips tr
             JOIN bus_lines l ON l.id = tr.line_id
             JOIN buses b ON b.id = tr.bus_id
             WHERE tr.id = ?", [$tripId]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        $inventory = TripInventory::forTrip((int)$tripId);

        // Si pas d'inventaire, proposer la génération
        if (empty($inventory)) {
            $this->view('voyages/inventory/empty', [
                'title' => "Inventaire · {$trip['trip_code']}",
                'trip'  => $trip,
            ]);
            return;
        }

        // Stats globales
        $totalCapacity = array_sum(array_column($inventory, 'capacity'));
        $totalSold     = array_sum(array_column($inventory, 'sold_count'));
        $totalReserved = array_sum(array_column($inventory, 'reserved_count'));
        $totalAvail    = $totalCapacity - $totalSold - $totalReserved;
        $loadFactor    = $totalCapacity > 0 ? round($totalSold / $totalCapacity * 100) : 0;
        $totalRevenue  = 0;
        foreach ($inventory as $i) {
            $totalRevenue += (int)$i['price_fcfa'] * (int)$i['sold_count'];
        }

        $this->view('voyages/inventory/show', [
            'title'         => "Inventaire · {$trip['trip_code']}",
            'trip'          => $trip,
            'inventory'     => $inventory,
            'totalCapacity' => $totalCapacity,
            'totalSold'     => $totalSold,
            'totalReserved' => $totalReserved,
            'totalAvail'    => $totalAvail,
            'loadFactor'    => $loadFactor,
            'totalRevenue'  => $totalRevenue,
        ]);
    }

    /** Mise à jour d'une classe pour un voyage. */
    public function updateClass(Request $request, string $tripId, string $classCode): void
    {
        if (!Auth::can('voyages.inventory.manage')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }

        $changes = [];
        if ($request->input('capacity') !== null && $request->input('capacity') !== '') {
            $changes['capacity'] = (int)$request->input('capacity');
        }
        if ($request->input('price_fcfa') !== null && $request->input('price_fcfa') !== '') {
            $changes['price_fcfa'] = (int)$request->input('price_fcfa');
        }
        if ($request->input('overbooking_pct') !== null) {
            $changes['overbooking_pct'] = (int)$request->input('overbooking_pct');
        }
        if ($request->input('blocked_count') !== null) {
            $changes['blocked_count'] = (int)$request->input('blocked_count');
        }
        $changes['price_reason'] = $request->input('price_reason', 'Ajustement manuel');

        try {
            $this->svc->updateClass((int)$tripId, $classCode, $changes);
            $this->flash('success', "Classe $classCode mise à jour.");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    /** Régénérer l'inventaire (utile après changement de bus). */
    public function regenerate(Request $request, string $tripId): void
    {
        if (!Auth::can('voyages.inventory.manage')) {
            $this->flash('danger', 'Permission refusée.');
            back(); return;
        }

        $trip = Database::selectOne(
            "SELECT tr.id, b.seats FROM trips tr
             JOIN buses b ON b.id = tr.bus_id
             WHERE tr.id = ?", [$tripId]
        );
        if (!$trip) { back(); return; }

        // Vérifier qu'aucun ticket n'est encore vendu
        $sold = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM tickets WHERE trip_id = ? AND status NOT IN ('annule') AND deleted_at IS NULL",
            [(int)$tripId]
        )['c'] ?? 0);
        if ($sold > 0) {
            $this->flash('warning', "Régénération impossible : $sold billet(s) déjà vendus.");
            back(); return;
        }

        Database::execute("DELETE FROM trip_inventory WHERE trip_id = ?", [(int)$tripId]);
        $count = $this->svc->generateForTrip((int)$tripId, (int)$trip['seats']);
        $this->flash('success', "Inventaire régénéré : $count classes créées.");
        back();
    }

    // ─── Admin : référentiel des classes ───

    public function classesIndex(Request $request): void
    {
        if (!Auth::can('admin.settings.view')) { $this->flash('danger', 'Permission refusée.'); back(); return; }
        $classes = Database::select(
            "SELECT *, (SELECT COUNT(*) FROM trip_inventory WHERE class_id = c.id) AS used_count
             FROM inventory_classes c ORDER BY sort_order, code"
        );
        $this->view('admin/inventory-classes/index', [
            'title'   => 'Classes d\'inventaire',
            'classes' => $classes,
        ]);
    }

    public function classEdit(Request $request, string $id): void
    {
        if (!Auth::can('admin.settings.edit')) { $this->flash('danger', 'Permission refusée.'); back(); return; }
        $class = Database::selectOne("SELECT * FROM inventory_classes WHERE id = ?", [$id]);
        if (!$class) { http_response_code(404); $this->view('errors/404'); return; }
        $this->view('admin/inventory-classes/form', [
            'title' => 'Modifier ' . $class['code'],
            'class' => $class,
        ]);
    }

    public function classUpdate(Request $request, string $id): void
    {
        if (!Auth::can('admin.settings.edit')) { back(); return; }
        Database::execute(
            "UPDATE inventory_classes SET
                label = ?, description = ?, flexibility = ?,
                refund_policy_pct = ?, change_fee_fcfa = ?, no_show_fee_pct = ?,
                priority_boarding = ?, color_hex = ?, is_active = ?, sort_order = ?
             WHERE id = ?",
            [
                $request->input('label'),
                $request->input('description'),
                $request->input('flexibility', 'medium'),
                (int)$request->input('refund_policy_pct', 50),
                (int)$request->input('change_fee_fcfa', 0),
                (int)$request->input('no_show_fee_pct', 100),
                (int)$request->input('priority_boarding', 5),
                $request->input('color_hex', '#64748b'),
                (int)$request->input('is_active', 0),
                (int)$request->input('sort_order', 100),
                $id,
            ]
        );
        \CityBus\Models\AuditLog::record('inventory_class.update', 'inventory_class', (int)$id);
        $this->flash('success', 'Classe mise à jour.');
        redirect('admin/inventory-classes');
    }
}
