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

    protected DocumentIntelligence $intelligence;

    public function __construct()
    {
        $this->intelligence = new DocumentIntelligence();
        $this->readers = [
            new TextReader(),
            new SpreadsheetReader(),
            new PdfReader(),
        ];
    }

    public function read(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileName = basename($filePath);

        foreach ($this->readers as $reader) {
            if ($reader->supports($extension)) {
                $data = $reader->read($filePath);
                $data['file_name'] = $fileName;
                $data['file_path'] = $filePath;

                $textSource = (string) ($data['full_text'] ?? $data['summary'] ?? '');
                $data['summary'] = $this->intelligence->summarize($textSource);
                $data['chunks'] = $this->intelligence->chunk($textSource);
                $data['insights'] = $this->intelligence->insightData($textSource, [
                    'type' => $data['type'] ?? '',
                    'file_name' => $fileName,
                ]);

                $this->addToIndex($data);

                return $data;
            }
        }

        throw new RuntimeException('Nenhum leitor disponível para este tipo de arquivo: ' . $extension);
    }

    protected function addToIndex(array $document): void
    {
        if (!isset($_SESSION['document_index'])) {
            $_SESSION['document_index'] = [];
        }

        $path = $document['file_path'];
        $_SESSION['document_index'][$path] = [
            'file_name' => $document['file_name'],
            'type' => $document['type'] ?? 'unknown',
            'summary' => $document['summary'],
            'chunks' => $document['chunks'] ?? [],
            'insights' => $document['insights'] ?? [],
            'indexed_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function getIndex(): array
    {
        return $_SESSION['document_index'] ?? [];
    }

    public function clearIndex(): void
    {
        $_SESSION['document_index'] = [];
    }
}
