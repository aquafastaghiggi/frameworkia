<?php

declare(strict_types=1);

namespace App\Chat;

use RuntimeException;

class ChatHistoryManager
{
    private string $historyFilePath;

    public function __construct(string $basePath)
    {
        $this->historyFilePath = $basePath . 
'/storage/chat_history.json';
    }

    public function loadHistory(): array
    {
        if (!file_exists($this->historyFilePath)) {
            return [];
        }

        $content = file_get_contents($this->historyFilePath);
        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o histórico do chat.');
        }

        $history = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Erro ao decodificar o histórico do chat: ' . json_last_error_msg());
        }

        return is_array($history) ? $history : [];
    }

    public function saveHistory(array $history): void
    {
        $content = json_encode($history, JSON_PRETTY_PRINT);
        if ($content === false) {
            throw new RuntimeException('Erro ao codificar o histórico do chat: ' . json_last_error_msg());
        }

        if (file_put_contents($this->historyFilePath, $content) === false) {
            throw new RuntimeException('Não foi possível salvar o histórico do chat.');
        }
    }

    public function addMessage(string $role, string $message): void
    {
        $history = $this->loadHistory();
        $history[] = ['role' => $role, 'message' => $message, 'timestamp' => date('Y-m-d H:i:s')];
        $this->saveHistory($history);
    }

    public function clearHistory(): void
    {
        if (file_exists($this->historyFilePath)) {
            unlink($this->historyFilePath);
        }
    }
}
