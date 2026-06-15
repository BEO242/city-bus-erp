<?php

declare(strict_types=1);

namespace CityBus\Controllers\Admin;

use CityBus\Controllers\Controller;
use CityBus\Core\AuditPresenter;
use CityBus\Core\Database;
use CityBus\Core\Request;

final class AuditController extends Controller
{
    // ── Filtres communs ─────────────────────────────────────────────────────
    private function buildFilters(Request $request): array
    {
        return [
            'userF'    => (int)$request->input('user_id', 0),
            'actionF'  => trim((string)$request->input('action', '')),
            'entityF'  => trim((string)$request->input('entity', '')),
            'dateFrom' => trim((string)$request->input('from', '')),
            'dateTo'   => trim((string)$request->input('to', '')),
        ];
    }

    private function applyFilters(string $base, array $f): array
    {
        $sql    = $base;
        $params = [];
        if ($f['userF'] > 0)  { $sql .= " AND al.user_id = ?";     $params[] = $f['userF']; }
        if ($f['actionF'])    { $sql .= " AND al.action LIKE ?";    $params[] = "%{$f['actionF']}%"; }
        if ($f['entityF'])    { $sql .= " AND al.entity = ?";       $params[] = $f['entityF']; }
        if ($f['dateFrom'])   { $sql .= " AND al.created_at >= ?";  $params[] = $f['dateFrom']; }
        if ($f['dateTo'])     { $sql .= " AND al.created_at <= ?";  $params[] = $f['dateTo'] . ' 23:59:59'; }
        return [$sql, $params];
    }

    // ── Index ───────────────────────────────────────────────────────────────
    public function index(Request $request): void
    {
        // Purge automatique opportuniste (max 1 fois / 24h) selon audit.retention_days
        $this->purgeIfDue();

        $f    = $this->buildFilters($request);
        $page = max(1, (int)$request->input('page', 1));
        $per  = 50;

        [$sql, $params] = $this->applyFilters(
            "SELECT al.*, u.first_name, u.last_name, u.email
             FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE 1",
            $f
        );
        $dataSql  = $sql . " ORDER BY al.created_at DESC LIMIT {$per} OFFSET " . ($page - 1) * $per;
        $rows     = Database::select($dataSql, $params);

        [$csql, $cParams] = $this->applyFilters(
            "SELECT COUNT(*) AS n FROM audit_logs al WHERE 1",
            $f
        );
        $total = (int)(Database::selectOne($csql, $cParams)['n'] ?? 0);

        $users = Database::select(
            "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
             FROM audit_logs al JOIN users u ON u.id = al.user_id
             ORDER BY u.last_name, u.first_name"
        );

        // Groupes d'actions pour le filtre (code => libellé FR)
        $actionGroups = $this->buildActionGroups();
        // Groupes d'entités pour le filtre (code => libellé FR)
        $entityGroups = $this->buildEntityGroups();

        $this->view('admin/audit/index', [
            'title'        => 'Journal d\'activité',
            'rows'         => $rows,
            'total'        => $total,
            'page'         => $page,
            'per'          => $per,
            'lastPage'     => max(1, (int)ceil($total / $per)),
            'filters'      => $f,
            'users'        => $users,
            'actionGroups' => $actionGroups,
            'entityGroups' => $entityGroups,
        ]);
    }

    // ── Détail ──────────────────────────────────────────────────────────────
    public function show(Request $request, string $id): void
    {
        $row = Database::selectOne(
            "SELECT al.*, u.first_name, u.last_name, u.email
             FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
             WHERE al.id = ?",
            [(int)$id]
        );
        if (!$row) {
            $this->flash('danger', 'Entrée introuvable.');
            redirect('admin/audit');
        }

        // Décoder les détails JSON pour affichage
        $details = [];
        if ($row['details_json']) {
            $decoded = json_decode((string)$row['details_json'], true);
            if (is_array($decoded)) $details = $decoded;
        }

        $this->view('admin/audit/show', [
            'title'   => 'Détail audit #' . $row['id'],
            'row'     => $row,
            'details' => $details,
        ]);
    }

    // ── Export CSV ──────────────────────────────────────────────────────────
    public function exportCsv(Request $request): void
    {
        $f = $this->buildFilters($request);

        [$sql, $params] = $this->applyFilters(
            "SELECT al.id, al.created_at, al.action, al.entity, al.entity_id,
                    CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS user_name,
                    u.email, al.ip_address, al.mac_address, al.user_agent, al.details_json
             FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE 1",
            $f
        );
        $sql .= " ORDER BY al.created_at DESC";
        $rows = Database::select($sql, $params);

        $filename = 'journal_activite_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
        fputcsv($out, [
            'N°', 'Date', 'Heure', 'Utilisateur', 'Email',
            'Action (lisible)', 'Action (code)', 'Objet', 'N° objet',
            'Adresse IP', 'Adresse MAC', 'Appareil / Navigateur',
            'Données supplémentaires',
        ], ';');
        foreach ($rows as $r) {
            $details = [];
            if ($r['details_json']) {
                $d = json_decode((string)$r['details_json'], true);
                if (is_array($d)) {
                    foreach ($d as $k => $v) {
                        $details[] = AuditPresenter::detailKey($k) . ' : ' . AuditPresenter::detailValue($k, $v);
                    }
                }
            }
            $dt = strtotime($r['created_at']);
            fputcsv($out, [
                $r['id'],
                date('d/m/Y', $dt),
                date('H:i:s', $dt),
                trim($r['user_name']),
                $r['email'] ?? '',
                AuditPresenter::actionLabel($r['action']),
                $r['action'],
                $r['entity'] ? AuditPresenter::entityLabel($r['entity']) : '',
                $r['entity_id'] ?? '',
                $r['ip_address'] ?? '',
                $r['mac_address'] ?? '',
                AuditPresenter::deviceLabel((string)($r['user_agent'] ?? '')),
                implode(' | ', $details),
            ], ';');
        }
        fclose($out);
        exit;
    }

    /**
     * Liste des actions distinctes présentes en base, avec leur libellé FR.
     * @return array<string,string>
     */
    private function buildActionGroups(): array
    {
        $rows = Database::select("SELECT DISTINCT action FROM audit_logs ORDER BY action");
        $result = [];
        foreach ($rows as $r) {
            $result[$r['action']] = AuditPresenter::actionLabel($r['action']);
        }
        asort($result);
        return $result;
    }

    /**
     * Liste des entités distinctes présentes en base, avec leur libellé FR.
     * @return array<string,string>
     */
    private function buildEntityGroups(): array
    {
        $rows = Database::select("SELECT DISTINCT entity FROM audit_logs WHERE entity IS NOT NULL ORDER BY entity");
        $result = [];
        foreach ($rows as $r) {
            $result[$r['entity']] = AuditPresenter::entityLabel($r['entity']);
        }
        asort($result);
        return $result;
    }

    /**
     * Purge les enregistrements audit_logs au-delà de audit.retention_days.
     * Limitée à 1 exécution / 24 h via un fichier marqueur dans storage/cache/.
     */
    private function purgeIfDue(): void
    {
        $days = (int)\CityBus\Core\Setting::getInt('audit.retention_days', 365);
        if ($days <= 0) return;

        $marker = BASE_PATH . '/storage/cache/audit_purge.last';
        if (is_file($marker) && (time() - (int)filemtime($marker)) < 86400) {
            return; // déjà purgé dans les 24 dernières heures
        }
        @file_put_contents($marker, date('c'));

        try {
            Database::execute(
                "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::warning('Audit purge: échec', ['error' => $e->getMessage()]);
        }
    }
}
