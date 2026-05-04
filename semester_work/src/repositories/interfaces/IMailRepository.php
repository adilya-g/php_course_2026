<?php

namespace MyApp\repositories\interfaces;

use MyApp\entities\Mail;

interface IMailRepository
{
    public function saveMail(Mail $mail): ?Mail;
    public function getMail($mailId): ?Mail;
    public function getMails($userId): ?array;
    public function deleteMail($mailId): bool;
    public function updateMail($mailId, $mailData): bool;

    public function getLastHistoryId(int $userId): ?string;
    public function saveLastHistoryId(int $userId, string $historyId): bool;
}
