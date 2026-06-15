<?php

declare(strict_types=1);

return [
    'name'     => $_ENV['SESSION_NAME']     ?? 'citybus_session',
    'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 120) * 60,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Strict',
    'storage'  => BASE_PATH . '/storage/sessions',
];
