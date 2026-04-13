<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MyApp\Router;
use Dotenv\Dotenv;
use MyApp\Logging\LoggerFactory;
use MyApp\Exceptions\AppException;

$dotenvPath = __DIR__ . '/../';
if (file_exists($dotenvPath . '.env')) {
    $dotenv = Dotenv::createImmutable($dotenvPath);
    $dotenv->load();
}

$debugMode = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

LoggerFactory::init($debugMode, __DIR__ . '/../storage/logs/app.log');

set_error_handler(function ($level, $message, $file, $line) {
    if (!(error_reporting() & $level)) {
        return false;
    }
    throw new ErrorException($message, 0, $level, $file, $line);
});

set_exception_handler(function (Throwable $exception) {
    $logger = LoggerFactory::getLogger();
    $context = $exception instanceof AppException ? $exception->getContext() : [];
    
    $logger->error($exception->getMessage(), array_merge([
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ], $context));

    http_response_code(500);
    
    if (LoggerFactory::isDebugMode()) {
        // В dev-режиме показываем подробности
        echo '<h1>Ошибка 500</h1>';
        echo '<p><strong>' . htmlspecialchars($exception->getMessage()) . '</strong></p>';
        echo '<pre>' . $exception->getTraceAsString() . '</pre>';
    } else {
        // На проде показываем статичную страницу
        $errorPage = __DIR__ . '/500.html';
        if (file_exists($errorPage)) {
            readfile($errorPage);
        } else {
            echo '<h1>Внутренняя ошибка сервера</h1><p>Попробуйте позже.</p>';
        }
    }
    exit;
});

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

try {
    $router->dispatch($requestUri, $requestMethod);
} catch (Throwable $e) {
    throw $e;
}