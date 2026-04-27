<?php

namespace MyApp\repositories\interfaces;

interface ITokenRepository
{
    function saveRefreshTokenToDatabase(int $userId, string $refreshToken): void;
    function getRefreshTokenFromDatabase(int $userId): ?string;
}