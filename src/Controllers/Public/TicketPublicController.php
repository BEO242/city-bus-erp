<?php

declare(strict_types=1);

namespace CityBus\Controllers\Public;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\WalletPassService;

/**
 * Page publique d'affichage d'un e-billet via son QR token (pas d'authentification).
 * Permet le partage WhatsApp et l'ajout à l'agenda.
 */
final class TicketPublicController extends Controller
{
    public function show(Request $request, string $token): void
    {
        $ticket = Database::selectOne(
            "SELECT t.*, tr.trip_code, tr.trip_date, tr.departure_scheduled, tr.arrival_scheduled,
                    l.code AS line_code, l.name AS line_name,
                    cf.name AS from_city, ct.name AS to_city
             FROM tickets t
             JOIN trips tr ON tr.id = t.trip_id
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             LEFT JOIN cities cf ON cf.id = l.departure_city_id
             LEFT JOIN cities ct ON ct.id = l.arrival_city_id
             WHERE t.qr_code = ? AND t.deleted_at IS NULL", [$token]
        );
        if (!$ticket) { http_response_code(404); $this->view('errors/404'); return; }

        $wallet = new WalletPassService();
        $this->view('public/ticket', [
            'title'   => 'Mon billet CITY BUS',
            'ticket'  => $ticket,
            'shareText' => urlencode($wallet->whatsappShareText($ticket, $ticket)),
            'gcalUrl' => $wallet->googleCalendarUrl($ticket, $ticket),
            'icsUrl'  => url('public/ticket/' . $token . '.ics'),
        ]);
    }

    public function ics(Request $request, string $token): void
    {
        $ticket = Database::selectOne("SELECT * FROM tickets WHERE qr_code = ?", [$token]);
        if (!$ticket) { http_response_code(404); exit; }

        $ics = (new WalletPassService())->generateIcs($ticket);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="citybus-' . $ticket['ticket_number'] . '.ics"');
        echo $ics;
        exit;
    }
}
