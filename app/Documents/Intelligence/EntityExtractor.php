<?php

declare(strict_types=1);

namespace App\Documents\Intelligence;

class EntityExtractor
{
    /**
     * Extrai entidades nomeadas do texto
     */
    public function extrairEntidades(string $texto): array
    {
        return [
            'nomes_pessoas' => $this->extrairNomes($texto),
            'locais' => $this->extrairLocais($texto),
            'datas' => $this->extrairDatas($texto),
            'emails' => $this->extrairEmails($texto),
            'urls' => $this->extrairUrls($texto),
            'numeros' => $this->extrairNumeros($texto),
            'hashtags' => $this->extrairHashtags($texto),
        ];
    }

    /**
     * Extrai nomes próprios (palavras capitalizadas consecutivas)
     */
    protected function extrairNomes(string $texto): array
    {
        // Padrão: palavras capitalizadas no início de sentença ou após ponto
        preg_match_all('/(?:^|\.\s+|,\s+|\n)([A-Z][a-záéíóúàâãêôõ]+(?:\s+[A-Z][a-záéíóúàâãêôõ]+)*)/m', $texto, $matches);

        $nomes = array_unique($matches[1] ?? []);
        return array_filter($nomes, fn($n) => strlen($n) > 2);
    }

    /**
     * Extrai possíveis locais (capitalizados, geralmente cidades/países)
     */
    protected function extrairLocais(string $texto): array
    {
        // Padrão: "em [Cidade]", "de [País]", "em [Estado]"
        preg_match_all('/\b(?:em|de|à|em|para|desde|até)\s+([A-Z][a-záéíóúàâãêôõ]+(?:\s+[A-Z][a-záéíóúàâãêôõ]+)?)/i', $texto, $matches);

        $locais = array_unique($matches[1] ?? []);
        return array_filter($locais, fn($l) => strlen($l) > 3);
    }

    /**
     * Extrai datas em diversos formatos
     */
    protected function extrairDatas(string $texto): array
    {
        $datas = [];

        // Formato: DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY
        preg_match_all('/\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4})\b/', $texto, $matches);
        $datas = array_merge($datas, $matches[1] ?? []);

        // Formato: DD de [mês] de YYYY
        preg_match_all('/\b(\d{1,2})\s+de\s+(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)\s+de\s+(\d{4})/i', $texto, $matches);
        for ($i = 0; $i < count($matches[0] ?? []); $i++) {
            $datas[] = $matches[0][$i];
        }

        // Formato: YYYY-MM-DD
        preg_match_all('/\b(\d{4}-\d{1,2}-\d{1,2})\b/', $texto, $matches);
        $datas = array_merge($datas, $matches[1] ?? []);

        return array_unique($datas);
    }

    /**
     * Extrai endereços de email
     */
    protected function extrairEmails(string $texto): array
    {
        preg_match_all('/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/', $texto, $matches);
        return array_unique($matches[0] ?? []);
    }

    /**
     * Extrai URLs
     */
    protected function extrairUrls(string $texto): array
    {
        preg_match_all('/https?:\/\/[^\s]+/', $texto, $matches);
        return array_unique($matches[0] ?? []);
    }

    /**
     * Extrai números (valores, quantidades)
     */
    protected function extrairNumeros(string $texto): array
    {
        preg_match_all('/\b\d+(?:[.,]\d+)?\b/', $texto, $matches);

        $numeros = array_unique($matches[0] ?? []);
        // Filtrar muito pequenos ou duplicados
        return array_filter($numeros, fn($n) => $n !== '0' && $n !== '1');
    }

    /**
     * Extrai hashtags
     */
    protected function extrairHashtags(string $texto): array
    {
        preg_match_all('/#[\wáéíóúàâãêôõ]+/u', $texto, $matches);
        return array_unique($matches[0] ?? []);
    }

    /**
     * Identifica padrões de tabelas em texto
     */
    public function extrairEstruturaTabulares(string $texto): array
    {
        // Detectar linhas com separadores consistentes (|, -, etc)
        $linhas = explode("\n", $texto);
        $tabelasDetectadas = [];
        $tabelaAtual = [];

        foreach ($linhas as $linha) {
            if (preg_match('/[|].*[|]/', $linha)) {
                // Linha de tabela
                $colunas = array_map('trim', explode('|', $linha));
                $colunas = array_filter($colunas, fn($c) => $c !== '');
                if (count($colunas) > 1) {
                    $tabelaAtual[] = $colunas;
                }
            } elseif (!empty($tabelaAtual) && trim($linha) === '') {
                // Fim de tabela
                $tabelasDetectadas[] = $tabelaAtual;
                $tabelaAtual = [];
            }
        }

        if (!empty($tabelaAtual)) {
            $tabelasDetectadas[] = $tabelaAtual;
        }

        return [
            'total_tabelas' => count($tabelasDetectadas),
            'tabelas' => array_slice($tabelasDetectadas, 0, 5), // Primeiras 5
        ];
    }

    /**
     * Identifica listas (numeradas ou com bullets)
     */
    public function extrairListas(string $texto): array
    {
        $linhas = explode("\n", $texto);
        $listasDetectadas = [];
        $listaAtual = [];
        $tipoLista = null;

        foreach ($linhas as $linha) {
            // Detectar lista com bullets
            if (preg_match('/^[\s]*[-•*]\s+(.+)/', $linha, $matches)) {
                if ($tipoLista !== 'bullet' && !empty($listaAtual)) {
                    $listasDetectadas[] = [
                        'tipo' => $tipoLista,
                        'itens' => $listaAtual,
                    ];
                    $listaAtual = [];
                }
                $tipoLista = 'bullet';
                $listaAtual[] = trim($matches[1]);
            } // Detectar lista numerada
            elseif (preg_match('/^[\s]*(\d+)\.\s+(.+)/', $linha, $matches)) {
                if ($tipoLista !== 'numerada' && !empty($listaAtual)) {
                    $listasDetectadas[] = [
                        'tipo' => $tipoLista,
                        'itens' => $listaAtual,
                    ];
                    $listaAtual = [];
                }
                $tipoLista = 'numerada';
                $listaAtual[] = trim($matches[2]);
            } elseif (!empty($listaAtual) && trim($linha) === '') {
                // Fim de lista
                $listasDetectadas[] = [
                    'tipo' => $tipoLista,
                    'itens' => $listaAtual,
                ];
                $listaAtual = [];
                $tipoLista = null;
            }
        }

        if (!empty($listaAtual)) {
            $listasDetectadas[] = [
                'tipo' => $tipoLista,
                'itens' => $listaAtual,
            ];
        }

        return [
            'total_listas' => count($listasDetectadas),
            'listas' => $listasDetectadas,
        ];
    }

    /**
     * Identifica títulos e hierarquia
     */
    public function extrairHierarquia(string $texto): array
    {
        $linhas = explode("\n", $texto);
        $hierarquia = [];

        foreach ($linhas as $linha) {
            // Markdown headers (# ## ### etc)
            if (preg_match('/^(#{1,6})\s+(.+)$/', $linha, $matches)) {
                $nivel = strlen($matches[1]);
                $titulo = trim($matches[2]);
                $hierarquia[] = [
                    'nivel' => $nivel,
                    'titulo' => $titulo,
                ];
            } // Títulos em caixa alta ou com underline
            elseif (preg_match('/^[A-Z]{2,}[A-Z\s]{3,}$/', trim($linha))) {
                $hierarquia[] = [
                    'nivel' => 1,
                    'titulo' => trim($linha),
                    'tipo' => 'maiusculas',
                ];
            }
        }

        return [
            'total_secoes' => count($hierarquia),
            'hierarquia' => $hierarquia,
        ];
    }

    /**
     * Extrai tópicos principais (palavras-chave com frequência)
     */
    public function extrairTopicos(string $texto, int $limite = 10): array
    {
        // Remover stopwords
        $stopwords = [
            'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas',
            'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na',
            'e', 'ou', 'por', 'para', 'com', 'sem', 'à', 'ao',
            'é', 'são', 'ser', 'está', 'estão', 'tem', 'têm',
            'mais', 'menos', 'muito', 'pouco', 'todo', 'nada',
        ];

        // Extrair palavras
        preg_match_all('/\b[a-záéíóúàâãêôõ]+\b/u', strtolower($texto), $matches);
        $palavras = array_filter($matches[0] ?? [], fn($p) => !in_array($p, $stopwords) && strlen($p) > 3);

        // Contar frequência
        $frequencia = array_count_values($palavras);
        arsort($frequencia);

        $topicos = [];
        foreach (array_slice($frequencia, 0, $limite) as $palavra => $freq) {
            $topicos[] = [
                'palavra' => $palavra,
                'frequencia' => $freq,
                'relevancia' => min(10, (int)($freq / 2)), // Score 1-10
            ];
        }

        return [
            'total_topicos' => count($topicos),
            'topicos' => $topicos,
        ];
    }
}
