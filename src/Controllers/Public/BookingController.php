<?php

declare(strict_types=1);

namespace CityBus\Controllers\Public;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Setting;
use CityBus\Services\PnrService;
use CityBus\Services\OdFareService;
use CityBus\Services\CustomerService;
use CityBus\Services\Payments\PaymentGatewayService;
use CityBus\Services\InvoiceService;
use CityBus\Services\NotificationV4Service;

/**
 * Réservation publique B2C — pas d'auth, mais session pour panier.
 */
final class BookingController extends Controller
{
    public function home(Request $request): void
    {
        $this->view('public/booking/home', [
            'title' => 'City Bus · Réservez votre voyage',
            'cities' => Database::select("SELECT id, name FROM cities ORDER BY name"),
        ]);
    }

    public function search(Request $request): void
    {
        $fromCity = (int)$request->input('from', 0);
        $toCity   = (int)$request->input('to', 0);
        $date     = $request->input('date', date('Y-m-d'));
        $pax      = (int)$request->input('pax', 1);

        $trips = [];
        if ($fromCity && $toCity && $date) {
            $trips = Database::select(
                "SELECT t.*, l.code AS line_code, l.name AS line_name, l.distance_km, l.estimated_duration_minutes,
                        cd.name AS departure_city, ca.name AS arrival_city,
                        b.brand AS bus_brand, b.model AS bus_model
                 FROM trips t
                 JOIN bus_lines l ON l.id = t.line_id
                 JOIN cities cd ON cd.id = l.departure_city_id
                 JOIN cities ca ON ca.id = l.arrival_city_id
                 LEFT JOIN buses b ON b.id = t.bus_id
                 WHERE l.departure_city_id = ? AND l.arrival_city_id = ?
                   AND t.trip_date = ?
                   AND t.status IN ('planifie','planifié','valide','embarquement')
                 ORDER BY t.departure_time",
                [$fromCity, $toCity, $date]
            );

            // Pour chaque voyage, dispo par classe
            foreach ($trips as &$t) {
                $t['inventory'] = Database::select(
                    "SELECT class_code, capacity, sold_count, blocked_count, price_fcfa
                     FROM trip_inventory WHERE trip_id = ?", [$t['id']]
                );
                $t['min_price'] = (int)(min(array_column($t['inventory'], 'price_fcfa')) ?: 0);
            }
        }

        $this->view('public/booking/search', [
            'title'   => 'Résultats',
            'trips'   => $trips,
            'cities'  => Database::select("SELECT id, name FROM cities ORDER BY name"),
            'fromCity'=> $fromCity, 'toCity' => $toCity, 'date' => $date, 'pax' => $pax,
        ]);
    }

    public function tripDetails(Request $request, string $tripId): void
    {
        $trip = Database::selectOne(
            "SELECT t.*, l.code AS line_code, l.name AS line_name,
                    cd.name AS departure_city, ca.name AS arrival_city
             FROM trips t
             JOIN bus_lines l ON l.id = t.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             JOIN cities ca ON ca.id = l.arrival_city_id
             WHERE t.id = ?", [(int)$tripId]
        );
        if (!$trip) { http_response_code(404); $this->view('errors/404'); return; }

        $inventory = Database::select(
            "SELECT ti.*, ic.name AS class_name, ic.color_hex, ic.priority_boarding, ic.refund_policy_pct
             FROM trip_inventory ti
             LEFT JOIN inventory_classes ic ON ic.code = ti.class_code
             WHERE ti.trip_id = ?
             ORDER BY ti.price_fcfa DESC", [(int)$tripId]
        );

        $this->view('public/booking/trip_details', [
            'title' => 'Voyage ' . $trip['trip_code'],
            'trip'  => $trip,
            'inventory' => $inventory,
        ]);
    }

    public function checkout(Request $request, string $tripId): void
    {
        $class = $request->input('class', 'M');
        $pax = max(1, min(9, (int)$request->input('pax', 1)));
        $this->view('public/booking/checkout', [
            'title' => 'Finaliser réservation',
            'trip_id' => (int)$tripId,
            'class' => $class,
            'pax' => $pax,
        ]);
    }

    public function submit(Request $request): void
    {
        $tripId = (int)$request->input('trip_id');
        $class  = (string)$request->input('class', 'M');
        $paxNames = $request->input('pax_names', []);
        $contactName = (string)$request->input('contact_name');
        $contactPhone = (string)$request->input('contact_phone');
        $contactEmail = (string)$request->input('contact_email');
        $paymentMethod = (string)$request->input('payment_method', 'CASH');

        if (empty($contactPhone)) { $this->flash('danger', 'Téléphone requis'); back(); return; }

        $trip = Database::selectOne("SELECT line_id, line_id AS line FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) { $this->flash('danger', 'Voyage introuvable'); back(); return; }

        $line = Database::selectOne("SELECT id, departure_city_id, arrival_city_id FROM bus_lines WHERE id = ?", [$trip['line_id']]);
        $stops = Database::select(
            "SELECT s.id FROM line_stops ls JOIN stops s ON s.id = ls.stop_id WHERE ls.line_id = ? ORDER BY ls.sequence",
            [$line['id']]
        );
        $fromStop = $stops[0]['id'] ?? null;
        $toStop = end($stops)['id'] ?? null;

        try {
            // Customer
            $custSvc = new CustomerService();
            $customer = $custSvc->findOrCreateFromTicket([
                'phone' => $contactPhone, 'name' => $contactName, 'email' => $contactEmail,
            ]);

            $pnrSvc = new PnrService();
            $pnrId = $pnrSvc->createHeader([
                'customer_id'   => $customer,
                'contact_name'  => $contactName,
                'contact_phone' => $contactPhone,
                'contact_email' => $contactEmail,
                'channel'       => 'website',
            ]);

            $totalAmount = 0;
            foreach ($paxNames as $i => $name) {
                $name = trim((string)$name);
                if (empty($name)) continue;
                $parts = explode(' ', $name, 2);
                $paxId = $pnrSvc->addPassenger($pnrId, [
                    'first_name' => $parts[0] ?? '',
                    'last_name'  => $parts[1] ?? '',
                ]);
                $segId = $pnrSvc->addSegment($pnrId, $paxId, [
                    'trip_id'           => $tripId,
                    'boarding_stop_id'  => $fromStop,
                    'alighting_stop_id' => $toStop,
                    'booking_class'     => $class,
                    'pax_type'          => 'ADT',
                    'passenger_name'    => $name,
                    'passenger_phone'   => $contactPhone,
                ]);
                $price = Database::selectOne("SELECT price_fcfa FROM reservation_items WHERE id = ?", [$segId]);
                $totalAmount += (int)$price['price_fcfa'];
            }

            if ($totalAmount === 0) { $this->flash('danger', 'Aucun passager renseigné'); back(); return; }

            $pnrSvc->confirm($pnrId);

            // Crée facture
            $invoiceId = (new InvoiceService())->create([
                'type' => 'sale',
                'customer_id' => $customer,
                'pnr_id' => $pnrId,
                'issued_at' => date('Y-m-d H:i:s'),
            ], [[
                'line_type' => 'ticket',
                'description' => "Voyage $tripId · classe $class · " . count($paxNames) . " pax",
                'quantity' => count($paxNames),
                'unit_price' => (int)round($totalAmount / count($paxNames)),
                'is_ttc' => true,
            ]]);

            // Paiement
            $gw = new PaymentGatewayService();
            $r = $gw->initiate($paymentMethod, $totalAmount, $contactPhone, "PNR-" . $pnrId);
            $gw->recordPayment($invoiceId, $paymentMethod, $totalAmount, $r['provider_tx_id'] ?? null, $r['status']);

            if ($r['status'] === 'confirmed') {
                $pnr = Database::selectOne("SELECT pnr_code FROM reservations WHERE id = ?", [$pnrId]);
                $pnrSvc->ticket($pnrId, 0);

                // Notif
                (new NotificationV4Service())->queueByCode('BOOKING_CONFIRMED_SMS', [
                    'phone' => $contactPhone, 'name' => $contactName,
                    'pnr' => $pnr['pnr_code'], 'trip_code' => "T-$tripId",
                    'date' => '', 'time' => '',
                    'url' => url('public/ticket/' . $pnr['pnr_code']),
                ]);
                $this->redirect('public/booking/success?pnr=' . $pnr['pnr_code']);
            } else {
                $this->flash('warning', 'Paiement en attente. Vérifiez votre téléphone (' . $r['message'] . ')');
                $this->redirect('public/booking/pending?pnr_id=' . $pnrId);
            }
        } catch (\Throwable $e) {
            $this->flash('danger', 'Erreur : ' . $e->getMessage());
            back();
        }
    }

    public function success(Request $request): void
    {
        $code = $request->input('pnr');
        $pnr = (new PnrService())->findByCode($code);
        $this->view('public/booking/success', [
            'title' => 'Réservation confirmée',
            'pnr' => $pnr,
        ]);
    }

    public function pending(Request $request): void
    {
        $this->view('public/booking/pending', [
            'title' => 'Paiement en attente',
            'pnr_id' => (int)$request->input('pnr_id'),
        ]);
    }

    public function lookupPnr(Request $request): void
    {
        $code = trim((string)$request->input('pnr', ''));
        if (empty($code)) { $this->view('public/booking/lookup', ['title' => 'Recherche PNR']); return; }
        $pnr = (new PnrService())->findByCode($code);
        $this->view('public/booking/lookup', [
            'title' => 'Recherche PNR',
            'pnr'   => $pnr,
            'code'  => $code,
        ]);
    }
}
