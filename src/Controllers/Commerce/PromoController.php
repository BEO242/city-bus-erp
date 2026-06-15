<?php

declare(strict_types=1);

namespace CityBus\Controllers\Commerce;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;
use CityBus\Services\PromoService;

final class PromoController extends Controller
{
    public function index(Request $request): void
    {
        $promos = Database::select(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM promo_redemptions WHERE promo_id = p.id) AS redeemed
             FROM promo_codes p ORDER BY p.is_active DESC, p.valid_until ASC, p.id DESC"
        );
        $this->view('commerce/promo/index', [
            'title'  => 'Codes promo',
            'promos' => $promos,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('commerce/promo/form', ['title' => 'Nouveau code promo', 'promo' => null]);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('promo.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'code'          => 'required|min:3|max:40',
            'label'         => 'required|min:3|max:120',
            'discount_type' => 'required|in:percent,fixed,free_seat',
            'discount_value'=> 'required|integer',
        ]);
        $id = (int)Database::insert(
            "INSERT INTO promo_codes
                (code, label, discount_type, discount_value, min_amount_fcfa,
                 max_discount_fcfa, max_uses, max_uses_per_customer,
                 valid_from, valid_until, is_active, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW())",
            [
                strtoupper(trim($data['code'])),
                $data['label'],
                $data['discount_type'],
                (int)$data['discount_value'],
                (int)($request->input('min_amount_fcfa') ?: 0),
                $request->input('max_discount_fcfa') ? (int)$request->input('max_discount_fcfa') : null,
                $request->input('max_uses') ? (int)$request->input('max_uses') : null,
                $request->input('max_uses_per_customer') ? (int)$request->input('max_uses_per_customer') : 1,
                $request->input('valid_from')  ?: null,
                $request->input('valid_until') ?: null,
                (int)$request->input('is_active', 1),
                (int)Auth::id(),
            ]
        );
        AuditLog::record('promo.create', 'promo', $id, ['code' => $data['code']]);
        $this->flash('success', 'Code promo créé.');
        redirect('commerce/promo');
    }

    public function destroy(Request $request, string $id): void
    {
        if (!Auth::can('promo.manage')) { back(); }
        Database::execute("UPDATE promo_codes SET is_active = 0 WHERE id = ?", [(int)$id]);
        $this->flash('success', 'Code désactivé.');
        redirect('commerce/promo');
    }

    /** Validation AJAX d'un code promo (à la vente). */
    public function validateCode(Request $request): void
    {
        $code   = (string)$request->input('code', '');
        $amount = (int)$request->input('amount', 0);
        $cust   = $request->input('customer_id') ? (int)$request->input('customer_id') : null;
        $line   = $request->input('line_id') ? (int)$request->input('line_id') : null;
        $cat    = $request->input('category') ?: null;

        $result = (new PromoService())->validate($code, $amount, $cust, $line, $cat);
        if (!$result) {
            $this->json(['valid' => false, 'message' => 'Code invalide ou inapplicable.']);
            return;
        }
        $this->json([
            'valid'         => true,
            'promo'         => $result['promo'],
            'discount_fcfa' => $result['discount_fcfa'],
            'final_amount'  => max(0, $amount - $result['discount_fcfa']),
        ]);
    }
}
