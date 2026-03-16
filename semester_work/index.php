<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';
session_start();

$client = new Google\Client();
$client->setHttpClient(new \GuzzleHttp\Client(['verify' => false])); 
$client->setAuthConfig('client_secret.json');
$client->addScope(Google\Service\Gmail::GMAIL_READONLY);
$client->setRedirectUri('http://localhost:8000/index.php');
$client->setAccessType('offline');

function getHeader($headers, $name) {
    foreach ($headers as $header) {
        if ($header->getName() === $name) {
            return $header->getValue();
        }
    }
    return null;
}

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
}

if (isset($_GET['code'])) {
    try {
        $client->authenticate($_GET['code']);
        $_SESSION['access_token'] = $client->getAccessToken();
        exit;
    } catch (Exception $e) {
        error_log("Auth error: " . $e->getMessage());
        exit;
    }
}

if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
    $client->setAccessToken($_SESSION['access_token']);

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $_SESSION['access_token'] = $client->getAccessToken();
        } else {
            unset($_SESSION['access_token']);
            exit;
        }
    }

    $service = new Google_Service_Gmail($client);
    $messages = $service->users_messages->listUsersMessages('me', ['maxResults' => 30]);
    foreach ($messages->getMessages() as $msg) {
        $message = $service->users_messages->get('me', $msg->getId());
        $payload = $message->getPayload();
        $headers = $payload->getHeaders();


        $from = getHeader($headers, 'From');
        $subject = getHeader($headers, 'Subject');
        $date = getHeader($headers, 'Date');
        echo 'Message ID: ' . $msg->getId() . '<br>';
        echo 'From: ' . $from . '<br>';
        echo 'Subject: ' . $subject . '<br>';
        echo 'date: ' . $date . '<br>';
        echo 'Snippet: ' . $message->getSnippet() . '<br><br>';
    }
} else {
    exit;
}