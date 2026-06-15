<?php

declare(strict_types=1);

namespace CityBus\Controllers\Crm;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\CustomerV4Service;
use CityBus\Services\ComplaintService;

final class CustomerV4Controller extends Controller
{
    private CustomerV4Service $svc;
    private ComplaintService $complaints;
    public function __construct() {
        $this->svc = new CustomerV4Service();
        $this->complaints = new ComplaintService();
    }

    public function show(Request $request, string $id): void
    {
        if (!Auth::can('crm.customers.view')) { back(); return; }
        $detail = $this->svc->detail((int)$id);
        if (!$detail) { http_response_code(404); $this->view('errors/404'); return; }
        $this->view('crm/customer_v4_detail', [
            'title' => 'Client · ' . trim(($detail['first_name'] ?? '') . ' ' . ($detail['last_name'] ?? '')),
            'c' => $detail,
        ]);
    }

    public function duplicates(Request $request): void
    {
        if (!Auth::can('crm.customers.merge')) { back(); return; }
        $this->view('crm/duplicates', [
            'title' => 'Doublons clients',
            'duplicates' => $this->svc->findDuplicates(),
        ]);
    }

    public function merge(Request $request): void
    {
        if (!Auth::can('crm.customers.merge')) { back(); return; }
        $keep = (int)$request->input('keep_id');
        $from = (int)$request->input('from_id');
        if ($keep && $from && $keep !== $from) {
            $this->svc->merge($keep, $from);
            $this->flash('success', "Client #$from fusionné dans #$keep.");
        }
        back();
    }

    public function rfm(Request $request): void
    {
        if (!Auth::can('crm.customers.view')) { back(); return; }
        if ($request->input('recompute')) {
            $n = $this->svc->rfmSegmentation();
            $this->flash('success', "RFM recalculé sur $n clients.");
            $this->redirect('crm/rfm');
        }
        $this->view('crm/rfm', [
            'title' => 'Segmentation RFM',
            'distribution' => $this->svc->rfmDistribution(),
        ]);
    }

    public function complaintsIndex(Request $request): void
    {
        if (!Auth::can('crm.complaints.view')) { back(); return; }
        $this->view('crm/complaints/index', [
            'title' => 'Réclamations clients',
            'rows'  => $this->complaints->listOpen($request->input('severity')),
            'stats' => $this->complaints->stats(),
        ]);
    }

    public function complaintsResolve(Request $request, string $id): void
    {
        if (!Auth::can('crm.complaints.manage')) { back(); return; }
        $this->complaints->resolve(
            (int)$id,
            (string)$request->input('resolution', ''),
            (int)$request->input('compensation_fcfa', 0),
            $request->input('voucher_code')
        );
        $this->flash('success', 'Réclamation résolue.');
        back();
    }

    public function complaintsClose(Request $request, string $id): void
    {
        if (!Auth::can('crm.complaints.manage')) { back(); return; }
        $this->complaints->close((int)$id);
        $this->flash('success', 'Réclamation fermée.');
        back();
    }

    public function complaintsOpen(Request $request): void
    {
        if (!Auth::can('crm.complaints.manage')) { back(); return; }
        $cid = (int)$request->input('customer_id');
        if ($cid > 0) {
            $id = $this->complaints->open($cid, [
                'pnr_id'      => $request->input('pnr_id'),
                'trip_id'     => $request->input('trip_id'),
                'category'    => $request->input('category', 'other'),
                'severity'    => $request->input('severity', 'medium'),
                'description' => $request->input('description', ''),
                'assigned_to' => Auth::id(),
            ]);
            $this->flash('success', "Réclamation #$id ouverte.");
        }
        back();
    }
}
