<?php

namespace MyApp\routing;

use MyApp\DIContainer\Container;
use MyApp\Logging\FileLogger;
use ReflectionClass;
use MyApp\attributes\Route;

class Router
{
    private array $routes = [];
    private FileLogger $logger;
    private Container $container;

    function __construct(FileLogger $logger, Container $container)
    {
        $this->logger = $logger;
        $this->container = $container;
    }
    public function addRoute(string $method, string $pattern, string $controller, string $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'controller' => $controller,
            'action' => $action
        ];
    }

    public function register(array $classes)
    {

        foreach ($classes as $class)
        {
            $reflector = new ReflectionClass($class);
            $methods = $reflector->getMethods();

            foreach ($methods as $method)
            {
                $attributes = $method->getAttributes(Route::class);

                foreach ($attributes as $attribute)
                {
                    $route = $attribute->newInstance();

                    $path = $route->path;
                    $httpMethods = $route->methods;
                    $controller = $class;
                    $action = $method->getName();
                    $this->logger->info("path: $path");
                    $this->addRoute($httpMethods[0], $path, $controller, $action);
                }
            }
        }
    }
    
    private function matchPattern(string $pattern, string $uri): bool
    {
        if ($pattern === $uri) {
            return true;
        }
        
        $regex = '#^' . preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern) . '$#';
        return preg_match($regex, $uri) === 1;
    }
    
    private function extractParams(string $pattern, string $uri): array
    {
        $regex = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern) . '$#';
        
        if (preg_match($regex, $uri, $matches)) {
            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }
        
        return [];
    }
    
    private function getHttpMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        
        return $method;
    }
    
    private function getRequestParams(): array
    {
        $params = [];
        
        if (!empty($_GET)) {
            $params['query'] = $_GET;
        }
        
        if (!empty($_POST)) {
            $params['post'] = $_POST;
        }
        
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $jsonData = json_decode($input, true);
            if ($jsonData) {
                $params['json'] = $jsonData;
            }
        }
        
        return $params;
    }
    
    public function dispatch(string $uri, ?string $method = null)
    {
        $method = $method ?? $this->getHttpMethod();
        
        if ($uri === '' || $uri === '/public/index.php') {
            $uri = '/';
        }
        $uri = parse_url($uri, PHP_URL_PATH);
        $routeFound = false;
        
        foreach ($this->routes as $route) {
            
            if ($route['method'] !== $method) {
                continue;
            }
            
            if ($this->matchPattern($route['pattern'], $uri)) {
                $routeFound = true;
                $params = $this->extractParams($route['pattern'], $uri);
                $params['request'] = $this->getRequestParams();
                
                $controllerClass =  $route['controller'];
                $actionName = $route['action'];
                
                if (!class_exists($controllerClass)) {
                    echo $controllerClass . " does not exist\n";
                    return;
                }
                
                $controller = $this->container->get($controllerClass);
                
                if (!method_exists($controller, $actionName)) {
                    return;
                }

                $controller->$actionName($params);
                return;
            }
        }
        
        if (!$routeFound) {
            return false;
        }
    }
}