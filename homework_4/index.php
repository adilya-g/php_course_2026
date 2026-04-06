<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (preg_match('/\.(?:png|jpg|jpeg|gif|css|html?|js)$/', $_SERVER["REQUEST_URI"])) {
    return false;
    
}

$routes = [
    'GET' => [
        '/create' => function($params){
            $error = $_SESSION['error'] ?? '';
            $old = $_SESSION['old'] ?? [];
            unset($_SESSION['error'], $_SESSION['old']);
            
            $errorHtml = '';
            if ($error) {
                $errorHtml = '<div style="color: red; margin-bottom: 10px;">' . htmlspecialchars($error) . '</div>';
            }
            
            return '<form method="post" action="/create" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                ' . $errorHtml . '
                <span>Название:</span>
                <input name="title" type="text" value="' . htmlspecialchars($old['title'] ?? '') . '"><br>
                <span>Текст:</span>
                <textarea name="comment">' . htmlspecialchars($old['comment'] ?? '') . '</textarea><br>
                <input type="file" name="attachment" accept=".jpg,.png"><br>
                <button type="submit">Создать</button>
            </form>';
        },
        '/edit' => function($params){
            if (!isset($_SESSION['tasks'][$params['id']])) {
                header('Location: /getAll');
                exit();
            }
            $task = $_SESSION['tasks'][$params['id']];
            $currentImage = '';
            if (!empty($task['file']) && file_exists($task['file'])) {
                $currentImage = '<img src="' . htmlspecialchars($task['file']) . '" width="200" alt="Текущее изображение"><br>';
            }
            
            $error = $_SESSION['error'] ?? '';
            $old = $_SESSION['old'] ?? [];
            unset($_SESSION['error'], $_SESSION['old']);
            
            $errorHtml = '';
            if ($error) {
                $errorHtml = '<div style="color: red; margin-bottom: 10px;">' . htmlspecialchars($error) . '</div>';
            }
            
            return '<form method="post" action="/edit" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                <input type="hidden" name="id" value="' . $params['id'] . '">
                ' . $errorHtml . '
                <span>Название:</span>
                <input name="title" type="text" value="' . htmlspecialchars($old['title'] ?? $task['title']) . '"><br>
                <span>Текст:</span>
                <textarea name="comment">' . htmlspecialchars($old['comment'] ?? $task['text']) . '</textarea><br>
                ' . $currentImage . '
                <input type="file" name="attachment" accept=".jpg,.png"><br>
                <button type="submit">Сохранить</button>
            </form>';
        },
        '/getAll' => function($params) {
            $tasks = $_SESSION['tasks'] ?? [];
            $html = '<h1>Список задач</h1>';
            $html .= '<a href="/create">Создать задачу</a><br>';
            
            if (empty($tasks)) {
                $html .= '<p>Задач нет</p>';
                return $html;
            }
            
            foreach ($tasks as $key => $task) {
                $html .= '<h3>Название: ' . htmlspecialchars($task['title']) . '</h3>';
                $html .= '<p>Текст: ' . htmlspecialchars($task['text']) . '</p>';
                if (!empty($task['file']) && file_exists($task['file'])) {
                    $html .= '<img src="' . htmlspecialchars($task['file']) . '" width="200" alt="Изображение"><br>';
                } else {
                    $html .= '<p>Нет изображения</p>';
                }
                $html .= '<a href="/edit?id=' . $key . '">Редактировать</a><br><hr>';
            }
            return $html;
        },
    ],
    'POST' => [
        '/create' => function($params){
            if (empty($params['csrf_token']) || empty($_SESSION['csrf_token']) || 
                !hash_equals($_SESSION['csrf_token'], $params['csrf_token'])) {
                http_response_code(403);
                exit('CSRF token invalid');
            }
            
            $title = isset($params['title']) ? trim($params['title']) : '';
            $text = isset($params['comment']) ? trim($params['comment']) : '';
            $error = '';
            
            if (empty($title)) {
                $error = 'Название обязательно';
            } elseif (strlen($title) < 3) {
                $error = 'Название должно содержать минимум 3 символа';
            } elseif (strlen($title) > 100) {
                $error = 'Название должно содержать максимум 100 символов';
            }
            
            if (empty($error) && empty($text)) {
                $error = 'Текст обязателен';
            } elseif (empty($error) && strlen($text) < 10) {
                $error = 'Текст должен содержать минимум 10 символов';
            }
            
            $file = $_FILES['attachment'] ?? null;
            $fileName = '';
            
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                if ($file['size'] > 5*1024*1024) {
                    $error = 'Файл слишком большой. Максимум 5MB';
                } else {
                    $allowed = ['jpg', 'jpeg', 'png'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) {
                        $error = 'Разрешены только JPG и PNG файлы';
                    }
                }
            }
            
            if ($error) {
                $_SESSION['error'] = $error;
                $_SESSION['old'] = ['title' => $title, 'comment' => $text];
                header('Location: /create');
                exit;
            }
            
            if ($file && $file['error'] === UPLOAD_ERR_OK && $file['size'] < 5*1024*1024) {
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'uploads/' . uniqid() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $fileName);
            }
            
            $_SESSION['tasks'][] = ['title' => $title, 'text' => $text, 'file' => $fileName];
            header('Location: /getAll');
            exit();
        },
        '/edit' => function($params){
            if (empty($params['csrf_token']) || empty($_SESSION['csrf_token']) || 
                !hash_equals($_SESSION['csrf_token'], $params['csrf_token'])) {
                http_response_code(403);
                exit('CSRF token invalid');
            }
            
            $id = $params['id'] ?? null;
            if ($id === null || !isset($_SESSION['tasks'][$id])) {
                header('Location: /getAll');
                exit();
            }
            
            $title = isset($params['title']) ? trim($params['title']) : '';
            $text = isset($params['comment']) ? trim($params['comment']) : '';
            $error = '';
            
            if (empty($title)) {
                $error = 'Название обязательно';
            } elseif (strlen($title) < 3) {
                $error = 'Название должно содержать минимум 3 символа';
            } elseif (strlen($title) > 100) {
                $error = 'Название должно содержать максимум 100 символов';
            }
            
            if (empty($error) && empty($text)) {
                $error = 'Текст обязателен';
            } elseif (empty($error) && strlen($text) < 10) {
                $error = 'Текст должен содержать минимум 10 символов';
            }
            
            $file = $_FILES['attachment'] ?? null;
            $fileName = $_SESSION['tasks'][$id]['file'] ?? '';
            
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                if ($file['size'] > 5*1024*1024) {
                    $error = 'Файл слишком большой. Максимум 5MB';
                } else {
                    $allowed = ['jpg', 'jpeg', 'png'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) {
                        $error = 'Разрешены только JPG и PNG файлы';
                    }
                }
            }
            
            if ($error) {
                $_SESSION['error'] = $error;
                $_SESSION['old'] = ['title' => $title, 'comment' => $text];
                header('Location: /edit?id=' . $id);
                exit;
            }
            
            if ($file && $file['error'] === UPLOAD_ERR_OK && $file['size'] < 5*1024*1024) {
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                if (!empty($fileName) && file_exists($fileName)) {
                    unlink($fileName);
                }
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'uploads/' . uniqid() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $fileName);
            }
            
            $_SESSION['tasks'][$id] = ['title' => $title, 'text' => $text, 'file' => $fileName];
            header('Location: /getAll');
            exit();
        }
    ]
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