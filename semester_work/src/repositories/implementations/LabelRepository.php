<?php

namespace MyApp\repositories\implementations;

use MyApp\database\Database;
use MyApp\Logging\FileLogger;
use MyApp\repositories\interfaces\ILabelRepository;
use PDO;

class LabelRepository implements ILabelRepository
{
    private FileLogger $logger;
    private PDO $pdo;

    function __construct(FileLogger $logger)
    {
        $this->logger = $logger;
        $this->pdo = Database::getConnection();
    }
    public function getOrCreate(string $gmailLabelId, ?string $name = null): int
    {
        try {

            $stmt = $this->pdo->prepare("
                SELECT id
                FROM gmail_labels
                WHERE gmail_label_id = ?
            ");

            $stmt->execute([$gmailLabelId]);

            $label = $stmt->fetch();

            if ($label) {
                return (int)$label['id'];
            }

            $labelName = $name ?? $gmailLabelId;

            $insertStmt = $this->pdo->prepare("
                INSERT INTO gmail_labels
                (
                    gmail_label_id,
                    name,
                    type
                )
                VALUES
                (
                    ?,
                    ?,
                    'gmail'
                )
            ");

            $insertStmt->execute([
                $gmailLabelId,
                $labelName
            ]);

            return (int)$this->pdo->lastInsertId();

        } catch (PDOException $e) {

            $this->logger->error($e->getMessage());

            throw $e;

        } catch (Exception $e) {

            $this->logger->error($e->getMessage());

            throw $e;
        }
    }

    public function attachLabelsToMessage(int $messageId, array $gmailLabels): void
    {
        try
        {
            foreach ($gmailLabels as $gmailLabel) {

                $labelId = $this->getOrCreate(
                    $gmailLabel
                );

                $stmt = $this->pdo->prepare("
            INSERT OR IGNORE
            INTO gmail_message_labels
            (message_id, label_id)
            VALUES (?, ?)
        ");

                $stmt->execute([
                    $messageId,
                    $labelId
                ]);
            }
        }
        catch (\PDOException $e){
            $this->logger->error($e->getMessage());
        }
        catch (\Exception $e){
            $this->logger->error($e->getMessage());
        }
    }

    public function syncMessageLabels(int $messageId, array $gmailLabels): void
    {
        try
        {
            $stmt = $this->pdo->prepare("
                DELETE FROM gmail_message_labels
                WHERE message_id = ?
            ");

            $stmt->execute([$messageId]);

            $this->attachLabelsToMessage(
                $messageId,
                $gmailLabels
            );
        }
        catch (\PDOException $e){
            $this->logger->error($e->getMessage());
        }
        catch (\Exception $e){
            $this->logger->error($e->getMessage());
        }
    }

    public function getLabelsByMessageId(int $messageId): array
    {
        try {

            $stmt = $this->pdo->prepare("
                SELECT
                    gl.id,
                    gl.gmail_label_id,
                    gl.name,
                    gl.type
                FROM gmail_labels gl
                INNER JOIN gmail_message_labels gml
                    ON gml.label_id = gl.id
                WHERE gml.message_id = ?
            ");

            $stmt->execute([$messageId]);

            return $stmt->fetchAll();

        } catch (PDOException $e) {

            $this->logger->error($e->getMessage());

            return [];

        } catch (Exception $e) {

            $this->logger->error($e->getMessage());

            return [];
        }
    }

    public function createCustomLabel(
        int $userId,
        string $name,
        ?string $color = null
    ): int
    {
        try {

            $stmt = $this->pdo->prepare("
            SELECT id
            FROM gmail_labels
            WHERE
                created_by_user_id = ?
                AND name = ?
                AND type = 'custom'
        ");

            $stmt->execute([
                $userId,
                $name
            ]);

            $existing = $stmt->fetch();

            if ($existing) {
                return (int)$existing['id'];
            }

            $insertStmt = $this->pdo->prepare("
            INSERT INTO gmail_labels
            (
                gmail_label_id,
                name,
                color,
                type,
                created_by_user_id
            )
            VALUES
            (
                NULL,
                ?,
                ?,
                'custom',
                ?
            )
        ");

            $insertStmt->execute([
                $name,
                $color,
                $userId
            ]);

            return (int)$this->pdo->lastInsertId();

        } catch (PDOException $e) {

            $this->logger->error(
                $e->getMessage()
            );

            throw $e;

        } catch (Exception $e) {

            $this->logger->error(
                $e->getMessage()
            );

            throw $e;
        }
    }
}