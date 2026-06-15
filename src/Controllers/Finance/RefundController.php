<?php

declare(strict_types=1);

namespace CityBus\Controllers\Finance;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\RefundService;

/**
 * Consultation centralisée des remboursements (tickets + fret).
 */
final class RefundController extends Controller
{
    public function __construct(
        private RefundService $service = new RefundService(),
    ) {}

    public function index(Request $request): void
    {
        $filters = [
            'refund_type' => $request->input('type', ''),
            'status'      => $request->input('status', ''),
            'agency_id'   => $request->input('agency_id', ''),
            'date_from'   => $request->input('date_from', ''),
            'date_to'     => $request->input('date_to', ''),
        ];

        $page    = max(1, (int)$request->input('page', 1));
        $perPage = 30;

        $result = $this->service->list($filters, $page, $perPage);
        $kpis   = $this->service->kpis($filters['agency_id'] ? (int)$filters['agency_id'] : null);
        $totals = $this->service->totals(
            $filters['date_from'] ?: null,
            $filters['date_to'] ?: null,
            $filters['agency_id'] ? (int)$filters['agency_id'] : null
        );

        $agencies = Database::select("SELECT id, name FROM agencies WHERE is_active = 1 ORDER BY name");

        $this->view('finance/refunds/index', [
            'title'    => 'Remboursements',
            'rows'     => $result['rows'],
            'total'    => $result['total'],
            'page'     => $result['page'],
            'lastPage' => $result['lastPage'],
            'filters'  => $filters,
            'kpis'     => $kpis,
            'totals'   => $totals,
            'agencies' => $agencies,
        ]);
    }
}
