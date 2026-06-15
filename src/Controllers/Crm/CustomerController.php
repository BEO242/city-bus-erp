<?php

declare(strict_types=1);

namespace CityBus\Controllers\Crm;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;
use CityBus\Services\CustomerService;
use CityBus\Core\Database;

final class CustomerController extends Controller
{
    private CustomerService $svc;
    public function __construct() { $this->svc = new CustomerService(); }

    public function index(Request $request): void
    {
        $q     = trim((string)$request->input('q', ''));
        $page  = max(1, (int)$request->input('page', 1));
        $perP  = 25;
        $data  = $this->svc->paginate($page, $perP, $q ?: null);
        $top   = $this->svc->topCustomers(10);

        $this->view('crm/index', [
            'title'   => 'CRM passagers',
            'data'    => $data,
            'top'     => $top,
            'q'       => $q,
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $customer = $this->svc->profile((int)$id);
        if (!$customer) { http_response_code(404); $this->view('errors/404'); return; }
        $tickets = $this->svc->ticketHistory((int)$id);

        $this->view('crm/show', [
            'title'    => 'Client · ' . trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: $customer['phone_display'],
            'customer' => $customer,
            'tickets'  => $tickets,
        ]);
    }

    public function update(Request $request, string $id): void
    {
        if (!Auth::can('crm.edit')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'first_name'        => 'max:80',
            'last_name'         => 'max:80',
            'email'             => 'max:120',
            'id_doc_number'     => 'max:50',
            'date_of_birth'     => '',
            'preferred_seat'    => 'max:10',
            'notes'             => 'max:2000',
        ]);
        Database::execute(
            "UPDATE customers
                SET first_name=?, last_name=?, email=?, id_doc_number=?, date_of_birth=?,
                    preferred_seat=?, notes=?,
                    sms_opt_in=?, email_opt_in=?
              WHERE id = ?",
            [
                $data['first_name'] ?? null, $data['last_name'] ?? null,
                $data['email'] ?? null, $data['id_doc_number'] ?? null,
                !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                $data['preferred_seat'] ?? null, $data['notes'] ?? null,
                (int)$request->input('sms_opt_in', 0),
                (int)$request->input('email_opt_in', 0),
                (int)$id,
            ]
        );
        AuditLog::record('customer.update', 'customer', (int)$id);
        $this->flash('success', 'Profil client mis à jour.');
        back();
    }

    public function exportCsv(Request $request): void
    {
        if (!Auth::can('crm.export')) { back(); }
        $rows = Database::select(
            "SELECT phone_display, first_name, last_name, email,
                    total_trips, total_spent, last_trip_at, first_trip_at,
                    sms_opt_in, email_opt_in
             FROM customers WHERE deleted_at IS NULL ORDER BY total_spent DESC"
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="crm-clients_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Téléphone','Prénom','Nom','Email','Voyages','Dépenses (FCFA)','Premier voyage','Dernier voyage','SMS opt-in','Email opt-in'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['phone_display'], $r['first_name'], $r['last_name'], $r['email'],
                (int)$r['total_trips'], (int)$r['total_spent'],
                $r['first_trip_at'], $r['last_trip_at'],
                (int)$r['sms_opt_in'], (int)$r['email_opt_in']
            ], ';');
        }
        fclose($out);
        exit;
    }

    /** Recherche AJAX par téléphone (auto-complétion vente). */
    public function lookup(Request $request): void
    {
        $q = trim((string)$request->input('q', ''));
        if (mb_strlen($q) < 3) { $this->json([]); return; }
        $this->json($this->svc->search($q, 10));
    }

    public function backfill(Request $request): void
    {
        if (!Auth::can('crm.edit')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $count = $this->svc->backfillTickets(1000);
        $this->flash('success', "Backfill : $count tickets associés à un client.");
        back();
    }
}
