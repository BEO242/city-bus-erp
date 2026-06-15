<?php

declare(strict_types=1);

namespace CityBus\Controllers\Referentiel;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\BaggageTariff;
use CityBus\Models\Tariff;

final class BaggageTariffController extends Controller
{
    // ─── index : redirige vers la page unifiée (onglet bagage) ──────────────
    public function index(Request $request): void
    {
        // Transmettre les filtres éventuels vers la page unifiée
        $params = array_filter([
            'tab'          => 'bagage',
            'bt_line_id'   => $request->input('line_id',   '') ?: '',
            'bt_nature_id' => $request->input('nature_id', '') ?: '',
            'bt_status'    => $request->input('status',    '') ?: '',
        ], fn($v) => $v !== '');

        $qs = $params ? '?' . http_build_query($params) : '?tab=bagage';
        header('Location: ' . url('referentiel/tariffs') . $qs, true, 302);
        exit;
    }

    // ─── create ───────────────────────────────────────────────────────────────
    public function create(Request $request): void
    {
        header('Location: ' . url('referentiel/tariffs/create'), true, 302);
        exit;
    }

    // ─── store ────────────────────────────────────────────────────────────────
    public function store(Request $request): void
    {
        $data     = $this->validateForm($request);
        $brackets = $this->parseBrackets($request);

        if ($data['bracket_mode'] && empty($brackets)) {
            $this->flash('danger', 'Le mode tranches requiert au moins une tranche de poids.');
            back(); return;
        }

        // Anti-chevauchement
        $conflict = BaggageTariff::overlapExists(
            (int)$data['line_id'],
            (int)$data['baggage_nature_id'],
            $data['valid_from'],
            $data['valid_until']
        );
        if ($conflict) {
            $period = $this->formatPeriod($conflict['valid_from'] ?? null, $conflict['valid_until'] ?? null);
            $this->flash('danger',
                "Un tarif bagage actif existe déjà pour cette ligne et cette nature ({$period}). " .
                "Désactivez-le ou ajustez les périodes de validité.");
            back(); return;
        }

        try {
            $id = Database::insert(
                "INSERT INTO baggage_tariffs
                   (line_id, baggage_nature_ids, label, base_fee_fcfa, per_kg_fcfa, bracket_mode,
                    volume_surcharge_fcfa, max_weight_kg, max_length_cm, max_width_cm, max_height_cm,
                    max_girth_cm, valid_from, valid_until, notes, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)",
                [
                    $data['line_id'], json_encode([$data['baggage_nature_id']]), $data['label'],
                    $data['base_fee_fcfa'], $data['per_kg_fcfa'], $data['bracket_mode'],
                    $data['volume_surcharge_fcfa'], $data['max_weight_kg'],
                    $data['max_length_cm'], $data['max_width_cm'], $data['max_height_cm'],
                    $data['max_girth_cm'], $data['valid_from'], $data['valid_until'], $data['notes'],
                ]
            );
            $this->saveBrackets((int)$id, $brackets);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                $this->flash('danger', 'Un tarif bagage actif existe déjà pour cette ligne et cette nature de bagage.');
            } else {
                $this->flash('danger', $e->getMessage());
            }
            back(); return;
        }

        $this->flash('success', 'Tarif bagage créé.');
        redirect('referentiel/tariffs?tab=bagage');
    }

    // ─── edit ─────────────────────────────────────────────────────────────────
    public function edit(Request $request, string $id): void
    {
        $bt = BaggageTariff::findOrFail((int)$id);
        if (!empty($bt['tariff_id'])) {
            header('Location: ' . url('referentiel/tariffs/' . (int)$bt['tariff_id'] . '/edit'), true, 302);
        } else {
            $this->flash('danger', "Ce tarif bagage n'est pas encore lié à un tarif passager. Créez un nouveau tarif unifié.");
            header('Location: ' . url('referentiel/tariffs'), true, 302);
        }
        exit;
    }

    // ─── update ───────────────────────────────────────────────────────────────
    public function update(Request $request, string $id): void
    {
        BaggageTariff::findOrFail((int)$id); // 404 si inexistant
        $data     = $this->validateForm($request);
        $brackets = $this->parseBrackets($request);

        if ($data['bracket_mode'] && empty($brackets)) {
            $this->flash('danger', 'Le mode tranches requiert au moins une tranche de poids.');
            back(); return;
        }

        $isActive = (int)$request->input('is_active', 0);
        if ($isActive === 1) {
            $conflict = BaggageTariff::overlapExists(
                (int)$data['line_id'],
                (int)$data['baggage_nature_id'],
                $data['valid_from'],
                $data['valid_until'],
                (int)$id
            );
            if ($conflict) {
                $period = $this->formatPeriod($conflict['valid_from'] ?? null, $conflict['valid_until'] ?? null);
                $this->flash('danger',
                    "Un autre tarif bagage actif couvre déjà ce périmètre ({$period}). " .
                    "Désactivez-le ou ajustez les périodes de validité.");
                back(); return;
            }
        }

        try {
            Database::execute(
                "UPDATE baggage_tariffs
                 SET line_id=?, baggage_nature_ids=?, label=?, base_fee_fcfa=?, per_kg_fcfa=?,
                     bracket_mode=?, volume_surcharge_fcfa=?, max_weight_kg=?, max_length_cm=?,
                     max_width_cm=?, max_height_cm=?, max_girth_cm=?, valid_from=?, valid_until=?,
                     notes=?, is_active=?
                 WHERE id=?",
                [
                    $data['line_id'], json_encode([$data['baggage_nature_id']]), $data['label'],
                    $data['base_fee_fcfa'], $data['per_kg_fcfa'], $data['bracket_mode'],
                    $data['volume_surcharge_fcfa'], $data['max_weight_kg'],
                    $data['max_length_cm'], $data['max_width_cm'], $data['max_height_cm'],
                    $data['max_girth_cm'], $data['valid_from'], $data['valid_until'], $data['notes'],
                    $isActive, (int)$id,
                ]
            );
            // Remplacer les tranches
            Database::execute("DELETE FROM baggage_tariff_brackets WHERE baggage_tariff_id=?", [(int)$id]);
            $this->saveBrackets((int)$id, $brackets);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                $this->flash('danger', 'Un tarif bagage actif existe déjà pour cette ligne et cette nature de bagage.');
            } else {
                $this->flash('danger', $e->getMessage());
            }
            back(); return;
        }

        $this->flash('success', 'Tarif bagage mis à jour.');
        redirect('referentiel/tariffs?tab=bagage');
    }

    // ─── destroy ──────────────────────────────────────────────────────────────
    public function destroy(Request $request, string $id): void
    {
        $btId = (int)$id;
        BaggageTariff::findOrFail($btId);

        // Vérifier si des billets bagage référencent ce barème
        $used = Database::selectOne(
            "SELECT COUNT(*) AS n FROM baggage_tickets WHERE baggage_tariff_id = ?",
            [$btId]
        );
        if ((int)($used['n'] ?? 0) > 0) {
            $this->flash('danger', 'Impossible de supprimer ce barème : des billets bagage y sont rattachés. Désactivez-le plutôt.');
            redirect('referentiel/tariffs?tab=bagage');
            return;
        }

        // Les tranches sont supprimées par CASCADE
        BaggageTariff::delete($btId);
        $this->flash('success', 'Tarif bagage supprimé.');
        redirect('referentiel/tariffs?tab=bagage');
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────

    private function validateForm(Request $request): array
    {
        $lineId    = (int)$request->input('line_id', 0);
        $natureId  = (int)$request->input('baggage_nature_id', 0);
        $label     = trim((string)$request->input('bg_label', ''));
        $baseFee   = max(0, (int)$request->input('base_fee_fcfa', 0));
        $perKg     = $request->input('per_kg_fcfa') !== '' ? max(0, (int)$request->input('per_kg_fcfa', 0)) : null;
        $bracketMode = (int)($request->input('bracket_mode', 0) == '1');
        $volSurch  = $request->input('volume_surcharge_fcfa') !== '' ? max(0, (int)$request->input('volume_surcharge_fcfa', 0)) : null;
        $maxWt     = $request->input('max_weight_kg')   !== '' ? (float)$request->input('max_weight_kg')   : null;
        $maxLen    = $request->input('max_length_cm')   !== '' ? (int)$request->input('max_length_cm')     : null;
        $maxW      = $request->input('max_width_cm')    !== '' ? (int)$request->input('max_width_cm')      : null;
        $maxH      = $request->input('max_height_cm')   !== '' ? (int)$request->input('max_height_cm')     : null;
        $maxGirth  = $request->input('max_girth_cm')    !== '' ? (int)$request->input('max_girth_cm')      : null;
        $validFrom  = trim((string)$request->input('valid_from', ''))  ?: null;
        $validUntil = trim((string)$request->input('valid_until', '')) ?: null;
        $notes      = trim((string)$request->input('notes', '')) ?: null;

        if ($lineId <= 0) {
            $this->flash('danger', 'Veuillez sélectionner une ligne.');
            back(); exit;
        }
        if ($natureId <= 0) {
            $this->flash('danger', 'Veuillez sélectionner une nature de bagage.');
            back(); exit;
        }
        if ($label === '' || mb_strlen($label) > 150) {
            $this->flash('danger', 'Le libellé est requis (max 150 caractères).');
            back(); exit;
        }
        if (!$bracketMode && $perKg === null) {
            $this->flash('danger', 'Indiquez un prix par kg, ou activez le mode tranches.');
            back(); exit;
        }
        if ($validFrom && $validUntil && $validUntil < $validFrom) {
            $this->flash('danger', 'La date de fin ne peut pas être antérieure à la date de début.');
            back(); exit;
        }

        return compact(
            'lineId', 'natureId', 'label', 'baseFee', 'perKg', 'bracketMode',
            'volSurch', 'maxWt', 'maxLen', 'maxW', 'maxH', 'maxGirth',
            'validFrom', 'validUntil', 'notes'
        ) + [
            'line_id'              => $lineId,
            'baggage_nature_id'    => $natureId,
            'base_fee_fcfa'        => $baseFee,
            'per_kg_fcfa'          => $perKg,
            'bracket_mode'         => $bracketMode,
            'volume_surcharge_fcfa'=> $volSurch,
            'max_weight_kg'        => $maxWt,
            'max_length_cm'        => $maxLen,
            'max_width_cm'         => $maxW,
            'max_height_cm'        => $maxH,
            'max_girth_cm'         => $maxGirth,
            'valid_from'           => $validFrom,
            'valid_until'          => $validUntil,
        ];
    }

    /**
     * Parse les tranches de poids depuis le POST.
     * Les champs sont : brackets[0][weight_from], brackets[0][weight_to], brackets[0][price]
     * @return array<int,array{weight_from_kg,weight_to_kg,price_fcfa,sort_order}>
     */
    private function parseBrackets(Request $request): array
    {
        $raw = $request->input('brackets', []);
        if (!is_array($raw)) {
            return [];
        }

        $brackets = [];
        foreach ($raw as $i => $b) {
            $from  = isset($b['weight_from']) && $b['weight_from'] !== '' ? (float)$b['weight_from'] : null;
            $to    = isset($b['weight_to'])   && $b['weight_to']   !== '' ? (float)$b['weight_to']   : null;
            $price = isset($b['price'])       && $b['price']       !== '' ? max(0, (int)$b['price'])  : null;

            if ($from === null || $price === null) {
                continue; // ignorer les lignes incomplètes
            }
            $brackets[] = [
                'weight_from_kg' => $from,
                'weight_to_kg'   => $to,
                'price_fcfa'     => $price,
                'sort_order'     => $i + 1,
            ];
        }

        // Trier par poids croissant
        usort($brackets, fn($a, $b) => $a['weight_from_kg'] <=> $b['weight_from_kg']);

        return $brackets;
    }

    private function saveBrackets(int $tariffId, array $brackets): void
    {
        foreach ($brackets as $b) {
            Database::execute(
                "INSERT INTO baggage_tariff_brackets (baggage_tariff_id, weight_from_kg, weight_to_kg, price_fcfa, sort_order)
                 VALUES (?,?,?,?,?)",
                [$tariffId, $b['weight_from_kg'], $b['weight_to_kg'], $b['price_fcfa'], $b['sort_order']]
            );
        }
    }

    private function formatPeriod(?string $from, ?string $until): string
    {
        if (!$from && !$until) return 'permanent';
        $f = $from  ? date('d/m/Y', strtotime($from))  : '∞';
        $u = $until ? date('d/m/Y', strtotime($until)) : '∞';
        return "$f → $u";
    }
}
