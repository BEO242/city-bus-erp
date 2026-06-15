<?php
/**
 * Définition des routes de l'application.
 * Le routeur est accessible via $app->router (depuis bootstrap).
 */

declare(strict_types=1);

use CityBus\Controllers\Auth\LoginController;
use CityBus\Controllers\Auth\PasswordResetController;
use CityBus\Controllers\DashboardController;
use CityBus\Controllers\UserController;
use CityBus\Controllers\ProfileController;
use CityBus\Controllers\Admin;
use CityBus\Controllers\Referentiel\AgencyController;
use CityBus\Controllers\Referentiel\CityController;
use CityBus\Controllers\Referentiel\LineController;
use CityBus\Controllers\Referentiel\BusController;
use CityBus\Controllers\Referentiel\TariffController;
use CityBus\Controllers\Referentiel\TariffConfigController;
use CityBus\Controllers\Referentiel\BaggageTariffController;
use CityBus\Controllers\Referentiel\DriverController;
use CityBus\Controllers\Referentiel\ConvoyeurController;
use CityBus\Controllers\Referentiel\NoteController;
use CityBus\Controllers\Referentiel\VehicleTypeController;
use CityBus\Controllers\Voyage\TripController;
use CityBus\Controllers\Voyage\InventoryController;
use CityBus\Controllers\Voyage\TrackingController;
use CityBus\Controllers\Voyage\BriefingController;
use CityBus\Controllers\Voyage\DepartureBoardController;
use CityBus\Controllers\Ops\OccController;
use CityBus\Controllers\Voyage\PricingController;
use CityBus\Controllers\Voyage\IropController;
use CityBus\Controllers\Hos\HosController as HosV3Controller;
use CityBus\Controllers\Driver\HomeController as DriverHome;
use CityBus\Controllers\Pnr\OdFareController;
use CityBus\Controllers\Crm\CustomerV4Controller;
use CityBus\Controllers\Finance\InvoiceController;
use CityBus\Controllers\Finance\AccountingV4Controller;
use CityBus\Controllers\Caisse\CashDrawerController;
use CityBus\Controllers\Cargo\CargoV4Controller;
use CityBus\Controllers\Analytics\KpiController;
use CityBus\Controllers\Public\BookingController as B2CBookingController;
use CityBus\Controllers\Api\ApiV2Controller;
use CityBus\Controllers\Billetterie\TicketController;
use CityBus\Controllers\Billetterie\PrePrintController;
use CityBus\Controllers\Billetterie\BaggageTicketController;
use CityBus\Controllers\Billetterie\UrbanTicketController;
use CityBus\Controllers\Controle\CheckpointController;
use CityBus\Controllers\Controle\ControleController;
use CityBus\Controllers\Caisse\CaisseController;
use CityBus\Controllers\Flotte\MaintenanceController;
use CityBus\Controllers\Flotte\FuelController;
use CityBus\Controllers\Flotte\IncidentController;
use CityBus\Controllers\RH\RhController;
use CityBus\Controllers\RH\HosController;
use CityBus\Controllers\RH\RhPositionController;
use CityBus\Controllers\Cargo\ParcelController;
use CityBus\Controllers\Cargo\ParcelTariffController;
use CityBus\Controllers\Cargo\FretCategoryController;
use CityBus\Controllers\Operations\FretController;
use CityBus\Controllers\Finance\TaxController;
use CityBus\Controllers\Finance\PnlController;
use CityBus\Controllers\Finance\AccountingController;
use CityBus\Controllers\Crm\CustomerController;
use CityBus\Controllers\Commerce\PromoController;
use CityBus\Controllers\Commerce\VoucherController;
use CityBus\Controllers\Billetterie\WaitlistController;
use CityBus\Controllers\Billetterie\ReservationController;
use CityBus\Controllers\Commerce\CorporateController;
use CityBus\Controllers\Commerce\PartnerController;
use CityBus\Controllers\Treasury\TreasuryController;
use CityBus\Controllers\Treasury\TreasuryCategoryController;
use CityBus\Controllers\Finance\CaisseManagementController;
use CityBus\Controllers\Finance\RefundController;
use CityBus\Controllers\Voyage\SchedulePatternController;
use CityBus\Controllers\Admin\NotificationTemplateController;
use CityBus\Controllers\Admin\ApiClientController;
use CityBus\Controllers\Ops\ControlTowerController;
use CityBus\Controllers\Flotte\InspectionController;
use CityBus\Controllers\Crm\FeedbackController;
use CityBus\Controllers\Crm\LoyaltyController;
use CityBus\Controllers\Public\TicketPublicController;
use CityBus\Controllers\Api\OAuthController;
use CityBus\Controllers\Media\MediaController;
use CityBus\Controllers\Api\ApiV1Controller;

/** @var \CityBus\Core\App $app */
$r = $app->router;

// ============================================================
// PUBLIC / GUEST
// ============================================================
$r->group(['middleware' => ['guest']], function ($r) {
    $r->get('/',         [LoginController::class, 'showLogin']);
    $r->get('/login',    [LoginController::class, 'showLogin'])->name('login');
    $r->post('/login',   [LoginController::class, 'login']);

    // Mot de passe oublié — self-service
    $r->get('/forgot-password',  [PasswordResetController::class, 'showRequest'])->name('password.request');
    $r->post('/forgot-password', [PasswordResetController::class, 'sendLink']);
    $r->get('/reset-password',   [PasswordResetController::class, 'showReset'])->name('password.reset');
    $r->post('/reset-password',  [PasswordResetController::class, 'reset']);
});

$r->any('/logout', [LoginController::class, 'logout'])->name('logout');

// ============================================================
// ROUTES PUBLIQUES (sans auth)
// ============================================================
// ─── V4.E B2C Booking + V4.G Cargo public tracking ───
$r->get('/public/booking',                   [B2CBookingController::class, 'home']);
$r->get('/public/booking/search',            [B2CBookingController::class, 'search']);
$r->get('/public/booking/trip/{tripId}',     [B2CBookingController::class, 'tripDetails']);
$r->get('/public/booking/checkout/{tripId}', [B2CBookingController::class, 'checkout']);
$r->post('/public/booking/submit',           [B2CBookingController::class, 'submit']);
$r->get('/public/booking/success',           [B2CBookingController::class, 'success']);
$r->get('/public/booking/pending',           [B2CBookingController::class, 'pending']);
$r->get('/public/booking/lookup',            [B2CBookingController::class, 'lookupPnr']);
$r->get('/public/cargo/track',               [CargoV4Controller::class, 'publicTrack']);
$r->get('/public/cargo/track/{tracking}',    [CargoV4Controller::class, 'publicTrack']);

// ─── V4.L API v2 OpenAPI public ───
$r->get('/api/v2/openapi.json',              [ApiV2Controller::class, 'openapi']);

$r->get('/public/ticket/{token}.ics', [TicketPublicController::class, 'ics']);
$r->get('/public/ticket/{token}',     [TicketPublicController::class, 'show']);
$r->get('/public/departures',         [DepartureBoardController::class, 'index']);
$r->get('/public/departures/{cityId}',[DepartureBoardController::class, 'forCity']);
$r->get('/feedback/{token}/thanks',   [FeedbackController::class, 'thanks']);
$r->get('/feedback/{token}',          [FeedbackController::class, 'showPublicForm']);
$r->post('/feedback/{token}',         [FeedbackController::class, 'submitPublic']);

// API OAuth2 token endpoint
$r->post('/api/oauth/token',          [OAuthController::class, 'token']);

// ============================================================
// API REST (bearer token, hors session/CSRF)
// ============================================================
$r->group(['prefix' => '/api/v1', 'middleware' => ['api-token']], function ($r) {
    $r->get('/ping',                  [ApiV1Controller::class, 'ping']);
    $r->get('/trips',                 [ApiV1Controller::class, 'trips']);
    $r->get('/buses',                 [ApiV1Controller::class, 'buses']);
    $r->get('/tickets/{code}',        [ApiV1Controller::class, 'ticketByCode']);
});

// V4.L API v2
$r->group(['prefix' => '/api/v2', 'middleware' => ['api-token']], function ($r) {
    $r->get('/search',                     [ApiV2Controller::class, 'search']);
    $r->post('/bookings',                  [ApiV2Controller::class, 'createBooking']);
    $r->get('/bookings/{pnrCode}',         [ApiV2Controller::class, 'getPnr']);
    $r->post('/bookings/{pnrCode}/cancel', [ApiV2Controller::class, 'cancelPnr']);
});

// 2FA pendant login (auth pas encore complète, mais csrf actif)
$r->group(['middleware' => ['csrf']], function ($r) {
    $r->get('/login/2fa',  [LoginController::class, 'show2fa']);
    $r->post('/login/2fa', [LoginController::class, 'verify2fa']);
});

// ============================================================
// PROTÉGÉ
// ============================================================
$r->group(['middleware' => ['auth', 'csrf', 'maintenance', 'audit']], function ($r) {

    // Dashboard
    $r->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ─── Profil utilisateur connecté ───
    $r->get('/profile',                 [ProfileController::class, 'index'])->name('profile.index');
    $r->get('/profile/password',        [ProfileController::class, 'showPassword']);
    $r->post('/profile/password',       [ProfileController::class, 'updatePassword']);
    $r->get('/profile/2fa',             [ProfileController::class, 'show2faSetup']);
    $r->post('/profile/2fa',            [ProfileController::class, 'enable2fa']);
    $r->post('/profile/2fa/disable',    [ProfileController::class, 'disable2fa']);
    $r->get('/profile/2fa/codes',       [ProfileController::class, 'showRecoveryCodes']);

    // ─── Administration ───
    $r->group(['prefix' => '/admin'], function ($r) {
        // Utilisateurs
        $r->group(['middleware' => ['permission:admin.users.view']], function ($r) {
            $r->get('/users',                            [Admin\UserController::class, 'index'])->name('admin.users.index');
            $r->get('/users/create',                     [Admin\UserController::class, 'create']);
            $r->post('/users',                           [Admin\UserController::class, 'store']);
            $r->get('/users/{id}/edit',                  [Admin\UserController::class, 'edit']);
            $r->post('/users/{id}',                      [Admin\UserController::class, 'update']);
            $r->post('/users/{id}/delete',               [Admin\UserController::class, 'destroy']);
            $r->post('/users/{id}/unlock',               [Admin\UserController::class, 'unlock']);
            $r->post('/users/{id}/reset-password',       [Admin\UserController::class, 'resetPassword']);
            $r->post('/users/{id}/reset-2fa',            [Admin\UserController::class, 'reset2fa']);
        });

        // Rôles
        $r->group(['middleware' => ['permission:admin.roles.view']], function ($r) {
            $r->get('/roles',              [Admin\RoleController::class, 'index'])->name('admin.roles.index');
            $r->get('/roles/create',       [Admin\RoleController::class, 'create']);
            $r->post('/roles',             [Admin\RoleController::class, 'store']);
            $r->get('/roles/{id}/edit',    [Admin\RoleController::class, 'edit']);
            $r->post('/roles/{id}',        [Admin\RoleController::class, 'update']);
            $r->post('/roles/{id}/delete', [Admin\RoleController::class, 'destroy']);
        });

        // Paramètres
        $r->group(['middleware' => ['permission:admin.settings.view']], function ($r) {
            $r->get('/settings',         [Admin\SettingController::class, 'index'])->name('admin.settings.index');
            $r->post('/settings',        [Admin\SettingController::class, 'update']);
            $r->post('/settings/test-smtp', [Admin\SettingController::class, 'testSmtp'])->middleware('permission:admin.settings.edit');
            $r->post('/settings/test-webhook', [Admin\SettingController::class, 'testWebhook'])->middleware('permission:admin.settings.edit');
            $r->get('/settings/export',  [Admin\SettingController::class, 'export'])->middleware('permission:admin.settings.edit');
            $r->post('/settings/import', [Admin\SettingController::class, 'import'])->middleware('permission:admin.settings.edit');
            $r->post('/settings/reset',  [Admin\SettingController::class, 'resetCategory'])->middleware('permission:admin.settings.edit');
            $r->post('/settings/logo',   [Admin\SettingController::class, 'uploadLogo'])->middleware('permission:admin.settings.edit');
        });

        // Sauvegardes
        $r->group(['middleware' => ['permission:admin.settings.view']], function ($r) {
            $r->get('/backups',                    [Admin\BackupController::class, 'index'])->name('admin.backups.index');
            $r->post('/backups/run',               [Admin\BackupController::class, 'run'])->middleware('permission:admin.settings.edit');
            $r->get('/backups/{name}/download',    [Admin\BackupController::class, 'download'])->middleware('permission:admin.settings.edit');
            $r->post('/backups/{name}/delete',     [Admin\BackupController::class, 'destroy'])->middleware('permission:admin.settings.edit');
        });

        // Audit
        $r->group(['middleware' => ['permission:admin.audit.view']], function ($r) {
            $r->get('/audit',          [Admin\AuditController::class, 'index'])->name('admin.audit.index');
            $r->get('/audit/export',   [Admin\AuditController::class, 'exportCsv']);
            $r->get('/audit/{id}',     [Admin\AuditController::class, 'show']);
        });
    });

    // ─── Référentiel ───
    $r->group(['prefix' => '/referentiel', 'middleware' => ['permission:referentiel.view']], function ($r) {
        // Villes
        $r->get('/cities',                [CityController::class, 'index'])->name('cities.index');
        $r->get('/cities/create',         [CityController::class, 'create'])->middleware('permission:referentiel.create');
        $r->post('/cities',               [CityController::class, 'store'])->middleware('permission:referentiel.create');
        $r->get('/cities/{id}/edit',      [CityController::class, 'edit'])->middleware('permission:referentiel.edit');
        $r->post('/cities/{id}',          [CityController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/cities/{id}/delete',   [CityController::class, 'destroy'])->middleware('permission:referentiel.delete');

        $r->get('/agencies',              [AgencyController::class, 'index'])->name('agencies.index');
        $r->get('/agencies/create',       [AgencyController::class, 'create'])->middleware('permission:referentiel.create');
        $r->post('/agencies',             [AgencyController::class, 'store'])->middleware('permission:referentiel.create');
        $r->get('/agencies/{id}/edit',    [AgencyController::class, 'edit'])->middleware('permission:referentiel.edit');
        $r->post('/agencies/{id}',        [AgencyController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/agencies/{id}/delete', [AgencyController::class, 'destroy'])->middleware('permission:referentiel.delete');

        $r->get('/lines',                 [LineController::class, 'index'])->name('lines.index');
        $r->get('/lines/create',          [LineController::class, 'create'])->middleware('permission:referentiel.create');
        $r->post('/lines',                [LineController::class, 'store'])->middleware('permission:referentiel.create');
        $r->get('/lines/{id}',            [LineController::class, 'show']);
        $r->get('/lines/{id}/edit',       [LineController::class, 'edit'])->middleware('permission:referentiel.edit');
        $r->post('/lines/{id}',           [LineController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/lines/{id}/delete',    [LineController::class, 'destroy'])->middleware('permission:referentiel.delete');

        // Notes ligne
        $r->post('/lines/{id}/notes',                 [NoteController::class, 'storeLine'])->middleware('permission:referentiel.edit');
        $r->post('/lines/{id}/notes/{noteId}/delete', [NoteController::class, 'destroyLine'])->middleware('permission:referentiel.edit');

        // Types de véhicules
        $r->get('/vehicle-types',              [VehicleTypeController::class, 'index'])->name('vehicle-types.index');
        $r->get('/vehicle-types/create',       [VehicleTypeController::class, 'create'])->middleware('permission:referentiel.create');
        $r->post('/vehicle-types',             [VehicleTypeController::class, 'store'])->middleware('permission:referentiel.create');
        $r->get('/vehicle-types/{id}/edit',    [VehicleTypeController::class, 'edit'])->middleware('permission:referentiel.edit');
        $r->post('/vehicle-types/{id}',        [VehicleTypeController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/vehicle-types/{id}/delete', [VehicleTypeController::class, 'destroy'])->middleware('permission:referentiel.delete');

        // Véhicules (ex-buses)
        $r->get('/vehicules',              [BusController::class, 'index'])->name('vehicules.index');
        $r->get('/vehicules/create',       [BusController::class, 'create'])->middleware('permission:referentiel.create');
        $r->post('/vehicules',             [BusController::class, 'store'])->middleware('permission:referentiel.create');
        $r->get('/vehicules/{id}',         [BusController::class, 'show']);
        $r->get('/vehicules/{id}/edit',    [BusController::class, 'edit'])->middleware('permission:referentiel.edit');
        $r->post('/vehicules/{id}',        [BusController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/vehicules/{id}/delete', [BusController::class, 'destroy'])->middleware('permission:referentiel.delete');

        // Notes véhicule
        $r->post('/vehicules/{id}/notes',                   [NoteController::class, 'storeBus'])->middleware('permission:referentiel.edit');
        $r->post('/vehicules/{id}/notes/{noteId}/delete',   [NoteController::class, 'destroyBus'])->middleware('permission:referentiel.edit');

        $r->get('/drivers',                [DriverController::class, 'index'])->name('drivers.index');
        $r->get('/drivers/create',         [DriverController::class, 'create'])->middleware('permission:referentiel.create');
        $r->post('/drivers',               [DriverController::class, 'store'])->middleware('permission:referentiel.create');
        $r->get('/drivers/{id}',           [DriverController::class, 'show']);
        $r->get('/drivers/{id}/edit',      [DriverController::class, 'edit'])->middleware('permission:referentiel.edit');
        $r->post('/drivers/{id}',          [DriverController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/drivers/{id}/delete',   [DriverController::class, 'destroy'])->middleware('permission:referentiel.delete');

        // Notes chauffeur
        $r->post('/drivers/{id}/notes',                 [NoteController::class, 'storeDriver'])->middleware('permission:referentiel.edit');
        $r->post('/drivers/{id}/notes/{noteId}/delete', [NoteController::class, 'destroyDriver'])->middleware('permission:referentiel.edit');

        // Convoyeurs
        $r->get('/convoyeurs',                [ConvoyeurController::class, 'index'])->name('convoyeurs.index');
        $r->get('/convoyeurs/create',         [ConvoyeurController::class, 'create'])->middleware('permission:referentiel.create');
        $r->post('/convoyeurs',               [ConvoyeurController::class, 'store'])->middleware('permission:referentiel.create');
        $r->get('/convoyeurs/{id}',           [ConvoyeurController::class, 'show']);
        $r->get('/convoyeurs/{id}/edit',      [ConvoyeurController::class, 'edit'])->middleware('permission:referentiel.edit');
        $r->post('/convoyeurs/{id}',          [ConvoyeurController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/convoyeurs/{id}/delete',   [ConvoyeurController::class, 'destroy'])->middleware('permission:referentiel.delete');

        $r->get('/tariffs',               [TariffController::class, 'index'])->name('tariffs.index');
        $r->get('/tariffs/create',        [TariffController::class, 'create'])->middleware('permission:referentiel.create');
        $r->post('/tariffs',              [TariffController::class, 'store'])->middleware('permission:referentiel.create');
        $r->get('/tariffs/{id}/edit',     [TariffController::class, 'edit'])->middleware('permission:referentiel.edit');
        $r->post('/tariffs/{id}',         [TariffController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/tariffs/{id}/delete',  [TariffController::class, 'destroy'])->middleware('permission:referentiel.delete');

        // Configuration dynamique des types de tarifs
        $r->get('/tariffs/config',                                      [TariffConfigController::class, 'index']);
        $r->post('/tariffs/config/{type}',                              [TariffConfigController::class, 'store'])->middleware('permission:referentiel.create');
        $r->post('/tariffs/config/{type}/{id}',                         [TariffConfigController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/tariffs/config/{type}/{id}/delete',                  [TariffConfigController::class, 'destroy'])->middleware('permission:referentiel.delete');
        $r->post('/tariffs/config/{type}/{id}/toggle',                  [TariffConfigController::class, 'toggle'])->middleware('permission:referentiel.edit');
        $r->post('/tariffs/config/{type}/reorder',                      [TariffConfigController::class, 'reorder'])->middleware('permission:referentiel.edit');

        // Tarifs bagages
        $r->get('/baggage-tariffs',              [BaggageTariffController::class, 'index'])->name('baggage-tariffs.index');
        $r->get('/baggage-tariffs/create',       [BaggageTariffController::class, 'create'])->middleware('permission:referentiel.create');
        $r->post('/baggage-tariffs',             [BaggageTariffController::class, 'store'])->middleware('permission:referentiel.create');
        $r->get('/baggage-tariffs/{id}/edit',    [BaggageTariffController::class, 'edit'])->middleware('permission:referentiel.edit');
        $r->post('/baggage-tariffs/{id}',        [BaggageTariffController::class, 'update'])->middleware('permission:referentiel.edit');
        $r->post('/baggage-tariffs/{id}/delete', [BaggageTariffController::class, 'destroy'])->middleware('permission:referentiel.delete');
    });

    // ─── Voyages — refonte audit profondeur ───
    $r->group(['prefix' => '/voyages', 'middleware' => ['permission:voyages.view']], function ($r) {
        $r->get('/',                  [TripController::class, 'index'])->name('voyages.index');
        $r->get('/export.csv',        [TripController::class, 'exportCsv'])->middleware('permission:voyages.export');
        $r->get('/create',            [TripController::class, 'create'])->middleware('permission:voyages.create');
        $r->post('/',                 [TripController::class, 'store'])->middleware('permission:voyages.create');

        // Patterns horaires (GAP-11)
        $r->group(['middleware' => ['permission:voyages.schedule.view']], function ($r) {
            $r->get('/schedules',                       [SchedulePatternController::class, 'index']);
            $r->get('/schedules/create',                [SchedulePatternController::class, 'create']);
            $r->post('/schedules',                      [SchedulePatternController::class, 'store']);
            $r->post('/schedules/generate-all',         [SchedulePatternController::class, 'generateAll']);
            $r->post('/schedules/{id}/generate',        [SchedulePatternController::class, 'generate']);
            $r->post('/schedules/{id}/delete',          [SchedulePatternController::class, 'destroy']);
        });

        // Détail + actions
        $r->get('/{id}',                  [TripController::class, 'show']);
        $r->get('/{id}/edit',             [TripController::class, 'edit'])->middleware('permission:voyages.edit');
        $r->post('/{id}/update',          [TripController::class, 'update'])->middleware('permission:voyages.edit');
        $r->post('/{id}/status',          [TripController::class, 'changeStatus']);
        $r->get('/{id}/manifest',         [TripController::class, 'manifest']);

        // Actions opérationnelles
        $r->post('/{id}/delete',          [TripController::class, 'destroy'])->middleware('permission:voyages.delete');
        $r->post('/{id}/replace-bus',     [TripController::class, 'replaceBus'])->middleware('permission:voyages.replace_bus');
        $r->post('/{id}/replace-driver',  [TripController::class, 'replaceDriver'])->middleware('permission:voyages.replace_driver');
        $r->post('/{id}/lock',            [TripController::class, 'lockManifest'])->middleware('permission:voyages.lock_manifest');
        $r->post('/{id}/unlock',          [TripController::class, 'unlockManifest'])->middleware('permission:voyages.unlock_manifest');
        $r->post('/{id}/communicate',     [TripController::class, 'communicate'])->middleware('permission:voyages.communicate');
        $r->post('/{id}/replacement',     [TripController::class, 'createReplacement'])->middleware('permission:voyages.create');
        $r->post('/{id}/documents',       [TripController::class, 'uploadDocument'])->middleware('permission:voyages.documents.upload');
        $r->post('/{id}/costs',           [TripController::class, 'addCost'])->middleware('permission:voyages.costs.manage');
        $r->post('/{id}/disputes',        [TripController::class, 'openDispute'])->middleware('permission:voyages.dispute.manage');

        // ─── V3 Phase 1 : Inventaire par classe (Y/B/M/H/L) ───
        $r->group(['middleware' => ['permission:voyages.inventory.view']], function ($r) {
            $r->get('/{id}/inventory',                       [InventoryController::class, 'tripInventory']);
            $r->post('/{id}/inventory/class/{classCode}',    [InventoryController::class, 'updateClass'])->middleware('permission:voyages.inventory.manage');
            $r->post('/{id}/inventory/regenerate',           [InventoryController::class, 'regenerate'])->middleware('permission:voyages.inventory.manage');
        });

        // ─── V3 Phase 1 : Suivi stop-by-stop ───
        $r->get('/{id}/tracking',                            [TrackingController::class, 'show']);
        $r->post('/{id}/tracking/generate',                  [TrackingController::class, 'generate'])->middleware('permission:voyages.tracking.update');
        $r->post('/{id}/tracking/stops/{stopId}/arrival',    [TrackingController::class, 'recordArrival'])->middleware('permission:voyages.tracking.update');
        $r->post('/{id}/tracking/stops/{stopId}/departure',  [TrackingController::class, 'recordDeparture'])->middleware('permission:voyages.tracking.update');
        $r->post('/{id}/tracking/stops/{stopId}/skip',       [TrackingController::class, 'skipStop'])->middleware('permission:voyages.tracking.update');

        // ─── V3 Phase 1 : Briefing voyage ───
        $r->get('/{id}/briefing',                            [BriefingController::class, 'show'])->middleware('permission:voyages.briefing.view');
        $r->get('/{id}/briefing/print',                      [BriefingController::class, 'printable'])->middleware('permission:voyages.briefing.print');
    });

    // ─── V3 Phase 1 : Référentiel classes d'inventaire (Y/B/M/H/L) ───
    $r->group(['prefix' => '/referentiel/inventory-classes', 'middleware' => ['permission:voyages.inventory.view']], function ($r) {
        $r->get('/',                  [InventoryController::class, 'classesIndex']);
        $r->get('/{id}/edit',         [InventoryController::class, 'classEdit'])->middleware('permission:voyages.inventory.manage');
        $r->post('/{id}',             [InventoryController::class, 'classUpdate'])->middleware('permission:voyages.inventory.manage');
    });

    // ─── V3 Phase 1 : Operations Control Center ───
    $r->group(['prefix' => '/ops', 'middleware' => ['permission:ops.occ.view']], function ($r) {
        $r->get('/occ',               [OccController::class, 'dashboard']);
    });

    // ─── V3 Phase 2 : Pricing dynamique ───
    $r->group(['prefix' => '/voyages/pricing', 'middleware' => ['permission:voyages.pricing.view']], function ($r) {
        $r->get('/',                  [PricingController::class, 'index']);
        $r->get('/create',            [PricingController::class, 'create'])->middleware('permission:voyages.pricing.manage');
        $r->post('/',                 [PricingController::class, 'store'])->middleware('permission:voyages.pricing.manage');
        $r->get('/{id}/edit',         [PricingController::class, 'edit'])->middleware('permission:voyages.pricing.manage');
        $r->post('/{id}',             [PricingController::class, 'update'])->middleware('permission:voyages.pricing.manage');
        $r->post('/{id}/delete',      [PricingController::class, 'destroy'])->middleware('permission:voyages.pricing.manage');
    });
    $r->post('/voyages/{tripId}/pricing/recalc', [PricingController::class, 'applyToTrip'])->middleware('permission:voyages.pricing.apply');

    // ─── V3 Phase 2 : IROP ───
    $r->group(['prefix' => '/voyages/irop', 'middleware' => ['permission:voyages.irop.view']], function ($r) {
        $r->get('/',                       [IropController::class, 'index']);
        $r->get('/{id}',                   [IropController::class, 'show']);
        $r->post('/{id}/init-rebook',      [IropController::class, 'initRebook'])->middleware('permission:voyages.irop.rebook');
        $r->post('/{id}/rebook/{rebookId}',[IropController::class, 'rebookTo'])->middleware('permission:voyages.irop.rebook');
        $r->post('/{id}/refund/{rebookId}',[IropController::class, 'refund'])->middleware('permission:voyages.irop.rebook');
        $r->post('/{id}/resolve',          [IropController::class, 'resolve'])->middleware('permission:voyages.irop.manage');
        $r->post('/{id}/close',            [IropController::class, 'close'])->middleware('permission:voyages.irop.manage');
    });
    $r->post('/voyages/{tripId}/irop/open', [IropController::class, 'open'])->middleware('permission:voyages.irop.manage');

    // ─── V3 Phase 3 : HOS ───
    $r->group(['prefix' => '/hos', 'middleware' => ['permission:hos.view']], function ($r) {
        $r->get('/',                              [HosV3Controller::class, 'dashboard']);
        $r->get('/driver/{driverId}',             [HosV3Controller::class, 'driver']);
        $r->post('/driver/{driverId}/start',      [HosV3Controller::class, 'startDuty'])->middleware('permission:hos.log');
        $r->post('/driver/{driverId}/end/{logId}',[HosV3Controller::class, 'endDuty'])->middleware('permission:hos.log');
        $r->post('/violation/{violationId}/ack',  [HosV3Controller::class, 'ackViolation'])->middleware('permission:hos.violations');
    });

    // ─── V3 Phase 5 : PWA chauffeur ───
    $r->group(['prefix' => '/m/driver'], function ($r) {
        $r->get('/',                       [DriverHome::class, 'index']);
        $r->get('/stops',                  [DriverHome::class, 'stops']);
        $r->get('/hos',                    [DriverHome::class, 'hos']);
        $r->get('/profile',                [DriverHome::class, 'profile']);
        $r->post('/hos/start',             [DriverHome::class, 'startDuty']);
        $r->post('/hos/end/{logId}',       [DriverHome::class, 'endDuty']);
        $r->get('/trip/{tripId}',          [DriverHome::class, 'trip']);
        $r->post('/trip/{tripId}/arrive/{stopId}', [DriverHome::class, 'arrive']);
        $r->post('/trip/{tripId}/depart/{stopId}', [DriverHome::class, 'depart']);
    });

    // ─── Réservations / PNR (GAP-01) ───
    $r->group(['prefix' => '/billetterie/reservations', 'middleware' => ['permission:reservations.view']], function ($r) {
        $r->get('/',                            [ReservationController::class, 'index']);
        $r->get('/create',                      [ReservationController::class, 'create']);
        $r->post('/',                           [ReservationController::class, 'store']);
        $r->post('/lookup',                     [ReservationController::class, 'lookup']);
        $r->get('/{pnr}',                       [ReservationController::class, 'show']);
        $r->post('/{pnr}/confirm',              [ReservationController::class, 'confirm']);
        $r->post('/{pnr}/convert',              [ReservationController::class, 'convert']);
        $r->post('/{pnr}/cancel',               [ReservationController::class, 'cancel']);
    });

    // ─── Pré-vérifications voyage (GAP-14) ───
    $r->group(['prefix' => '/flotte/inspections', 'middleware' => ['permission:inspection.view']], function ($r) {
        $r->get('/{tripId}',  [InspectionController::class, 'form']);
        $r->post('/{tripId}', [InspectionController::class, 'save'])->middleware('permission:inspection.create');
    });

    // ─── Tour de contrôle ops (GAP-28) ───
    $r->group(['prefix' => '/ops', 'middleware' => ['permission:ops.control_tower.view']], function ($r) {
        $r->get('/control-tower',           [ControlTowerController::class, 'index'])->name('ops.control_tower');
        $r->get('/control-tower/live',      [ControlTowerController::class, 'liveData']);
        $r->post('/alerts/{id}/ack',        [ControlTowerController::class, 'alertAck']);
    });

    // ─── Liste d'attente (GAP-12) ───
    $r->group(['prefix' => '/billetterie/waitlist', 'middleware' => ['permission:waitlist.view']], function ($r) {
        $r->get('/{tripId}',                       [WaitlistController::class, 'show']);
        $r->post('/{tripId}/add',                  [WaitlistController::class, 'add']);
        $r->post('/{tripId}/notify-next',          [WaitlistController::class, 'notifyNext']);
        $r->post('/entry/{entryId}/cancel',        [WaitlistController::class, 'cancel']);
    });

    // ─── Codes promo & avoirs (GAP-06, GAP-08) ───
    $r->group(['prefix' => '/commerce'], function ($r) {
        $r->get('/promo',                         [PromoController::class, 'index'])->middleware('permission:promo.view');
        $r->get('/promo/create',                  [PromoController::class, 'create'])->middleware('permission:promo.manage');
        $r->post('/promo',                        [PromoController::class, 'store'])->middleware('permission:promo.manage');
        $r->post('/promo/{id}/delete',            [PromoController::class, 'destroy'])->middleware('permission:promo.manage');
        $r->post('/promo/validate',               [PromoController::class, 'validateCode'])->middleware('permission:promo.view');

        $r->get('/vouchers',                      [VoucherController::class, 'index'])->middleware('permission:vouchers.view');
        $r->post('/vouchers/issue',               [VoucherController::class, 'issue'])->middleware('permission:vouchers.issue');
        $r->post('/vouchers/{id}/void',           [VoucherController::class, 'void'])->middleware('permission:vouchers.issue');
        $r->post('/vouchers/check',               [VoucherController::class, 'check'])->middleware('permission:vouchers.view');

        // Comptes corporate (GAP-09)
        $r->get('/corporate',               [CorporateController::class, 'index'])->middleware('permission:corporate.view');
        $r->get('/corporate/create',        [CorporateController::class, 'create'])->middleware('permission:corporate.manage');
        $r->post('/corporate',              [CorporateController::class, 'store'])->middleware('permission:corporate.manage');
        $r->get('/corporate/{id}',          [CorporateController::class, 'show'])->middleware('permission:corporate.view');

        // Partenaires commerciaux (GAP-25)
        $r->get('/partners',                [PartnerController::class, 'index'])->middleware('permission:partners.view');
        $r->get('/partners/create',         [PartnerController::class, 'create'])->middleware('permission:partners.manage');
        $r->post('/partners',               [PartnerController::class, 'store'])->middleware('permission:partners.manage');
        $r->get('/partners/{id}',           [PartnerController::class, 'show'])->middleware('permission:partners.view');
        $r->post('/partners/{id}/payout',   [PartnerController::class, 'generatePayout'])->middleware('permission:partners.manage');
    });

    // ─── CRM Feedback (GAP-20) ───
    $r->group(['prefix' => '/crm', 'middleware' => ['permission:feedback.view']], function ($r) {
        $r->get('/feedback', [FeedbackController::class, 'index'])->name('crm.feedback');
    });


    // ─── Admin notifications + API ───
    $r->group(['prefix' => '/admin'], function ($r) {
        $r->get('/notifications',                  [NotificationTemplateController::class, 'index'])->middleware('permission:notifications.view');
        $r->get('/notifications/logs',             [NotificationTemplateController::class, 'logs'])->middleware('permission:notifications.view');
        $r->get('/notifications/{id}/edit',        [NotificationTemplateController::class, 'edit'])->middleware('permission:notifications.manage');
        $r->post('/notifications/{id}',            [NotificationTemplateController::class, 'update'])->middleware('permission:notifications.manage');
        $r->get('/notifications/{id}/preview',     [NotificationTemplateController::class, 'preview'])->middleware('permission:notifications.view');

        // Clients API publique
        $r->get('/api',                            [ApiClientController::class, 'index'])->middleware('permission:api.tokens.manage');
        $r->get('/api/create',                     [ApiClientController::class, 'create'])->middleware('permission:api.tokens.manage');
        $r->post('/api',                           [ApiClientController::class, 'store'])->middleware('permission:api.tokens.manage');
        $r->post('/api/{id}/revoke',               [ApiClientController::class, 'revoke'])->middleware('permission:api.tokens.manage');
    });

    // ─── Billetterie ───
    $r->group(['prefix' => '/billetterie', 'middleware' => ['permission:billetterie.view']], function ($r) {
        $r->get('/',                  [TicketController::class, 'index'])->name('billetterie.index');
        $r->get('/select-trip',       [TicketController::class, 'selectTrip']);
        $r->get('/sale/{tripId}',     [TicketController::class, 'showSale']);
        $r->get('/resolve-tariff',    [TicketController::class, 'resolveTariff']);
        $r->post('/',                 [TicketController::class, 'store']);
        $r->post('/sell',             [TicketController::class, 'store']); // alias parlant pour la vente

        // Tickets pré-imprimés — avant /{id} pour éviter le conflit de routes
        $r->get('/preprint',                     [PrePrintController::class, 'index']);
        $r->get('/preprint/create',              [PrePrintController::class, 'create']);
        $r->post('/preprint',                    [PrePrintController::class, 'store']);
        $r->get('/preprint/config',              [PrePrintController::class, 'config']);
        $r->post('/preprint/config',             [PrePrintController::class, 'updateConfig']);
        $r->get('/preprint/batch/{batchId}',     [PrePrintController::class, 'showBatch']);
        $r->get('/preprint/batch/{batchId}/pdf', [PrePrintController::class, 'downloadBatch']);
        $r->post('/preprint/{id}/cancel',        [PrePrintController::class, 'cancel']);
        $r->post('/preprint/lookup',             [PrePrintController::class, 'lookup']);

        // Tickets urbains pré-imprimés — avant /{id} pour éviter le conflit
        $r->get('/urban-tickets',                 [UrbanTicketController::class, 'index'])->middleware('permission:billetterie.preprint');
        $r->get('/urban-tickets/create',          [UrbanTicketController::class, 'create'])->middleware('permission:billetterie.preprint');
        $r->post('/urban-tickets',                [UrbanTicketController::class, 'store'])->middleware('permission:billetterie.preprint');
        $r->get('/urban-tickets/{id}',            [UrbanTicketController::class, 'show'])->middleware('permission:billetterie.preprint');
        $r->get('/urban-tickets/{id}/pdf',        [UrbanTicketController::class, 'downloadPdf'])->middleware('permission:billetterie.preprint');
        $r->post('/urban-tickets/{id}/start',     [UrbanTicketController::class, 'start'])->middleware('permission:billetterie.preprint');
        $r->post('/urban-tickets/{id}/close',     [UrbanTicketController::class, 'close'])->middleware('permission:billetterie.preprint');
        $r->post('/urban-tickets/{id}/cancel',    [UrbanTicketController::class, 'cancel'])->middleware('permission:billetterie.preprint');

        // Routes tickets par ID — après les routes statiques
        $r->get('/{id}',              [TicketController::class, 'show']);
        $r->get('/{id}/print',        [TicketController::class, 'printView']);
        $r->get('/{id}/pdf',          [TicketController::class, 'pdf']);
        $r->post('/{id}/cancel',      [TicketController::class, 'cancel']);
        $r->post('/{id}/pay',         [TicketController::class, 'pay']);
        $r->post('/{id}/refund',      [TicketController::class, 'refund']);
        $r->post('/{id}/status',      [TicketController::class, 'updateStatus']);
        $r->get('/{id}/reprint',      [TicketController::class, 'reprint']);
    });

    // ─── Billetterie Bagages ───
    $r->group(['prefix' => '/billetterie-bagages', 'middleware' => ['permission:billetterie.view']], function ($r) {
        $r->get('/',                        [BaggageTicketController::class, 'index'])->name('billetterie-bagages.index');
        $r->get('/select-trip',             [BaggageTicketController::class, 'selectTrip']);
        $r->get('/sale/{tripId}',           [BaggageTicketController::class, 'showSale']);
        $r->post('/',                       [BaggageTicketController::class, 'store']);
        $r->get('/calc',                    [BaggageTicketController::class, 'calcPrice']);
        $r->get('/{id}',                    [BaggageTicketController::class, 'show']);
        $r->get('/{id}/print',              [BaggageTicketController::class, 'printView']);
        $r->get('/{id}/pdf',                [BaggageTicketController::class, 'pdf']);
        $r->post('/{id}/cancel',            [BaggageTicketController::class, 'cancel']);
    });

    // ─── Contrôle ───
    $r->group(['prefix' => '/controle', 'middleware' => ['permission:controle.view']], function ($r) {
        $r->get('/',                       [ControleController::class, 'index'])->name('controle.index');
        $r->post('/validate',              [ControleController::class, 'validateTicket']);
        $r->get('/trip/{tripId}/cache',    [ControleController::class, 'tripCache']);
        $r->post('/sync',                  [ControleController::class, 'syncBatch']);

        // Postes de contrôle (CRUD)
        $r->get('/checkpoints',                [CheckpointController::class, 'index'])->name('checkpoints.index');
        $r->get('/checkpoints/create',         [CheckpointController::class, 'create']);
        $r->post('/checkpoints',               [CheckpointController::class, 'store']);
        $r->get('/checkpoints/{id}/edit',      [CheckpointController::class, 'edit']);
        $r->post('/checkpoints/{id}',          [CheckpointController::class, 'update']);
        $r->post('/checkpoints/{id}/delete',   [CheckpointController::class, 'destroy']);
    });

    // ─── Caisse ───
    $r->group(['prefix' => '/caisse', 'middleware' => ['permission:caisse.view']], function ($r) {
        $r->get('/',                          [CaisseController::class, 'index'])->name('caisse.index');
        $r->get('/open',                      [CaisseController::class, 'showOpen']);
        $r->post('/open',                     [CaisseController::class, 'open']);
        $r->get('/close',                     [CaisseController::class, 'showClose']);
        $r->post('/close',                    [CaisseController::class, 'close']);
        $r->post('/closures/{id}/validate',   [CaisseController::class, 'validateClosure']);
        $r->get('/closures/{id}/pdf',         [CaisseController::class, 'closurePdf']);
    });

    // ─── Flotte ───
    $r->group(['prefix' => '/flotte', 'middleware' => ['permission:flotte.view']], function ($r) {
        $r->get('/maintenance',                  [MaintenanceController::class, 'index']);
        $r->get('/maintenance/create',           [MaintenanceController::class, 'create'])->middleware('permission:flotte.maintenance.create');
        $r->post('/maintenance',                 [MaintenanceController::class, 'store'])->middleware('permission:flotte.maintenance.create');
        $r->get('/maintenance/{id}/edit',        [MaintenanceController::class, 'edit'])->middleware('permission:flotte.maintenance.edit');
        $r->post('/maintenance/{id}',            [MaintenanceController::class, 'update'])->middleware('permission:flotte.maintenance.edit');
        $r->post('/maintenance/{id}/status',     [MaintenanceController::class, 'changeStatus'])->middleware('permission:flotte.maintenance.edit');
        $r->get('/maintenance/{id}',             [MaintenanceController::class, 'show']);

        $r->get('/fuel',              [FuelController::class, 'index']);
        $r->get('/fuel/create',       [FuelController::class, 'create'])->middleware('permission:flotte.fuel.log');
        $r->post('/fuel',             [FuelController::class, 'store'])->middleware('permission:flotte.fuel.log');
        $r->get('/fuel/{id}/edit',    [FuelController::class, 'edit'])->middleware('permission:flotte.fuel.log');
        $r->post('/fuel/{id}',        [FuelController::class, 'update'])->middleware('permission:flotte.fuel.log');
        $r->post('/fuel/{id}/delete', [FuelController::class, 'destroy'])->middleware('permission:flotte.fuel.log');
        $r->get('/fuel/{id}',         [FuelController::class, 'show']);

        // Incidents
        $r->get('/incidents',                    [IncidentController::class, 'index']);
        $r->get('/incidents/create',             [IncidentController::class, 'create']);
        $r->post('/incidents',                   [IncidentController::class, 'store']);
        $r->get('/incidents/{id}/edit',          [IncidentController::class, 'edit']);
        $r->post('/incidents/{id}',              [IncidentController::class, 'update']);
        $r->post('/incidents/{id}/resolve',      [IncidentController::class, 'resolve']);
        $r->post('/incidents/{id}/reopen',       [IncidentController::class, 'reopen']);
        $r->get('/incidents/{id}',               [IncidentController::class, 'show']);
    });

    // ─── RH ───
    $r->group(['prefix' => '/rh', 'middleware' => ['permission:rh.view']], function ($r) {
        $r->get('/',                        [RhController::class, 'dashboard'])->name('rh.dashboard');
        $r->get('/dashboard',               [RhController::class, 'dashboard']);

        // Postes & fonctions
        $r->get('/positions',               [RhPositionController::class, 'index'])->middleware('permission:rh.view');
        $r->get('/positions/create',        [RhPositionController::class, 'create'])->middleware('permission:rh.create');
        $r->post('/positions',              [RhPositionController::class, 'store'])->middleware('permission:rh.create');
        $r->get('/positions/{id}/edit',     [RhPositionController::class, 'edit'])->middleware('permission:rh.edit');
        $r->post('/positions/{id}',         [RhPositionController::class, 'update'])->middleware('permission:rh.edit');
        $r->post('/positions/{id}/delete',  [RhPositionController::class, 'destroy'])->middleware('permission:rh.edit');

        // HOS chauffeurs (GAP-13)
        $r->get('/hos',                       [HosController::class, 'dashboard'])->middleware('permission:hos.view')->name('rh.hos');
        $r->get('/hos/{driverId}',            [HosController::class, 'show'])->middleware('permission:hos.view');
        $r->post('/hos/{driverId}/log',       [HosController::class, 'logEntry'])->middleware('permission:hos.edit');

        // Planning
        $r->get('/schedule',                  [RhController::class, 'schedule'])->name('rh.schedule');
        $r->post('/schedule',                 [RhController::class, 'storeSchedule']);
        $r->post('/schedule/{id}/delete',     [RhController::class, 'destroySchedule']);

        $r->get('/employees',               [RhController::class, 'employees']);
        $r->get('/employees/export.csv',    [RhController::class, 'exportEmployeesCsv']);
        $r->get('/employees/create',        [RhController::class, 'createEmployee'])->middleware('permission:rh.create');
        $r->post('/employees',              [RhController::class, 'storeEmployee'])->middleware('permission:rh.create');
        $r->get('/employees/{id}/edit',     [RhController::class, 'editEmployee'])->middleware('permission:rh.edit');
        $r->post('/employees/{id}',         [RhController::class, 'updateEmployee'])->middleware('permission:rh.edit');
        $r->post('/employees/{id}/toggle',  [RhController::class, 'toggleEmployee'])->middleware('permission:rh.edit');
        $r->get('/employees/{id}',          [RhController::class, 'showEmployee']);

        $r->get('/payroll',                 [RhController::class, 'payrolls'])->middleware('permission:rh.payroll');
        $r->post('/payroll/run',            [RhController::class, 'runPayroll'])->middleware('permission:rh.payroll');
        $r->get('/payroll/{id}/pdf',        [RhController::class, 'payslipPdf'])->middleware('permission:rh.payroll');
        $r->post('/payroll/{id}/paid',      [RhController::class, 'markPaid'])->middleware('permission:rh.payroll');
    });

    // ─── Finance / Fiscal & P&L (GAP-21, GAP-22) ───
    $r->group(['prefix' => '/finance'], function ($r) {
        $r->get('/tax/vat',         [TaxController::class, 'vatReport'])->name('finance.tax.vat')->middleware('permission:finance.tax.view');
        $r->get('/tax/vat/export',  [TaxController::class, 'exportVatCsv'])->middleware('permission:finance.tax.export');

        $r->get('/pnl',                              [PnlController::class, 'index'])->name('finance.pnl')->middleware('permission:finance.pnl.view');
        $r->get('/pnl/trip/{tripId}',                [PnlController::class, 'trip'])->middleware('permission:finance.pnl.view');
        $r->post('/pnl/trip/{tripId}/recompute',     [PnlController::class, 'recompute'])->middleware('permission:finance.pnl.view');
        $r->get('/pnl/export',                       [PnlController::class, 'export'])->middleware('permission:finance.pnl.export');

        // ─── Gestion des caisses ───
        $r->group(['prefix' => '/caisses', 'middleware' => ['permission:caisse.view']], function ($r) {
            $r->get('/',                   [CaisseManagementController::class, 'index'])->name('finance.caisses');
            $r->get('/create',             [CaisseManagementController::class, 'create']);
            $r->post('/',                  [CaisseManagementController::class, 'store']);
            $r->get('/{id}/edit',          [CaisseManagementController::class, 'edit']);
            $r->post('/{id}',              [CaisseManagementController::class, 'update']);
            $r->post('/{id}/delete',       [CaisseManagementController::class, 'destroy']);
            $r->post('/{id}/toggle',       [CaisseManagementController::class, 'toggle']);
        });

        // ─── Trésorerie ───
        $r->group(['prefix' => '/treasury', 'middleware' => ['permission:finance.treasury.view']], function ($r) {
            $r->get('/',                               [TreasuryController::class, 'dashboard'])->name('finance.treasury');
            $r->get('/transactions',                   [TreasuryController::class, 'transactions']);
            $r->get('/transaction',                    [TreasuryController::class, 'createTransaction'])->middleware('permission:finance.treasury.manage');
            $r->post('/transaction',                   [TreasuryController::class, 'storeTransaction'])->middleware('permission:finance.treasury.manage');
            $r->post('/transaction/{id}/approve',      [TreasuryController::class, 'approveTransaction'])->middleware('permission:finance.treasury.validate');
            $r->post('/transaction/{id}/reject',       [TreasuryController::class, 'rejectTransaction'])->middleware('permission:finance.treasury.validate');
            $r->get('/transfer',                       [TreasuryController::class, 'createTransfer'])->middleware('permission:finance.treasury.manage');
            $r->post('/transfer',                      [TreasuryController::class, 'storeTransfer'])->middleware('permission:finance.treasury.manage');
            $r->post('/transfer/{id}/validate',        [TreasuryController::class, 'validateTransfer'])->middleware('permission:finance.treasury.validate');
            $r->get('/closure',                        [TreasuryController::class, 'showClosure'])->middleware('permission:finance.treasury.manage');
            $r->post('/closure',                       [TreasuryController::class, 'storeClosure'])->middleware('permission:finance.treasury.manage');
            $r->get('/closures',                       [TreasuryController::class, 'closures']);
            $r->get('/closures/{id}',                  [TreasuryController::class, 'showClosure2']);
            $r->post('/closures/{id}/validate',        [TreasuryController::class, 'validateClosure'])->middleware('permission:finance.treasury.validate');

            // Quick expense — AJAX bidirectionnel (depuis fiches voyage/bus/chauffeur)
            $r->post('/quick-expense',                 [TreasuryController::class, 'quickExpense'])->middleware('permission:finance.treasury.manage');

            // Catégories de transactions
            $r->get('/categories',                     [TreasuryCategoryController::class, 'index']);
            $r->get('/categories/create',              [TreasuryCategoryController::class, 'create'])->middleware('permission:finance.treasury.manage');
            $r->post('/categories',                    [TreasuryCategoryController::class, 'store'])->middleware('permission:finance.treasury.manage');
            $r->get('/categories/{id}/edit',            [TreasuryCategoryController::class, 'edit'])->middleware('permission:finance.treasury.manage');
            $r->post('/categories/{id}',               [TreasuryCategoryController::class, 'update'])->middleware('permission:finance.treasury.manage');
            $r->post('/categories/{id}/delete',        [TreasuryCategoryController::class, 'destroy'])->middleware('permission:finance.treasury.manage');
        });

        // Comptabilité SYSCOHADA (GAP-23)
        $r->get('/accounting',                       [AccountingController::class, 'journal'])->name('finance.accounting')->middleware('permission:finance.accounting.view');
        $r->get('/accounting/export',                [AccountingController::class, 'exportCsv'])->middleware('permission:finance.accounting.export');
        $r->get('/accounting/export-sage',           [AccountingController::class, 'exportSage'])->middleware('permission:finance.accounting.export');

        // Remboursements (consultation centralisée)
        $r->get('/refunds',                          [RefundController::class, 'index'])->name('finance.refunds');
    });

    // ─── CRM passagers (GAP-02) ───
    $r->group(['prefix' => '/crm', 'middleware' => ['permission:crm.view']], function ($r) {
        $r->get('/customers',                  [CustomerController::class, 'index'])->name('crm.customers');
        $r->get('/customers/lookup',           [CustomerController::class, 'lookup']);
        $r->get('/customers/export',           [CustomerController::class, 'exportCsv'])->middleware('permission:crm.export');
        $r->post('/customers/backfill',        [CustomerController::class, 'backfill'])->middleware('permission:crm.edit');
        $r->get('/customers/{id}',             [CustomerController::class, 'show']);
        $r->post('/customers/{id}',            [CustomerController::class, 'update'])->middleware('permission:crm.edit');

        // Programme de fidélité
        $r->get('/loyalty',                    [LoyaltyController::class, 'config'])->name('crm.loyalty');
        $r->post('/loyalty/config',            [LoyaltyController::class, 'saveConfig'])->middleware('permission:crm.edit');
        $r->post('/loyalty/generate-codes',    [LoyaltyController::class, 'generateCodes'])->middleware('permission:crm.edit');
        $r->post('/loyalty/{id}/enroll',       [LoyaltyController::class, 'enroll'])->middleware('permission:crm.edit');
    });

    // ─── Fret unifié (bagages + colis) ───
    $r->group(['prefix' => '/operations/fret', 'middleware' => ['permission:fret.view']], function ($r) {
        $r->get('/',                         [FretController::class, 'index'])->name('fret.index');
        $r->get('/create',                   [FretController::class, 'create'])->middleware('permission:fret.create');
        $r->post('/',                        [FretController::class, 'store'])->middleware('permission:fret.create');
        $r->get('/calc-price',               [FretController::class, 'calcPrice']);
        $r->get('/{id}',                     [FretController::class, 'show']);
        $r->get('/{id}/talon',               [FretController::class, 'talon'])->middleware('permission:fret.print_talon');
        $r->post('/{id}/status',             [FretController::class, 'updateStatus'])->middleware('permission:fret.edit');
        $r->post('/{id}/cancel',             [FretController::class, 'cancel'])->middleware('permission:fret.cancel');
        $r->post('/{id}/pay',                [FretController::class, 'pay'])->middleware('permission:fret.edit');
        $r->post('/{id}/refund',             [FretController::class, 'refund'])->middleware('permission:fret.cancel');
    });

    // ─── Cargo / Colis (GAP-10) ───
    $r->group(['prefix' => '/cargo', 'middleware' => ['permission:cargo.view']], function ($r) {
        $r->get('/',                         [ParcelController::class, 'dashboard'])->name('cargo.dashboard');

        // Catégories fret (source unique de tarification — remplace les anciens tarifs)
        $r->get('/categories/create',        [FretCategoryController::class, 'create'])->middleware('permission:cargo.tariffs');
        $r->post('/categories',              [FretCategoryController::class, 'store'])->middleware('permission:cargo.tariffs');
        $r->get('/categories/{id}/edit',     [FretCategoryController::class, 'edit'])->middleware('permission:cargo.tariffs');
        $r->post('/categories/{id}',         [FretCategoryController::class, 'update'])->middleware('permission:cargo.tariffs');
        $r->post('/categories/{id}/delete',  [FretCategoryController::class, 'destroy'])->middleware('permission:cargo.tariffs');

        // Colis
        $r->get('/parcels',                  [ParcelController::class, 'index'])->name('cargo.parcels');
        $r->get('/parcels/create',           [ParcelController::class, 'create']);
        $r->post('/parcels',                 [ParcelController::class, 'store']);
        $r->get('/parcels/quote',            [ParcelController::class, 'quote']);
        $r->post('/parcels/lookup',          [ParcelController::class, 'lookup']);
        $r->get('/parcels/{id}',             [ParcelController::class, 'show']);
        $r->get('/parcels/{id}/label',       [ParcelController::class, 'label']);
        $r->post('/parcels/{id}/load',       [ParcelController::class, 'loadOnTrip']);
        $r->post('/parcels/{id}/arrive',     [ParcelController::class, 'markArrived']);
        $r->post('/parcels/{id}/pickup',     [ParcelController::class, 'pickup']);
        $r->post('/parcels/{id}/issue',      [ParcelController::class, 'reportIssue']);
        $r->post('/parcels/{id}/cancel',     [ParcelController::class, 'cancel']);

        // Manifeste cargo d'un voyage
        $r->get('/manifest/{tripId}',        [ParcelController::class, 'manifest']);
    });


    // ─── Media (système réutilisable — auth seul) ───
    $r->group(['prefix' => '/media'], function ($r) {
        $r->get('/{id}/file',    [MediaController::class, 'serve']);
        $r->get('/{id}/thumb',   [MediaController::class, 'thumb']);
        $r->post('/upload',      [MediaController::class, 'upload']);
        $r->post('/{id}/update', [MediaController::class, 'update']);
        $r->post('/{id}/delete', [MediaController::class, 'destroy']);
        $r->post('/reorder',     [MediaController::class, 'reorder']);
    });

    // ════════════════════════════════════════════════════════════════
    // V4 — toutes nouvelles routes
    // ════════════════════════════════════════════════════════════════

    // V4.A — O-D fares
    $r->group(['prefix' => '/pnr/od-fares', 'middleware' => ['permission:od_fares.view']], function ($r) {
        $r->get('/',                   [OdFareController::class, 'index']);
        $r->post('/bulk',              [OdFareController::class, 'bulkGenerate'])->middleware('permission:od_fares.manage');
        $r->post('/{id}',              [OdFareController::class, 'update'])->middleware('permission:od_fares.manage');
        $r->post('/{id}/delete',       [OdFareController::class, 'destroy'])->middleware('permission:od_fares.manage');
    });

    // V4.B — CRM V4
    $r->group(['prefix' => '/crm', 'middleware' => ['permission:crm.customers.view']], function ($r) {
        $r->get('/customers/{id}',         [CustomerV4Controller::class, 'show']);
        $r->get('/duplicates',             [CustomerV4Controller::class, 'duplicates']);
        $r->post('/customers/merge',       [CustomerV4Controller::class, 'merge'])->middleware('permission:crm.customers.merge');
        $r->get('/rfm',                    [CustomerV4Controller::class, 'rfm']);
        $r->get('/complaints',             [CustomerV4Controller::class, 'complaintsIndex'])->middleware('permission:crm.complaints.view');
        $r->post('/complaints',            [CustomerV4Controller::class, 'complaintsOpen'])->middleware('permission:crm.complaints.manage');
        $r->post('/complaints/{id}/resolve', [CustomerV4Controller::class, 'complaintsResolve'])->middleware('permission:crm.complaints.manage');
        $r->post('/complaints/{id}/close',   [CustomerV4Controller::class, 'complaintsClose'])->middleware('permission:crm.complaints.manage');
    });

    // V4.C — Finance V4
    $r->group(['prefix' => '/finance', 'middleware' => ['permission:finance.invoices.view']], function ($r) {
        $r->get('/invoices',              [InvoiceController::class, 'index']);
        $r->get('/invoices/{id}',         [InvoiceController::class, 'show']);
        $r->post('/invoices/{id}/pay',    [InvoiceController::class, 'markPaid'])->middleware('permission:finance.invoices.create');
        $r->post('/invoices/{id}/void',   [InvoiceController::class, 'void'])->middleware('permission:finance.invoices.cancel');
        $r->get('/tax/declaration',       [InvoiceController::class, 'vatDeclaration'])->middleware('permission:finance.tax.declare');
        $r->get('/tax/export',            [InvoiceController::class, 'vatExportCsv'])->middleware('permission:finance.tax.declare');
    });
    $r->group(['prefix' => '/finance/accounting-v4', 'middleware' => ['permission:finance.accounting.view']], function ($r) {
        $r->get('/journal',               [AccountingV4Controller::class, 'journal']);
        $r->get('/ledger',                [AccountingV4Controller::class, 'ledger']);
        $r->get('/balance',               [AccountingV4Controller::class, 'balance']);
        $r->get('/export',                [AccountingV4Controller::class, 'exportCsv'])->middleware('permission:finance.accounting.export');
        $r->post('/{id}/post',            [AccountingV4Controller::class, 'post'])->middleware('permission:finance.accounting.post');
    });
    $r->group(['prefix' => '/finance/pnl-v4', 'middleware' => ['permission:finance.pnl.view']], function ($r) {
        $r->get('/',                      [AccountingV4Controller::class, 'pnlIndex']);
        $r->post('/trip/{tripId}',        [AccountingV4Controller::class, 'pnlTrip'])->middleware('permission:finance.pnl.recompute');
    });

    // V4.D — Caisse drawers
    $r->group(['prefix' => '/caisse/drawer', 'middleware' => ['permission:caisse.drawers.view']], function ($r) {
        $r->get('/',                      [CashDrawerController::class, 'index']);
        $r->post('/open',                 [CashDrawerController::class, 'open'])->middleware('permission:caisse.drawers.open');
        $r->post('/{id}/close',           [CashDrawerController::class, 'close'])->middleware('permission:caisse.drawers.close');
    });

    // V4.G — Cargo V4
    $r->group(['prefix' => '/cargo-v4', 'middleware' => ['permission:cargo.scan']], function ($r) {
        $r->post('/{parcelId}/event',     [CargoV4Controller::class, 'event']);
        $r->post('/{parcelId}/pod',       [CargoV4Controller::class, 'pod'])->middleware('permission:cargo.deliver');
        $r->post('/{parcelId}/cod',       [CargoV4Controller::class, 'cod'])->middleware('permission:cargo.cod.collect');
        $r->get('/{parcelId}/label',      [CargoV4Controller::class, 'label']);
    });


    // V4.J — Analytics
    $r->group(['prefix' => '/analytics', 'middleware' => ['permission:kpi.view']], function ($r) {
        $r->get('/kpi',                   [KpiController::class, 'dashboard']);
        $r->get('/forecast',              [KpiController::class, 'forecast'])->middleware('permission:forecast.view');
    });
});
