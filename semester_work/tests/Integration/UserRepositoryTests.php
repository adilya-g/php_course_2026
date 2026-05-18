<?php

namespace Tests\Integration\Repositories;

use PHPUnit\Framework\TestCase;
use MyApp\repositories\implementations\UserRepository;
use MyApp\Entities\User;
use MyApp\Logging\FileLogger;
use MyApp\database\Database;
use PDO;

class UserRepositoryIntegrationTest extends TestCase
{
    private PDO $testConnection;
    private UserRepository $repository;

    protected function setUp(): void
    {
        $this->testConnection = Database::getConnection($this->testConnection);
        $loggerMock = $this->createMock(FileLogger::class);
        $this->repository = new UserRepository($loggerMock);
    }

    protected function tearDown(): void
    {
        $this->testConnection->exec('DELETE FROM users');
        $this->testConnection->exec("DELETE FROM sqlite_sequence WHERE name='users'");
        $this->testConnection = null;
    }

    private function getUserFromDb(int $id): ?array
    {
        $stmt = $this->testConnection->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }
    public function testSaveNewUserInsertsRecordAndReturnsId(): void
    {
        $user = new User();
        $user->email = 'alice@example.com';

        $result = $this->repository->saveNewUser($user);

        // 1. Проверяем возвращаемый объект
        $this->assertNotNull($result->userId);
        $this->assertIsInt($result->userId);
        $this->assertEquals('alice@example.com', $result->email);

        // 2. Проверяем, что запись действительно попала в БД
        $dbRecord = $this->getUserFromDb($result->userId);
        $this->assertNotNull($dbRecord);
        $this->assertEquals($result->userId, $dbRecord['id']);
        $this->assertEquals('alice@example.com', $dbRecord['email']);
    }

    public function testGetUserByEmailFindsExistingUser(): void
    {
        // Сначала создаем запись напрямую в БД
        $this->testConnection->exec("INSERT INTO users (email) VALUES ('bob@example.com')");
        $dbId = (int)$this->testConnection->lastInsertId();

        // Ищем через репозиторий
        $foundUser = $this->repository->getUserByEmail('bob@example.com');

        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertEquals($dbId, $foundUser->userId);
        $this->assertEquals('bob@example.com', $foundUser->email);
    }

    public function testGetUserByEmailReturnsNullForNonExistent(): void
    {
        $result = $this->repository->getUserByEmail('ghost@example.com');
        $this->assertNull($result);
    }

    public function testGetUserByIdReturnsCorrectUser(): void
    {
        $this->testConnection->exec("INSERT INTO users (email) VALUES ('charlie@example.com')");
        $dbId = (int)$this->testConnection->lastInsertId();

        $foundUser = $this->repository->getUserById($dbId);

        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertEquals($dbId, $foundUser->userId);
    }

    public function testUpdateUserChangesEmailInDatabase(): void
    {
        // Создаем пользователя
        $user = new User();
        $user->email = 'old@example.com';
        $savedUser = $this->repository->saveNewUser($user);

        // Меняем email и обновляем
        $savedUser->email = 'new@example.com';
        $this->repository->updateUser($savedUser);

        // Проверяем напрямую в БД
        $dbRecord = $this->getUserFromDb($savedUser->userId);
        $this->assertEquals('new@example.com', $dbRecord['email']);
    }

    public function testDeleteUserRemovesRecordFromDatabase(): void
    {
        // Создаем пользователя
        $user = new User();
        $user->email = 'delete_me@example.com';
        $savedUser = $this->repository->saveNewUser($user);

        // Удаляем
        $this->repository->deleteUser($savedUser->userId);

        // Проверяем, что запись исчезла
        $this->assertNull($this->getUserFromDb($savedUser->userId));

        // И репозиторий тоже возвращает null
        $this->assertNull($this->repository->getUserById($savedUser->userId));
    }

    public function testUniqueEmailConstraintWorks(): void
    {
        $user1 = new User();
        $user1->email = 'unique@example.com';
        $this->repository->saveNewUser($user1);

        $user2 = new User();
        $user2->email = 'unique@example.com'; // Дубликат

        // В текущей реализации репозиторий ловит PDOException и логирует его.
        // Интеграционный тест должен проверить, что второй insert не создал дубликат.
        $result = $this->repository->saveNewUser($user2);

        // Проверяем, что в БД осталась только одна запись
        $stmt = $this->testConnection->query('SELECT COUNT(*) FROM users WHERE email = "unique@example.com"');
        $this->assertEquals(1, (int)$stmt->fetchColumn());
    }
}