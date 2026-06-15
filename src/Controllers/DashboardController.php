<?php

declare(strict_types=1);

namespace CityBus\Controllers;

use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Setting;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $today = date('Y-m-d');
        $month = date('Y-m');

        $kpis = [
            'ca_jour'        => (int)(Database::selectOne(
                "SELECT COALESCE(SUM(amount_fcfa),0) AS s FROM sales WHERE DATE(created_at)=?", [$today]
            )['s'] ?? 0),
            'ca_mois'        => (int)(Database::selectOne(
                "SELECT COALESCE(SUM(amount_fcfa),0) AS s FROM sales WHERE DATE_FORMAT(created_at,'%Y-%m')=?", [$month]
            )['s'] ?? 0),
            'tickets_jour'   => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM tickets WHERE DATE(sold_at)=? AND status!='annule'", [$today]
            )['c'] ?? 0),
            'voyages_jour'   => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM trips WHERE trip_date=?", [$today]
            )['c'] ?? 0),
            'voyages_route'  => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM trips WHERE status IN ('embarquement','en_route')"
            )['c'] ?? 0),
            'bus_dispo'      => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM buses WHERE status='disponible'"
            )['c'] ?? 0),
            'bus_total'      => (int)(Database::selectOne("SELECT COUNT(*) AS c FROM buses")['c'] ?? 0),
            'employees'      => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM employees WHERE status='actif' AND deleted_at IS NULL"
            )['c'] ?? 0),
        ];

        $recentSales = Database::select(
            "SELECT t.ticket_number, t.passenger_name, t.price_fcfa, t.sold_at, l.name AS line_name
             FROM tickets t
             JOIN trips tr ON tr.id = t.trip_id
             JOIN bus_lines l ON l.id = tr.line_id
             WHERE t.status != 'annule' AND t.deleted_at IS NULL
             ORDER BY t.sold_at DESC LIMIT 8"
        );

        // Évolution CA 7 derniers jours
        $chart = Database::select(
            "SELECT DATE(created_at) AS d, COALESCE(SUM(amount_fcfa),0) AS total
             FROM sales
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(created_at)
             ORDER BY d ASC"
        );

        // Recettes par agence (mois)
        $byAgency = Database::select(
            "SELECT a.name, ct.slug AS city, ct.name AS city_name, COALESCE(SUM(s.amount_fcfa),0) AS total
             FROM agencies a
             LEFT JOIN cities ct ON ct.id = a.city_id
             LEFT JOIN sales s
               ON s.cash_register_id IN (SELECT id FROM cash_registers WHERE agency_id=a.id)
              AND DATE_FORMAT(s.created_at,'%Y-%m')=?
             WHERE a.type IN ('principale','point_vente')
             GROUP BY a.id ORDER BY total DESC", [$month]
        );

        // Voyages actifs du jour (pour slide 3)
        $activeTrips = Database::select(
            "SELECT t.id, t.departure_scheduled AS departure_time, t.status,
                    l.name AS line_name,
                    b.code AS bus_code,
                    CONCAT(d.first_name,' ',d.last_name) AS driver_name,
                    COALESCE(b.seats, 47) AS capacity,
                    (SELECT COUNT(*) FROM tickets tk WHERE tk.trip_id=t.id AND tk.status!='annule') AS pax_count
             FROM trips t
             JOIN bus_lines l ON l.id = t.line_id
             LEFT JOIN buses b ON b.id = t.bus_id
             LEFT JOIN drivers d ON d.id = t.driver_id
             WHERE t.trip_date = ? AND t.status IN ('planifie','embarquement','en_route')
             ORDER BY t.departure_scheduled ASC LIMIT 6",
            [$today]
        );

        $dailyTarget = Setting::getInt('caisse.daily_target', 2500000);

        $alerts = [];
        $expiringInsurance = Database::select(
            "SELECT code, plate, insurance_expiry FROM buses
             WHERE insurance_expiry IS NOT NULL
               AND insurance_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY insurance_expiry ASC LIMIT 5"
        );
        foreach ($expiringInsurance as $b) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'shield-alert',
                'message' => "Assurance bus {$b['code']} expire le " . date('d/m/Y', strtotime($b['insurance_expiry'])),
            ];
        }

        // Alertes contrôle technique expirant (délai paramétrable)
        $techAlertDays = max(1, Setting::getInt('flotte.tech_control_alert_days', 30));
        $expiringTech = Database::select(
            "SELECT code, plate, tech_control_expiry FROM buses
             WHERE tech_control_expiry IS NOT NULL
               AND tech_control_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY tech_control_expiry ASC LIMIT 5",
            [$techAlertDays]
        );
        foreach ($expiringTech as $b) {
            $alerts[] = [
                'type'    => 'warning',
                'icon'    => 'wrench',
                'message' => "Contrôle technique bus {$b['code']} expire le " . date('d/m/Y', strtotime($b['tech_control_expiry'])),
            ];
        }

        // Alertes permis de conduire expirant (délai paramétrable)
        $licenseAlertDays = max(1, Setting::getInt('rh.license_alert_days', 45));
        $expiringLicenses = Database::select(
            "SELECT CONCAT(first_name,' ',last_name) AS name, license_expiry FROM drivers
             WHERE license_expiry IS NOT NULL
               AND license_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND deleted_at IS NULL
             ORDER BY license_expiry ASC LIMIT 5",
            [$licenseAlertDays]
        );
        foreach ($expiringLicenses as $d) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'id-card',
                'message' => "Permis de {$d['name']} expire le " . date('d/m/Y', strtotime($d['license_expiry'])),
            ];
        }

        // Alertes visite médicale expirant (délai paramétrable)
        $medicalAlertDays = max(1, Setting::getInt('rh.medical_cert_alert_days', 30));
        $expiringMedical = Database::select(
            "SELECT CONCAT(first_name,' ',last_name) AS name, medical_cert_expiry FROM drivers
             WHERE medical_cert_expiry IS NOT NULL
               AND medical_cert_expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND deleted_at IS NULL
             ORDER BY medical_cert_expiry ASC LIMIT 5",
            [$medicalAlertDays]
        );
        foreach ($expiringMedical as $d) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'heart-pulse',
                'message' => "Visite médicale de {$d['name']} expire le " . date('d/m/Y', strtotime($d['medical_cert_expiry'])),
            ];
        }

        // ── V4 platform widgets ──
        $v4 = [
            'gps_alerts_open'  => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM gps_alerts WHERE acknowledged_at IS NULL"
            )['c'] ?? 0),
            'pnr_today'        => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM reservations WHERE DATE(created_at)=? AND status NOT IN ('cancelled','annule')", [$today]
            )['c'] ?? 0),
            'cargo_transit'    => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM parcels WHERE status = 'en_transit'"
            )['c'] ?? 0),
            'notif_sent_today' => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM notification_dispatches WHERE DATE(created_at)=? AND status='sent'", [$today]
            )['c'] ?? 0),
            'invoices_pending' => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM invoices WHERE status IN ('issued','overdue')"
            )['c'] ?? 0),
            'complaints_open'  => (int)(Database::selectOne(
                "SELECT COUNT(*) AS c FROM customer_complaints WHERE status IN ('open','investigating','escalated')"
            )['c'] ?? 0),
        ];

        // Recent PNR bookings
        $recentPnr = Database::select(
            "SELECT r.pnr_code, r.status, r.created_at, r.contact_name, r.contact_phone,
                    COUNT(ri.id) AS pax_count,
                    COALESCE(SUM(ri.price_fcfa),0) AS total_ttc
             FROM reservations r
             LEFT JOIN reservation_items ri ON ri.reservation_id = r.id
             WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
             GROUP BY r.id
             ORDER BY r.created_at DESC LIMIT 6"
        );

        $this->view('dashboard', [
            'title'        => 'Tableau de bord',
            'kpis'         => $kpis,
            'recentSales'  => $recentSales,
            'chart'        => $chart,
            'byAgency'     => $byAgency,
            'alerts'       => $alerts,
            'activeTrips'  => $activeTrips,
            'dailyTarget'  => $dailyTarget,
            'v4'           => $v4,
            'recentPnr'    => $recentPnr,
        ]);
    }
}
