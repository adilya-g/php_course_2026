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
            $stmt = $pdo->prepare("SELECT * FROM `emails` WHERE `id` = :id");
            $stmt->bindParam(':id', $mailId);
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
            $stmt = $pdo->prepare("SELECT * FROM `emails` WHERE `user_id` = :userId");
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
            $stmt = $pdo->prepare("DELETE FROM `emails` WHERE `id` = :mailId");
            $stmt->bindParam(':mailId', $mailId);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->logger->error($e, [__METHOD__]);
            return false;
        }
    }

    public function updateMail($mailId, $mailData): bool
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE `emails` SET `importance` = :mailPriority WHERE id = :mailId");
            $stmt->bindParam(':mailPriority', $mailData);
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
            $stmt = $pdo->prepare("INSERT INTO emails (user_id, sender_id, gmail_message_id, thread_id,
                    gmail_link, recipient, subject, snippet, body, recieved_at, history_id, importance, created_at) VALUES 
                    (:user_id, :sender_id, :gmail_message_id, :thread_id,
                    :gmail_link, :recipient, :subject, :snippet, :body, :recieved_at, :history_id, :importance, :created_at)");
            $stmt->execute([
                ':user_id' => $mail->userId,
                ':sender_id' => $mail->senderId,
                ':gmail_message_id' => $mail->messageId,
                ':thread_id' => $mail->threadId,
                ':gmail_link' => $mail->link,
                ':recipient' => $mail->recepient,
            ':subject' => $mail->subject,
            ':snippet' => $mail->snippet,
            ':body' => $mail->body,
            ':recieved_at' => $mail->receivedAt,
            '::history_id' => $mail->historyId,
            ':importance' => $mail->importance
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
