<?php

declare(strict_types=1);

namespace CityBus\Controllers\Caisse;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Services\CashDrawerService;

final class CashDrawerController extends Controller
{
    private CashDrawerService $svc;
    public function __construct() { $this->svc = new CashDrawerService(); }

    public function index(Request $request): void
    {
        if (!Auth::can('caisse.drawers.view')) { back(); return; }
        $userId = (int)(Auth::user()['id'] ?? 0);
        $current = $this->svc->openDrawer($userId);
        $this->view('caisse/drawers/index', [
            'title' => 'Ma caisse',
            'current' => $current,
            'history' => $this->svc->listForCashier($userId, 20),
            'summary' => $current ? $this->svc->summary((int)$current['id']) : null,
        ]);
    }

    public function open(Request $request): void
    {
        if (!Auth::can('caisse.drawers.open')) { back(); return; }
        $userId = (int)(Auth::user()['id'] ?? 0);
        try {
            $id = $this->svc->open(
                $userId,
                (int)(Auth::user()['agency_id'] ?? null) ?: null,
                (int)$request->input('opening_balance', 0),
                $request->input('drawer_code')
            );
            $this->flash('success', "Caisse #$id ouverte.");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect('caisse/drawer');
    }

    public function close(Request $request, string $id): void
    {
        if (!Auth::can('caisse.drawers.close')) { back(); return; }
        try {
            $r = $this->svc->close((int)$id, (int)$request->input('declared_cash', 0));
            $msg = "Caisse clôturée · attendu " . number_format($r['expected']) .
                   " · déclaré " . number_format($r['declared']) .
                   " · variance " . number_format($r['variance']);
            $this->flash($r['variance'] === 0 ? 'success' : 'warning', $msg);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect('caisse/drawer');
    }
}
