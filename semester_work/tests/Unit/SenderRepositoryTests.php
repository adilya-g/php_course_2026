<?php
namespace Tests\Unit;

use MyApp\entities\Sender;
use MyApp\Logging\FileLogger;
use MyApp\repositories\implementations\SenderRepository;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

class SenderRepositoryTests extends TestCase
{
    public function testCreateSender(): Sender
    {
        $logger = $this->createMock(FileLogger::class);

        $repository = new SenderRepository($logger);

        $sender = $repository->create(
            'test2@gmail.com',
            'Test User'
        );

        $this->assertNotNull($sender);

        $this->assertEquals(
            'test2@gmail.com',
            $sender->email
        );

        $this->assertEquals(
            'Test User',
            $sender->displayName
        );

        return $sender;
    }

    #[Depends('testCreateSender')]
    public function testGetOrCreateOnValidSender(Sender $previousSender): void
    {
        $logger = $this->createMock(FileLogger::class);
        $repository = new SenderRepository($logger);
        $sender = $repository->getOrCreate('test2@gmail.com', 'Test User');
        $this->assertEquals($previousSender->id, $sender->id);
    }

    public function testGetOrCreateOnNotExistedSender(): void
    {
        $logger = $this->createMock(FileLogger::class);
        $repository = new SenderRepository($logger);
        $sender = $repository->getOrCreate('not_existing@gmail.com', 'Bank');
        $this->assertNotNull($sender->id);
    }
}