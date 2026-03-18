<?php

declare(strict_types=1);

namespace App\Core;

class Logger
{
    protected string $logPath;

    public function __construct(string $basePath)
    {
        $this->logPath = $basePath . '/storage/logs';
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0777, true);
        }
    }

    public function info(string $message, array $context = [], string $channel = 'app'): void
    {
        $this->log('INFO', $message, $context, $channel);
    }

    public function error(string $message, array $context = [], string $channel = 'app'): void
    {
        $this->log('ERROR', $message, $context, $channel);
    }

    public function debug(string $message, array $context = [], string $channel = 'app'): void
    {
        $this->log('DEBUG', $message, $context, $channel);
    }

    public function ai(string $type, string $content, array $meta = []): void
    {
        $this->log('AI', $type, array_merge(['content' => $content], $meta), 'ai');
    }

    protected function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        $date = date('Y-m-d H:i:s');
        $fileDate = date('Y-m-d');
        $filename = "{$this->logPath}/{$channel}-{$fileDate}.log";

        $logEntry = [
            'timestamp' => $date,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'request_id' => $_SESSION['request_id'] ?? 'none',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];

        $json = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($filename, $json . PHP_EOL, FILE_APPEND);
    }
}
