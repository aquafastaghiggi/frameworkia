<?php

declare(strict_types=1);

namespace App\Documents\Intelligence;

use App\Documents\DocumentManager;

class DocumentAnalyzer
{
    protected DocumentManager $documentManager;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    /**
     * Analisa documento completo
     */
    public function analisar(string $caminhoArquivo): array
    {
        $metadados = $this->documentManager->extrairMetadados($caminhoArquivo);
        $conteudo = file_get_contents($caminhoArquivo);

        return [
            'arquivo' => $metadados['arquivo'],
            'extensao' => $metadados['extensao'],
            'analise' => [
                'densidade_textual' => $this->calcularDensidadeTextual($conteudo ?? ''),
                'complexidade' => $this->calcularComplexidade($conteudo ?? ''),
                'sentimento' => $this->analisarSentimento($conteudo ?? ''),
                'estatisticas' => $this->extrairEstatisticas($conteudo ?? ''),
                'qualidade' => $this->avaliarQualidade($conteudo ?? ''),
            ],
        ];
    }

    /**
     * Calcula densidade textual (palavras por 1000 caracteres)
     */
    protected function calcularDensidadeTextual(string $texto): float
    {
        if (strlen($texto) === 0) {
            return 0.0;
        }

        $palavras = str_word_count($texto);
        return round(($palavras / strlen($texto)) * 1000, 2);
    }

    /**
     * Calcula complexidade do texto (1-10)
     */
    protected function calcularComplexidade(string $texto): int
    {
        $sentenças = count(preg_split('/[.!?]+/', $texto, -1, PREG_SPLIT_NO_EMPTY)) ?? 0;
        $palavras = str_word_count($texto);

        if ($sentenças === 0 || $palavras === 0) {
            return 1;
        }

        // Média de palavras por sentença
        $mediaPalavras = $palavras / $sentenças;

        // Palavras com mais de 10 caracteres (indicador de complexidade)
        preg_match_all('/\b\w{10,}\b/', $texto, $matches);
        $palavrasComplexas = count($matches[0] ?? []);
        $percentualComplexo = ($palavrasComplexas / $palavras) * 100;

        // Score: 1-10
        $score = min(10, (int)((($mediaPalavras / 20) * 5) + ($percentualComplexo / 10)));
        return max(1, $score);
    }

    /**
     * Analisa sentimento do texto
     */
    protected function analisarSentimento(string $texto): array
    {
        $textoMinusculo = strtolower($texto);

        // Palavras positivas
        $positivas = [
            'ótimo', 'excelente', 'incrível', 'maravilhoso', 'fantástico',
            'perfeito', 'bom', 'adorei', 'melhor', 'sucesso', 'ganhar',
            'feliz', 'alegre', 'amo', 'legal', 'top', 'show',
        ];

        // Palavras negativas
        $negativas = [
            'péssimo', 'horrível', 'ruim', 'terrível', 'fraco', 'falhou',
            'problema', 'erro', 'bug', 'triste', 'odeio', 'pior',
            'fracasso', 'decepção', 'chato', 'entediante', 'lixo',
        ];

        $contadorPos = 0;
        $contadorNeg = 0;

        foreach ($positivas as $palavra) {
            $contadorPos += substr_count($textoMinusculo, $palavra);
        }

        foreach ($negativas as $palavra) {
            $contadorNeg += substr_count($textoMinusculo, $palavra);
        }

        $total = $contadorPos + $contadorNeg;
        $score = $total === 0 ? 0 : (($contadorPos - $contadorNeg) / $total);

        return [
            'positivas' => $contadorPos,
            'negativas' => $contadorNeg,
            'score' => round($score, 2),
            'sentimento' => match (true) {
                $score > 0.3 => 'Positivo',
                $score < -0.3 => 'Negativo',
                default => 'Neutro'
            },
        ];
    }

    /**
     * Extrai estatísticas de texto
     */
    protected function extrairEstatisticas(string $texto): array
    {
        $totalCaracteres = strlen($texto);
        $totalPalavras = str_word_count($texto);
        $totalLinhas = count(array_filter(preg_split('/\n/', $texto) ?? []));
        $totalParagrafos = count(array_filter(preg_split('/\n\n+/', $texto) ?? []));

        // Comprimento médio de palavras
        $comprimentoMedio = $totalPalavras > 0 ? round($totalCaracteres / $totalPalavras, 2) : 0;

        return [
            'caracteres_total' => $totalCaracteres,
            'palavras_total' => $totalPalavras,
            'linhas_total' => $totalLinhas,
            'paragrafos_total' => $totalParagrafos,
            'comprimento_medio_palavra' => $comprimentoMedio,
            'tempo_leitura_minutos' => max(1, (int)($totalPalavras / 200)),
        ];
    }

    /**
     * Avalia qualidade do documento
     */
    protected function avaliarQualidade(string $texto): array
    {
        $scores = [];

        // Score de preenchimento (nenhum documento vazio)
        $scores['preenchimento'] = strlen($texto) > 100 ? 10 : (int)((strlen($texto) / 100) * 10);

        // Score de estrutura (parágrafos bem divididos)
        $paragrafosMedio = count(array_filter(preg_split('/\n\n+/', $texto) ?? []));
        $linhasPorParagrafo = strlen($texto) > 0 ? count(array_filter(preg_split('/\n/', $texto) ?? [])) / max(1, $paragrafosMedio) : 0;
        $scores['estrutura'] = match (true) {
            $linhasPorParagrafo < 3 => 10,
            $linhasPorParagrafo < 10 => 8,
            $linhasPorParagrafo < 20 => 6,
            default => 4
        };

        // Score de variedade (não muitas repetições)
        $palavras = str_word_count(strtolower($texto), 1);
        $palavrasUnicas = count(array_unique($palavras ?? []));
        $variedade = count($palavras ?? []) > 0 ? ($palavrasUnicas / count($palavras)) : 0;
        $scores['variedade'] = max(1, (int)($variedade * 10));

        // Score de ortografia (sem caracteres muito estranhos)
        preg_match_all('/[^a-záéíóúàâãêôõñç\s0-9\.,!?;:\-()]/i', $texto, $matches);
        $caracteresEstranhos = count($matches[0] ?? []);
        $scores['ortografia'] = max(1, 10 - (int)($caracteresEstranhos / 100));

        $mediaScores = (array_sum($scores) / count($scores));

        return [
            'scores_componentes' => $scores,
            'score_geral' => round($mediaScores, 1),
            'status' => match (true) {
                $mediaScores >= 8 => 'Excelente',
                $mediaScores >= 6 => 'Bom',
                $mediaScores >= 4 => 'Aceitável',
                default => 'Precisa melhorias'
            },
        ];
    }

    /**
     * Detecta idioma provável
     */
    public function detectarIdioma(string $caminhoArquivo): array
    {
        $conteudo = file_get_contents($caminhoArquivo);

        if ($conteudo === false) {
            return ['idioma' => 'desconhecido', 'confianca' => 0];
        }

        $textoMinusculo = strtolower($conteudo);

        // Palavras-chave por idioma
        $marcadores = [
            'português' => ['o', 'que', 'de', 'para', 'com', 'por', 'em', 'são'],
            'inglês' => ['the', 'and', 'to', 'of', 'in', 'is', 'that', 'for'],
            'espanhol' => ['el', 'la', 'de', 'que', 'y', 'por', 'se', 'en'],
            'francês' => ['le', 'de', 'et', 'à', 'en', 'les', 'un', 'que'],
        ];

        $scores = [];

        foreach ($marcadores as $idioma => $palavras) {
            $scores[$idioma] = 0;
            foreach ($palavras as $palavra) {
                $scores[$idioma] += substr_count($textoMinusculo, $palavra);
            }
        }

        arsort($scores);
        $idiomaDetectado = array_key_first($scores) ?? 'desconhecido';
        $confianca = array_values($scores)[0] ?? 0;

        // Normalizar confiança (0-1)
        $confiancaNormalizada = min(1, $confianca / 50);

        return [
            'idioma' => $idiomaDetectado,
            'confianca' => round($confiancaNormalizada, 2),
            'scores_candidatos' => array_slice($scores, 0, 3),
        ];
    }

    /**
     * Gera resumo automático
     */
    public function gerarResumoAutomatico(string $caminhoArquivo, int $sentenças = 3): array
    {
        $conteudo = file_get_contents($caminhoArquivo);

        if ($conteudo === false) {
            return ['erro' => 'Falha ao ler arquivo', 'resumo' => ''];
        }

        // Dividir em sentenças
        $sentenças_array = array_filter(
            preg_split('/[.!?]+/', $conteudo) ?? [],
            fn($s) => strlen(trim($s)) > 10
        );

        if (count($sentenças_array) <= $sentenças) {
            return [
                'resumo' => $conteudo,
                'sentencas_selecionadas' => count($sentenças_array),
                'original' => count($sentenças_array),
            ];
        }

        // Selecionar sentenças principais (primeiras de cada parágrafo)
        $paragrafos = array_filter(
            preg_split('/\n\n+/', $conteudo) ?? [],
            fn($p) => strlen(trim($p)) > 0
        );

        $resumoSentenças = [];
        foreach ($paragrafos as $paragrafo) {
            $primeiraSetença = array_values(
                array_filter(
                    preg_split('/[.!?]+/', $paragrafo) ?? [],
                    fn($s) => strlen(trim($s)) > 10
                )
            )[0] ?? null;

            if ($primeiraSetença && count($resumoSentenças) < $sentenças) {
                $resumoSentenças[] = trim($primeiraSetença) . '.';
            }
        }

        return [
            'resumo' => implode(' ', $resumoSentenças),
            'sentencas_selecionadas' => count($resumoSentenças),
            'original' => count($sentenças_array),
            'reducao_percentual' => round((1 - count($resumoSentenças) / count($sentenças_array)) * 100, 1),
        ];
    }

    /**
     * Analisa múltiplos documentos
     */
    public function analisarMultiplos(array $caminhos): array
    {
        $resultados = [];

        foreach ($caminhos as $caminho) {
            try {
                $resultados[] = [
                    'sucesso' => true,
                    'caminho' => $caminho,
                    'analise' => $this->analisar($caminho),
                ];
            } catch (\Throwable $e) {
                $resultados[] = [
                    'sucesso' => false,
                    'caminho' => $caminho,
                    'erro' => $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }
}
