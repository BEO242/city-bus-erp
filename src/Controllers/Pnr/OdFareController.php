<?php

declare(strict_types=1);

namespace CityBus\Controllers\Pnr;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\OdFareService;

final class OdFareController extends Controller
{
    private OdFareService $svc;
    public function __construct() { $this->svc = new OdFareService(); }

    public function index(Request $request): void
    {
        if (!Auth::can('od_fares.view')) { back(); return; }
        $lineId = (int)$request->input('line_id', 0);
        $lines = Database::select("SELECT id, code, name FROM bus_lines ORDER BY code");
        $fares = $lineId > 0 ? $this->svc->listForLine($lineId) : [];
        $this->view('pnr/od_fares/index', [
            'title' => 'Tarifs Origin-Destination',
            'lines' => $lines,
            'lineId' => $lineId,
            'fares' => $fares,
        ]);
    }

    public function bulkGenerate(Request $request): void
    {
        if (!Auth::can('od_fares.manage')) { back(); return; }
        $lineId = (int)$request->input('line_id');
        $base = (int)$request->input('base_price', 5000);
        $stops = Database::select(
            "SELECT s.id, s.name, ls.sequence
             FROM line_stops ls
             JOIN stops s ON s.id = ls.stop_id
             WHERE ls.line_id = ?
             ORDER BY ls.sequence", [$lineId]
        );
        $count = $this->svc->bulkGenerate($lineId, $stops, ['base' => $base]);
        $this->flash('success', "$count tarifs générés.");
        $this->redirect('pnr/od-fares?line_id=' . $lineId);
    }

    public function update(Request $request, string $id): void
    {
        if (!Auth::can('od_fares.manage')) { back(); return; }
        $price = (int)$request->input('base_price_fcfa', 0);
        Database::update('od_fares', ['base_price_fcfa' => $price], 'id = ?', [(int)$id]);
        $this->flash('success', 'Tarif mis à jour.');
        back();
    }

    public function destroy(Request $request, string $id): void
    {
        if (!Auth::can('od_fares.manage')) { back(); return; }
        Database::delete('od_fares', 'id = ?', [(int)$id]);
        $this->flash('success', 'Tarif supprimé.');
        back();
    }
}
