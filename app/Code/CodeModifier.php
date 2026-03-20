<?php

declare(strict_types=1);

namespace App\Code;

use App\Code\Parser\PhpParser;
use App\Code\Validator\ValidadorSintaxe;
use App\Code\Diff\GeradorDiff;
use RuntimeException;

class CodeModifier
{
    protected PhpParser $parser;
    protected ValidadorSintaxe $validador;
    protected GeradorDiff $gerador;

    public function __construct()
    {
        $this->parser = new PhpParser();
        $this->validador = new ValidadorSintaxe();
        $this->gerador = new GeradorDiff();
    }

    /**
     * Extrai instrução de substituição do tipo "LOCALIZAR: ... SUBSTITUIR POR: ..."
     */
    public function extrairInstrucaoSubstituicao(string $texto): ?array
    {
        $padrao = '/LOCALIZAR:\s*(.*?)\s*SUBSTITUIR POR:\s*(.*)/s';

        if (!preg_match($padrao, $texto, $correspondencias)) {
            return null;
        }

        $encontrar = trim((string) ($correspondencias[1] ?? ''));
        $substituir = trim((string) ($correspondencias[2] ?? ''));

        // Remove blocos ```php ``` se existirem
        $encontrar = preg_replace('/^```[a-zA-Z]*\s*/', '', $encontrar);
        $encontrar = preg_replace('/```$/', '', $encontrar);

        $substituir = preg_replace('/^```[a-zA-Z]*\s*/', '', $substituir);
        $substituir = preg_replace('/```$/', '', $substituir);

        $encontrar = trim($encontrar);
        $substituir = trim($substituir);

        if ($encontrar === '' && $substituir === '') {
            return null;
        }

        return [
            'encontrar' => $encontrar,
            'substituir' => $substituir,
        ];
    }

    /**
     * Extrai bloco de código do tipo ```php ... ```
     */
    public function extrairBlocoCodig(string $texto): string
    {
        $padrao = '/```[a-zA-Z0-9]*\s*(.*?)```/s';

        if (preg_match($padrao, $texto, $correspondencias)) {
            return trim((string) ($correspondencias[1] ?? ''));
        }

        return '';
    }

    /**
     * Gera preview de alteração (sem aplicar)
     * Retorna array com diff, resumo, validação e confirmação necessária
     */
    public function gerarPreview(
        string $caminhoArquivo,
        string $conteudoOriginal,
        string $conteudoNovo
    ): array {
        // Gerar diff
        $diff = $this->gerador->gerarDiff($conteudoOriginal, $conteudoNovo);
        
        // Gerar resumo
        $resumo = $this->gerador->gerarResumo($conteudoOriginal, $conteudoNovo);
        
        // Validar
        $validacao = $this->validador->validarSubstituicao(
            $caminhoArquivo,
            $conteudoOriginal,
            $conteudoNovo
        );

        return [
            'valido' => $validacao['valido'],
            'diff' => $diff,
            'resumo' => $resumo,
            'erros' => $validacao['erros'],
            'avisos' => $validacao['avisos'],
            'requer_confirmacao' => !$validacao['valido'] || !empty($validacao['avisos']),
        ];
    }

    /**
     * Aplica substituição com segurança
     * Valida antes de aplicar, gera backup, escreve arquivo
     */
    public function aplicarSubstituicaoSegura(
        string $caminhoArquivo,
        string $conteudoOriginal,
        string $conteudoNovo,
        callable $criarBackup,
        callable $escreverArquivo
    ): array {
        // Validar
        $validacao = $this->validador->validarSubstituicao(
            $caminhoArquivo,
            $conteudoOriginal,
            $conteudoNovo
        );

        if (!$validacao['valido']) {
            throw new RuntimeException(
                'Validação falhou: ' . implode(', ', $validacao['erros'])
            );
        }

        // Criar backup
        $caminhoBackup = $criarBackup($caminhoArquivo, $conteudoOriginal);

        try {
            // Escrever novo conteúdo
            $escreverArquivo($caminhoArquivo, $conteudoNovo);

            return [
                'sucesso' => true,
                'mensagem' => 'Alterações aplicadas com sucesso',
                'backup' => $caminhoBackup,
                'avisos' => $validacao['avisos'],
            ];
        } catch (RuntimeException $e) {
            throw new RuntimeException('Erro ao escrever arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Detecta tipo de mudança entre duas versões
     */
    public function detectarTipoMudanca(string $conteudoOriginal, string $conteudoNovo): string
    {
        $linhasOriginais = count(explode("\n", $conteudoOriginal));
        $linhasNovas = count(explode("\n", $conteudoNovo));

        if ($linhasNovas === 0) {
            return 'arquivo_vazio';
        }

        if ($linhasNovas > $linhasOriginais * 1.5) {
            return 'grande_adicao';
        }

        if ($linhasNovas < $linhasOriginais * 0.5) {
            return 'grande_remocao';
        }

        if ($conteudoOriginal === $conteudoNovo) {
            return 'sem_alteracoes';
        }

        return 'mudanca_moderada';
    }

    /**
     * Gera relatório completo de mudança
     */
    public function gerarRelatorio(
        string $caminhoArquivo,
        string $conteudoOriginal,
        string $conteudoNovo
    ): array {
        $tipoMudanca = $this->detectarTipoMudanca($conteudoOriginal, $conteudoNovo);
        $preview = $this->gerarPreview($caminhoArquivo, $conteudoOriginal, $conteudoNovo);

        return [
            'arquivo' => $caminhoArquivo,
            'tipo_mudanca' => $tipoMudanca,
            'preview' => $preview,
            'funcoes_afetadas' => str_ends_with($caminhoArquivo, '.php') 
                ? $this->compararFuncoes($conteudoOriginal, $conteudoNovo)
                : null,
            'classes_afetadas' => str_ends_with($caminhoArquivo, '.php')
                ? $this->compararClasses($conteudoOriginal, $conteudoNovo)
                : null,
        ];
    }

    /**
     * Compara funções entre original e novo
     */
    protected function compararFuncoes(string $conteudoOriginal, string $conteudoNovo): array
    {
        $funcoesOriginais = $this->parser->extrairFuncoes($conteudoOriginal);
        $funcoesNovas = $this->parser->extrairFuncoes($conteudoNovo);

        $nomesOriginais = array_map(fn($f) => $f['nome'], $funcoesOriginais);
        $nomesNovas = array_map(fn($f) => $f['nome'], $funcoesNovas);

        return [
            'adicionadas' => array_diff($nomesNovas, $nomesOriginais),
            'removidas' => array_diff($nomesOriginais, $nomesNovas),
            'modificadas' => array_intersect($nomesOriginais, $nomesNovas),
        ];
    }

    /**
     * Compara classes entre original e novo
     */
    protected function compararClasses(string $conteudoOriginal, string $conteudoNovo): array
    {
        $classesOriginais = $this->parser->extrairClasses($conteudoOriginal);
        $classesNovas = $this->parser->extrairClasses($conteudoNovo);

        $nomesOriginais = array_map(fn($c) => $c['nome'], $classesOriginais);
        $nomesNovas = array_map(fn($c) => $c['nome'], $classesNovas);

        return [
            'adicionadas' => array_diff($nomesNovas, $nomesOriginais),
            'removidas' => array_diff($nomesOriginais, $nomesNovas),
            'modificadas' => array_intersect($nomesOriginais, $nomesNovas),
        ];
    }
}
