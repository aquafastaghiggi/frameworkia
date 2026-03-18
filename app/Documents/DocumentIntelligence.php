<?php

declare(strict_types=1);

namespace App\Documents;

class DocumentIntelligence
{
    protected array $stopWords = [
        'de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas', 'e', 'o', 'a', 'os', 'as',
        'que', 'para', 'por', 'com', 'como', 'uma', 'um', 'se', 'ao', 'à', 'às', 'é', 'mais', 'ou',
    ];

    public function summarize(string $text, int $maxChars = 600): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return 'Sem conteúdo textual suficiente para gerar resumo.';
        }

        $sentences = preg_split('/(?<=[.!?\n])\s+/', $text);

        if (!$sentences || empty($sentences[0])) {
            return mb_substr($text, 0, $maxChars) . (mb_strlen($text) > $maxChars ? '...' : '');
        }

        $summary = '';
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            if (mb_strlen($summary . ' ' . $sentence) > $maxChars) {
                break;
            }

            $summary .= ($summary === '' ? '' : ' ') . $sentence;
        }

        if ($summary === '') {
            $summary = mb_substr($text, 0, $maxChars);
        }

        return mb_strlen($summary) > $maxChars ? mb_substr($summary, 0, $maxChars) . '...' : $summary;
    }

    public function chunk(string $text, int $chunkSize = 1200): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; $offset += $chunkSize) {
            $chunk = mb_substr($text, $offset, $chunkSize);
            if (trim($chunk) !== '') {
                $chunks[] = trim($chunk);
            }
        }

        return $chunks;
    }

    public function extractKeywords(string $text, int $limit = 5): array
    {
        $text = mb_strtolower(strip_tags($text));
        $words = preg_split('/\W+/u', $text);

        $freq = [];
        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '' || mb_strlen($word) < 3 || in_array($word, $this->stopWords, true)) {
                continue;
            }
            $freq[$word] = ($freq[$word] ?? 0) + 1;
        }

        arsort($freq);
        return array_slice(array_keys($freq), 0, $limit);
    }

    public function insightData(string $text, array $context = []): array
    {
        return [
            'length' => mb_strlen($text),
            'keywords' => $this->extractKeywords($text),
            'context_hint' => array_filter([
                $context['type'] ?? null,
                $context['file_name'] ?? null,
            ]),
        ];
    }
}
