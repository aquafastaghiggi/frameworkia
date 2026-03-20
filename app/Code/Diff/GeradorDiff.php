<?php

declare(strict_types=1);

namespace App\Code\Diff;

class GeradorDiff
{
    /**
     * Gera um diff no estilo unified entre dois textos
     */
    public function gerarDiff(string $conteudoOriginal, string $conteudoNovo): string
    {
        $linhasOriginais = explode("\n", $conteudoOriginal);
        $linhasNovas = explode("\n", $conteudoNovo);

        $diff = "--- Original\n";
        $diff .= "+++ Modificado\n";

        // Algoritmo simples de diff (LCS-based)
        $sequencia = $this->encontrarSequenciaComum($linhasOriginais, $linhasNovas);
        
        $i = 0;
        $j = 0;
        $contextSize = 3;
        
        foreach ($sequencia as $match) {
            // Linhas removidas
            while ($i < $match['orig']) {
                $diff .= "- " . $linhasOriginais[$i] . "\n";
                $i++;
            }
            
            // Linhas adicionadas
            while ($j < $match['novo']) {
                $diff .= "+ " . $linhasNovas[$j] . "\n";
                $j++;
            }
            
            // Linhas em comum (contexto)
            if ($i < count($linhasOriginais) && $j < count($linhasNovas) && $linhasOriginais[$i] === $linhasNovas[$j]) {
                $diff .= "  " . $linhasOriginais[$i] . "\n";
                $i++;
                $j++;
            }
        }

        // Linhas restantes
        while ($i < count($linhasOriginais)) {
            $diff .= "- " . $linhasOriginais[$i] . "\n";
            $i++;
        }

        while ($j < count($linhasNovas)) {
            $diff .= "+ " . $linhasNovas[$j] . "\n";
            $j++;
        }

        return $diff;
    }

    /**
     * Gera resumo das alterações (simples)
     */
    public function gerarResumo(string $conteudoOriginal, string $conteudoNovo): array
    {
        $linhasOriginais = explode("\n", $conteudoOriginal);
        $linhasNovas = explode("\n", $conteudoNovo);

        $linhAsAdicionadas = 0;
        $linhasRemovidas = 0;

        if (count($linhasNovas) > count($linhasOriginais)) {
            $linhAsAdicionadas = count($linhasNovas) - count($linhasOriginais);
        } else {
            $linhasRemovidas = count($linhasOriginais) - count($linhasNovas);
        }

        return [
            'linhas_adicionadas' => $linhAsAdicionadas,
            'linhas_removidas' => $linhasRemovidas,
            'linhas_totais_original' => count($linhasOriginais),
            'linhas_totais_novo' => count($linhasNovas),
            'percentual_mudanca' => round(abs($linhAsAdicionadas - $linhasRemovidas) / max(count($linhasOriginais), 1) * 100, 2),
        ];
    }

    /**
     * Encontra sequência de linhas comuns (algoritmo simplificado)
     */
    protected function encontrarSequenciaComum(array $linhasOriginais, array $linhasNovas): array
    {
        $sequencia = [];
        $i = 0;
        $j = 0;

        while ($i < count($linhasOriginais) && $j < count($linhasNovas)) {
            if ($linhasOriginais[$i] === $linhasNovas[$j]) {
                $sequencia[] = [
                    'orig' => $i,
                    'novo' => $j,
                ];
                $i++;
                $j++;
            } else {
                $i++;
            }
        }

        return $sequencia;
    }

    /**
     * Formata diff para exibição HTML
     */
    public function formatarHtml(string $diffTexto): string
    {
        $html = '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">';
        
        $linhas = explode("\n", $diffTexto);
        foreach ($linhas as $linha) {
            if (str_starts_with($linha, '-')) {
                $html .= '<span style="color: #d32f2f; background: #ffebee;">' . htmlspecialchars($linha) . '</span><br>';
            } elseif (str_starts_with($linha, '+')) {
                $html .= '<span style="color: #388e3c; background: #e8f5e9;">' . htmlspecialchars($linha) . '</span><br>';
            } elseif (str_starts_with($linha, '---') || str_starts_with($linha, '+++')) {
                $html .= '<span style="color: #1976d2; font-weight: bold;">' . htmlspecialchars($linha) . '</span><br>';
            } else {
                $html .= htmlspecialchars($linha) . '<br>';
            }
        }
        
        $html .= '</pre>';
        return $html;
    }
}
