<?php

declare(strict_types=1);

namespace App\Documents\Readers;

use RuntimeException;

class TextReader implements DocumentReader
{
    /**
     * Lê arquivo de texto
     */
    public function ler(string $caminhoArquivo): array
    {
        if (!file_exists($caminhoArquivo)) {
            throw new RuntimeException('Arquivo não encontrado: ' . $caminhoArquivo);
        }

        if (!is_readable($caminhoArquivo)) {
            throw new RuntimeException('Arquivo não é legível: ' . $caminhoArquivo);
        }

        $conteudo = file_get_contents($caminhoArquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Falha ao ler arquivo: ' . $caminhoArquivo);
        }

        $tamanho = filesize($caminhoArquivo);
        $linhas = explode("\n", $conteudo);
        $quantidade = count($linhas);

        return [
            'tipo' => 'texto',
            'caminho' => $caminhoArquivo,
            'tamanho' => $tamanho,
            'quantidade_linhas' => $quantidade,
            'conteudo' => $conteudo,
            'linhas' => array_filter($linhas, fn($l) => trim($l) !== ''),
            'data_leitura' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Extrai metadados de texto
     */
    public function extrairMetadados(string $conteudo): array
    {
        $linhas = explode("\n", $conteudo);
        $palavras = str_word_count($conteudo);
        $caracteres = strlen($conteudo);
        
        // Detectar cabeçalhos (linhas que parecem ser títulos)
        $cabecalhos = [];
        foreach ($linhas as $indice => $linha) {
            $linha_trim = trim($linha);
            if (strlen($linha_trim) > 0 && strlen($linha_trim) < 100 && 
                (str_starts_with($linha_trim, '#') || 
                 (strlen($linha_trim) > 3 && ctype_upper(str_replace(' ', '', $linha_trim)) && strlen($linha_trim) < 50))) {
                $cabecalhos[] = [
                    'linha' => $indice + 1,
                    'conteudo' => $linha_trim,
                ];
            }
        }

        return [
            'total_palavras' => $palavras,
            'total_caracteres' => $caracteres,
            'total_linhas' => count($linhas),
            'linhas_vazias' => count($linhas) - count(array_filter($linhas, fn($l) => trim($l) !== '')),
            'cabecalhos_detectados' => count($cabecalhos),
            'cabecalhos' => array_slice($cabecalhos, 0, 10),
        ];
    }

    /**
     * Busca texto em conteúdo
     */
    public function buscar(string $conteudo, string $termo): array
    {
        $termo_lower = strtolower($termo);
        $linhas = explode("\n", $conteudo);
        $resultados = [];

        foreach ($linhas as $indice => $linha) {
            if (stripos($linha, $termo) !== false) {
                $resultados[] = [
                    'linha' => $indice + 1,
                    'conteudo' => trim($linha),
                    'posicao' => stripos($linha, $termo),
                ];
            }
        }

        return [
            'termo' => $termo,
            'total_encontrado' => count($resultados),
            'resultados' => array_slice($resultados, 0, 20),
        ];
    }
}
