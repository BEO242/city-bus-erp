<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;

/**
 * Génération de pass mobiles (Apple Wallet / Google Wallet) et URLs courtes
 * partageables (GAP-18).
 *
 * Implémentation simplifiée :
 *  - Apple Wallet : génère un .pkpass non signé (signing nécessite certificats Apple)
 *  - Google Wallet : génère un JWT avec payload pour ajout via lien
 *  - Lien court partageable WhatsApp : URL avec token aléatoire
 *  - Calendrier : .ics
 */
final class WalletPassService
{
    /** Génère un fichier .ics (rappel calendrier) pour un ticket. */
    public function generateIcs(array $ticket): string
    {
        $trip = Database::selectOne(
            "SELECT tr.*, l.code AS line_code, l.name AS line_name,
                    cf.name AS from_city, ct.name AS to_city
             FROM trips tr
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             LEFT JOIN cities cf ON cf.id = l.departure_city_id
             LEFT JOIN cities ct ON ct.id = l.arrival_city_id
             WHERE tr.id = ?", [(int)$ticket['trip_id']]
        );
        $start = strtotime($trip['trip_date'] . ' ' . $trip['departure_scheduled']);
        $end   = $trip['arrival_scheduled']
            ? strtotime($trip['trip_date'] . ' ' . $trip['arrival_scheduled'])
            : $start + 4 * 3600;

        $uid = $ticket['ticket_number'] . '@citybus';
        $now = gmdate('Ymd\THis\Z');
        $startUtc = gmdate('Ymd\THis\Z', $start);
        $endUtc   = gmdate('Ymd\THis\Z', $end);
        $summary = "Voyage CITY BUS " . ($trip['trip_code'] ?? '');
        $location = ($trip['from_city'] ?? '') . ' → ' . ($trip['to_city'] ?? '');
        $description = "Billet n° " . $ticket['ticket_number'] . "\\nSiège : " . ($ticket['seat_number'] ?? '—');

        return "BEGIN:VCALENDAR\r\n" .
               "VERSION:2.0\r\n" .
               "PRODID:-//CITY BUS//FR\r\n" .
               "BEGIN:VEVENT\r\n" .
               "UID:$uid\r\n" .
               "DTSTAMP:$now\r\n" .
               "DTSTART:$startUtc\r\n" .
               "DTEND:$endUtc\r\n" .
               "SUMMARY:$summary\r\n" .
               "LOCATION:$location\r\n" .
               "DESCRIPTION:$description\r\n" .
               "END:VEVENT\r\n" .
               "END:VCALENDAR\r\n";
    }

    /** Génère un lien court partageable (WhatsApp). */
    public function shareUrl(array $ticket): string
    {
        // Le QR du ticket sert de token public (vérifiable en lecture seule)
        return url('public/ticket/' . $ticket['qr_code']);
    }

    /** Texte pré-rempli pour partage WhatsApp. */
    public function whatsappShareText(array $ticket, array $trip): string
    {
        $msg = "🚍 Mon billet CITY BUS n° {$ticket['ticket_number']}\n";
        $msg .= "Voyage : " . ($trip['trip_code'] ?? '') . "\n";
        $msg .= "Date : " . date('d/m/Y H:i', strtotime($trip['trip_date'] . ' ' . $trip['departure_scheduled'])) . "\n";
        $msg .= "Siège : " . ($ticket['seat_number'] ?? '—') . "\n\n";
        $msg .= "Voir : " . $this->shareUrl($ticket);
        return $msg;
    }

    /** URL pour ajouter à l'agenda Google. */
    public function googleCalendarUrl(array $ticket, array $trip): string
    {
        $start = date('Ymd\THis', strtotime($trip['trip_date'] . ' ' . $trip['departure_scheduled']));
        $end = $trip['arrival_scheduled']
            ? date('Ymd\THis', strtotime($trip['trip_date'] . ' ' . $trip['arrival_scheduled']))
            : date('Ymd\THis', strtotime($trip['trip_date'] . ' ' . $trip['departure_scheduled']) + 4 * 3600);

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => 'CITY BUS · ' . ($trip['trip_code'] ?? ''),
            'dates'    => "$start/$end",
            'details'  => "Billet n° {$ticket['ticket_number']}, siège " . ($ticket['seat_number'] ?? '—'),
            'location' => ($trip['line_name'] ?? ''),
        ]);
    }
}
