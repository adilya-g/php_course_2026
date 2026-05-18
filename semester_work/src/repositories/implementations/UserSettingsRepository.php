<?php

namespace MyApp\repositories\implementations;

use MyApp\database\Database;
use MyApp\entities\UserSenderSettings;
use MyApp\Logging\FileLogger;
use MyApp\repositories\interfaces\IUserSenderSettingsRepository;
use PDO;
use PDOException;

class UserSettingsRepository implements IUserSenderSettingsRepository
{

    private FileLogger $logger;
    private PDO $pdo;

    function __construct(FileLogger $logger)
    {
        $this->logger = $logger;
        $this->pdo = Database::getConnection();
    }

    public function getByUserAndSender(int $userId, int $senderId): ?UserSenderSettings
    {
        try
        {
            $stmt = $this->pdo->prepare("SELECT * FROM user_sender_settings WHERE user_id = :user_id AND sender_id = :sender_id");
            $stmt->execute(["user_id" => $userId, "sender_id" => $senderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return  $this->map($row);
        }
        catch (PDOException $e)
        {
            $this->logger->error($e->getMessage());
            return null;
        }
        catch (\Exception $e)
        {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    public function createDefault(int $userId, int $senderId): ?UserSenderSettings
    {
        try
        {
            $stmt = $this->pdo->prepare("INSERT INTO user_sender_settings (user_id, sender_id) VALUES (:user_id, :sender_id)");
            $stmt->execute(["user_id" => $userId, "sender_id" => $senderId]);
            return $this->getByUserAndSender(
                $userId,
                $senderId
            );
        }
        catch (PDOException $e)
        {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    public function updateImportance(int $importance, UserSenderSettings $senderSettings): ?UserSenderSettings
    {
        try
        {
            $stmt = $this->pdo->prepare("UPDATE user_sender_settings SET importance = :importance WHERE user_id = :user_id");
            $stmt->execute(["importance" => $importance, "user_id" => $senderSettings->userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $this->map($row);
        }
        catch (PDOException $e)
        {
            $this->logger->error($e->getMessage());
            return null;
        }
    }
    public function markSpam(UserSenderSettings $senderSettings): void
    {
        try {

            $stmt = $this->pdo->prepare("
            UPDATE user_sender_settings
            SET is_spam = :is_spam
            WHERE user_id = :user_id
              AND sender_id = :sender_id
        ");

            $stmt->execute([
                ':is_spam' => (int)$senderSettings->isSpam,
                ':user_id' => $senderSettings->userId,
                ':sender_id' => $senderSettings->senderId
            ]);

        } catch (PDOException $e) {

            $this->logger->error($e->getMessage());
        }
    }

    public function markTrusted(UserSenderSettings $senderSettings)
    {
        try
        {
            $stmt = $this->pdo->prepare("UPDATE user_sender_settings SET is_trusted = :is_trusted WHERE user_id = :user_id AND sender_id = :sender_id");
            $stmt->execute(["is_trusted" => (int)$senderSettings->isTrusted, "user_id" => $senderSettings->userId, "sender_id" => $senderSettings->id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $this->map($row);
        }
        catch (PDOException $e)
        {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    private function map(?array $row): ?UserSenderSettings
    {
        if ($row == null || $row == false) {
            return null;
        }

        $settings = new UserSenderSettings();

        $settings->id = (int)$row['id'];
        $settings->userId = (int)$row['user_id'];
        $settings->senderId = (int)$row['sender_id'];

        $settings->importance = (int)$row['importance'];

        $settings->isTrusted = (bool)$row['is_trusted'];
        $settings->isSpam = (bool)$row['is_spam'];

        return $settings;
    }
}