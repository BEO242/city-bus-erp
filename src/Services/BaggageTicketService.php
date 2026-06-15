<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\View;
use CityBus\Models\BaggageTariff;
use CityBus\Models\BaggageTicket;

/**
 * Métier : vente, annulation et reprint des billets bagages.
 */
final class BaggageTicketService
{
    public function __construct(
        private QrCodeService $qr  = new QrCodeService(),
        private PdfService    $pdf = new PdfService(),
    ) {}

    /**
     * Crée un billet bagage dans une transaction atomique.
     *
     * @param array $data {
     *   trip_id, line_id, passenger_ticket_id?, passenger_name, passenger_phone?,
     *   baggage_tariff_id, baggage_nature_id, weight_kg,
     *   length_cm?, width_cm?, height_cm?, description?,
     *   agency_id, sold_by, cash_register_id?
     * }
     */
    public function create(array $data): array
    {
        return Database::transaction(function () use ($data) {

            // 1. Charger le tarif bagage et VALIDER : actif + dans sa fenêtre temporelle
            //    par rapport à la date du voyage (anti-fraude : on relit côté serveur)
            $tripDate = Database::selectOne(
                "SELECT trip_date FROM trips WHERE id = ?",
                [(int)$data['trip_id']]
            )['trip_date'] ?? date('Y-m-d');

            $tariff = Database::selectOne(
                "SELECT * FROM baggage_tariffs
                 WHERE id = ?
                   AND is_active = 1
                   AND (valid_from  IS NULL OR valid_from  <= ?)
                   AND (valid_until IS NULL OR valid_until >= ?)",
                [(int)$data['baggage_tariff_id'], $tripDate, $tripDate]
            );
            if (!$tariff) {
                throw new \RuntimeException(
                    'Tarif bagage introuvable, inactif ou hors période de validité pour cette date.'
                );
            }

            // Cohérence : la nature transmise doit être couverte par le tarif
            if (isset($data['baggage_nature_id'])) {
                $tariffNatureIds = json_decode($tariff['baggage_nature_ids'] ?? '[]', true) ?: [];
                if (!in_array((int)$data['baggage_nature_id'], $tariffNatureIds, true)) {
                    throw new \RuntimeException('Incohérence entre la nature et le tarif bagage.');
                }
            }

            $weightKg    = (float)$data['weight_kg'];
            $baseFee     = (int)$tariff['base_fee_fcfa'];
            $weightFee   = BaggageTariff::calculatePrice($tariff, $weightKg);
            if ($weightFee === null) {
                $maxKg = $tariff['max_weight_kg'] !== null
                    ? (float)$tariff['max_weight_kg']
                    : null;
                $msg = $maxKg !== null
                    ? sprintf('Poids %.2f kg hors limites du tarif (max %.2f kg).', $weightKg, $maxKg)
                    : sprintf('Poids %.2f kg hors des tranches définies pour ce tarif.', $weightKg);
                throw new \RuntimeException($msg);
            }
            $volSurcharge = 0;

            // Surcharge hors gabarit
            if ($tariff['volume_surcharge_fcfa'] !== null) {
                $oversize = BaggageTariff::isOversize(
                    $tariff,
                    isset($data['length_cm']) ? (int)$data['length_cm'] : null,
                    isset($data['width_cm'])  ? (int)$data['width_cm']  : null,
                    isset($data['height_cm']) ? (int)$data['height_cm'] : null,
                );
                if ($oversize) {
                    $volSurcharge = (int)$tariff['volume_surcharge_fcfa'];
                }
            }

            $totalPrice = $baseFee + $weightFee + $volSurcharge;

            // 2. Numéro de billet
            $ticketNumber = BaggageTicket::nextNumber();

            // 3. QR code
            $qrCode = $this->qr->generateUuid();

            // 4. Insertion
            $id = Database::insert(
                "INSERT INTO baggage_tickets
                   (ticket_number, trip_id, line_id, passenger_ticket_id,
                    passenger_name, passenger_phone,
                    baggage_tariff_id, baggage_nature_id,
                    weight_kg, length_cm, width_cm, height_cm, description,
                    base_fee_fcfa, weight_fee_fcfa, volume_surcharge_fcfa, total_price_fcfa,
                    agency_id, sold_by, cash_register_id,
                    status, qr_code_path, sold_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'emis',?,NOW())",
                [
                    $ticketNumber,
                    (int)$data['trip_id'],
                    (int)$data['line_id'],
                    isset($data['passenger_ticket_id']) && $data['passenger_ticket_id'] > 0
                        ? (int)$data['passenger_ticket_id'] : null,
                    $data['passenger_name'],
                    $data['passenger_phone'] ?? null,
                    (int)$data['baggage_tariff_id'],
                    (int)$data['baggage_nature_id'],
                    $weightKg,
                    isset($data['length_cm']) && $data['length_cm'] !== '' ? (int)$data['length_cm'] : null,
                    isset($data['width_cm'])  && $data['width_cm']  !== '' ? (int)$data['width_cm']  : null,
                    isset($data['height_cm']) && $data['height_cm'] !== '' ? (int)$data['height_cm'] : null,
                    $data['description'] ?? null,
                    $baseFee, $weightFee, $volSurcharge, $totalPrice,
                    (int)($data['agency_id'] ?? 1),
                    (int)$data['sold_by'],
                    isset($data['cash_register_id']) ? (int)$data['cash_register_id'] : null,
                    $qrCode,
                ]
            );

            // 5. Enregistrer dans la caisse + journal des ventes
            if (!empty($data['cash_register_id']) && $totalPrice > 0) {
                Database::execute(
                    "INSERT INTO cash_register_entries
                       (cash_register_id, entry_type, amount_fcfa, reference_type, reference_id, note, created_at)
                     VALUES (?, 'vente_bagage', ?, 'baggage_ticket', ?, ?, NOW())",
                    [
                        (int)$data['cash_register_id'],
                        $totalPrice,
                        $id,
                        'Billet bagage ' . $ticketNumber,
                    ]
                );

                // Journal des ventes (table sales) — pour cohérence reporting CA
                Database::execute(
                    "INSERT INTO sales (cash_register_id, sale_type, ticket_id, baggage_ticket_id, amount_fcfa, payment_method)
                     VALUES (?, 'baggage', NULL, ?, ?, ?)",
                    [
                        (int)$data['cash_register_id'],
                        $id,
                        $totalPrice,
                        $data['payment_method'] ?? 'especes',
                    ]
                );
            }

            $ticket = BaggageTicket::findWithRelations((int)$id);

            // 6. Générer le PDF
            $pdfPath = $this->generatePdf($ticket, $qrCode);
            Database::execute(
                "UPDATE baggage_tickets SET pdf_path = ? WHERE id = ?",
                [$pdfPath, $id]
            );
            $ticket['pdf_path'] = $pdfPath;

            return $ticket;
        });
    }

    /**
     * Annule un billet bagage.
     */
    public function cancel(int $id, string $reason, int $cancelledBy): void
    {
        $ticket = Database::selectOne("SELECT * FROM baggage_tickets WHERE id = ?", [$id]);
        if (!$ticket) {
            throw new \RuntimeException('Billet bagage introuvable.');
        }
        if ($ticket['status'] === 'annule') {
            throw new \RuntimeException('Ce billet est déjà annulé.');
        }

        Database::transaction(function () use ($ticket, $id, $reason, $cancelledBy) {
            Database::execute(
                "UPDATE baggage_tickets
                 SET status='annule', cancelled_at=NOW(), cancelled_by=?, cancel_reason=?
                 WHERE id=?",
                [$cancelledBy, $reason, $id]
            );

            // Contre-passation en caisse
            if (!empty($ticket['cash_register_id']) && (int)$ticket['total_price_fcfa'] > 0) {
                Database::execute(
                    "INSERT INTO cash_register_entries
                       (cash_register_id, entry_type, amount_fcfa, reference_type, reference_id, note, created_at)
                     VALUES (?, 'remboursement_bagage', ?, 'baggage_ticket', ?, ?, NOW())",
                    [
                        (int)$ticket['cash_register_id'],
                        -(int)$ticket['total_price_fcfa'],
                        $id,
                        'Annulation ' . $ticket['ticket_number'],
                    ]
                );
            }
        });
    }

    /**
     * Génère le PDF d'un billet bagage. Retourne le chemin relatif.
     */
    public function generatePdf(array $ticket, string $qrCode): string
    {
        $qrBase64 = $this->qr->generateBase64($qrCode, 240, 6);
        $agency   = Database::selectOne("SELECT * FROM agencies WHERE id = ?", [$ticket['agency_id'] ?? 1]);

        $html = (new View())->render('billetterie-bagages/pdf/ticket', [
            'ticket'   => $ticket,
            'agency'   => $agency,
            'qrBase64' => $qrBase64,
        ]);

        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A5',
            'orientation'   => 'P',
            'tempDir'       => BASE_PATH . '/storage/cache/mpdf',
            'default_font'  => 'dejavusans',
            'margin_left'   => 8, 'margin_right' => 8,
            'margin_top'    => 10, 'margin_bottom' => 10,
        ]);
        $mpdf->WriteHTML($html);

        $dir = BASE_PATH . '/storage/tickets';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $filename = $ticket['ticket_number'] . '.pdf';
        $mpdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);

        return 'tickets/' . $filename;
    }

    /**
     * Réimprime (ou régénère) le PDF d'un billet existant.
     */
    public function reprint(int $id): string
    {
        $ticket = BaggageTicket::findWithRelations($id);
        if (!$ticket) {
            throw new \RuntimeException('Billet bagage introuvable.');
        }
        $qrCode = $ticket['qr_code_path']; // on stocke le UUID ici
        $path   = $this->generatePdf($ticket, $qrCode);
        Database::execute("UPDATE baggage_tickets SET pdf_path=? WHERE id=?", [$path, $id]);
        return $path;
    }
}
