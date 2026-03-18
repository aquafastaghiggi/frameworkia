<?php

declare(strict_types=1);

namespace App\AI;

use RuntimeException;

class AiResponseStore
{
    protected string $filePath;

    public function __construct(string $basePath)
    {
        $this->filePath = $basePath . '/storage/ai_last_response.json';
        $dir = dirname($this->filePath);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException('Não foi possível criar o diretório de armazenamento de respostas da IA.');
            }
        }
    }

    public function save(string $response, ?string $filePath = null, array $meta = []): void
    {
        $payload = [
            'response' => $response,
            'file_path' => $filePath,
            'meta' => $meta,
            'saved_at' => date('Y-m-d H:i:s'),
        ];

        $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($content === false) {
            throw new RuntimeException('Erro ao codificar a resposta da IA: ' . json_last_error_msg());
        }

        if (file_put_contents($this->filePath, $content) === false) {
            throw new RuntimeException('Não foi possível salvar a resposta da IA.');
        }
    }

    public function load(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [];
        }

        return $data;
    }

    public function clear(): void
    {
        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
    }
}
