<?php

declare(strict_types=1);

namespace App\AI;

class MockAIProvider implements AIProviderInterface
{
    public function respond(string $prompt, array $context = []): string
    {
        $file = $context['file_path'] ?? 'nenhum arquivo';
        $workspace = $context['workspace'] ?? 'nenhum workspace';
        $currentPath = $context['current_path'] ?? '/';

        $summary = [];
        $summary[] = "Resposta mockada da IA.";
        $summary[] = "Prompt recebido: {$prompt}";
        $summary[] = "Arquivo em foco: {$file}";
        $summary[] = "Pasta atual: {$currentPath}";
        $summary[] = "Workspace: {$workspace}";

        if (!empty($context['file_content'])) {
            $contentPreview = mb_substr((string) $context['file_content'], 0, 400);
            $summary[] = "Prévia do conteúdo do arquivo:";
            $summary[] = $contentPreview;
        }

        $summary[] = "Próximo passo: integrar provider real (OpenAI ou Ollama).";

        return implode("\n\n", $summary);
    }
}