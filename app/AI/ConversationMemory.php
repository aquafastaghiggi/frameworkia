<?php

declare(strict_types=1);

namespace App\AI;

/**
 * Gerenciador de Memória de Conversas
 * 
 * Mantém histórico estruturado de conversas com capacidade de:
 * - Armazenar múltiplas conversas independentes
 * - Recuperar contexto histórico
 * - Gerenciar limites de tamanho
 * - Exportar para formatos de prompt
 */
class ConversationMemory
{
    private array $conversas = [];
    private string $converAuaAtual = '';
    private int $maxMensagensArmazenadas = 50;

    public function __construct()
    {
        // Carregar histórico da sessão se existir
        $this->carregar();
    }

    /**
     * Inicia uma nova conversa
     */
    public function iniciarConversa(string $id = '', string $titulo = ''): void
    {
        if (empty($id)) {
            $id = 'conversa_' . uniqid();
        }

        $this->conversas[$id] = [
            'id' => $id,
            'titulo' => $titulo ?: 'Conversa ' . count($this->conversas),
            'criada_em' => date('Y-m-d H:i:s'),
            'atualizada_em' => date('Y-m-d H:i:s'),
            'mensagens' => [],
            'contexto' => [],
            'resumo' => '',
        ];

        $this->converAuaAtual = $id;
        $this->salvar();
    }

    /**
     * Adiciona uma mensagem à conversa atual
     */
    public function adicionarMensagem(string $papel, string $conteúdo, array $contexto = []): void
    {
        if (empty($this->converAuaAtual) || !isset($this->conversas[$this->converAuaAtual])) {
            $this->iniciarConversa();
        }

        $conversa = &$this->conversas[$this->converAuaAtual];

        $mensagem = [
            'id' => uniqid(),
            'papel' => $papel, // 'user', 'assistant', 'system'
            'conteúdo' => $conteúdo,
            'contexto' => $contexto,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $conversa['mensagens'][] = $mensagem;
        $conversa['atualizada_em'] = date('Y-m-d H:i:s');

        // Manter limite de armazenamento
        if (count($conversa['mensagens']) > $this->maxMensagensArmazenadas) {
            array_shift($conversa['mensagens']);
        }

        $this->salvar();
    }

    /**
     * Obtém mensagens da conversa atual
     */
    public function obterMensagens(int $limite = 10): array
    {
        if (empty($this->converAuaAtual) || !isset($this->conversas[$this->converAuaAtual])) {
            return [];
        }

        $mensagens = $this->conversas[$this->converAuaAtual]['mensagens'];
        return array_slice($mensagens, -$limite);
    }

    /**
     * Obtém contexto da conversa atual
     */
    public function obterContexto(): array
    {
        if (empty($this->converAuaAtual) || !isset($this->conversas[$this->converAuaAtual])) {
            return [];
        }

        return $this->conversas[$this->converAuaAtual]['contexto'] ?? [];
    }

    /**
     * Atualiza contexto da conversa atual
     */
    public function atualizarContexto(array $novoContexto): void
    {
        if (empty($this->converAuaAtual) || !isset($this->conversas[$this->converAuaAtual])) {
            return;
        }

        $this->conversas[$this->converAuaAtual]['contexto'] = array_merge(
            $this->conversas[$this->converAuaAtual]['contexto'],
            $novoContexto
        );

        $this->salvar();
    }

    /**
     * Gera resumo da conversa
     */
    public function gerarResumo(): string
    {
        if (empty($this->converAuaAtual) || !isset($this->conversas[$this->converAuaAtual])) {
            return '';
        }

        $conversa = $this->conversas[$this->converAuaAtual];
        $mensagens = $conversa['mensagens'];

        if (empty($mensagens)) {
            return '';
        }

        $primeiraMsg = $mensagens[0]['conteúdo'] ?? '';
        $ultimaMsg = $mensagens[count($mensagens) - 1]['conteúdo'] ?? '';

        $resumo = "Conversa com " . count($mensagens) . " mensagens.\n";
        $resumo .= "Iniciada com: " . substr($primeiraMsg, 0, 100) . "...\n";
        $resumo .= "Última interação: " . substr($ultimaMsg, 0, 100) . "...";

        $this->conversas[$this->converAuaAtual]['resumo'] = $resumo;
        $this->salvar();

        return $resumo;
    }

    /**
     * Constrói prompt com histórico
     */
    public function construirPromptComHistórico(string $promptAtual): string
    {
        $mensagens = $this->obterMensagens(5);

        if (empty($mensagens)) {
            return $promptAtual;
        }

        $prompt = "# HISTÓRICO DE CONVERSA\n\n";

        foreach ($mensagens as $msg) {
            $papel = ucfirst($msg['papel']);
            $prompt .= "## {$papel}\n";
            $prompt .= $msg['conteúdo'] . "\n\n";
        }

        $prompt .= "# NOVA SOLICITAÇÃO\n{$promptAtual}\n";

        return $prompt;
    }

    /**
     * Lista todas as conversas
     */
    public function listarConversas(): array
    {
        $lista = [];
        foreach ($this->conversas as $id => $conversa) {
            $lista[] = [
                'id' => $id,
                'titulo' => $conversa['titulo'],
                'criada_em' => $conversa['criada_em'],
                'atualizada_em' => $conversa['atualizada_em'],
                'mensagens_count' => count($conversa['mensagens']),
                'resumo' => $conversa['resumo'],
            ];
        }
        return $lista;
    }

    /**
     * Carrega uma conversa específica
     */
    public function carregarConversa(string $id): bool
    {
        if (!isset($this->conversas[$id])) {
            return false;
        }

        $this->converAuaAtual = $id;
        return true;
    }

    /**
     * Limpa a conversa atual
     */
    public function limparConversaAtual(): void
    {
        if (!empty($this->converAuaAtual) && isset($this->conversas[$this->converAuaAtual])) {
            $this->conversas[$this->converAuaAtual]['mensagens'] = [];
            $this->conversas[$this->converAuaAtual]['contexto'] = [];
            $this->conversas[$this->converAuaAtual]['resumo'] = '';
            $this->conversas[$this->converAuaAtual]['atualizada_em'] = date('Y-m-d H:i:s');
            $this->salvar();
        }
    }

    /**
     * Limpa todas as conversas
     */
    public function limparTodas(): void
    {
        $this->conversas = [];
        $this->converAuaAtual = '';
        $this->salvar();
    }

    /**
     * Exporta conversa em formato de prompt
     */
    public function exportarParaPrompt(string $id = ''): string
    {
        if (empty($id)) {
            $id = $this->converAuaAtual;
        }

        if (!isset($this->conversas[$id])) {
            return '';
        }

        $conversa = $this->conversas[$id];
        $prompt = "# CONVERSA: {$conversa['titulo']}\n";
        $prompt .= "Criada em: {$conversa['criada_em']}\n";
        $prompt .= "Atualizada em: {$conversa['atualizada_em']}\n\n";

        foreach ($conversa['mensagens'] as $msg) {
            $papel = ucfirst($msg['papel']);
            $prompt .= "## {$papel} ({$msg['timestamp']})\n";
            $prompt .= "{$msg['conteúdo']}\n\n";

            if (!empty($msg['contexto'])) {
                $prompt .= "**Contexto:** " . json_encode($msg['contexto'], JSON_PRETTY_PRINT) . "\n\n";
            }
        }

        return $prompt;
    }

    /**
     * Salva estado em sessão
     */
    private function salvar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ia_conversation_memory'] = [
                'conversas' => $this->conversas,
                'conversa_atual' => $this->converAuaAtual,
            ];
        }
    }

    /**
     * Carrega estado da sessão
     */
    private function carregar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['ia_conversation_memory'])) {
            $memoria = $_SESSION['ia_conversation_memory'];
            $this->conversas = $memoria['conversas'] ?? [];
            $this->converAuaAtual = $memoria['conversa_atual'] ?? '';
        }
    }

    /**
     * Obtém ID da conversa atual
     */
    public function obterConveraAtual(): string
    {
        return $this->converAuaAtual;
    }

    /**
     * Obtém informações da conversa atual
     */
    public function obterInfoConveraAtual(): array
    {
        if (empty($this->converAuaAtual) || !isset($this->conversas[$this->converAuaAtual])) {
            return [];
        }

        $conversa = $this->conversas[$this->converAuaAtual];
        return [
            'id' => $conversa['id'],
            'titulo' => $conversa['titulo'],
            'criada_em' => $conversa['criada_em'],
            'atualizada_em' => $conversa['atualizada_em'],
            'mensagens_count' => count($conversa['mensagens']),
            'tokens_estimados' => $this->estimarTokens(),
        ];
    }

    /**
     * Estima tokens necessários para o histórico
     */
    private function estimarTokens(): int
    {
        if (empty($this->converAuaAtual) || !isset($this->conversas[$this->converAuaAtual])) {
            return 0;
        }

        $mensagens = $this->conversas[$this->converAuaAtual]['mensagens'];
        $totalCaracteres = 0;

        foreach ($mensagens as $msg) {
            $totalCaracteres += strlen($msg['conteúdo']);
        }

        return (int) ceil($totalCaracteres / 4);
    }
}
