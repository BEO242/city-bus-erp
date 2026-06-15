<?php
/**
 * City Bus ERP — Front Controller
 * Point d'entrée unique de l'application.
 */

declare(strict_types=1);

// Serveur PHP intégré : servir les fichiers statiques directement
if (php_sapi_name() === 'cli-server') {
    $staticFile = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($staticFile)) {
        return false; // Laisser le serveur built-in servir le fichier
    }
}

define('CITYBUS_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));

// 1. Autoload Composer
require BASE_PATH . '/vendor/autoload.php';

// 2. Bootstrap application
$app = require BASE_PATH . '/src/bootstrap.php';

// 3. Charger les routes
require BASE_PATH . '/src/routes.php';

// 4. Dispatcher la requête
try {
    $app->run();
} catch (\Throwable $e) {
    \CityBus\Core\ErrorHandler::handle($e);
}
