<?php

declare(strict_types=1);

namespace App\Documents\Readers;

use RuntimeException;

class ExcelReader implements DocumentReader
{
    /**
     * Lê arquivo Excel (XLSX)
     * Requer: composer require phpoffice/phpspreadsheet
     */
    public function ler(string $caminhoArquivo): array
    {
        if (!file_exists($caminhoArquivo)) {
            throw new RuntimeException('Arquivo não encontrado: ' . $caminhoArquivo);
        }

        if (!is_readable($caminhoArquivo)) {
            throw new RuntimeException('Arquivo não é legível: ' . $caminhoArquivo);
        }

        // Tentar usar PhpSpreadsheet se disponível
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new RuntimeException('PhpSpreadsheet não está instalado. Execute: composer require phpoffice/phpspreadsheet');
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($caminhoArquivo);
            $planilhas = [];

            foreach ($spreadsheet->getSheetNames() as $nomePlanilha) {
                $sheet = $spreadsheet->getSheetByName($nomePlanilha);
                $dados = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $linha = [];
                    foreach ($row->getCellIterator() as $cell) {
                        $linha[] = $cell->getValue();
                    }
                    $dados[] = $linha;
                }

                $planilhas[] = [
                    'nome' => $nomePlanilha,
                    'total_linhas' => count($dados),
                    'total_colunas' => count($dados[0] ?? []),
                    'dados' => array_slice($dados, 0, 100), // Limitar para performance
                ];
            }

            return [
                'tipo' => 'excel',
                'caminho' => $caminhoArquivo,
                'tamanho' => filesize($caminhoArquivo),
                'total_planilhas' => count($planilhas),
                'planilhas' => $planilhas,
                'data_leitura' => date('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            throw new RuntimeException('Erro ao ler arquivo Excel: ' . $e->getMessage());
        }
    }

    /**
     * Extrai metadados de Excel
     */
    public function extrairMetadados(string $conteudo): array
    {
        // Metadados básicos sobre a estrutura esperada
        return [
            'formato' => 'excel',
            'requer_libraria' => 'phpoffice/phpspreadsheet',
            'tipos_suportados' => ['xlsx', 'xls', 'ods'],
        ];
    }

    /**
     * Busca em Excel
     */
    public function buscar(string $conteudo, string $termo): array
    {
        // Busca em arquivo Excel é limitada sem parsear tudo
        // Implementar busca estruturada seria complexo
        return [
            'termo' => $termo,
            'total_encontrado' => 0,
            'aviso' => 'Busca em Excel requer análise de arquivo binário. Use importar para CSV primeiro.',
            'resultados' => [],
        ];
    }

    /**
     * Detecta cabeçalhos em planilha
     */
    public function detectarCabecalhos(string $caminhoArquivo): array
    {
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new RuntimeException('PhpSpreadsheet não está instalado');
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($caminhoArquivo);
            $sheet = $spreadsheet->getActiveSheet();
            
            $cabecalhos = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $cabecalhos[] = $cell->getValue();
                }
            }

            return [
                'sucesso' => true,
                'cabecalhos' => $cabecalhos,
                'total' => count($cabecalhos),
            ];
        } catch (\Exception $e) {
            throw new RuntimeException('Erro ao detectar cabeçalhos: ' . $e->getMessage());
        }
    }

    /**
     * Infere tipos de dados nas colunas
     */
    public function inferirTipos(string $caminhoArquivo): array
    {
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new RuntimeException('PhpSpreadsheet não está instalado');
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($caminhoArquivo);
            $sheet = $spreadsheet->getActiveSheet();

            $tipos = [];
            $amostra = 20; // Analisar 20 primeiras linhas

            foreach ($sheet->getRowIterator(2, $amostra) as $rowIndex => $row) {
                $colIndex = 0;
                foreach ($row->getCellIterator() as $cell) {
                    $valor = $cell->getValue();
                    
                    if (!isset($tipos[$colIndex])) {
                        $tipos[$colIndex] = [
                            'inteiro' => 0,
                            'decimal' => 0,
                            'data' => 0,
                            'texto' => 0,
                            'vazio' => 0,
                        ];
                    }

                    if ($valor === '' || $valor === null) {
                        $tipos[$colIndex]['vazio']++;
                    } elseif (is_numeric($valor)) {
                        if (strpos($valor, '.') !== false) {
                            $tipos[$colIndex]['decimal']++;
                        } else {
                            $tipos[$colIndex]['inteiro']++;
                        }
                    } elseif (strtotime($valor) !== false) {
                        $tipos[$colIndex]['data']++;
                    } else {
                        $tipos[$colIndex]['texto']++;
                    }

                    $colIndex++;
                }
            }

            $resultado = [];
            foreach ($tipos as $col => $contagem) {
                $tipoProvavel = array_key_first(
                    array_filter($contagem, fn($v) => $v > 0)
                );
                $resultado[$col] = $tipoProvavel;
            }

            return [
                'sucesso' => true,
                'tipos_por_coluna' => $resultado,
                'amostras_analisadas' => $amostra,
            ];
        } catch (\Exception $e) {
            throw new RuntimeException('Erro ao inferir tipos: ' . $e->getMessage());
        }
    }
}
