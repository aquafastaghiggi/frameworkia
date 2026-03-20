<?php

declare(strict_types=1);

namespace App\Documents\Readers;

use RuntimeException;

class PdfReader implements DocumentReader
{
    /**
     * Lê arquivo PDF
     * Requer: composer require smalot/pdfparser
     * Fallback: extração basic sem library
     */
    public function ler(string $caminhoArquivo): array
    {
        if (!file_exists($caminhoArquivo)) {
            throw new RuntimeException('Arquivo não encontrado: ' . $caminhoArquivo);
        }

        if (!is_readable($caminhoArquivo)) {
            throw new RuntimeException('Arquivo não é legível: ' . $caminhoArquivo);
        }

        // Tentar usar library se disponível
        if (class_exists('Smalot\PdfParser\Parser')) {
            return $this->lerComLibrary($caminhoArquivo);
        }

        // Fallback: extração básica
        return $this->lerBasico($caminhoArquivo);
    }

    /**
     * Lê PDF usando Smalot PdfParser
     */
    protected function lerComLibrary(string $caminhoArquivo): array
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($caminhoArquivo);

            $texto = '';
            $paginas = [];

            foreach ($pdf->getPages() as $numero => $page) {
                $conteudoPagina = $page->getText();
                $texto .= $conteudoPagina . "\n";

                $paginas[] = [
                    'numero' => $numero + 1,
                    'conteudo' => $conteudoPagina,
                    'tamanho' => strlen($conteudoPagina),
                ];
            }

            return [
                'tipo' => 'pdf',
                'caminho' => $caminhoArquivo,
                'tamanho' => filesize($caminhoArquivo),
                'total_paginas' => count($paginas),
                'total_palavras' => str_word_count($texto),
                'total_caracteres' => strlen($texto),
                'paginas' => array_slice($paginas, 0, 20), // Limitar para performance
                'conteudo_completo' => $texto,
                'data_leitura' => date('Y-m-d H:i:s'),
                'metodo_extracao' => 'smalot/pdfparser',
            ];
        } catch (\Exception $e) {
            throw new RuntimeException('Erro ao ler PDF com library: ' . $e->getMessage());
        }
    }

    /**
     * Lê PDF de forma básica (sem library)
     * Extrai texto através de busca de padrões
     */
    protected function lerBasico(string $caminhoArquivo): array
    {
        $conteudo = file_get_contents($caminhoArquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Falha ao ler arquivo PDF');
        }

        // Tentar extrair stream de texto do PDF
        $texto = $this->extrairTextoBasico($conteudo);

        return [
            'tipo' => 'pdf',
            'caminho' => $caminhoArquivo,
            'tamanho' => filesize($caminhoArquivo),
            'total_paginas' => $this->contarPaginas($conteudo),
            'total_palavras' => str_word_count($texto),
            'total_caracteres' => strlen($texto),
            'conteudo_extraido' => substr($texto, 0, 5000), // Primeiros 5000 caracteres
            'data_leitura' => date('Y-m-d H:i:s'),
            'metodo_extracao' => 'basico_sem_library',
            'aviso' => 'Instalado smalot/pdfparser para melhor extração: composer require smalot/pdfparser',
        ];
    }

    /**
     * Extrai texto básico de PDF (sem library)
     */
    protected function extrairTextoBasico(string $conteudoBinario): string
    {
        // Buscar streams de texto no PDF
        $padrao = '/stream\s+(.*?)\s+endstream/s';
        $texto = '';

        if (preg_match_all($padrao, $conteudoBinario, $matches)) {
            foreach ($matches[1] as $stream) {
                // Remover caracteres não-imprimíveis
                $stream = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $stream);
                $texto .= $stream . " ";
            }
        }

        // Limpar resultado
        $texto = preg_replace('/\s+/', ' ', $texto);
        return trim($texto) ?: 'Não foi possível extrair texto do PDF. Instale smalot/pdfparser para melhor suporte.';
    }

    /**
     * Conta número de páginas
     */
    protected function contarPaginas(string $conteudoBinario): int
    {
        return substr_count($conteudoBinario, '/Type/Page');
    }

    /**
     * Extrai metadados de PDF
     */
    public function extrairMetadados(string $conteudo): array
    {
        return [
            'formato' => 'pdf',
            'suporta_extracao_basica' => true,
            'suporta_extracao_avancada' => class_exists('Smalot\PdfParser\Parser'),
            'libraria_recomendada' => 'smalot/pdfparser',
            'comando_instalacao' => 'composer require smalot/pdfparser',
        ];
    }

    /**
     * Busca em PDF
     */
    public function buscar(string $conteudo, string $termo): array
    {
        $termo_lower = strtolower($termo);
        $conteudo_lower = strtolower($conteudo);

        $posicoes = [];
        $offset = 0;

        while (($pos = strpos($conteudo_lower, $termo_lower, $offset)) !== false) {
            $inicio = max(0, $pos - 50);
            $fim = min(strlen($conteudo), $pos + strlen($termo) + 50);

            $posicoes[] = [
                'posicao' => $pos,
                'contexto' => substr($conteudo, $inicio, $fim - $inicio),
            ];

            $offset = $pos + 1;
        }

        return [
            'termo' => $termo,
            'total_encontrado' => count($posicoes),
            'resultados' => array_slice($posicoes, 0, 20),
        ];
    }
}
