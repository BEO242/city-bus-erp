<?php

declare(strict_types=1);

namespace CityBus\Controllers\Commerce;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

final class PartnerController extends Controller
{
    public function index(Request $request): void
    {
        $partners = Database::select(
            "SELECT sp.*,
                    (SELECT COUNT(*) FROM tickets WHERE partner_id = sp.id) AS tickets_count,
                    (SELECT COALESCE(SUM(partner_commission_fcfa),0) FROM tickets WHERE partner_id = sp.id) AS commission_total
             FROM sales_partners sp
             ORDER BY sp.is_active DESC, sp.name"
        );
        $this->view('commerce/partners/index', [
            'title' => 'Partenaires commerciaux',
            'partners' => $partners,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('commerce/partners/form', ['title' => 'Nouveau partenaire', 'partner' => null]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('partners.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'name'           => 'required|min:2|max:150',
            'code'           => 'required|min:2|max:40',
            'commission_percent' => 'required|numeric',
        ]);
        $id = (int)Database::insert(
            "INSERT INTO sales_partners
                (name, code, contact_name, contact_phone, contact_email,
                 commission_percent, payout_schedule, bank_details, is_active)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [
                $data['name'],
                strtoupper(trim($data['code'])),
                $request->input('contact_name'), $request->input('contact_phone'), $request->input('contact_email'),
                (float)$data['commission_percent'],
                $request->input('payout_schedule', 'monthly'),
                $request->input('bank_details'),
                (int)$request->input('is_active', 1),
            ]
        );
        AuditLog::record('partner.create', 'partner', $id);
        $this->flash('success', 'Partenaire créé.');
        redirect('commerce/partners');
    }

    public function show(Request $request, string $id): void
    {
        $partner = Database::selectOne("SELECT * FROM sales_partners WHERE id=?", [(int)$id]);
        if (!$partner) { http_response_code(404); $this->view('errors/404'); return; }
        $payouts = Database::select(
            "SELECT * FROM partner_payouts WHERE partner_id = ? ORDER BY period_from DESC LIMIT 20",
            [(int)$id]
        );
        $stats = Database::selectOne(
            "SELECT COUNT(*) AS tickets, COALESCE(SUM(price_fcfa),0) AS revenue,
                    COALESCE(SUM(partner_commission_fcfa),0) AS commission
             FROM tickets WHERE partner_id = ? AND deleted_at IS NULL", [(int)$id]
        ) ?: ['tickets'=>0,'revenue'=>0,'commission'=>0];

        $this->view('commerce/partners/show', [
            'title'   => $partner['name'],
            'partner' => $partner,
            'payouts' => $payouts,
            'stats'   => $stats,
        ]);
    }

    /** Génère un payout pour la période demandée. */
    public function generatePayout(Request $request, string $id): void
    {
        if (!Auth::can('partners.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $from = $request->input('from', date('Y-m-01'));
        $to   = $request->input('to',   date('Y-m-d'));
        $stats = Database::selectOne(
            "SELECT COUNT(*) AS tickets, COALESCE(SUM(price_fcfa),0) AS revenue,
                    COALESCE(SUM(partner_commission_fcfa),0) AS commission
             FROM tickets
             WHERE partner_id = ? AND deleted_at IS NULL
               AND DATE(sold_at) BETWEEN ? AND ?",
            [(int)$id, $from, $to]
        ) ?: ['tickets'=>0,'revenue'=>0,'commission'=>0];

        Database::insert(
            "INSERT INTO partner_payouts
                (partner_id, period_from, period_to, tickets_count, revenue_fcfa, commission_fcfa, status)
             VALUES (?,?,?,?,?,?, 'pending')",
            [(int)$id, $from, $to, (int)$stats['tickets'], (int)$stats['revenue'], (int)$stats['commission']]
        );
        $this->flash('success', "Payout généré : {$stats['tickets']} tickets, " . fcfa((int)$stats['commission']));
        back();
    }
}
