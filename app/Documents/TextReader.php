<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;

class TextReader implements DocumentReaderInterface
{
    protected array $extensions = ['txt', 'md', 'php', 'js', 'css', 'html', 'json', 'sql', 'xml', 'yaml', 'yml'];

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), $this->extensions, true);
    }

    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Arquivo de texto não encontrado: ' . $filePath);
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o arquivo de texto.');
        }

        $text = trim($content);

        return [
            'type' => 'text',
            'summary' => mb_substr($text, 0, 1000) . (mb_strlen($text) > 1000 ? '...' : ''),
            'full_text' => $text,
        ];
    }
}
