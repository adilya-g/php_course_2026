<?php

namespace MyApp\repositories\implementations;

use MyApp\database\Database;
use MyApp\repositories\interfaces\ITokenRepository;
use MyApp\Logging\FileLogger;
use PDO;
use PDOException;
use RuntimeException;

class TokenRepository implements ITokenRepository
{
    private FileLogger $logger;

    public function __construct(FileLogger $logger)
    {
        $this->logger = $logger;
    }

    public function saveRefreshTokenToDatabase(
        int $userId,
        string $refreshToken,
        int $expiresInSeconds = 15768000
    ): void {
        try {
            $pdo = Database::getConnection();
            $encryptedToken = $this->encryptToken($refreshToken);
            if ($encryptedToken === null) {
                $this->logger->error("Failed to save refresh token: encryption failed for user $userId");
                return;
            }

            $expiresAt = date('Y-m-d H:i:s', time() + $expiresInSeconds);
            $now = date('Y-m-d H:i:s');

            $sql = "
                INSERT INTO google_tokens (user_id, refresh_token, created_at, updated_at, expires_at)
                VALUES (:user_id, :token, :created, :updated, :expires)
                ON DUPLICATE KEY UPDATE
                    refresh_token = VALUES(refresh_token),
                    updated_at = VALUES(updated_at),
                    expires_at = VALUES(expires_at)
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':token'   => $encryptedToken,
                ':created' => $now,
                ':updated' => $now,
                ':expires' => $expiresAt,
            ]);
        } catch (PDOException $e) {
            $this->logger->error("Failed to save refresh token to database for user $userId: " . $e->getMessage());
        }
    }

    public function getRefreshTokenFromDatabase(int $userId): ?string
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT refresh_token, expires_at
                FROM google_tokens
                WHERE user_id = :user_id
                ORDER BY updated_at DESC
                LIMIT 1
            ");
            $stmt->execute([':user_id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->logger->error("Refresh token not found for user $userId");
                return null;
            }

            if (strtotime($row['expires_at']) < time()) {
                $this->logger->error("Refresh token expired for user $userId");
                return null;
            }

            $decryptedToken = $this->decryptToken($row['refresh_token']);
            if ($decryptedToken === null) {
                $this->logger->error("Failed to decrypt refresh token for user $userId");
                return null;
            }

            return $decryptedToken;
        } catch (PDOException $e) {
            $this->logger->error("Failed to get refresh token from database for user $userId: " . $e->getMessage());
            return null;
        }
    }

    public function deleteRefreshTokensForUser(int $userId): bool
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("DELETE FROM google_tokens WHERE user_id = :user_id");
            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            $this->logger->error("Failed to delete refresh tokens for user $userId: " . $e->getMessage());
            return false;
        }
    }

    private function encryptToken(string $plainToken): ?string
    {
        try {
            $key = $this->getEncryptionKey();
            if ($key === null) {
                return null;
            }
            $ivLength = openssl_cipher_iv_length('AES-256-CBC');
            $iv = openssl_random_pseudo_bytes($ivLength);
            $encrypted = openssl_encrypt($plainToken, 'AES-256-CBC', $key, 0, $iv);
            if ($encrypted === false) {
                $this->logger->error("OpenSSL encryption failed");
                return null;
            }
            return base64_encode($iv . $encrypted);
        } catch (Exception $e) {
            $this->logger->error("Encryption error: " . $e->getMessage());
            return null;
        }
    }

    private function decryptToken(string $encryptedToken): ?string
    {
        try {
            $key = $this->getEncryptionKey();
            if ($key === null) {
                return null;
            }
            $data = base64_decode($encryptedToken);
            $ivLength = openssl_cipher_iv_length('AES-256-CBC');
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            if ($decrypted === false) {
                $this->logger->error("OpenSSL decryption failed");
                return null;
            }
            return $decrypted;
        } catch (Exception $e) {
            $this->logger->error("Decryption error: " . $e->getMessage());
            return null;
        }
    }

    private function getEncryptionKey(): ?string
    {
        $key = getenv("ENCRYPTION_METHOD");
        if (!$key || strlen($key) !== 32) {
            $this->logger->error("Invalid encryption key: must be 32 bytes for AES-256");
            return null;
        }
        return $key;
    }
}
