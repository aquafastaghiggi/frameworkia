<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;

class TextReader implements DocumentReaderInterface
{
    protected array $extensions = [
        'txt',
        'md',
        'json',
        'csv',
        'log',
    ];

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), $this->extensions, true);
    }

    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Arquivo de texto não encontrado.');
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o arquivo de texto.');
        }

        return [
            'type' => 'text',
            'summary' => mb_substr($content, 0, 4000),
            'full_text' => $content,
        ];
    }
}