<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
            $error = $_SESSION['error'] ?? '';
            $old = $_SESSION['old'] ?? [];
            unset($_SESSION['error'], $_SESSION['old']);
            
            $html = '<h1>Форма обратной связи</h1>';
            if ($error) {
                $html .= '<div style="color: red; margin-bottom: 10px;">' . $error . '</div>';
            }
            
            $html .= '<form method="POST" action="/form">
                <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">
                
                <div class="form-group">
                    <label for="name">Имя:</label>
                    <input type="text" id="name" name="name" value="'.($old['name'] ?? '').'">
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="'.($old['email'] ?? '').'">
                </div>
                <button type="submit">Отправить</button>
            </form>
            <p><a href="/">На главную</a></p>';
            
            return $html;
        },
    ],
    'POST' => [
        '/form' => function($params){
            if (!isset($params['csrf_token']) || $params['csrf_token'] !== $_SESSION['csrf_token']) {
                die('Ошибка безопасности');
            }
            
            $name = isset($params['name']) ? trim($params['name']) : '';
            $email = isset($params['email']) ? trim($params['email']) : '';
            
            $error = '';
            if (empty($name)) {
                $error = 'Имя обязательно';
            } elseif (strlen($name) < 3) {
                $error = 'Имя должно содержать минимум 3 символа';
            } elseif (empty($email)) {
                $error = 'Email обязателен';
            } elseif (strlen($email) < 3) {
                $error = 'Email должен содержать минимум 3 символа';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Введите корректный email';
            }
            
            if ($error) {
                $_SESSION['error'] = $error;
                $_SESSION['old'] = ['name' => $name, 'email' => $email];
                header('Location: /form');
                exit;
            }
            
            $name = htmlspecialchars($name);
            $email = htmlspecialchars($email);
            
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
