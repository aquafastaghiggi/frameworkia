<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;

class PdfReader implements DocumentReaderInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'pdf';
    }

    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('PDF não encontrado.');
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o PDF.');
        }

        $text = $this->extractBasicText($content);
        $text = trim($text);

        if ($text === '') {
            return [
                'type' => 'pdf',
                'summary' => 'Não foi possível extrair texto útil deste PDF com o leitor básico atual.',
                'full_text' => '',
            ];
        }

        return [
            'type' => 'pdf',
            'summary' => mb_substr($text, 0, 4000),
            'full_text' => $text,
        ];
    }

    protected function extractBasicText(string $binary): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $binary);
        $text = preg_replace('/[^(\x20-\x7E|\x0A|\x0D|\x09)]/', ' ', (string) $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);

        return trim((string) $text);
    }
}