<?php
$name = htmlspecialchars($_GET['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?? 'name';
$role = htmlspecialchars($_GET['role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?? 'role';
$skills = $_GET['skill'] ?? [];
$profile = [
    'name'   => $name,
    'role'   => $role,
    'skills' => $skills
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>Имя: <?= $profile['name']  ?> </h1>
    <h1>Роль: <?= $profile['role']  ?></h1>
    <h2>Навыки:</h2></br>
    <ul>
        <?php
        foreach ($profile['skills'] as $skill): ?>
        <li><?= $skill ?></li>
        <?php endforeach; ?>
    </ul>
</body>
</html>