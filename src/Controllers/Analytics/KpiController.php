<?php

declare(strict_types=1);

namespace CityBus\Controllers\Analytics;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Services\KpiService;
use CityBus\Services\ForecastService;

final class KpiController extends Controller
{
    public function dashboard(Request $request): void
    {
        if (!Auth::can('kpi.view')) { back(); return; }
        $from = $request->input('from', date('Y-m-d', strtotime('-30 days')));
        $to   = $request->input('to', date('Y-m-d'));

        if ($request->input('recompute')) {
            $svc = new KpiService();
            $cur = $from;
            $count = 0;
            while ($cur <= $to) {
                $svc->snapshotDay($cur);
                $cur = date('Y-m-d', strtotime("$cur +1 day"));
                $count++;
            }
            $this->flash('success', "$count jours recalculés.");
            $this->redirect("analytics/kpi?from=$from&to=$to");
            return;
        }

        $kpiSvc   = new KpiService();
        $timeline = $kpiSvc->timeline('global', null, $from, $to);
        $perLine  = $kpiSvc->perLineLatest();
        $this->view('analytics/kpi/dashboard', [
            'title'    => 'KPIs Performance',
            'from'     => $from,
            'to'       => $to,
            'timeline' => $timeline,
            'perLine'  => $perLine,
        ]);
    }

    public function forecast(Request $request): void
    {
        if (!Auth::can('forecast.view')) { back(); return; }
        $from = $request->input('from', date('Y-m-d'));
        $to   = $request->input('to', date('Y-m-d', strtotime('+14 days')));

        if ($request->input('recompute')) {
            $svc = new ForecastService();
            $cur = $from;
            $count = 0;
            while ($cur <= $to) {
                $count += $svc->recomputeAll($cur);
                $cur = date('Y-m-d', strtotime("$cur +1 day"));
            }
            $this->flash('success', "$count prévisions calculées.");
            $this->redirect("analytics/forecast?from=$from&to=$to");
            return;
        }

        $forecasts = (new ForecastService())->listForecasts($from, $to);
        $this->view('analytics/forecast/index', [
            'title' => 'Forecast demande',
            'from' => $from, 'to' => $to,
            'forecasts' => $forecasts,
        ]);
    }
}
