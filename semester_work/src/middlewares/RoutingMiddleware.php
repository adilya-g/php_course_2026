<?php

namespace MyApp\middlewares;

use MyApp\entities\Request;
use MyApp\Logging\FileLogger;
use MyApp\routing\ClassesGetter;
use MyApp\routing\Router;

class RoutingMiddleware implements IMiddleware
{
    private Router $router;

    function __construct(Router $router)
    {
        $this->router = $router;
    }
    public function handle(Request $request, $next)
    {

        $routes = ClassesGetter::getClasses();
        $this->router->register($routes);
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
        $routeFound = $this->router->dispatch($requestUri, $requestMethod);
        if(!$routeFound)
        {
            return $next($request);
        }
    }
}