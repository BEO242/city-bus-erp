<?php

declare(strict_types=1);

namespace CityBus\Controllers\Cargo;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Services\CargoV4Service;
use CityBus\Core\Database;

final class CargoV4Controller extends Controller
{
    private CargoV4Service $svc;
    public function __construct() { $this->svc = new CargoV4Service(); }

    public function event(Request $request, string $parcelId): void
    {
        if (!Auth::can('cargo.scan')) { back(); return; }
        $type = $request->input('event_type', 'in_transit');
        try {
            $this->svc->recordEvent((int)$parcelId, $type, [
                'location' => $request->input('location'),
                'trip_id'  => $request->input('trip_id'),
                'notes'    => $request->input('notes'),
            ]);
            $this->flash('success', "Événement '$type' enregistré.");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function pod(Request $request, string $parcelId): void
    {
        if (!Auth::can('cargo.deliver')) { back(); return; }
        try {
            $this->svc->recordPod((int)$parcelId, [
                'recipient_name' => $request->input('recipient_name'),
                'id_doc'         => $request->input('id_doc'),
                'signature'      => $request->input('signature'),
                'photo'          => $request->input('photo'),
            ]);
            $this->flash('success', 'Colis remis (POD enregistré).');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function cod(Request $request, string $parcelId): void
    {
        if (!Auth::can('cargo.cod.collect')) { back(); return; }
        try {
            $this->svc->collectCod((int)$parcelId, (int)$request->input('amount'));
            $this->flash('success', 'COD collecté.');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        back();
    }

    public function label(Request $request, string $parcelId): void
    {
        if (!Auth::can('cargo.scan')) { back(); return; }
        echo $this->svc->generateQrLabel((int)$parcelId);
        exit;
    }

    public function publicTrack(Request $request, string $tracking): void
    {
        if (!\CityBus\Core\Setting::getBool('cargo.public_tracking_enabled', true)) {
            http_response_code(404); $this->view('errors/404'); return;
        }
        $p = $this->svc->trackByNumber($tracking);
        $this->view('public/cargo/track', [
            'title' => 'Suivi colis',
            'parcel' => $p,
            'tracking' => $tracking,
        ]);
    }
}
