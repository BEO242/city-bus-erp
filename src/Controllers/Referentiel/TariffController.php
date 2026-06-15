<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\BaggageTariff;
use CityBus\Models\Tariff;

final class TariffController extends Controller
{
    public function index(Request $request): void
    {
        $lineFilter = (int)$request->input('line_id', 0);
        $tab        = in_array($request->input('tab'), ['passagers','cargo'], true)
                    ? $request->input('tab') : 'passagers';

        $lines = Database::select("SELECT id, code, name FROM bus_lines ORDER BY code");

        // ── Tarifs passagers ──────────────────────────────────────────
        $paxWhere  = $lineFilter > 0 ? 'WHERE t.line_id = ?' : '';
        $paxParams = $lineFilter > 0 ? [$lineFilter] : [];
        $tariffs = Database::select(
            "SELECT t.id, t.line_id, t.ticket_type,
                    t.passenger_categories, t.travel_class,
                    t.price_fcfa, t.is_active,
                    t.valid_from, t.valid_until, t.label, t.notes,
                    t.origin_stop_id, t.destination_stop_id,
                    l.code AS line_code, l.name AS line_name,
                    sd.name AS destination_stop_name,
                    so.name AS origin_stop_name,
                    cd.name AS departure_city_name,
                    ca.name AS arrival_city_name
             FROM tariffs t
             INNER JOIN bus_lines l ON l.id = t.line_id
             LEFT JOIN cities cd ON cd.id = l.departure_city_id
             LEFT JOIN cities ca ON ca.id = l.arrival_city_id
             LEFT JOIN stops sd ON sd.id = t.destination_stop_id
             LEFT JOIN stops so ON so.id = t.origin_stop_id
             $paxWhere
             ORDER BY l.code ASC, t.ticket_type ASC, t.price_fcfa ASC",
            $paxParams
        );

        // ── Tarifs fret/colis (source unique : fret_categories) ────────
        $parcelTariffs = Database::select(
            "SELECT c.id, c.label, c.slug AS category, c.price_per_kg, c.min_price_fcfa,
                    c.is_active, c.color, c.sort_order,
                    (SELECT COUNT(*) FROM parcels p WHERE p.parcel_type = c.slug AND p.deleted_at IS NULL) AS parcel_count
             FROM fret_categories c
             ORDER BY c.sort_order ASC, c.label ASC"
        );

        $this->view('referentiel/tariffs/index', [
            'title'         => 'Tarifs',
            'tab'           => $tab,
            'lineFilter'    => $lineFilter,
            'lines'         => $lines,
            'tariffs'       => $tariffs,
            'parcelTariffs' => $parcelTariffs,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('referentiel/tariffs/form', [
            'title'        => 'Nouveau tarif',
            'tariff'       => null,
            'lines'        => Database::select("SELECT id, code, name, line_type FROM bus_lines WHERE is_active=1 ORDER BY line_type, code"),
            'stops'        => $this->loadStopsByLine(),
            'ticketTypes'  => $this->validTypes(),
            'travelClasses'=> $this->validClasses(),
            'action'       => url('referentiel/tariffs'),
        ]);
    }

    public function store(Request $request): void
    {
        $lineId  = (int)$request->input('line_id', 0);
        $type    = trim((string)$request->input('ticket_type', ''));
        $price   = (int)$request->input('price_fcfa', 0);
        $rawCats = $request->input('passenger_category', []);
        $cats    = is_array($rawCats) ? $rawCats : ($rawCats !== '' ? [$rawCats] : []);
        $validCats    = array_keys($this->validCategories());
        $validTypes   = array_keys($this->validTypes());
        $validClasses = array_keys($this->validClasses());

        $cats  = array_values(array_filter(array_map('trim', $cats), fn($c) => in_array($c, $validCats)));
        $class = trim((string)$request->input('travel_class', 'standard'));

        // Validations
        if ($lineId <= 0) {
            $this->flash('danger', 'Veuillez sélectionner une ligne.');
            back(); return;
        }
        if (!in_array($type, $validTypes)) {
            $this->flash('danger', 'Type de billet invalide.');
            back(); return;
        }
        if (empty($cats)) {
            $this->flash('danger', 'Veuillez sélectionner au moins une catégorie passager.');
            back(); return;
        }
        if (!in_array($class, $validClasses)) {
            $class = 'standard';
        }
        if ($price < 0) {
            $this->flash('danger', 'Le prix ne peut pas être négatif.');
            back(); return;
        }

        $validFrom  = trim((string)$request->input('valid_from', ''))  ?: null;
        $validUntil = trim((string)$request->input('valid_until', '')) ?: null;

        if ($validFrom && $validUntil && $validUntil < $validFrom) {
            $this->flash('danger', 'La date de fin ne peut pas être antérieure à la date de début.');
            back(); return;
        }

        // Résoudre origin_stop_id AVANT le check d'overlap
        $originStopId = (int)$request->input('origin_stop_id', 0) ?: null;
        if ($originStopId !== null) {
            $stopOk = Database::selectOne("SELECT id FROM stops WHERE id = ? AND line_id = ?", [$originStopId, $lineId]);
            if (!$stopOk) $originStopId = null;
        }

        // Multi-destination : récupérer la liste des destination_stop_ids (mode création multi)
        $rawDestIds = $request->input('destination_stop_ids', []);
        if (is_array($rawDestIds) && !empty($rawDestIds)) {
            // Multi-sélection : convertir. 0 = terminus (NULL)
            $destStopIds = [];
            foreach ($rawDestIds as $did) {
                $did = (int)$did;
                if ($did === 0) {
                    $destStopIds[] = null; // terminus
                } else {
                    $stopOk = Database::selectOne("SELECT id FROM stops WHERE id = ? AND line_id = ?", [$did, $lineId]);
                    if ($stopOk) {
                        $destStopIds[] = $did;
                    }
                }
            }
            if (empty($destStopIds)) {
                $destStopIds = [null]; // fallback terminus
            }
        } else {
            // Mode simple (un seul destination_stop_id, ex: édition)
            $destStopId = (int)$request->input('destination_stop_id', 0) ?: null;
            if ($destStopId !== null) {
                $stopOk = Database::selectOne("SELECT id FROM stops WHERE id = ? AND line_id = ?", [$destStopId, $lineId]);
                if (!$stopOk) $destStopId = null;
            }
            $destStopIds = [$destStopId];
        }

        // Anti-chevauchement + même arrêt origine/destination pour CHAQUE destination
        foreach ($destStopIds as $destStopId) {
            // ── Vérifier que l'origine et la destination sont distinctes ──────
            $sameStopError = $this->checkSameOriginDest($lineId, $originStopId, $destStopId);
            if ($sameStopError) {
                $this->flash('danger', $sameStopError);
                back(); return;
            }

            foreach ($cats as $catCheck) {
                $conflict = Tariff::overlapExists($lineId, $type, $catCheck, $class, $validFrom, $validUntil, null, $originStopId, $destStopId);
                if ($conflict) {
                    $period = $this->formatPeriod($conflict['valid_from'] ?? null, $conflict['valid_until'] ?? null);
                    $destLabel = $destStopId
                        ? (Database::selectOne("SELECT name FROM stops WHERE id=?", [$destStopId])['name'] ?? "arrêt #{$destStopId}")
                        : 'terminus';
                    $this->flash('danger',
                        "Un tarif actif couvre déjà la catégorie '{$catCheck}' vers {$destLabel} ({$period}). " .
                        "Désactivez-le ou ajustez la période de validité avant de créer ce nouveau tarif.");
                    back(); return;
                }
            }
        }

        $label  = trim((string)$request->input('label', '')) ?: null;
        $notes  = trim((string)$request->input('notes', '')) ?: null;
        $bagQty = max(0, (int)$request->input('baggage_included_qty', 1));
        $bagKg  = max(0.0, (float)$request->input('baggage_included_kg', 15));
        $bagemsRaw = $request->input('bagages', []);
        $servicesIds = $request->input('services', []);

        try {
            $createdCount = 0;
            foreach ($destStopIds as $destStopId) {
                $data = [
                    'line_id'              => $lineId,
                    'origin_stop_id'       => $originStopId,
                    'destination_stop_id'  => $destStopId,
                    'ticket_type'          => $type,
                    'label'                => $label,
                    'passenger_categories' => json_encode($cats),
                    'travel_class'         => $class,
                    'valid_from'           => $validFrom,
                    'valid_until'          => $validUntil,
                    'notes'                => $notes,
                    'price_fcfa'           => $price,
                    'baggage_included_qty' => $bagQty,
                    'baggage_included_kg'  => $bagKg,
                    'is_active'            => 1,
                ];

                $newId = Tariff::create($data);
                $this->saveServiceMap((int)$newId, $servicesIds);

                // ─── Barèmes bagage (un enregistrement par barème, plusieurs natures en JSON) ───
                if (is_array($bagemsRaw)) {
                    foreach ($bagemsRaw as $item) {
                        if (!is_array($item)) { continue; }
                        $btData = $this->extractBagemeItem($item);
                        if ($btData === null) { continue; }
                        $btId = Database::insert(
                            "INSERT INTO baggage_tariffs
                               (line_id, tariff_id, baggage_nature_ids, label, base_fee_fcfa, per_kg_fcfa,
                                bracket_mode, volume_surcharge_fcfa, max_weight_kg, max_length_cm,
                                max_width_cm, max_height_cm, max_girth_cm, max_volume_cm3,
                                valid_from, valid_until, notes, is_active)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)",
                            [
                                $lineId, (int)$newId, json_encode($btData['nature_ids']), $btData['label'],
                                $btData['base_fee'], $btData['per_kg'], $btData['bracket_mode'],
                                $btData['vol_surch'], $btData['max_wt'], $btData['max_len'],
                                $btData['max_w'], $btData['max_h'], $btData['max_girth'],
                                $btData['max_vol'], $validFrom, $validUntil, $notes,
                            ]
                        );
                        $this->saveBrackets((int)$btId, $this->parseBracketsFromArray($item['brackets'] ?? []));
                    }
                }
                $createdCount++;
            }
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                $this->flash('danger', 'Un tarif identique (même ligne, type, catégorie, classe) existe déjà et est actif.');
            } else {
                $this->flash('danger', $e->getMessage());
            }
            back(); return;
        }
        $msg = $createdCount > 1 ? "{$createdCount} tarifs créés." : 'Tarif créé.';
        $this->flash('success', $msg);
        redirect('referentiel/tariffs');
    }

    public function edit(Request $request, string $id): void
    {
        $tariff = Tariff::findOrFail((int)$id);

        $this->view('referentiel/tariffs/form', [
            'title'        => 'Modifier tarif',
            'tariff'       => $tariff,
            'lines'        => Database::select("SELECT id, code, name, line_type FROM bus_lines ORDER BY line_type, code"),
            'stops'        => $this->loadStopsByLine(),
            'ticketTypes'  => $this->validTypes(),
            'travelClasses'=> $this->validClasses(),
            'action'       => url('referentiel/tariffs/' . $id),
        ]);
    }

    public function update(Request $request, string $id): void
    {
        // ─── Freeze : interdire la modification si le tarif a déjà servi sur un voyage clôturé ─
        $frozen = Database::selectOne(
            "SELECT COUNT(*) AS n
               FROM tickets tk
               INNER JOIN trips tr ON tr.id = tk.trip_id
              WHERE tk.tariff_id = ?
                AND tr.status IN ('arrive','cloture')
                AND tk.status != 'annule'",
            [(int)$id]
        );
        if ((int)($frozen['n'] ?? 0) > 0) {
            $this->flash('danger', 'Ce tarif est gelé : des billets émis sur des voyages clôturés/arrivés y font référence. Créez un nouveau tarif au lieu de le modifier.');
            redirect('referentiel/tariffs');
            return;
        }

        $lineId  = (int)$request->input('line_id', 0);
        $type    = trim((string)$request->input('ticket_type', ''));
        $price   = (int)$request->input('price_fcfa', 0);
        $rawCats = $request->input('passenger_category', []);
        $rawCats = is_array($rawCats) ? $rawCats : [$rawCats];
        $cats    = array_values(array_filter(
            array_map('trim', $rawCats),
            fn($c) => $c !== '' && array_key_exists($c, $this->validCategories())
        ));
        if (empty($cats)) { $cats = ['adulte']; }
        $class   = trim((string)$request->input('travel_class', 'standard'));

        if ($lineId <= 0) {
            $this->flash('danger', 'Veuillez sélectionner une ligne.');
            back(); return;
        }
        if (!array_key_exists($type, $this->validTypes())) {
            $this->flash('danger', 'Type de billet invalide.');
            back(); return;
        }
        if (!array_key_exists($class, $this->validClasses())) {
            $this->flash('danger', 'Classe de voyage invalide.');
            back(); return;
        }
        if ($price < 0) {
            $this->flash('danger', 'Le prix ne peut pas être négatif.');
            back(); return;
        }

        $validFrom  = trim((string)$request->input('valid_from', ''))  ?: null;
        $validUntil = trim((string)$request->input('valid_until', '')) ?: null;

        if ($validFrom && $validUntil && $validUntil < $validFrom) {
            $this->flash('danger', 'La date de fin ne peut pas être antérieure à la date de début.');
            back(); return;
        }

        $isActive = (int)$request->input('is_active', 0);

        // Résoudre origin_stop_id et destination_stop_id AVANT le check d'overlap
        $originStopId = (int)$request->input('origin_stop_id', 0) ?: null;
        if ($originStopId !== null) {
            $stopOk = Database::selectOne("SELECT id FROM stops WHERE id = ? AND line_id = ?", [$originStopId, $lineId]);
            if (!$stopOk) $originStopId = null;
        }
        $destStopId = (int)$request->input('destination_stop_id', 0) ?: null;
        if ($destStopId !== null) {
            $stopOk = Database::selectOne("SELECT id FROM stops WHERE id = ? AND line_id = ?", [$destStopId, $lineId]);
            if (!$stopOk) $destStopId = null;
        }

        // ── Vérifier que l'origine et la destination sont distinctes ──────────
        $sameStopError = $this->checkSameOriginDest($lineId, $originStopId, $destStopId);
        if ($sameStopError) {
            $this->flash('danger', $sameStopError);
            back(); return;
        }

        // Anti-chevauchement
        if ($isActive === 1) {
            foreach ($cats as $catCheck) {
                $conflict = Tariff::overlapExists($lineId, $type, $catCheck, $class, $validFrom, $validUntil, (int)$id, $originStopId, $destStopId);
                if ($conflict) {
                    $period = $this->formatPeriod($conflict['valid_from'] ?? null, $conflict['valid_until'] ?? null);
                    $this->flash('danger',
                        "Un autre tarif actif couvre déjà la catégorie '{$catCheck}' sur ce segment ({$period}). " .
                        "Désactivez-le ou ajustez les périodes de validité.");
                    back(); return;
                }
            }
        }

        $data = [
            'line_id'              => $lineId,
            'origin_stop_id'       => $originStopId,
            'destination_stop_id'  => $destStopId,
            'ticket_type'          => $type,
            'label'                => trim((string)$request->input('label', '')) ?: null,
            'passenger_categories' => json_encode($cats),
            'travel_class'         => $class,
            'valid_from'           => $validFrom,
            'valid_until'          => $validUntil,
            'notes'                => trim((string)$request->input('notes', '')) ?: null,
            'price_fcfa'           => $price,
            'baggage_included_qty' => max(0, (int)$request->input('baggage_included_qty', 1)),
            'baggage_included_kg'  => max(0.0, (float)$request->input('baggage_included_kg', 15)),
            'is_active'            => $isActive,
        ];

        try {
            Tariff::update((int)$id, $data);
            $this->saveServiceMap((int)$id, $request->input('services', []));
            // ─── Barèmes bagage : désactiver les anciens si utilisés, sinon supprimer ──
            $oldBts = Database::select(
                "SELECT id FROM baggage_tariffs WHERE tariff_id = ?",
                [(int)$id]
            );
            if (!empty($oldBts)) {
                $oldBtIds     = array_column($oldBts, 'id');
                $placeholders = implode(',', array_fill(0, count($oldBtIds), '?'));
                $usedBt = Database::selectOne(
                    "SELECT COUNT(*) AS n FROM baggage_tickets WHERE baggage_tariff_id IN ({$placeholders})",
                    $oldBtIds
                );
                if ((int)($usedBt['n'] ?? 0) > 0) {
                    // Des billets existent : on désactive les anciens barèmes (archive)
                    Database::execute(
                        "UPDATE baggage_tariffs SET is_active = 0 WHERE id IN ({$placeholders})",
                        $oldBtIds
                    );
                } else {
                    // Aucun billet lié : suppression propre
                    foreach ($oldBts as $old) {
                        Database::execute(
                            "DELETE FROM baggage_tariff_brackets WHERE baggage_tariff_id=?",
                            [(int)$old['id']]
                        );
                        BaggageTariff::delete((int)$old['id']);
                    }
                }
            }
            $bagemsRaw = $request->input('bagages', []);
            if (is_array($bagemsRaw)) {
                foreach ($bagemsRaw as $item) {
                    if (!is_array($item)) { continue; }
                    $btData   = $this->extractBagemeItem($item);
                    if ($btData === null) { continue; }
                    $brackets = $this->parseBracketsFromArray($item['brackets'] ?? []);
                    $btId = Database::insert(
                        "INSERT INTO baggage_tariffs
                           (line_id, tariff_id, baggage_nature_ids, label, base_fee_fcfa, per_kg_fcfa,
                            bracket_mode, volume_surcharge_fcfa, max_weight_kg, max_length_cm,
                            max_width_cm, max_height_cm, max_girth_cm, max_volume_cm3,
                            valid_from, valid_until, notes, is_active)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)",
                        [
                            $lineId, (int)$id, json_encode($btData['nature_ids']), $btData['label'],
                            $btData['base_fee'], $btData['per_kg'], $btData['bracket_mode'],
                            $btData['vol_surch'], $btData['max_wt'], $btData['max_len'],
                            $btData['max_w'], $btData['max_h'], $btData['max_girth'],
                            $btData['max_vol'], $validFrom, $validUntil, $data['notes'],
                        ]
                    );
                    $this->saveBrackets((int)$btId, $brackets);
                }
            }
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                $this->flash('danger', 'Un tarif identique (même ligne, type, catégorie, classe et période) existe déjà.');
            } else {
                $this->flash('danger', $e->getMessage());
            }
            back(); return;
        }
        $this->flash('success', 'Tarif mis à jour.');
        redirect('referentiel/tariffs');
    }

    public function destroy(Request $request, string $id): void
    {
        $tariffId = (int)$id;
        Tariff::findOrFail($tariffId);

        // Vérifier si des billets bagage référencent les barèmes de ce tarif
        $usedBt = Database::selectOne(
            "SELECT COUNT(*) AS n
               FROM baggage_tickets bgt
               INNER JOIN baggage_tariffs bt ON bt.id = bgt.baggage_tariff_id
              WHERE bt.tariff_id = ?",
            [$tariffId]
        );
        if ((int)($usedBt['n'] ?? 0) > 0) {
            $this->flash('danger', 'Impossible de supprimer ce tarif : des billets bagage sont rattachés à ses barèmes. Désactivez-le plutôt.');
            redirect('referentiel/tariffs');
            return;
        }

        Tariff::delete($tariffId);
        $this->flash('success', 'Tarif supprimé.');
        redirect('referentiel/tariffs');
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────

    private function extractBagemeItem(array $item): ?array
    {
        $rawNatureIds = (array)($item['nature_ids'] ?? []);
        $natureIds    = array_values(array_unique(array_filter(
            array_map('intval', $rawNatureIds),
            fn($n) => $n > 0
        )));
        $label = trim((string)($item['label'] ?? ''));
        if (empty($natureIds) || $label === '' || mb_strlen($label) > 150) {
            return null;
        }
        $v = fn(string $k) => isset($item[$k]) && $item[$k] !== '' ? $item[$k] : null;
        return [
            'nature_ids'   => $natureIds,
            'label'        => $label,
            'base_fee'     => max(0, (int)($item['base_fee_fcfa'] ?? 0)),
            'per_kg'       => $v('per_kg_fcfa')          !== null ? max(0, (int)$v('per_kg_fcfa'))          : null,
            'bracket_mode' => (int)(($item['bracket_mode'] ?? '0') === '1'),
            'vol_surch'    => $v('volume_surcharge_fcfa') !== null ? max(0, (int)$v('volume_surcharge_fcfa')) : null,
            'max_wt'       => $v('max_weight_kg')         !== null ? (float)$v('max_weight_kg')              : null,
            'max_len'      => $v('max_length_cm')         !== null ? (int)$v('max_length_cm')                : null,
            'max_w'        => $v('max_width_cm')          !== null ? (int)$v('max_width_cm')                 : null,
            'max_h'        => $v('max_height_cm')         !== null ? (int)$v('max_height_cm')                : null,
            'max_girth'    => $v('max_girth_cm')          !== null ? (int)$v('max_girth_cm')                 : null,
            'max_vol'      => $v('max_volume_cm3')        !== null ? (int)$v('max_volume_cm3')               : null,
        ];
    }

    private function parseBracketsFromArray(array $raw): array
    {
        $brackets = [];
        foreach ($raw as $i => $b) {
            if (!is_array($b)) { continue; }
            $from  = isset($b['weight_from']) && $b['weight_from'] !== '' ? (float)$b['weight_from'] : null;
            $to    = isset($b['weight_to'])   && $b['weight_to']   !== '' ? (float)$b['weight_to']   : null;
            $price = isset($b['price'])       && $b['price']       !== '' ? max(0, (int)$b['price'])  : null;
            if ($from === null || $price === null) { continue; }
            $brackets[] = [
                'weight_from_kg' => $from,
                'weight_to_kg'   => $to,
                'price_fcfa'     => $price,
                'sort_order'     => $i + 1,
            ];
        }
        usort($brackets, fn($a, $b) => $a['weight_from_kg'] <=> $b['weight_from_kg']);
        return $brackets;
    }

    private function saveBrackets(int $btId, array $brackets): void
    {
        foreach ($brackets as $b) {
            Database::execute(
                "INSERT INTO baggage_tariff_brackets (baggage_tariff_id, weight_from_kg, weight_to_kg, price_fcfa, sort_order) VALUES (?,?,?,?,?)",
                [$btId, $b['weight_from_kg'], $b['weight_to_kg'], $b['price_fcfa'], $b['sort_order']]
            );
        }
    }

    /**
     * Remplace entièrement les services d'un tarif.
     * @param int[] $serviceIds IDs des services cochés (depuis le POST)
     */
    private function saveServiceMap(int $tariffId, mixed $serviceIds): void
    {
        Database::execute("DELETE FROM tariff_service_map WHERE tariff_id = ?", [$tariffId]);

        if (!is_array($serviceIds) || empty($serviceIds)) {
            return;
        }

        // Valider que les IDs existent dans tariff_services
        $validIds = array_column(
            Database::select("SELECT id FROM tariff_services WHERE id IN (" . implode(',', array_fill(0, count($serviceIds), '?')) . ")", array_map('intval', $serviceIds)),
            'id'
        );

        foreach ($validIds as $sid) {
            Database::execute(
                "INSERT IGNORE INTO tariff_service_map (tariff_id, service_id) VALUES (?,?)",
                [$tariffId, (int)$sid]
            );
        }
    }

    // ─── Référentiels partagés store/update ──────────────────────────────────

    /** Types de billets valides : slug => label. Lit la DB avec fallback codé en dur. */
    private function validTypes(): array
    {
        $types = Tariff::types();   // ['aller_simple' => 'Aller simple', ...]
        if (!empty($types)) return $types;
        // Fallback si la table tariff_ticket_types est vide
        return [
            'aller_simple' => 'Aller simple',
            'aller_retour' => 'Aller-retour',
            'abonnement'   => 'Abonnement mensuel',
            'groupe'       => 'Groupe / collectif',
        ];
    }

    /** Catégories passager valides : slug => label. */
    private function validCategories(): array
    {
        $cats = Tariff::passengerCategories();
        if (!empty($cats)) return $cats;
        return [
            'adulte'   => 'Adulte',
            'enfant'   => 'Enfant',
            'etudiant' => 'Étudiant',
            'senior'   => 'Senior',
            'vip'      => 'VIP',
        ];
    }

    /** Classes de voyage valides : slug => label. */
    private function validClasses(): array
    {
        $classes = Tariff::travelClasses();
        if (!empty($classes)) return $classes;
        return [
            'standard'   => 'Standard',
            'vip'        => 'VIP / Confort',
            'economique' => 'Économique',
        ];
    }

    /**
     * Charge tous les arrêts groupés par line_id pour le sélecteur Alpine.js du formulaire.
     * Retourne : [ line_id => [ ['id'=>…, 'name'=>…, 'order_position'=>…], … ], … ]
     */
    private function loadStopsByLine(): array
    {
        $rows = Database::select(
            "SELECT id, line_id, name, order_position FROM stops ORDER BY line_id ASC, order_position ASC"
        );
        $grouped = [];
        foreach ($rows as $s) {
            $grouped[(int)$s['line_id']][] = [
                'id'             => (int)$s['id'],
                'name'           => $s['name'],
                'order_position' => (int)$s['order_position'],
            ];
        }
        return $grouped;
    }

    /**
     * Formate une période (valid_from, valid_until) pour les messages d'erreur.
     */
    private function formatPeriod(?string $from, ?string $until): string
    {
        if (!$from && !$until) return 'permanent';
        $f = $from  ? date('d/m/Y', strtotime($from))  : '∞';
        $u = $until ? date('d/m/Y', strtotime($until)) : '∞';
        return "$f → $u";
    }

    /**
     * Vérifie que l'origine et la destination d'un tarif sont distinctes.
     *
     * Retourne un message d'erreur si elles se résolvent au même nom affiché,
     * null sinon. Couvre :
     *   • même ID de stop (les deux non-null et égaux)
     *   • stop nommé "X" en origine ET stop/terminus nommé "X" en destination
     *   • origine = ville de départ "X" et destination = terminus/ville arrivée "X"
     */
    private function checkSameOriginDest(int $lineId, ?int $originStopId, ?int $destStopId): ?string
    {
        // Cas rapide : même ID de stop
        if ($originStopId !== null && $destStopId !== null && $originStopId === $destStopId) {
            return "L'arrêt d'origine et l'arrêt de destination ne peuvent pas être identiques.";
        }

        // Résoudre le nom d'affichage de l'origine
        $originName = null;
        if ($originStopId !== null) {
            $row = Database::selectOne("SELECT name FROM stops WHERE id = ?", [$originStopId]);
            $originName = $row ? mb_strtolower(trim($row['name'])) : null;
        } else {
            $row = Database::selectOne(
                "SELECT c.name FROM bus_lines l
                   INNER JOIN cities c ON c.id = l.departure_city_id
                  WHERE l.id = ?",
                [$lineId]
            );
            $originName = $row ? mb_strtolower(trim($row['name'])) : null;
        }

        // Résoudre le nom d'affichage de la destination
        $destName = null;
        if ($destStopId !== null) {
            $row = Database::selectOne("SELECT name FROM stops WHERE id = ?", [$destStopId]);
            $destName = $row ? mb_strtolower(trim($row['name'])) : null;
        } else {
            // Terminus = dernier arrêt de la ligne par order_position
            $row = Database::selectOne(
                "SELECT name FROM stops WHERE line_id = ? ORDER BY order_position DESC LIMIT 1",
                [$lineId]
            );
            $destName = $row ? mb_strtolower(trim($row['name'])) : null;
            // Fallback : ville d'arrivée de la ligne
            if ($destName === null) {
                $row = Database::selectOne(
                    "SELECT c.name FROM bus_lines l
                       INNER JOIN cities c ON c.id = l.arrival_city_id
                      WHERE l.id = ?",
                    [$lineId]
                );
                $destName = $row ? mb_strtolower(trim($row['name'])) : null;
            }
        }

        if ($originName !== null && $destName !== null && $originName === $destName) {
            return "L'origine et la destination ont le même nom (\"" . ucfirst($originName) . "\"). "
                 . "Sélectionnez des arrêts distincts pour définir un segment tarifaire valide.";
        }

        return null;
    }
}


