<?php

declare(strict_types=1);

namespace CityBus\Controllers\Treasury;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Models\AuditLog;
use CityBus\Services\TreasuryExpenseService;

final class TreasuryController extends Controller
{
    /* ═══════════════════════════════════════════
       DASHBOARD
    ═══════════════════════════════════════════ */
    public function dashboard(Request $request): void
    {
        // Le dashboard trésorerie redirige vers la page Transactions (plus complète)
        redirect('finance/treasury/transactions');
    }

    /* ═══════════════════════════════════════════
       TRANSACTIONS : liste
    ═══════════════════════════════════════════ */
    public function transactions(Request $request): void
    {
        $q          = trim((string)$request->input('q', ''));
        $type       = (string)$request->input('type', '');
        $status     = (string)$request->input('status', 'all_except_rejected');
        $source     = (string)$request->input('source', '');
        $catId      = (int)$request->input('category', 0);
        $dateFrom   = (string)$request->input('date_from', '');
        $dateTo     = (string)$request->input('date_to', '');
        $timeFrom   = (string)$request->input('time_from', '');
        $timeTo     = (string)$request->input('time_to', '');

        $where  = ['1=1'];
        $params = [];

        if ($type && in_array($type, ['encaissement', 'decaissement'])) {
            $where[] = 'tt.type = ?'; $params[] = $type;
        }
        // Statut : par défaut on exclut les rejetés
        if ($status === 'pending') {
            $where[] = "COALESCE(tt.status,'pending') = 'pending'";
        } elseif ($status === 'confirmed') {
            $where[] = "tt.status = 'confirmed'";
        } elseif ($status === 'rejected') {
            $where[] = "tt.status = 'rejected'";
        } else {
            // 'all_except_rejected' ou '' : afficher tout sauf rejected
            $where[] = "COALESCE(tt.status,'pending') != 'rejected'";
        }
        if ($source && in_array($source, ['tresorerie','vente','colis','autre'])) {
            $where[] = "COALESCE(tc.source,'tresorerie') = ?"; $params[] = $source;
        }
        if ($catId > 0) {
            $where[] = 'tt.category_id = ?'; $params[] = $catId;
        }
        if ($dateFrom) {
            $where[] = 'DATE(tt.created_at) >= ?'; $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[] = 'DATE(tt.created_at) <= ?'; $params[] = $dateTo;
        }
        if ($timeFrom) {
            $where[] = 'TIME(tt.created_at) >= ?'; $params[] = $timeFrom;
        }
        if ($timeTo) {
            $where[] = 'TIME(tt.created_at) <= ?'; $params[] = $timeTo;
        }
        if ($q !== '') {
            $where[] = '(tt.description LIKE ? OR tt.reference LIKE ? OR tc.label LIKE ?)';
            $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
        }

        $whereStr = implode(' AND ', $where);

        // KPIs (sans filtre de statut pour les totaux globaux)
        $kpiBase = "FROM treasury_transactions tt JOIN treasury_categories tc ON tc.id = tt.category_id
                    WHERE COALESCE(tt.status,'pending') != 'rejected'";
        $kpis = Database::selectOne(
            "SELECT
               COALESCE(SUM(CASE WHEN tt.type='encaissement' THEN tt.amount_fcfa ELSE 0 END),0) AS total_enc,
               COALESCE(SUM(CASE WHEN tt.type='decaissement' THEN tt.amount_fcfa ELSE 0 END),0) AS total_dec,
               COALESCE(SUM(CASE WHEN DATE(tt.created_at)=CURDATE() AND tt.type='encaissement' THEN tt.amount_fcfa
                                 WHEN DATE(tt.created_at)=CURDATE() AND tt.type='decaissement' THEN -tt.amount_fcfa
                                 ELSE 0 END),0) AS today_net
             $kpiBase"
        );

        // Nombre de transactions en attente (badge Confirmations)
        $pendingCount = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM treasury_transactions WHERE COALESCE(status,'pending') = 'pending'"
        )['n'] ?? 0);

        $page   = max(1, (int)$request->input('page', 1));
        $limit  = 30;
        $offset = ($page - 1) * $limit;

        $total = (int)(Database::selectOne(
            "SELECT COUNT(*) AS n FROM treasury_transactions tt
             JOIN treasury_categories tc ON tc.id = tt.category_id
             WHERE $whereStr", $params
        )['n'] ?? 0);

        $transactions = Database::select(
            "SELECT tt.*,
                    COALESCE(tt.status,'pending') AS tx_status,
                    tc.label AS cat_label, tc.color AS cat_color, tc.code AS cat_code,
                    COALESCE(tc.source,'tresorerie') AS cat_source,
                    a.name AS agency_name,
                    u.first_name, u.last_name,
                    cv.first_name AS conf_first, cv.last_name AS conf_last,
                    b.plate AS bus_plate
             FROM treasury_transactions tt
             JOIN treasury_categories tc ON tc.id = tt.category_id
             JOIN cash_registers cr ON cr.id = tt.cash_register_id
             JOIN agencies a ON a.id = cr.agency_id
             JOIN users u ON u.id = tt.created_by
             LEFT JOIN users cv ON cv.id = tt.confirmed_by
             LEFT JOIN buses b ON b.id = tt.bus_id
             WHERE $whereStr
             ORDER BY tt.created_at DESC
             LIMIT $limit OFFSET $offset",
            $params
        );

        $categories = Database::select(
            "SELECT id, label, type, COALESCE(source,'tresorerie') AS source, sort_order
             FROM treasury_categories WHERE is_active=1 ORDER BY type DESC, sort_order, label"
        );

        $this->view('treasury/transactions', [
            'title'        => 'Transactions',
            'transactions' => $transactions,
            'categories'   => $categories,
            'pendingCount' => $pendingCount,
            'kpis'         => $kpis,
            'total'        => $total,
            'page'         => $page,
            'pages'        => (int)ceil($total / $limit),
            'q'            => $q,
            'type'         => $type,
            'status'       => $status,
            'source'       => $source,
            'catId'        => $catId,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'timeFrom'     => $timeFrom,
            'timeTo'       => $timeTo,
        ]);
    }

    /* ─── Approuver une transaction ─── */
    public function approveTransaction(Request $request, string $id): void
    {
        if (!can('finance.treasury.validate')) {
            $this->flash('danger', 'Permission refusée.'); back(); return;
        }
        Database::execute(
            "UPDATE treasury_transactions SET status='confirmed', confirmed_by=?, confirmed_at=NOW() WHERE id=?",
            [(int)Auth::id(), (int)$id]
        );
        AuditLog::record('treasury.confirm', 'treasury_transaction', (int)$id);
        $this->flash('success', 'Transaction confirmée.');
        back();
    }

    /* ─── Rejeter une transaction ─── */
    public function rejectTransaction(Request $request, string $id): void
    {
        if (!can('finance.treasury.validate')) {
            $this->flash('danger', 'Permission refusée.'); back(); return;
        }
        $reason = trim((string)$request->input('reason', ''));
        Database::execute(
            "UPDATE treasury_transactions SET status='rejected', rejection_reason=?, confirmed_by=?, confirmed_at=NOW() WHERE id=?",
            [$reason ?: null, (int)Auth::id(), (int)$id]
        );
        AuditLog::record('treasury.reject', 'treasury_transaction', (int)$id, ['reason' => $reason]);
        $this->flash('warning', 'Transaction rejetée.');
        back();
    }

    /* ═══════════════════════════════════════════
       ENCAISSEMENT / DECAISSEMENT : formulaire
    ═══════════════════════════════════════════ */
    public function createTransaction(Request $request): void
    {
        $txType = (string)$request->input('type', 'encaissement');
        if (!in_array($txType, ['encaissement', 'decaissement'])) $txType = 'encaissement';

        $categories = Database::select(
            "SELECT id, code, label, color, sort_order FROM treasury_categories WHERE is_active=1 AND type=? ORDER BY sort_order, label",
            [$txType]
        );
        $openRegisters = Database::select(
            "SELECT cr.id, a.name AS agency_name, u.first_name, u.last_name
             FROM cash_registers cr
             JOIN agencies a ON a.id = cr.agency_id
             JOIN users u ON u.id = cr.cashier_id
             WHERE cr.status = 'ouverte' ORDER BY a.name"
        );
        $buses = Database::select("SELECT id, plate, code FROM buses WHERE status != 'hors_service' ORDER BY plate");
        $trips = Database::select(
            "SELECT t.id, t.trip_code, l.name AS line_name, t.departure_scheduled
             FROM trips t JOIN bus_lines l ON l.id = t.line_id
             WHERE DATE(t.departure_scheduled) = CURDATE() AND t.status IN ('planifie','embarquement','en_route')
             ORDER BY t.departure_scheduled"
        );

        // Pré-sélectionner la caisse de l'utilisateur connecté
        $userRegister = Database::selectOne(
            "SELECT id FROM cash_registers WHERE cashier_id = ? AND status = 'ouverte'",
            [(int)Auth::id()]
        );

        $this->view('treasury/transaction_form', [
            'title'          => $txType === 'encaissement' ? 'Nouvel encaissement' : 'Nouveau décaissement',
            'txType'         => $txType,
            'categories'     => $categories,
            'registers'      => $openRegisters,
            'buses'          => $buses,
            'trips'          => $trips,
            'userRegisterId' => $userRegister ? (int)$userRegister['id'] : 0,
        ]);
    }

    public function storeTransaction(Request $request): void
    {
        $txType       = (string)$request->input('type', '');
        $categoryId   = (int)$request->input('category_id', 0);
        $registerId   = (int)$request->input('cash_register_id', 0);
        $amount       = (int)$request->input('amount_fcfa', 0);
        $method       = (string)$request->input('payment_method', 'especes');
        $description  = trim((string)$request->input('description', ''));
        $reference    = trim((string)$request->input('reference', ''));
        $tripId       = ((int)$request->input('trip_id', 0)) ?: null;
        $busId        = ((int)$request->input('bus_id', 0)) ?: null;
        $driverId     = ((int)$request->input('driver_id', 0)) ?: null;

        // Validations
        if (!in_array($txType, ['encaissement', 'decaissement'])) {
            $this->flash('danger', 'Type invalide.'); back(); return;
        }
        if ($amount <= 0) {
            $this->flash('danger', 'Le montant doit être supérieur à 0.'); back(); return;
        }
        $category = Database::selectOne("SELECT * FROM treasury_categories WHERE id=? AND is_active=1", [$categoryId]);
        if (!$category || $category['type'] !== $txType) {
            $this->flash('danger', 'Catégorie invalide.'); back(); return;
        }
        $register = Database::selectOne("SELECT * FROM cash_registers WHERE id=? AND status='ouverte'", [$registerId]);
        if (!$register) {
            $this->flash('danger', 'Caisse introuvable ou fermée.'); back(); return;
        }
        if (!in_array($method, ['especes', 'mobile_money', 'carte', 'virement', 'cheque'])) {
            $method = 'especes';
        }

        $id = (int)Database::insert(
            "INSERT INTO treasury_transactions
                (cash_register_id, category_id, type, amount_fcfa, payment_method, description, reference, trip_id, bus_id, driver_id, created_by, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?, 'pending')",
            [$registerId, $categoryId, $txType, $amount, $method,
             $description ?: null, $reference ?: null, $tripId, $busId, $driverId, (int)Auth::id()]
        );

        AuditLog::record("treasury.$txType", 'treasury_transaction', $id, [
            'amount' => $amount, 'category' => $category['code'], 'register' => $registerId
        ]);

        $label = $txType === 'encaissement' ? 'Encaissement' : 'Décaissement';
        $this->flash('success', "$label de " . number_format($amount, 0, ',', ' ') . " FCFA enregistré.");
        redirect('finance/treasury');
    }

    /* ═══════════════════════════════════════════
       VIREMENTS INTER-CAISSE
    ═══════════════════════════════════════════ */
    public function createTransfer(Request $request): void
    {
        $openRegisters = Database::select(
            "SELECT cr.id, a.name AS agency_name, u.first_name, u.last_name,
                    COALESCE(cr.opening_amount, 0)
                      + COALESCE((SELECT SUM(CASE WHEN tt.type='encaissement' THEN tt.amount_fcfa ELSE 0 END) FROM treasury_transactions tt WHERE tt.cash_register_id=cr.id), 0)
                      - COALESCE((SELECT SUM(CASE WHEN tt.type='decaissement' THEN tt.amount_fcfa ELSE 0 END) FROM treasury_transactions tt WHERE tt.cash_register_id=cr.id), 0)
                    AS solde
             FROM cash_registers cr
             JOIN agencies a ON a.id = cr.agency_id
             JOIN users u ON u.id = cr.cashier_id
             WHERE cr.status = 'ouverte'
             ORDER BY a.name"
        );

        $this->view('treasury/transfer_form', [
            'title'     => 'Virement inter-caisse',
            'registers' => $openRegisters,
        ]);
    }

    public function storeTransfer(Request $request): void
    {
        $fromId = (int)$request->input('from_register_id', 0);
        $toId   = (int)$request->input('to_register_id', 0);
        $amount = (int)$request->input('amount_fcfa', 0);
        $motif  = trim((string)$request->input('motif', ''));

        if ($fromId === $toId || $fromId <= 0 || $toId <= 0) {
            $this->flash('danger', 'Les caisses source et destination doivent être différentes.'); back(); return;
        }
        if ($amount <= 0) {
            $this->flash('danger', 'Le montant doit être supérieur à 0.'); back(); return;
        }

        $from = Database::selectOne("SELECT * FROM cash_registers WHERE id=? AND status='ouverte'", [$fromId]);
        $to   = Database::selectOne("SELECT * FROM cash_registers WHERE id=? AND status='ouverte'", [$toId]);
        if (!$from || !$to) {
            $this->flash('danger', 'Caisse(s) introuvable(s) ou fermée(s).'); back(); return;
        }

        $id = (int)Database::insert(
            "INSERT INTO treasury_transfers (from_register_id, to_register_id, amount_fcfa, motif, status, initiated_by)
             VALUES (?,?,?,?,?,?)",
            [$fromId, $toId, $amount, $motif ?: null, 'en_attente', (int)Auth::id()]
        );

        AuditLog::record('treasury.transfer.create', 'treasury_transfer', $id, [
            'amount' => $amount, 'from' => $fromId, 'to' => $toId
        ]);
        $this->flash('success', 'Virement de ' . number_format($amount, 0, ',', ' ') . ' FCFA initié (en attente de validation).');
        redirect('finance/treasury');
    }

    public function validateTransfer(Request $request, string $id): void
    {
        $transfer = Database::selectOne("SELECT * FROM treasury_transfers WHERE id=?", [(int)$id]);
        if (!$transfer || $transfer['status'] !== 'en_attente') {
            $this->flash('danger', 'Virement introuvable ou déjà traité.'); back(); return;
        }

        $action = (string)$request->input('action', '');

        if ($action === 'valider') {
            // Créer les transactions de décaissement (source) et encaissement (destination)
            Database::execute("UPDATE treasury_transfers SET status='valide', validated_by=?, validated_at=NOW() WHERE id=?",
                [(int)Auth::id(), (int)$id]);

            // Catégorie système pour les transferts — utiliser "autre" si pas de catégorie dédiée
            $catOut = Database::selectOne("SELECT id FROM treasury_categories WHERE code='virement_sortant'");
            $catIn  = Database::selectOne("SELECT id FROM treasury_categories WHERE code='virement_entrant'");

            // Si pas de catégories dédiées, on les crée
            if (!$catOut) {
                $catOutId = (int)Database::insert(
                    "INSERT INTO treasury_categories (code, label, type, is_system, color, sort_order) VALUES ('virement_sortant','Virement sortant','decaissement',1,'amber',5)",
                    []
                );
            } else {
                $catOutId = (int)$catOut['id'];
            }
            if (!$catIn) {
                $catInId = (int)Database::insert(
                    "INSERT INTO treasury_categories (code, label, type, is_system, color, sort_order) VALUES ('virement_entrant','Virement entrant','encaissement',1,'amber',6)",
                    []
                );
            } else {
                $catInId = (int)$catIn['id'];
            }

            // Décaissement source
            Database::insert(
                "INSERT INTO treasury_transactions (cash_register_id, category_id, type, amount_fcfa, payment_method, description, transfer_id, created_by)
                 VALUES (?,?,'decaissement',?,'especes',?,?,?)",
                [$transfer['from_register_id'], $catOutId, $transfer['amount_fcfa'],
                 'Virement inter-caisse #' . $id, (int)$id, (int)Auth::id()]
            );
            // Encaissement destination
            Database::insert(
                "INSERT INTO treasury_transactions (cash_register_id, category_id, type, amount_fcfa, payment_method, description, transfer_id, created_by)
                 VALUES (?,?,'encaissement',?,'especes',?,?,?)",
                [$transfer['to_register_id'], $catInId, $transfer['amount_fcfa'],
                 'Virement inter-caisse #' . $id, (int)$id, (int)Auth::id()]
            );

            AuditLog::record('treasury.transfer.validate', 'treasury_transfer', (int)$id);
            $this->flash('success', 'Virement validé et comptabilisé.');
        } elseif ($action === 'rejeter') {
            Database::execute("UPDATE treasury_transfers SET status='rejete', validated_by=?, validated_at=NOW() WHERE id=?",
                [(int)Auth::id(), (int)$id]);
            AuditLog::record('treasury.transfer.reject', 'treasury_transfer', (int)$id);
            $this->flash('warning', 'Virement rejeté.');
        }

        redirect('finance/treasury');
    }

    /* ═══════════════════════════════════════════
       CLOTURE DE CAISSE AVEC BILLETTAGE
       (showClosure gardé pour compat ancienne route)
    ═══════════════════════════════════════════ */
    public function showClosure(Request $request): void
    {
        redirect('finance/treasury/closures');
    }

    /** Calcule le théorique complet d'une caisse (ventes + entrées + transactions tréso) */
    private function computeTheorique(int $registerId, int $openingAmount): array
    {
        $sales = Database::selectOne(
            "SELECT COALESCE(SUM(amount_fcfa),0) AS total, COUNT(*) AS cnt
             FROM sales WHERE cash_register_id=?", [$registerId]
        );
        $entries = (int)(Database::selectOne(
            "SELECT COALESCE(SUM(amount_fcfa),0) AS t FROM cash_register_entries WHERE cash_register_id=?",
            [$registerId]
        )['t'] ?? 0);
        $txIn  = (int)(Database::selectOne(
            "SELECT COALESCE(SUM(amount_fcfa),0) AS t FROM treasury_transactions WHERE cash_register_id=? AND type='encaissement'",
            [$registerId]
        )['t'] ?? 0);
        $txOut = (int)(Database::selectOne(
            "SELECT COALESCE(SUM(amount_fcfa),0) AS t FROM treasury_transactions WHERE cash_register_id=? AND type='decaissement'",
            [$registerId]
        )['t'] ?? 0);

        return [
            'theorique'    => $openingAmount + (int)$sales['total'] + $entries + $txIn - $txOut,
            'ticket_count' => (int)$sales['cnt'],
            'sales_total'  => (int)$sales['total'],
            'tx_in'        => $txIn,
            'tx_out'       => $txOut,
        ];
    }

    public function storeClosure(Request $request): void
    {
        $registerId   = (int)$request->input('cash_register_id', 0);
        $soldeDeclare = (int)$request->input('solde_declare', 0);
        $notes        = trim((string)$request->input('notes', ''));

        $register = Database::selectOne(
            "SELECT * FROM cash_registers WHERE id=? AND status='ouverte'", [$registerId]
        );
        if (!$register) {
            $this->flash('danger', 'Caisse introuvable ou déjà clôturée.'); back(); return;
        }

        $calc      = $this->computeTheorique($registerId, (int)$register['opening_amount']);
        $theorique = $calc['theorique'];
        $ecart     = $soldeDeclare - $theorique;

        // Écriture dans daily_closures (source unifiée)
        $closureId = (int)Database::insert(
            "INSERT INTO daily_closures
                (cash_register_id, theoretical_amount, declared_amount, gap_amount,
                 ticket_count, closed_by, notes, closed_at)
             VALUES (?,?,?,?,?,?,?,NOW())",
            [$registerId, $theorique, $soldeDeclare, $ecart,
             $calc['ticket_count'], (int)Auth::id(), $notes ?: null]
        );

        // Billettage → treasury_closure_denominations (closure_id = daily_closures.id)
        $denoms = [10000, 5000, 2000, 1000, 500, 100, 50, 25, 10, 5];
        foreach ($denoms as $d) {
            $qty = max(0, (int)$request->input("denom_$d", 0));
            if ($qty > 0) {
                Database::insert(
                    "INSERT INTO treasury_closure_denominations (closure_id, denomination, quantity, subtotal)
                     VALUES (?,?,?,?)",
                    [$closureId, $d, $qty, $d * $qty]
                );
            }
        }

        // Fermer la session
        Database::execute("UPDATE cash_registers SET status='cloturee', closed_at=NOW() WHERE id=?", [$registerId]);

        AuditLog::record('treasury.closure', 'daily_closure', $closureId, [
            'register' => $registerId, 'theorique' => $theorique,
            'declare'  => $soldeDeclare, 'ecart'    => $ecart,
        ]);

        $abs = number_format(abs($ecart), 0, ',', ' ');
        $msg = $ecart === 0 ? 'Caisse clôturée — solde exact.'
             : ($ecart > 0  ? "Caisse clôturée — excédent de $abs FCFA."
                             : "Caisse clôturée — déficit de $abs FCFA.");
        $this->flash($ecart === 0 ? 'success' : 'warning', $msg);
        redirect('finance/treasury/closures');
    }

    /* ═══════════════════════════════════════════
       HISTORIQUE CLOTURES (depuis daily_closures)
    ═══════════════════════════════════════════ */
    public function closures(Request $request): void
    {
        // Filtres
        $agencyId  = (int)$request->input('agency', 0);
        $status    = (string)$request->input('status', '');
        $dateFrom  = (string)$request->input('date_from', '');
        $dateTo    = (string)$request->input('date_to', '');

        $where  = ['1=1'];
        $params = [];
        if ($agencyId > 0)  { $where[] = 'cr.agency_id = ?';          $params[] = $agencyId; }
        if ($dateFrom)      { $where[] = 'DATE(dc.closed_at) >= ?';   $params[] = $dateFrom; }
        if ($dateTo)        { $where[] = 'DATE(dc.closed_at) <= ?';   $params[] = $dateTo;   }
        if ($status === 'validated')  { $where[] = 'dc.validated_by IS NOT NULL'; }
        if ($status === 'pending')    { $where[] = 'dc.validated_by IS NULL'; }
        $whereStr = implode(' AND ', $where);

        $closures = Database::select(
            "SELECT dc.id, dc.cash_register_id,
                    dc.theoretical_amount, dc.declared_amount, dc.gap_amount,
                    dc.ticket_count, dc.closed_at, dc.validated_by, dc.validated_at, dc.notes,
                    cr.opened_at, cr.opening_amount,
                    a.name AS agency_name,
                    c.name  AS caisse_name, c.code AS caisse_code,
                    u.first_name AS closer_first, u.last_name AS closer_last,
                    v.first_name AS validator_first, v.last_name AS validator_last
             FROM daily_closures dc
             JOIN cash_registers cr ON cr.id  = dc.cash_register_id
             JOIN agencies a         ON a.id  = cr.agency_id
             LEFT JOIN caisses c     ON c.id  = cr.caisse_id
             LEFT JOIN users u       ON u.id  = dc.closed_by
             LEFT JOIN users v       ON v.id  = dc.validated_by
             WHERE $whereStr
             ORDER BY dc.closed_at DESC
             LIMIT 150",
            $params
        );

        // KPI — comptages clôtures
        $kpi = Database::selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN dc.validated_by IS NULL THEN 1 ELSE 0 END) AS pending,
                    SUM(ABS(dc.gap_amount)) AS total_ecart,
                    SUM(CASE WHEN DATE(dc.closed_at) = CURDATE() THEN 1 ELSE 0 END) AS today
             FROM daily_closures dc"
        );

        // KPI financiers — totaux enc/dec sur toutes les transactions (filtrés comme la liste)
        $finKpiWhere = $whereStr
            ? str_replace('dc.', 'dc2.', str_replace('cr.agency_id', 'cr2.agency_id', $whereStr))
            : '1=1';
        $finKpi = Database::selectOne(
            "SELECT
               COALESCE(SUM(CASE WHEN tt.type='encaissement' THEN tt.amount_fcfa ELSE 0 END),0) AS total_enc,
               COALESCE(SUM(CASE WHEN tt.type='decaissement' THEN tt.amount_fcfa ELSE 0 END),0) AS total_dec
             FROM treasury_transactions tt
             JOIN cash_registers cr2 ON cr2.id = tt.cash_register_id
             JOIN daily_closures dc2 ON dc2.cash_register_id = cr2.id
             WHERE COALESCE(tt.status,'pending') != 'rejected'
               AND $finKpiWhere",
            $params
        ) ?: ['total_enc' => 0, 'total_dec' => 0];

        $agencies = Database::select("SELECT id, name FROM agencies WHERE is_active=1 ORDER BY name");

        // Sessions ouvertes avec théorique + enc/dec détaillés
        $openRegisters = Database::select(
            "SELECT cr.id, cr.opening_amount, cr.opened_at, cr.agency_id,
                    a.name AS agency_name,
                    u.first_name AS cashier_first, u.last_name AS cashier_last,
                    c.name AS caisse_name, c.code AS caisse_code,
                    COALESCE((SELECT SUM(s.amount_fcfa) FROM sales s WHERE s.cash_register_id=cr.id),0) AS total_ventes,
                    COALESCE((SELECT SUM(tt.amount_fcfa) FROM treasury_transactions tt WHERE tt.cash_register_id=cr.id AND tt.type='encaissement'),0) AS total_tx_enc,
                    COALESCE((SELECT SUM(tt.amount_fcfa) FROM treasury_transactions tt WHERE tt.cash_register_id=cr.id AND tt.type='decaissement'),0) AS total_tx_dec,
                    COALESCE(cr.opening_amount,0)
                      + COALESCE((SELECT SUM(s.amount_fcfa)  FROM sales s WHERE s.cash_register_id=cr.id),0)
                      + COALESCE((SELECT SUM(e.amount_fcfa)  FROM cash_register_entries e WHERE e.cash_register_id=cr.id),0)
                      + COALESCE((SELECT SUM(CASE WHEN tt.type='encaissement' THEN tt.amount_fcfa ELSE 0 END) FROM treasury_transactions tt WHERE tt.cash_register_id=cr.id),0)
                      - COALESCE((SELECT SUM(CASE WHEN tt.type='decaissement' THEN tt.amount_fcfa ELSE 0 END) FROM treasury_transactions tt WHERE tt.cash_register_id=cr.id),0)
                    AS theorique
             FROM cash_registers cr
             JOIN agencies a ON a.id=cr.agency_id
             JOIN users u ON u.id=cr.cashier_id
             LEFT JOIN caisses c ON c.id=cr.caisse_id
             WHERE cr.status='ouverte'
             ORDER BY cr.opened_at DESC"
        );

        // Postes disponibles pour ouvrir une caisse
        $postes = \CityBus\Controllers\Finance\CaisseManagementController::loadActive();

        $this->view('treasury/closures', [
            'title'         => 'Gestion des caisses',
            'closures'      => $closures,
            'kpi'           => $kpi,
            'finKpi'        => $finKpi,
            'agencies'      => $agencies,
            'agencyId'      => $agencyId,
            'status'        => $status,
            'dateFrom'      => $dateFrom,
            'dateTo'        => $dateTo,
            'openRegisters' => $openRegisters,
            'postes'        => $postes,
        ]);
    }

    public function showClosure2(Request $request, string $id): void
    {
        $closure = Database::selectOne(
            "SELECT dc.*, cr.opened_at, cr.opening_amount,
                    a.name  AS agency_name,
                    c.name  AS caisse_name, c.code AS caisse_code,
                    u.first_name AS closer_first, u.last_name AS closer_last,
                    v.first_name AS validator_first, v.last_name AS validator_last
             FROM daily_closures dc
             JOIN cash_registers cr ON cr.id = dc.cash_register_id
             JOIN agencies a        ON a.id  = cr.agency_id
             LEFT JOIN caisses c    ON c.id  = cr.caisse_id
             LEFT JOIN users u      ON u.id  = dc.closed_by
             LEFT JOIN users v      ON v.id  = dc.validated_by
             WHERE dc.id = ?",
            [(int)$id]
        );
        if (!$closure) {
            $this->flash('danger', 'Clôture introuvable.');
            redirect('finance/treasury/closures'); return;
        }

        // Billettage (dénominations) lié à cette clôture
        $denoms = Database::select(
            "SELECT * FROM treasury_closure_denominations WHERE closure_id = ? ORDER BY denomination DESC",
            [(int)$id]
        );

        // Détail des ventes par mode de paiement
        $salesByMode = Database::select(
            "SELECT payment_method, SUM(amount_fcfa) AS total, COUNT(*) AS cnt
             FROM sales WHERE cash_register_id = ?
             GROUP BY payment_method ORDER BY total DESC",
            [(int)$closure['cash_register_id']]
        );

        // Transactions trésorerie liées
        $txList = Database::select(
            "SELECT tt.*, tc.label AS cat_label, tc.color AS cat_color
             FROM treasury_transactions tt
             JOIN treasury_categories tc ON tc.id = tt.category_id
             WHERE tt.cash_register_id = ?
             ORDER BY tt.created_at DESC",
            [(int)$closure['cash_register_id']]
        );

        $closureNum = 'CLO-' . date('ymd', strtotime($closure['opened_at'])) . '-'
                    . str_pad((string)$closure['cash_register_id'], 3, '0', STR_PAD_LEFT);

        $this->view('treasury/closure_detail', [
            'title'       => 'Clôture ' . $closureNum,
            'closure'     => $closure,
            'closureNum'  => $closureNum,
            'denoms'      => $denoms,
            'salesByMode' => $salesByMode,
            'txList'      => $txList,
        ]);
    }

    public function validateClosure(Request $request, string $id): void
    {
        $affected = Database::execute(
            "UPDATE daily_closures SET validated_by=?, validated_at=NOW() WHERE id=? AND validated_by IS NULL",
            [(int)Auth::id(), (int)$id]
        );
        if (!$affected) {
            $this->flash('warning', 'Clôture déjà validée ou introuvable.');
        } else {
            AuditLog::record('treasury.closure.validate', 'daily_closure', (int)$id);
            $this->flash('success', 'Clôture validée.');
        }
        back();
    }

    /* ═══════════════════════════════════════════
       QUICK EXPENSE — endpoint AJAX bidirectionnel
       Appelé depuis les fiches voyage / bus / chauffeur
    ═══════════════════════════════════════════ */
    public function quickExpense(Request $request): void
    {
        if ($request->method !== 'POST') {
            Response::json(['error' => 'Méthode invalide.'], 405);
        }

        if (!Auth::can('finance.treasury.manage')) {
            Response::json(['error' => 'Permission refusée.'], 403);
        }

        $categoryCode  = trim((string)$request->input('category_code', ''));
        $amount        = (int)$request->input('amount_fcfa', 0);
        $description   = trim((string)$request->input('description', ''));
        $paymentMethod = trim((string)$request->input('payment_method', 'especes'));
        $reference     = trim((string)$request->input('reference', ''));

        // Auto-calcul montant carburant si litres fournis
        if ($categoryCode === 'carburant' && $amount <= 0) {
            $liters  = (float)$request->input('liters', 0);
            $priceL  = (float)$request->input('price_per_liter', 625);
            if ($liters > 0 && $priceL > 0) {
                $amount = (int)round($liters * $priceL);
            }
        }

        if ($categoryCode === '' || $amount <= 0) {
            Response::json(['error' => 'Catégorie et montant requis.'], 422);
        }

        $service = new TreasuryExpenseService();

        try {
            $tx = $service->create([
                'category_code'    => $categoryCode,
                'amount_fcfa'      => $amount,
                'description'      => $description,
                'payment_method'   => $paymentMethod,
                'reference'        => $reference,
                'trip_id'          => $request->input('trip_id'),
                'bus_id'           => $request->input('bus_id'),
                'driver_id'        => $request->input('driver_id'),
                // Champs carburant
                'liters'           => $request->input('liters'),
                'price_per_liter'  => $request->input('price_per_liter'),
                'km_at_fill'       => $request->input('km_at_fill'),
                'station_name'     => $request->input('station_name'),
                // Champs maintenance
                'maintenance_type' => $request->input('maintenance_type'),
                'mechanic_id'      => $request->input('mechanic_id'),
                'cash_register_id' => $request->input('cash_register_id'),
                // Champs pneumatique
                'tire_position'    => $request->input('tire_position'),
                'tire_brand'       => $request->input('tire_brand'),
                'tire_size'        => $request->input('tire_size'),
                'tire_type'        => $request->input('tire_type'),
                'tire_quantity'    => $request->input('tire_quantity'),
                'tire_km'          => $request->input('tire_km'),
                'tire_supplier'    => $request->input('tire_supplier'),
                // Champs assurance
                'insurance_company'=> $request->input('insurance_company'),
                'insurance_policy' => $request->input('insurance_policy'),
                'coverage_type'    => $request->input('coverage_type'),
                'insurance_start'  => $request->input('insurance_start'),
                'insurance_end'    => $request->input('insurance_end'),
                // Champs visite technique
                'inspection_date'       => $request->input('inspection_date'),
                'inspection_center'     => $request->input('inspection_center'),
                'inspection_result'     => $request->input('inspection_result'),
                'inspection_certificate'=> $request->input('inspection_certificate'),
                'inspection_next_due'   => $request->input('inspection_next_due'),
                'inspection_observations'=> $request->input('inspection_observations'),
                // Champs amende
                'infraction_type'  => $request->input('infraction_type'),
                'fine_location'    => $request->input('fine_location'),
                'fine_authority'   => $request->input('fine_authority'),
                'fine_date'        => $request->input('fine_date'),
                'is_contested'     => $request->input('is_contested'),
                // Champs lavage
                'wash_type'        => $request->input('wash_type'),
                'wash_location'    => $request->input('wash_location'),
                // Champs péage
                'toll_name'        => $request->input('toll_name'),
                'toll_route'       => $request->input('toll_route'),
                // Champs parking
                'parking_location' => $request->input('parking_location'),
                'parking_duration' => $request->input('parking_duration'),
                // Champs salaire/avance
                'payroll_month'    => $request->input('payroll_month'),
                'payroll_year'     => $request->input('payroll_year'),
                'payroll_base'     => $request->input('payroll_base'),
                'payroll_deductions'=> $request->input('payroll_deductions'),
                'payroll_notes'    => $request->input('payroll_notes'),
                // Champs compensations chauffeur
                'comp_reason'      => $request->input('comp_reason'),
                'comp_rate_type'   => $request->input('comp_rate_type'),
                'comp_rate_value'  => $request->input('comp_rate_value'),
            ]);

            AuditLog::record('treasury.quick_expense', 'treasury_transaction', (int)$tx['id'], [
                'category' => $categoryCode, 'amount' => $amount,
            ]);

            Response::json(['success' => true, 'transaction' => $tx]);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }
}
