<?php

declare(strict_types=1);

namespace CityBus\Controllers\Admin;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Models\AuditLog;

final class NotificationTemplateController extends Controller
{
    public function index(Request $request): void
    {
        $templates = Database::select("SELECT * FROM notification_templates ORDER BY template_key, channel");
        $this->view('admin/notifications/index', [
            'title' => 'Templates de notifications',
            'templates' => $templates,
        ]);
    }

    public function edit(Request $request, string $id): void
    {
        $tpl = Database::selectOne("SELECT * FROM notification_templates WHERE id=?", [(int)$id]);
        if (!$tpl) { $this->flash('danger', 'Introuvable.'); redirect('admin/notifications'); }
        $this->view('admin/notifications/form', ['title' => 'Modifier ' . $tpl['template_key'], 'template' => $tpl]);
    }

    public function update(Request $request, string $id): void
    {
        if (!Auth::can('notifications.manage')) { $this->flash('danger', 'Permission refusée.'); back(); }
        $data = $this->validate($request, [
            'label'   => 'required|min:3|max:120',
            'subject' => 'max:200',
            'body'    => 'required|min:5',
        ]);
        Database::execute(
            "UPDATE notification_templates
                SET label=?, subject=?, body=?,
                    is_active=?, version = version + 1
              WHERE id=?",
            [
                $data['label'],
                $data['subject'] ?? null,
                $data['body'],
                (int)$request->input('is_active', 0),
                (int)$id,
            ]
        );
        AuditLog::record('notif_template.update', 'notification_template', (int)$id);
        $this->flash('success', 'Template mis à jour.');
        redirect('admin/notifications');
    }

    /** Test d'un template (substitution avec données fictives). */
    public function preview(Request $request, string $id): void
    {
        $tpl = Database::selectOne("SELECT * FROM notification_templates WHERE id=?", [(int)$id]);
        if (!$tpl) { $this->json(['error' => 'Introuvable']); return; }
        $vars = (array)$request->input('vars', []);
        if (empty($vars)) {
            // Variables démo
            $declared = json_decode((string)($tpl['variables'] ?? '[]'), true) ?: [];
            foreach ($declared as $v) $vars[$v] = '[' . strtoupper($v) . ']';
        }
        $svc = new \CityBus\Services\NotificationService();
        $this->json([
            'subject' => $tpl['subject'] ? $svc->substitute($tpl['subject'], $vars) : null,
            'body'    => $svc->substitute($tpl['body'], $vars),
        ]);
    }

    public function logs(Request $request): void
    {
        $logs = Database::select(
            "SELECT * FROM notification_log ORDER BY created_at DESC LIMIT 200"
        );
        $this->view('admin/notifications/logs', [
            'title' => 'Historique notifications',
            'logs'  => $logs,
        ]);
    }
}
