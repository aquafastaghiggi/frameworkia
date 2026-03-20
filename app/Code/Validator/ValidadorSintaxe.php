<?php

declare(strict_types=1);

namespace App\Code\Validator;

use App\Code\Parser\PhpParser;

class ValidadorSintaxe
{
    protected PhpParser $parser;

    public function __construct()
    {
        $this->parser = new PhpParser();
    }

    /**
     * Valida código PHP completo
     * Retorna array com 'valido' => bool e 'erros' => array
     */
    public function validarPhp(string $codigo): array
    {
        $erros = [];

        // Verificar equilibrio de chaves
        $problemasEquilibrio = $this->parser->verificarEquilibrio($codigo);
        if (!empty($problemasEquilibrio)) {
            $erros = array_merge($erros, $problemasEquilibrio);
        }

        // Verificar sintaxe
        if (!$this->parser->validarSintaxe($codigo)) {
            $erros[] = 'Erro de sintaxe PHP detectado';
        }

        return [
            'valido' => empty($erros),
            'erros' => $erros,
        ];
    }

    /**
     * Valida se uma substituição não quebra estrutura
     * $caminhoArquivo: caminho do arquivo original
     * $conteudoOriginal: conteúdo original
     * $conteudoNovo: conteúdo novo após modificação
     */
    public function validarSubstituicao(string $caminhoArquivo, string $conteudoOriginal, string $conteudoNovo): array
    {
        $erros = [];
        $avisos = [];

        // Validação 1: Sintaxe PHP
        if (str_ends_with($caminhoArquivo, '.php')) {
            $validacao = $this->validarPhp($conteudoNovo);
            if (!$validacao['valido']) {
                $erros = array_merge($erros, $validacao['erros']);
            }
        }

        // Validação 2: Comprimento do arquivo não pode ser zero
        if (strlen($conteudoNovo) === 0) {
            $erros[] = 'O arquivo resultante não pode estar vazio';
        }

        // Validação 3: Detectar perda de linhas
        $linhasOriginais = count(explode("\n", $conteudoOriginal));
        $linhasNovas = count(explode("\n", $conteudoNovo));
        
        if ($linhasNovas < $linhasOriginais * 0.5) {
            $avisos[] = 'Aviso: O arquivo perdeu mais de 50% de suas linhas (' . $linhasOriginais . ' → ' . $linhasNovas . ')';
        }

        // Validação 4: Detectar perda de funções (para PHP)
        if (str_ends_with($caminhoArquivo, '.php')) {
            $funcoesOriginais = $this->parser->extrairFuncoes($conteudoOriginal);
            $funcoesNovas = $this->parser->extrairFuncoes($conteudoNovo);
            
            $nomesOriginais = array_map(fn($f) => $f['nome'], $funcoesOriginais);
            $nomesNovas = array_map(fn($f) => $f['nome'], $funcoesNovas);
            
            $funcoesPerdidas = array_diff($nomesOriginais, $nomesNovas);
            if (!empty($funcoesPerdidas)) {
                $avisos[] = 'Aviso: ' . count($funcoesPerdidas) . ' função(ões) foram perdidas: ' . implode(', ', $funcoesPerdidas);
            }
        }

        // Validação 5: Detectar perda de classes (para PHP)
        if (str_ends_with($caminhoArquivo, '.php')) {
            $classesOriginais = $this->parser->extrairClasses($conteudoOriginal);
            $classesNovas = $this->parser->extrairClasses($conteudoNovo);
            
            $nomesOriginais = array_map(fn($c) => $c['nome'], $classesOriginais);
            $nomesNovas = array_map(fn($c) => $c['nome'], $classesNovas);
            
            $classesPerdidas = array_diff($nomesOriginais, $nomesNovas);
            if (!empty($classesPerdidas)) {
                $avisos[] = 'Aviso: ' . count($classesPerdidas) . ' classe(s) foram perdidas: ' . implode(', ', $classesPerdidas);
            }
        }

        // Validação 6: Detectar uso de funções perigosas
        $funcoesPerigosas = ['eval', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];
        foreach ($funcoesPerigosas as $funcao) {
            if (preg_match('/\\b' . preg_quote($funcao) . '\\s*\(/', $conteudoNovo)) {
                $avisos[] = 'Aviso de segurança: Função "' . $funcao . '" detectada no código';
            }
        }

        return [
            'valido' => empty($erros),
            'erros' => $erros,
            'avisos' => $avisos,
        ];
    }
}
