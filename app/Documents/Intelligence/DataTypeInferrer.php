<?php

declare(strict_types=1);

namespace App\Documents\Intelligence;

class DataTypeInferrer
{
    /**
     * Infere tipo de valor único
     */
    public function inferirTipo(string $valor): string
    {
        $valor = trim($valor);

        if ($valor === '' || strtolower($valor) === 'n/a' || strtolower($valor) === 'null') {
            return 'vazio';
        }

        if ($this->ehBooleano($valor)) {
            return 'booleano';
        }

        if ($this->ehInteiro($valor)) {
            return 'inteiro';
        }

        if ($this->ehDecimal($valor)) {
            return 'decimal';
        }

        if ($this->ehCurrencia($valor)) {
            return 'moeda';
        }

        if ($this->ehData($valor)) {
            return 'data';
        }

        if ($this->ehEmail($valor)) {
            return 'email';
        }

        if ($this->ehUrl($valor)) {
            return 'url';
        }

        if ($this->ehTelefone($valor)) {
            return 'telefone';
        }

        if ($this->ehCpfCnpj($valor)) {
            return 'cpf_cnpj';
        }

        if ($this->ehPercentual($valor)) {
            return 'percentual';
        }

        return 'texto';
    }

    /**
     * Infere tipos de múltiplos valores (coluna)
     */
    public function inferirTipoColuna(array $valores): array
    {
        $tipos = [];
        $contadores = [];

        foreach ($valores as $valor) {
            $tipo = $this->inferirTipo($valor);
            $tipos[] = $tipo;
            $contadores[$tipo] = ($contadores[$tipo] ?? 0) + 1;
        }

        // Tipo predominante
        arsort($contadores);
        $tipoPrincipal = array_key_first($contadores) ?? 'texto';
        $percentualPrincipal = count($valores) > 0 ? ($contadores[$tipoPrincipal] / count($valores)) * 100 : 0;

        return [
            'tipo_predominante' => $tipoPrincipal,
            'confianca_percentual' => round($percentualPrincipal, 1),
            'distribuicao_tipos' => $contadores,
            'total_valores' => count($valores),
            'valores_vazios' => $contadores['vazio'] ?? 0,
        ];
    }

    /**
     * Analisa estrutura completa de dados
     */
    public function analisarEstrutura(array $dados, array $cabecalhos = []): array
    {
        if (empty($dados)) {
            return ['erro' => 'Dados vazios'];
        }

        // Se não houver cabeçalhos, usar números
        if (empty($cabecalhos)) {
            $primeiraLinha = $dados[0];
            for ($i = 0; $i < count($primeiraLinha); $i++) {
                $cabecalhos[] = 'Coluna_' . ($i + 1);
            }
        }

        $analise = [
            'total_linhas' => count($dados),
            'total_colunas' => count($cabecalhos),
            'colunas' => [],
        ];

        // Analisar cada coluna
        for ($col = 0; $col < count($cabecalhos); $col++) {
            $colunaDados = array_column($dados, $col);
            $inferencia = $this->inferirTipoColuna($colunaDados);

            $analise['colunas'][] = [
                'nome' => $cabecalhos[$col] ?? 'Coluna_' . ($col + 1),
                'indice' => $col,
                'tipo_detectado' => $inferencia['tipo_predominante'],
                'confianca' => $inferencia['confianca_percentual'],
                'valores_vazios' => $inferencia['valores_vazios'],
                'valores_unicos' => count(array_unique($colunaDados)),
                'comprimento_medio' => $this->calcularComprimentoMedio($colunaDados),
                'valor_minimo' => $this->extrairMinimo($colunaDados),
                'valor_maximo' => $this->extrairMaximo($colunaDados),
            ];
        }

        return $analise;
    }

    /**
     * Detecta padrões em dados
     */
    public function detectarPadroes(array $valores): array
    {
        $padroes = [];

        // Padrão sequencial
        if ($this->ehSequencial($valores)) {
            $padroes[] = 'sequencial';
        }

        // Padrão repetitivo
        $frequencia = array_count_values($valores);
        if (max($frequencia) >= count($valores) * 0.5) {
            $padroes[] = 'com_repeticoes_altas';
        }

        // Distribuição uniforme
        if ($this->ehDistribuicaoUniforme($frequencia, count($valores))) {
            $padroes[] = 'distribuicao_uniforme';
        }

        // Valores duplicados
        if (count(array_unique($valores)) < count($valores) * 0.8) {
            $padroes[] = 'muitos_duplicados';
        }

        return [
            'padroes_detectados' => $padroes,
            'valores_unicos' => count(array_unique($valores)),
            'total_valores' => count($valores),
            'taxa_unica' => round((count(array_unique($valores)) / count($valores)) * 100, 1),
        ];
    }

    // ============ MÉTODOS PRIVADOS ============

    protected function ehBooleano(string $valor): bool
    {
        $valor = strtolower($valor);
        return in_array($valor, ['sim', 'não', 'verdadeiro', 'falso', 'true', 'false', 's', 'n', '1', '0', 'yes', 'no']);
    }

    protected function ehInteiro(string $valor): bool
    {
        return preg_match('/^-?\d+$/', trim($valor)) === 1;
    }

    protected function ehDecimal(string $valor): bool
    {
        return preg_match('/^-?\d+([.,]\d+)?$/', trim($valor)) === 1;
    }

    protected function ehCurrencia(string $valor): bool
    {
        return preg_match('/^(R\$|USD|\$|€|£)?\s*\d+([.,]\d+)?$/', trim($valor)) === 1;
    }

    protected function ehData(string $valor): bool
    {
        // DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD, etc
        return preg_match('/^(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4}|\d{4}[-]\d{1,2}[-]\d{1,2})$/', trim($valor)) === 1;
    }

    protected function ehEmail(string $valor): bool
    {
        return filter_var(trim($valor), FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function ehUrl(string $valor): bool
    {
        return filter_var(trim($valor), FILTER_VALIDATE_URL) !== false;
    }

    protected function ehTelefone(string $valor): bool
    {
        return preg_match('/^(\+\d{1,3})?\s?(\(?\d{1,4}\)?)?[\s.-]?\d{3,4}[\s.-]?\d{3,4}[\s.-]?\d{3,4}$/', trim($valor)) === 1;
    }

    protected function ehCpfCnpj(string $valor): bool
    {
        $valor = preg_replace('/\D/', '', trim($valor));

        // CPF: 11 dígitos
        if (strlen($valor) === 11) {
            return $this->validarCpf($valor);
        }

        // CNPJ: 14 dígitos
        if (strlen($valor) === 14) {
            return $this->validarCnpj($valor);
        }

        return false;
    }

    protected function ehPercentual(string $valor): bool
    {
        return preg_match('/^-?\d+([.,]\d+)?%?$/', trim($valor)) === 1;
    }

    protected function validarCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Cálculo de validação simplificado
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }

        $digito1 = 11 - ($soma % 11);
        $digito1 = $digito1 >= 10 ? 0 : $digito1;

        return intval($cpf[9]) === $digito1;
    }

    protected function validarCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // Cálculo simplificado
        $soma = 0;
        $multiplicador = 5;
        for ($i = 0; $i < 12; $i++) {
            $soma += intval($cnpj[$i]) * $multiplicador;
            $multiplicador = $multiplicador === 2 ? 9 : $multiplicador - 1;
        }

        $digito1 = 11 - ($soma % 11);
        $digito1 = $digito1 >= 10 ? 0 : $digito1;

        return intval($cnpj[12]) === $digito1;
    }

    protected function ehSequencial(array $valores): bool
    {
        if (count($valores) < 3) {
            return false;
        }

        $numericos = array_filter($valores, fn($v) => $this->ehInteiro($v));

        if (count($numericos) < count($valores) * 0.8) {
            return false;
        }

        $numericos = array_values($numericos);
        $numericos = array_map('intval', $numericos);
        sort($numericos);

        $diferenca = null;
        for ($i = 1; $i < count($numericos); $i++) {
            if ($diferenca === null) {
                $diferenca = $numericos[$i] - $numericos[$i - 1];
            } elseif ($numericos[$i] - $numericos[$i - 1] !== $diferenca) {
                return false;
            }
        }

        return true;
    }

    protected function ehDistribuicaoUniforme(array $frequencia, int $total): bool
    {
        if (empty($frequencia) || $total === 0) {
            return false;
        }

        $media = $total / count($frequencia);
        $desvio = 0;

        foreach ($frequencia as $freq) {
            $desvio += abs($freq - $media);
        }

        $desvio = $desvio / count($frequencia);

        return $desvio <= $media * 0.3; // Desvio máximo de 30%
    }

    protected function calcularComprimentoMedio(array $valores): float
    {
        if (empty($valores)) {
            return 0;
        }

        $totalChars = array_reduce($valores, fn($carry, $v) => $carry + strlen($v), 0);
        return round($totalChars / count($valores), 2);
    }

    protected function extrairMinimo(array $valores): string
    {
        $numericos = array_filter($valores, fn($v) => $this->ehDecimal($v));

        if (empty($numericos)) {
            return min(array_filter($valores, fn($v) => $v !== ''));
        }

        return (string) min(array_map('floatval', $numericos));
    }

    protected function extrairMaximo(array $valores): string
    {
        $numericos = array_filter($valores, fn($v) => $this->ehDecimal($v));

        if (empty($numericos)) {
            return max(array_filter($valores, fn($v) => $v !== ''));
        }

        return (string) max(array_map('floatval', $numericos));
    }
}
