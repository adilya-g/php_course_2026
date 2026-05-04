<?php

namespace MyApp\repositories\implementations;

use MyApp\repositories\interfaces\IUserRepository;
use MyApp\Entities\User;
use MyApp\database\Database;
use MyApp\repositories\interfaces\ITokenRepository;
use PDO;
use PDOException;
use RuntimeException;
use MyApp\Logging\FileLogger;

class UserRepository implements IUserRepository
{
    private FileLogger $logger;
    public function __construct(FileLogger $logger)
    {
        $this->logger = $logger;
    }
    public function getUserByEmail(string $email): ?User
    {
        try {
            $pdo = Database::getConnection();
            $statement = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $statement->bindValue(":email", $email);
            $statement->execute();
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            $userObject = new User();
            $userObject->userId = $user["id"];
            $userObject->email = $user["email"];
            return $userObject;
        } catch (PDOException $e) {
            $this->logger->error("Failed to get user by email: " . $e->getMessage());
        }
        return null;
    }

    public function getUserById(int $id)
    {
        try {
            $pdo = Database::getConnection();
            $statement = $pdo->prepare("SELECT * FROM users WHERE id = :id");
            $statement->bindValue(":id", $id);
            $statement->execute();
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            $userObject = new User();
            $userObject->userId = $user["id"];
            $userObject->email = $user["email"];
            return $userObject;
        } catch (PDOException $e) {
            $this->logger->error("Failed to get user by id: " . $e->getMessage());
        }
    }

    public function saveNewUser(User $user)
    {
        try {
            $pdo = Database::getConnection();
            $statement = $pdo->prepare("INSERT INTO users (email) VALUES (:email)");
            $statement->bindValue(":email", $user->email);
            $statement->execute();
            $userId = $pdo->lastInsertId();
            $user->userId = $userId;
            return $user;
        } catch (PDOException $e) {
            $this->logger->error("Failed to create new user: " . $e->getMessage());
        }
    }

    public function updateUser(User $user): void
    {
        try {
            $pdo = Database::getConnection();
            $statement = $pdo->prepare("UPDATE users SET email = :email WHERE id = :id");
            $statement->bindValue(":email", $user->email);
            $statement->bindValue(":id", $user->userId);
            $statement->execute();
        } catch (PDOException $e) {
            $this->logger->error("Failed to update user: " . $e->getMessage());
        }
    }

    public function deleteUser(int $id)
    {
        try {
            $pdo = Database::getConnection();
            $statement = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $statement->bindValue(":id", $id);
            $statement->execute();
        } catch (PDOException $e) {
            $this->logger->error("Failed to delete user: " . $e->getMessage());
        }
    }
}
