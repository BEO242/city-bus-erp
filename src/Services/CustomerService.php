<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * CRM passagers : création/déduplication automatique du dossier client à
 * partir du téléphone passager normalisé.
 */
final class CustomerService
{
    /**
     * Trouve ou crée un client à partir des infos passager d'un ticket.
     * Met à jour les compteurs (voyages, dépenses).
     */
    public function findOrCreateFromTicket(array $passengerData): ?int
    {
        if (!Setting::getBool('crm.enabled', true)) return null;
        $phone = $this->normalizePhone($passengerData['phone'] ?? $passengerData['passenger_phone'] ?? '');
        if (!$phone) return null;

        $existing = Database::selectOne(
            "SELECT id FROM customers WHERE phone_norm = ? AND deleted_at IS NULL",
            [$phone]
        );
        if ($existing) {
            $this->updateProfile((int)$existing['id'], $passengerData);
            return (int)$existing['id'];
        }

        return (int)Database::insert(
            "INSERT INTO customers
                (phone_norm, phone_display, first_name, last_name, email, id_doc_number, created_at)
             VALUES (?,?,?,?,?,?,NOW())",
            [
                $phone,
                $passengerData['phone'] ?? $passengerData['passenger_phone'] ?? null,
                $passengerData['first_name']   ?? $this->extractFirstName($passengerData['name'] ?? $passengerData['passenger_name'] ?? ''),
                $passengerData['last_name']    ?? $this->extractLastName($passengerData['name']  ?? $passengerData['passenger_name'] ?? ''),
                $passengerData['email']        ?? null,
                $passengerData['id_doc']       ?? null,
            ]
        );
    }

    /** Met à jour les compteurs après une vente. */
    public function bumpStats(int $customerId, int $amountSpent, string $type = 'trip'): void
    {
        $col = match ($type) {
            'baggage' => 'total_baggage',
            'parcel'  => 'total_parcels',
            default   => 'total_trips',
        };
        Database::execute(
            "UPDATE customers
                SET $col = $col + 1,
                    total_spent = total_spent + ?,
                    last_trip_at = NOW(),
                    first_trip_at = COALESCE(first_trip_at, NOW())
              WHERE id = ?",
            [$amountSpent, $customerId]
        );
    }

    public function profile(int $customerId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL", [$customerId]
        );
    }

    public function ticketHistory(int $customerId, int $limit = 50): array
    {
        return Database::select(
            "SELECT t.id, t.ticket_number, t.passenger_name, t.seat_number, t.price_fcfa,
                    t.status, t.sold_at,
                    tr.trip_code, tr.trip_date,
                    l.code AS line_code, l.name AS line_name
             FROM tickets t
             JOIN trips tr ON tr.id = t.trip_id
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             WHERE t.customer_id = ? AND t.deleted_at IS NULL
             ORDER BY t.sold_at DESC LIMIT $limit",
            [$customerId]
        );
    }

    public function search(string $q, int $limit = 20): array
    {
        $like = '%' . $q . '%';
        $phoneNorm = $this->normalizePhone($q);
        return Database::select(
            "SELECT id, phone_display, phone_norm, first_name, last_name, email,
                    total_trips, total_spent, last_trip_at
             FROM customers
             WHERE deleted_at IS NULL
               AND (phone_norm = ? OR phone_norm LIKE ? OR
                    first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
             ORDER BY last_trip_at DESC LIMIT $limit",
            [$phoneNorm, $phoneNorm . '%', $like, $like, $like]
        );
    }

    public function topCustomers(int $limit = 50): array
    {
        return Database::select(
            "SELECT id, first_name, last_name, phone_display,
                    total_trips, total_spent, last_trip_at
             FROM customers
             WHERE deleted_at IS NULL
             ORDER BY total_spent DESC LIMIT $limit"
        );
    }

    public function paginate(int $page, int $perPage, ?string $q = null): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];
        if ($q) {
            $like = "%$q%";
            $phoneNorm = $this->normalizePhone($q);
            $where[] = '(phone_norm LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
            array_push($params, $phoneNorm . '%', $like, $like, $like);
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $total = (int)(Database::selectOne("SELECT COUNT(*) AS c FROM customers $whereSql", $params)['c'] ?? 0);
        $offset = max(0, ($page - 1) * $perPage);
        $items = Database::select(
            "SELECT * FROM customers $whereSql ORDER BY last_trip_at DESC, id DESC LIMIT $perPage OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
                'pages' => max(1, (int)ceil($total / $perPage))];
    }

    // ─── Programme de fidélité ────────────────────────────────────

    /**
     * Générer un code client unique (6 caractères alphanumérique).
     */
    public function generateCustomerCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = Database::selectOne(
                "SELECT id FROM customers WHERE customer_code = ?",
                [$code]
            );
        } while ($exists);
        return $code;
    }

    /**
     * Inscrire un client au programme de fidélité.
     */
    public function enrollLoyalty(int $customerId, int $enrolledBy): bool
    {
        $customer = $this->profile($customerId);
        if (!$customer) return false;

        if ($customer['is_loyalty_member']) {
            return true; // Déjà inscrit
        }

        // Vérifier que le programme est activé
        $config = Database::selectOne("SELECT * FROM loyalty_program_config WHERE id = 1");
        if (!$config || !(int)$config['is_enabled']) {
            return false;
        }

        // Générer le code client s'il n'en a pas
        $code = $customer['customer_code'] ?: $this->generateCustomerCode();

        Database::execute(
            "UPDATE customers SET customer_code = ?, is_loyalty_member = 1,
                    loyalty_enrolled_at = NOW(), loyalty_enrolled_by = ?
             WHERE id = ?",
            [$code, $enrolledBy, $customerId]
        );

        return true;
    }

    /**
     * Vérifier si un client est éligible à une réduction fidélité.
     * Retourne le pourcentage de réduction, 0 si pas éligible.
     */
    public function loyaltyDiscount(int $customerId): float
    {
        $config = Database::selectOne("SELECT * FROM loyalty_program_config WHERE id = 1");
        if (!$config || !(int)$config['is_enabled']) return 0;

        $customer = $this->profile($customerId);
        if (!$customer || !$customer['is_loyalty_member']) return 0;

        $requiredTrips = (int)$config['required_trips'];
        if ($requiredTrips <= 0) return 0;

        // Compter les voyages dans la période de validité
        $periodMonths = (int)$config['period_months'];
        $where = "customer_id = ? AND status != 'annule' AND deleted_at IS NULL";
        $params = [$customerId];

        if ($periodMonths > 0) {
            $where .= " AND sold_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)";
            $params[] = $periodMonths;
        }

        $count = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM tickets WHERE {$where}",
            $params
        )['c'] ?? 0);

        // Éligible si count est un multiple du nombre requis (réduction périodique)
        if ($count > 0 && $count % $requiredTrips === 0) {
            return (float)$config['discount_percent'];
        }

        return 0;
    }

    /**
     * Configuration du programme de fidélité.
     */
    public function loyaltyConfig(): ?array
    {
        return Database::selectOne("SELECT * FROM loyalty_program_config WHERE id = 1");
    }

    /** Backfill : associe les tickets existants à un customer (job de migration). */
    public function backfillTickets(int $batchSize = 500): int
    {
        $rows = Database::select(
            "SELECT id, passenger_name, passenger_phone
             FROM tickets WHERE customer_id IS NULL AND passenger_phone IS NOT NULL
             LIMIT $batchSize"
        );
        $count = 0;
        foreach ($rows as $r) {
            $cid = $this->findOrCreateFromTicket([
                'name'  => $r['passenger_name'] ?? '',
                'phone' => $r['passenger_phone'] ?? '',
            ]);
            if ($cid) {
                Database::execute("UPDATE tickets SET customer_id = ? WHERE id = ?", [$cid, (int)$r['id']]);
                $count++;
            }
        }
        return $count;
    }

    // ─── Privé ──────────────────────────────────────────────────────

    private function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        $countryCode = Setting::getString('crm.country_code', '+242');

        // Garde uniquement chiffres et + initial
        $clean = preg_replace('/[^\d+]/', '', $raw);
        if (str_starts_with($clean, '+')) {
            $clean = '+' . preg_replace('/[^\d]/', '', substr($clean, 1));
        } else {
            $digits = preg_replace('/[^\d]/', '', $clean);
            // Si commence par 00 → remplacer par +
            if (str_starts_with($digits, '00')) {
                $clean = '+' . substr($digits, 2);
            } elseif (strlen($digits) <= 10) {
                // Numéro local sans indicatif — préfixe avec country code
                $clean = $countryCode . $digits;
            } else {
                $clean = '+' . $digits;
            }
        }
        return $clean;
    }

    private function extractFirstName(string $full): ?string
    {
        $full = trim($full);
        if ($full === '') return null;
        $parts = preg_split('/\s+/', $full);
        return $parts[0] ?? null;
    }

    private function extractLastName(string $full): ?string
    {
        $full = trim($full);
        if ($full === '') return null;
        $parts = preg_split('/\s+/', $full);
        if (count($parts) <= 1) return null;
        return implode(' ', array_slice($parts, 1));
    }

    private function updateProfile(int $customerId, array $data): void
    {
        // N'écrase pas les champs déjà renseignés
        $update = [];
        $params = [];
        foreach (['first_name','last_name','email'] as $field) {
            $val = $data[$field] ?? null;
            if (!$val && $field === 'first_name') $val = $this->extractFirstName($data['name'] ?? $data['passenger_name'] ?? '');
            if (!$val && $field === 'last_name')  $val = $this->extractLastName($data['name'] ?? $data['passenger_name'] ?? '');
            if ($val) {
                $update[] = "$field = COALESCE($field, ?)";
                $params[] = $val;
            }
        }
        if (!$update) return;
        $params[] = $customerId;
        Database::execute("UPDATE customers SET " . implode(',', $update) . " WHERE id = ?", $params);
    }
}
