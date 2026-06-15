<?php

declare(strict_types=1);

namespace CityBus\Controllers\Admin;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;
use CityBus\Services\OAuthService;

final class ApiClientController extends Controller
{
    public function index(Request $request): void
    {
        $clients = Database::select(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM api_request_log WHERE client_id = c.id AND request_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS calls_24h
             FROM oauth_clients c ORDER BY c.is_active DESC, c.name"
        );
        $this->view('admin/api/index', [
            'title' => 'Clients API',
            'clients' => $clients,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('admin/api/form', ['title' => 'Nouveau client API']);
    }

    public function store(Request $request): void
    {
        if (!Auth::can('api.tokens.manage')) { back(); }
        $data = $this->validate($request, [
            'name' => 'required|min:3|max:120',
            'scopes' => 'required',
        ]);
        $r = (new OAuthService())->createClient(
            $data['name'],
            (string)$data['scopes'],
            $request->input('description'),
            $request->input('rate_limit_per_min') ? (int)$request->input('rate_limit_per_min') : null
        );
        AuditLog::record('api_client.create', null, null, ['client_id' => $r['client_id']]);
        $this->view('admin/api/show_credentials', [
            'title' => 'Identifiants client API',
            'client_id' => $r['client_id'],
            'client_secret' => $r['client_secret'],
        ]);
    }

    public function revoke(Request $request, string $id): void
    {
        if (!Auth::can('api.tokens.manage')) { back(); }
        Database::execute("UPDATE oauth_clients SET is_active = 0 WHERE id = ?", [(int)$id]);
        Database::execute("UPDATE oauth_access_tokens SET revoked = 1 WHERE client_id = ?", [(int)$id]);
        AuditLog::record('api_client.revoke', null, null, ['id' => (int)$id]);
        $this->flash('success', 'Client révoqué.');
        back();
    }
}
