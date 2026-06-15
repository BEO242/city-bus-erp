<?php
/**
 * Synchronisation bagages → fret_items
 * ─────────────────────────────────────
 * Crée des fret_items (type "baggage") pour tous les bagages qui n'en ont pas encore.
 *
 * Sources synchronisées :
 *   1. baggage_tickets (billets bagage excédentaires)
 *   2. luggage_tags    (tags bagages simples liés aux tickets passagers)
 *
 * Usage CLI :
 *   php database/sync_baggage_to_fret.php
 *   php database/sync_baggage_to_fret.php --dry-run        (simulation, aucune écriture)
 *   php database/sync_baggage_to_fret.php --category=colis (forcer une catégorie fret)
 *
 * Comportement par défaut :
 *   - Franchise (luggage_tags.type = 'franchise', prix = 0) → is_franchise = 1, total = 0
 *   - Excédent  (luggage_tags.type = 'excedent'           ) → is_franchise = 0, total = price_fcfa
 *   - baggage_tickets                                       → is_franchise = 0 (toujours excédentaires)
 *   - Catégorie fret : meilleure correspondance par prix/kg, ou première catégorie active
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────
$root = dirname(__DIR__);
define('BASE_PATH', $root);
require_once $root . '/vendor/autoload.php';

// Désactiver l'ErrorHandler HTML (incompatible CLI) avant le bootstrap
define('CITYBUS_CLI', true);
require_once $root . '/src/bootstrap.php';

use CityBus\Core\Database;
use CityBus\Models\FretItem;

// ── Arguments CLI ─────────────────────────────────────────────────────────────
$dryRun          = in_array('--dry-run', $argv ?? [], true);
$forcedCategory  = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--category=')) {
        $forcedCategory = trim(substr($arg, strlen('--category=')));
    }
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function log_line(string $msg, string $level = 'INFO'): void
{
    $ts     = date('H:i:s');
    $colors = ['INFO' => "\033[0m", 'OK' => "\033[32m", 'SKIP' => "\033[33m",
                'ERR' => "\033[31m", 'DRY' => "\033[36m", 'HEAD' => "\033[1m"];
    $col    = $colors[$level] ?? "\033[0m";
    echo "{$col}[{$ts}] [{$level}] {$msg}\033[0m\n";
}

function generate_tracking(): string
{
    return FretItem::generateTrackingCode();
}

// Mappe un status baggage_ticket → fret_item
function map_status(string $bt): string
{
    return match ($bt) {
        'arrive'                    => 'retire',
        'annule'                    => 'annule',
        'embarque', 'valide'        => 'charge',
        default                     => 'enregistre', // emis
    };
}

// Choisit la meilleure catégorie fret selon le prix/kg
function best_fret_category(array $cats, int $pricePerKg): array
{
    if (empty($cats)) throw new \RuntimeException('Aucune catégorie fret active.');
    usort($cats, fn($a, $b) =>
        abs((int)$a['price_per_kg'] - $pricePerKg) - abs((int)$b['price_per_kg'] - $pricePerKg)
    );
    return $cats[0];
}

// ── Chargement des catégories fret ──────────────────────────────────────────
$fretCats = Database::select(
    "SELECT slug, label, price_per_kg, min_price_fcfa FROM fret_categories
      WHERE is_active = 1 ORDER BY sort_order"
);

if (empty($fretCats)) {
    log_line('Aucune catégorie fret active configurée — impossible de synchroniser.', 'ERR');
    exit(1);
}

// Catégorie forcée ?
$defaultCat = null;
if ($forcedCategory !== null) {
    $defaultCat = array_values(array_filter($fretCats, fn($c) => $c['slug'] === $forcedCategory))[0] ?? null;
    if (!$defaultCat) {
        log_line("Catégorie forcée '{$forcedCategory}' introuvable ou inactive.", 'ERR');
        exit(1);
    }
    log_line("Catégorie forcée : {$defaultCat['label']} ({$defaultCat['slug']})", 'INFO');
}

// ── Counters ─────────────────────────────────────────────────────────────────
$created  = 0;
$skipped  = 0;
$errors   = 0;

log_line('═══════════════════════════════════════════════════════', 'HEAD');
log_line(' SYNCHRONISATION BAGAGES → FRET_ITEMS', 'HEAD');
log_line(' Mode : ' . ($dryRun ? 'SIMULATION (--dry-run)' : 'ÉCRITURE RÉELLE'), 'HEAD');
log_line('═══════════════════════════════════════════════════════', 'HEAD');

// ════════════════════════════════════════════════════════════════════════════
// SOURCE 1 : baggage_tickets (billets bagage excédentaires)
// ════════════════════════════════════════════════════════════════════════════
log_line('── Source 1 : baggage_tickets ──────────────────────────', 'HEAD');

$orphanBt = Database::select(
    "SELECT bt.*
       FROM baggage_tickets bt
      WHERE bt.deleted_at IS NULL
        AND NOT EXISTS (
            SELECT 1 FROM fret_items fi
             WHERE fi.item_type = 'baggage'
               AND fi.passenger_ticket_id = bt.passenger_ticket_id
               AND fi.trip_id             = bt.trip_id
        )
      ORDER BY bt.id"
);

log_line(count($orphanBt) . ' billet(s) bagage sans fret_item trouvé(s).');

foreach ($orphanBt as $bt) {
    // Sélectionner la meilleure catégorie fret par correspondance de prix
    $pricePerKg = $bt['weight_kg'] > 0
        ? (int)round((int)$bt['total_price_fcfa'] / (float)$bt['weight_kg'])
        : 0;

    try {
        $cat = $defaultCat ?? best_fret_category($fretCats, $pricePerKg);

        // Statut fret
        $fretStatus  = map_status($bt['status']);
        $trackingCode = generate_tracking();
        $weightKg    = max(0, (float)$bt['weight_kg']);

        // Prix fret = total_price_fcfa (déjà calculé à la vente)
        $totalFret = (int)$bt['total_price_fcfa'];

        if ($dryRun) {
            log_line(
                "DRY baggage_ticket#{$bt['id']} → {$trackingCode} cat={$cat['slug']} " .
                "weight={$weightKg}kg status={$fretStatus} total={$totalFret}F",
                'DRY'
            );
            $created++;
            continue;
        }

        Database::insert(
            "INSERT INTO fret_items (
                tracking_code, item_type, category_slug, trip_id, passenger_ticket_id,
                sender_name, sender_phone, recipient_name, recipient_phone,
                weight_kg, pieces_count, description, is_franchise,
                price_per_kg, min_price_fcfa, total_price_fcfa,
                origin_agency_id, destination_agency_id, status,
                agency_id, registered_by, cash_register_id, created_at, updated_at
            ) VALUES (
                ?, 'baggage', ?, ?, ?,
                ?, ?, '', '',
                ?, 1, ?, 0,
                ?, ?, ?,
                NULL, NULL, ?,
                ?, ?, ?, ?, NOW()
            )",
            [
                $trackingCode,
                $cat['slug'],
                (int)$bt['trip_id'],
                $bt['passenger_ticket_id'] ? (int)$bt['passenger_ticket_id'] : null,

                $bt['passenger_name'],
                $bt['passenger_phone'] ?? null,

                $weightKg,
                $bt['description'] ?? null,

                (int)$cat['price_per_kg'],
                (int)$cat['min_price_fcfa'],
                $totalFret,

                $fretStatus,
                (int)$bt['agency_id'],
                (int)$bt['sold_by'],
                $bt['cash_register_id'] ?? null,
                $bt['sold_at'],   // preserve original date
            ]
        );

        log_line(
            "OK baggage_ticket#{$bt['id']} → {$trackingCode} [{$cat['slug']}] " .
            "{$weightKg}kg {$totalFret}F [{$fretStatus}]",
            'OK'
        );
        $created++;

    } catch (\Throwable $e) {
        log_line("ERR baggage_ticket#{$bt['id']} : " . $e->getMessage(), 'ERR');
        $errors++;
    }
}

// ════════════════════════════════════════════════════════════════════════════
// SOURCE 2 : luggage_tags (bagages simples franchise / excédent)
// ════════════════════════════════════════════════════════════════════════════
log_line('── Source 2 : luggage_tags ─────────────────────────────', 'HEAD');

$orphanLt = Database::select(
    "SELECT lt.*, tk.trip_id, tk.passenger_name, tk.passenger_phone,
            tk.agency_id, tk.sold_by, tk.sold_at, NULL AS cash_register_id
       FROM luggage_tags lt
       JOIN tickets tk ON tk.id = lt.ticket_id AND tk.deleted_at IS NULL
      WHERE NOT EXISTS (
          SELECT 1 FROM fret_items fi
           WHERE fi.item_type        = 'baggage'
             AND fi.passenger_ticket_id = lt.ticket_id
      )
      ORDER BY lt.id"
);

log_line(count($orphanLt) . ' luggage_tag(s) sans fret_item trouvé(s).');

foreach ($orphanLt as $lt) {
    $isFranchise = ($lt['type'] === 'franchise') ? 1 : 0;
    $weightKg    = max(0, (float)$lt['weight_kg']);

    // Prix fret : 0 si franchise, sinon price_fcfa du tag
    $totalFret = $isFranchise ? 0 : (int)$lt['price_fcfa'];

    $pricePerKg = (!$isFranchise && $weightKg > 0)
        ? (int)round($totalFret / $weightKg)
        : 0;

    try {
        $cat          = $defaultCat ?? best_fret_category($fretCats, $pricePerKg);
        $trackingCode = generate_tracking();

        if ($dryRun) {
            log_line(
                "DRY luggage_tag#{$lt['id']} ticket#{$lt['ticket_id']} " .
                "type={$lt['type']} cat={$cat['slug']} {$weightKg}kg total={$totalFret}F",
                'DRY'
            );
            $created++;
            continue;
        }

        Database::insert(
            "INSERT INTO fret_items (
                tracking_code, item_type, category_slug, trip_id, passenger_ticket_id,
                sender_name, sender_phone, recipient_name, recipient_phone,
                weight_kg, pieces_count, description, is_franchise,
                price_per_kg, min_price_fcfa, total_price_fcfa,
                origin_agency_id, destination_agency_id, status,
                agency_id, registered_by, cash_register_id, created_at, updated_at
            ) VALUES (
                ?, 'baggage', ?, ?, ?,
                ?, ?, '', '',
                ?, 1, NULL, ?,
                ?, ?, ?,
                NULL, NULL, 'enregistre',
                ?, ?, ?, ?, NOW()
            )",
            [
                $trackingCode,
                $cat['slug'],
                (int)$lt['trip_id'],
                (int)$lt['ticket_id'],

                $lt['passenger_name'],
                $lt['passenger_phone'] ?? null,

                $weightKg,
                $isFranchise,

                (int)$cat['price_per_kg'],
                (int)$cat['min_price_fcfa'],
                $totalFret,

                (int)$lt['agency_id'],
                (int)$lt['sold_by'],
                $lt['cash_register_id'] ?? null,
                $lt['created_at'],
            ]
        );

        log_line(
            "OK luggage_tag#{$lt['id']} ticket#{$lt['ticket_id']} → {$trackingCode} " .
            "[{$cat['slug']}] {$lt['type']} {$weightKg}kg {$totalFret}F",
            'OK'
        );
        $created++;

    } catch (\Throwable $e) {
        log_line("ERR luggage_tag#{$lt['id']} : " . $e->getMessage(), 'ERR');
        $errors++;
    }
}

// ── Résumé ───────────────────────────────────────────────────────────────────
log_line('═══════════════════════════════════════════════════════', 'HEAD');
log_line(sprintf(
    'Terminé — %d créé(s)  %d ignoré(s)  %d erreur(s)%s',
    $created, $skipped, $errors,
    $dryRun ? '  [SIMULATION — aucune écriture]' : ''
), $errors > 0 ? 'ERR' : 'OK');
log_line('═══════════════════════════════════════════════════════', 'HEAD');

exit($errors > 0 ? 1 : 0);
