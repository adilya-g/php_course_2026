<?php

require_once __DIR__ . '/../autoload.php';

use MyApp\Router;

session_start();

$router = new Router();

$routes = require __DIR__ . '/../src/routes.php';

foreach ($routes as $routeConfig) {
    $router->addRoute(
        $routeConfig['method'],
        $routeConfig['pattern'],
        $routeConfig['controller'],
        $routeConfig['action']
    );
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$basePath = '';
if (strpos($requestUri, '/public/index.php') === 0) {
    $basePath = '/public/index.php';
} elseif (strpos($requestUri, '/index.php') === 0) {
    $basePath = '/index.php';
}

$requestUri = str_replace($basePath, '', $requestUri);
$requestUri = $requestUri === '' ? '/' : $requestUri;

$requestMethod = $_SERVER['REQUEST_METHOD'];

$router->dispatch($requestUri, $requestMethod);