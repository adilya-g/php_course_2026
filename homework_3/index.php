<?php
$routes = 
[
    'GET' => [
        '/' => function($params){
            return '<h1>Главная страница</h1>
                    <p>Добро пожаловать!</p>
                    <ul>
                        <li><a href="/form">Перейти к форме обратной связи</a></li>
                    </ul>';
        },
        '/form' => function($params){
            return '<h1>Форма обратной связи</h1>
                <form method="POST" action="/form">
                    <div class="form-group">
                        <label for="name">Имя:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <button type="submit">Отправить</button>
                </form>
                <p><a href="/">На главную</a></p>';
        },
    ],
    'POST' => [
        '/form' => function($params){
            $name = isset($params['name']) ? htmlspecialchars(trim($params['name'])) : '';
            $email = isset($params['email']) ? htmlspecialchars(trim($params['email'])) : '';

            return '<div class="data">
                        <h3>Вы отправили:</h3>
                        <p><strong>Имя:</strong> ' . $name . '</p>
                        <p><strong>Email:</strong> ' . $email . '</p>
                        <p><a href="/form">Назад к форме</a> | <a href="/">На главную</a></p>
                    </div>';
        }
    ],    
];

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$params = [];
if ($method === 'GET') {
    $params = $_GET;
} elseif ($method === 'POST') {
    $params = $_POST;
}

if (isset($routes[$method][$uri])) {
    $callback = $routes[$method][$uri];
    echo $callback($params);
} else {
    http_response_code(404);
    echo '404 - Страница не найдена';
}