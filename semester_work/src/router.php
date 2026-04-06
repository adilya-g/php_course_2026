<?php

namespace MyApp;

class Router
{
    private array $routes = [];
    
    public function addRoute(string $method, string $pattern, string $controller, string $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'controller' => $controller,
            'action' => $action
        ];
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
    
    public function dispatch(string $uri, ?string $method = null): void
    {
        $method = $method ?? $this->getHttpMethod();
        
        if ($uri === '' || $uri === '/public/index.php') {
            $uri = '/';
        }
        
        echo "Debug: Trying to match URI: '{$uri}' with method: {$method}<br>";
        
        $routeFound = false;
        
        foreach ($this->routes as $route) {
            echo "Debug: Checking route: {$route['method']} - {$route['pattern']}<br>";
            
            if ($route['method'] !== $method) {
                continue;
            }
            
            if ($this->matchPattern($route['pattern'], $uri)) {
                $routeFound = true;
                $params = $this->extractParams($route['pattern'], $uri);
                $params['request'] = $this->getRequestParams();
                
                $controllerClass = 'MyApp\\Controllers\\' . $route['controller'];
                $actionName = $route['action'];
                
                echo "Debug: Found controller: {$controllerClass}::{$actionName}<br>";
                
                if (!class_exists($controllerClass)) {
                    echo "Error: Controller class {$controllerClass} not found<br>";
                    return;
                }
                
                $controller = new $controllerClass();
                
                if (!method_exists($controller, $actionName)) {
                    echo "Error: Method {$actionName} not found in {$controllerClass}<br>";
                    return;
                }
                
                $controller->$actionName($params);
                return;
            }
        }
        
        if (!$routeFound) {
            http_response_code(404);
            echo "Route not found for URI: '{$uri}' with method: {$method}<br>";
            echo "<br>Available routes:<br>";
            foreach ($this->routes as $route) {
                echo "- {$route['method']} : {$route['pattern']}<br>";
            }
        }
    }
}