<?php

declare(strict_types=1);

namespace App\Agent;

use App\AI\MultiContextManager;
use App\AI\ChatService;

/**
 * AI Agent Autônomo
 * 
 * Transforma IA em "dev autônomo" que pode:
 * - Planejar tarefas automaticamente
 * - Executar em etapas
 * - Auto-debug inteligente
 * - Loop de melhoria contínua
 */
class AutonomousAgent
{
    private ChatService $chatService;
    private array $planosAtivos = [];
    private array $tarefasExecutadas = [];
    private int $maxTentativas = 3;
    private int $maxEtapas = 10;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
        $this->carregar();
    }

    /**
     * Analisa objetivo e cria plano de execução
     */
    public function criarPlano(string $objetivo, array $contexto = []): array
    {
        $idPlano = 'plano_' . uniqid();

        // Pedir ao ChatService para planejar
        $promptPlanejamento = "Você é um agente de desenvolvimento autônomo.

Objetivo: $objetivo

Contexto fornecido:
" . json_encode($contexto, JSON_PRETTY_PRINT) . "

Crie um plano de execução detalhado em etapas. Responda APENAS com JSON válido neste formato:
{
  \"etapas\": [
    {\"número\": 1, \"descrição\": \"...\", \"ação\": \"...\"},
    {\"número\": 2, \"descrição\": \"...\", \"ação\": \"...\"}
  ],
  \"recursos_necessários\": [...],
  \"riscos\": [...],
  \"tempo_estimado_minutos\": N
}";

        $resultado = $this->chatService->send($promptPlanejamento, $contexto);

        // Parsear resposta JSON
        $plano = $this->extrairPlanoJSON($resultado['response'] ?? '');

        if (!$plano) {
            $plano = [
                'etapas' => [
                    ['número' => 1, 'descrição' => 'Analisar objetivo', 'ação' => $objetivo],
                ],
                'recursos_necessários' => [],
                'riscos' => ['Impossível gerar plano automático'],
                'tempo_estimado_minutos' => 30,
            ];
        }

        $this->planosAtivos[$idPlano] = [
            'id' => $idPlano,
            'objetivo' => $objetivo,
            'plano' => $plano,
            'status' => 'criado',
            'etapa_atual' => 0,
            'tentativas' => 0,
            'criado_em' => date('Y-m-d H:i:s'),
            'logs' => [],
        ];

        $this->salvar();

        return [
            'sucesso' => true,
            'plano_id' => $idPlano,
            'plano' => $plano,
        ];
    }

    /**
     * Executa a próxima etapa do plano
     */
    public function executarEtapaProxima(string $idPlano): array
    {
        if (!isset($this->planosAtivos[$idPlano])) {
            return ['sucesso' => false, 'erro' => 'Plano não encontrado'];
        }

        $plano = &$this->planosAtivos[$idPlano];

        if ($plano['etapa_atual'] >= count($plano['plano']['etapas'])) {
            $plano['status'] = 'concluído';
            return ['sucesso' => true, 'mensagem' => 'Plano já foi totalmente executado'];
        }

        if ($plano['etapa_atual'] >= $this->maxEtapas) {
            $plano['status'] = 'abortado';
            return ['sucesso' => false, 'erro' => 'Limite de etapas excedido'];
        }

        $etapa = $plano['plano']['etapas'][$plano['etapa_atual']];
        $resultado = $this->executarEtapa($etapa, $plano);

        $plano['etapa_atual']++;
        $plano['logs'][] = [
            'etapa' => $etapa['número'],
            'resultado' => $resultado,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // Avaliar se precisa de debug
        if (!$resultado['sucesso'] && $plano['tentativas'] < $this->maxTentativas) {
            $plano['tentativas']++;
            $resultado['debug_necessário'] = true;
            $resultado['tentativa'] = $plano['tentativas'];
        }

        $this->salvar();

        return [
            'sucesso' => $resultado['sucesso'],
            'etapa' => $etapa['número'],
            'resultado' => $resultado,
            'próxima_etapa' => $plano['etapa_atual'] + 1,
            'plano_concluído' => $plano['etapa_atual'] >= count($plano['plano']['etapas']),
        ];
    }

    /**
     * Executa uma etapa individual
     */
    private function executarEtapa(array $etapa, array $plano): array
    {
        $ação = $etapa['ação'] ?? '';

        // Simulação de execução (em produção seria chamar serviços específicos)
        try {
            // Aqui seria integrado com WorkspaceController, GitService, etc.
            return [
                'sucesso' => true,
                'mensagem' => "Etapa '{$etapa['descrição']}' executada",
                'detalhes' => ['ação' => $ação],
            ];
        } catch (\Throwable $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'ação' => $ação,
            ];
        }
    }

    /**
     * Auto-debug: Analisa falha e tenta corrigir automaticamente
     */
    public function autoDebug(string $idPlano): array
    {
        if (!isset($this->planosAtivos[$idPlano])) {
            return ['sucesso' => false, 'erro' => 'Plano não encontrado'];
        }

        $plano = $this->planosAtivos[$idPlano];
        $ultimoLog = end($plano['logs']);

        $promptDebug = "Você é um debug specialist.

Etapa falhada: " . json_encode($ultimoLog['resultado'], JSON_PRETTY_PRINT) . "

Sugira 3 correções possíveis em JSON:
{
  \"análise\": \"...\",
  \"causas_prováveis\": [...],
  \"soluções\": [
    {\"número\": 1, \"descrição\": \"...\", \"código_sugerido\": \"...\"}
  ]
}";

        $resultado = $this->chatService->send($promptDebug, []);

        $análiseDebug = [
            'plano_id' => $idPlano,
            'sugestões' => $this->extrairDebugJSON($resultado['response'] ?? ''),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        return [
            'sucesso' => true,
            'análise' => $análiseDebug,
        ];
    }

    /**
     * Loop de melhoria: Avalia execução e propõe otimizações
     */
    public function executarMelhoriaContínua(string $idPlano): array
    {
        if (!isset($this->planosAtivos[$idPlano])) {
            return ['sucesso' => false, 'erro' => 'Plano não encontrado'];
        }

        $plano = $this->planosAtivos[$idPlano];

        // Calcular métricas
        $tempoTotal = count($plano['logs']);
        $sucessos = count(array_filter($plano['logs'], fn($l) => $l['resultado']['sucesso']));
        $taxaSucesso = $tempoTotal > 0 ? ($sucessos / $tempoTotal) * 100 : 0;

        // Pedir sugestões de melhoria
        $promptMelhoria = "Você é um especialista em otimização de processos de desenvolvimento.

Objetivo original: " . $plano['objetivo'] . "
Etapas executadas: $tempoTotal
Taxa de sucesso: $taxaSucesso%
Tentativas de recuperação: " . $plano['tentativas'] . "

Sugira otimizações para melhorar a taxa de sucesso em próximas execuções. Responda em JSON:
{
  \"análise_geral\": \"...\",
  \"otimizações_propostas\": [
    {\"área\": \"...\", \"sugestão\": \"...\", \"impacto_estimado\": \"...\"}
  ],
  \"prioridade\": \"alta/média/baixa\"
}";

        $resultado = $this->chatService->send($promptMelhoria, []);

        return [
            'sucesso' => true,
            'métricas' => [
                'tempo_total' => $tempoTotal,
                'sucessos' => $sucessos,
                'taxa_sucesso' => $taxaSucesso . '%',
                'tentativas_recuperação' => $plano['tentativas'],
            ],
            'otimizações' => $this->extrairMelhoriaJSON($resultado['response'] ?? ''),
        ];
    }

    /**
     * Obtém histórico de planos
     */
    public function obterHistórico(): array
    {
        $histórico = [];

        foreach ($this->planosAtivos as $id => $plano) {
            $histórico[] = [
                'id' => $id,
                'objetivo' => $plano['objetivo'],
                'status' => $plano['status'],
                'etapa_atual' => $plano['etapa_atual'],
                'total_etapas' => count($plano['plano']['etapas']),
                'criado_em' => $plano['criado_em'],
                'logs_count' => count($plano['logs']),
            ];
        }

        return $histórico;
    }

    /**
     * Obtém detalhes de um plano
     */
    public function obterDetalhesPlano(string $idPlano): ?array
    {
        return $this->planosAtivos[$idPlano] ?? null;
    }

    /**
     * Extrai JSON do plano
     */
    private function extrairPlanoJSON(string $texto): ?array
    {
        if (preg_match('/\{[\s\S]*\}/m', $texto, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        return null;
    }

    /**
     * Extrai JSON do debug
     */
    private function extrairDebugJSON(string $texto): ?array
    {
        return $this->extrairPlanoJSON($texto);
    }

    /**
     * Extrai JSON da melhoria
     */
    private function extrairMelhoriaJSON(string $texto): ?array
    {
        return $this->extrairPlanoJSON($texto);
    }

    /**
     * Salva estado
     */
    private function salvar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['autonomous_agent'] = $this->planosAtivos;
        }
    }

    /**
     * Carrega estado
     */
    private function carregar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['autonomous_agent'])) {
            $this->planosAtivos = $_SESSION['autonomous_agent'];
        }
    }
}
