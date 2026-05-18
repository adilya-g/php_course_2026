<?php

namespace MyApp\repositories\interfaces;

use MyApp\entities\Sender;
use MyApp\entities\UserSenderSettings;

interface IUserSenderSettingsRepository
{
    public function getByUserAndSender(int $userId, int $senderId): ?UserSenderSettings;

    public function createDefault(int $userId, int $senderId): ?UserSenderSettings;

    public function updateImportance(int $importance, UserSenderSettings $senderSettings): ?UserSenderSettings;

    public function markSpam(UserSenderSettings $senderSettings);

    public function markTrusted(UserSenderSettings $senderSettings);
}