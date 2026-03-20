<?php

declare(strict_types=1);

namespace App\Documents;

use App\Documents\Readers\DocumentReader;
use App\Documents\Readers\TextReader;
use App\Documents\Readers\CsvReader;
use App\Documents\Readers\ExcelReader;
use App\Documents\Readers\PdfReader;
use RuntimeException;

class DocumentManager
{
    protected array $leitores = [];
    protected array $extensoesSuportadas = [
        'txt' => TextReader::class,
        'md' => TextReader::class,
        'json' => TextReader::class,
        'csv' => CsvReader::class,
        'xlsx' => ExcelReader::class,
        'xls' => ExcelReader::class,
        'pdf' => PdfReader::class,
    ];

    public function __construct()
    {
        // Inicializar leitores
        foreach ($this->extensoesSuportadas as $extensao => $classe) {
            if (!isset($this->leitores[$classe])) {
                $this->leitores[$classe] = new $classe();
            }
        }
    }

    /**
     * Obtém extensão de arquivo
     */
    protected function obterExtensao(string $caminhoArquivo): string
    {
        return strtolower(pathinfo($caminhoArquivo, PATHINFO_EXTENSION));
    }

    /**
     * Obtém leitor apropriado para arquivo
     */
    protected function obterLeitor(string $extensao): DocumentReader
    {
        $classe = $this->extensoesSuportadas[$extensao] ?? null;

        if ($classe === null) {
            throw new RuntimeException('Tipo de arquivo não suportado: ' . $extensao);
        }

        return $this->leitores[$classe];
    }

    /**
     * Lê documento e retorna conteúdo estruturado
     */
    public function ler(string $caminhoArquivo): array
    {
        $extensao = $this->obterExtensao($caminhoArquivo);
        $leitor = $this->obterLeitor($extensao);

        return $leitor->ler($caminhoArquivo);
    }

    /**
     * Lê múltiplos documentos
     */
    public function lerMultiplos(array $caminhos): array
    {
        $resultados = [];

        foreach ($caminhos as $caminho) {
            try {
                $resultados[] = [
                    'sucesso' => true,
                    'caminho' => $caminho,
                    'dados' => $this->ler($caminho),
                ];
            } catch (RuntimeException $e) {
                $resultados[] = [
                    'sucesso' => false,
                    'caminho' => $caminho,
                    'erro' => $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }

    /**
     * Extrai metadados de documento
     */
    public function extrairMetadados(string $caminhoArquivo): array
    {
        $conteudo = file_get_contents($caminhoArquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Falha ao ler arquivo');
        }

        $extensao = $this->obterExtensao($caminhoArquivo);
        $leitor = $this->obterLeitor($extensao);

        return [
            'arquivo' => basename($caminhoArquivo),
            'extensao' => $extensao,
            'tamanho' => filesize($caminhoArquivo),
            'metadados' => $leitor->extrairMetadados($conteudo),
        ];
    }

    /**
     * Busca termo em documento
     */
    public function buscar(string $caminhoArquivo, string $termo): array
    {
        $conteudo = file_get_contents($caminhoArquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Falha ao ler arquivo');
        }

        $extensao = $this->obterExtensao($caminhoArquivo);
        $leitor = $this->obterLeitor($extensao);

        return [
            'arquivo' => basename($caminhoArquivo),
            'extensao' => $extensao,
            'busca' => $leitor->buscar($conteudo, $termo),
        ];
    }

    /**
     * Busca em múltiplos documentos
     */
    public function buscarMultiplos(array $caminhos, string $termo): array
    {
        $resultados = [];

        foreach ($caminhos as $caminho) {
            try {
                $resultados[] = [
                    'sucesso' => true,
                    'dados' => $this->buscar($caminho, $termo),
                ];
            } catch (RuntimeException $e) {
                $resultados[] = [
                    'sucesso' => false,
                    'caminho' => $caminho,
                    'erro' => $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }

    /**
     * Indexa documento para busca rápida
     */
    public function indexar(string $caminhoArquivo): array
    {
        $extensao = $this->obterExtensao($caminhoArquivo);
        $dados = $this->ler($caminhoArquivo);

        // Criar índice simplificado
        $indice = [
            'arquivo' => basename($caminhoArquivo),
            'extensao' => $extensao,
            'tamanho' => filesize($caminhoArquivo),
            'hash' => hash_file('sha256', $caminhoArquivo),
            'palavras_chave' => $this->extrairPalavrasChave($dados),
            'metadata' => $this->extrairMetadados($caminhoArquivo),
            'indexado_em' => date('Y-m-d H:i:s'),
        ];

        return $indice;
    }

    /**
     * Extrai palavras-chave de documento
     */
    protected function extrairPalavrasChave(array $dados): array
    {
        $texto = '';

        // Extrair texto conforme tipo
        if (isset($dados['conteudo'])) {
            $texto = $dados['conteudo'];
        } elseif (isset($dados['dados']) && is_array($dados['dados'])) {
            // Para CSV/Excel, juntar tudo
            $texto = json_encode($dados['dados']);
        }

        // Extrair palavras com mais de 4 caracteres
        preg_match_all('/\b\w{4,}\b/u', strtolower($texto), $matches);
        $palavras = array_count_values($matches[0] ?? []);

        // Retornar top 20 palavras mais frequentes
        arsort($palavras);
        return array_slice(array_keys($palavras), 0, 20);
    }

    /**
     * Gera resumo de documento
     */
    public function gerarResumo(string $caminhoArquivo, int $tamanho = 500): array
    {
        $conteudo = file_get_contents($caminhoArquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Falha ao ler arquivo');
        }

        $resumo = substr($conteudo, 0, $tamanho);
        if (strlen($conteudo) > $tamanho) {
            $resumo = substr($resumo, 0, strrpos($resumo, ' ')) . '...';
        }

        return [
            'arquivo' => basename($caminhoArquivo),
            'tamanho_original' => strlen($conteudo),
            'resumo' => $resumo,
            'truncado' => strlen($conteudo) > $tamanho,
        ];
    }

    /**
     * Lista tipos de arquivo suportados
     */
    public function tiposSuportados(): array
    {
        return [
            'suportados' => array_keys($this->extensoesSuportadas),
            'total' => count($this->extensoesSuportadas),
            'tipos' => [
                'texto' => ['txt', 'md', 'json'],
                'dados' => ['csv', 'xlsx', 'xls'],
                'documentos' => ['pdf'],
            ],
        ];
    }
}