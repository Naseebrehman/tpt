<?php
/** Front controller for clean URLs; legacy .php entry points remain supported. */
require_once __DIR__ . '/core/bootstrap.php';
$router = require __DIR__ . '/app/routes.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (BASE_URL !== '' && strpos($path, BASE_URL . '/') === 0) { $path = substr($path, strlen(BASE_URL)); }
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
