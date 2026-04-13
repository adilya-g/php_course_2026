<?php

namespace MyApp\Exceptions;

use Throwable;

class AuthenticationException extends AppException
{
    protected $code = 401;

    public function __construct(
        string $message = "Требуется аутентификация",
        int $code = 401,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }

    public static function noToken(): self
    {
        return new self("Отсутствует токен доступа. Необходимо повторно авторизоваться.");
    }

    public static function noRefreshToken(): self
    {
        return new self("Refresh-токен отсутствует. Требуется полная повторная аутентификация.");
    }

    public static function tokenExchangeFailed(string $reason, ?Throwable $previous = null): self
    {
        return new self("Не удалось получить токен доступа: {$reason}", 401, $previous);
    }
}