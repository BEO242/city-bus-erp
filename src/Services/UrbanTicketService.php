<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Auth;
use CityBus\Core\Database;
use Mpdf\Mpdf;

final class UrbanTicketService
{
    public const TICKETS_PER_PAGE = 24;
    public const COLS = 4;
    public const ROWS = 6;

    // ─── Symboles ───────────────────────────────────────────────────────

    public function symbols(): array
    {
        return Database::select(
            "SELECT * FROM urban_ticket_symbols WHERE is_active = 1 ORDER BY sort_order, id"
        );
    }

    public function symbolById(int $id): ?array
    {
        return Database::selectOne("SELECT * FROM urban_ticket_symbols WHERE id = ?", [$id]);
    }

    // ─── Séries ─────────────────────────────────────────────────────────

    public function allSeries(array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND s.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND s.ticket_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND s.ticket_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['bus_code'])) {
            $where .= ' AND s.bus_code LIKE ?';
            $params[] = '%' . $filters['bus_code'] . '%';
        }

        return Database::select(
            "SELECT s.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS creator_name,
                    CONCAT(uc.first_name, ' ', uc.last_name) AS closer_name
             FROM urban_ticket_series s
             JOIN users u ON u.id = s.created_by
             LEFT JOIN users uc ON uc.id = s.closed_by
             WHERE $where
             ORDER BY s.ticket_date DESC, s.num_start DESC
             LIMIT 200",
            $params
        );
    }

    public function getById(int $id): ?array
    {
        return Database::selectOne(
            "SELECT s.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS creator_name,
                    CONCAT(uc.first_name, ' ', uc.last_name) AS closer_name
             FROM urban_ticket_series s
             JOIN users u ON u.id = s.created_by
             LEFT JOIN users uc ON uc.id = s.closed_by
             WHERE s.id = ?",
            [$id]
        );
    }

    public function stats(): array
    {
        return Database::selectOne(
            "SELECT
                COUNT(*)                              AS total_series,
                SUM(status = 'planifiee')             AS planifiees,
                SUM(status = 'en_cours')              AS en_cours,
                SUM(status = 'cloturee')              AS cloturees,
                SUM(status = 'annulee')               AS annulees,
                COALESCE(SUM(ticket_count), 0)        AS total_tickets,
                COALESCE(SUM(tickets_sold), 0)        AS total_sold,
                COALESCE(SUM(revenue_expected), 0)    AS total_expected,
                COALESCE(SUM(revenue_actual), 0)      AS total_actual
             FROM urban_ticket_series"
        ) ?: [];
    }

    public function nextNumStart(): int
    {
        $max = Database::selectOne(
            "SELECT MAX(num_end) AS mx FROM urban_ticket_series WHERE status != 'annulee'"
        );
        return ($max && $max['mx']) ? ((int)$max['mx'] + 1) : 1;
    }

    // ─── Création ───────────────────────────────────────────────────────

    public function createSeries(array $data): array
    {
        $ticketDate = $data['ticket_date'];
        $dateCode   = date('ymd', strtotime($ticketDate));
        $symbolId   = (int)$data['symbol_id'];
        $symbol     = $this->symbolById($symbolId);
        if (!$symbol) {
            throw new \RuntimeException("Symbole invalide.");
        }

        $priceFcfa  = max(1, (int)($data['price_fcfa'] ?? 150));
        $busCode    = trim((string)($data['bus_code'] ?? ''));
        $departure  = trim((string)($data['departure'] ?? ''));
        $arrival    = trim((string)($data['arrival'] ?? ''));
        $network    = trim((string)($data['network_label'] ?? 'Réseau urbain · Brazzaville'));
        $numStart   = max(1, (int)($data['num_start'] ?? $this->nextNumStart()));
        $ticketCount = max(1, (int)($data['ticket_count'] ?? 96));

        $numEnd    = $numStart + $ticketCount - 1;
        $pageCount = (int)ceil($ticketCount / self::TICKETS_PER_PAGE);

        // Code séquentiel par date — toutes séries confondues (annulées incluses)
        $seqRow     = Database::selectOne(
            "SELECT COUNT(*) + 1 AS seq FROM urban_ticket_series WHERE date_code = ?",
            [$dateCode]
        );
        $seq        = (int)($seqRow['seq'] ?? 1);
        $seriesCode = 'URB-' . $dateCode . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

        // Vérifier chevauchement
        $overlap = Database::selectOne(
            "SELECT id FROM urban_ticket_series
             WHERE status != 'annulee'
               AND ((num_start <= ? AND num_end >= ?) OR (num_start <= ? AND num_end >= ?))
             LIMIT 1",
            [$numEnd, $numStart, $numEnd, $numStart]
        );
        if ($overlap) {
            throw new \RuntimeException("Chevauchement de numérotation avec la série #{$overlap['id']}. Prochain numéro disponible : " . $this->nextNumStart());
        }

        $id = (int)Database::insert(
            "INSERT INTO urban_ticket_series
                (series_code, ticket_date, date_code, symbol_id, symbol_char, price_fcfa,
                 bus_code, departure, arrival, network_label,
                 num_start, num_end, ticket_count, page_count,
                 revenue_expected, created_by, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'planifiee')",
            [
                $seriesCode, $ticketDate, $dateCode, $symbolId, $symbol['symbol'], $priceFcfa,
                $busCode, $departure, $arrival, $network,
                $numStart, $numEnd, $ticketCount, $pageCount,
                $ticketCount * $priceFcfa, (int)Auth::id(),
            ]
        );

        return $this->getById($id) ?: [];
    }

    // ─── Clôture ────────────────────────────────────────────────────────

    public function closeSeries(int $id, int $ticketsSold, ?int $revenueActual = null): void
    {
        $series = $this->getById($id);
        if (!$series) throw new \RuntimeException("Série introuvable.");
        if ($series['status'] === 'cloturee') throw new \RuntimeException("Série déjà clôturée.");
        if ($series['status'] === 'annulee') throw new \RuntimeException("Série annulée.");

        Database::execute(
            "UPDATE urban_ticket_series
             SET status = 'cloturee', tickets_sold = ?, revenue_actual = ?,
                 closed_by = ?, closed_at = NOW()
             WHERE id = ?",
            [$ticketsSold, $revenueActual, (int)Auth::id(), $id]
        );
    }

    public function startSeries(int $id): void
    {
        Database::execute(
            "UPDATE urban_ticket_series SET status = 'en_cours' WHERE id = ? AND status = 'planifiee'",
            [$id]
        );
    }

    public function cancelSeries(int $id): void
    {
        Database::execute(
            "UPDATE urban_ticket_series SET status = 'annulee' WHERE id = ? AND status IN ('planifiee','en_cours')",
            [$id]
        );
    }

    // ─── Génération PDF ─────────────────────────────────────────────────

    public function generatePdf(int $seriesId): string
    {
        $series = $this->getById($seriesId);
        if (!$series) throw new \RuntimeException("Série introuvable.");

        $ticketDate  = date('d/m/Y', strtotime($series['ticket_date']));
        $dateCode    = $series['date_code'];
        $symbol      = $series['symbol_char'];
        $busCode     = $series['bus_code'];
        $departure   = $series['departure'];
        $arrival     = $series['arrival'];
        $network     = $series['network_label'];
        $priceFcfa   = (int)$series['price_fcfa'];
        $priceLabel  = number_format($priceFcfa, 0, ',', ' ') . ' FCFA';
        $numStart    = (int)$series['num_start'];
        $numEnd      = (int)$series['num_end'];
        $ticketCount = (int)$series['ticket_count'];
        $pageCount   = (int)$series['page_count'];

        $css = $this->ticketCss();
        $html = '';

        $ticketNum = $numStart;
        for ($page = 0; $page < $pageCount; $page++) {
            if ($page > 0) {
                $html .= '<pagebreak />';
            }
            $html .= '<table class="sheet">';
            for ($row = 0; $row < self::ROWS; $row++) {
                $html .= '<tr>';
                for ($col = 0; $col < self::COLS; $col++) {
                    if ($ticketNum > $numEnd) {
                        $html .= '<td class="ticket empty"></td>';
                    } else {
                        $numFormatted = 'CB-' . $dateCode . '-' . str_pad((string)$ticketNum, 4, '0', STR_PAD_LEFT);
                        $html .= $this->ticketCell(
                            $numFormatted, $priceLabel, $network, $departure, $arrival,
                            $ticketDate, $busCode, $symbol
                        );
                        $ticketNum++;
                    }
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        $tempDir = BASE_PATH . '/storage/cache/mpdf';
        if (!is_dir($tempDir)) @mkdir($tempDir, 0775, true);

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_left'   => 5,
            'margin_right'  => 5,
            'margin_top'    => 5,
            'margin_bottom' => 5,
            'tempDir'       => $tempDir,
        ]);
        $mpdf->SetTitle('Tickets urbains — ' . $series['series_code']);
        $mpdf->SetAuthor('City Bus ERP');
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

        $dir = BASE_PATH . '/storage/urban-tickets';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $filename = 'urban-' . $series['series_code'] . '.pdf';
        $path = $dir . '/' . $filename;
        $mpdf->Output($path, \Mpdf\Output\Destination::FILE);

        Database::execute(
            "UPDATE urban_ticket_series SET pdf_path = ? WHERE id = ?",
            ['storage/urban-tickets/' . $filename, $seriesId]
        );

        return $path;
    }

    public function getPdfPath(int $seriesId): ?string
    {
        $series = $this->getById($seriesId);
        if (!$series || !$series['pdf_path']) return null;
        $full = BASE_PATH . '/' . $series['pdf_path'];
        return is_file($full) ? $full : null;
    }

    // ─── Génération HTML ticket ─────────────────────────────────────────

    private function ticketCell(
        string $number, string $price, string $network,
        string $departure, string $arrival,
        string $date, string $busCode, string $symbol
    ): string {
        $dep = htmlspecialchars($departure, ENT_QUOTES, 'UTF-8');
        $arr = htmlspecialchars($arrival, ENT_QUOTES, 'UTF-8');
        $net = htmlspecialchars($network, ENT_QUOTES, 'UTF-8');
        $sym = htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8');
        $pr  = htmlspecialchars($price,    ENT_QUOTES, 'UTF-8');
        $dt  = htmlspecialchars($date,     ENT_QUOTES, 'UTF-8');
        $bus = htmlspecialchars($busCode,  ENT_QUOTES, 'UTF-8');

        return <<<HTML
<td class="ticket">
<table class="ti" cellpadding="0" cellspacing="0">
  <tr>
    <td class="th" colspan="2">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="tbrand">CITY BUS</td>
          <td class="tprice">{$pr}</td>
        </tr>
      </table>
    </td>
  </tr>
  <tr><td class="tnet" colspan="2">{$net}</td></tr>
  <tr><td class="tnum" colspan="2">N&deg;&nbsp;{$number}</td></tr>
  <tr>
    <td class="troute" colspan="2">
      <table class="trb" cellpadding="0" cellspacing="0"><tr>
        <td class="trtd">{$dep}&nbsp;&rarr;&nbsp;{$arr}</td>
      </tr></table>
    </td>
  </tr>
  <tr>
    <td class="tdet">
      <span class="tlbl">Date</span><br/>
      <span class="tval">{$dt}</span><br/>
      <span class="tlbl">Bus</span>&nbsp;<span class="tval">{$bus}</span>
    </td>
    <td class="tsymc">
      <table class="tsymw" cellpadding="0" cellspacing="0"><tr>
        <td class="tsym">{$sym}</td>
      </tr></table>
    </td>
  </tr>
  <tr><td class="tfoot" colspan="2">1 voyage &middot; non remboursable</td></tr>
</table>
</td>
HTML;
    }

    private function ticketCss(): string
    {
        return <<<'CSS'
body { margin:0; padding:0; font-family:Arial,Helvetica,sans-serif; }
table.sheet { width:100%; border-collapse:collapse; table-layout:fixed; }
td.ticket { width:25%; height:47mm; border:0.3mm dashed #aaa; padding:0; vertical-align:top; overflow:hidden; }
td.ticket.empty { border-color:transparent; }

/* Inner layout table */
table.ti { width:100%; border-collapse:collapse; }

/* Header */
td.th { background:#111; padding:1.5mm 2mm 1.2mm; }
td.tbrand { color:#fff; font-weight:bold; font-size:8pt; letter-spacing:0.5pt; }
td.tprice { color:#fff; font-weight:bold; font-size:8pt; text-align:right; }

/* Network */
td.tnet { text-align:center; font-size:5.5pt; color:#555; padding:1mm 1mm 0.6mm; border-bottom:0.2mm solid #ddd; }

/* Serial number */
td.tnum { text-align:center; font-family:"Courier New",Courier,monospace; font-size:7pt; font-weight:bold; color:#222; padding:0.8mm 1mm; }

/* Route box */
td.troute { padding:0.3mm 1.5mm 0.5mm; }
table.trb { width:100%; border-collapse:collapse; border:0.3mm solid #333; }
td.trtd { text-align:center; font-size:6.5pt; font-weight:bold; color:#111; padding:1mm 1mm; }

/* Details row */
td.tdet { font-size:5.5pt; color:#222; line-height:1.7; padding:1.5mm 0 0 2mm; vertical-align:middle; height:16mm; }
span.tlbl { color:#999; font-size:5pt; }
span.tval { font-size:6.5pt; font-weight:bold; color:#222; }

/* Symbol */
td.tsymc { width:13mm; text-align:center; vertical-align:middle; padding-right:2mm; }
table.tsymw { border-collapse:collapse; margin:0 auto; }
td.tsym { border:0.5mm solid #333; width:10mm; height:10mm; text-align:center; vertical-align:middle; font-size:18pt; line-height:1; }

/* Footer */
td.tfoot { text-align:center; font-size:4.5pt; color:#666; background:#eee; padding:0.6mm; }
CSS;
    }
}
