<?php
declare(strict_types=1);
namespace App\Utils;
use RuntimeException;
class DiffApplier
{
    /**
     * Aplica um patch de diff no conteúdo de um arquivo.
     * Suporta o formato unidiff (unified diff).
     *
     * @param string $originalContent O conteúdo original do arquivo.
     * @param string $diffContent O conteúdo do diff no formato unidiff.
     * @return string O conteúdo do arquivo após a aplicação do patch.
     * @throws RuntimeException Se o patch não puder ser aplicado.
     */
    public function applyPatch(string $originalContent, string $diffContent): string
    {
        $originalLines = explode("\n", $originalContent);
        $diffLines = explode("\n", $diffContent);
        $patchedLines = [];
        $originalLineIndex = 0;
        $diffLineIndex = 0;
        while ($diffLineIndex < count($diffLines)) {
            $line = $diffLines[$diffLineIndex];
            if (str_starts_with($line, '--- ') || str_starts_with($line, '+++ ')) {
                // Ignorar cabeçalhos de diff
                $diffLineIndex++;
                continue;
            }
            if (str_starts_with($line, '@@ ')) {
                // Linha de cabeçalho do hunk (chunk de mudanças)
                preg_match('/@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', $line, $matches);
                $originalStart = (int) $matches[1] - 1; // Ajustar para índice 0
                $originalLength = isset($matches[2]) ? (int) $matches[2] : 1;
                $newStart = (int) $matches[3] - 1; // Ajustar para índice 0
                $newLength = isset($matches[4]) ? (int) $matches[4] : 1;
                // Adicionar linhas não modificadas antes do hunk
                while ($originalLineIndex < $originalStart) {
                    $patchedLines[] = $originalLines[$originalLineIndex];
                    $originalLineIndex++;
                }
                $diffLineIndex++;
                continue;
            }
            $firstChar = substr($line, 0, 1);
            $content = substr($line, 1);
            switch ($firstChar) {
                case ' ':
                    // Linha de contexto (não modificada)
                    if (!isset($originalLines[$originalLineIndex]) || $originalLines[$originalLineIndex] !== $content) {
                        throw new RuntimeException("Erro de patch: Linha de contexto não corresponde ao original na linha " . ($originalLineIndex + 1));
                    }
                    $patchedLines[] = $content;
                    $originalLineIndex++;
                    break;
                case '+':
                    // Linha adicionada
                    $patchedLines[] = $content;
                    break;
                case '-':
                    // Linha removida
                    if (!isset($originalLines[$originalLineIndex]) || $originalLines[$originalLineIndex] !== $content) {
                        throw new RuntimeException("Erro de patch: Linha removida não corresponde ao original na linha " . ($originalLineIndex + 1));
                    }
                    $originalLineIndex++;
                    break;
                default:
                    throw new RuntimeException("Erro de patch: Caractere desconhecido no diff: " . $firstChar);
            }
            $diffLineIndex++;
        }
        // Adicionar quaisquer linhas restantes do original após o último hunk
        while ($originalLineIndex < count($originalLines)) {
            $patchedLines[] = $originalLines[$originalLineIndex];
            $originalLineIndex++;
        }
        return implode("\n", $patchedLines);
    }
}
