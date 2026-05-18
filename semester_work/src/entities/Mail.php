<?php

namespace MyApp\entities;

class Mail
{
    public int $id;
    public int $userId;
    public string $messageId;
    public string $subject;
    public int $senderId;
    public string $recipient;
    public string $snippet;
    public \DateTimeImmutable $createdAt;
    public \DateTimeImmutable $receivedAt;
    public string $historyId;
    public string $link;
    public string $threadId;
    public int $importance;
}
