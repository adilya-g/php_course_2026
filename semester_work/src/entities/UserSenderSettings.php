<?php

namespace MyApp\entities;

class UserSenderSettings
{
    public int $id;
    public int $userId;
    public int $senderId;
    public int $importance;
    public bool $isTrusted;
    public bool $isSpam;
}