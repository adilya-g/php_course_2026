<?php

namespace MyApp\Entities;

use Database;

class User
{
    private ?int $id = null;
    private string $name;
    private string $email;
    private int $age;

    public function __construct(string $name, string $email, int $age, ?int $id = null)
    {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
        $this->id = $id;
    }

    public function save(): bool
    {
        $pdo = Database::getConnection();

        if ($this->id === null) {
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, age)
                VALUES (:name, :email, :age)
            ");
            $success = $stmt->execute([
                ':name' => $this->name,
                ':email' => $this->email,
                ':age' => $this->age,
            ]);
            if ($success) {
                $this->id = (int) $pdo->lastInsertId();
            }
            return $success;
        } else {
            $stmt = $pdo->prepare("
                UPDATE users
                SET name = :name, email = :email, age = :age
                WHERE id = :id
            ");
            return $stmt->execute([
                ':id' => $this->id,
                ':name' => $this->name,
                ':email' => $this->email,
                ':age' => $this->age,
            ]);
        }
    }

    public function delete(): bool
    {
        if ($this->id === null) {
            return false;
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute([':id' => $this->id]);
    }

    public static function find(int $id): ?self
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            return new self($row['name'], $row['email'], $row['age'], (int) $row['id']);
        }
        return null;
    }


    public static function all(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM users ORDER BY id");
        $rows = $stmt->fetchAll();

        $users = [];
        foreach ($rows as $row) {
            $users[] = new self($row['name'], $row['email'], $row['age'], (int) $row['id']);
        }
        return $users;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAge(): int
    {
        return $this->age;
    }
}