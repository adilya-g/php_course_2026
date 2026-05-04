<?php

namespace MyApp\Logging;

class LoggerFactory
{
    private static $instance = null;
    private static $debugMode = false;
    private static $logFile = '';

    public static function init(bool $debug, string $logFilePath): void
    {
        self::$debugMode = $debug;
        self::$logFile = $logFilePath;
        self::$instance = new self();
    }

    public static function getLogger(): LoggerInterface
    {
        return new FileLogger(self::$logFile);
    }

    public static function isDebugMode(): bool
    {
        return self::$debugMode;
    }
}
