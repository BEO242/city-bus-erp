<?php

declare(strict_types=1);

namespace CityBus\Controllers\Commerce;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

final class CorporateController extends Controller
{
    public function index(Request $request): void
    {
        $accounts = Database::select(
            "SELECT ca.*,
                    (SELECT COUNT(*) FROM tickets WHERE corporate_id = ca.id) AS tickets_count
             FROM corporate_accounts ca
             ORDER BY ca.is_active DESC, ca.company_name"
        );
        $this->view('commerce/corporate/index', [
            'title' => 'Comptes corporate',
            'accounts' => $accounts,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('commerce/corporate/form', ['title' => 'Nouveau compte corporate', 'account' => null]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('corporate.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'company_name'  => 'required|min:2|max:150',
            'contact_name'  => 'required|min:2|max:120',
            'contact_phone' => 'required|min:6|max:30',
        ]);
        $id = (int)Database::insert(
            "INSERT INTO corporate_accounts
                (company_name, legal_id, contact_name, contact_phone, contact_email, address,
                 discount_percent, credit_limit_fcfa, payment_terms_days, is_active, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['company_name'],
                $request->input('legal_id'),
                $data['contact_name'], $data['contact_phone'],
                $request->input('contact_email'), $request->input('address'),
                (float)$request->input('discount_percent', 0),
                (int)$request->input('credit_limit_fcfa', 0),
                (int)$request->input('payment_terms_days', 30),
                (int)$request->input('is_active', 1),
                $request->input('notes'),
            ]
        );
        AuditLog::record('corporate.create', 'corporate_account', $id);
        $this->flash('success', 'Compte créé.');
        redirect('commerce/corporate');
    }

    public function show(Request $request, string $id): void
    {
        $account = Database::selectOne("SELECT * FROM corporate_accounts WHERE id=?", [(int)$id]);
        if (!$account) { http_response_code(404); $this->view('errors/404'); return; }
        $tickets = Database::select(
            "SELECT t.*, tr.trip_code, tr.trip_date, l.code AS line_code
             FROM tickets t JOIN trips tr ON tr.id = t.trip_id
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             WHERE t.corporate_id = ? AND t.deleted_at IS NULL
             ORDER BY t.sold_at DESC LIMIT 50",
            [(int)$id]
        );
        $invoices = Database::select(
            "SELECT * FROM corporate_invoices WHERE corporate_id = ? ORDER BY created_at DESC LIMIT 20",
            [(int)$id]
        );
        $this->view('commerce/corporate/show', [
            'title'    => $account['company_name'],
            'account'  => $account,
            'tickets'  => $tickets,
            'invoices' => $invoices,
        ]);
    }
}
