<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfReader implements DocumentReaderInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'pdf';
    }

    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Arquivo PDF não encontrado: ' . $filePath);
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            
            // Limpeza básica de espaços extras
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            if ($text === '') {
                return [
                    'type' => 'pdf',
                    'summary' => 'O PDF foi lido, mas nenhum texto foi extraído (pode ser um PDF de imagem/escaneado).',
                    'full_text' => '',
                    'metadata' => $pdf->getDetails()
                ];
            }

            return [
                'type' => 'pdf',
                'summary' => mb_substr($text, 0, 1000) . (mb_strlen($text) > 1000 ? '...' : ''),
                'full_text' => $text,
                'metadata' => $pdf->getDetails()
            ];
        } catch (Throwable $e) {
            throw new RuntimeException('Erro ao processar o PDF: ' . $e->getMessage());
        }
    }
}
