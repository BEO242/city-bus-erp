<?php

declare(strict_types=1);

namespace CityBus\Controllers\Admin;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Core\Response;
use CityBus\Services\BackupService;

final class BackupController extends Controller
{
    public function __construct(private BackupService $service = new BackupService()) {}

    public function index(Request $request): void
    {
        $this->view('admin/backups/index', [
            'title'   => 'Sauvegardes',
            'backups' => $this->service->list(),
        ]);
    }

    public function run(Request $request): void
    {
        if (!Auth::can('admin.settings.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        try {
            $path = $this->service->run((int)Auth::id());
            $this->flash('success', 'Sauvegarde créée : ' . basename($path));
        } catch (\Throwable $e) {
            \CityBus\Core\Logger::error('Backup failed: ' . $e->getMessage());
            $this->flash('danger', 'Échec de la sauvegarde : ' . $e->getMessage());
        }
        redirect('admin/backups');
    }

    public function download(Request $request, string $name): void
    {
        if (!Auth::can('admin.settings.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $path = $this->service->path($name);
        if (!$path) {
            $this->flash('danger', 'Sauvegarde introuvable.');
            redirect('admin/backups');
        }
        $mime = str_ends_with($name, '.gz') ? 'application/gzip' : 'application/sql';
        Response::download($path, $name, $mime);
    }

    public function destroy(Request $request, string $name): void
    {
        if (!Auth::can('admin.settings.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        if ($this->service->delete($name)) {
            $this->flash('success', 'Sauvegarde supprimée.');
        } else {
            $this->flash('danger', 'Suppression impossible.');
        }
        redirect('admin/backups');
    }
}
