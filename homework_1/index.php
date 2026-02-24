<?php
$name = htmlspecialchars($_GET['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$role = htmlspecialchars($_GET['role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$uri = $_SERVER['REQUEST_URI'];
$full_url = $protocol . $host . $uri;

if($role == 'admin'){
    echo "<h1>Добрый день, админ $name</h1></br>";
}
else{
    echo "<h1>Добрый день, $name</h1></br>";
}
echo "<p> {$_SERVER['REQUEST_METHOD']}</p></br>";
echo $full_url;
?>