<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use MyApp\Controllers\AuthController;
use MyApp\controllers\EmailController;
use MyApp\Controllers\HomeController;
use MyApp\database\database;
use MyApp\DIContainer\Container;
use MyApp\DIContainer\ContainerInterface;
use MyApp\entities\Request;
use MyApp\Exceptions\AppException;
use MyApp\Logging\FileLogger;
use MyApp\Logging\LoggerFactory;
use MyApp\Middlewares\AuthMiddleware;
use MyApp\middlewares\MiddlewarePipeline;
use MyApp\middlewares\RoutingMiddleware;
use MyApp\middlewares\StaticFileMiddleware;
use MyApp\repositories\implementations\MailRepository;
use MyApp\repositories\implementations\TokenRepository;
use MyApp\repositories\implementations\UserRepository;
use MyApp\repositories\interfaces\IMailRepository;
use MyApp\repositories\interfaces\ITokenRepository;
use MyApp\repositories\interfaces\IUserRepository;
use MyApp\routing\ClassesGetter;
use MyApp\routing\Router;
use MyApp\middlewares;
use MyApp\attributes\Route;
use MyApp\Services\AuthService;
use MyApp\Services\GmailService;
use MyApp\Services\UserService;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');


try {
    $dotenvPath = __DIR__ . '/../';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $servicesContainer = new Container();
    $servicesContainer->singleton(FileLogger::class);

    $servicesContainer->singleton(database::class);
    $servicesContainer->singleton(PDO::class);


    $servicesContainer->bindSingleton(ITokenRepository::class, TokenRepository::class);
    $servicesContainer->bindSingleton(IMailRepository::class, MailRepository::class);
    $servicesContainer->bindSingleton(IUserRepository::class, UserRepository::class);

    $servicesContainer->singleton(AuthService::class);
    $servicesContainer->singleton(GmailService::class);
    $servicesContainer->singleton(UserService::class);

    $servicesContainer->singleton(AuthMiddleware::class);
    $servicesContainer->singleton(RoutingMiddleware::class);
    $servicesContainer->singleton(StaticFileMiddleware::class);

    $servicesContainer->setClass(AuthController::class);
    $servicesContainer->setClass(EmailController::class);
    $servicesContainer->setClass(HomeController::class);

    $servicesContainer->singleton(Router::class);

    if (file_exists($dotenvPath . '.env')) {
        $dotenv = Dotenv::createImmutable($dotenvPath);
        $dotenv->load();
    }


    set_exception_handler(function ($exception) use ($servicesContainer) {
        $servicesContainer->get(FileLogger::class)->error(
            $exception->getMessage(),
            [
                $exception->getCode(),
                $exception->getFile(),
                $exception->getLine()
            ]
        );
        echo "An error occurred: " . $exception->getMessage();
    });


    $request = new Request();
    $request->params = $_GET ?? $_POST;
    $request->sessionData = $_SESSION ?? [];
    $request->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $request->uri = $_SERVER['REQUEST_URI'] ?? '/';
    $request->container = $servicesContainer;

    $pipeline = new MiddlewarePipeline()->useMiddleware(AuthMiddleware::class, $servicesContainer)
        ->useMiddleware(RoutingMiddleware::class, $servicesContainer)
        ->useMiddleware(middlewares\StaticFileMiddleware::class, $servicesContainer);
    $pipeline->executeAsync($request);


} catch (Exception $exception) {
    echo $exception->getMessage();
}