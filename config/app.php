<?php

declare(strict_types=1);

return [
    'name'     => $_ENV['APP_NAME'] ?? 'City Bus ERP',
    'env'      => $_ENV['APP_ENV'] ?? 'production',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'      => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Africa/Brazzaville',
    'locale'   => $_ENV['APP_LOCALE'] ?? 'fr',
    'key'      => $_ENV['APP_KEY'] ?? '',
];
