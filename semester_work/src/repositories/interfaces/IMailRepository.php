<?php

namespace MyApp\repositories\interfaces;

use MyApp\entities\Mail;

interface IMailRepository
{
    function saveMail(Mail $mail): ?Mail;
    function getMail($mailId): ?Mail;
    function getMails($userId): ?array;
    function deleteMail($mailId): bool;
    function updateMail($mailId, $mailData): bool;

    function getLastHistoryId(int $userId): ?string;
    function saveLastHistoryId(int $userId, string $historyId): bool;
}