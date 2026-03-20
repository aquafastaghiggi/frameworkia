<?php

declare(strict_types=1);

namespace App\AI;

use App\Documents\DocumentManager;
use App\Documents\Intelligence\DocumentAnalyzer;
use App\Documents\Intelligence\EntityExtractor;
use App\Code\CodeModifier;
use App\Code\Parser\PhpParser;
use App\Git\GitService;

/**
 * Gerenciador de Contexto Multi-Arquivo
 * 
 * Orquestrador que coleta contexto de múltiplas fontes:
 * - Código (arquivos, estrutura, histórico git)
 * - Documentos (PDF, Excel, CSV, Texto)
 * - Análise semântica e entidades
 * 
 * Objetivo: Fornecer contexto rico para IA trabalhar com múltiplas fontes
 * sem exceder limites de tokens
 */
class MultiContextManager
{
    private DocumentManager $documentManager;
    private DocumentAnalyzer $documentAnalyzer;
    private EntityExtractor $entityExtractor;
    private CodeModifier $codeModifier;
    private PhpParser $phpParser;
    private GitService $gitService;
    private int $maxTokens;
    private int $currentTokens;

    public function __construct(int $maxTokens = 8000)
    {
        $this->documentManager = new DocumentManager();
        $this->documentAnalyzer = new \App\Documents\Intelligence\DocumentAnalyzer($this->documentManager);
        $this->entityExtractor = new EntityExtractor();
        $this->codeModifier = new CodeModifier();
        $this->phpParser = new PhpParser();
        $this->gitService = new GitService();
        $this->maxTokens = $maxTokens;
        $this->currentTokens = 0;
    }

    /**
     * Constrói contexto multi-arquivo
     * 
     * Processa código e documentos, prioriza por importância
     * e respeita limites de tokens
     */
    public function construirContextoMulti(array $opcoes = []): array
    {
        $this->currentTokens = 0;
        $contexto = [
            'código' => [],
            'documentos' => [],
            'análises' => [],
            'entidades_compartilhadas' => [],
            'recomendações' => [],
        ];

        $caminhosCódigo = $opcoes['caminhos_código'] ?? [];
        $caminhosDocumentos = $opcoes['caminhos_documentos'] ?? [];
        $diretorioRaiz = $opcoes['diretorio_raiz'] ?? '';
        $incluirGit = $opcoes['incluir_git'] ?? true;
        $incluirEstrutura = $opcoes['incluir_estrutura'] ?? true;

        // 1. Processar código
        if (!empty($caminhosCódigo)) {
            foreach ($caminhosCódigo as $caminho) {
                if ($this->currentTokens >= $this->maxTokens) {
                    break;
                }
                $contexto['código'][] = $this->analisarArquivoCódigo($caminho);
            }
        }

        // 2. Processar documentos
        if (!empty($caminhosDocumentos)) {
            foreach ($caminhosDocumentos as $caminho) {
                if ($this->currentTokens >= $this->maxTokens) {
                    break;
                }
                $contexto['documentos'][] = $this->analisarDocumento($caminho);
            }
        }

        // 3. Análises combinadas
        if (!empty($caminhosCódigo) && !empty($caminhosDocumentos)) {
            $contexto['análises'] = $this->executarAnálisesCombinadasCódigo($caminhosCódigo, $caminhosDocumentos);
        }

        // 4. Extrair entidades compartilhadas
        if (!empty($caminhosDocumentos)) {
            $contexto['entidades_compartilhadas'] = $this->extrairEntidadesCompartilhadas($caminhosDocumentos);
        }

        // 5. Gerar recomendações
        $contexto['recomendações'] = $this->gerarRecomendacoes($contexto);

        // 6. Incluir git se disponível
        if ($incluirGit && $diretorioRaiz) {
            try {
                if ($this->gitService->isRepository($diretorioRaiz)) {
                    $contexto['git'] = $this->coletarContextoGit($diretorioRaiz);
                }
            } catch (\Throwable $e) {
                // Git não disponível
            }
        }

        // 7. Incluir estrutura do projeto
        if ($incluirEstrutura && $diretorioRaiz) {
            $contexto['estrutura'] = $this->mapearEstruturaProto($diretorioRaiz);
        }

        $contexto['metadata'] = [
            'tokens_utilizados' => $this->currentTokens,
            'tokens_limite' => $this->maxTokens,
            'tokens_disponiveis' => $this->maxTokens - $this->currentTokens,
            'tempo_construcao' => date('Y-m-d H:i:s'),
        ];

        return $contexto;
    }

    /**
     * Analisa um arquivo de código
     */
    private function analisarArquivoCódigo(string $caminho): array
    {
        try {
            if (!file_exists($caminho) || !is_file($caminho)) {
                return ['erro' => 'Arquivo não encontrado'];
            }

            $conteudo = file_get_contents($caminho);
            if ($conteudo === false) {
                return ['erro' => 'Não foi possível ler o arquivo'];
            }

            $tokens = (int) ceil(strlen($conteudo) / 4);
            $this->currentTokens += $tokens;

            $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
            $estrutura = [];

            if ($extensao === 'php') {
                $estrutura = [
                    'funcoes' => $this->phpParser->extrairFuncoes($conteudo),
                    'classes' => $this->phpParser->extrairClasses($conteudo),
                ];
            }

            return [
                'caminho' => $caminho,
                'tipo' => $extensao,
                'tamanho' => strlen($conteudo),
                'linhas' => count(explode("\n", $conteudo)),
                'estrutura' => $estrutura,
                'resumo' => substr($conteudo, 0, 500) . (strlen($conteudo) > 500 ? '...' : ''),
                'hash' => md5($conteudo),
                'tokens_utilizados' => $tokens,
            ];
        } catch (\Throwable $e) {
            return ['erro' => $e->getMessage()];
        }
    }

    /**
     * Analisa um documento
     */
    private function analisarDocumento(string $caminho): array
    {
        try {
            if (!file_exists($caminho) || !is_file($caminho)) {
                return ['erro' => 'Documento não encontrado'];
            }

            $documento = $this->documentManager->ler($caminho);
            $tokens = (int) ceil((strlen($documento['full_text'] ?? '') / 4));
            $this->currentTokens += $tokens;

            // Análise semântica
            $análise = $this->documentAnalyzer->analisar($documento['full_text'] ?? '');

            return [
                'caminho' => $caminho,
                'tipo' => $documento['type'] ?? 'desconhecido',
                'tamanho' => filesize($caminho),
                'tipo_conteúdo' => $documento['content_type'] ?? 'texto',
                'resumo' => $documento['summary'] ?? '',
                'análise_semântica' => [
                    'idioma' => $análise['idioma'] ?? 'desconhecido',
                    'sentimento' => $análise['sentimento'] ?? 'neutro',
                    'complexidade' => $análise['complexidade'] ?? 'média',
                    'qualidade' => $análise['qualidade'] ?? 0.5,
                ],
                'tokens_utilizados' => $tokens,
            ];
        } catch (\Throwable $e) {
            return ['erro' => $e->getMessage()];
        }
    }

    /**
     * Executa análises que combinam código e documentos
     */
    private function executarAnálisesCombinadasCódigo(array $caminhosCódigo, array $caminhosDocumentos): array
    {
        $análises = [];

        // 1. Detectar referências cruzadas
        $análises['referências_cruzadas'] = $this->detectarReferênciasCruzadas($caminhosCódigo, $caminhosDocumentos);

        // 2. Mapear fluxos de dados
        $análises['fluxos_dados'] = $this->mapearFluxosDados($caminhosCódigo, $caminhosDocumentos);

        // 3. Detectar padrões de integração
        $análises['padrões_integração'] = $this->detectarPadrõesIntegração($caminhosCódigo, $caminhosDocumentos);

        return $análises;
    }

    /**
     * Detecta referências cruzadas entre código e documentos
     */
    private function detectarReferênciasCruzadas(array $caminhosCódigo, array $caminhosDocumentos): array
    {
        $referências = [];

        foreach ($caminhosCódigo as $caminhoCode) {
            if (!file_exists($caminhoCode)) {
                continue;
            }

            $conteúdoCode = file_get_contents($caminhoCode);
            if ($conteúdoCode === false) {
                continue;
            }

            foreach ($caminhosDocumentos as $caminhoDoc) {
                if (!file_exists($caminhoDoc)) {
                    continue;
                }

                try {
                    $documento = $this->documentManager->ler($caminhoDoc);
                    $conteúdoDoc = $documento['full_text'] ?? '';

                    // Buscar termos comuns
                    $termosComuns = $this->encontrarTermosComuns($conteúdoCode, $conteúdoDoc);

                    if (!empty($termosComuns)) {
                        $referências[] = [
                            'arquivo_código' => basename($caminhoCode),
                            'documento' => basename($caminhoDoc),
                            'termos_compartilhados' => $termosComuns,
                            'força_relação' => count($termosComuns) / 10,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Ignorar erro ao processar documento
                }
            }
        }

        return $referências;
    }

    /**
     * Encontra termos comuns entre duas strings
     */
    private function encontrarTermosComuns(string $texto1, string $texto2, int $minTamanho = 3): array
    {
        $palavras1 = array_unique(str_word_count(strtolower($texto1), 1));
        $palavras2 = array_unique(str_word_count(strtolower($texto2), 1));

        $comuns = array_intersect($palavras1, $palavras2);
        
        // Filtrar palavras pequenas e comuns
        $stopWords = ['o', 'a', 'e', 'é', 'que', 'de', 'da', 'do', 'para', 'com', 'um', 'uma', 'the', 'and', 'or', 'in'];
        $comuns = array_filter($comuns, function($palavra) use ($minTamanho, $stopWords) {
            return strlen($palavra) >= $minTamanho && !in_array($palavra, $stopWords);
        });

        return array_values(array_slice($comuns, 0, 5));
    }

    /**
     * Mapeia fluxos de dados entre código e documentos
     */
    private function mapearFluxosDados(array $caminhosCódigo, array $caminhosDocumentos): array
    {
        $fluxos = [];

        // Análise básica de padrão de entrada/saída
        foreach ($caminhosCódigo as $caminho) {
            if (!file_exists($caminho)) {
                continue;
            }

            $conteudo = file_get_contents($caminho);
            if ($conteudo === false) {
                continue;
            }

            $entrada = preg_match_all('/(input|get|post|request|read)/i', $conteudo);
            $saída = preg_match_all('/(output|response|return|write|save)/i', $conteudo);
            $processamento = preg_match_all('/(process|transform|calculate|filter)/i', $conteudo);

            if ($entrada > 0 || $saída > 0) {
                $fluxos[] = [
                    'arquivo' => basename($caminho),
                    'entrada' => (int) $entrada,
                    'processamento' => (int) $processamento,
                    'saída' => (int) $saída,
                    'tipo' => $this->classificarFluxo((int) $entrada, (int) $processamento, (int) $saída),
                ];
            }
        }

        return $fluxos;
    }

    /**
     * Classifica o tipo de fluxo
     */
    private function classificarFluxo(int $entrada, int $processamento, int $saída): string
    {
        if ($processamento > ($entrada + $saída) * 2) {
            return 'processamento_pesado';
        }
        if ($entrada > $saída) {
            return 'leitor_dados';
        }
        if ($saída > $entrada) {
            return 'gerador_dados';
        }
        return 'intermediário';
    }

    /**
     * Detecta padrões de integração
     */
    private function detectarPadrõesIntegração(array $caminhosCódigo, array $caminhosDocumentos): array
    {
        $padrões = [];

        // Padrão 1: Validação de dados
        $temValidação = false;
        foreach ($caminhosCódigo as $caminho) {
            if (!file_exists($caminho)) {
                continue;
            }
            $conteudo = file_get_contents($caminho);
            if ($conteudo && (preg_match('/(validate|check|verify|assert)/i', $conteudo))) {
                $temValidação = true;
                break;
            }
        }

        if ($temValidação) {
            $padrões['validação'] = true;
        }

        // Padrão 2: Transformação de dados
        $temTransformação = false;
        foreach ($caminhosCódigo as $caminho) {
            if (!file_exists($caminho)) {
                continue;
            }
            $conteudo = file_get_contents($caminho);
            if ($conteudo && (preg_match('/(transform|convert|map|parse)/i', $conteudo))) {
                $temTransformação = true;
                break;
            }
        }

        if ($temTransformação) {
            $padrões['transformação'] = true;
        }

        // Padrão 3: Armazenamento
        $temArmazenamento = false;
        foreach ($caminhosCódigo as $caminho) {
            if (!file_exists($caminho)) {
                continue;
            }
            $conteudo = file_get_contents($caminho);
            if ($conteudo && (preg_match('/(save|store|persist|write|export)/i', $conteudo))) {
                $temArmazenamento = true;
                break;
            }
        }

        if ($temArmazenamento) {
            $padrões['armazenamento'] = true;
        }

        return $padrões;
    }

    /**
     * Extrai entidades compartilhadas entre documentos
     */
    private function extrairEntidadesCompartilhadas(array $caminhosDocumentos): array
    {
        $todasAsEntidades = [];
        $entidadesCompartilhadas = [];

        foreach ($caminhosDocumentos as $caminho) {
            if (!file_exists($caminho)) {
                continue;
            }

            try {
                $documento = $this->documentManager->ler($caminho);
                $conteúdo = $documento['full_text'] ?? '';

                if ($conteúdo) {
                    $entidades = $this->entityExtractor->extrairEntidades($conteúdo);
                    
                    foreach ($entidades as $tipo => $valores) {
                        if (!isset($todasAsEntidades[$tipo])) {
                            $todasAsEntidades[$tipo] = [];
                        }
                        $todasAsEntidades[$tipo] = array_merge($todasAsEntidades[$tipo], (array) $valores);
                    }
                }
            } catch (\Throwable $e) {
                // Ignorar erro ao processar documento
            }
        }

        // Encontrar entidades que aparecem em múltiplos documentos
        foreach ($todasAsEntidades as $tipo => $valores) {
            $contagem = array_count_values($valores);
            $compartilhadas = array_filter($contagem, function($count) {
                return $count > 1;
            });

            if (!empty($compartilhadas)) {
                $entidadesCompartilhadas[$tipo] = array_keys($compartilhadas);
            }
        }

        return $entidadesCompartilhadas;
    }

    /**
     * Gera recomendações baseadas no contexto
     */
    private function gerarRecomendacoes(array $contexto): array
    {
        $recomendações = [];

        // Recomendação 1: Se há código e documentos
        if (!empty($contexto['código']) && !empty($contexto['documentos'])) {
            $recomendações[] = [
                'tipo' => 'integração_sugerida',
                'mensagem' => 'Detectamos código e documentos relacionados. Considere validar se a implementação está alinhada com a documentação.',
                'ação' => 'revisar_alinhamento',
            ];
        }

        // Recomendação 2: Se há referências cruzadas
        if (!empty($contexto['análises']['referências_cruzadas']) && count($contexto['análises']['referências_cruzadas']) > 3) {
            $recomendações[] = [
                'tipo' => 'alto_acoplamento',
                'mensagem' => 'Múltiplas referências cruzadas detectadas. Considere modularizar o código.',
                'ação' => 'refatorar_modulos',
            ];
        }

        // Recomendação 3: Análise de padrões
        $padrões = $contexto['análises']['padrões_integração'] ?? [];
        if (count($padrões) >= 2) {
            $recomendações[] = [
                'tipo' => 'padrão_detectado',
                'mensagem' => 'Múltiplos padrões de integração encontrados. Considere documentar o fluxo de dados.',
                'ação' => 'documentar_fluxo',
            ];
        }

        return $recomendações;
    }

    /**
     * Coleta contexto Git
     */
    private function coletarContextoGit(string $diretorioRaiz): array
    {
        try {
            return [
                'status' => $this->gitService->getStatus($diretorioRaiz),
                'commits_recentes' => array_slice($this->gitService->getCommitHistory($diretorioRaiz, 1, 5), 0, 3),
                'branch_atual' => $this->gitService->getCurrentBranch($diretorioRaiz),
            ];
        } catch (\Throwable $e) {
            return ['erro' => 'Não foi possível coletar contexto Git'];
        }
    }

    /**
     * Mapeia estrutura do projeto
     */
    private function mapearEstruturaProto(string $diretorioRaiz): array
    {
        $estrutura = [];
        $maxProfundidade = 3;
        
        try {
            $estrutura = $this->explorarDiretório($diretorioRaiz, $maxProfundidade, 0);
        } catch (\Throwable $e) {
            // Ignorar erros
        }

        return $estrutura;
    }

    /**
     * Explora diretório recursivamente
     */
    private function explorarDiretório(string $caminho, int $maxProfundidade, int $profundidadeAtual): array
    {
        if ($profundidadeAtual >= $maxProfundidade) {
            return [];
        }

        $conteúdo = [];

        try {
            if (!is_dir($caminho)) {
                return [];
            }

            $arquivos = array_diff(scandir($caminho) ?: [], ['.', '..']);

            foreach (array_slice($arquivos, 0, 10) as $arquivo) {
                $caminhoCom = $caminho . DIRECTORY_SEPARATOR . $arquivo;
                
                if (is_dir($caminhoCom)) {
                    $conteúdo[] = [
                        'nome' => $arquivo,
                        'tipo' => 'diretório',
                        'filhos' => $this->explorarDiretório($caminhoCom, $maxProfundidade, $profundidadeAtual + 1),
                    ];
                } else {
                    $conteúdo[] = [
                        'nome' => $arquivo,
                        'tipo' => 'arquivo',
                        'extensão' => pathinfo($arquivo, PATHINFO_EXTENSION),
                        'tamanho' => filesize($caminhoCom),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Ignorar erros de permissão
        }

        return $conteúdo;
    }

    /**
     * Constrói prompt enriquecido com múltiplos contextos
     */
    public function construirPromptMultiContexto(string $promptOriginal, array $contexto): string
    {
        $prompt = "# CONTEXTO MULTI-ARQUIVO\n\n";

        // Seção de código
        if (!empty($contexto['código'])) {
            $prompt .= "## CÓDIGO\n";
            foreach ($contexto['código'] as $info) {
                if (!isset($info['erro'])) {
                    $prompt .= "### {$info['caminho']}\n";
                    $prompt .= "- Tipo: {$info['tipo']}\n";
                    $prompt .= "- Linhas: {$info['linhas']}\n";
                    if (!empty($info['estrutura']['funcoes'])) {
                        $prompt .= "- Funções: " . count($info['estrutura']['funcoes']) . "\n";
                    }
                    if (!empty($info['estrutura']['classes'])) {
                        $prompt .= "- Classes: " . count($info['estrutura']['classes']) . "\n";
                    }
                    $prompt .= "\n";
                }
            }
        }

        // Seção de documentos
        if (!empty($contexto['documentos'])) {
            $prompt .= "## DOCUMENTOS\n";
            foreach ($contexto['documentos'] as $info) {
                if (!isset($info['erro'])) {
                    $prompt .= "### {$info['caminho']}\n";
                    $prompt .= "- Tipo: {$info['tipo']}\n";
                    $prompt .= "- Resumo: " . substr($info['resumo'], 0, 100) . "\n";
                    $prompt .= "- Sentimento: {$info['análise_semântica']['sentimento']}\n";
                    $prompt .= "\n";
                }
            }
        }

        // Seção de análises
        if (!empty($contexto['análises'])) {
            $prompt .= "## ANÁLISES COMBINADAS\n";
            
            if (!empty($contexto['análises']['referências_cruzadas'])) {
                $prompt .= "### Referências Cruzadas\n";
                foreach ($contexto['análises']['referências_cruzadas'] as $ref) {
                    $prompt .= "- {$ref['arquivo_código']} ↔ {$ref['documento']}\n";
                }
                $prompt .= "\n";
            }

            if (!empty($contexto['análises']['padrões_integração'])) {
                $prompt .= "### Padrões de Integração\n";
                $prompt .= implode(', ', array_keys($contexto['análises']['padrões_integração'])) . "\n\n";
            }
        }

        // Seção de recomendações
        if (!empty($contexto['recomendações'])) {
            $prompt .= "## RECOMENDAÇÕES\n";
            foreach ($contexto['recomendações'] as $rec) {
                $prompt .= "- **{$rec['tipo']}**: {$rec['mensagem']}\n";
            }
            $prompt .= "\n";
        }

        // Prompt original
        $prompt .= "## TAREFA SOLICITADA\n{$promptOriginal}\n";

        // Metadata
        $prompt .= "\n---\n";
        $prompt .= "Tokens utilizados: {$contexto['metadata']['tokens_utilizados']} / {$contexto['metadata']['tokens_limite']}\n";

        return $prompt;
    }
}
