<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Logger;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

/**
 * Service métier du module Cargo (colis / fret).
 *
 * Centralise :
 *  - tarification (résolution du barème selon poids/type)
 *  - dépôt (numérotation, calcul prix, audit, événement timeline, SMS)
 *  - chargement / arrivée / retrait
 *  - manifeste cargo d'un voyage
 */
final class CargoService
{
    /**
     * Calcule le prix d'un colis selon son tarif, son poids et sa valeur déclarée.
     *
     * @return array{base:int, insurance:int, tax:int, total:int, tariff_id:int|null}
     */
    public function quote(string $type, float $weightKg, int $declaredValueFcfa = 0, ?int $tariffId = null): array
    {
        $tariff = $this->resolveTariff($type, $weightKg, $tariffId);
        if (!$tariff) {
            return ['base' => 0, 'insurance' => 0, 'tax' => 0, 'total' => 0, 'tariff_id' => null];
        }

        // Prix = max(montant minimum, poids × tarif/kg)
        $perKg     = (int)round($weightKg * (int)$tariff['price_per_kg']);
        $minPrice  = (int)($tariff['min_price_fcfa'] ?? 0);
        $base      = max($minPrice, $perKg);

        $taxRatePct = (float)Setting::getString('cargo.tax_rate_percent', '0');
        $tax        = (int)round($base * $taxRatePct / 100);
        $total      = $base + $tax;

        return [
            'base'      => $base,
            'insurance' => 0,
            'tax'       => $tax,
            'total'     => $total,
            'tariff_id' => (int)$tariff['id'],
        ];
    }

    /**
     * Crée un colis (statut "depose") et journalise l'événement initial.
     * Retourne l'ID inséré.
     */
    public function deposit(array $data, int $userId): int
    {
        $quote = $this->quote(
            $data['parcel_type'],
            (float)$data['weight_kg'],
            (int)($data['declared_value_fcfa'] ?? 0),
            !empty($data['parcel_tariff_id']) ? (int)$data['parcel_tariff_id'] : null
        );

        $parcelNumber = $this->generateNumber();
        $qrToken      = bin2hex(random_bytes(16));

        $id = (int)Database::insert(
            "INSERT INTO parcels (
                parcel_number, qr_token,
                origin_agency_id, destination_agency_id,
                sender_name, sender_phone, sender_id_doc, sender_address,
                recipient_name, recipient_phone, recipient_id_doc, recipient_address,
                parcel_type, description, weight_kg, volume_m3, declared_value_fcfa, pieces_count,
                parcel_tariff_id, base_price_fcfa, insurance_fee_fcfa, tax_amount_fcfa, total_price_fcfa,
                payment_method, paid_at_origin,
                status, deposited_at, deposited_by, cash_register_id, notes
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?)",
            [
                $parcelNumber, $qrToken,
                (int)$data['origin_agency_id'], (int)$data['destination_agency_id'],
                $data['sender_name'], $data['sender_phone'],
                $data['sender_id_doc']  ?? null, $data['sender_address']  ?? null,
                $data['recipient_name'], $data['recipient_phone'],
                $data['recipient_id_doc'] ?? null, $data['recipient_address'] ?? null,
                $data['parcel_type'], $data['description'],
                (float)$data['weight_kg'],
                !empty($data['volume_m3']) ? (float)$data['volume_m3'] : null,
                (int)($data['declared_value_fcfa'] ?? 0),
                (int)($data['pieces_count'] ?? 1),
                $quote['tariff_id'],
                $quote['base'], $quote['insurance'], $quote['tax'], $quote['total'],
                $data['payment_method'] ?? 'especes',
                ($data['payment_method'] ?? '') === 'a_destination' ? 0 : 1,
                'depose',
                $userId,
                !empty($data['cash_register_id']) ? (int)$data['cash_register_id'] : null,
                $data['notes'] ?? null,
            ]
        );

        $this->logEvent($id, 'depose', "Dépôt initial · n° $parcelNumber", $data['sender_address'] ?? null, $userId);
        AuditLog::record('parcel.create', 'parcel', $id, [
            'number' => $parcelNumber,
            'amount' => $quote['total'],
            'origin' => (int)$data['origin_agency_id'],
            'dest'   => (int)$data['destination_agency_id'],
        ]);

        // SMS au dépôt si activé
        if (Setting::getBool('cargo.notify_recipient_at_deposit', true)) {
            $this->notifyDeposit($id);
        }

        WebhookService::dispatch('parcel.deposited', ['id' => $id, 'number' => $parcelNumber]);

        // Écriture comptable (GAP-23)
        try {
            $parcel = Database::selectOne("SELECT * FROM parcels WHERE id=?", [$id]);
            if ($parcel) (new AccountingService())->recordParcelSale($parcel);
        } catch (\Throwable $e) {
            Logger::warning('accounting.parcel_failed: ' . $e->getMessage());
        }

        return $id;
    }

    /**
     * Affecte un colis à un voyage (chargement). Le statut passe à "en_transit".
     */
    public function loadOnTrip(int $parcelId, int $tripId, int $userId): void
    {
        Database::execute(
            "UPDATE parcels SET trip_id=?, status='en_transit' WHERE id=? AND status='depose'",
            [$tripId, $parcelId]
        );
        $this->logEvent($parcelId, 'charge', "Chargé sur le voyage #$tripId", null, $userId);
        AuditLog::record('parcel.load', 'parcel', $parcelId, ['trip_id' => $tripId]);
        WebhookService::dispatch('parcel.in_transit', ['id' => $parcelId, 'trip_id' => $tripId]);
    }

    /**
     * Marque un colis comme arrivé à destination + SMS au destinataire.
     */
    public function markArrived(int $parcelId, int $userId, ?string $location = null): void
    {
        Database::execute(
            "UPDATE parcels SET status='arrive' WHERE id=?", [$parcelId]
        );
        $this->logEvent($parcelId, 'arrivee_destination', 'Colis arrivé à destination', $location, $userId);
        AuditLog::record('parcel.arrive', 'parcel', $parcelId);

        if (Setting::getBool('cargo.notify_recipient_sms', true)) {
            $this->notifyArrival($parcelId);
        }
        WebhookService::dispatch('parcel.arrived', ['id' => $parcelId]);
    }

    /**
     * Marque un colis comme retiré.
     */
    public function pickup(int $parcelId, array $data, int $userId): void
    {
        Database::execute(
            "UPDATE parcels
                SET status='retire', picked_up_at=NOW(), picked_up_by=?,
                    pickup_recipient_name=?, pickup_id_doc=?, pickup_signature_path=?, pickup_notes=?
              WHERE id=?",
            [
                $userId,
                $data['pickup_recipient_name'] ?? null,
                $data['pickup_id_doc'] ?? null,
                $data['pickup_signature_path'] ?? null,
                $data['pickup_notes'] ?? null,
                $parcelId,
            ]
        );
        $this->logEvent($parcelId, 'retrait', 'Colis retiré : ' . ($data['pickup_recipient_name'] ?? 'destinataire'), null, $userId);
        AuditLog::record('parcel.pickup', 'parcel', $parcelId, [
            'by' => $data['pickup_recipient_name'] ?? null,
        ]);
        WebhookService::dispatch('parcel.picked_up', ['id' => $parcelId]);
    }

    public function cancel(int $parcelId, string $reason, int $userId): void
    {
        Database::execute(
            "UPDATE parcels
                SET deleted_at=NOW(), cancelled_at=NOW(),
                    cancelled_by=?, cancel_reason=?
              WHERE id=?",
            [$userId, $reason, $parcelId]
        );
        $this->logEvent($parcelId, 'annule', "Annulation : $reason", null, $userId);
        AuditLog::record('parcel.cancel', 'parcel', $parcelId, ['reason' => $reason]);
    }

    public function reportIssue(int $parcelId, string $type, string $description, int $userId): void
    {
        if (!in_array($type, ['perdu', 'endommage'], true)) return;
        Database::execute("UPDATE parcels SET status=? WHERE id=?", [$type, $parcelId]);
        $this->logEvent($parcelId, 'litige', "$type · $description", null, $userId);
        AuditLog::record('parcel.issue', 'parcel', $parcelId, ['type' => $type]);
        WebhookService::dispatch('parcel.issue', ['id' => $parcelId, 'type' => $type]);
    }

    /**
     * Récupère la timeline d'un colis pour la fiche détail.
     */
    public function timeline(int $parcelId): array
    {
        return Database::select(
            "SELECT pte.*, CONCAT(u.first_name,' ',u.last_name) AS actor_name
               FROM parcel_tracking_events pte
               LEFT JOIN users u ON u.id = pte.actor_id
              WHERE pte.parcel_id = ?
              ORDER BY pte.occurred_at ASC, pte.id ASC",
            [$parcelId]
        );
    }

    /**
     * Manifeste cargo d'un voyage : tous les colis affectés.
     */
    public function manifestForTrip(int $tripId): array
    {
        return Database::select(
            "SELECT p.*,
                    ao.name AS origin_agency,
                    ad.name AS destination_agency
               FROM parcels p
               JOIN agencies ao ON ao.id = p.origin_agency_id
               JOIN agencies ad ON ad.id = p.destination_agency_id
              WHERE p.trip_id = ? AND p.deleted_at IS NULL
              ORDER BY p.deposited_at ASC",
            [$tripId]
        );
    }

    /**
     * Trouve un colis par son numéro ou son token QR.
     */
    public function findByCodeOrToken(string $code): ?array
    {
        return Database::selectOne(
            "SELECT * FROM parcels WHERE (parcel_number = ? OR qr_token = ?) AND deleted_at IS NULL",
            [$code, $code]
        );
    }

    // ─── Privé ────────────────────────────────────────────────────────────

    private function resolveTariff(string $type, float $weightKg, ?int $forceId = null): ?array
    {
        // Résolution depuis fret_categories (source unique)
        return Database::selectOne(
            "SELECT id, slug AS category, label, price_per_kg, min_price_fcfa
             FROM fret_categories
             WHERE is_active = 1 AND slug = ?
             LIMIT 1",
            [$type]
        );
    }

    private function generateNumber(): string
    {
        $format = Setting::getString('cargo.numbering_format', 'PCL-{YYYYMM}-{seq:06d}');

        // Compteur séquentiel basé sur le mois courant
        $prefix = preg_replace_callback('/\{YYYY\}|\{YYYYMM\}/', fn($m) => match ($m[0]) {
            '{YYYY}'   => date('Y'),
            '{YYYYMM}' => date('Ym'),
        }, $format);

        // Compte les colis créés ce mois (incl. soft-deleted) pour numérotation continue
        $count = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM parcels WHERE DATE_FORMAT(created_at, '%Y%m') = ?",
            [date('Ym')]
        )['c'] ?? 0);
        $seq = $count + 1;

        // Itère pour éviter la collision si plusieurs ventes simultanées
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $num = preg_replace_callback('/\{seq:0(\d+)d\}/', fn($m) => str_pad((string)$seq, (int)$m[1], '0', STR_PAD_LEFT), $prefix);
            $exists = Database::selectOne("SELECT id FROM parcels WHERE parcel_number = ?", [$num]);
            if (!$exists) return $num;
            $seq++;
        }
        return $prefix . '-' . bin2hex(random_bytes(3));
    }

    private function logEvent(int $parcelId, string $type, ?string $description, ?string $location, ?int $actorId): void
    {
        Database::execute(
            "INSERT INTO parcel_tracking_events
                (parcel_id, event_type, description, location, actor_id, occurred_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$parcelId, $type, $description, $location, $actorId]
        );
    }

    private function notifyDeposit(int $parcelId): void
    {
        $p = Database::selectOne(
            "SELECT p.*, ao.name AS origin_name, ad.name AS dest_name
             FROM parcels p
             JOIN agencies ao ON ao.id = p.origin_agency_id
             JOIN agencies ad ON ad.id = p.destination_agency_id
             WHERE p.id = ?", [$parcelId]
        );
        if (!$p) return;
        $msg = sprintf(
            "CITY BUS · Colis %s deposé à %s pour %s. Suivi : %s",
            $p['parcel_number'],
            $p['origin_name'],
            $p['recipient_name'],
            $p['parcel_number']
        );
        try {
            SmsService::send((string)$p['recipient_phone'], $msg);
        } catch (\Throwable $e) {
            Logger::warning('cargo.sms_deposit_failed: ' . $e->getMessage());
        }
    }

    private function notifyArrival(int $parcelId): void
    {
        $p = Database::selectOne(
            "SELECT p.*, ad.name AS dest_name, ad.address AS dest_addr, ad.phone AS dest_phone
             FROM parcels p
             JOIN agencies ad ON ad.id = p.destination_agency_id
             WHERE p.id = ?", [$parcelId]
        );
        if (!$p) return;
        $msg = sprintf(
            "CITY BUS · Votre colis %s est arrivé à %s. Présentez-vous avec une pièce d'identité. Tel : %s",
            $p['parcel_number'],
            $p['dest_name'],
            $p['dest_phone'] ?? ''
        );
        try {
            SmsService::send((string)$p['recipient_phone'], $msg);
        } catch (\Throwable $e) {
            Logger::warning('cargo.sms_arrival_failed: ' . $e->getMessage());
        }
    }
}
