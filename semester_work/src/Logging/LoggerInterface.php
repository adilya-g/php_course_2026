<?php

namespace MyApp\Logging;

interface LoggerInterface
{
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
}