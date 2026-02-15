<?php
$name = htmlspecialchars($_GET['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$role = htmlspecialchars($_GET['role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if($role == 'admin'){
    echo "<h1>Добрый день, админ $name</h1>";
}
else{
    echo "<h1>Добрый день, $name</h1>";
}
?>