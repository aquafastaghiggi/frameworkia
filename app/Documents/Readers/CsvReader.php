<?php

declare(strict_types=1);

namespace App\Documents\Readers;

use RuntimeException;

class CsvReader implements DocumentReader
{
    /**
     * Lê arquivo CSV
     */
    public function ler(string $caminhoArquivo): array
    {
        if (!file_exists($caminhoArquivo)) {
            throw new RuntimeException('Arquivo não encontrado: ' . $caminhoArquivo);
        }

        if (!is_readable($caminhoArquivo)) {
            throw new RuntimeException('Arquivo não é legível: ' . $caminhoArquivo);
        }

        $handle = fopen($caminhoArquivo, 'r');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo: ' . $caminhoArquivo);
        }

        $linhas = [];
        $cabecalho = null;
        $indice = 0;

        while (($dados = fgetcsv($handle, 0, ',')) !== false) {
            if ($indice === 0) {
                $cabecalho = $dados;
            } else {
                // Montar objeto com chave => valor
                $linha = [];
                foreach ($cabecalho as $col => $coluna) {
                    $linha[$coluna] = $dados[$col] ?? '';
                }
                $linhas[] = $linha;
            }
            $indice++;
        }

        fclose($handle);

        return [
            'tipo' => 'csv',
            'caminho' => $caminhoArquivo,
            'tamanho' => filesize($caminhoArquivo),
            'total_linhas' => count($linhas),
            'total_colunas' => count($cabecalho ?? []),
            'cabecalho' => $cabecalho ?? [],
            'dados' => $linhas,
            'data_leitura' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Extrai metadados de CSV
     */
    public function extrairMetadados(string $conteudo): array
    {
        $linhas = explode("\n", $conteudo);
        $primeiraLinha = str_getcsv($linhas[0] ?? '');

        return [
            'total_colunas' => count($primeiraLinha),
            'colunas' => $primeiraLinha,
            'total_linhas' => count($linhas),
            'total_registros' => max(0, count($linhas) - 1),
        ];
    }

    /**
     * Busca em CSV
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
