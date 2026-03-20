<?php

declare(strict_types=1);

namespace App\Code\Parser;

class PhpParser
{
    /**
     * Extrai todas as funções do código PHP
     */
    public function extrairFuncoes(string $codigo): array
    {
        $funcoes = [];
        
        $padrao = '/(?:public|private|protected|static)?\s*(?:async\s+)?function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(([^)]*)\)\s*(?:\?[^{]*)?{/';
        
        if (preg_match_all($padrao, $codigo, $correspondencias, PREG_OFFSET_CAPTURE)) {
            foreach ($correspondencias[0] as $indice => $correspondencia) {
                $nome = $correspondencias[1][$indice][0];
                $parametros = $correspondencias[2][$indice][0];
                $offsetInicio = $correspondencia[1];
                $linhaInicio = $this->obterLinhaNoOffset($codigo, $offsetInicio);
                
                $offsetFim = $this->encontrarChaveFechar($codigo, $offsetInicio);
                $linhaFim = $this->obterLinhaNoOffset($codigo, $offsetFim);
                
                $funcoes[] = [
                    'nome' => $nome,
                    'parametros' => trim($parametros),
                    'linha_inicio' => $linhaInicio,
                    'linha_fim' => $linhaFim,
                    'offset_inicio' => $offsetInicio,
                    'offset_fim' => $offsetFim,
                    'corpo' => substr($codigo, $offsetInicio, $offsetFim - $offsetInicio + 1),
                ];
            }
        }
        
        return $funcoes;
    }

    /**
     * Extrai todas as classes do código PHP
     */
    public function extrairClasses(string $codigo): array
    {
        $classes = [];
        
        $padrao = '/(?:abstract\s+)?(?:final\s+)?class\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*(?:extends\s+([a-zA-Z_][a-zA-Z0-9_\\]*))?\s*(?:implements\s+([^{]*))?{/';
        
        if (preg_match_all($padrao, $codigo, $correspondencias, PREG_OFFSET_CAPTURE)) {
            foreach ($correspondencias[0] as $indice => $correspondencia) {
                $nome = $correspondencias[1][$indice][0];
                $estende = $correspondencias[2][$indice][0] ?? null;
                $implementa = $correspondencias[3][$indice][0] ?? null;
                $offsetInicio = $correspondencia[1];
                $linhaInicio = $this->obterLinhaNoOffset($codigo, $offsetInicio);
                
                $offsetFim = $this->encontrarChaveFechar($codigo, $offsetInicio);
                $linhaFim = $this->obterLinhaNoOffset($codigo, $offsetFim);
                
                $classes[] = [
                    'nome' => $nome,
                    'estende' => $estende ? trim($estende) : null,
                    'implementa' => $implementa ? array_map('trim', explode(',', $implementa)) : [],
                    'linha_inicio' => $linhaInicio,
                    'linha_fim' => $linhaFim,
                    'offset_inicio' => $offsetInicio,
                    'offset_fim' => $offsetFim,
                    'corpo' => substr($codigo, $offsetInicio, $offsetFim - $offsetInicio + 1),
                ];
            }
        }
        
        return $classes;
    }

    /**
     * Encontra a chave fechante para uma chave abridora
     * Retorna o offset da chave fechante
     */
    public function encontrarChaveFechar(string $codigo, int $offsetAbridora): int
    {
        $profundidade = 0;
        $emString = false;
        $caracterString = null;
        $emComentario = false;
        $emComentarioLinha = false;

        for ($i = $offsetAbridora; $i < strlen($codigo); $i++) {
            $caracter = $codigo[$i];
            $proximoCaracter = $i + 1 < strlen($codigo) ? $codigo[$i + 1] : '';

            if ($emComentarioLinha) {
                if ($caracter === "\n") {
                    $emComentarioLinha = false;
                }
                continue;
            }

            if ($emComentario) {
                if ($caracter === '*' && $proximoCaracter === '/') {
                    $emComentario = false;
                    $i++;
                }
                continue;
            }

            if (!$emString && $caracter === '/' && $proximoCaracter === '/') {
                $emComentarioLinha = true;
                $i++;
                continue;
            }

            if (!$emString && $caracter === '/' && $proximoCaracter === '*') {
                $emComentario = true;
                $i++;
                continue;
            }

            if (!$emString && ($caracter === '"' || $caracter === "'" || $caracter === '`')) {
                $emString = true;
                $caracterString = $caracter;
                continue;
            }

            if ($emString && $caracter === $caracterString && $codigo[$i - 1] !== '\\') {
                $emString = false;
                continue;
            }

            if (!$emString && !$emComentario && !$emComentarioLinha) {
                if ($caracter === '{') {
                    $profundidade++;
                } elseif ($caracter === '}') {
                    $profundidade--;
                    if ($profundidade === 0) {
                        return $i;
                    }
                }
            }
        }

        return strlen($codigo) - 1;
    }

    /**
     * Obtém o número da linha em um offset
     */
    public function obterLinhaNoOffset(string $codigo, int $offset): int
    {
        return substr_count($codigo, "\n", 0, min($offset, strlen($codigo))) + 1;
    }

    /**
     * Valida a sintaxe PHP
     */
    public function validarSintaxe(string $codigo): bool
    {
        $arquivoTemp = tempnam(sys_get_temp_dir(), 'php_');
        file_put_contents($arquivoTemp, '<?php ' . $codigo);

        $saida = shell_exec("php -l " . escapeshellarg($arquivoTemp) . " 2>&1");
        unlink($arquivoTemp);

        return strpos($saida, 'No syntax errors detected') !== false;
    }

    /**
     * Verifica chaves, colchetes e parênteses balanceados
     */
    public function verificarEquilibrio(string $codigo): array
    {
        $problemas = [];
        $chaves = [];
        $colchetes = [];
        $parenteses = [];
        $emString = false;
        $caracterString = null;

        for ($i = 0; $i < strlen($codigo); $i++) {
            $caracter = $codigo[$i];
            $proximoCaracter = $i + 1 < strlen($codigo) ? $codigo[$i + 1] : '';

            if (!$emString && ($caracter === '"' || $caracter === "'" || $caracter === '`')) {
                $emString = true;
                $caracterString = $caracter;
                continue;
            }

            if ($emString && $caracter === $caracterString && $codigo[$i - 1] !== '\\') {
                $emString = false;
                continue;
            }

            if ($emString) {
                continue;
            }

            if ($caracter === '/' && $proximoCaracter === '/') {
                while ($i < strlen($codigo) && $codigo[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($caracter === '/' && $proximoCaracter === '*') {
                while ($i < strlen($codigo) - 1 && !($codigo[$i] === '*' && $codigo[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;
                continue;
            }

            if ($caracter === '{') {
                $chaves[] = $i;
            } elseif ($caracter === '}') {
                if (empty($chaves)) {
                    $problemas[] = 'Chave fechante sem chave abridora correspondente na posição ' . $i;
                } else {
                    array_pop($chaves);
                }
            }

            if ($caracter === '[') {
                $colchetes[] = $i;
            } elseif ($caracter === ']') {
                if (empty($colchetes)) {
                    $problemas[] = 'Colchete fechante sem colchete abridora correspondente na posição ' . $i;
                } else {
                    array_pop($colchetes);
                }
            }

            if ($caracter === '(') {
                $parenteses[] = $i;
            } elseif ($caracter === ')') {
                if (empty($parenteses)) {
                    $problemas[] = 'Parêntese fechante sem parêntese abridora correspondente na posição ' . $i;
                } else {
                    array_pop($parenteses);
                }
            }
        }

        if (!empty($chaves)) {
            $problemas[] = count($chaves) . ' chave(s) não fechada(s)';
        }
        if (!empty($colchetes)) {
            $problemas[] = count($colchetes) . ' colchete(s) não fechado(s)';
        }
        if (!empty($parenteses)) {
            $problemas[] = count($parenteses) . ' parêntese(s) não fechado(s)';
        }

        return $problemas;
    }
}
