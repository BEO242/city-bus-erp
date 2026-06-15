<?php

declare(strict_types=1);

namespace CityBus\Controllers\Caisse;

use CityBus\Controllers\Controller;
use CityBus\Controllers\Finance\CaisseManagementController;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Models\AuditLog;
use CityBus\Services\CashRegisterService;
use CityBus\Services\PdfService;

final class CaisseController extends Controller
{
    public function __construct(
        private CashRegisterService $service = new CashRegisterService(),
        private PdfService $pdf = new PdfService(),
    ) {}

    public function index(Request $request): void
    {
        $user = Auth::user();
        $current = $this->service->currentForUser((int)$user['id']);
        $summary = null;
        if ($current) {
            $summary = $this->service->summary((int)$current['id']);
            // Enc/dec trésorerie pour la session en cours
            $sessionFinance = Database::selectOne(
                "SELECT
                   COALESCE(SUM(CASE WHEN type='encaissement' THEN amount_fcfa ELSE 0 END),0) AS total_enc,
                   COALESCE(SUM(CASE WHEN type='decaissement' THEN amount_fcfa ELSE 0 END),0) AS total_dec
                 FROM treasury_transactions
                 WHERE cash_register_id = ? AND COALESCE(status,'pending') != 'rejected'",
                [(int)$current['id']]
            ) ?: ['total_enc' => 0, 'total_dec' => 0];
            $current = array_merge($current, [
                'agency_name' => $summary['agency_name'],
                'theoretical' => (int)$summary['total'],
                'total_enc'   => (int)$sessionFinance['total_enc'],
                'total_dec'   => (int)$sessionFinance['total_dec'],
            ]);
            // Enrichir avec le nom du poste si lié
            if (!empty($current['caisse_id'])) {
                $poste = Database::selectOne("SELECT name, code FROM caisses WHERE id = ?", [(int)$current['caisse_id']]);
                if ($poste) {
                    $current['caisse_name'] = $poste['name'];
                    $current['caisse_code'] = strtoupper($poste['code']);
                }
            }
        }

        // Historique — scopé selon le rôle
        if (Auth::role() === 'admin') {
            $histWhere  = '1=1';
            $histParams = [];
        } elseif (Auth::role() === 'chef_agence' && !empty($user['agency_id'])) {
            $histWhere  = 'cr.agency_id=?';
            $histParams = [(int)$user['agency_id']];
        } else {
            $histWhere  = 'cr.cashier_id=?';
            $histParams = [(int)$user['id']];
        }

        $history = Database::select(
            "SELECT cr.*,
                    dc.theoretical_amount, dc.declared_amount, dc.gap_amount,
                    dc.ticket_count, dc.id AS closure_id,
                    u.first_name AS cashier_first, u.last_name AS cashier_last,
                    a.name AS agency_name,
                    c.name AS caisse_name, c.code AS caisse_code,
                    -- Ventes (encaissements billetterie)
                    COALESCE((SELECT SUM(s.amount_fcfa) FROM sales s
                               WHERE s.cash_register_id = cr.id), 0) AS total_ventes,
                    -- Encaissements trésorerie
                    COALESCE((SELECT SUM(t.amount_fcfa) FROM treasury_transactions t
                               WHERE t.cash_register_id = cr.id AND t.type = 'encaissement'), 0) AS total_tx_enc,
                    -- Décaissements trésorerie
                    COALESCE((SELECT SUM(t.amount_fcfa) FROM treasury_transactions t
                               WHERE t.cash_register_id = cr.id AND t.type = 'decaissement'), 0) AS total_tx_dec,
                    -- Ajustements / entrées de caisse (positifs = encaissement, négatifs = décaissement)
                    COALESCE((SELECT SUM(CASE WHEN e.amount_fcfa > 0 THEN e.amount_fcfa ELSE 0 END)
                               FROM cash_register_entries e
                               WHERE e.cash_register_id = cr.id), 0) AS total_entries_enc,
                    COALESCE((SELECT SUM(CASE WHEN e.amount_fcfa < 0 THEN ABS(e.amount_fcfa) ELSE 0 END)
                               FROM cash_register_entries e
                               WHERE e.cash_register_id = cr.id), 0) AS total_entries_dec
             FROM cash_registers cr
             JOIN users u ON u.id = cr.cashier_id
             JOIN agencies a ON a.id = cr.agency_id
             LEFT JOIN daily_closures dc ON dc.cash_register_id = cr.id
             LEFT JOIN caisses c ON c.id = cr.caisse_id
             WHERE $histWhere
             ORDER BY cr.opened_at DESC LIMIT 50",
            $histParams
        );

        // Postes actifs pour le formulaire d'ouverture
        $postes = CaisseManagementController::loadActive();

        $this->view('caisse/index', [
            'title'   => 'Clôture de Caisse',
            'current' => $current,
            'summary' => $summary,
            'history' => $history,
            'postes'  => $postes,
        ]);
    }

    public function showOpen(Request $request): void
    {
        // L'ouverture se fait désormais via le modal intégré dans la page principale.
        redirect('caisse');
    }

    public function open(Request $request): void
    {
        $data = $this->validate($request, ['opening_amount' => 'integer', 'agency_id' => 'integer']);
        $user = Auth::user();
        $caisseId = (int)$request->input('caisse_id', 0);
        $agencyId = 0;

        if ($caisseId > 0) {
            $poste = Database::selectOne("SELECT * FROM caisses WHERE id = ? AND is_active = 1", [$caisseId]);
            if (!$poste) {
                $this->flash('danger', 'Poste de caisse introuvable ou inactif.');
                back(); return;
            }
            $agencyId = (int)$poste['agency_id'];
        } else {
            $agencyId = (int)($data['agency_id'] ?? $user['agency_id'] ?? 0);
        }

        if ($agencyId <= 0) {
            $this->flash('danger', 'Agence requise pour ouvrir une caisse.');
            back(); return;
        }
        try {
            $register = $this->service->open($agencyId, (int)$user['id'], (int)($data['opening_amount'] ?? 0));
            // Lier au poste si fourni
            if ($caisseId > 0 && !empty($register['id'])) {
                Database::execute("UPDATE cash_registers SET caisse_id = ? WHERE id = ?", [$caisseId, (int)$register['id']]);
            }
            AuditLog::record('caisse.open', 'cash_register', null, ['agency_id' => $agencyId, 'caisse_id' => $caisseId, 'opening_amount' => (int)($data['opening_amount'] ?? 0)]);
            $this->flash('success', 'Caisse ouverte.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage()); back(); return;
        }
        redirect('caisse');
    }

    public function showClose(Request $request): void
    {
        // La clôture se fait désormais via le modal intégré dans la page principale.
        redirect('caisse');
    }

    public function close(Request $request): void
    {
        $current = $this->service->currentForUser((int)Auth::id());
        if (!$current) { $this->flash('danger', 'Aucune caisse ouverte.'); redirect('caisse'); }

        // GAP-26 : si des champs counted_* sont fournis, utiliser la clôture multi-modes
        if ($request->input('counted_especes') !== null) {
            $counted = [
                'especes'      => (int)$request->input('counted_especes', 0),
                'mobile_money' => (int)$request->input('counted_mobile_money', 0),
                'carte'        => (int)$request->input('counted_carte', 0),
                'virement'     => (int)$request->input('counted_virement', 0),
                'voucher'      => (int)$request->input('counted_voucher', 0),
            ];
            $closure = $this->service->closeMultiMode((int)$current['id'], $counted, (int)Auth::id(), $request->input('notes'));
            $this->flash('success', 'Caisse clôturée (multi-modes). Écart total : ' . fcfa($closure['gap_amount']));
            redirect('caisse');
        }

        $data = $this->validate($request, ['declared_amount' => 'required|integer']);
        $closure = $this->service->close((int)$current['id'], (int)$data['declared_amount'], (int)Auth::id(), $request->input('notes'));

        // Sauvegarder le billettage (coupures) si fourni via le modal
        if (!empty($closure['id'])) {
            $denoms = [10000, 5000, 2000, 1000, 500, 100, 50, 25, 10, 5];
            foreach ($denoms as $d) {
                $qty = max(0, (int)$request->input("denom_$d", 0));
                if ($qty > 0) {
                    Database::insert(
                        "INSERT INTO treasury_closure_denominations (closure_id, denomination, quantity, subtotal) VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), subtotal=VALUES(subtotal)",
                        [(int)$closure['id'], $d, $qty, $d * $qty]
                    );
                }
            }
        }

        AuditLog::record('caisse.close', 'cash_register', (int)$current['id'], [
            'declared_amount' => (int)$data['declared_amount'],
            'gap_amount'      => $closure['gap_amount'] ?? null,
        ]);
        $this->flash('success', 'Caisse clôturée. Écart : ' . fcfa($closure['gap_amount']));
        redirect('caisse');
    }

    public function validateClosure(Request $request, string $id): void
    {
        if (!Auth::can('caisse.validate')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $this->service->validateClosure((int)$id, (int)Auth::id());
        $this->flash('success', 'Clôture validée.');
        back();
    }

    public function closurePdf(Request $request, string $id): void
    {
        $closure = Database::selectOne("SELECT * FROM daily_closures WHERE id=?", [$id]);
        if (!$closure) {
            $this->flash('danger', 'Clôture introuvable.');
            redirect('caisse');
            return;
        }
        $register = Database::selectOne(
            "SELECT cr.*, u.first_name, u.last_name, a.name AS agency_name
             FROM cash_registers cr JOIN users u ON u.id=cr.cashier_id JOIN agencies a ON a.id=cr.agency_id
             WHERE cr.id=?", [$closure['cash_register_id']]
        );
        if (!$register) {
            $this->flash('danger', 'Caisse associée introuvable.');
            redirect('caisse');
            return;
        }
        $sales = Database::select(
            "SELECT s.*, t.ticket_number, t.passenger_name FROM sales s
             LEFT JOIN tickets t ON t.id=s.ticket_id
             WHERE s.cash_register_id=?",
            [$closure['cash_register_id']]
        );
        $path = BASE_PATH . '/storage/' . $this->pdf->generateCashClosure($closure, $register, $sales);
        Response::download($path, "cloture-$id.pdf", 'application/pdf');
    }
}
