<?php

session_start(); // обязательно для хранения токена

require_once 'vendor/autoload.php';

// Настройки клиента
$client = new Google\Client();
$client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
$client->setAuthConfig('client_secret.json'); // файл с учётными данными из Google Cloud Console
$client->addScope(Google\Service\Gmail::GMAIL_READONLY); // доступ только для чтения писем
// Можно использовать другие области: GMAIL_SEND, GMAIL_MODIFY, GMAIL_COMPOSE, MAIL_GOOGLE_COM и т.д.
$client->setRedirectUri('https://localhost:8000/index.php'); // точно такой же URI, как указано в консоли
$client->setAccessType('offline'); // чтобы получить refresh token
$client->setPrompt('consent'); // всегда показывать экран согласия (полезно для тестирования)

// 1. Если нет кода авторизации – отправляем пользователя на consent screen
if (!isset($_GET['code'])) {
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
    exit;
}

// 2. Есть код – обмениваем его на токены
if (isset($_GET['code'])) {
    $client->authenticate($_GET['code']);
    $_SESSION['access_token'] = $client->getAccessToken();
    // Здесь можно сохранить токен в базу данных для постоянного использования
    // Перенаправляем на эту же страницу без ?code, чтобы очистить параметр
    header('Location: ' . filter_var($client->getRedirectUri(), FILTER_SANITIZE_URL));
    exit;
}

// 3. Если токен уже есть в сессии – используем его
if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
    $client->setAccessToken($_SESSION['access_token']);

    // Проверяем, не истёк ли токен
    if ($client->isAccessTokenExpired()) {
        // Если есть refresh token – обновляем
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $_SESSION['access_token'] = $client->getAccessToken();
        } else {
            // Refresh token отсутствует – нужно заново авторизоваться
            unset($_SESSION['access_token']);
            header('Location: ' . filter_var($client->getRedirectUri(), FILTER_SANITIZE_URL));
            exit;
        }
    }

    // Токен готов – можно работать с Gmail API
    $service = new Google_Service_Gmail($client);

    // Пример: получить список писем (первые 10)
    $user = 'me';
    $messages = $service->users_messages->listUsersMessages($user, ['maxResults' => 10]);
    foreach ($messages->getMessages() as $msg) {
        echo 'Message ID: ' . $msg->getId() . '<br>';
    }

} else {
    // Нет токена – отправляем на авторизацию
    header('Location: ' . filter_var($client->getRedirectUri(), FILTER_SANITIZE_URL));
    exit;
}
