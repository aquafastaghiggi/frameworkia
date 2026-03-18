<?php

declare(strict_types=1);

namespace App\AI;

use Throwable;
use App\Core\Logger;

class ChatService
{
    protected ?Logger $logger = null;

    public function __construct(
        protected AIProviderInterface $provider
    ) {
    }

    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
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

        // Aplicar role se especificada no contexto
        $role = $context['role'] ?? 'dev';
        $context['system_instruction'] = $this->getSystemInstructionByRole($role);

        if ($this->logger) {
            $this->logger->ai('PROMPT', $prompt, [
                'role' => $role,
                'context_keys' => array_keys($context)
            ]);
        }

        try {
            $response = $this->provider->respond($prompt, $context);

            if ($this->logger) {
                $this->logger->ai('RESPONSE', $response);
            }

            return [
                'success' => true,
                'message' => 'Resposta gerada com sucesso.',
                'response' => $response,
            ];
        } catch (Throwable $e) {
            if ($this->logger) {
                $this->logger->error('Erro na IA: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ], 'ai');
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function getSystemInstructionByRole(string $role): string
    {
        $roles = [
            'dev' => "Você é um engenheiro de software sênior focado em escrever código limpo, eficiente e seguro.",
            'reviewer' => "Você é um revisor de código rigoroso. Analise o código em busca de bugs, falhas de segurança e melhorias de arquitetura.",
            'debugger' => "Você é um especialista em depuração. Analise erros e logs para encontrar a causa raiz dos problemas.",
            'architect' => "Você é um arquiteto de software. Foque na estrutura do projeto, padrões de projeto e escalabilidade."
        ];

        return $roles[$role] ?? $roles['dev'];
    }
}