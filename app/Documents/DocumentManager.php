<?php

declare(strict_types=1);

namespace App\Documents;

use RuntimeException;

class DocumentManager
{
    /**
     * @var DocumentReaderInterface[]
     */
    protected array $readers;

    public function __construct()
    {
        $this->readers = [
            new TextReader(),
            new SpreadsheetReader(),
            new PdfReader(),
        ];
    }

    public function read(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        foreach ($this->readers as $reader) {
            if ($reader->supports($extension)) {
                return $reader->read($filePath);
            }
        }

        throw new RuntimeException('Nenhum leitor disponível para este tipo de arquivo.');
    }
}