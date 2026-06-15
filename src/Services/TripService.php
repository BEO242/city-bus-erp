<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;
use CityBus\Models\Trip;

/**
 * Service métier des voyages (refonte audit profondeur 10 mai 2026).
 *
 * Centralise toutes les écritures sur trips et trip_crew.
 * Le contrôleur ne doit faire que de la lecture directe — toute mutation
 * passe par cette classe pour garantir audit, validations et webhooks.
 */
final class TripService
{
    /** Machine à états — tous les cas réels couverts. */
    private const ALLOWED_TRANSITIONS = [
        'planifie'     => ['valide', 'embarquement', 'incident', 'annule'],
        'valide'       => ['embarquement', 'planifie', 'incident', 'annule'],
        'embarquement' => ['en_route', 'valide', 'incident', 'annule'],
        'en_route'     => ['arrive', 'incident', 'retourne'],
        'arrive'       => ['cloture', 'litige'],
        'incident'     => ['planifie', 'valide', 'embarquement', 'en_route', 'arrive', 'retourne', 'annule'],
        'retourne'     => ['cloture', 'litige', 'annule'],
        'litige'       => ['cloture', 'annule'],
        'cloture'      => ['litige'],
        'annule'       => [],
    ];

    /** Statuts qui exigent un motif obligatoire. */
    private const REASON_REQUIRED = ['incident', 'annule', 'retourne', 'litige'];

    // ═══════════════════════════════════════════════════════════════
    // CRÉATION
    // ═══════════════════════════════════════════════════════════════

    public function create(array $data): int
    {
        $this->validateTimingPolicy($data);
        $this->assertBusUsable((int)$data['bus_id']);
        $this->assertDriverActive((int)$data['driver_id']);
        $this->assertNoConflict(
            (int)$data['bus_id'], (int)$data['driver_id'],
            (string)$data['trip_date'], (string)$data['departure_scheduled'],
            $data['arrival_scheduled'] ?? null, null
        );
        $this->assertHosCompliant((int)$data['driver_id'], $data);

        return Database::transaction(function () use ($data) {
            Database::execute("SELECT GET_LOCK('trip_code_gen', 5)");
            try {
                $code = $this->generateCode((int)$data['line_id'], (string)$data['trip_date']);
                $agencyOrigin = $this->resolveAgencyForLine((int)$data['line_id'], 'origin');
                $agencyDest   = $this->resolveAgencyForLine((int)$data['line_id'], 'destination');

                $id = (int)Database::insert(
                    "INSERT INTO trips
                        (trip_code, trip_type, priority, public_visible,
                         line_id, bus_id, driver_id,
                         trip_date, departure_scheduled, arrival_scheduled,
                         mileage_start, weather_conditions, weather_temp_celsius,
                         external_reference, notes, status,
                         agency_origin_id, agency_destination_id,
                         created_by)
                     VALUES (?,?,?,?, ?,?,?, ?,?,?, ?,?,?, ?,?, 'planifie', ?,?, ?)",
                    [
                        $code,
                        $data['trip_type'] ?? 'commercial',
                        $data['priority'] ?? 'normale',
                        (int)($data['public_visible'] ?? 1),
                        (int)$data['line_id'], (int)$data['bus_id'], (int)$data['driver_id'],
                        $data['trip_date'], $data['departure_scheduled'], $data['arrival_scheduled'] ?? null,
                        !empty($data['mileage_start']) ? (int)$data['mileage_start'] : null,
                        $data['weather_conditions'] ?? null,
                        isset($data['weather_temp_celsius']) && $data['weather_temp_celsius'] !== ''
                            ? (int)$data['weather_temp_celsius'] : null,
                        $data['external_reference'] ?? null,
                        $data['notes'] ?? null,
                        $agencyOrigin, $agencyDest,
                        Auth::id(),
                    ]
                );

                $this->logStatusChange($id, null, 'planifie', 'Création du voyage', null);
                AuditLog::record('trip.create', 'trip', $id, [
                    'code' => $code, 'line_id' => (int)$data['line_id'],
                    'bus_id' => (int)$data['bus_id'], 'driver_id' => (int)$data['driver_id'],
                ]);
                WebhookService::dispatch('trip.created', ['trip_id' => $id, 'code' => $code]);

                // ─── Phase 1 v3 : génération auto inventaire + arrêts ───
                if (\CityBus\Core\Setting::getBool('voyage.inventory.enabled', true)) {
                    try {
                        $bus = Database::selectOne("SELECT seats FROM buses WHERE id = ?", [(int)$data['bus_id']]);
                        $seats = (int)($bus['seats'] ?? 0);
                        if ($seats > 0) {
                            (new InventoryService())->generateForTrip($id, $seats, (int)($data['base_price_fcfa'] ?? 0));
                        }
                    } catch (\Throwable $e) {
                        \CityBus\Core\Logger::warning('trip.inventory.auto_gen_failed: ' . $e->getMessage());
                    }
                }
                try {
                    (new StopTrackingService())->generateForTrip($id);
                } catch (\Throwable $e) {
                    \CityBus\Core\Logger::warning('trip.stops.auto_gen_failed: ' . $e->getMessage());
                }

                return $id;
            } finally {
                Database::execute("SELECT RELEASE_LOCK('trip_code_gen')");
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // MISE À JOUR
    // ═══════════════════════════════════════════════════════════════

    public function update(int $tripId, array $data): void
    {
        $before = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$tripId]);
        if (!$before) throw new \RuntimeException("Voyage #$tripId introuvable.");
        if (Trip::isTerminal($before['status'])) {
            throw new \RuntimeException("Impossible de modifier un voyage clôturé/annulé.");
        }

        $changedScheduling =
            (int)$data['bus_id']    !== (int)$before['bus_id']
         || (int)$data['driver_id'] !== (int)$before['driver_id']
         || $data['trip_date']      !== $before['trip_date']
         || $data['departure_scheduled'] !== $before['departure_scheduled']
         || ($data['arrival_scheduled'] ?? null) !== ($before['arrival_scheduled'] ?? null);

        if ($changedScheduling) {
            $this->assertBusUsable((int)$data['bus_id']);
            $this->assertDriverActive((int)$data['driver_id']);
            $this->assertNoConflict(
                (int)$data['bus_id'], (int)$data['driver_id'],
                (string)$data['trip_date'], (string)$data['departure_scheduled'],
                $data['arrival_scheduled'] ?? null, $tripId
            );
        }

        Database::execute(
            "UPDATE trips SET
                trip_type=?, priority=?, public_visible=?,
                line_id=?, bus_id=?, driver_id=?,
                trip_date=?, departure_scheduled=?, arrival_scheduled=?,
                departure_actual=?, arrival_actual=?,
                mileage_start=?, mileage_end=?,
                weather_conditions=?, weather_temp_celsius=?,
                external_reference=?,
                notes=?, incident_notes=?,
                updated_at=NOW()
             WHERE id=?",
            [
                $data['trip_type'] ?? $before['trip_type'],
                $data['priority']  ?? $before['priority'],
                (int)($data['public_visible'] ?? $before['public_visible']),
                (int)$data['line_id'], (int)$data['bus_id'], (int)$data['driver_id'],
                $data['trip_date'], $data['departure_scheduled'], $data['arrival_scheduled'] ?? null,
                $data['departure_actual'] ?? null,
                $data['arrival_actual'] ?? null,
                isset($data['mileage_start']) && $data['mileage_start'] !== '' ? (int)$data['mileage_start'] : null,
                isset($data['mileage_end'])   && $data['mileage_end'] !== ''   ? (int)$data['mileage_end']   : null,
                $data['weather_conditions'] ?? null,
                isset($data['weather_temp_celsius']) && $data['weather_temp_celsius'] !== ''
                    ? (int)$data['weather_temp_celsius'] : null,
                $data['external_reference'] ?? null,
                $data['notes'] ?? null,
                $data['incident_notes'] ?? null,
                $tripId,
            ]
        );

        if (!empty($data['departure_actual']) && !empty($before['departure_scheduled'])) {
            $delay = $this->computeDelayMinutes(
                (string)$before['trip_date'], (string)$before['departure_scheduled'], (string)$data['departure_actual']
            );
            Database::execute("UPDATE trips SET delay_minutes = ? WHERE id = ?", [$delay, $tripId]);
        }

        $after = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$tripId]);
        AuditLog::record('trip.update', 'trip', $tripId, $this->diffSnapshot($before, $after));
    }

    // ═══════════════════════════════════════════════════════════════
    // CHANGEMENT DE STATUT
    // ═══════════════════════════════════════════════════════════════

    public function changeStatus(int $tripId, string $newStatus, ?string $reason = null, array $metadata = []): bool
    {
        return Database::transaction(function () use ($tripId, $newStatus, $reason, $metadata) {
            $current = Database::selectOne("SELECT * FROM trips WHERE id=? FOR UPDATE", [$tripId]);
            if (!$current) throw new \RuntimeException('Voyage introuvable.');

            $from = (string)$current['status'];
            if ($from === $newStatus) return true;

            $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                throw new \RuntimeException("Transition interdite : $from → $newStatus");
            }

            if (in_array($newStatus, self::REASON_REQUIRED, true) && empty(trim((string)$reason))) {
                throw new \RuntimeException("Motif obligatoire pour le statut '$newStatus'.");
            }

            // Validation pré-vérif obligatoire
            if ($newStatus === 'en_route'
                && Setting::getBool('voyage.require_inspection_for_departure', false)) {
                $insp = Database::selectOne(
                    "SELECT id, overall_status FROM pre_trip_inspections WHERE trip_id = ? ORDER BY id DESC LIMIT 1",
                    [$tripId]
                );
                if (!$insp || $insp['overall_status'] === 'fail') {
                    throw new \RuntimeException("Pré-vérification absente ou échouée — départ bloqué.");
                }
            }

            // HOS chauffeur avant en_route
            if ($newStatus === 'en_route' && !empty($current['driver_id'])) {
                $this->assertHosCompliantForTrip((int)$current['driver_id'], (array)$current);
            }

            // Construire le SQL d'update avec les bonnes colonnes selon le statut
            $extraSql = '';
            $extraParams = [];
            if ($newStatus === 'en_route') {
                $extraSql = ', departure_actual = COALESCE(departure_actual, ?)';
                $extraParams[] = date('H:i:s');
            } elseif ($newStatus === 'arrive') {
                $extraSql = ', arrival_actual = COALESCE(arrival_actual, ?)';
                $extraParams[] = date('H:i:s');
            } elseif ($newStatus === 'cloture') {
                $extraSql = ', closed_at = NOW(), closed_by = ?';
                $extraParams[] = Auth::id();
            } elseif ($newStatus === 'annule') {
                $extraSql = ', cancelled_at = NOW(), cancelled_by = ?, cancellation_reason = ?';
                $extraParams[] = Auth::id();
                $extraParams[] = $reason;
            }

            Database::execute(
                "UPDATE trips SET status = ? $extraSql, updated_at = NOW() WHERE id = ?",
                array_merge([$newStatus], $extraParams, [$tripId])
            );

            if ($newStatus === 'en_route' && !empty($current['departure_scheduled'])) {
                $delay = $this->computeDelayMinutes(
                    (string)$current['trip_date'],
                    (string)$current['departure_scheduled'],
                    date('H:i:s')
                );
                Database::execute("UPDATE trips SET delay_minutes = ? WHERE id = ?", [$delay, $tripId]);
            }

            // Synchro statut bus
            if (in_array($newStatus, ['embarquement','en_route'], true)) {
                Database::execute("UPDATE buses SET status='en_voyage' WHERE id=?", [(int)$current['bus_id']]);
            } elseif (in_array($newStatus, ['arrive','cloture','annule','retourne'], true)) {
                Database::execute("UPDATE buses SET status='disponible' WHERE id=?", [(int)$current['bus_id']]);
            }

            $this->logStatusChange($tripId, $from, $newStatus, $reason, $metadata);

            AuditLog::record('trip.status', 'trip', $tripId, [
                'from' => $from, 'to' => $newStatus, 'reason' => $reason,
            ]);

            if ($newStatus === 'annule') {
                $this->cancelTicketsCascade($tripId, $reason ?: 'Voyage annulé');
            }

            $eventMap = [
                'valide'       => 'trip.confirmed',
                'embarquement' => 'trip.boarding',
                'en_route'     => 'trip.departed',
                'arrive'       => 'trip.arrived',
                'cloture'      => 'trip.closed',
                'incident'     => 'trip.incident',
                'retourne'     => 'trip.returned',
                'litige'       => 'trip.disputed',
                'annule'       => 'trip.cancelled',
            ];
            if (isset($eventMap[$newStatus])) {
                WebhookService::dispatch($eventMap[$newStatus], [
                    'trip_id' => $tripId, 'from' => $from, 'to' => $newStatus, 'reason' => $reason,
                ]);
            }

            // Notifications passagers
            if ($newStatus === 'en_route' && Setting::getBool('sms.notify_trip_departure', false)) {
                $this->notifyPassengers($tripId, "CITY BUS · Votre voyage vient de partir. Bon trajet !");
            } elseif ($newStatus === 'incident' && Setting::getBool('sms.notify_trip_delay', false)) {
                $this->notifyPassengers($tripId,
                    "CITY BUS · Incident sur votre voyage" . ($reason ? " ($reason)" : '') . ". Plus d'infos à l'agence.");
            } elseif ($newStatus === 'retourne') {
                $this->notifyPassengers($tripId,
                    "CITY BUS · Votre voyage fait demi-tour" . ($reason ? " ($reason)" : '') . ". Présentez-vous à l'agence.");
            }

            return true;
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // ACTIONS OPÉRATIONNELLES
    // ═══════════════════════════════════════════════════════════════

    public function replaceBus(int $tripId, int $newBusId, string $reason): void
    {
        $trip = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) throw new \RuntimeException('Voyage introuvable.');
        if (Trip::isTerminal($trip['status'])) {
            throw new \RuntimeException("Impossible de changer le bus d'un voyage terminé.");
        }
        if ((int)$trip['bus_id'] === $newBusId) {
            throw new \RuntimeException('Ce bus est déjà affecté.');
        }
        $this->assertBusUsable($newBusId);

        Database::execute(
            "UPDATE trips SET replaced_bus_id = bus_id, bus_id = ?, updated_at = NOW() WHERE id = ?",
            [$newBusId, $tripId]
        );
        Database::execute("UPDATE buses SET status = 'disponible' WHERE id = ?", [(int)$trip['bus_id']]);
        if (in_array($trip['status'], ['embarquement','en_route'], true)) {
            Database::execute("UPDATE buses SET status = 'en_voyage' WHERE id = ?", [$newBusId]);
        }

        AuditLog::record('trip.replace_bus', 'trip', $tripId, [
            'old_bus_id' => (int)$trip['bus_id'], 'new_bus_id' => $newBusId, 'reason' => $reason,
        ]);
        $this->logStatusChange($tripId, $trip['status'], $trip['status'], "Bus remplacé : {$reason}", [
            'old_bus_id' => (int)$trip['bus_id'], 'new_bus_id' => $newBusId,
        ]);
        WebhookService::dispatch('trip.bus_changed', ['trip_id' => $tripId, 'new_bus_id' => $newBusId]);
    }

    public function replaceDriver(int $tripId, int $newDriverId, string $reason): void
    {
        $trip = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) throw new \RuntimeException('Voyage introuvable.');
        if (Trip::isTerminal($trip['status'])) throw new \RuntimeException('Voyage terminé.');
        if ((int)$trip['driver_id'] === $newDriverId) throw new \RuntimeException('Déjà affecté.');

        $this->assertDriverActive($newDriverId);
        $this->assertHosCompliantForTrip($newDriverId, (array)$trip);

        Database::execute(
            "UPDATE trips SET replaced_driver_id = driver_id, driver_id = ?, updated_at = NOW() WHERE id = ?",
            [$newDriverId, $tripId]
        );
        AuditLog::record('trip.replace_driver', 'trip', $tripId, [
            'old_driver_id' => (int)$trip['driver_id'], 'new_driver_id' => $newDriverId, 'reason' => $reason,
        ]);
        $this->logStatusChange($tripId, $trip['status'], $trip['status'], "Chauffeur remplacé : {$reason}", [
            'old_driver_id' => (int)$trip['driver_id'], 'new_driver_id' => $newDriverId,
        ]);
        WebhookService::dispatch('trip.driver_changed', ['trip_id' => $tripId, 'new_driver_id' => $newDriverId]);
    }

    public function lockManifest(int $tripId, ?string $reason = null): void
    {
        Database::execute(
            "UPDATE trips SET manifest_locked_at = NOW(), manifest_locked_by = ? WHERE id = ?",
            [Auth::id(), $tripId]
        );
        AuditLog::record('trip.manifest.lock', 'trip', $tripId, ['reason' => $reason]);
    }

    public function unlockManifest(int $tripId, ?string $reason = null): void
    {
        Database::execute(
            "UPDATE trips SET manifest_locked_at = NULL, manifest_locked_by = NULL WHERE id = ?",
            [$tripId]
        );
        AuditLog::record('trip.manifest.unlock', 'trip', $tripId, ['reason' => $reason]);
    }

    public function createReplacement(int $parentTripId, array $overrides = []): int
    {
        $parent = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$parentTripId]);
        if (!$parent) throw new \RuntimeException('Voyage parent introuvable.');

        $newId = $this->create([
            'line_id'             => (int)$parent['line_id'],
            'bus_id'              => (int)($overrides['bus_id'] ?? $parent['bus_id']),
            'driver_id'           => (int)($overrides['driver_id'] ?? $parent['driver_id']),
            'trip_date'           => $overrides['trip_date'] ?? date('Y-m-d'),
            'departure_scheduled' => $overrides['departure_scheduled'] ?? date('H:i:s'),
            'arrival_scheduled'   => $overrides['arrival_scheduled'] ?? null,
            'trip_type'           => 'commercial',
            'priority'            => 'express',
            'notes'               => "Voyage de remplacement de #{$parentTripId} ({$parent['trip_code']})",
            'external_reference'  => $parent['trip_code'] . '-R',
        ]);
        Database::execute("UPDATE trips SET parent_trip_id = ? WHERE id = ?", [$parentTripId, $newId]);
        AuditLog::record('trip.replacement.create', 'trip', $newId, ['parent_trip_id' => $parentTripId]);
        return $newId;
    }

    public function communicateToPassengers(int $tripId, string $message, string $audience = 'all_passengers'): array
    {
        $rows = Database::select(
            "SELECT DISTINCT passenger_phone FROM tickets
              WHERE trip_id = ? AND status NOT IN ('annule') AND deleted_at IS NULL
                AND passenger_phone IS NOT NULL AND passenger_phone <> ''",
            [$tripId]
        );

        $sent = 0; $errors = 0;
        foreach ($rows as $r) {
            try { SmsService::send((string)$r['passenger_phone'], $message); $sent++; }
            catch (\Throwable $e) { $errors++; }
        }

        Database::insert(
            "INSERT INTO trip_messages (trip_id, channel, audience, body, recipients_count, success_count, sent_by)
             VALUES (?, 'sms', ?, ?, ?, ?, ?)",
            [$tripId, $audience, $message, count($rows), $sent, Auth::id()]
        );
        AuditLog::record('trip.communicate.sms', 'trip', $tripId, [
            'audience' => $audience, 'recipients' => count($rows), 'sent' => $sent,
        ]);
        return ['recipients' => count($rows), 'sent' => $sent, 'errors' => $errors];
    }

    public function delete(int $tripId): void
    {
        $trip = Database::selectOne("SELECT trip_code, status FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) throw new \RuntimeException('Voyage introuvable.');
        $emis = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM tickets WHERE trip_id = ? AND status NOT IN ('annule') AND deleted_at IS NULL",
            [$tripId]
        )['c'] ?? 0);
        if ($emis > 0) throw new \RuntimeException("Impossible : $emis billet(s) actif(s).");
        Database::execute("DELETE FROM trips WHERE id = ?", [$tripId]);
        AuditLog::record('trip.delete', 'trip', $tripId, ['code' => $trip['trip_code']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // VALIDATIONS
    // ═══════════════════════════════════════════════════════════════

    public function isManifestLocked(int $tripId): bool
    {
        $row = Database::selectOne("SELECT manifest_locked_at FROM trips WHERE id = ?", [$tripId]);
        if (!$row) return false;
        if (!empty($row['manifest_locked_at'])) return true;
        return $this->shouldAutoLock($tripId);
    }

    private function shouldAutoLock(int $tripId): bool
    {
        $delay = Setting::getInt('voyage.lock_sales_after_departure_min', 15);
        if ($delay <= 0) return false;
        $row = Database::selectOne(
            "SELECT trip_date, departure_actual FROM trips WHERE id = ?", [$tripId]
        );
        if (!$row || empty($row['departure_actual'])) return false;
        $depTs = strtotime($row['trip_date'] . ' ' . $row['departure_actual']);
        return $depTs && (time() - $depTs) >= $delay * 60;
    }

    private function validateTimingPolicy(array $data): void
    {
        if (Setting::getBool('voyage.allow_same_day_creation_only', false)) {
            if (strtotime($data['trip_date']) < strtotime(date('Y-m-d'))) {
                throw new \RuntimeException("Création de voyage dans le passé interdite.");
            }
        }
        $restH = Setting::getInt('voyage.min_driver_rest_hours', 0);
        if ($restH > 0 && !empty($data['driver_id'])) {
            $departTs = strtotime((string)$data['trip_date'] . ' ' . (string)$data['departure_scheduled']);
            if ($departTs !== false) {
                $lastTrip = Database::selectOne(
                    "SELECT trip_date, COALESCE(arrival_actual, arrival_scheduled, departure_scheduled) AS end_ts
                       FROM trips WHERE driver_id = ? AND status != 'annule'
                      ORDER BY trip_date DESC, departure_scheduled DESC LIMIT 1",
                    [(int)$data['driver_id']]
                );
                if ($lastTrip && !empty($lastTrip['end_ts'])) {
                    $lastEndTs = strtotime((string)$lastTrip['trip_date'] . ' ' . (string)$lastTrip['end_ts']);
                    if ($lastEndTs !== false && ($departTs - $lastEndTs) < $restH * 3600) {
                        throw new \RuntimeException("Repos chauffeur insuffisant : minimum {$restH}h.");
                    }
                }
            }
        }
    }

    private function assertBusUsable(int $busId): void
    {
        $bus = Database::selectOne("SELECT id, status FROM buses WHERE id = ?", [$busId]);
        if (!$bus) throw new \RuntimeException("Bus #$busId introuvable.");
        $unusable = ['hors_service', 'maintenance', 'reforme'];
        if (in_array($bus['status'], $unusable, true)) {
            throw new \RuntimeException("Bus indisponible (statut : {$bus['status']}).");
        }
    }

    private function assertDriverActive(int $driverId): void
    {
        $emp = Database::selectOne(
            "SELECT id, status FROM drivers WHERE id = ? AND deleted_at IS NULL",
            [$driverId]
        );
        if (!$emp) throw new \RuntimeException("Chauffeur #$driverId introuvable.");
        if ($emp['status'] !== 'actif') {
            throw new \RuntimeException("Chauffeur non actif (statut : {$emp['status']}).");
        }
    }

    private function assertHosCompliant(int $driverId, array $data): void
    {
        if (!class_exists(DriverHosService::class)) return;
        try {
            $tripStart = $data['trip_date'] . ' ' . $data['departure_scheduled'];
            $tripEnd   = !empty($data['arrival_scheduled'])
                ? $data['trip_date'] . ' ' . $data['arrival_scheduled']
                : $tripStart;
            $driver = Database::selectOne(
                "SELECT id FROM drivers WHERE id = ? AND deleted_at IS NULL LIMIT 1",
                [$driverId]
            );
            if (!$driver) return;
            $check = (new DriverHosService())->canAssign((int)$driver['id'], $tripStart, $tripEnd);
            if ($check['blocking'] && $check['enforce']) {
                $msgs = array_column($check['warnings'], 'message');
                throw new \RuntimeException("HOS chauffeur dépassé : " . implode(' · ', $msgs));
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::warning('hos.check_failed: ' . $e->getMessage());
        }
    }

    private function assertHosCompliantForTrip(int $driverId, array $trip): void
    {
        $this->assertHosCompliant($driverId, [
            'trip_date'           => $trip['trip_date'],
            'departure_scheduled' => $trip['departure_scheduled'],
            'arrival_scheduled'   => $trip['arrival_scheduled'] ?? null,
        ]);
    }

    public function assertNoConflictPublic(
        int $busId, int $driverId, string $tripDate,
        string $departureScheduled, ?string $arrivalScheduled, ?int $excludeTripId
    ): void {
        $this->assertNoConflict($busId, $driverId, $tripDate, $departureScheduled, $arrivalScheduled, $excludeTripId);
    }

    private function assertNoConflict(
        int $busId, int $driverId, string $tripDate,
        string $departureScheduled, ?string $arrivalScheduled, ?int $excludeTripId
    ): void {
        $bufferHours = max(1, Setting::getInt('voyage.conflict_buffer_hours', 2));
        $start = $tripDate . ' ' . $departureScheduled;
        if ($arrivalScheduled) {
            $end = $tripDate . ' ' . $arrivalScheduled;
            if (strtotime($end) <= strtotime($start)) {
                $end = date('Y-m-d H:i:s', strtotime($start) + $bufferHours * 3600);
            }
        } else {
            $end = date('Y-m-d H:i:s', strtotime($start) + $bufferHours * 3600);
        }

        $params = [$busId, $driverId, $end, $bufferHours, $start];
        $sql = "SELECT id, trip_code, bus_id, driver_id, departure_scheduled, trip_date
                  FROM trips
                 WHERE status NOT IN ('annule','cloture')
                   AND (bus_id = ? OR driver_id = ?)
                   AND CONCAT(trip_date,' ',departure_scheduled) < ?
                   AND COALESCE(
                         CONCAT(trip_date,' ',arrival_scheduled),
                         DATE_ADD(CONCAT(trip_date,' ',departure_scheduled), INTERVAL ? HOUR)
                       ) > ?";

        if ($excludeTripId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excludeTripId;
        }
        $rows = Database::select($sql, $params);

        foreach ($rows as $r) {
            if ((int)$r['bus_id'] === $busId) {
                throw new \RuntimeException("Conflit : bus déjà affecté au voyage {$r['trip_code']} ({$r['trip_date']} {$r['departure_scheduled']}).");
            }
            if ((int)$r['driver_id'] === $driverId) {
                throw new \RuntimeException("Conflit : chauffeur déjà affecté au voyage {$r['trip_code']} ({$r['trip_date']} {$r['departure_scheduled']}).");
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function generateCode(int $lineId, string $date): string
    {
        $line = Database::selectOne("SELECT code FROM bus_lines WHERE id = ?", [$lineId]);
        $prefix = ($line['code'] ?? 'L000') . '-' . date('Ymd', strtotime($date));
        $row = Database::selectOne(
            "SELECT trip_code FROM trips WHERE trip_code LIKE ? ORDER BY id DESC LIMIT 1",
            ["$prefix-%"]
        );
        $next = $row ? ((int)substr($row['trip_code'], -2)) + 1 : 1;
        return sprintf('%s-%02d', $prefix, $next);
    }

    private function resolveAgencyForLine(int $lineId, string $end): ?int
    {
        $col = $end === 'origin' ? 'departure_city_id' : 'arrival_city_id';
        $row = Database::selectOne(
            "SELECT a.id FROM bus_lines l
             JOIN agencies a ON a.city_id = l.$col AND a.type='principale' AND a.is_active=1
             WHERE l.id = ? LIMIT 1",
            [$lineId]
        );
        return $row ? (int)$row['id'] : null;
    }

    private function computeDelayMinutes(string $tripDate, string $scheduled, string $actual): int
    {
        $sched = strtotime($tripDate . ' ' . $scheduled);
        $act   = strtotime($tripDate . ' ' . $actual);
        if (!$sched || !$act) return 0;
        return (int)round(($act - $sched) / 60);
    }

    private function logStatusChange(int $tripId, ?string $from, string $to, ?string $reason, ?array $metadata): void
    {
        Database::insert(
            "INSERT INTO trip_status_log (trip_id, from_status, to_status, reason, metadata, changed_by)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $tripId, $from, $to, $reason,
                $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                Auth::id(),
            ]
        );
    }

    private function cancelTicketsCascade(int $tripId, string $reason): void
    {
        $cnt = Database::execute(
            "UPDATE tickets SET status='annule', cancelled_at=NOW(),
                                cancel_reason=CONCAT('Voyage annulé: ', ?)
              WHERE trip_id=? AND status IN ('emis','controle','utilise','embarque') AND deleted_at IS NULL",
            [$reason, $tripId]
        );
        Database::execute(
            "UPDATE baggage_tickets SET status='annule', cancelled_at=NOW(),
                                        cancel_reason=CONCAT('Voyage annulé: ', ?)
              WHERE trip_id=? AND status='emis' AND deleted_at IS NULL",
            [$reason, $tripId]
        );
        AuditLog::record('trip.cancel.cascade', 'trip', $tripId, ['tickets_cancelled' => $cnt]);
    }

    private function notifyPassengers(int $tripId, string $message): void
    {
        try {
            $rows = Database::select(
                "SELECT DISTINCT passenger_phone FROM tickets
                  WHERE trip_id = ? AND status NOT IN ('annule') AND deleted_at IS NULL
                    AND passenger_phone IS NOT NULL AND passenger_phone <> ''",
                [$tripId]
            );
            $sent = 0;
            foreach ($rows as $r) {
                try { SmsService::send((string)$r['passenger_phone'], $message); $sent++; } catch (\Throwable $e) {}
            }
            Database::insert(
                "INSERT INTO trip_messages (trip_id, channel, audience, body, recipients_count, success_count, sent_by)
                 VALUES (?, 'sms', 'all_passengers', ?, ?, ?, NULL)",
                [$tripId, $message, count($rows), $sent]
            );
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::warning("trip.notify_passengers: " . $e->getMessage());
        }
    }

    private function diffSnapshot(array $before, array $after): array
    {
        $changes = [];
        $tracked = ['line_id','bus_id','driver_id','trip_date','departure_scheduled',
                    'arrival_scheduled','departure_actual','arrival_actual',
                    'mileage_start','mileage_end','trip_type','priority',
                    'weather_conditions','external_reference','notes'];
        foreach ($tracked as $f) {
            $b = $before[$f] ?? null; $a = $after[$f] ?? null;
            if ($b != $a) {
                $changes[$f] = ['from' => $b, 'to' => $a];
            }
        }
        return $changes;
    }

    // ═══════════════════════════════════════════════════════════════
    // LECTURES
    // ═══════════════════════════════════════════════════════════════

    public function bookedSeats(int $tripId): array
    {
        $rows = Database::select(
            "SELECT seat_number FROM tickets
             WHERE trip_id = ? AND seat_number IS NOT NULL
               AND status IN ('emis','valide','arrive','controle','utilise','embarque') AND deleted_at IS NULL",
            [$tripId]
        );
        return array_map(fn($r) => (int)$r['seat_number'], $rows);
    }

    public function statusTimeline(int $tripId): array
    {
        return Database::select(
            "SELECT tsl.*, CONCAT(u.first_name,' ',u.last_name) AS author
             FROM trip_status_log tsl
             LEFT JOIN users u ON u.id = tsl.changed_by
             WHERE tsl.trip_id = ?
             ORDER BY tsl.changed_at ASC, tsl.id ASC",
            [$tripId]
        );
    }

    public function autoBoardingCron(): int
    {
        $minutes = Setting::getInt('voyage.auto_boarding_minutes', 0);
        if ($minutes <= 0) return 0;
        $rows = Database::select(
            "SELECT id FROM trips
              WHERE status = 'planifie'
                AND TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(trip_date,' ',departure_scheduled)) BETWEEN 0 AND ?",
            [$minutes]
        );
        $cnt = 0;
        foreach ($rows as $r) {
            try { $this->changeStatus((int)$r['id'], 'embarquement', 'Auto-embarquement (cron)'); $cnt++; }
            catch (\Throwable $e) {}
        }
        return $cnt;
    }

    public function autoCloseCron(): int
    {
        $minutes = Setting::getInt('voyage.auto_close_minutes', 0);
        if ($minutes <= 0) return 0;
        $rows = Database::select(
            "SELECT id FROM trips
              WHERE status = 'arrive'
                AND arrival_actual IS NOT NULL
                AND TIMESTAMPDIFF(MINUTE, CONCAT(trip_date,' ',arrival_actual), NOW()) >= ?",
            [$minutes]
        );
        $cnt = 0;
        foreach ($rows as $r) {
            try { $this->changeStatus((int)$r['id'], 'cloture', 'Auto-clôture (cron)'); $cnt++; }
            catch (\Throwable $e) {}
        }
        return $cnt;
    }
}
