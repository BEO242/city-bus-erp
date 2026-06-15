<?php

declare(strict_types=1);

namespace CityBus\Controllers\Api;

use CityBus\Controllers\Controller;
use CityBus\Core\Request;
use CityBus\Services\OAuthService;

final class OAuthController extends Controller
{
    /** POST /api/oauth/token (client_credentials) */
    public function token(Request $request): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $grantType    = $request->input('grant_type');
        $clientId     = $request->input('client_id');
        $clientSecret = $request->input('client_secret');
        $scope        = $request->input('scope', 'read');

        if ($grantType !== 'client_credentials') {
            http_response_code(400);
            echo json_encode(['error' => 'unsupported_grant_type']);
            exit;
        }
        if (!$clientId || !$clientSecret) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_request']);
            exit;
        }

        $token = (new OAuthService())->issueToken($clientId, $clientSecret, $scope);
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'invalid_client']);
            exit;
        }
        echo json_encode($token);
        exit;
    }
}
