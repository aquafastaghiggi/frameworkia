<?php

declare(strict_types=1);

namespace App\AI\Providers;

use Exception;

/**
 * OpenAI Provider - Real API Integration
 */
class OpenAIProvider implements AIProvider
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $maxTokens = 2000;
    private float $temperature = 0.7;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY', '');
        $this->model = env('OPENAI_MODEL', 'gpt-4o-mini');
        $this->baseUrl = env('OPENAI_BASE_URL', 'https://api.openai.com/v1');

        if (empty($this->apiKey) || str_contains($this->apiKey, 'mock')) {
            throw new Exception('OpenAI API key não configurada. Configure OPENAI_API_KEY no .env');
        }
    }

    public function chat(array $messages, array $options = []): string
    {
        try {
            $payload = [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
                'temperature' => $options['temperature'] ?? $this->temperature,
            ];

            $response = $this->makeRequest('/chat/completions', $payload);

            if (isset($response['choices'][0]['message']['content'])) {
                return $response['choices'][0]['message']['content'];
            }

            throw new Exception('Resposta inválida da OpenAI API');

        } catch (Exception $e) {
            throw new Exception('Erro ao chamar OpenAI API: ' . $e->getMessage());
        }
    }

    public function embeddings(string $text): array
    {
        try {
            $payload = [
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ];

            $response = $this->makeRequest('/embeddings', $payload);

            if (isset($response['data'][0]['embedding'])) {
                return $response['data'][0]['embedding'];
            }

            throw new Exception('Resposta inválida na geração de embeddings');

        } catch (Exception $e) {
            throw new Exception('Erro ao gerar embeddings: ' . $e->getMessage());
        }
    }

    public function tokenize(string $text): int
    {
        // Estimativa rápida: ~4 caracteres por token
        return (int)ceil(strlen($text) / 4);
    }

    private function makeRequest(string $endpoint, array $payload): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Erro na requisição: $error");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP $httpCode: $response");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erro ao decodificar resposta JSON: ' . json_last_error_msg());
        }

        if (isset($decoded['error'])) {
            throw new Exception('API Error: ' . ($decoded['error']['message'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    public function setMaxTokens(int $tokens): void
    {
        $this->maxTokens = $tokens;
    }

    public function setTemperature(float $temperature): void
    {
        $this->temperature = max(0, min(2, $temperature));
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }
}
