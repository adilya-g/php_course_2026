<?php
$routes = 
[
    '/print/hello' => function(){
        echo 'Hello!';
    },
    '/print/world' => function(){
        echo 'World';
    },
    '/home' => function(){
        echo 'You`re on home page!';
    }
];

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (isset($routes[$uri])) {
    $routes[$uri]();
} else {
    http_response_code(404);
    echo '404 - Страница не найдена';
}
