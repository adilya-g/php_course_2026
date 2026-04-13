<?php

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;
use MyApp\Entities\User;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "<pre>";

$user1 = new User('Alice', 'alice@example.com', 25);
$user2 = new User('Bob', 'bob@example.com', 30);
$user3 = new User('Charlie', 'charlie@example.com', 35);

$user1->save();
$user2->save();
$user3->save();

echo "Список после создания\n";
foreach (User::all() as $user) {
    echo "{$user->getId()}: {$user->getName()}, ({$user->getEmail()}), {$user->getAge()}\n";
}


$alice = User::find(1);
if ($alice) {
    $updatedAlice = new User('Alice Updated', 'alice@example.com', 26, 1);
    $updatedAlice->save();
    echo "\nОбновлён пользователь ID=1 (Alice)\n";
}

$bob = User::find(2);
if ($bob) {
    $bob->delete();
    echo "Удалён пользователь ID=2 (Bob)\n";
}

echo "\nСписок после обновления и удаления\n";
foreach (User::all() as $user) {
    echo "{$user->getId()}: {$user->getName()}, ({$user->getEmail()}), {$user->getAge()}\n";
}

echo "</pre>";