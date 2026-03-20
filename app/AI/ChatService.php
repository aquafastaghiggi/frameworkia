<?php

declare(strict_types=1);

namespace App\AI;

use Throwable;

class ChatService
{
    public function __construct(
        protected AIProviderInterface $provider
    ) {
    }

    public function send(string $prompt, array $context = []): array
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            return [
                'success' => false,
                'message' => 'O prompt não pode estar vazio.',
            ];
        }

        try {
            $response = $this->provider->respond($prompt, $context);

            return [
                'success' => true,
                'message' => 'Resposta gerada com sucesso.',
                'response' => $response,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}