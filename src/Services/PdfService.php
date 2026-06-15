<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\View;
use Mpdf\Mpdf;

final class PdfService
{
    private function mpdf(string $format = 'A5', string $orientation = 'P'): Mpdf
    {
        $tempDir = BASE_PATH . '/storage/cache/mpdf';
        if (!is_dir($tempDir)) @mkdir($tempDir, 0775, true);

        return new Mpdf([
            'mode'         => 'utf-8',
            'format'       => $format,
            'orientation'  => $orientation,
            'tempDir'      => $tempDir,
            'default_font' => 'dejavusans',
            'margin_left'   => 8,
            'margin_right'  => 8,
            'margin_top'    => 10,
            'margin_bottom' => 10,
        ]);
    }

    /**
     * Mappe la valeur stockée dans tickets.ticket_type vers la clé de
     * ticket_type_configs (paramétrée dans /billetterie/preprint/config).
     *
     * Réalité observée :
     *  - tickets.ticket_type ENUM('passager','arret_route','bagage_franchise','bagage_excedent')
     *  - tariff_ticket_types.slug : 'finale' | 'arret_route'  (référentiel actuel)
     *  - ticket_type_configs.type_key : 'passage_final' | 'passage_arret'
     *      | 'bagage_excedent' | 'bagage_inclus' | 'talon_arret'
     */
    private function resolveTypeConfigKey(?string $ticketType): string
    {
        return match ($ticketType) {
            'arret_route'      => 'passage_arret',
            'bagage_franchise' => 'bagage_inclus',
            'bagage_excedent'  => 'bagage_excedent',
            'finale', 'passager', '', null => 'passage_final',
            default            => 'passage_final',
        };
    }

    /**
     * Génère le PDF d'un ticket et le sauvegarde dans storage/tickets/.
     * Retourne le chemin relatif.
     */
    public function generateTicket(array $ticket): string
    {
        $qr = new QrCodeService();
        // Taille du QR paramétrable
        $qrSize = max(80, min(400, \CityBus\Core\Setting::getInt('print.qr_size', 240)));
        $qrBase64 = $qr->generateBase64($ticket['qr_code'], $qrSize, 6);

        // Charger relations utiles
        $trip = Database::selectOne(
            "SELECT t.*, l.name AS line_name, l.code AS line_code, b.code AS bus_code, b.plate AS bus_plate
             FROM trips t
             JOIN bus_lines l ON l.id = t.line_id
             JOIN buses b ON b.id = t.bus_id
             WHERE t.id = ?", [$ticket['trip_id']]
        );
        $agency = Database::selectOne("SELECT * FROM agencies WHERE id = ?", [$ticket['agency_id']]);

        // Charger la config de couleurs/libellé du type depuis les paramètres
        $configKey  = $this->resolveTypeConfigKey($ticket['ticket_type'] ?? 'passager');
        $typeConfig = Database::selectOne(
            "SELECT type_key, label, color, text_color, show_qr, show_trip_info, show_seat_info,
                    show_price_field, show_company_contact, show_passenger_reference, layout_variant
               FROM ticket_type_configs WHERE type_key = ?",
            [$configKey]
        ) ?: [];

        $company = company_info(true);
        // Logo désactivable globalement via paramètre print.ticket_logo_enabled
        if (!\CityBus\Core\Setting::getBool('print.ticket_logo_enabled', true)) {
            unset($company['logo_base64']);
            $company['logo_path'] = '';
        }

        $html = (new View())->render('billetterie/pdf/ticket', [
            'ticket'      => $ticket,
            'trip'        => $trip,
            'agency'      => $agency,
            'qrBase64'    => $qrBase64,
            'typeConfig'  => $typeConfig,
            'company'     => $company,
            'footerText'  => \CityBus\Core\Setting::getString('print.ticket_footer_text', 'Conservez ce ticket pour tout contrôle'),
        ]);

        $mpdf = $this->mpdf('A5', 'P');
        $mpdf->WriteHTML($html);

        // Copies multiples paramétrables
        $copies = max(1, min(5, \CityBus\Core\Setting::getInt('print.receipt_copies', 1)));
        for ($i = 2; $i <= $copies; $i++) {
            $mpdf->AddPage();
            $mpdf->WriteHTML($html);
        }

        $dir = BASE_PATH . '/storage/tickets';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = "tickets/{$ticket['ticket_number']}.pdf";
        $mpdf->Output(BASE_PATH . '/storage/' . $path, \Mpdf\Output\Destination::FILE);

        return $path;
    }

    /**
     * Génère le PDF d'un lot de tickets pré-imprimés (grille A4).
     * @param array|null $trip        Informations du voyage lié (optionnel)
     * @param array      $typeConfigs Config couleurs par type [key => [color, text_color, label]]
     */
    public function generatePrePrintBatch(array $tickets, string $batchId, ?array $trip = null, array $typeConfigs = [], array $companyInfo = []): string
    {
        $qr = new QrCodeService();
        $items = array_map(function ($t) use ($qr) {
            $t['qr_base64'] = $qr->generateBase64($t['qr_code'], 200, 4);
            return $t;
        }, $tickets);

        $company = $companyInfo['company'] ?? company_info(true);
        $html = (new View())->render('billetterie/pdf/preprint-batch', [
            'tickets'       => $items,
            'batch_id'      => $batchId,
            'trip'          => $trip,
            'typeConfigs'   => $typeConfigs,
            'generated'     => now()->format('d/m/Y H:i'),
            'logo_base64'   => $companyInfo['logo_base64']  ?? ($company['logo_base64'] ?? null),
            'company_name'  => $companyInfo['company_name'] ?? $company['name'],
            'company_phone' => $companyInfo['company_phone'] ?? $company['phone'],
            'company_email' => $companyInfo['company_email'] ?? $company['email'],
            'company'       => $company,
            'preprint_type' => $companyInfo['preprint_type'] ?? 'billet',
        ]);

        $mpdf = $this->mpdf('A4', 'P');
        // Filigrane PRÉ-IMPRIMÉ paramétrable
        if (\CityBus\Core\Setting::getBool('print.preprint_watermark', false)) {
            $mpdf->SetWatermarkText('PRÉ-IMPRIMÉ');
            $mpdf->showWatermarkText = true;
            $mpdf->watermarkTextAlpha = 0.08;
        }
        $mpdf->WriteHTML($html);

        $dir = BASE_PATH . '/storage/tickets/preprint';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = "tickets/preprint/batch-{$batchId}.pdf";
        $mpdf->Output(BASE_PATH . '/storage/' . $path, \Mpdf\Output\Destination::FILE);
        return $path;
    }

    public function generatePayslip(array $payroll, array $employee, array $agency): string
    {
        $html = (new View())->render('rh/pdf/payslip', [
            'payroll' => $payroll,
            'employee' => $employee,
            'agency' => $agency,
            'company' => company_info(true),
        ]);
        $mpdf = $this->mpdf('A4', 'P');
        $mpdf->WriteHTML($html);

        $dir = BASE_PATH . '/storage/payroll';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = sprintf('payroll/%s_%d-%02d.pdf', $employee['matricule'], $payroll['year'], $payroll['month']);
        $mpdf->Output(BASE_PATH . '/storage/' . $path, \Mpdf\Output\Destination::FILE);
        return $path;
    }

    /** Génère l'étiquette PDF (A6) d'un colis avec QR. */
    public function generateParcelLabel(array $parcel): string
    {
        $qrSvc = new QrCodeService();
        $qrSize = 180;
        $qrPng  = $qrSvc->generatePng($parcel['qr_token'], $qrSize);
        $qrDataUri = 'data:image/png;base64,' . base64_encode($qrPng);

        $html = (new View())->render('cargo/pdf/label', [
            'parcel'     => $parcel,
            'qr'         => $qrDataUri,
            'company'    => company_info(true),
        ]);

        $mpdf = $this->mpdf('A6', 'P');
        $mpdf->WriteHTML($html);

        $dir = BASE_PATH . '/storage/cargo';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = sprintf('cargo/label-%s.pdf', $parcel['parcel_number']);
        $mpdf->Output(BASE_PATH . '/storage/' . $path, \Mpdf\Output\Destination::FILE);
        return $path;
    }

    /** Manifeste cargo PDF d'un voyage. */
    public function generateCargoManifest(array $trip, array $parcels, array $totals): string
    {
        $html = (new View())->render('cargo/pdf/manifest', [
            'trip'    => $trip,
            'parcels' => $parcels,
            'totals'  => $totals,
            'company' => company_info(true),
        ]);
        $mpdf = $this->mpdf('A4', 'P');
        $mpdf->WriteHTML($html);
        $dir = BASE_PATH . '/storage/cargo';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = sprintf('cargo/manifest-%s.pdf', $trip['trip_code'] ?? $trip['id']);
        $mpdf->Output(BASE_PATH . '/storage/' . $path, \Mpdf\Output\Destination::FILE);
        return $path;
    }

    /** Manifeste passagers PDF d'un voyage. */
    public function generateTripManifest(array $trip, array $tickets, array $crew): string
    {
        $html = (new View())->render('voyages/pdf/manifest', [
            'trip'    => $trip,
            'tickets' => $tickets,
            'crew'    => $crew,
            'company' => company_info(true),
        ]);
        $mpdf = $this->mpdf('A4', 'P');
        $mpdf->WriteHTML($html);
        $dir = BASE_PATH . '/storage/voyages';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = sprintf('voyages/manifest-%s.pdf', $trip['trip_code'] ?? $trip['id']);
        $mpdf->Output(BASE_PATH . '/storage/' . $path, \Mpdf\Output\Destination::FILE);
        return $path;
    }

    public function generateCashClosure(array $closure, array $register, array $sales): string
    {
        $html = (new View())->render('caisse/pdf/closure', [
            'closure'  => $closure,
            'register' => $register,
            'sales'    => $sales,
            'company'  => company_info(true),
        ]);
        $mpdf = $this->mpdf('A4', 'P');
        $mpdf->WriteHTML($html);

        $dir = BASE_PATH . '/storage/closures';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = sprintf('closures/closure-%d-%s.pdf', $closure['id'], date('Ymd'));
        $mpdf->Output(BASE_PATH . '/storage/' . $path, \Mpdf\Output\Destination::FILE);
        return $path;
    }
}
