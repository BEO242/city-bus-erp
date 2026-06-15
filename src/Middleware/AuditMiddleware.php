<?php

declare(strict_types=1);

namespace CityBus\Middleware;

use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

/**
 * Journalise automatiquement toutes les opérations d'écriture (POST/PUT/PATCH/DELETE).
 * Le log est effectué AVANT l'appel au handler car redirect()+exit empêche tout
 * code s'exécutant après.
 */
final class AuditMiddleware
{
    /** Routes à exclure du log automatique (AJAX utilitaires, exports…) */
    private const EXCLUDE_SUFFIXES = [
        '/test-smtp',
    ];

    /** Données POST à ne jamais logger */
    private const SENSITIVE_KEYS = [
        '_csrf', 'password', 'password_confirmation',
        'password_current', 'new_password', 'confirm_password', '_method',
    ];

    /** Mapping URI regex → [entity_name, capture_group_index|null] */
    private const ENTITY_MAP = [
        '#^/admin/users/(\d+)#'                => ['user',              1],
        '#^/admin/users$#'                     => ['user',              null],
        '#^/admin/roles/(\d+)#'                => ['role',              1],
        '#^/admin/roles$#'                     => ['role',              null],
        '#^/admin/settings#'                   => ['setting',           null],
        '#^/referentiel/agencies/(\d+)#'       => ['agency',            1],
        '#^/referentiel/agencies$#'            => ['agency',            null],
        '#^/referentiel/vehicules/(\d+)#'      => ['bus',               1],
        '#^/referentiel/vehicules$#'           => ['bus',               null],
        '#^/referentiel/lines/(\d+)#'          => ['bus_line',          1],
        '#^/referentiel/lines$#'               => ['bus_line',          null],
        '#^/referentiel/drivers/(\d+)#'        => ['driver',            1],
        '#^/referentiel/drivers$#'             => ['driver',            null],
        '#^/referentiel/tariffs/(\d+)#'        => ['tariff',            1],
        '#^/referentiel/tariffs$#'             => ['tariff',            null],
        '#^/referentiel/baggage-tariffs/(\d+)#'=> ['baggage_tariff',    1],
        '#^/referentiel/baggage-tariffs$#'     => ['baggage_tariff',    null],
        '#^/voyages/trips/(\d+)#'              => ['trip',              1],
        '#^/voyages/trips$#'                   => ['trip',              null],
        '#^/trips/(\d+)#'                      => ['trip',              1],
        '#^/trips$#'                           => ['trip',              null],
        '#^/rh/employees/(\d+)#'               => ['employee',          1],
        '#^/rh/employees$#'                    => ['employee',          null],
        '#^/rh/payroll#'                       => ['payroll',           null],
        '#^/flotte/maintenance/(\d+)#'         => ['maintenance_order', 1],
        '#^/flotte/maintenance$#'              => ['maintenance_order', null],
        '#^/flotte/fuel/(\d+)#'               => ['fuel_log',          1],
        '#^/flotte/fuel$#'                    => ['fuel_log',          null],
        '#^/caisse/close#'                     => ['cash_register',     null],
        '#^/caisse/open#'                      => ['cash_register',     null],
        '#^/caisse#'                           => ['cash_register',     null],
        '#^/billetterie/tickets/(\d+)#'        => ['ticket',            1],
        '#^/billetterie/tickets$#'             => ['ticket',            null],
        '#^/billetterie/bagages#'              => ['baggage_ticket',    null],
        '#^/controle#'                         => ['controle',          null],
        '#^/preprint#'                         => ['preprint_batch',    null],
    ];

    /** Mapping suffixe URI → verbe d'action */
    private const SUFFIX_ACTION_MAP = [
        '/delete'            => 'delete',
        '/toggle'            => 'toggle',
        '/status'            => 'status_change',
        '/reset'             => 'reset',
        '/cancel'            => 'cancel',
        '/close'             => 'close',
        '/open'              => 'open',
        '/unlock'            => 'unlock',
        '/reset-password'    => 'reset_password',
        '/reset-2fa'         => 'reset_2fa',
        '/paid'              => 'mark_paid',
        '/logo'              => 'upload_logo',
        '/import'            => 'import',
        '/run'               => 'run',
        '/validate'          => 'validate',
        '/pdf'               => null,   // GET-like operation even on POST, skip
        '/download'          => null,
        '/export'            => null,
    ];

    public function handle(Request $request, callable $next): mixed
    {
        $method = $request->method;

        // Seuls les mutants
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        // Utilisateur non connecté → pas de log utile (géré par AuthMiddleware)
        if (!Auth::check()) {
            return $next($request);
        }

        $path = $request->path;

        // Exclusions explicites
        foreach (self::EXCLUDE_SUFFIXES as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return $next($request);
            }
        }

        // Déterminer l'entité
        [$entity, $entityId] = $this->resolveEntity($path);

        // Déterminer l'action
        $action = $this->resolveAction($path, $entity, $request);

        // Ne pas logguer si action non déterminable ou skip explicite
        if ($action === null) {
            return $next($request);
        }

        // Construire les détails (données POST sanitisées)
        $details = $this->sanitize($request->all());

        // Logger AVANT le handler (redirect+exit empêche le code après)
        AuditLog::record($action, $entity, $entityId, $details);

        return $next($request);
    }

    /**
     * Résout l'entité métier à partir du chemin URI.
     * Retourne [entity_name|null, entity_id|null]
     *
     * @return array{0:string|null, 1:int|null}
     */
    private function resolveEntity(string $path): array
    {
        foreach (self::ENTITY_MAP as $pattern => [$entityName, $captureGroup]) {
            if (preg_match($pattern, $path, $matches)) {
                $entityId = ($captureGroup !== null && isset($matches[$captureGroup]))
                    ? (int)$matches[$captureGroup]
                    : null;
                return [$entityName, $entityId];
            }
        }
        return [null, null];
    }

    /**
     * Déduit le verbe de l'action (ex: "user.create", "trip.delete").
     */
    private function resolveAction(string $path, ?string $entity, Request $request): ?string
    {
        $entityPrefix = $entity ?? ltrim(explode('/', $path)[1] ?? 'unknown', '');

        // Tester les suffixes de chemin connus
        foreach (self::SUFFIX_ACTION_MAP as $suffix => $verb) {
            if (str_ends_with($path, $suffix)) {
                if ($verb === null) return null; // skip explicite
                return $entityPrefix . '.' . $verb;
            }
        }

        // _method override (PUT/PATCH simulé via POST form)
        $httpMethod = strtoupper($request->input('_method', $request->method));

        // Tenter de détecter create vs update depuis l'URI
        // Pattern: /entity/{id} → update ; /entity → create
        if (preg_match('#/(\d+)(/[a-z_-]+)?$#', $path, $m)) {
            // Il y a un ID en URI → update (sauf si suffix déjà traité)
            return $entityPrefix . '.update';
        }

        // Pas d'ID → create
        if (in_array($httpMethod, ['POST'], true)) {
            return $entityPrefix . '.create';
        }

        if (in_array($httpMethod, ['DELETE'], true)) {
            return $entityPrefix . '.delete';
        }

        if (in_array($httpMethod, ['PUT', 'PATCH'], true)) {
            return $entityPrefix . '.update';
        }

        return $entityPrefix . '.write';
    }

    /**
     * Retire les données sensibles du tableau POST.
     */
    private function sanitize(array $data): array
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            unset($data[$key]);
        }
        // Limiter la taille des valeurs longues (textarea, html...)
        foreach ($data as $k => $v) {
            if (is_string($v) && mb_strlen($v) > 500) {
                $data[$k] = mb_substr($v, 0, 500) . '…';
            }
        }
        return $data;
    }
}
