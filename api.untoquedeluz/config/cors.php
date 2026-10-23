<?php

$allowedOrigins = [env('FRONTEND_URL', 'http://localhost:5173')];

if (in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)) {
    $allowedOrigins[] = 'http://localhost:5173';
    $allowedOrigins[] = 'http://127.0.0.1:5173';
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_unique($allowedOrigins)),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
