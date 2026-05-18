<?php

namespace MyApp\repositories\implementations;

use MyApp\database\Database;
use MyApp\entities\Sender;
use MyApp\Logging\FileLogger;
use MyApp\repositories\interfaces\ISenderRepository;
use PDO;
use PDOException;

class SenderRepository implements ISenderRepository
{

    private FileLogger $logger;
    private PDO $pdo;

    function __construct(FileLogger $logger)
    {
        $this->logger = $logger;
        $this->pdo = Database::getConnection();
    }

    public function getOrCreate(string $email, ?string $displayName = null): ?Sender
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `senders` WHERE `email` = :email LIMIT 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                $sender = $this->map($row);
            }
            return $sender;
        }
        catch (PDOException $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    public function create(string $email, ?string $displayName,): ?Sender
    {
        try
        {
            $stmt = $this->pdo->prepare("INSERT INTO `senders` (`email`, `display_name`) VALUES (:email, :displayName)");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':displayName', $displayName);
            $stmt->execute();
            $id = $this->pdo->lastInsertId();
            if ($id !== null) {
                $sender = new Sender();
                $sender->id = (int)$id;
                $sender->email = $email;
                $sender->displayName = $displayName ?? '';
                return $sender;
            }
            return null;
        }
        catch (PDOException $e)
        {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    private function map(array $row): Sender
    {
        $sender = new Sender();

        $sender->id = (int)$row['id'];
        $sender->email = $row['email'];
        $sender->displayName = $row['display_name'] ?? '';

        return $sender;
    }
}