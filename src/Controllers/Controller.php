<?php

declare(strict_types=1);

namespace CityBus\Controllers;

use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Core\Session;
use CityBus\Core\View;
use CityBus\Core\Validator;
use CityBus\Core\ValidationException;

abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        echo (new View())->render($template, $data);
    }

    protected function validate(Request $request, array $rules, array $labels = []): array
    {
        try {
            return Validator::make($request->all(), $rules, [], $labels)->validate();
        } catch (ValidationException $e) {
            // Persister entrées et erreurs
            Session::set('_errors', $e->errors);
            Session::set('_old', $request->all());
            \CityBus\Core\Logger::warning('Validation failed in ' . static::class . ' ' . $_SERVER['REQUEST_URI'] . ': ' . json_encode($e->errors));
            if ($request->isAjax()) {
                Response::json(['errors' => $e->errors], 422);
            }
            // Flash visible côté UI : compile la première erreur de chaque champ
            $first = [];
            foreach ($e->errors as $field => $msgs) {
                $first[] = is_array($msgs) ? ($msgs[0] ?? '') : (string)$msgs;
            }
            $summary = implode(' • ', array_filter($first));
            Session::flash('danger', 'Formulaire invalide : ' . ($summary !== '' ? $summary : 'veuillez vérifier les champs.'));
            back();
        }
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function json(mixed $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    /**
     * Vérifie une permission. Affiche 403 et stoppe l'exécution si refusée.
     */
    protected function authorize(string $permission): void
    {
        Auth::authorize($permission);
    }

    /**
     * Vérifie qu'un utilisateur peut accéder à une ressource liée à une agence.
     * - admin / raf / exploitation : accès global
     * - autres rôles : doivent appartenir à la même agence
     */
    protected function requireAgencyAccess(?int $agencyId): void
    {
        if ($agencyId === null || $agencyId === 0) {
            return; // ressources non scopées
        }
        $user = Auth::user();
        if (!$user) {
            http_response_code(403);
            echo (new View())->render('errors/403', ['permission' => 'agency.access']);
            exit;
        }
        $globalRoles = ['admin', 'raf', 'exploitation'];
        $role = $user['role_slug'] ?? $user['role'] ?? null;
        if (in_array($role, $globalRoles, true)) {
            return;
        }
        $userAgencyId = isset($user['agency_id']) ? (int)$user['agency_id'] : 0;
        if ($userAgencyId !== (int)$agencyId) {
            http_response_code(403);
            echo (new View())->render('errors/403', ['permission' => 'agency.access']);
            exit;
        }
    }
}
