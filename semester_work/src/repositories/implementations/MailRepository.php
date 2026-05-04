<?php

namespace MyApp\repositories\implementations;

use MyApp\entities\Mail;
use MyApp\repositories\interfaces\IMailRepository;
use MyApp\Logging\FileLogger;
use PDO;
use PDOException;
use MyApp\database\Database;

class MailRepository implements IMailRepository
{
    private FileLogger $logger;

    public function __construct(FileLogger $logger)
    {
        $this->logger = $logger;
    }
    public function getMail($mailId): ?Mail
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM `mails` WHERE `mailId` = :mailId");
            $stmt->bindParam(':mailId', $mailId);
            $stmt->execute();
            $mail = $stmt->fetch();
            return $mail;
        } catch (PDOException $e) {
            $this->logger->error($e, [__METHOD__]);
            return null;
        }
    }

    public function getMails($userId): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM `mails` WHERE `userId` = :userId");
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $mails = $stmt->fetchAll();
            return $mails;
        } catch (PDOException $e) {
            $this->logger->error($e, [__METHOD__]);
            return null;
        }
    }

    public function deleteMail($mailId): bool
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM `mails` WHERE `mailId` = :mailId");
            $stmt->bindParam(':mailId', $mailId);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->logger->error($e, [__METHOD__]);
            return false;
        }
    }

    public function updateMail($mailId, $mailPriority): bool
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE `mails` SET `mailPriority` = :mailPriority WHERE mailId = :mailId");
            $stmt->bindParam(':mailPriority', $mailPriority);
            $stmt->bindParam(':mailId', $mailId);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->logger->error($e, [__METHOD__]);
            return false;
        }
    }

    public function saveMail(Mail $mail): ?Mail
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO emails (user_id, message_id, subject, 
                    from_email, date, snippet, priority) VALUES 
                    (:user_id, :message_id, :subject, :from_email, :date, :snippet, :priority)");
            $stmt->execute([
                ':user_id' => $mail->userId,
                ':message_id' => $mail->messageId,
                ':subject' => $mail->subject,
                ':from_email' => $mail->fromEmail,
                ':date' => $mail->date,
                ':snippet' => $mail->snippet,
                ':priority' => $mail->priority,
            ]);
            $mailId = $pdo->lastInsertId();
            $mail->mailId = $mailId;
            return $mail;
        } catch (PDOException $e) {
            $this->logger->error($e, [__METHOD__]);
            return null;
        }
    }

    public function getLastHistoryId(int $userId): ?string
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT history_id FROM sync_state WHERE user_id = :userId");
            $stmt->execute([':userId' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['history_id'] : null;
        } catch (PDOException $e) {
            error_log("Error getting last history_id: " . $e->getMessage());
            return null;
        }
    }

    public function saveLastHistoryId(int $userId, string $historyId): bool
    {
        try {
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare("
            INSERT INTO sync_state (user_id, history_id, updated_at)
            VALUES (:userId, :historyId, CURRENT_TIMESTAMP)
            ON CONFLICT(user_id) DO UPDATE SET
                history_id = excluded.history_id,
                updated_at = CURRENT_TIMESTAMP
        ");

            return $stmt->execute([
                ':userId' => $userId,
                ':historyId' => $historyId,
            ]);
        } catch (PDOException $e) {
            error_log("Error saving history_id: " . $e->getMessage());
            return false;
        }
    }
}
