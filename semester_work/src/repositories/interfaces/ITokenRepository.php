<?php

namespace MyApp\repositories\interfaces;

interface ITokenRepository
{
    public function saveRefreshTokenToDatabase(int $userId, string $refreshToken): void;
    public function getRefreshTokenFromDatabase(int $userId): ?string;
}
