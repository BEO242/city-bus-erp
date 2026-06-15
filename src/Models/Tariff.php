<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class Tariff extends BaseModel
{
    protected static string $table = 'tariffs';

    // Cache per-requete
    private static array $_ticketTypes          = [];
    private static array $_passengerCategories  = [];
    private static array $_travelClasses        = [];
    private static array $_baggageNatures       = [];
    private static array $_services             = [];

    private static function _loadTicketTypes(): void
    {
        if (empty(self::$_ticketTypes)) {
            $rows = Database::select(
                "SELECT slug, label, icon, color_class, description FROM tariff_ticket_types WHERE is_active=1 ORDER BY sort_order, id"
            );
            self::$_ticketTypes = array_column($rows, null, 'slug');
        }
    }

    private static function _loadPassengerCategories(): void
    {
        if (empty(self::$_passengerCategories)) {
            $rows = Database::select(
                "SELECT slug, label, icon, color_class, description FROM tariff_passenger_categories WHERE is_active=1 ORDER BY sort_order, id"
            );
            self::$_passengerCategories = array_column($rows, null, 'slug');
        }
    }

    private static function _loadTravelClasses(): void
    {
        if (empty(self::$_travelClasses)) {
            $rows = Database::select(
                "SELECT slug, label, icon, color_class, description FROM tariff_travel_classes WHERE is_active=1 ORDER BY sort_order, id"
            );
            self::$_travelClasses = array_column($rows, null, 'slug');
        }
    }

    private static function _loadBaggageNatures(): void
    {
        if (empty(self::$_baggageNatures)) {
            $rows = Database::select(
                "SELECT id, slug, label, icon, color_class, description FROM tariff_baggage_natures WHERE is_active=1 ORDER BY sort_order, id"
            );
            self::$_baggageNatures = array_column($rows, null, 'slug');
        }
    }

    private static function _loadServices(): void
    {
        if (empty(self::$_services)) {
            $rows = Database::select(
                "SELECT id, slug, label, icon, color_class, description FROM tariff_services WHERE is_active=1 ORDER BY sort_order, id"
            );
            self::$_services = array_column($rows, null, 'slug');
        }
    }

    // Types de billets

    public static function ticketTypesFull(): array
    {
        self::_loadTicketTypes();
        return self::$_ticketTypes;
    }

    public static function types(): array
    {
        self::_loadTicketTypes();
        return array_map(fn($r) => $r['label'], self::$_ticketTypes);
    }

    public static function typeIcons(): array
    {
        self::_loadTicketTypes();
        return array_map(fn($r) => $r['icon'], self::$_ticketTypes);
    }

    public static function typeColors(): array
    {
        self::_loadTicketTypes();
        return array_map(fn($r) => $r['color_class'], self::$_ticketTypes);
    }

    // Categories passagers

    public static function passengerCategoriesFull(): array
    {
        self::_loadPassengerCategories();
        return self::$_passengerCategories;
    }

    public static function passengerCategories(): array
    {
        self::_loadPassengerCategories();
        return array_map(fn($r) => $r['label'], self::$_passengerCategories);
    }

    public static function categoryIcons(): array
    {
        self::_loadPassengerCategories();
        return array_map(fn($r) => $r['icon'], self::$_passengerCategories);
    }

    public static function categoryColors(): array
    {
        self::_loadPassengerCategories();
        return array_map(fn($r) => $r['color_class'], self::$_passengerCategories);
    }

    // Classes de voyage

    public static function travelClassesFull(): array
    {
        self::_loadTravelClasses();
        return self::$_travelClasses;
    }

    public static function travelClasses(): array
    {
        self::_loadTravelClasses();
        return array_map(fn($r) => $r['label'], self::$_travelClasses);
    }

    public static function classIcons(): array
    {
        self::_loadTravelClasses();
        return array_map(fn($r) => $r['icon'], self::$_travelClasses);
    }

    public static function classColors(): array
    {
        self::_loadTravelClasses();
        return array_map(fn($r) => $r['color_class'], self::$_travelClasses);
    }

    // Natures de bagages

    public static function baggageNaturesFull(): array
    {
        self::_loadBaggageNatures();
        return self::$_baggageNatures;
    }

    public static function baggageNatures(): array
    {
        self::_loadBaggageNatures();
        return array_map(fn($r) => $r['label'], self::$_baggageNatures);
    }

    public static function baggageNaturesById(): array
    {
        self::_loadBaggageNatures();
        $result = [];
        foreach (self::$_baggageNatures as $slug => $row) {
            $result[(int)$row['id']] = $slug;
        }
        return $result;
    }

    // Services inclus

    public static function servicesFull(): array
    {
        self::_loadServices();
        return self::$_services;
    }

    public static function services(): array
    {
        self::_loadServices();
        return array_map(fn($r) => $r['label'], self::$_services);
    }

    public static function servicesForTariff(int $tariffId): array
    {
        return Database::select(
            "SELECT s.id, s.slug, s.label, s.icon, s.color_class
               FROM tariff_services s
               INNER JOIN tariff_service_map m ON m.service_id = s.id
              WHERE m.tariff_id = ?
              ORDER BY s.sort_order, s.id",
            [$tariffId]
        );
    }

    // Helpers

    public static function statusClass(int $isActive): string
    {
        return $isActive
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
            : 'bg-slate-100 text-slate-600 border-slate-200';
    }

    public static function validityStatus(array $tariff): string
    {
        $today = date('Y-m-d');
        $from  = $tariff['valid_from']  ?? null;
        $until = $tariff['valid_until'] ?? null;

        if (empty($from) && empty($until)) {
            return 'permanent';
        }
        if (!empty($from) && $from > $today) {
            return 'futur';
        }
        if (!empty($until) && $until < $today) {
            return 'expire';
        }
        return 'actif';
    }

    public static function validityClass(string $status): string
    {
        return match ($status) {
            'actif'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'expire' => 'bg-rose-50 text-rose-600 border-rose-200',
            'futur'  => 'bg-sky-50 text-sky-700 border-sky-200',
            default  => 'bg-slate-50 text-slate-600 border-slate-200',
        };
    }

    public static function validityLabel(string $status): string
    {
        return match ($status) {
            'actif'  => 'En cours',
            'expire' => 'Expire',
            'futur'  => 'A venir',
            default  => 'Permanent',
        };
    }

    public static function summary(array $tariff): string
    {
        $types = self::types();
        $cats  = self::passengerCategories();
        $clss  = self::travelClasses();

        $type  = $types[$tariff['ticket_type'] ?? ''] ?? $tariff['ticket_type'] ?? '';
        $catsArr = json_decode($tariff['passenger_categories'] ?? '["adulte"]', true) ?: ['adulte'];
        $catLabels = array_filter(array_map(fn($c) => $cats[$c] ?? '', $catsArr));
        $catStr = implode('/', $catLabels);
        $class = $clss[$tariff['travel_class'] ?? 'standard'] ?? '';

        $parts = array_filter([$type, $catStr !== 'Adulte' ? $catStr : '', $class !== 'Standard' ? $class : '']);
        return implode(' - ', $parts) ?: $type;
    }

    public static function alerts(array $tariff): array
    {
        $alerts   = [];
        $price    = (int)($tariff['price_fcfa'] ?? 0);
        $type     = (string)($tariff['ticket_type'] ?? '');
        $catsArr  = json_decode($tariff['passenger_categories'] ?? '["adulte"]', true) ?: ['adulte'];
        $cat      = $catsArr[0] ?? 'adulte';  // catégorie principale pour les comparaisons
        $class    = (string)($tariff['travel_class'] ?? 'standard');
        $lineId   = (int)($tariff['line_id'] ?? 0);
        $tariffId = (int)($tariff['id'] ?? 0);

        if ($price < 0) {
            $alerts[] = ['level' => 'danger', 'label' => 'Prix negatif', 'icon' => 'alert-triangle',
                         'detail' => 'Le prix ne peut pas etre negatif'];
        } elseif ($price === 0) {
            $alerts[] = ['level' => 'warn', 'label' => 'Prix a zero', 'icon' => 'alert-circle',
                         'detail' => 'Verifiez si ce tarif est intentionnellement gratuit'];
        }

        if (!empty($tariff['is_active']) && $lineId > 0) {
            $line = Database::selectOne("SELECT is_active FROM bus_lines WHERE id=?", [$lineId]);
            if ($line && empty($line['is_active'])) {
                $alerts[] = ['level' => 'warn', 'label' => 'Ligne desactivee', 'icon' => 'power-off',
                             'detail' => 'Le tarif est actif mais la ligne est desactivee'];
            }
        }

        $vs = self::validityStatus($tariff);
        if (!empty($tariff['is_active']) && $vs === 'expire') {
            $alerts[] = ['level' => 'warn', 'label' => 'Tarif expire', 'icon' => 'calendar-x',
                         'detail' => 'La date de fin est depassee - desactivez ce tarif'];
        }

        if (!empty($tariff['is_active']) && $lineId > 0 && $tariffId > 0) {
            // JSON_OVERLAPS n'existe pas en MariaDB < 10.9 — on charge les autres tarifs en PHP
            // On compare uniquement les tarifs qui ciblent le même destination_stop_id
            $destStopId = isset($tariff['destination_stop_id']) && $tariff['destination_stop_id'] !== null
                ? (int)$tariff['destination_stop_id'] : null;
            $destCond   = $destStopId === null ? 'AND destination_stop_id IS NULL' : 'AND destination_stop_id = ' . $destStopId;
            $others = Database::select(
                "SELECT passenger_categories FROM tariffs
                  WHERE line_id=? AND ticket_type=? AND travel_class=?
                    AND is_active=1 AND id<>? {$destCond}",
                [$lineId, $type, $class, $tariffId]
            );
            $hasOverlap = false;
            foreach ($others as $other) {
                $otherCats = json_decode($other['passenger_categories'] ?? '[]', true) ?: [];
                if (array_intersect($catsArr, $otherCats)) {
                    $hasOverlap = true;
                    break;
                }
            }
            if ($hasOverlap) {
                $alerts[] = ['level' => 'warn', 'label' => 'Tarif en doublon', 'icon' => 'copy',
                             'detail' => 'Un autre tarif actif couvre déjà une ou plusieurs de ces catégories'];
            }
        }

        if (!empty($tariff['is_active']) && $lineId > 0 && $price > 0) {
            $reducedCats = array_keys(array_filter(
                self::passengerCategories(),
                fn($slug) => !in_array($slug, ['adulte', 'groupe'], true),
                ARRAY_FILTER_USE_KEY
            ));
            if (in_array($cat, $reducedCats, true)) {
                $adulte = Database::selectOne(
                    "SELECT price_fcfa FROM tariffs
                     WHERE line_id=? AND ticket_type=? AND JSON_CONTAINS(passenger_categories, '\"adulte\"') AND travel_class=? AND is_active=1",
                    [$lineId, $type, $class]
                );
                if ($adulte && $price > (int)$adulte['price_fcfa']) {
                    $alerts[] = ['level' => 'info', 'label' => 'Tarif reduit > adulte', 'icon' => 'trending-up',
                                 'detail' => 'Ce tarif reduit est plus cher que le tarif adulte de reference'];
                }
            }
        }

        return $alerts;
    }

    /**
     * Résout LE tarif actif pour un périmètre donné à une date donnée.
     * Retourne null si aucun tarif applicable (ou ambigu — ce qui ne devrait
     * pas arriver grâce à la contrainte UNIQUE et au check de chevauchement).
     *
     * @return array|null Tarif complet (* + line_code/line_name)
     */
    public static function resolve(
        int $lineId,
        string $ticketType,
        string $passengerCategory = 'adulte',
        string $travelClass = 'standard',
        ?string $date = null,
        ?int $destinationStopId = null,
        ?int $originStopId = null
    ): ?array {
        $date = $date ?: date('Y-m-d');

        // Matching strict : on cherche EXACTEMENT le segment demandé.
        // NULL passé → le tarif en base doit aussi avoir NULL (pas d'arrêt spécifié).
        // Valeur passée → le tarif en base doit avoir exactement cet arrêt.
        // Aucun fallback implicite : si aucun tarif exact n'existe, resolve() retourne null
        // et l'appelant passera en saisie manuelle.
        $originClause = $originStopId !== null
            ? "AND t.origin_stop_id = ?"
            : "AND t.origin_stop_id IS NULL";

        $destClause = $destinationStopId !== null
            ? "AND t.destination_stop_id = ?"
            : "AND t.destination_stop_id IS NULL";

        $params = [$lineId, $ticketType, json_encode($passengerCategory), $travelClass, $date, $date];
        if ($originStopId      !== null) $params[] = $originStopId;
        if ($destinationStopId !== null) $params[] = $destinationStopId;

        $rows = Database::select(
            "SELECT t.*, l.code AS line_code, l.name AS line_name
               FROM tariffs t
               INNER JOIN bus_lines l ON l.id = t.line_id
              WHERE t.line_id = ?
                AND (t.ticket_type = '' OR t.ticket_type IS NULL OR t.ticket_type = ?)
                AND JSON_CONTAINS(t.passenger_categories, ?)
                AND (t.travel_class = '' OR t.travel_class IS NULL OR t.travel_class = ?)
                AND t.is_active = 1
                AND (t.valid_from  IS NULL OR t.valid_from  <= ?)
                AND (t.valid_until IS NULL OR t.valid_until >= ?)
                {$originClause}
                {$destClause}
              ORDER BY (t.ticket_type != '') DESC,
                       (t.travel_class != '') DESC,
                       (t.valid_from IS NOT NULL) DESC,
                       (t.valid_until IS NOT NULL) DESC,
                       t.id DESC
              LIMIT 1",
            $params
        );

        return $rows[0] ?? null;
    }

    /**
     * Vérifie qu'aucun autre tarif actif ne chevauche le périmètre + plage de dates.
     *
     * @param int|null $excludeId ID à exclure (utile pour update)
     * @return array|null Tarif en conflit s'il existe, null sinon
     */
    /**
     * Vérifie qu'aucun autre tarif actif ne chevauche le périmètre + plage de dates.
     *
     * Le paramètre $destinationStopId différencie un tarif "destination finale" (NULL)
     * d'un tarif "arrêt intermédiaire" (ID de l'arrêt). Deux tarifs ne peuvent se
     * chevaucher que s'ils ciblent le MÊME destination_stop_id.
     *
     * @param int|null $excludeId        ID à exclure (utile pour update)
     * @param int|null $destinationStopId NULL = destination finale, entier = arrêt en route
     * @return array|null Tarif en conflit s'il existe, null sinon
     */
    public static function overlapExists(
        int $lineId,
        string $ticketType,
        string $passengerCategory,
        string $travelClass,
        ?string $validFrom,
        ?string $validUntil,
        ?int $excludeId = null,
        ?int $originStopId = null,
        ?int $destinationStopId = null
    ): ?array {
        // Deux plages [a,b] et [c,d] se chevauchent ssi a<=d ET c<=b

        $sql = "SELECT id, valid_from, valid_until, price_fcfa, origin_stop_id, destination_stop_id
                  FROM tariffs
                 WHERE line_id = ?
                   AND (ticket_type = '' OR ticket_type IS NULL OR ticket_type = ?)
                   AND JSON_CONTAINS(passenger_categories, ?)
                   AND (travel_class = '' OR travel_class IS NULL OR travel_class = ?)
                   AND is_active = 1";
        $params = [$lineId, $ticketType, json_encode($passengerCategory), $travelClass];

        // Deux tarifs ne peuvent conflictuer que s'ils partagent le même segment origin→destination.
        if ($originStopId === null) {
            $sql .= " AND origin_stop_id IS NULL";
        } else {
            $sql .= " AND origin_stop_id = ?";
            $params[] = $originStopId;
        }
        if ($destinationStopId === null) {
            $sql .= " AND destination_stop_id IS NULL";
        } else {
            $sql .= " AND destination_stop_id = ?";
            $params[] = $destinationStopId;
        }

        if ($excludeId !== null) {
            $sql      .= " AND id <> ?";
            $params[] = $excludeId;
        }

        // newFrom <= existingUntil (existingUntil IS NULL = +inf)
        if ($validFrom !== null && $validFrom !== '') {
            $sql      .= " AND (valid_until IS NULL OR valid_until >= ?)";
            $params[] = $validFrom;
        }
        // existingFrom <= newUntil (newUntil IS NULL = +inf)
        if ($validUntil !== null && $validUntil !== '') {
            $sql      .= " AND (valid_from IS NULL OR valid_from <= ?)";
            $params[] = $validUntil;
        }

        $sql .= " LIMIT 1";

        return Database::selectOne($sql, $params) ?: null;
    }
}