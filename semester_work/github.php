<?php
$githubToken = $_POST['githubToken'] ?? '';

if (empty($githubToken)) {
    die('Токен не передан');
}

if (!function_exists('curl_init')) {
    die('Ошибка: cURL не установлен. Включите extension=curl в php.ini');
}

$url = "https://api.github.com/notifications?all=true";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $githubToken,
        'User-Agent: PHP-App',
        'Accept: application/vnd.github.v3+json',
        'X-GitHub-Api-Version: 2022-11-28'
    ],
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    die("cURL Error: $curlError");
}

if ($httpCode === 401) {
    echo "<h2>Ошибка 401 Unauthorized</h2>";
    echo "<p>Ваш токен недействителен или не имеет прав на чтение уведомлений.</p>";
    echo "<p>Создайте новый токен на <a href='https://github.com/settings/tokens' target='_blank'>GitHub</a> с правами 'notifications' или 'repo'.</p>";
} elseif ($httpCode === 200) {
    header('Content-Type: application/json');
    echo $response;
} else {
    echo "HTTP код: $httpCode<br>";
    echo "Ответ: " . htmlspecialchars($response);
}
?>