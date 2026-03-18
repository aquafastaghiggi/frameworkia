<?php

declare(strict_types=1);

namespace App\Queue;

use RuntimeException;

class QueueLogger
{
    protected string $logPath;

    public function __construct(string $basePath)
    {
        $this->logPath = $basePath . '/storage/logs/queue.log';
        $dir = dirname($this->logPath);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException('Não foi possível criar o diretório de logs da fila.');
            }
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    protected function write(string $level, string $message, array $context = []): void
    {
        $payload = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($line === false) {
            throw new RuntimeException('Erro ao registrar log da fila.');
        }

        file_put_contents($this->logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
