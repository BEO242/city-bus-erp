<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Auth;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

final class CargoV4Service
{
    public function recordEvent(int $parcelId, string $eventType, array $opts = []): int
    {
        $allowed = ['registered','picked_up','loaded','in_transit','arrived','transferred','out_for_delivery','delivered','returned','lost','damaged','held','customs'];
        if (!in_array($eventType, $allowed, true)) {
            throw new \InvalidArgumentException("Event type invalide");
        }

        $id = Database::insert('parcel_events', [
            'parcel_id'   => $parcelId,
            'event_type'  => $eventType,
            'location'    => $opts['location'] ?? null,
            'trip_id'     => $opts['trip_id'] ?? null,
            'actor_id'    => $opts['actor_id'] ?? Auth::id(),
            'notes'       => $opts['notes'] ?? null,
            'proof_photo' => $opts['proof_photo'] ?? null,
        ]);

        // Met à jour status parcel selon mapping
        $newStatus = match($eventType) {
            'loaded','in_transit'    => 'en_transit',
            'arrived','transferred'  => 'arrive',
            'delivered'              => 'retire',
            'returned'               => 'retourne',
            'lost'                   => 'perdu',
            'damaged'                => 'endommage',
            default                  => null,
        };
        if ($newStatus) {
            Database::update('parcels', ['status' => $newStatus], 'id = ?', [$parcelId]);
        }

        // Notif auto si activé
        $this->notifyEvent($parcelId, $eventType);
        AuditLog::record('parcel.event', 'parcel', $parcelId, ['event' => $eventType]);
        return $id;
    }

    public function recordPod(int $parcelId, array $podData): void
    {
        Database::update('parcels', [
            'status'              => 'retire',
            'picked_up_at'        => date('Y-m-d H:i:s'),
            'picked_up_by'        => Auth::id(),
            'pod_recipient_name'  => $podData['recipient_name'] ?? null,
            'pod_recipient_id_doc'=> $podData['id_doc'] ?? null,
            'pod_signature_data'  => $podData['signature'] ?? null,
            'pod_photo_path'      => $podData['photo'] ?? null,
        ], 'id = ?', [$parcelId]);
        $this->recordEvent($parcelId, 'delivered', ['notes' => 'POD enregistré']);
    }

    public function collectCod(int $parcelId, int $amount): void
    {
        $p = Database::selectOne("SELECT cod_amount_fcfa, cod_collected_at FROM parcels WHERE id = ?", [$parcelId]);
        if (!$p) throw new \RuntimeException("Colis introuvable");
        if ($p['cod_collected_at']) throw new \RuntimeException("COD déjà collecté");
        if ($amount < (int)$p['cod_amount_fcfa']) throw new \RuntimeException("Montant insuffisant");

        Database::update('parcels', [
            'cod_collected_at' => date('Y-m-d H:i:s'),
            'cod_collected_by' => Auth::id(),
        ], 'id = ?', [$parcelId]);
        AuditLog::record('parcel.cod_collected', 'parcel', $parcelId, ['amount' => $amount]);
    }

    public function planRoute(int $parcelId, array $tripIds): int
    {
        Database::execute("DELETE FROM parcel_routes WHERE parcel_id = ?", [$parcelId]);
        foreach ($tripIds as $i => $tid) {
            Database::insert('parcel_routes', [
                'parcel_id' => $parcelId,
                'sequence'  => $i + 1,
                'trip_id'   => (int)$tid,
                'status'    => 'planned',
            ]);
        }
        Database::update('parcels',
            ['routed_via_segments' => json_encode($tripIds)],
            'id = ?', [$parcelId]
        );
        return count($tripIds);
    }

    public function trackByNumber(string $tracking): ?array
    {
        $p = Database::selectOne(
            "SELECT id, parcel_number, sender_name, recipient_name, sender_phone, recipient_phone,
                    weight_kg, status, deposited_at, picked_up_at, total_price_fcfa,
                    origin_agency_id, destination_agency_id
             FROM parcels WHERE parcel_number = ? OR qr_token = ?",
            [$tracking, $tracking]
        );
        if (!$p) return null;
        $p['events'] = Database::select(
            "SELECT * FROM parcel_events WHERE parcel_id = ? ORDER BY occurred_at ASC", [$p['id']]
        );
        return $p;
    }

    public function generateQrLabel(int $parcelId): string
    {
        $p = Database::selectOne("SELECT * FROM parcels WHERE id = ?", [$parcelId]);
        if (!$p) throw new \RuntimeException("Colis introuvable");

        // Génère URL QR Google Charts (en prod : utiliser endroid/qr-code)
        $url = url('public/cargo/track/' . $p['qr_token']);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($url);

        $html = "<!doctype html><html><head><meta charset='utf-8'><title>Étiquette {$p['parcel_number']}</title>
        <style>
          @page { size: 100mm 150mm; margin: 4mm; }
          body { font-family: sans-serif; font-size: 10pt; }
          .label { border: 2px solid #000; padding: 4mm; }
          .pn { font-size: 14pt; font-weight: bold; text-align: center; }
          .qr { text-align: center; margin: 4mm 0; }
          .info { display: flex; justify-content: space-between; }
          .from, .to { width: 48%; }
          .from h3, .to h3 { font-size: 8pt; text-transform: uppercase; margin: 0 0 1mm; color: #666; }
          .price { text-align: right; font-weight: bold; font-size: 12pt; margin-top: 3mm; }
        </style></head><body>
        <div class='label'>
          <div class='pn'>{$p['parcel_number']}</div>
          <div class='qr'><img src='$qrUrl' style='width:200px;height:200px;'></div>
          <div class='info'>
            <div class='from'><h3>Expéditeur</h3>{$p['sender_name']}<br>{$p['sender_phone']}</div>
            <div class='to'><h3>Destinataire</h3>{$p['recipient_name']}<br>{$p['recipient_phone']}</div>
          </div>
          <div style='margin-top:3mm; font-size:9pt;'>{$p['description']}<br>Poids : {$p['weight_kg']} kg</div>
          <div class='price'>" . number_format((int)$p['total_price_fcfa']) . " FCFA</div>
        </div></body></html>";
        return $html;
    }

    private function notifyEvent(int $parcelId, string $eventType): void
    {
        $p = Database::selectOne("SELECT sender_phone, recipient_phone, parcel_number FROM parcels WHERE id = ?", [$parcelId]);
        if (!$p) return;

        $msg = match($eventType) {
            'registered'    => "CityBus Fret: Colis {$p['parcel_number']} enregistré.",
            'arrived'       => "CityBus Fret: Votre colis {$p['parcel_number']} est arrivé. Venez le retirer.",
            'delivered'     => "CityBus Fret: Colis {$p['parcel_number']} remis avec succès.",
            'lost','damaged'=> "CityBus Fret: Problème sur colis {$p['parcel_number']}. Contactez-nous.",
            default         => null,
        };
        if (!$msg) return;

        if (Setting::getBool('cargo.notify_sender', true) && $p['sender_phone']) {
            try { (new NotificationV4Service())->queueByCode('CARGO_GENERIC', ['phone'=>$p['sender_phone'],'message'=>$msg]); }
            catch (\Throwable $e) {}
        }
        if (Setting::getBool('cargo.notify_recipient', true) && $p['recipient_phone'] && in_array($eventType, ['arrived','delivered'])) {
            try { (new NotificationV4Service())->queueByCode('CARGO_GENERIC', ['phone'=>$p['recipient_phone'],'message'=>$msg]); }
            catch (\Throwable $e) {}
        }
    }
}
