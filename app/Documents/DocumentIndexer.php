<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;

class DocumentIndexer
{
    protected DocumentManager $manager;
    protected array $indice = [];
    protected string $caminhoIndice;

    public function __construct(DocumentManager $manager)
    {
        $this->manager = $manager;
        $this->caminhoIndice = storage_path('indices/documentos.json');
        $this->carregarIndice();
    }

    /**
     * Carrega índice do armazenamento
     */
    protected function carregarIndice(): void
    {
        if (file_exists($this->caminhoIndice)) {
            $conteudo = file_get_contents($this->caminhoIndice);
            $this->indice = $conteudo ? json_decode($conteudo, true) : [];
        }
    }

    /**
     * Salva índice no armazenamento
     */
    protected function salvarIndice(): void
    {
        $dir = dirname($this->caminhoIndice);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->caminhoIndice, json_encode($this->indice, JSON_PRETTY_PRINT));
    }

    /**
     * Indexa um documento
     */
    public function indexarDocumento(string $caminhoArquivo): array
    {
        if (!file_exists($caminhoArquivo)) {
            throw new RuntimeException('Arquivo não encontrado: ' . $caminhoArquivo);
        }

        $hash = hash_file('sha256', $caminhoArquivo);
        $documento = $this->manager->indexar($caminhoArquivo);
        $documento['hash_verificacao'] = $hash;

        $this->indice[$hash] = $documento;
        $this->salvarIndice();

        return [
            'sucesso' => true,
            'mensagem' => 'Documento indexado com sucesso',
            'dados' => $documento,
        ];
    }

    /**
     * Indexa múltiplos documentos
     */
    public function indexarMultiplos(array $caminhos): array
    {
        $resultados = [];

        foreach ($caminhos as $caminho) {
            try {
                $resultados[] = $this->indexarDocumento($caminho);
            } catch (RuntimeException $e) {
                $resultados[] = [
                    'sucesso' => false,
                    'mensagem' => $e->getMessage(),
                    'caminho' => $caminho,
                ];
            }
        }

        return $resultados;
    }

    /**
     * Busca em índice
     */
    public function buscarNoIndice(string $termo): array
    {
        $termo = strtolower($termo);
        $resultados = [];

        foreach ($this->indice as $documento) {
            $score = 0;

            // Busca no nome do arquivo
            if (stripos($documento['arquivo'], $termo) !== false) {
                $score += 10;
            }

            // Busca nas palavras-chave
            foreach ($documento['palavras_chave'] as $palavra) {
                if (stripos($palavra, $termo) !== false) {
                    $score += 5;
                }
            }

            if ($score > 0) {
                $resultados[] = [
                    'documento' => $documento['arquivo'],
                    'extensao' => $documento['extensao'],
                    'score' => $score,
                    'palavras_chave' => $documento['palavras_chave'],
                ];
            }
        }

        // Ordena por score
        usort($resultados, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'termo_busca' => $termo,
            'total_resultados' => count($resultados),
            'resultados' => $resultados,
        ];
    }

    /**
     * Obtém estatísticas do índice
     */
    public function obterEstatisticas(): array
    {
        $extensoes = [];
        $tamanhoTotal = 0;
        $dataMaisRecente = null;

        foreach ($this->indice as $documento) {
            $ext = $documento['extensao'];
            $extensoes[$ext] = ($extensoes[$ext] ?? 0) + 1;
            $tamanhoTotal += $documento['tamanho'];

            if ($dataMaisRecente === null || $documento['indexado_em'] > $dataMaisRecente) {
                $dataMaisRecente = $documento['indexado_em'];
            }
        }

        return [
            'total_documentos' => count($this->indice),
            'tamanho_total_bytes' => $tamanhoTotal,
            'tamanho_total_mb' => round($tamanhoTotal / 1024 / 1024, 2),
            'documentos_por_tipo' => $extensoes,
            'ultima_atualizacao' => $dataMaisRecente,
        ];
    }

    /**
     * Remove documento do índice
     */
    public function removerDocumento(string $caminhoArquivo): array
    {
        $hash = hash_file('sha256', $caminhoArquivo);

        if (!isset($this->indice[$hash])) {
            return [
                'sucesso' => false,
                'mensagem' => 'Documento não encontrado no índice',
            ];
        }

        $documento = $this->indice[$hash];
        unset($this->indice[$hash]);
        $this->salvarIndice();

        return [
            'sucesso' => true,
            'mensagem' => 'Documento removido do índice',
            'documento_removido' => $documento['arquivo'],
        ];
    }

    /**
     * Limpa índice completo
     */
    public function limparIndice(): array
    {
        $total = count($this->indice);
        $this->indice = [];
        $this->salvarIndice();

        return [
            'sucesso' => true,
            'mensagem' => 'Índice limpo',
            'documentos_removidos' => $total,
        ];
    }

    /**
     * Valida integridade de documentos indexados
     */
    public function validarIntegridade(): array
    {
        $resultados = [
            'documentos_validos' => 0,
            'documentos_invalidos' => [],
            'arquivos_removidos' => [],
        ];

        foreach ($this->indice as $hash => $documento) {
            $caminho = $documento['arquivo'];

            if (!file_exists($caminho)) {
                $resultados['documentos_invalidos'][] = [
                    'arquivo' => $caminho,
                    'motivo' => 'Arquivo não encontrado',
                ];
                continue;
            }

            // Verificar integridade do arquivo
            $hashAtual = hash_file('sha256', $caminho);
            if ($hashAtual !== $documento['hash_verificacao']) {
                $resultados['documentos_invalidos'][] = [
                    'arquivo' => $caminho,
                    'motivo' => 'Hash não corresponde (arquivo modificado)',
                ];
                continue;
            }

            $resultados['documentos_validos']++;
        }

        return $resultados;
    }

    /**
     * Obtém documento indexado
     */
    public function obterDocumento(string $caminhoArquivo): ?array
    {
        $hash = hash_file('sha256', $caminhoArquivo);
        return $this->indice[$hash] ?? null;
    }

    /**
     * Lista todos os documentos indexados
     */
    public function listarDocumentos(): array
    {
        $documentos = [];

        foreach ($this->indice as $documento) {
            $documentos[] = [
                'arquivo' => $documento['arquivo'],
                'extensao' => $documento['extensao'],
                'tamanho' => $documento['tamanho'],
                'tamanho_mb' => round($documento['tamanho'] / 1024 / 1024, 2),
                'indexado_em' => $documento['indexado_em'],
                'total_palavras_chave' => count($documento['palavras_chave']),
            ];
        }

        return [
            'total' => count($documentos),
            'documentos' => $documentos,
        ];
    }
}
