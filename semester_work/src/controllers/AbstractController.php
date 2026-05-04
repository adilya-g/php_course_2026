<?php

namespace MyApp\Controllers;

abstract class AbstractController
{
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function getParam(string $key, $default = null, array $params = [])
    {
        if (isset($params[$key])) {
            return $params[$key];
        }

        if (isset($params['request']['query'][$key])) {
            return $params['request']['query'][$key];
        }

        if (isset($params['request']['post'][$key])) {
            return $params['request']['post'][$key];
        }

        return $default;
    }

    protected function isMethod(string $method, array $params): bool
    {
        $currentMethod = $_SERVER['REQUEST_METHOD'];

        if ($currentMethod === 'POST' && isset($_POST['_method'])) {
            $currentMethod = strtoupper($_POST['_method']);
        }

        return strtoupper($method) === $currentMethod;
    }

    protected function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    protected function render($templatePath, $data = [])
    {
        $template = file_get_contents(__DIR__ . '/../../public/' . $templatePath);
        extract($data, EXTR_SKIP);
        ob_start();
        eval('?>' . $template . '<?php ');
        $content = ob_get_clean();
        return $content;
    }
}
