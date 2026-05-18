<?php

namespace MyApp\repositories\interfaces;

use MyApp\entities\Sender;

interface ISenderRepository
{
    public function create(
        string $email,
        ?string $displayName,
    ): ?Sender;

    public function getOrCreate(
        string $email,
        ?string $displayName = null
    ): ?Sender;
}