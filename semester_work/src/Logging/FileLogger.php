<?php

namespace MyApp\Logging;

class FileLogger implements LoggerInterface
{
    private $logFile;

    public function __construct()
    {
        $this->logFile = __DIR__ . '/../../storage/logs/app.log';
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $logEntry = date('Y-m-d H:i:s') . ' ' . $level . ' [' . gethostname() . '] ';
        $logEntry .= $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL;

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function error(string $message, array $context = []): void
    {
        $logEntry = date('Y-m-d H:i:s') . ' ERROR [' . gethostname() . '] ';
        $logEntry .= $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL;

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }
}
