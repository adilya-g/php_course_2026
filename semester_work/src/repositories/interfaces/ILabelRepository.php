<?php

namespace MyApp\repositories\interfaces;

interface ILabelRepository
{
    public function getOrCreate(
        string $gmailLabelId,
        ?string $name = null
    ): int;

    public function attachLabelsToMessage(
        int $messageId,
        array $gmailLabels
    ): void;

    public function syncMessageLabels(
        int $messageId,
        array $gmailLabels
    ): void;

    public function getLabelsByMessageId(
        int $messageId
    ): array;

    public function createCustomLabel(
        int $userId,
        string $name,
        ?string $color = null
    ): int;
}