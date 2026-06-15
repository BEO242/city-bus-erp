<?php

declare(strict_types=1);

namespace CityBus\Controllers\Flotte;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\FuelLog;

final class FuelController extends Controller
{
    public function index(Request $request): void
    {
        $logs = Database::select(
            "SELECT f.*, b.code AS bus_code, b.plate
             FROM fuel_logs f JOIN buses b ON b.id=f.bus_id
             ORDER BY f.logged_at DESC LIMIT 100"
        );
        // Conso moyenne par bus (sur les pleins enregistrés)
        $stats = Database::select(
            "SELECT b.code AS bus_code, b.plate,
                SUM(f.liters) AS total_liters,
                    SUM(f.total_cost) AS total_cost,
                    MAX(f.km_at_fill) - MIN(f.km_at_fill) AS km_diff
             FROM fuel_logs f JOIN buses b ON b.id=f.bus_id
             WHERE f.logged_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
             GROUP BY b.id"
        );
        $this->view('flotte/fuel/index', ['title' => 'Carburant', 'logs' => $logs, 'stats' => $stats]);
    }

    public function create(Request $request): void
    {
        $this->view('flotte/fuel/form', [
            'title' => 'Nouveau plein', 'log' => null,
            'buses' => Database::select("SELECT * FROM buses ORDER BY code"),
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request, [
            'bus_id'          => 'required|integer',
            'liters'          => 'required|numeric',
            'price_per_liter' => 'required|numeric',
            'km_at_fill'      => 'integer',
            'station_name'    => 'max:100',
        ]);
        $data['total_cost'] = (int)round((float)$data['liters'] * (float)$data['price_per_liter']);
        $data['logged_by']  = \CityBus\Core\Auth::id();
        $data['logged_at']  = date('Y-m-d H:i:s');
        $fuelId = (int)FuelLog::create($data);

        // Écriture comptable (GAP-23)
        try {
            $log = Database::selectOne("SELECT * FROM fuel_logs WHERE id=?", [$fuelId]);
            if ($log) (new \CityBus\Services\AccountingService())->recordFuelPurchase($log);
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::warning('accounting.fuel_failed: ' . $e->getMessage());
        }

        // Mettre à jour le km du bus
        if (!empty($data['km_at_fill'])) {
            Database::execute("UPDATE buses SET km_current=GREATEST(km_current, ?) WHERE id=?",
                [(int)$data['km_at_fill'], (int)$data['bus_id']]);
        }

        $this->flash('success', 'Plein enregistré.');
        $next = trim((string)$request->input('_next', ''));
        redirect($next !== '' ? $next : 'flotte/fuel');
    }

    public function edit(Request $request, string $id): void
    {
        $log = FuelLog::find((int)$id);
        if (!$log) { http_response_code(404); $this->view('errors/404'); return; }
        $this->view('flotte/fuel/form', [
            'title' => 'Modifier le plein',
            'log'   => $log,
            'buses' => Database::select("SELECT * FROM buses ORDER BY code"),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        $log = FuelLog::find((int)$id);
        if (!$log) { http_response_code(404); $this->view('errors/404'); return; }
        $data = $this->validate($request, [
            'bus_id'          => 'required|integer',
            'liters'          => 'required|numeric',
            'price_per_liter' => 'required|numeric',
            'km_at_fill'      => 'integer',
            'station_name'    => 'max:100',
        ]);
        $data['total_cost'] = (int)round((float)$data['liters'] * (float)$data['price_per_liter']);
        FuelLog::update((int)$id, $data);
        $this->flash('success', 'Plein mis à jour.');
        redirect('flotte/fuel');
    }

    public function destroy(Request $request, string $id): void
    {
        FuelLog::delete((int)$id);
        $this->flash('success', 'Entrée supprimée.');
        redirect('flotte/fuel');
    }

    public function show(Request $request, string $id): void
    {
        $log = Database::selectOne(
            "SELECT f.*, b.code AS bus_code, b.plate, b.brand, b.model,
                    CONCAT(u.first_name,' ',u.last_name) AS logged_by_name
               FROM fuel_logs f
               JOIN buses b ON b.id=f.bus_id
               LEFT JOIN users u ON u.id=f.logged_by
              WHERE f.id=?",
            [(int)$id]
        );
        if (!$log) { http_response_code(404); $this->view('errors/404'); return; }

        // Historique des derniers pleins du même bus pour calcul de conso entre deux pleins
        $previous = Database::selectOne(
            "SELECT km_at_fill, logged_at FROM fuel_logs
              WHERE bus_id=? AND id < ? AND km_at_fill IS NOT NULL
              ORDER BY logged_at DESC LIMIT 1",
            [(int)$log['bus_id'], (int)$id]
        );
        $consumption = null;
        if ($previous && !empty($log['km_at_fill']) && (int)$log['km_at_fill'] > (int)$previous['km_at_fill']) {
            $kmDiff = (int)$log['km_at_fill'] - (int)$previous['km_at_fill'];
            if ($kmDiff > 0) {
                $consumption = round((float)$log['liters'] / $kmDiff * 100, 2);
            }
        }

        $this->view('flotte/fuel/show', [
            'title'       => 'Plein du ' . date('d/m/Y', strtotime((string)$log['logged_at'])),
            'log'         => $log,
            'previous'    => $previous,
            'consumption' => $consumption,
        ]);
    }
}
