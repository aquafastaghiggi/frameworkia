<?php

declare(strict_types=1);

namespace App\Performance;

/**
 * Gerenciador de Performance e Cache
 * 
 * Responsável por:
 * - Cache de arquivos
 * - Lazy loading de explorer
 * - Índice rápido de projeto
 * - Fila de processamento para tarefas pesadas
 * - Otimizações de query
 */
class PerformanceManager
{
    private array $cache = [];
    private int $cacheTTL = 3600; // 1 hora em segundos
    private array $indice = [];
    private array $filaProcessamento = [];
    private int $maxFilaProcessamento = 1000;

    public function __construct()
    {
        $this->carregar();
    }

    /**
     * Armazena dados em cache
     */
    public function cache(string $chave, $valor, int $ttl = 0): void
    {
        $ttl = $ttl > 0 ? $ttl : $this->cacheTTL;

        $this->cache[$chave] = [
            'valor' => $valor,
            'expira_em' => time() + $ttl,
            'criado_em' => time(),
        ];

        $this->salvar();
    }

    /**
     * Recupera dados do cache
     */
    public function obterDoCache(string $chave)
    {
        if (!isset($this->cache[$chave])) {
            return null;
        }

        $item = $this->cache[$chave];

        // Verificar expiração
        if (time() >= $item['expira_em']) {
            unset($this->cache[$chave]);
            $this->salvar();
            return null;
        }

        return $item['valor'];
    }

    /**
     * Verifica se está em cache
     */
    public function temCache(string $chave): bool
    {
        return $this->obterDoCache($chave) !== null;
    }

    /**
     * Limpa cache
     */
    public function limparCache(): void
    {
        $this->cache = [];
        $this->salvar();
    }

    /**
     * Limpa itens expirados do cache
     */
    public function limparCacheExpirado(): void
    {
        $agora = time();
        $removidos = 0;

        foreach ($this->cache as $chave => $item) {
            if ($agora >= $item['expira_em']) {
                unset($this->cache[$chave]);
                $removidos++;
            }
        }

        if ($removidos > 0) {
            $this->salvar();
        }
    }

    /**
     * Indexa projeto para busca rápida
     */
    public function indexarProjeto(string $diretório, int $maxProfundidade = 4): array
    {
        $chaveÍndice = 'índice_projeto_' . md5($diretório);
        $indiceExistente = $this->obterDoCache($chaveÍndice);

        if ($indiceExistente !== null && is_array($indiceExistente)) {
            return $indiceExistente;
        }

        $indice = $this->explorarDiretório($diretório, $maxProfundidade);
        $this->cache($chaveÍndice, $indice, 1800); // Cache 30 minutos

        return $indice;
    }

    /**
     * Explora diretório recursivamente para indexação
     */
    private function explorarDiretório(string $caminho, int $maxProfundidade, int $profundidadeAtual = 0): array
    {
        if ($profundidadeAtual >= $maxProfundidade) {
            return [];
        }

        $resultado = [];

        try {
            if (!is_dir($caminho)) {
                return [];
            }

            $arquivos = array_diff(scandir($caminho) ?: [], ['.', '..', '.git', 'vendor', 'node_modules']);

            foreach (array_slice($arquivos, 0, 500) as $arquivo) {
                $caminhoCompleto = $caminho . DIRECTORY_SEPARATOR . $arquivo;

                if (is_dir($caminhoCompleto)) {
                    $resultado[] = [
                        'tipo' => 'diretório',
                        'nome' => $arquivo,
                        'caminho' => $caminhoCompleto,
                        'filhos' => $this->explorarDiretório($caminhoCompleto, $maxProfundidade, $profundidadeAtual + 1),
                    ];
                } else {
                    $resultado[] = [
                        'tipo' => 'arquivo',
                        'nome' => $arquivo,
                        'caminho' => $caminhoCompleto,
                        'extensão' => pathinfo($arquivo, PATHINFO_EXTENSION),
                        'tamanho' => filesize($caminhoCompleto),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Ignorar erros
        }

        return $resultado;
    }

    /**
     * Lazy loading - retorna apenas arquivos visíveis do explorer
     */
    public function obterArquivosVisíveis(string $diretório, int $limite = 50): array
    {
        try {
            if (!is_dir($diretório)) {
                return [];
            }

            $arquivos = array_diff(scandir($diretório) ?: [], ['.', '..']);
            $resultado = [];

            foreach (array_slice($arquivos, 0, $limite) as $arquivo) {
                $caminhoCompleto = $diretório . DIRECTORY_SEPARATOR . $arquivo;
                
                $resultado[] = [
                    'nome' => $arquivo,
                    'caminho' => $caminhoCompleto,
                    'é_diretório' => is_dir($caminhoCompleto),
                    'tamanho' => is_file($caminhoCompleto) ? filesize($caminhoCompleto) : 0,
                ];
            }

            return $resultado;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Adiciona tarefa à fila de processamento
     */
    public function adicionarTarefaNaFila(string $tipo, array $dados): array
    {
        if (count($this->filaProcessamento) >= $this->maxFilaProcessamento) {
            return [
                'sucesso' => false,
                'mensagem' => 'Fila de processamento cheia',
            ];
        }

        $tarefa = [
            'id' => 'tarefa_' . uniqid(),
            'tipo' => $tipo,
            'dados' => $dados,
            'status' => 'pendente',
            'criada_em' => time(),
            'resultado' => null,
        ];

        $this->filaProcessamento[] = $tarefa;
        $this->salvar();

        return [
            'sucesso' => true,
            'tarefa_id' => $tarefa['id'],
            'posição_na_fila' => count($this->filaProcessamento),
        ];
    }

    /**
     * Obtém próxima tarefa na fila
     */
    public function obterPróximaTarefa(): ?array
    {
        foreach ($this->filaProcessamento as $indice => $tarefa) {
            if ($tarefa['status'] === 'pendente') {
                $this->filaProcessamento[$indice]['status'] = 'processando';
                $this->salvar();
                return $this->filaProcessamento[$indice];
            }
        }

        return null;
    }

    /**
     * Marca tarefa como concluída
     */
    public function finalizarTarefa(string $tarefaId, $resultado): bool
    {
        foreach ($this->filaProcessamento as $indice => $tarefa) {
            if ($tarefa['id'] === $tarefaId) {
                $this->filaProcessamento[$indice]['status'] = 'concluída';
                $this->filaProcessamento[$indice]['resultado'] = $resultado;
                $this->filaProcessamento[$indice]['concluída_em'] = time();
                $this->salvar();
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém status da fila
     */
    public function obterStatusFila(): array
    {
        $pendentes = count(array_filter($this->filaProcessamento, fn($t) => $t['status'] === 'pendente'));
        $processando = count(array_filter($this->filaProcessamento, fn($t) => $t['status'] === 'processando'));
        $concluídas = count(array_filter($this->filaProcessamento, fn($t) => $t['status'] === 'concluída'));

        return [
            'total_tarefas' => count($this->filaProcessamento),
            'pendentes' => $pendentes,
            'processando' => $processando,
            'concluídas' => $concluídas,
            'capacidade_máxima' => $this->maxFilaProcessamento,
            'ocupação' => (count($this->filaProcessamento) / $this->maxFilaProcessamento) * 100,
        ];
    }

    /**
     * Limpa tarefas concluídas antigas
     */
    public function limparTarefasAntigas(int $horas = 24): int
    {
        $limiteTempo = time() - ($horas * 3600);
        $removidas = 0;

        foreach ($this->filaProcessamento as $indice => $tarefa) {
            if ($tarefa['status'] === 'concluída' && $tarefa['concluída_em'] < $limiteTempo) {
                unset($this->filaProcessamento[$indice]);
                $removidas++;
            }
        }

        if ($removidas > 0) {
            $this->filaProcessamento = array_values($this->filaProcessamento);
            $this->salvar();
        }

        return $removidas;
    }

    /**
     * Gera relatório de performance
     */
    public function gerarRelatório(): array
    {
        $this->limparCacheExpirado();

        return [
            'cache' => [
                'itens' => count($this->cache),
                'tamanho_estimado' => strlen(json_encode($this->cache)),
            ],
            'fila' => $this->obterStatusFila(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Salva estado em sessão
     */
    private function salvar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['performance_manager'] = [
                'cache' => $this->cache,
                'fila' => $this->filaProcessamento,
            ];
        }
    }

    /**
     * Carrega estado da sessão
     */
    private function carregar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['performance_manager'])) {
            $this->cache = $_SESSION['performance_manager']['cache'] ?? [];
            $this->filaProcessamento = $_SESSION['performance_manager']['fila'] ?? [];
        }
    }
}
