<?php

namespace MyApp\Controllers;

class StaticPageController extends AbstractController
{
    public function show(array $params = []): void
    {
        $page = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        if ($page === '') {
            $page = 'index';
        }

        $viewFile = __DIR__ . '/../../public/' . $page . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(404);
            $errorFile = __DIR__ . '/../../public/404.php';
            if (file_exists($errorFile)) {
                require $errorFile;
            } else {
                echo '<h1>404 Not Found</h1><p>Страница не найдена.</p>';
            }
            return;
        }

        $title = $this->getPageTitle($page);
        $currentYear = date('Y');

        require $viewFile;
    }

    private function getPageTitle(string $page): string
    {
        $titles = [
            'index'    => 'Главная',
            'about'    => 'О нас',
            'contacts' => 'Контакты',
            'privacy'  => 'Политика конфиденциальности',
        ];

        return $titles[$page] ?? 'Страница';
    }

    public function sendContactForm(array $params = []): void
    {
        if (!$this->isMethod('POST', $params)) {
            $this->redirect('/contacts');
            return;
        }

        $token = $this->getParam('csrf_token', '', $params);
        if (!$this->verifyCsrfToken($token)) {
            $this->jsonResponse(['error' => 'Недействительный CSRF токен'], 403);
            return;
        }

        $name    = $this->getParam('name', '', $params);
        $email   = $this->getParam('email', '', $params);
        $message = $this->getParam('message', '', $params);

        $errors = [];
        if (empty($name)) $errors['name'] = 'Имя обязательно';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Некорректный email';
        if (empty($message)) $errors['message'] = 'Сообщение не может быть пустым';

        if (!empty($errors)) {
            $this->jsonResponse(['errors' => $errors], 422);
            return;
        }


        if ($this->isAjaxRequest()) {
            $this->jsonResponse(['success' => 'Сообщение отправлено!']);
        } else {
            $_SESSION['flash_message'] = 'Спасибо! Ваше сообщение отправлено.';
            $this->redirect('/contacts');
        }
    }


    private function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
               && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}