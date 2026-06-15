<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;
use Ramsey\Uuid\Uuid;

final class PrePrintService
{
    public function __construct(
        private QrCodeService $qr = new QrCodeService(),
        private PdfService $pdf = new PdfService(),
    ) {}

    /** Types de pré-impression supportés. */
    public const PREPRINT_TYPES = [
        'billet'       => 'Billet passager',
        'talon_bagage' => 'Talon bagage',
        'talon_colis'  => 'Talon colis',
    ];

    /**
     * Génère un lot de N supports pré-imprimés.
     * @param string $preprintType  billet|talon_bagage|talon_colis
     */
    public function generateBatch(
        int $size, int $agencyId, int $userId,
        ?string $notes = null, ?int $tripId = null,
        string $ticketType = 'passage_final', ?string $ticketColor = null,
        string $preprintType = 'billet'
    ): array {
        if ($size < 1 || $size > 500) {
            throw new \InvalidArgumentException('Taille du lot : entre 1 et 500.');
        }
        if (!isset(self::PREPRINT_TYPES[$preprintType])) {
            throw new \InvalidArgumentException('Type de pré-impression invalide.');
        }

        $isBillet = ($preprintType === 'billet');

        // Ticket type validation only matters for billets
        if ($isBillet) {
            $allowedTypes = ['passage_arret', 'passage_final', 'bagage_excedent', 'bagage_inclus', 'talon_arret'];
            if (!in_array($ticketType, $allowedTypes, true)) {
                throw new \InvalidArgumentException('Type de ticket invalide.');
            }
        } else {
            // Talons bagage/colis use a fixed ticket_type
            $ticketType = ($preprintType === 'talon_bagage') ? 'bagage_excedent' : 'passage_arret';
        }

        $batchId = Uuid::uuid4()->toString();

        // Charger config couleurs
        $typeConfigs = $this->loadTypeConfigs();

        // Couleur effective
        if (!$ticketColor) {
            if ($isBillet) {
                $ticketColor = $typeConfigs[$ticketType]['color']
                    ?? match($ticketType) {
                        'passage_arret'   => '#C62828',
                        'passage_final'   => '#1A237E',
                        'bagage_excedent' => '#F57C00',
                        'bagage_inclus'   => '#1A237E',
                        'talon_arret'     => '#C62828',
                        default           => '#C62828',
                    };
            } else {
                $ticketColor = ($preprintType === 'talon_bagage') ? '#0D47A1' : '#4A148C';
            }
        }

        // Charger les infos du voyage si lié (obligatoire pour billets, optionnel pour talons)
        $trip = null;
        if ($tripId) {
            $trip = Database::selectOne(
                "SELECT tr.*, l.name AS line_name,
                        cd.slug AS departure_city, cd.name AS departure_city_name,
                        ca.slug AS arrival_city,   ca.name AS arrival_city_name,
                        b.seats, b.code AS bus_code
                 FROM trips tr
                 JOIN bus_lines l ON l.id = tr.line_id
                 JOIN cities cd ON cd.id = l.departure_city_id
                 JOIN cities ca ON ca.id = l.arrival_city_id
                 JOIN buses b ON b.id = tr.bus_id
                 WHERE tr.id = ?",
                [$tripId]
            );
            if (!$trip) throw new \InvalidArgumentException('Voyage introuvable.');
            if ($isBillet && $size > (int)$trip['seats']) {
                throw new \InvalidArgumentException("Le lot ({$size}) dépasse la capacité du bus ({$trip['seats']} sièges).");
            }
        } elseif ($isBillet) {
            throw new \InvalidArgumentException('Un voyage est requis pour les billets pré-imprimés.');
        }

        return Database::transaction(function () use ($size, $agencyId, $userId, $notes, $batchId, $tripId, $trip, $ticketType, $ticketColor, $typeConfigs, $preprintType, $isBillet) {
            $year = date('Y');

            // Préfixe de numérotation selon le preprint_type
            $prefixMap = [
                'billet'       => null, // Utilise la config du ticket_type
                'talon_bagage' => 'CB-TB',
                'talon_colis'  => 'CB-TC',
            ];

            if ($isBillet) {
                $typeCfg = $typeConfigs[$ticketType] ?? [];
                $prefix  = $typeCfg['number_prefix']  ?? 'CB-PP';
                $padding = max(4, (int)($typeCfg['number_padding'] ?? 5));
            } else {
                $prefix  = $prefixMap[$preprintType];
                $padding = 5;
            }
            $pattern = "{$prefix}-{$year}-%";

            // Numéro courant (FOR UPDATE = verrou optimiste)
            $row = Database::selectOne(
                "SELECT pre_print_number FROM pre_printed_tickets
                 WHERE pre_print_number LIKE ?
                 ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$pattern]
            );
            $next = 1;
            if ($row) {
                $parts = explode('-', $row['pre_print_number']);
                $last  = (int)end($parts);
                $next  = $last + 1;
            }

            $tickets = [];
            for ($i = 0; $i < $size; $i++) {
                $number     = sprintf('%s-%s-%0'.$padding.'d', $prefix, $year, $next++);
                $seatNumber = $isBillet ? ($i + 1) : null;
                $qrCode     = $this->qr->generateUuid();
                $hash       = $this->qr->hash($qrCode);
                $shortCode  = $this->qr->generateShortCode('pre_printed_tickets', 'short_code');

                $id = Database::insert(
                    "INSERT INTO pre_printed_tickets
                     (preprint_type, pre_print_number, batch_id, trip_id, ticket_type, ticket_color,
                      seat_number, qr_code, qr_code_hash, short_code,
                      agency_id, pre_printed_by, pre_printed_at, status, notes)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW(), 'disponible', ?)",
                    [$preprintType, $number, $batchId, $tripId, $ticketType, $ticketColor,
                     $seatNumber, $qrCode, $hash, $shortCode,
                     $agencyId, $userId, $notes]
                );

                $tickets[] = [
                    'id'               => $id,
                    'pre_print_number' => $number,
                    'qr_code'          => $qrCode,
                    'short_code'       => $shortCode,
                    'seat_number'      => $seatNumber,
                    'ticket_type'      => $ticketType,
                    'ticket_color'     => $ticketColor,
                    'preprint_type'    => $preprintType,
                    'agency_name'      => null,
                ];
            }

            $company = company_info(true);
            $pdfPath = $this->pdf->generatePrePrintBatch($tickets, $batchId, $trip, $typeConfigs, [
                'logo_base64'   => $company['logo_base64'] ?? null,
                'company_name'  => $company['name'],
                'company_phone' => $company['phone'],
                'company_email' => $company['email'],
                'company'       => $company,
                'preprint_type' => $preprintType,
            ]);
            Database::execute(
                "UPDATE pre_printed_tickets SET pdf_path=? WHERE batch_id=?",
                [$pdfPath, $batchId]
            );

            AuditLog::record('preprint.batch.create', 'preprint_batch', null, [
                'batch_id' => $batchId, 'size' => $size, 'agency_id' => $agencyId,
                'trip_id' => $tripId, 'ticket_type' => $ticketType,
                'preprint_type' => $preprintType,
            ]);

            return ['batch_id' => $batchId, 'pdf_path' => $pdfPath, 'tickets' => $tickets, 'size' => $size, 'preprint_type' => $preprintType];
        });
    }

    /** Charge le logo société en base64 (data URI) depuis le chemin paramétré en settings. */
    private function loadLogoBase64(): ?string
    {
        $path = Setting::getString('company.logo_path');
        if (!$path) return null;
        $full = BASE_PATH . '/public/' . ltrim($path, '/');
        if (!file_exists($full)) return null;
        $mime = mime_content_type($full) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($full));
    }

    /** Charge la config couleurs ET numérotation depuis DB (avec fallback). */
    public function loadTypeConfigs(): array
    {
        $rows = Database::select(
            "SELECT type_key, color, text_color, label, description,
                    COALESCE(number_prefix,'CB-PP')  AS number_prefix,
                    COALESCE(number_padding,5)        AS number_padding,
                    COALESCE(number_reset,'yearly')   AS number_reset,
                    COALESCE(layout_variant,'A')      AS layout_variant,
                    COALESCE(row_height_mm, CASE WHEN type_key='talon_arret' THEN 80 ELSE 62 END) AS row_height_mm,
                    COALESCE(show_qr,1)               AS show_qr,
                    COALESCE(show_company_contact,1)  AS show_company_contact,
                    COALESCE(show_company_phone,1)    AS show_company_phone,
                    COALESCE(show_trip_info,1)        AS show_trip_info,
                    COALESCE(show_seat_info,1)        AS show_seat_info,
                    COALESCE(show_price_field,1)      AS show_price_field,
                    COALESCE(show_agency_stub,1)      AS show_agency_stub,
                    COALESCE(show_passenger_reference,1) AS show_passenger_reference
             FROM ticket_type_configs"
        );
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['type_key']] = [
                'color'          => $r['color'],
                'text_color'     => $r['text_color'],
                'label'          => $r['label'],
                'description'    => $r['description'],
                'number_prefix'  => $r['number_prefix'],
                'number_padding' => (int)$r['number_padding'],
                'number_reset'   => $r['number_reset'],
                'layout_variant' => $r['layout_variant'],
                'row_height_mm'  => (int)$r['row_height_mm'],
                'show_qr'        => (bool)$r['show_qr'],
                'show_company_contact' => (bool)$r['show_company_contact'],
                'show_company_phone' => (bool)$r['show_company_phone'],
                'show_trip_info' => (bool)$r['show_trip_info'],
                'show_seat_info' => (bool)$r['show_seat_info'],
                'show_price_field' => (bool)$r['show_price_field'],
                'show_agency_stub' => (bool)$r['show_agency_stub'],
                'show_passenger_reference' => (bool)$r['show_passenger_reference'],
            ];
        }
        return $out;
    }

    public function cancel(int $id, string $reason, int $userId): bool
    {
        return Database::transaction(function () use ($id, $reason, $userId) {
            $pp = Database::selectOne(
                "SELECT * FROM pre_printed_tickets WHERE id=? AND deleted_at IS NULL FOR UPDATE",
                [$id]
            );
            if (!$pp) throw new \RuntimeException('Support introuvable.');
            if ($pp['status'] !== 'disponible') {
                throw new \RuntimeException('Seuls les supports disponibles peuvent être annulés.');
            }
            Database::execute(
                "UPDATE pre_printed_tickets
                 SET status='annule', cancelled_by=?, cancelled_at=NOW(), cancel_reason=?, deleted_at=NOW()
                 WHERE id=?",
                [$userId, $reason, $id]
            );
            AuditLog::record('preprint.cancel', 'preprint', $id, ['reason' => $reason]);
            return true;
        });
    }

    /** Recherche par QR code, numéro, ou code court. */
    public function findByQrOrNumber(string $value): ?array
    {
        $upper = strtoupper(trim($value));
        return Database::selectOne(
            "SELECT * FROM pre_printed_tickets
             WHERE (qr_code = ? OR pre_print_number = ? OR short_code = ?) AND deleted_at IS NULL LIMIT 1",
            [$value, $upper, $upper]
        );
    }
}
