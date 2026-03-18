<?php

declare(strict_types=1);

namespace App\AI;

use RuntimeException;

class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        protected array $config
    ) {
    }

    public function respond(string $prompt, array $context = []): string
    {
        $apiKey = trim((string) ($this->config['api_key'] ?? ''));
        $model = (string) ($this->config['model'] ?? 'gpt-5.4');
        $baseUrl = (string) ($this->config['base_url'] ?? 'https://api.openai.com/v1/responses');
        $temperature = (float) ($this->config['temperature'] ?? 0.2);
        $maxOutputTokens = (int) ($this->config['max_output_tokens'] ?? 1200);

        if ($apiKey === '') {
            throw new RuntimeException('API key da OpenAI não configurada.');
        }

        $systemPrompt = $this->buildSystemPrompt($context);
        $userPrompt = $this->buildUserPrompt($prompt, $context);

        $payload = [
            'model' => $model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $systemPrompt,
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $userPrompt,
                        ],
                    ],
                ],
            ],
            'temperature' => $temperature,
            'max_output_tokens' => $maxOutputTokens,
        ];

        $ch = curl_init($baseUrl);

        if ($ch === false) {
            throw new RuntimeException('Falha ao inicializar cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 90,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Erro de rede ao chamar OpenAI: ' . $curlError);
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida da OpenAI.');
        }

        if ($httpCode >= 400) {
            $message = $data['error']['message'] ?? 'Erro desconhecido na OpenAI.';
            throw new RuntimeException('OpenAI retornou erro: ' . $message);
        }

        $text = $this->extractText($data);

        if ($text === '') {
            throw new RuntimeException('A OpenAI não retornou texto utilizável.');
        }

        return $text;
    }

protected function buildSystemPrompt(array $context = []): string
{
    $roleInstruction = $context['system_instruction'] ?? "Você é um engenheiro de software sênior atuando dentro de uma IDE.";

    return <<<TXT
{$roleInstruction}

Seu objetivo:
- ajudar a escrever, refatorar e corrigir código
- trabalhar com contexto real de arquivos e mudanças

Regras importantes:
- priorize o diff e o arquivo aberto
- seja direto e técnico
- para mudanças pequenas ou localizadas, responda no formato:

LOCALIZAR:
<trecho exato atual>

SUBSTITUIR POR:
<trecho novo exato>

- para mudanças pequenas, NÃO devolva o arquivo inteiro
- só devolva o arquivo inteiro quando o usuário pedir explicitamente "reescreva o arquivo completo"
- o trecho em LOCALIZAR deve existir exatamente no arquivo quando possível
- o trecho em SUBSTITUIR POR deve preservar o restante do arquivo, mudando apenas o necessário
TXT;
}

protected function buildUserPrompt(string $prompt, array $context = []): string
{
    $workspace = (string) ($context['workspace'] ?? '');
    $filePath = (string) ($context['file_path'] ?? '');
    $currentPath = (string) ($context['current_path'] ?? '');
    $fileContent = (string) ($context['file_content'] ?? '');
    $gitDiff = (string) ($context['git_diff'] ?? '');
    $projectStructure = (string) ($context['project_structure'] ?? '');
    $attachments = (array) ($context['attachments'] ?? []);

    $filePreview = mb_substr($fileContent, 0, 8000);
    $diffPreview = mb_substr($gitDiff, 0, 4000);
    
    $attachmentsContext = "";
    if (!empty($attachments)) {
        $attachmentsContext = "\nANEXOS SELECIONADOS:\n";
        foreach ($attachments as $index => $att) {
            $num = $index + 1;
            $path = $att['path'] ?? 'desconhecido';
            $type = $att['type'] ?? 'desconhecido';
            $content = $att['content'] !== '' ? $att['content'] : ($att['summary'] ?? '');
            $preview = mb_substr($content, 0, 4000);
            
            $attachmentsContext .= "--- ANEXO #{$num} ---\n";
            $attachmentsContext .= "Caminho: {$path}\n";
            $attachmentsContext .= "Tipo: {$type}\n";
            $attachmentsContext .= "Conteúdo/Resumo:\n{$preview}\n\n";
        }
    }

    return <<<TXT
PROMPT DO USUÁRIO:
{$prompt}

ESTRUTURA DO PROJETO:
{$projectStructure}

CONTEXTO ATUAL:
- Workspace: {$workspace}
- Pasta atual: {$currentPath}
- Arquivo Aberto: {$filePath}

CÓDIGO DO ARQUIVO ABERTO:
{$filePreview}

ALTERAÇÕES NÃO COMMITADAS (git diff):
{$diffPreview}
{$attachmentsContext}
INSTRUÇÃO:
- Use a ESTRUTURA DO PROJETO para entender a arquitetura e onde criar novos arquivos se necessário.
- Se houver anexos, considere-os como contexto adicional importante.
- Relacione os anexos com o arquivo aberto e entre si quando fizer sentido.
- Seja objetivo e técnico.
TXT;
}

    protected function extractText(array $data): string
    {
        if (!empty($data['output_text']) && is_string($data['output_text'])) {
            return trim($data['output_text']);
        }

        if (!empty($data['output']) && is_array($data['output'])) {
            $parts = [];

            foreach ($data['output'] as $item) {
                if (!is_array($item) || empty($item['content']) || !is_array($item['content'])) {
                    continue;
                }

                foreach ($item['content'] as $content) {
                    $text = $content['text'] ?? null;

                    if (is_string($text) && trim($text) !== '') {
                        $parts[] = trim($text);
                    }
                }
            }

            return trim(implode("\n\n", $parts));
        }

        return '';
    }
}