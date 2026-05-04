<?php

namespace MyApp\Exceptions;

use Exception;
use Throwable;

class AppException extends Exception
{
    protected array $context = [];

    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getLogContext(): array
    {
        return array_merge([
            'exception' => get_class($this),
            'code'      => $this->getCode(),
            'file'      => $this->getFile(),
            'line'      => $this->getLine(),
        ], $this->context);
    }

    public static function fromGoogleError(array $error, ?Google_Service_Exception $previous = null): self
    {
        $message = $error['error']['message'] ?? 'Google API error';
        $code = $error['error']['code'] ?? 500;

        // Извлекаем детали ошибки
        $details = $error['error']['details'] ?? [];
        $reason = $details[0]['reason'] ?? 'unknown';

        // Формируем понятное сообщение для пользователя
        switch ($reason) {
            case 'CREDENTIALS_MISSING':
                $userMessage = 'Отсутствует авторизация. Пожалуйста, войдите снова.';
                break;
            case 'invalid_grant':
                $userMessage = 'Сессия истекла. Пожалуйста, авторизуйтесь заново.';
                break;
            default:
                $userMessage = 'Ошибка при работе с Gmail API: ' . $message;
        }

        return new self($userMessage, $code, $previous);
    }
}
