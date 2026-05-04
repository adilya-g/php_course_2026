<?php

namespace MyApp\entities;

class Mail
{
    public int $id;
    public int $userId;
    public string $messageId;
    public string $subject;
    public string $fromEmail;
    public string $date;
    public string $snippet;
    public string $createdAt;
    public int $priority;
}
