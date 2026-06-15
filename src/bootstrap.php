<?php
/**
 * Bootstrap de l'application City Bus ERP
 */

declare(strict_types=1);

use CityBus\Core\App;
use CityBus\Core\Config;
use CityBus\Core\ErrorHandler;
use CityBus\Core\Session;
use CityBus\Core\Database;
use Dotenv\Dotenv;

// 1. Variables d'environnement
if (file_exists(BASE_PATH . '/.env')) {
    Dotenv::createImmutable(BASE_PATH)->load();
}

// 2. Configuration
Config::load(BASE_PATH . '/config');

// 3. Timezone & locale
date_default_timezone_set(Config::get('app.timezone', 'Africa/Brazzaville'));
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'French');
mb_internal_encoding('UTF-8');

// 4. Gestion erreurs
ErrorHandler::register();

// 5. Session
Session::start();

// 5b. Headers de sécurité (CSP, HSTS, X-Frame-Options, etc.)
\CityBus\Core\SecurityHeaders::send();

// 6. DB (lazy: la connexion s'ouvre à la 1re requête)
Database::configure(Config::get('database'));

// 7. App principale
return new App();
