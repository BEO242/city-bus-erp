<?php

declare(strict_types=1);

namespace CityBus\Controllers\Commerce;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;
use CityBus\Services\DisruptionService;

final class VoucherController extends Controller
{
    public function index(Request $request): void
    {
        $q       = trim((string)$request->input('q', ''));
        $where   = ['1=1'];
        $params  = [];
        if ($q !== '') {
            $where[] = '(v.code LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone_norm LIKE ?)';
            $like = "%$q%";
            array_push($params, $like, $like, $like, $like);
        }
        $vouchers = Database::select(
            "SELECT v.*, c.first_name, c.last_name, c.phone_display, t.trip_code
             FROM vouchers v
             LEFT JOIN customers c ON c.id = v.customer_id
             LEFT JOIN trips t ON t.id = v.source_trip_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY v.issued_at DESC LIMIT 200",
            $params
        );

        $this->view('commerce/vouchers/index', [
            'title'    => 'Avoirs (vouchers)',
            'vouchers' => $vouchers,
            'q'        => $q,
        ]);
    }

    public function issue(Request $request): void
    {
        if (!Auth::can('vouchers.issue')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'amount'   => 'required|integer',
            'reason'   => 'required|min:5|max:255',
            'customer_id' => 'integer',
            'trip_id'  => 'integer',
            'validity_days' => 'integer',
        ]);
        $r = (new DisruptionService())->issueManualVoucher(
            (int)$data['amount'],
            !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
            !empty($data['trip_id'])     ? (int)$data['trip_id']     : null,
            $data['reason'],
            !empty($data['validity_days']) ? (int)$data['validity_days'] : null,
        );
        AuditLog::record('voucher.issue', 'voucher', $r['id'], ['code' => $r['code'], 'amount' => $r['amount']]);
        $this->flash('success', "Avoir émis : code {$r['code']} pour " . number_format($r['amount'], 0, ',', ' ') . ' FCFA');
        back();
    }

    public function void(Request $request, string $id): void
    {
        if (!Auth::can('vouchers.issue')) { back(); }
        Database::execute("UPDATE vouchers SET is_void=1 WHERE id=?", [(int)$id]);
        AuditLog::record('voucher.void', 'voucher', (int)$id);
        $this->flash('success', 'Avoir annulé.');
        back();
    }

    /** Vérifie un code (AJAX). */
    public function check(Request $request): void
    {
        $code   = (string)$request->input('code', '');
        $amount = (int)$request->input('amount', 0);
        $r = (new DisruptionService())->applyVoucher($code, $amount);
        if (!$r) {
            $this->json(['valid' => false, 'message' => 'Code introuvable, expiré ou épuisé.']);
            return;
        }
        $this->json([
            'valid'         => true,
            'voucher'       => $r['voucher'],
            'discount_fcfa' => $r['discount_fcfa'],
        ]);
    }
}
